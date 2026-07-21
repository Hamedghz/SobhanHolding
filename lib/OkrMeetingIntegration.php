<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/ManagementMeetingsRepository.php';
require_once __DIR__ . '/OkrService.php';
require_once __DIR__ . '/NotificationService.php';
require_once __DIR__ . '/../services/WorkPlannerService.php';

final class OkrMeetingIntegration
{
    public static function availableObjectives(array $actor): array
    {
        return array_values(array_filter(OkrService::listObjectives(), static fn(array $objective): bool =>
            OkrService::canManageObjective($objective, $actor)
            && !in_array($objective['status'], ['cancelled', 'completed'], true)
        ));
    }

    public static function keyResults(int $objectiveId): array
    {
        $objective = OkrService::objective($objectiveId);
        if (!$objective || !OkrService::canManageObjective($objective)) return [];
        return Database::fetchAll(
            'SELECT id,title,owner_user_id,due_date,status FROM okr_key_results WHERE objective_id=? AND status<>"cancelled" ORDER BY id',
            [$objectiveId]
        );
    }

    public static function decisionLinks(int $decisionId): array
    {
        $decision = ManagementMeetingsRepository::getDecisionById($decisionId);
        if (!$decision) return [];
        $rows = Database::fetchAll(
            'SELECT l.*,o.title objective_title,o.progress_score,kr.title key_result_title,i.title initiative_title,t.title planner_task_title,u.name creator_name
             FROM okr_decision_links l
             JOIN okr_objectives o ON o.id=l.objective_id
             LEFT JOIN okr_key_results kr ON kr.id=l.key_result_id
             LEFT JOIN okr_initiatives i ON i.id=l.initiative_id
             LEFT JOIN work_planner_tasks t ON t.id=l.planner_task_id
             LEFT JOIN users u ON u.id=l.created_by
             WHERE l.decision_id=? ORDER BY l.id DESC',
            [$decisionId]
        );
        return array_values(array_filter($rows, static fn(array $row): bool =>
            OkrService::objective((int)$row['objective_id']) !== null
        ));
    }

    public static function objectiveLinks(int $objectiveId): array
    {
        $objective = OkrService::objective($objectiveId);
        if (!$objective) return [];
        $rows = Database::fetchAll(
            'SELECT l.*,d.title decision_title,d.followup_status,d.progress_percent decision_progress,d.due_date decision_due_date,m.title meeting_title,kr.title key_result_title
             FROM okr_decision_links l
             JOIN management_decisions d ON d.id=l.decision_id
             JOIN management_meetings m ON m.id=d.meeting_id
             LEFT JOIN okr_key_results kr ON kr.id=l.key_result_id
             WHERE l.objective_id=? ORDER BY l.id DESC',
            [$objectiveId]
        );
        return array_values(array_filter($rows, static fn(array $row): bool =>
            ManagementMeetingsRepository::getDecisionById((int)$row['decision_id']) !== null
        ));
    }

