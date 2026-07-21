<?php

require_once __DIR__ . '/../core/Database.php';

final class FormulaSourceRegistry
{
    public static function sources(): array
    {
        return [
            'sample_input' => [
                'title' => 'ورودی نمونه کنترل‌شده',
                'table' => null,
                'metrics' => [
                    'gross_amount' => 'فروش ناخالص',
                    'discount_total' => 'تخفیف',
                    'net_amount' => 'فروش خالص',
                    'total_qty' => 'تعداد فروش',
                    'target_amount' => 'تارگت مبلغی',
                    'target_qty' => 'تارگت تعدادی',
                    'achievement_percent' => 'درصد تحقق',
                    'activity_commission' => 'پورسانت فعالیت',
                    'penalty_percent' => 'درصد ضریب کاهنده',
                    'return_amount' => 'مبلغ مرجوعی',
                    'coverage_count' => 'پوشش مشتری',
                    'brand_achievement_percent' => 'تحقق برند',
                    'work_minutes' => 'دقایق کارکرد',
                    'overtime_minutes' => 'دقایق اضافه‌کار',
                    'kpi_score' => 'امتیاز KPI',
                    'report_value' => 'مقدار گزارش مدیریتی',
                    'purchase_base' => 'مبنای خرید',
                    'offer_rate_percent' => 'درصد نرخ آفر',
                    'commission_after_penalty' => 'پورسانت پس از کاهنده',
                    'return_loss' => 'کسر مرجوعی',
                    'bonus_total' => 'مجموع پاداش',
                    'final_commission' => 'پورسانت نهایی',
                    'remaining_to_target' => 'باقیمانده تا هدف',
                    'reward_or_penalty' => 'پاداش یا جریمه',
                ],
                'filters' => [
                    'line_code' => 'لاین',
                    'product_code' => 'کالا',
                    'customer_code' => 'مشتری',
                    'user_id' => 'کاربر',
                ],
            ],
            'active_sales' => [
                'title' => 'فروش تجمیعی فعال',
                'table' => 'vw_active_sales_aggregate_rows',
                'date_field' => 'invoice_date',
                'user_field' => 'visitor_code',
                'metrics' => [
                    'gross_amount' => 'فروش ناخالص',
                    'discount_total' => 'تخفیف',
                    'net_amount' => 'فروش خالص',
                    'total_qty' => 'تعداد فروش',
                    'return_amount' => 'مبلغ مرجوعی',
                    'invoice_number' => 'شماره فاکتور',
                    'customer_code' => 'کد مشتری',
                    'visitor_code' => 'کد ویزیتور',
                ],
                'filters' => [
                    'invoice_date' => 'تاریخ فاکتور',
                    'line_code' => 'لاین',
                    'visitor_code' => 'ویزیتور',
                    'product_code' => 'کالا',
                    'customer_code' => 'مشتری',
                    'brand_code' => 'برند',
                    'supervisor_code' => 'سرپرست',
                    'sales_manager_code' => 'مدیر فروش',
                ],
            ],
            'manager_commission' => [
                'title' => 'خلاصه پورسانت مدیر فروش',
                'table' => 'manager_commission_summary',
                'user_field' => 'visitor_name',
                'metrics' => [
                    'sales_amount' => 'مبلغ فروش',
                    'achievement_percent' => 'درصد تحقق',
                    'activity_commission' => 'پورسانت فعالیت',
                    'penalty_percent' => 'درصد ضریب کاهنده',
                    'commission_after_penalty' => 'پورسانت پس از کاهنده',
                    'return_loss' => 'کسر مرجوعی',
                    'final_commission' => 'پورسانت نهایی',
                ],
                'filters' => [
                    'report_id' => 'گزارش',
                    'visitor_name' => 'ویزیتور',
                    'line_code' => 'لاین',
                ],
            ],
            'attendance' => [
                'title' => 'کارکرد تأییدشده',
                'table' => 'hr_attendance_entries',
                'date_field' => 'attendance_date',
                'user_field' => 'employee_id',
                'metrics' => [
                    'work_minutes' => 'دقایق کارکرد',
                    'late_minutes' => 'دقایق تأخیر',
                    'early_leave_minutes' => 'تعجیل در خروج',
                    'normal_overtime_minutes' => 'اضافه‌کار عادی',
                    'holiday_overtime_minutes' => 'اضافه‌کار تعطیل',
                ],
                'filters' => [
                    'attendance_date' => 'تاریخ کارکرد',
                    'employee_id' => 'کارمند',
                    'day_status' => 'وضعیت روز',
                    'is_holiday' => 'تعطیل',
                ],
            ],
            'kpi_scores' => [
                'title' => 'امتیازهای KPI',
                'table' => 'hr_kpi_scores',
                'user_field' => 'employee_id',
                'metrics' => [
                    'score' => 'امتیاز',
                    'period_id' => 'دوره KPI',
                    'criteria_id' => 'معیار KPI',
                ],
                'filters' => [
                    'employee_id' => 'کارمند',
                    'template_id' => 'قالب KPI',
                    'criteria_id' => 'معیار',
                    'period_id' => 'دوره',
                ],
            ],
            'management_reports' => [
                'title' => 'مقادیر گزارشات مدیریتی',
                'table' => 'management_report_values',
                'metrics' => [
                    'value_number' => 'مقدار عددی گزارش',
                    'submission_id' => 'شناسه گزارش',
                    'field_id' => 'شناسه فیلد',
                ],
                'filters' => [
                    'submission_id' => 'گزارش',
                    'field_id' => 'فیلد',
                ],
            ],
            'offer_budget_snapshot' => [
                'title' => 'Snapshot بودجه آفر',
                'table' => 'sales_offer_budget_requests',
                'date_field' => 'date_to',
                'user_field' => 'requested_by',
                'metrics' => [
                    'purchase_price' => 'قیمت خرید',
                    'requested_offer_qty' => 'تعداد آفر',
                    'sold_qty' => 'تعداد فروش',
                    'sold_amount' => 'مبلغ فروش',
                    'provisional_offer_rate' => 'نرخ آفر',
                    'provisional_budget' => 'بودجه مقدماتی',
                ],
                'filters' => [
                    'requested_by' => 'ثبت‌کننده',
                    'sales_line' => 'لاین',
                    'product_code' => 'کالا',
                    'brand_name' => 'برند',
                    'period_key' => 'دوره',
                ],
            ],
            'payroll_values' => [
                'title' => 'مقادیر کنترل‌شده فیش حقوقی',
                'table' => null,
                'metrics' => [
                    'base_salary' => 'حقوق پایه',
                    'housing_allowance' => 'حق مسکن',
                    'food_allowance' => 'بن کارگری',
                    'overtime' => 'اضافه‌کاری',
                    'bonus' => 'پاداش',
                    'insurance' => 'بیمه',
                    'tax' => 'مالیات',
                    'loan' => 'وام',
                    'advance' => 'مساعده',
                    'total_earnings' => 'جمع مزایا',
                    'total_deductions' => 'جمع کسورات',
                    'net_pay' => 'خالص پرداختی',
                ],
                'filters' => [
                    'employee_no' => 'شماره پرسنلی',
                    'department' => 'واحد',
                    'job_title' => 'عنوان شغلی',
                ],
            ],
        ];
    }

