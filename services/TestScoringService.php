<?php
require_once __DIR__ . '/../core/Database.php';

class TestScoringService
{
    public function calculate($employeeId, $testId, $answersJson, $assignmentId = null): array
    {
        $employeeId = (int)$employeeId;
        $testId = (int)$testId;
        $assignmentId = $assignmentId ? (int)$assignmentId : null;

        $test = Database::fetch('SELECT * FROM hr_assessment_tests WHERE id=?', [$testId]);
        if (!$test) throw new RuntimeException('test_not_found');

        $questions = Database::fetchAll('SELECT * FROM hr_assessment_questions WHERE test_id=? AND active=1 ORDER BY sort_order,id', [$testId]);
        $dimensions = Database::fetchAll('SELECT dimension_key,dimension_label FROM hr_assessment_dimensions WHERE test_id=? ORDER BY sort_order,id', [$testId]);
        $answers = is_array($answersJson) ? $answersJson : json_decode((string)$answersJson, true);
        if (!is_array($answers)) $answers = [];

        $metrics = $this->calculateMetrics((string)$test['code'], $questions, $answers);
        $dimensionLabels = [];
        foreach ($dimensions as $dimension) $dimensionLabels[(string)$dimension['dimension_key']] = (string)$dimension['dimension_label'];

        $final = $this->generateFinalResult((string)$test['code'], $metrics['normalized'], $dimensionLabels);
        $risk = $this->getRiskLevel((string)$test['code'], $metrics['normalized'], $metrics['overall'], $metrics['template_pending']);
        $profileSummary = $this->profileSummary((string)$test['code'], $metrics['normalized'], $dimensionLabels, $final, $metrics['overall'], $metrics['template_pending']);
        $recommendation = $this->generateRecommendation((string)$test['code'], $metrics['normalized'], $dimensionLabels, $metrics['overall'], $metrics['template_pending']);

        $result = [
            'raw_scores' => $metrics['raw'],
            'max_scores' => $metrics['max'],
            'normalized_scores' => $metrics['normalized'],
            'overall_score' => $metrics['overall'],
            'template_pending_key' => $metrics['template_pending'],
            'final_result' => $final,
            'risk_level' => $risk,
            'profile_summary' => $profileSummary,
            'recommendation' => $recommendation,
        ];

        if ($assignmentId) {
            Database::execute(
                'INSERT INTO hr_assessment_results(assignment_id,employee_id,test_id,raw_answers_json,calculated_scores_json,normalized_scores_json,final_result,risk_level,profile_summary,recommendation_text,calculated_at,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,NOW(),NOW())',
                [
                    $assignmentId,
                    $employeeId,
                    $testId,
                    json_encode($answers, JSON_UNESCAPED_UNICODE),
                    json_encode([
                        'raw_scores' => $metrics['raw'],
                        'max_scores' => $metrics['max'],
                        'overall_score' => $metrics['overall'],
                        'template_pending_key' => $metrics['template_pending'],
                    ], JSON_UNESCAPED_UNICODE),
                    json_encode($metrics['normalized'], JSON_UNESCAPED_UNICODE),
                    $final,
                    $risk,
                    $profileSummary,
                    $recommendation
                ]
            );
            $result['result_id'] = (int)Database::lastInsertId();
        }

        return $result;
    }

    private function calculateMetrics(string $testCode, array $questions, array $answers): array
    {
        $raw = [];
        $max = [];
        $counts = [];
        $correct = 0;
        $correctMax = 0;
        $templatePending = false;

        foreach ($questions as $question) {
            $dimension = (string)($question['dimension_key'] ?? 'general');
            if ($dimension === '') $dimension = 'general';
            $value = $answers[(string)$question['id']] ?? $answers[(int)$question['id']] ?? null;
            if ($value === null || $value === '') continue;

            $type = (string)$question['answer_type'];
            $weight = (float)($question['weight'] ?? 1);
            if (in_array($type, ['choice', 'scenario_choice'], true)) {
                if (!empty($question['correct_answer_json'])) {
                    $decoded = json_decode((string)$question['correct_answer_json'], true);
                    $accepted = is_array($decoded) ? array_map('strval', $decoded) : [(string)$decoded];
                    $isCorrect = in_array((string)$value, $accepted, true);
                    $raw[$dimension] = ($raw[$dimension] ?? 0) + ($isCorrect ? 1 : 0) * $weight;
                    $max[$dimension] = ($max[$dimension] ?? 0) + (1 * $weight);
                    $correct += $isCorrect ? 1 : 0;
                    $correctMax++;
                } else {
                    $templatePending = true;
                }
                continue;
            }

            if ($type === 'text') continue;
            $itemMax = $this->answerMax($type);
            $numeric = max(1, min($itemMax, (float)$value));
            if ((int)($question['reverse_score'] ?? 0) === 1) $numeric = $this->applyReverseScore($numeric, $type);
            $raw[$dimension] = ($raw[$dimension] ?? 0) + ($numeric * $weight);
            $max[$dimension] = ($max[$dimension] ?? 0) + ($itemMax * $weight);
            $counts[$dimension] = ($counts[$dimension] ?? 0) + 1;
        }

        $normalized = [];
        foreach ($max as $dimension => $dimensionMax) {
            if ($dimensionMax <= 0) {
                $normalized[$dimension] = 0;
                continue;
            }
            if ($this->isLikertTest($testCode)) {
                $avg = ($raw[$dimension] / max(1, (float)($counts[$dimension] ?? 1)));
                $normalized[$dimension] = round((($avg - 1) / 4) * 100, 2);
            } else {
                $normalized[$dimension] = round((($raw[$dimension] ?? 0) / $dimensionMax) * 100, 2);
            }
            $normalized[$dimension] = max(0, min(100, $normalized[$dimension]));
        }

        $overall = 0;
        if ($templatePending && !$correctMax) {
            $overall = null;
        } elseif (!$this->isLikertTest($testCode)) {
            $overall = $correctMax > 0 ? round(($correct / $correctMax) * 100, 2) : 0;
        } elseif ($normalized) {
            $overall = round(array_sum($normalized) / count($normalized), 2);
        }

        return [
            'raw' => $raw,
            'max' => $max,
            'normalized' => $normalized,
            'overall' => $overall,
            'template_pending' => $templatePending && !$correctMax,
        ];
    }

