<?php

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/ManagerDashboardCalculator.php';
require_once __DIR__ . '/ReportingViewRepository.php';

final class CanonicalSalesDashboardService
{
    public static function managerSnapshot(array $actor): array
    {
        $empty = [
            'has_data' => false,
            'source' => 'legacy_manager_report',
            'period_key' => '',
            'period_title' => '',
            'report_date' => '',
            'commission' => [],
            'lines' => [],
            'coverage' => [],
            'brands' => [],
            'widgets' => [],
        ];
        if (!Database::tableExists('vw_sales_by_period')
            || !Database::tableExists('vw_target_by_visitor')
            || !Database::tableExists('vw_target_achievement')) {
            return $empty;
        }

        try {
            $targetRows = ReportingViewRepository::fetch('vw_target_by_visitor', $actor, [], 2000);
            $salesRows = ReportingViewRepository::fetch('vw_sales_by_period', $actor, [], 2000);
            $periodKey = self::latestPeriodKey($targetRows, $salesRows);
            if ($periodKey === '') return $empty;

            $targetRows = self::periodRows($targetRows, $periodKey);
            $salesRows = self::periodRows($salesRows, $periodKey);
            $brandRows = self::periodRows(
                ReportingViewRepository::fetch('vw_target_achievement', $actor, [], 2000),
                $periodKey
            );
            if (!$targetRows && !$salesRows) return $empty;

            $period = Database::fetch(
                'SELECT title,start_date,end_date FROM system_periods WHERE period_key=? ORDER BY id DESC LIMIT 1',
                [$periodKey]
            ) ?: [];
            $reportDate = (string)($period['end_date'] ?? self::latestSalesDate($periodKey));
            $rules = ManagerDashboardCalculator::rules();
            $visitors = self::visitorRows($salesRows, $targetRows, $reportDate, $rules);
            $lines = self::lineRows($visitors, $reportDate);
            $brands = self::brandRows($brandRows, $reportDate, $rules);
            $widgets = self::widgets($visitors, $lines, $brands);

            return [
                'has_data' => (bool)($visitors || $lines || $brands),
                'source' => 'active_import_views_formulas',
                'period_key' => $periodKey,
                'period_title' => (string)($period['title'] ?? $periodKey),
                'report_date' => $reportDate,
                'commission' => $visitors,
                'lines' => $lines,
                'coverage' => [],
                'brands' => $brands,
                'widgets' => $widgets,
            ];
        } catch (Throwable $error) {
            error_log('Canonical manager dashboard fallback: ' . $error->getMessage());
            return $empty;
        }
    }

    private static function latestPeriodKey(array ...$sets): string
    {
        $keys = [];
        foreach ($sets as $rows) {
            foreach ($rows as $row) {
                $key = trim((string)($row['period_key'] ?? ''));
                if ($key !== '') $keys[$key] = true;
            }
        }
        if (!$keys) return '';
        $keys = array_keys($keys);
        rsort($keys, SORT_NATURAL);
        return (string)$keys[0];
    }

    private static function periodRows(array $rows, string $periodKey): array
    {
        return array_values(array_filter(
            $rows,
            static fn(array $row): bool => (string)($row['period_key'] ?? '') === $periodKey
        ));
    }

    private static function latestSalesDate(string $periodKey): string
    {
        $row = Database::fetch(
            'SELECT MAX(invoice_date) report_date FROM vw_sales_active WHERE period_key=?',
            [$periodKey]
        );
        return (string)($row['report_date'] ?? '');
    }