    public static function source(string $key): ?array
    {
        return self::sources()[$key] ?? null;
    }

    public static function loadRows(string $sourceKey, array $context = []): array
    {
        $source = self::source($sourceKey);
        if (!$source) throw new InvalidArgumentException('منبع داده فرمول معتبر نیست.');
        if ($sourceKey === 'sample_input' || ($source['table'] ?? null) === null) {
            return [is_array($context['sample_values'] ?? null) ? $context['sample_values'] : []];
        }
        $table = (string)($source['table'] ?? '');
        if ($table === '' || !Database::tableExists($table)) return [];

        $columns = array_values(array_unique(array_merge(
            array_keys($source['metrics']),
            array_keys($source['filters'])
        )));
        $where = [];
        $params = [];
        $dateField = (string)($source['date_field'] ?? '');
        if ($dateField !== '' && !empty($context['date_from'])) {
            $where[] = "`{$dateField}`>=?";
            $params[] = $context['date_from'];
        }
        if ($dateField !== '' && !empty($context['date_to'])) {
            $where[] = "`{$dateField}`<=?";
            $params[] = $context['date_to'];
        }
        foreach (['line_code', 'product_code', 'customer_code'] as $field) {
            if (!empty($context[$field]) && isset($source['filters'][$field])) {
                $where[] = "`{$field}`=?";
                $params[] = $context[$field];
            }
        }
        $userId = (int)($context['user_id'] ?? 0);
        $userField = (string)($source['user_field'] ?? '');
        if ($userId > 0 && $userField !== '' && isset($source['filters'][$userField])) {
            $user = Database::fetch('SELECT id,name,employee_no FROM users WHERE id=?', [$userId]);
            if ($user) {
                $value = $userField === 'employee_id' || $userField === 'requested_by'
                    ? $userId
                    : ($userField === 'visitor_code' ? ($user['employee_no'] ?? '') : ($user['name'] ?? ''));
                if ($value !== '') {
                    $where[] = "`{$userField}`=?";
                    $params[] = $value;
                }
            }
        }
        $select = implode(',', array_map(static fn(string $column): string => "`{$column}`", $columns));
        $sql = "SELECT {$select} FROM `{$table}`" . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' LIMIT 1000';
        return Database::fetchAll($sql, $params);
    }
}
