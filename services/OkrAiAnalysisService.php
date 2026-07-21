<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/SobhanApiClient.php';
require_once __DIR__ . '/../lib/OkrService.php';

final class OkrAiAnalysisService
{
    public const TYPES = [
        'executive_summary' => 'خلاصه مدیریتی',
        'risk_detection' => 'تشخیص اهداف در معرض خطر',
        'corrective_actions' => 'پیشنهاد اقدام اصلاحی',
        'okr_improvement' => 'پیشنهاد Objective و KR',
        'cycle_comparison' => 'مقایسه عملکرد دوره‌ها',
    ];

    public static function canRun(array $objective, ?array $actor = null): bool
    {
        $actor ??= Auth::user();
        if (!$actor || !OkrService::canViewObjective($objective, $actor)) return false;
        return OrgAccess::isAdmin($actor)
            || Auth::can('okr.ai', 'create')
            || Auth::can('okr.ai')
            || (Auth::can('use_ai_assistant') && OkrService::canManageObjective($objective, $actor));
    }

    public static function history(int $objectiveId, int $limit = 10): array
    {
        $objective = OkrService::objective($objectiveId);
        if (!$objective) return [];
        $limit = max(1, min(30, $limit));
        return Database::fetchAll(
            "SELECT a.*,u.name requester_name FROM okr_ai_analyses a JOIN users u ON u.id=a.requested_by WHERE a.objective_id=? ORDER BY a.id DESC LIMIT {$limit}",
            [$objectiveId]
        );
    }

    public static function run(int $objectiveId, string $type, int $actorId): array
    {
        $objective = OkrService::objective($objectiveId);
        $actor = Auth::user();
        if (!$objective || !$actor || (int)$actor['id'] !== $actorId || !self::canRun($objective, $actor)) {
            throw new DomainException('برای اجرای تحلیل هوشمند این هدف دسترسی ندارید.');
        }
        if (!isset(self::TYPES[$type])) throw new InvalidArgumentException('نوع تحلیل هوشمند معتبر نیست.');
        Auth::start();
        $rateKey = 'okr_ai_last_run_' . $objectiveId;
        if (time() - (int)($_SESSION[$rateKey] ?? 0) < 3) {
            throw new DomainException('لطفاً چند ثانیه صبر کنید و دوباره تحلیل را اجرا کنید.');
        }
        $_SESSION[$rateKey] = time();

        $context = self::buildContext($objectiveId);
        $deterministic = self::deterministicAnalysis($type, $context);
        $source = 'deterministic';
        $status = 'success';
        $error = null;
        $result = $deterministic;

        if (setting('sobhan_api_enabled', '0') === '1') {
            $payload = [
                'module' => 'okr',
                'analysis_type' => $type,
                'response_schema' => self::responseSchema(),
                'context' => $context,
                'instruction' => self::instruction($type),
            ];
            $payload['question'] = $payload['instruction'] . "\n" . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $api = (new SobhanApiClient())->post('/ai/ask', $payload);
            $answer = ai_answer_payload_from_result($api, $payload['question'], '');
            if ($api['ok'] && trim((string)$answer['answer']) !== '') {
                $parsed = self::parseModelResult((string)$answer['answer']);
                if ($parsed) {
                    $result = $parsed;
                    $source = 'sobhan_ai';
                } else {
                    $result['executive_summary'] .= "\n\nتحلیل تکمیلی مدل:\n" . self::cleanText((string)$answer['answer'], 6000);
                    $source = 'sobhan_ai_text';
                }
            } else {
                $status = 'fallback';
                $error = self::cleanText((string)($api['error']['message_fa'] ?? 'سرویس هوش مصنوعی در دسترس نبود.'), 500);
            }
        }

        $responseText = self::formatResult($result);
        Database::execute(
            'INSERT INTO okr_ai_analyses(objective_id,requested_by,analysis_type,context_summary_json,result_json,response_text,source,status,error_message,created_at) VALUES (?,?,?,?,?,?,?,?,?,NOW())',
            [$objectiveId,$actorId,$type,self::json(self::contextSummary($context)),self::json($result),$responseText,$source,$status,$error]
        );
        $analysisId = (int)Database::lastInsertId();
        OkrService::audit($objectiveId, null, $actorId, 'okr_ai_analysis_created', null, ['analysis_id'=>$analysisId,'analysis_type'=>$type,'source'=>$source], 'تحلیل خواندنی؛ بدون تغییر داده');
        return ['id'=>$analysisId,'type'=>$type,'type_label'=>self::TYPES[$type],'source'=>$source,'status'=>$status,'result'=>$result,'response_text'=>$responseText,'error_message'=>$error];
    }