    private function isLikertTest(string $testCode): bool
    {
        return !in_array($testCode, ['PRODUCT_KNOWLEDGE_DISTRIBUTION', 'SALES_CATALOG_KNOWLEDGE', 'SERVICE_STANDARDS', 'HEALTH_SAFETY', 'UPSELL_READINESS'], true);
    }

    public function applyReverseScore($value, $answerType)
    {
        $max = $this->answerMax((string)$answerType);
        return ($max + 1) - (float)$value;
    }

    private function answerMax(string $type): int
    {
        return $type === 'scale_1_7' ? 7 : 5;
    }

    private function generateFinalResult(string $testCode, array $scores, array $labels): string
    {
        if ($testCode === 'DISC_ORG') {
            $sorted = $scores;
            arsort($sorted);
            $keys = array_keys($sorted);
            return 'سبک اصلی: ' . ($labels[$keys[0]] ?? $keys[0] ?? '-') . ' | سبک ثانویه: ' . ($labels[$keys[1]] ?? $keys[1] ?? '-');
        }
        if ($testCode === 'MBTI_ORG_INFORMAL') {
            return $this->mbtiSummary($scores);
        }
        if ($testCode === 'EQ_ORG') return $this->bandedSummary($scores, $labels, [50 => 'نیازمند تقویت', 70 => 'قابل قبول', 85 => 'مناسب'], 'نقطه قوت');
        if ($testCode === 'JOB_SATISFACTION_ORG') return $this->overallBand((float)(array_sum($scores) / max(1, count($scores))), [45 => 'پایین', 70 => 'متوسط / نیازمند بررسی'], 'بالا');
        if ($testCode === 'BURNOUT_ORG') return $this->overallBand((float)(array_sum($scores) / max(1, count($scores))), [40 => 'پایین', 60 => 'نیازمند پایش', 80 => 'بالا'], 'نیازمند پیگیری فوری منابع انسانی');
        if (in_array($testCode, ['PRODUCT_KNOWLEDGE_DISTRIBUTION', 'SALES_CATALOG_KNOWLEDGE', 'SERVICE_STANDARDS', 'HEALTH_SAFETY', 'UPSELL_READINESS'], true)) {
            $overall = $scores ? round(array_sum($scores) / count($scores), 2) : 0;
            return $this->overallBand($overall, [70 => 'نیازمند بازآموزی', 80 => 'مشروط', 90 => 'آماده'], 'مسلط');
        }
        if ($testCode === 'INTEGRITY_HONESTY') {
            $overall = $scores ? round(array_sum($scores) / count($scores), 2) : 0;
            return $overall < 50 ? 'نیازمند بررسی چندمنبعی' : ($overall < 75 ? 'قابل قبول' : 'قوت رفتاری');
        }
        $overall = $scores ? round(array_sum($scores) / count($scores), 2) : 0;
        return 'امتیاز کل: ' . number_format($overall, 1) . ' از ۱۰۰';
    }

    private function getRiskLevel(string $testCode, array $scores, $overall, bool $templatePending): string
    {
        if ($templatePending) return 'template_pending_key';
        $average = is_numeric($overall) ? (float)$overall : ($scores ? array_sum($scores) / count($scores) : 0);
        if ($testCode === 'BURNOUT_ORG') return $this->overallBand($average, [40 => 'پایین', 60 => 'نیازمند پایش', 80 => 'بالا'], 'نیازمند پیگیری فوری منابع انسانی');
        if ($testCode === 'JOB_SATISFACTION_ORG') return $this->overallBand($average, [45 => 'پایین', 70 => 'متوسط / نیازمند بررسی'], 'بالا');
        if ($testCode === 'INTEGRITY_HONESTY') return $average < 50 ? 'نیازمند بررسی چندمنبعی' : ($average < 75 ? 'قابل قبول' : 'قوت رفتاری');
        if (in_array($testCode, ['PRODUCT_KNOWLEDGE_DISTRIBUTION', 'SALES_CATALOG_KNOWLEDGE', 'SERVICE_STANDARDS', 'HEALTH_SAFETY', 'UPSELL_READINESS'], true)) {
            return $this->overallBand($average, [70 => 'نیازمند بازآموزی', 80 => 'مشروط', 90 => 'آماده'], 'مسلط');
        }
        return 'پایین';
    }