    private static function visitorRows(array $salesRows, array $targetRows, string $reportDate, array $rules): array
    {
        $grouped = [];
        foreach ($salesRows as $row) {
            $key = self::visitorKey($row);
            if (!isset($grouped[$key])) $grouped[$key] = self::visitorBase($row, $reportDate);
            $grouped[$key]['sales_amount'] += (float)($row['net_sales_amount'] ?? 0);
            $grouped[$key]['sold_qty'] += (float)($row['net_quantity'] ?? 0);
        }
        foreach ($targetRows as $row) {
            $key = self::visitorKey($row);
            if (!isset($grouped[$key])) $grouped[$key] = self::visitorBase($row, $reportDate);
            $grouped[$key]['target_amount'] += (float)($row['target_amount'] ?? 0);
            $grouped[$key]['target_qty'] += (float)($row['target_quantity'] ?? 0);
            if ((float)$grouped[$key]['sales_amount'] <= 0) {
                $grouped[$key]['sales_amount'] += (float)($row['achievement_amount'] ?? 0);
            }
            if ((float)$grouped[$key]['sold_qty'] <= 0) {
                $grouped[$key]['sold_qty'] += (float)($row['achievement_quantity'] ?? 0);
            }
        }

        foreach ($grouped as &$row) {
            $target = (float)$row['target_amount'] > 0 ? (float)$row['target_amount'] : (float)$row['target_qty'];
            $actual = (float)$row['target_amount'] > 0 ? (float)$row['sales_amount'] : (float)$row['sold_qty'];
            $row['achievement_percent'] = ManagerDashboardCalculator::calculateAchievement($target, $actual);
            $penalty = ManagerDashboardCalculator::calculatePenalty($row, $rules);
            $row['penalty_percent'] = (float)$penalty['penalty_percent'];
            $commission = ManagerDashboardCalculator::calculateCommission($row + [
                'net_amount' => (float)$row['sales_amount'],
                'net_sales_amount' => (float)$row['sales_amount'],
            ], $rules);
            $row['condition_status'] = $commission['eligible'] ? 'ok' : 'عدم تحقق';
            $row['commission_after_penalty'] = (float)$commission['commission_after_penalty'];
            $row['return_loss'] = (float)$commission['return_loss'];
            $row['final_commission'] = (float)$commission['final_commission'];
            $row['target_achievement_percent'] = (float)$row['achievement_percent'];
        }
        unset($row);
        usort($grouped, static fn(array $a, array $b): int => $b['sales_amount'] <=> $a['sales_amount']);
        return array_values($grouped);
    }

    private static function visitorKey(array $row): string
    {
        $id = (int)($row['visitor_user_id'] ?? 0);
        if ($id > 0) return 'user:' . $id;
        $code = trim((string)($row['visitor_code'] ?? ''));
        if ($code !== '') return 'code:' . $code;
        return 'name:' . trim((string)($row['visitor_name'] ?? ''));
    }

    private static function visitorBase(array $row, string $reportDate): array
    {
        return [
            'report_date' => $reportDate,
            'visitor_user_id' => (int)($row['visitor_user_id'] ?? 0),
            'visitor_name' => (string)($row['visitor_name'] ?? 'نامشخص'),
            'line_code' => (string)($row['line_code'] ?? ''),
            'sales_amount' => 0.0,
            'sold_qty' => 0.0,
            'target_amount' => 0.0,
            'target_qty' => 0.0,
            'achievement_percent' => 0.0,
            'activity_commission' => 0.0,
            'penalty_percent' => 0.0,
            'commission_after_penalty' => 0.0,
            'return_loss' => 0.0,
            'quality_bonus' => 0.0,
            'target_line_count' => 0,
            'achieved_line_count' => 0,
            'target_achievement_percent' => 0.0,
            'target_bonus_before_condition' => 0.0,
            'target_bonus_status' => '',
            'target_bonus_final_amount' => 0.0,
            'achievement_below_75_count' => 0,
            'achievement_between_75_95_count' => 0,
            'group_achievement_bonus' => 0.0,
            'total_achievement_bonus' => 0.0,
            'coverage_bonus' => 0.0,
            'final_commission' => 0.0,
            'condition_status' => 'عدم تحقق',
        ];
    }

