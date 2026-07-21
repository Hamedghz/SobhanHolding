<?php

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/../services/FormulaRuntime.php';

class ManagerDashboardCalculator
{
    public static function ruleDefaults(): array
    {
        return [
            'minimum_commission_achievement_percent' => 75,
            'minimum_commission_sales_amount' => 0,
            'max_achievement_factor_percent' => 120,
            'high_achievement_threshold_percent' => 100,
            'line_underperformance_threshold_percent' => 75,
            'brand_target_min_percent' => 100,
            'customer_coverage_floor' => 0,
            'customer_target_floor' => 0,
            'default_return_loss_rate' => 0,
            'shared_visitor_deduction_percent_1' => 0,
            'shared_visitor_deduction_percent_2' => 0,
            'over_100_bonus_amount' => 0,
            'over_110_total_bonus_amount' => 0,
            'brand_customer_bonus_amount' => 0,
            'brand_target_bonus_amount' => 0,
            'customer_coverage_penalty_amount' => 0,
        ];
    }

    public static function rules(): array
    {
        $rules = [];
        foreach (self::ruleDefaults() as $key => $default) {
            $rules[$key] = (float)setting('manager_dashboard_rule_' . $key, (string)$default);
        }
        return $rules;
    }

    public static function calculateAchievement(float $target, float $actual): float
    {
        return $target > 0 ? round(($actual / $target) * 100, 2) : 0.0;
    }

    public static function calculateRemainingToTarget(float $target, float $actual): float
    {
        return round($target - $actual, 2);
    }

    public static function calculatePenalty(array $data, array $rules): array
    {
        $achievement = (float)($data['achievement_percent'] ?? 0);
        $threshold = (float)$rules['line_underperformance_threshold_percent'];
        $penaltyPercent = $achievement < $threshold ? max(0, $threshold - $achievement) : 0;
        $runtime = FormulaRuntime::evaluateByKey('manager_penalty', $data + [
            'achievement_percent' => $achievement,
            'penalty_percent' => $penaltyPercent,
        ]);
        if ($runtime !== null) $penaltyPercent = max(0, (float)$runtime['final_result']);
        return ['penalty_percent' => round($penaltyPercent, 2), 'is_underperforming' => $achievement < $threshold];
    }

    public static function calculateCommission(array $data, array $rules): array
    {
        $achievement = min((float)$rules['max_achievement_factor_percent'], max(0, (float)($data['achievement_percent'] ?? 0)));
        $sales = max(0, (float)($data['sales_amount'] ?? 0));
        $eligible = $achievement >= (float)$rules['minimum_commission_achievement_percent']
            && $sales >= (float)$rules['minimum_commission_sales_amount'];
        $base = $eligible ? max(0, (float)($data['activity_commission'] ?? 0)) : 0;
        $penalty = max(0, (float)($data['penalty_percent'] ?? self::calculatePenalty($data, $rules)['penalty_percent']));
        $afterPenalty = $base * (1 - min(100, $penalty) / 100);
        $sharedDeduction = 0.0;
        if (!empty($data['shared_visitor_type_1'])) $sharedDeduction += (float)$rules['shared_visitor_deduction_percent_1'];
        if (!empty($data['shared_visitor_type_2'])) $sharedDeduction += (float)$rules['shared_visitor_deduction_percent_2'];
        $afterPenalty *= 1 - min(100, max(0, $sharedDeduction)) / 100;
        $returnLoss = abs((float)($data['return_loss'] ?? ($sales * (float)$rules['default_return_loss_rate'] / 100)));
        $bonuses = (float)($data['quality_bonus'] ?? 0) + (float)($data['group_achievement_bonus'] ?? 0)
            + (float)($data['total_achievement_bonus'] ?? 0) + (float)($data['coverage_bonus'] ?? 0);
        if ($achievement >= 100) $bonuses += (float)$rules['over_100_bonus_amount'];
        if ($achievement >= 110) $bonuses += (float)$rules['over_110_total_bonus_amount'];
        $finalCommission = round(max(0, $afterPenalty - $returnLoss + $bonuses), 2);
        $runtime = FormulaRuntime::evaluateByKey('manager_commission', $data + [
            'achievement_percent' => $achievement,
            'sales_amount' => $sales,
            'commission_after_penalty' => $afterPenalty,
            'return_loss' => $returnLoss,
            'bonus_total' => $bonuses,
            'final_commission' => $finalCommission,
        ]);
        if ($runtime !== null) $finalCommission = max(0, (float)$runtime['final_result']);
        return [
            'eligible' => $eligible,
            'achievement_factor_percent' => $achievement,
            'commission_after_penalty' => round($afterPenalty, 2),
            'return_loss' => round($returnLoss, 2),
            'final_commission' => round($finalCommission, 2),
        ];
    }