    private function generateRecommendation(string $testCode, array $scores, array $labels, $overall, bool $templatePending): string
    {
        if ($templatePending) {
            return 'این آزمون برای امتیازدهی رسمی نیازمند تکمیل گزینه‌ها و کلید صحیح توسط مدیر منابع انسانی یا مدیر فرایند است.';
        }
        if ($testCode === 'DISC_ORG' || $testCode === 'MBTI_ORG_INFORMAL') {
            return 'این نتیجه صرفاً برای توسعه فردی، بهبود همکاری و چیدمان نقش استفاده شود و مبنای رد استخدامی قرار نگیرد.';
        }
        if ($testCode === 'JOB_SATISFACTION_ORG') {
            return 'نتیجه رضایت شغلی فقط برای کارکنان فعلی تفسیر شود و برای بررسی محیط کار، حمایت مدیریتی و منابع استفاده گردد.';
        }
        if ($testCode === 'BURNOUT_ORG') {
            return 'در صورت بالا بودن شاخص فرسودگی، حجم کار، اولویت‌ها، استراحت و حمایت سرپرستی در گفت‌وگوی غیرپزشکی منابع انسانی بازبینی شود.';
        }
        if ($testCode === 'INTEGRITY_HONESTY') {
            return 'این خروجی باید همراه با مشاهده رفتاری، سوابق کاری و بازخورد چندمنبعی تفسیر شود و هرگز به برچسب‌زنی قطعی منجر نشود.';
        }
        $sorted = $scores;
        asort($sorted);
        $lowest = array_key_first($sorted);
        return 'برای تقویت بعد «' . ($labels[$lowest] ?? $lowest ?? '-') . '» یک اقدام کوتاه‌مدت، مشخص و قابل پیگیری تعریف شود.';
    }

    private function profileSummary(string $testCode, array $scores, array $labels, string $final, $overall, bool $templatePending): string
    {
        if ($templatePending) return 'سوالات این آزمون ثبت شده‌اند اما برای محاسبه رسمی، تکمیل گزینه‌ها و کلید پاسخ لازم است.';
        $sorted = $scores;
        arsort($sorted);
        $top = array_key_first($sorted);
        if ($testCode === 'ORGANIZATIONAL_COMMITMENT' && isset($scores['affective_commitment']) && $scores['affective_commitment'] < 45) {
            return $final . ' | تعهد عاطفی پایین است و بهتر است از منظر نگهداشت نیروی انسانی بررسی شود.';
        }
        return $final . ' | برجسته‌ترین بعد: ' . ($labels[$top] ?? $top ?? '-') . (is_numeric($overall) ? ' | میانگین: ' . number_format((float)$overall, 1) : '');
    }

    private function overallBand(float $score, array $thresholds, string $lastLabel): string
    {
        foreach ($thresholds as $threshold => $label) {
            if ($score < (float)$threshold) return $label;
        }
        return $lastLabel;
    }

    private function bandedSummary(array $scores, array $labels, array $thresholds, string $lastLabel): string
    {
        $overall = $scores ? round(array_sum($scores) / count($scores), 2) : 0;
        $band = $this->overallBand($overall, $thresholds, $lastLabel);
        $sorted = $scores;
        arsort($sorted);
        $top = array_key_first($sorted);
        $bottom = array_key_last($sorted);
        return $band . ' | قوی‌ترین: ' . ($labels[$top] ?? $top ?? '-') . ' | نیازمند توجه: ' . ($labels[$bottom] ?? $bottom ?? '-');
    }

    private function mbtiSummary(array $scores): string
    {
        $pairs = [
            'EI' => ['E', 'I'],
            'SN' => ['S', 'N'],
            'TF' => ['T', 'F'],
            'JP' => ['J', 'P'],
        ];
        $map = ['EI' => 'EI', 'SN' => 'SN', 'TF' => 'TF', 'JP' => 'JP'];
        $letters = [];
        foreach ($map as $dimensionKey => $pairKey) {
            $score = (float)($scores[$dimensionKey] ?? 50);
            if (abs($score - 50) < 7) {
                $letters[] = $pairs[$pairKey][0] . '/' . $pairs[$pairKey][1] . ' با ترجیح خفیف';
            } else {
                $letters[] = $score >= 50 ? $pairs[$pairKey][0] : $pairs[$pairKey][1];
            }
        }
        return 'پروفایل غیررسمی: ' . implode(' | ', $letters);
    }
}