    private static function lineRows(array $visitors, string $reportDate): array
    {
        $grouped = [];
        foreach ($visitors as $visitor) {
            $key = trim((string)($visitor['line_code'] ?? '')) ?: 'نامشخص';
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'report_date' => $reportDate,
                    'line_code' => $key,
                    'line_sales_amount' => 0.0,
                    'sold_qty' => 0.0,
                    'target_qty' => 0.0,
                    'target_amount' => 0.0,
                    'achievement_percent' => 0.0,
                ];
            }
            $grouped[$key]['line_sales_amount'] += (float)$visitor['sales_amount'];
            $grouped[$key]['sold_qty'] += (float)$visitor['sold_qty'];
            $grouped[$key]['target_qty'] += (float)$visitor['target_qty'];
            $grouped[$key]['target_amount'] += (float)$visitor['target_amount'];
        }
        foreach ($grouped as &$row) {
            $target = (float)$row['target_amount'] > 0 ? (float)$row['target_amount'] : (float)$row['target_qty'];
            $actual = (float)$row['target_amount'] > 0 ? (float)$row['line_sales_amount'] : (float)$row['sold_qty'];
            $row['achievement_percent'] = ManagerDashboardCalculator::calculateAchievement($target, $actual);
        }
        unset($row);
        return array_values($grouped);
    }

    private static function brandRows(array $rows, string $reportDate, array $rules): array
    {
        $grouped = [];
        foreach ($rows as $row) {
            $visitorKey = self::visitorKey($row);
            if (!isset($grouped[$visitorKey])) {
                $grouped[$visitorKey] = [
                    'report_date' => $reportDate,
                    'visitor_name' => (string)($row['visitor_name'] ?? 'نامشخص'),
                    'target_brands' => [],
                    'achieved_brands' => [],
                ];
            }
            $brandKey = trim((string)($row['brand_code'] ?? ''));
            if ($brandKey === '') $brandKey = 'name:' . trim((string)($row['brand_name'] ?? 'بدون برند'));
            $grouped[$visitorKey]['target_brands'][$brandKey] = true;
            if ((float)($row['achievement_amount'] ?? 0) > 0 || (float)($row['achievement_quantity'] ?? 0) > 0) {
                $grouped[$visitorKey]['achieved_brands'][$brandKey] = true;
            }
        }
        $result = [];
        foreach ($grouped as $row) {
            $calculated = ManagerDashboardCalculator::calculateBrandAchievement([
                'target_brand_count' => count($row['target_brands']),
                'achieved_brand_count' => count($row['achieved_brands']),
            ], $rules);
            $result[] = [
                'report_date' => $row['report_date'],
                'visitor_name' => $row['visitor_name'],
                'target_brand_count' => count($row['target_brands']),
                'achieved_brand_count' => count($row['achieved_brands']),
                'achievement_percent' => (float)$calculated['achievement_percent'],
                'commission_status' => $calculated['eligible'] ? 'ok' : 'عدم تحقق',
            ];
        }
        return $result;
    }

    private static function widgets(array $visitors, array $lines, array $brands): array
    {
        $targetRows = [];
        $penaltyRows = [];
        foreach ($visitors as $row) {
            $targetRows[] = [
                'report_date' => $row['report_date'],
                'entity_type' => 'visitor',
                'line_code' => $row['line_code'],
                'person_name' => $row['visitor_name'],
                'target_qty' => (float)$row['target_qty'],
                'sold_qty' => (float)$row['sold_qty'],
                'achievement_percent' => (float)$row['achievement_percent'],
                'over_total_bonus' => (float)$row['total_achievement_bonus'],
            ];
            $penaltyRows[] = [
                'report_date' => $row['report_date'],
                'visitor_name' => $row['visitor_name'],
                'penalty_percent' => (float)$row['penalty_percent'],
                'commission_before_penalty' => (float)$row['activity_commission'],
                'commission_after_penalty' => (float)$row['commission_after_penalty'],
            ];
        }
        return [
            'commission_summary' => $visitors,
            'team_target_achievement' => $targetRows,
            'visitor_penalty' => $penaltyRows,
            'brand_target' => $brands,
            'line_performance' => $lines,
        ];
    }
}
