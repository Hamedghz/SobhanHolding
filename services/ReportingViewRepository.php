<?php

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/ReportingViewsModule.php';
require_once __DIR__ . '/../lib/OrgAccess.php';

final class ReportingViewRepository
{
    private const USER_SCOPED = [
        'vw_sales_active' => 'visitor_user_id',
        'vw_sales_by_period' => 'visitor_user_id',
        'vw_sales_by_visitor' => 'visitor_user_id',
        'vw_sales_by_customer' => 'visitor_user_id',
        'vw_sales_by_product' => 'visitor_user_id',
        'vw_sales_by_brand' => 'visitor_user_id',
        'vw_target_achievement' => 'visitor_user_id',
        'vw_target_by_visitor' => 'visitor_user_id',
        'vw_commission_inputs' => 'visitor_user_id',
        'vw_attendance_period_summary' => 'employee_id',
        'vw_action_workload' => 'user_id',
        'vw_daily_report_completion' => 'user_id',
    ];

    private const LINE_SCOPED = [
        'vw_sales_by_supervisor',
        'vw_sales_by_manager',
        'vw_sales_by_line',
        'vw_purchase_active',
        'vw_purchase_by_supplier',
        'vw_target_by_line',
    ];

    private const ADMIN_ONLY = [
        'vw_inventory_current',
        'vw_inventory_by_product',
    ];

    public static function fetch(string $view, array $actor, array $filters = [], int $limit = 500): array
    {
        if (!in_array($view, ReportingViewsModule::VIEW_NAMES, true)) {
            throw new InvalidArgumentException('گزارش درخواستی مجاز نیست.');
        }
        $where = [];
        $params = [];
        self::applyScope($view, $actor, $where, $params);
        foreach ($filters as $column => $value) {
            if (!preg_match('/^[a-z][a-z0-9_]*$/', (string)$column)) continue;
            if (!is_scalar($value) || trim((string)$value) === '') continue;
            $where[] = "`{$column}`=?";
            $params[] = trim((string)$value);
        }
        $limit = max(1, min(2000, $limit));
        return Database::fetchAll(
            "SELECT * FROM `{$view}`" . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . " LIMIT {$limit}",
            $params
        );
    }

    private static function applyScope(string $view, array $actor, array &$where, array &$params): void
    {
        if (OrgAccess::isAdmin($actor) || in_array(strtolower((string)($actor['role_key'] ?? '')), ['ceo','chief_executive','executive_manager'], true)) return;
        if (in_array($view, self::ADMIN_ONLY, true)) {
            throw new DomainException('مشاهده این گزارش به مجوز مدیریتی نیاز دارد.');
        }
        if (isset(self::USER_SCOPED[$view])) {
            [$sql, $ids] = OrgAccess::scopeSql($actor, self::USER_SCOPED[$view]);
            $where[] = $sql;
            array_push($params, ...$ids);
            return;
        }
        if (in_array($view, self::LINE_SCOPED, true)) {
            $ids = OrgAccess::accessibleUserIds($actor);
            if (!$ids) throw new DomainException('محدوده سازمانی معتبری یافت نشد.');
            if (count($ids) === 1) throw new DomainException('این نمای تجمیعی برای نقش کارمند قابل مشاهده نیست.');
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $lines = array_values(array_filter(array_unique(array_column(
                Database::fetchAll("SELECT DISTINCT sales_line FROM users WHERE id IN ({$placeholders}) AND sales_line IS NOT NULL AND sales_line<>''", $ids),
                'sales_line'
            ))));
            if (!$lines) throw new DomainException('لاین فروش مجاز برای این گزارش یافت نشد.');
            $where[] = 'line_code IN (' . implode(',', array_fill(0, count($lines), '?')) . ')';
            array_push($params, ...$lines);
            return;
        }
        throw new DomainException('محدوده دسترسی این گزارش تعریف نشده است.');
    }
}