    public static function decodeResult(array $analysis): array
    {
        $decoded = json_decode((string)($analysis['result_json'] ?? ''), true);
        return is_array($decoded) ? array_replace(self::responseSchema(), $decoded) : self::responseSchema();
    }

    private static function buildContext(int $objectiveId): array
    {
        $data = OkrService::detail($objectiveId);
        $objective = $data['objective'];
        $previous = Database::fetchAll(
            'SELECT o.id,o.title,o.status,o.progress_score,o.health_status,o.start_date,o.due_date,c.title cycle_title
             FROM okr_objectives o JOIN okr_cycles c ON c.id=o.cycle_id
             WHERE o.owner_user_id=? AND o.id<>? AND o.created_at<? ORDER BY o.created_at DESC LIMIT 5',
            [(int)$objective['owner_user_id'],$objectiveId,$objective['created_at']]
        );
        return [
            'objective' => [
                'id'=>(int)$objective['id'],'title'=>$objective['title'],'description'=>$objective['description'],
                'type'=>$objective['okr_type'],'priority'=>$objective['priority'],'status'=>$objective['status'],
                'health'=>$objective['health_status'],'progress'=>(float)$objective['progress_score'],
                'start_date'=>$objective['start_date'],'due_date'=>$objective['due_date'],'cycle'=>$objective['cycle_title'],
                'owner_role'=>$objective['owner_role_title'] ?: $objective['owner_role_code'],
                'org_unit'=>$objective['org_unit_title'],'sales_line'=>$objective['sales_line'],
            ],
            'key_results' => array_map(static fn(array $kr): array => [
                'title'=>$kr['title'],'baseline'=>(float)$kr['baseline_value'],'current'=>(float)$kr['current_value'],
                'target'=>(float)$kr['target_value'],'progress'=>(float)$kr['progress_percent'],'weight'=>(float)$kr['weight'],
                'health'=>$kr['health_status'],'due_date'=>$kr['due_date'],'source'=>$kr['data_source_type'],
                'last_checkin_at'=>$kr['last_checkin_at'],'last_calculated_at'=>$kr['last_calculated_at'],
            ], array_slice($data['krs'], 0, 30)),
            'recent_checkins' => array_map(static fn(array $row): array => [
                'key_result'=>$row['key_result_title'],'progress'=>(float)$row['progress_percent'],'health'=>$row['health_status'],
                'confidence'=>$row['confidence_level'],'blocker'=>$row['blocker_text'],'next_action'=>$row['next_action'],'created_at'=>$row['created_at'],
            ], array_slice($data['checkins'], 0, 15)),
            'initiatives' => array_map(static fn(array $row): array => [
                'title'=>$row['title'],'priority'=>$row['priority'],'status'=>$row['status'],'due_date'=>$row['due_date'],
                'planner_status'=>$row['planner_status'],'planner_progress'=>(float)($row['planner_progress'] ?? 0),
            ], array_slice($data['initiatives'], 0, 20)),
            'alignments' => array_map(static fn(array $row): array => [
                'parent_title'=>$row['parent_title'],'type'=>$row['alignment_type'],'weight'=>(float)$row['contribution_weight'],'parent_progress'=>(float)$row['parent_progress'],
            ], array_slice($data['alignments'], 0, 10)),
            'previous_cycles' => $previous,
            'generated_at' => date('Y-m-d H:i:s'),
        ];
    }

