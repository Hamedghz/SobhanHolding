<?php

final class DashboardModule
{
    public const SCOPES = [
        'ceo' => 'مدیرعامل',
        'sales_manager' => 'مدیر فروش',
        'supervisor' => 'سرپرست فروش',
        'employee' => 'کارمند',
        'department_manager' => 'مدیر واحد',
    ];

    public static function definitions(): array
    {
        return [
            'ceo' => [
                'summary_kpis' => self::widget('شاخص‌های کلیدی', 'vw_sales_reference_summary', 10, 'wide'),
                'sales_trend' => self::widget('روند فروش', 'vw_active_sales_aggregate_rows', 20, 'wide'),
                'line_performance' => self::widget('عملکرد لاین‌ها', 'vw_sales_by_line_reference', 30, 'half'),
                'visitor_performance' => self::widget('عملکرد ویزیتورها', 'vw_sales_by_visitor_reference', 40, 'half'),
                'brand_performance' => self::widget('عملکرد برندها', 'vw_sales_by_brand_reference', 50, 'half'),
                'inventory_snapshot' => self::widget('تصویر موجودی', 'vw_inventory_reference_summary', 60, 'half'),
                'data_quality' => self::widget('کیفیت و تازگی داده', 'sales_reference_import_batches', 70, 'third', false),
            ],
            'sales_manager' => [
                'summary_kpis' => self::widget('شاخص‌های کلیدی فروش', 'vw_sales_by_period', 10, 'wide'),
                'visitor_achievement_chart' => self::widget('تحقق ویزیتورها', 'vw_target_by_visitor', 20, 'third'),
                'visitor_commission_chart' => self::widget('پورسانت ویزیتورها', 'vw_commission_inputs', 30, 'third'),
                'line_achievement_chart' => self::widget('تحقق لاین‌ها', 'vw_target_by_line', 40, 'third'),
                'customer_coverage' => self::widget('پوشش مشتری', 'vw_sales_by_customer', 50, 'half'),
                'brand_target' => self::widget('تحقق برند', 'vw_target_achievement', 60, 'half'),
                'commission_summary' => self::widget('خلاصه پورسانت ویزیتورها', 'vw_commission_inputs', 100, 'wide'),
                'team_target_achievement' => self::widget('تحقق تارگت تیم فروش', 'vw_target_by_visitor', 110, 'wide'),
                'over_achievement_bonus' => self::widget('پاداش اچیو و توتال', 'vw_commission_inputs', 120, 'wide'),
                'visitor_penalty' => self::widget('ضرایب کاهنده ویزیتورها', 'vw_commission_inputs', 130, 'wide'),
                'supervisor_penalty' => self::widget('ضرایب کاهنده سرپرستان', 'vw_sales_by_supervisor', 140, 'wide'),
                'customer_target' => self::widget('تحقق هدف مشتری', 'vw_target_by_visitor', 150, 'wide'),
                'line_performance' => self::widget('عملکرد لاین‌ها', 'vw_target_by_line', 160, 'wide'),
                'supervisor_performance' => self::widget('عملکرد سرپرستان', 'vw_sales_by_supervisor', 170, 'wide'),
                'sales_manager_performance' => self::widget('عملکرد مدیران فروش', 'vw_sales_by_manager', 180, 'wide'),
                'ai_insights' => self::widget('بینش هوشمند فروش', 'manager_dashboard_ai_logs', 190, 'wide', false),
            ],
            'supervisor' => [
                'summary_kpis' => self::widget('خلاصه عملکرد تیم', 'vw_active_sales_aggregate_rows', 10, 'wide'),
                'visitor_performance' => self::widget('عملکرد ویزیتورها', 'vw_sales_by_visitor_reference', 20, 'wide'),
                'actions' => self::widget('اقدامات امروز و فوری', 'supervisor_actions', 30, 'half'),
                'team_structure' => self::widget('ساختار تیم', 'users', 40, 'half', false),
            ],
            'employee' => [
                'my_tasks' => self::widget('وظایف من', 'work_planner_tasks', 10, 'half'),
                'my_kpis' => self::widget('شاخص‌های من', 'hr_kpi_results', 20, 'half'),
                'my_attendance' => self::widget('کارکرد من', 'hr_attendance_entries', 30, 'wide'),
            ],
            'department_manager' => [
                'summary_kpis' => self::widget('شاخص‌های واحد', 'hr_kpi_results', 10, 'wide'),
                'team_tasks' => self::widget('وظایف تیم', 'work_planner_tasks', 20, 'half'),
                'attendance' => self::widget('کارکرد واحد', 'hr_attendance_entries', 30, 'half'),
                'reports' => self::widget('گزارش‌های واحد', 'management_report_submissions', 40, 'wide'),
            ],
        ];
    }

    public static function repair(PDO $pdo): void
    {
        $engine = ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS dashboard_widget_preferences (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                scope_type VARCHAR(40) NOT NULL,
                scope_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
                user_id INT UNSIGNED NOT NULL DEFAULT 0,
                widget_key VARCHAR(100) NOT NULL,
                title_override VARCHAR(190) NULL,
                visible TINYINT(1) NOT NULL DEFAULT 1,
                sort_order INT NOT NULL DEFAULT 0,
                size_key VARCHAR(20) NOT NULL DEFAULT 'wide',
                default_period_key VARCHAR(40) NOT NULL DEFAULT 'monthly',
                default_filters_json LONGTEXT NULL,
                refresh_seconds INT UNSIGNED NOT NULL DEFAULT 0,
                drilldown_enabled TINYINT(1) NOT NULL DEFAULT 1,
                data_source_key VARCHAR(150) NOT NULL,
                settings_json LONGTEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_dashboard_widget_preference (scope_type,scope_id,user_id,widget_key),
                INDEX idx_dashboard_widget_scope (scope_type,scope_id,user_id,visible,sort_order),
                INDEX idx_dashboard_widget_source (data_source_key)
            ){$engine}"
        );

        $statement = $pdo->prepare(
            'INSERT INTO dashboard_widget_preferences
             (scope_type,scope_id,user_id,widget_key,title_override,visible,sort_order,size_key,default_period_key,default_filters_json,refresh_seconds,drilldown_enabled,data_source_key,settings_json,created_at,updated_at)
             VALUES (?,0,0,?,?,1,?,?,?, ?,0,?,?,NULL,NOW(),NOW())
             ON DUPLICATE KEY UPDATE
                data_source_key=VALUES(data_source_key),
                updated_at=NOW()'
        );
        foreach (self::definitions() as $scope => $widgets) {
            foreach ($widgets as $key => $widget) {
                $statement->execute([
                    $scope,
                    $key,
                    $widget['title'],
                    $widget['sort_order'],
                    $widget['size_key'],
                    $widget['default_period_key'],
                    json_encode(['mode' => 'authorized_scope'], JSON_UNESCAPED_UNICODE),
                    $widget['drilldown_enabled'] ? 1 : 0,
                    $widget['data_source_key'],
                ]);
            }
        }
    }

    private static function widget(
        string $title,
        string $source,
        int $sortOrder,
        string $size,
        bool $drilldown = true
    ): array {
        return [
            'title' => $title,
            'data_source_key' => $source,
            'sort_order' => $sortOrder,
            'size_key' => $size,
            'default_period_key' => 'monthly',
            'drilldown_enabled' => $drilldown,
        ];
    }
}
