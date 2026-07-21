<?php

final class ReportingViewsModule
{
    public const VIEW_NAMES = [
        'vw_sales_active',
        'vw_sales_by_period',
        'vw_sales_by_visitor',
        'vw_sales_by_supervisor',
        'vw_sales_by_manager',
        'vw_sales_by_line',
        'vw_sales_by_customer',
        'vw_sales_by_product',
        'vw_sales_by_brand',
        'vw_purchase_active',
        'vw_purchase_by_supplier',
        'vw_inventory_current',
        'vw_inventory_by_product',
        'vw_target_achievement',
        'vw_target_by_visitor',
        'vw_target_by_line',
        'vw_commission_inputs',
        'vw_attendance_period_summary',
        'vw_action_workload',
        'vw_daily_report_completion',
    ];

    public static function repair(PDO $pdo): void
    {
        foreach (self::definitions() as $name => $select) {
            try {
                $pdo->exec("CREATE OR REPLACE VIEW `{$name}` AS {$select}");
            } catch (Throwable $error) {
                error_log('Reporting view repair ' . $name . ': ' . $error->getMessage());
            }
        }
    }

    public static function definitions(): array
    {
        $salesDimensions = 's.visitor_code,s.visitor_name,s.supervisor_code,s.supervisor_name,s.sales_manager_code,s.sales_manager_name,s.line_code,s.line_name';
        $salesUserJoins = "
            LEFT JOIN users visitor ON visitor.status='active' AND (visitor.employee_no=s.visitor_code OR visitor.kara_system_code=s.visitor_code)
            LEFT JOIN users supervisor ON supervisor.status='active' AND (supervisor.employee_no=s.supervisor_code OR supervisor.kara_system_code=s.supervisor_code)
            LEFT JOIN users manager ON manager.status='active' AND (manager.employee_no=s.sales_manager_code OR manager.kara_system_code=s.sales_manager_code)";
        $salesMetrics = "COUNT(DISTINCT CONCAT_WS(':',s.import_batch_id,s.invoice_number)) invoice_count,
            COALESCE(SUM(COALESCE(s.total_qty,s.quantity,0)),0) gross_quantity,
            COALESCE(SUM(COALESCE(s.return_quantity,0)),0) return_quantity,
            COALESCE(SUM(COALESCE(s.total_qty,s.quantity,0)-COALESCE(s.return_quantity,0)),0) net_quantity,
            COALESCE(SUM(COALESCE(s.gross_amount,0)),0) gross_amount,
            COALESCE(SUM(COALESCE(s.discount_total,s.discount_amount,0)),0) discount_amount,
            COALESCE(SUM(COALESCE(s.return_amount,0)),0) return_amount,
            COALESCE(SUM(COALESCE(s.net_amount,0)-COALESCE(s.return_amount,0)),0) net_sales_amount";
        $scopeIds = 'visitor.id visitor_user_id,supervisor.id supervisor_user_id,manager.id manager_user_id';

        return [
            'vw_sales_active' => "SELECT s.*,visitor.id visitor_user_id,supervisor.id supervisor_user_id,manager.id manager_user_id
                FROM vw_active_sales_aggregate_rows s {$salesUserJoins}",
            'vw_sales_by_period' => "SELECT COALESCE(NULLIF(s.period_key,''),DATE_FORMAT(s.invoice_date,'%Y-%m')) period_key,
                MIN(s.invoice_date) period_start,MAX(s.invoice_date) period_end,{$scopeIds},{$salesDimensions},{$salesMetrics}
                FROM vw_active_sales_aggregate_rows s {$salesUserJoins}
                GROUP BY period_key,visitor.id,supervisor.id,manager.id,{$salesDimensions}",
            'vw_sales_by_visitor' => "SELECT {$scopeIds},{$salesDimensions},{$salesMetrics}
                FROM vw_active_sales_aggregate_rows s {$salesUserJoins}
                GROUP BY visitor.id,supervisor.id,manager.id,{$salesDimensions}",
            'vw_sales_by_supervisor' => "SELECT supervisor.id supervisor_user_id,manager.id manager_user_id,
                s.supervisor_code,s.supervisor_name,s.sales_manager_code,s.sales_manager_name,s.line_code,s.line_name,{$salesMetrics}
                FROM vw_active_sales_aggregate_rows s {$salesUserJoins}
                GROUP BY supervisor.id,manager.id,s.supervisor_code,s.supervisor_name,s.sales_manager_code,s.sales_manager_name,s.line_code,s.line_name",
            'vw_sales_by_manager' => "SELECT manager.id manager_user_id,s.sales_manager_code,s.sales_manager_name,s.line_code,s.line_name,{$salesMetrics}
                FROM vw_active_sales_aggregate_rows s {$salesUserJoins}
                GROUP BY manager.id,s.sales_manager_code,s.sales_manager_name,s.line_code,s.line_name",
            'vw_sales_by_line' => "SELECT s.line_code,s.line_name,{$salesMetrics}
                FROM vw_active_sales_aggregate_rows s GROUP BY s.line_code,s.line_name",
            'vw_sales_by_customer' => "SELECT {$scopeIds},{$salesDimensions},s.customer_code,s.customer_name,
                MAX(s.customer_guild_code) customer_guild_code,MAX(s.customer_guild_name) customer_guild_name,{$salesMetrics}
                FROM vw_active_sales_aggregate_rows s {$salesUserJoins}
                GROUP BY visitor.id,supervisor.id,manager.id,{$salesDimensions},s.customer_code,s.customer_name",
            'vw_sales_by_product' => "SELECT {$scopeIds},{$salesDimensions},s.product_code,s.product_name,s.brand_code,s.brand_name,{$salesMetrics}
                FROM vw_active_sales_aggregate_rows s {$salesUserJoins}
                GROUP BY visitor.id,supervisor.id,manager.id,{$salesDimensions},s.product_code,s.product_name,s.brand_code,s.brand_name",
            'vw_sales_by_brand' => "SELECT {$scopeIds},{$salesDimensions},s.brand_code,s.brand_name,
                COUNT(DISTINCT s.product_code) product_count,{$salesMetrics}
                FROM vw_active_sales_aggregate_rows s {$salesUserJoins}
                GROUP BY visitor.id,supervisor.id,manager.id,{$salesDimensions},s.brand_code,s.brand_name",
            'vw_purchase_active' => 'SELECT * FROM vw_active_purchase_aggregate_rows',
            'vw_purchase_by_supplier' => "SELECT supplier_code,supplier_name,line_code,line_name,
                COUNT(DISTINCT CONCAT_WS(':',import_batch_id,invoice_number)) invoice_count,
                COUNT(DISTINCT product_code) product_count,COALESCE(SUM(quantity),0) quantity,
                COALESCE(SUM(gross_amount),0) gross_amount,COALESCE(SUM(discount_amount),0) discount_amount,
                COALESCE(SUM(net_amount),0) net_amount
                FROM vw_active_purchase_aggregate_rows
                GROUP BY supplier_code,supplier_name,line_code,line_name",
            'vw_inventory_current' => 'SELECT * FROM vw_active_inventory_aggregate_rows',
            'vw_inventory_by_product' => "SELECT product_code,product_name,brand_name,group_code,group_name,
                MAX(snapshot_date) snapshot_date,COALESCE(SUM(COALESCE(current_total_stock,period_total_stock)),0) stock_quantity,
                COALESCE(SUM(stock_value_by_last_cost),0) stock_value_by_last_cost,
                COALESCE(SUM(stock_value_by_sale_price_1),0) stock_value_by_sale_price
                FROM vw_active_inventory_aggregate_rows
                GROUP BY product_code,product_name,brand_name,group_code,group_name",
            'vw_target_achievement' => 'SELECT * FROM vw_sales_target_achievement',
            'vw_target_by_visitor' => 'SELECT * FROM vw_sales_target_visitor_totals',
            'vw_target_by_line' => 'SELECT * FROM vw_sales_target_line_totals',
            'vw_commission_inputs' => "SELECT a.*,p.priority_code,p.priority_rank,
                CASE WHEN COALESCE(a.target_amount,0)>0 THEN ROUND((a.achievement_amount/a.target_amount)*100,4) ELSE 0 END achievement_percent
                FROM vw_sales_target_achievement a
                LEFT JOIN vw_active_product_priorities p ON p.period_id=a.period_id AND p.product_code=a.product_code",
            'vw_attendance_period_summary' => "SELECT e.employee_id,YEAR(e.attendance_date) year,MONTH(e.attendance_date) month,
                MIN(e.attendance_date) period_start,MAX(e.attendance_date) period_end,
                SUM(e.work_minutes) work_minutes,SUM(e.late_minutes) late_minutes,SUM(e.early_leave_minutes) early_leave_minutes,
                SUM(CASE WHEN e.overtime_status='approved' THEN e.normal_overtime_minutes ELSE 0 END) normal_overtime_minutes,
                SUM(CASE WHEN e.overtime_status='approved' THEN e.holiday_overtime_minutes ELSE 0 END) holiday_overtime_minutes,
                SUM(e.day_status='absent') absent_days,SUM(e.day_status='leave') leave_days,
                SUM(e.day_status='mission') mission_days,SUM(e.day_status IN ('present','half_day','holiday_work')) present_days
                FROM hr_attendance_entries e
                LEFT JOIN sales_import_batches b ON b.id=e.import_batch_id
                WHERE e.import_batch_id IS NULL OR (b.source_module='attendance' AND b.is_active_reference=1 AND b.status='committed')
                GROUP BY e.employee_id,YEAR(e.attendance_date),MONTH(e.attendance_date)",
            'vw_action_workload' => "SELECT a.assigned_to user_id,a.status,a.priority,
                COUNT(*) action_count,SUM(a.due_date<CURDATE() AND a.status NOT IN ('done','cancelled')) overdue_count,
                MIN(a.due_date) nearest_due_date,MAX(a.updated_at) last_activity_at
                FROM actions a GROUP BY a.assigned_to,a.status,a.priority",
            'vw_daily_report_completion' => "SELECT r.user_id,r.report_date,r.status,COUNT(*) report_count,
                SUM(r.status IN ('submitted','approved')) completed_count,MAX(r.submitted_at) last_submitted_at
                FROM daily_reports r GROUP BY r.user_id,r.report_date,r.status",
        ];
    }
}