    private static function deterministicAnalysis(string $type, array $context): array
    {
        $objective = $context['objective'];
        $krs = $context['key_results'];
        $risks = [];
        $actions = [];
        $strengths = [];
        $warnings = [];
        $today = strtotime(date('Y-m-d'));
        foreach ($krs as $kr) {
            $days = (int)floor((strtotime((string)$kr['due_date']) - $today) / 86400);
            if ($kr['health'] === 'off_track' || ($kr['progress'] < 50 && $days <= 14)) $risks[] = 'نتیجه «'.$kr['title'].'» با پیشرفت '.$kr['progress'].'٪ و '.$days.' روز تا مهلت، پرریسک است.';
            elseif ($kr['health'] === 'at_risk') $risks[] = 'نتیجه «'.$kr['title'].'» در وضعیت نیازمند توجه قرار دارد.';
            if ($kr['progress'] >= 90) $strengths[] = 'نتیجه «'.$kr['title'].'» به '.$kr['progress'].'٪ رسیده است.';
            if (!$kr['last_checkin_at'] && $kr['source'] === 'manual') $warnings[] = 'برای «'.$kr['title'].'» هنوز Check-in ثبت نشده است.';
        }
        foreach (array_slice($risks, 0, 5) as $risk) $actions[] = 'برای '.$risk.' یک اقدام اصلاحی با مسئول و مهلت کوتاه ثبت شود.';
        if (!$actions) $actions[] = 'ریتم Check-in فعلی حفظ و موانع هر KR در جلسه بعد مرور شود.';
        $summary = 'هدف «'.$objective['title'].'» با پیشرفت '.$objective['progress'].'٪ در وضعیت «'.(OkrService::HEALTH_STATUSES[$objective['health']] ?? $objective['health']).'» قرار دارد.';
        if ($type === 'cycle_comparison') {
            $previous = $context['previous_cycles'];
            $summary .= $previous
                ? ' میانگین امتیاز اهداف قبلی همین مالک '.round(array_sum(array_column($previous,'progress_score')) / count($previous), 1).'٪ بوده است.'
                : ' سابقه کافی برای مقایسه دوره‌ای وجود ندارد.';
        }
        $suggestedObjectives = [];
        $suggestedKrs = [];
        if ($type === 'okr_improvement') {
            $suggestedObjectives[] = 'تمرکز هدف بر یک نتیجه کسب‌وکاری روشن و قابل سنجش';
            foreach (array_slice($krs, 0, 3) as $kr) {
                $suggestedKrs[] = 'بازبینی «'.$kr['title'].'» با مبنای '.$kr['baseline'].'، هدف '.$kr['target'].' و مالک مشخص';
            }
            if (!$suggestedKrs) $suggestedKrs[] = 'افزودن ۲ تا ۴ KR عددی با مجموع وزن ۱۰۰٪';
        }
        return [
            'executive_summary'=>$summary,
            'strengths'=>array_slice($strengths,0,5),
            'risks'=>array_slice($risks,0,7),
            'recommended_actions'=>array_slice($actions,0,7),
            'suggested_objectives'=>$suggestedObjectives,
            'suggested_key_results'=>$suggestedKrs,
            'data_warnings'=>array_slice($warnings,0,5),
        ];
    }

    private static function instruction(string $type): string
    {
        return 'شما تحلیلگر OKR شرکت سبحان هستید. نوع تحلیل: '.self::TYPES[$type].'. فقط داده ساختاریافته context را تحلیل کن. هیچ عددی را اختراع نکن، هیچ SQL یا دستور اجرایی نساز و پیشنهادها را به‌عنوان پیشنهاد غیرالزام‌آور بیان کن. پاسخ فقط JSON معتبر مطابق response_schema، فارسی، بدون markdown و کدبلاک باشد.';
    }

    private static function responseSchema(): array
    {
        return ['executive_summary'=>'','strengths'=>[],'risks'=>[],'recommended_actions'=>[],'suggested_objectives'=>[],'suggested_key_results'=>[],'data_warnings'=>[]];
    }

    private static function parseModelResult(string $answer): ?array
    {
        $clean = strip_ai_markdown_fences(trim($answer));
        $decoded = json_decode($clean, true);
        if (!is_array($decoded)) return null;
        if (isset($decoded['data']) && is_array($decoded['data'])) $decoded = $decoded['data'];
        $schema = self::responseSchema();
        $result = [];
        foreach ($schema as $key => $default) {
            if (is_array($default)) {
                $values = is_array($decoded[$key] ?? null) ? $decoded[$key] : [];
                $result[$key] = array_values(array_filter(array_map(static fn($value): string => self::cleanText((string)$value, 500), array_slice($values,0,10))));
            } else {
                $result[$key] = self::cleanText((string)($decoded[$key] ?? ''), 6000);
            }
        }
        return $result['executive_summary'] !== '' ? $result : null;
    }

    private static function formatResult(array $result): string
    {
        $sections = [$result['executive_summary'] ?? ''];
        $labels = ['strengths'=>'نقاط قوت','risks'=>'ریسک‌ها','recommended_actions'=>'اقدامات پیشنهادی','suggested_objectives'=>'Objectiveهای پیشنهادی','suggested_key_results'=>'KRهای پیشنهادی','data_warnings'=>'هشدار کیفیت داده'];
        foreach ($labels as $key=>$label) {
            if (empty($result[$key]) || !is_array($result[$key])) continue;
            $sections[] = $label . ":\n- " . implode("\n- ", $result[$key]);
        }
        return trim(implode("\n\n", array_filter($sections)));
    }

    private static function contextSummary(array $context): array
    {
        return [
            'objective_id'=>$context['objective']['id'],
            'key_result_count'=>count($context['key_results']),
            'checkin_count'=>count($context['recent_checkins']),
            'initiative_count'=>count($context['initiatives']),
            'alignment_count'=>count($context['alignments']),
            'previous_cycle_count'=>count($context['previous_cycles']),
        ];
    }

    private static function cleanText(string $value, int $max): string
    {
        return mb_substr(trim(strip_tags($value)), 0, $max);
    }

    private static function json(mixed $value): ?string
    {
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $json === false ? null : $json;
    }
}