    public static function linkDecision(int $decisionId, array $input, int $actorId): int
    {
        $actor = Auth::user();
        $decision = ManagementMeetingsRepository::getDecisionById($decisionId, $actor);
        if (!$actor || !$decision || !ManagementMeetingsRepository::canEditDecision($decision, $actor)) {
            throw new DomainException('برای اتصال این مصوبه به OKR دسترسی ندارید.');
        }
        $objectiveId = (int)($input['objective_id'] ?? 0);
        $objective = OkrService::objective($objectiveId);
        if (!$objective || !OkrService::canManageObjective($objective, $actor)) {
            throw new DomainException('هدف انتخاب‌شده در محدوده مدیریت شما نیست.');
        }
        $keyResultId = (int)($input['key_result_id'] ?? 0) ?: null;
        $keyResult = null;
        if ($keyResultId) {
            $keyResult = Database::fetch('SELECT * FROM okr_key_results WHERE id=? AND objective_id=? AND status<>"cancelled"', [$keyResultId,$objectiveId]);
            if (!$keyResult) throw new InvalidArgumentException('نتیجه کلیدی انتخاب‌شده متعلق به این هدف نیست.');
        }
        if (Database::fetch('SELECT id FROM okr_decision_links WHERE decision_id=? AND objective_id=? AND ((key_result_id IS NULL AND ? IS NULL) OR key_result_id=?)', [$decisionId,$objectiveId,$keyResultId,$keyResultId])) {
            throw new InvalidArgumentException('این مصوبه قبلاً به هدف یا نتیجه کلیدی انتخاب‌شده متصل شده است.');
        }
        $ownerId = (int)($input['owner_user_id'] ?? $decision['responsible_user_id'] ?? $objective['owner_user_id']);
        if ($ownerId <= 0) $ownerId = (int)$objective['owner_user_id'];
        $allowedOwners = array_map('intval', array_column(OkrService::availableOwners($actor), 'id'));
        if (!in_array($ownerId, $allowedOwners, true)) throw new DomainException('مسئول انتخاب‌شده خارج از دامنه مجاز OKR است.');
        $title = self::text($input['initiative_title'] ?? $decision['title'], 255);
        $description = self::text($input['initiative_description'] ?? $decision['description'], 5000);
        $priority = in_array((string)($input['priority'] ?? $decision['priority']), array_keys(OkrService::PRIORITIES), true)
            ? (string)($input['priority'] ?? $decision['priority']) : 'normal';
        $due = self::date((string)($input['due_date'] ?? $decision['due_date'] ?? $objective['due_date'])) ?: (string)$objective['due_date'];
        if ($due < $objective['start_date'] || $due > $objective['due_date']) {
            throw new InvalidArgumentException('مهلت اقدام باید داخل بازه Objective باشد.');
        }

        $initiativeId = null;
        $taskId = null;
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            if (!empty($input['create_initiative'])) {
                $initiativeId = OkrService::addInitiative($objectiveId, [
                    'key_result_id'=>$keyResultId,
                    'owner_user_id'=>$ownerId,
                    'title'=>$title,
                    'description'=>$description,
                    'priority'=>$priority,
                    'start_date'=>date('Y-m-d'),
                    'due_date'=>$due,
                ], $actorId);
                $initiative = Database::fetch('SELECT planner_task_id FROM okr_initiatives WHERE id=?', [$initiativeId]);
                $taskId = (int)($initiative['planner_task_id'] ?? 0) ?: null;
            } elseif (!empty($input['create_task'])) {
                $taskId = WorkPlannerService::createLinkedTask($actorId, $ownerId, [
                    'title'=>$title,
                    'description'=>$description,
                    'priority'=>$priority,
                    'start_date'=>date('Y-m-d'),
                    'due_date'=>$due,
                ], 'okr', $objectiveId);
                Database::execute('INSERT INTO okr_task_links(objective_id,key_result_id,initiative_id,planner_task_id,created_by,created_at) VALUES (?,?,NULL,?,?,NOW())', [$objectiveId,$keyResultId,$taskId,$actorId]);
            }
            Database::execute(
                'INSERT INTO okr_decision_links(decision_id,objective_id,key_result_id,initiative_id,planner_task_id,link_note,created_by,created_at) VALUES (?,?,?,?,?,?,?,NOW())',
                [$decisionId,$objectiveId,$keyResultId,$initiativeId,$taskId,self::text($input['link_note'] ?? '',500) ?: null,$actorId]
            );
            $linkId = (int)Database::lastInsertId();
            OkrService::audit($objectiveId, $keyResultId, $actorId, 'meeting_decision_linked', null, ['decision_id'=>$decisionId,'initiative_id'=>$initiativeId,'planner_task_id'=>$taskId], 'اتصال مصوبه جلسه به OKR');
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }

        $recipients = array_values(array_unique(array_filter([(int)$objective['owner_user_id'],(int)($keyResult['owner_user_id'] ?? 0),$ownerId])));
        foreach ($recipients as $recipientId) {
            if ($recipientId === $actorId) continue;
            NotificationService::safeNotify(static fn() => NotificationService::create(
                $recipientId,
                'okr_decision_linked',
                'مصوبه جدید به OKR متصل شد',
                $decision['title'],
                '/admin/okr-objective.php?id=' . $objectiveId,
                ['module'=>'okr','related_type'=>'okr_decision_link','related_id'=>$linkId,'priority'=>$priority,'safe_push_body'=>'یک مصوبه مدیریتی به هدف شما متصل شد.']
            ));
        }
        return $linkId;
    }

    private static function text(mixed $value, int $max): string
    {
        return mb_substr(trim((string)$value), 0, $max);
    }

    private static function date(string $value): ?string
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', trim($value));
        return $date && $date->format('Y-m-d') === trim($value) ? trim($value) : null;
    }
}