    public static function calculateCustomerCoverage(array $data, array $rules): array
    {
        $floor = max(0, (float)($data['customer_floor'] ?? 0), (float)$rules['customer_coverage_floor'], (float)$rules['customer_target_floor']);
        $coverage = max(0, (float)($data['coverage_count'] ?? 0));
        $remaining = self::calculateRemainingToTarget($floor, $coverage);
        $rewardOrPenalty = $remaining > 0 ? -(float)$rules['customer_coverage_penalty_amount'] : (float)$rules['brand_customer_bonus_amount'];
        $runtime = FormulaRuntime::evaluateByKey('manager_customer_coverage', $data + [
            'coverage_count' => $coverage,
            'target_qty' => $floor,
            'remaining_to_target' => $remaining,
            'reward_or_penalty' => $rewardOrPenalty,
        ]);
        if ($runtime !== null) $rewardOrPenalty = (float)$runtime['final_result'];
        return [
            'coverage_percent' => self::calculateAchievement($floor, $coverage),
            'remaining_to_target' => $remaining,
            'reward_or_penalty' => $rewardOrPenalty,
        ];
    }

    public static function calculateBrandAchievement(array $data, array $rules): array
    {
        $percent = self::calculateAchievement((float)($data['target_brand_count'] ?? 0), (float)($data['achieved_brand_count'] ?? 0));
        $eligible = $percent >= (float)$rules['brand_target_min_percent'];
        $bonus = $eligible ? (float)$rules['brand_target_bonus_amount'] : 0;
        $runtime = FormulaRuntime::evaluateByKey('manager_brand_bonus', $data + [
            'brand_achievement_percent' => $percent,
            'achievement_percent' => $percent,
            'bonus_total' => $bonus,
        ]);
        if ($runtime !== null) $bonus = max(0, (float)$runtime['final_result']);
        return ['achievement_percent' => $percent, 'eligible' => $eligible, 'bonus_amount' => $bonus];
    }

    public static function validateDashboardRows(array $rows): array
    {
        $warnings = [];
        foreach ($rows as $index => $row) {
            if (!is_array($row)) { $warnings[] = 'ردیف ' . ($index + 1) . ' ساختار معتبر ندارد.'; continue; }
            foreach (['achievement_percent', 'penalty_percent'] as $field) {
                if (isset($row[$field]) && ((float)$row[$field] < 0 || (float)$row[$field] > 200)) {
                    $warnings[] = 'ردیف ' . ($index + 1) . ': مقدار ' . $field . ' خارج از بازه معتبر است.';
                }
            }
            if (array_key_exists('target_qty', $row) && (float)$row['target_qty'] <= 0) $warnings[] = 'ردیف ' . ($index + 1) . ': تارگت خالی یا صفر است.';
            if (array_key_exists('sales_amount', $row) && (float)$row['sales_amount'] < 0) $warnings[] = 'ردیف ' . ($index + 1) . ': مبلغ فروش منفی است.';
        }
        return array_values(array_unique($warnings));
    }

    public static function summarizeReport(int $reportId): array
    {
        if ($reportId < 1) return [];
        $commission = Database::fetchAll('SELECT * FROM manager_commission_summary WHERE report_id=? ORDER BY id LIMIT 100', [$reportId]);
        $lines = Database::fetchAll('SELECT * FROM manager_line_performance WHERE report_id=? ORDER BY id LIMIT 100', [$reportId]);
        $sum = static fn(array $rows, string $field): float => array_sum(array_map(static fn($row): float => (float)($row[$field] ?? 0), $rows));
        $average = static fn(array $rows, string $field): float => $rows ? $sum($rows, $field) / count($rows) : 0;
        return [
            'total_sales' => round($sum($commission, 'sales_amount'), 2),
            'total_final_commission' => round($sum($commission, 'final_commission'), 2),
            'average_visitor_achievement' => round($average($commission, 'achievement_percent'), 2),
            'average_line_achievement' => round($average($lines, 'achievement_percent'), 2),
            'visitor_count' => count($commission),
            'line_count' => count($lines),
            'data_warnings' => self::validateDashboardRows(array_merge($commission, $lines)),
        ];
    }
}
