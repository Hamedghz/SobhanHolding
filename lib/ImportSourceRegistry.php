<?php

require_once __DIR__ . '/../core/SalesDataNormalizer.php';
require_once __DIR__ . '/../core/SalesAggregateImportService.php';
require_once __DIR__ . '/../core/InventoryImportService.php';

final class ImportSourceRegistry
{
    public static function all(): array
    {
        return [
            'sales_aggregate' => [
                'title' => 'ورود تجمیعی فروش',
                'tables' => ['tblsales', 'tbltajmi'],
                'sheets' => ['گزارش تجمیعی فروش', 'تجمیعی'],
                'signature' => ['نوع فاکتور','شماره فاکتور','تاریخ','کد فروشنده','کد مشتری','کد کالا','تعداد کل','مبلغ ناخالص','مبلغ خالص'],
                'mappings' => SalesDataNormalizer::mappingDefinitions(),
                'target_table' => 'sales_aggregate_rows',
            ],
            'purchase_aggregate' => [
                'title' => 'ورود تجمیعی خرید',
                'tables' => ['tblbuy'],
                'sheets' => ['گزارش تجمیعی خرید'],
                'signature' => ['نوع فاکتور','شماره فاکتور','تاریخ','کد تامین کننده','کد کالا','تعداد کل','ناخالص','مبلغ خالص'],
                'mappings' => self::mappings([
                    'ردیف'=>'source_row_id','نوع فاکتور'=>'invoice_type','شماره فاکتور'=>'invoice_number',
                    'شماره فاکتور تامین کننده'=>'supplier_invoice_number','تاریخ'=>'invoice_date_raw',
                    'کد تامین کننده'=>'supplier_code','نام تامین کننده'=>'supplier_name',
                    'کد تولیدکننده کالا'=>'manufacturer_code','نام تولیدکننده کالا'=>'manufacturer_name',
                    'کد لاین'=>'line_code','نام لاین'=>'line_name','کد کالا'=>'product_code','نام کالا'=>'product_name',
                    'تعداد کل'=>'quantity','ناخالص'=>'gross_amount','مبلغ ناخالص'=>'gross_amount',
                    'مبلغ تخفیف سطری'=>'discount_amount','مجموع مبلغ تخفیف سطری'=>'discount_amount',
                    'مبلغ خالص'=>'net_amount','کد برند'=>'brand_code','نام برند'=>'brand_name',
                ], ['invoice_number','invoice_date_raw','supplier_code','product_code','quantity','gross_amount','net_amount']),
                'target_table' => 'purchase_aggregate_rows',
            ],
            'inventory_aggregate' => [
                'title' => 'ورود موجودی انبار',
                'tables' => ['tblanbar'],
                'sheets' => ['tblanbar'],
                'signature' => ['کد کالا','نام کالا','تعداد در کارتن','موجودی دوره کل','موجودی فعلی کل','برند'],
                'mappings' => InventoryImportService::mappingDefinitions(),
                'target_table' => 'inventory_aggregate_rows',
                'snapshot_required' => true,
                'period_id_required' => true,
            ],
            'sales_targets' => [
                'title' => 'ورود تارگت',
                'tables' => ['tbltarget', 'tbltargrt', 'target'],
                'sheets' => ['تارگت', 'target'],
                'signature' => ['کد لاین','کد کالا','کد فروشنده','تارگت تعداد','تارگت مبلغ'],
                'signatures' => [
                    ['کد لاین','کد کالا','کد فروشنده','تارگت تعداد','تارگت مبلغ'],
                    ['سال','ماه','کد لاین','کد کالا','کد فروشنده','تارگت تعداد','تارگت مبلغ'],
                ],
                'mappings' => self::mappings([
                    'شناسه دوره'=>'period_id','سال'=>'target_year','ماه'=>'target_month','کد لاین'=>'line_code','کد کالا'=>'product_code','نام کالا'=>'product_name',
                    'کد برند'=>'brand_code','نام برند'=>'brand_name',
                    'اولویت'=>'priority_code','کد فروشنده'=>'visitor_code','کد سرپرست'=>'supervisor_code',
                    'تارگت تعداد'=>'target_quantity','تارگت قطعه'=>'target_quantity','تارگت مبلغ'=>'target_amount',
                    'درصد تخصیص'=>'allocation_percent',
                ], ['line_code','product_code','visitor_code']),
                'target_table' => 'sales_targets',
            ],
            'product_priorities' => [
                'title' => 'ورود اولویت کالا',
                'tables' => ['tblolaviyat', 'olaviyat'],
                'sheets' => ['اولویت کالا', 'اولویت'],
                'signature' => ['کد کالا','اولویت'],
                'mappings' => self::mappings([
                    'شناسه دوره'=>'period_id',
                    'کد کالا'=>'product_code','نام کالا'=>'product_name','کد برند'=>'brand_code','نام برند'=>'brand_name',
                    'برند'=>'brand_name','اولویت'=>'priority_code','رتبه اولویت'=>'priority_rank','موجودی'=>'inventory_quantity',
                    'موجودی کالا'=>'inventory_quantity','ارزش موجودی'=>'inventory_value','وضعیت'=>'status',
                ], ['product_code','priority_code']),
                'target_table' => 'product_priorities',
            ],
            'customer_coefficients' => [
                'title' => 'ورود ضرایب صنف',
                'tables' => ['tblzarib', 'zarib'],
                'sheets' => ['ضرایب صنف', 'ضریب صنف'],
                'signature' => ['کد صنف','ضریب'],
                'signatures' => [
                    ['کد صنف','ضریب'],
                    ['نام صنف','ضریب'],
                ],
                'mappings' => self::mappings([
                    'شناسه دوره'=>'period_id',
                    'کد صنف'=>'customer_class_code','نام صنف'=>'customer_class_title','ضریب'=>'coefficient',
                    'تاریخ شروع'=>'effective_from_raw','تاریخ پایان'=>'effective_to_raw',
                ], ['coefficient']),
                'target_table' => 'sales_customer_class_coefficients',
            ],
            'attendance' => [
                'title' => 'ورود کارکرد',
                'tables' => ['tblattendance', 'attendance', 'کارکرد'],
                'sheets' => ['کارکرد', 'حضور و غیاب'],
                'signature' => ['کد پرسنلی','ساعت ورود','ساعت خروج','کارکرد'],
                'signatures' => [
                    ['کد پرسنلی','ساعت ورود','ساعت خروج','کارکرد'],
                    ['کد سیستم کارا','ساعت ورود','ساعت خروج','کارکرد'],
                    ['کد پرسنلی','ساعت ورود','تاخیر (دقیقه)'],
                ],
                'mappings' => self::mappings([
                    'کد سیستم کارا'=>'kara_system_code','کد کارا'=>'kara_system_code',
                    'کد پرسنلی'=>'employee_no','شماره پرسنلی'=>'employee_no','نام'=>'first_name','نام خانوادگی'=>'last_name','تاریخ'=>'attendance_date_raw',
                    'ساعت شروع به کار'=>'approved_start_time','ساعت ورود'=>'actual_in_time','ساعت پایان کار'=>'approved_end_time',
                    'ساعت خروج'=>'actual_out_time','تاخیر'=>'late_minutes','اضافه کاری'=>'overtime_minutes',
                    'کارکرد'=>'work_minutes','اختلاف ساعت کاری'=>'work_difference_minutes','ماموریت'=>'mission_value',
                    'شرح ماموریت'=>'mission_details','مرخصی'=>'leave_value','نوع مرخصی'=>'leave_type',
                    'ساعت کاری روزانه'=>'daily_work_minutes',
                ], []),
                'target_table' => 'hr_attendance_entries',
                'snapshot_fallback' => true,
            ],
        ];
    }

    public static function get(string $key): array
    {
        $all = self::all();
        if (!isset($all[$key])) throw new InvalidArgumentException('نوع منبع ورود اطلاعات معتبر نیست.');
        return $all[$key];
    }

    public static function labels(): array
    {
        $out = [];
        foreach (self::all() as $key => $source) $out[$key] = $source['title'];
        return $out;
    }

    public static function normalizedAliases(array $values): array
    {
        return array_values(array_unique(array_map([SalesDataNormalizer::class, 'normalizeHeader'], $values)));
    }

    private static function mappings(array $headers, array $required): array
    {
        $numeric = [
            'source_row_id','quantity','gross_amount','discount_amount','net_amount','period_id','target_year','target_month','target_quantity','target_amount','allocation_percent',
            'priority_rank','inventory_quantity','inventory_value','coefficient',
        ];
        $out = [];
        foreach ($headers as $header => $key) {
            $out[] = [
                'source_header' => $header,
                'normalized_key' => $key,
                'required' => in_array($key, $required, true) ? 1 : 0,
                'data_type' => in_array($key, $numeric, true) ? 'decimal' : (str_ends_with($key, '_raw') ? 'date' : 'string'),
            ];
        }
        return $out;
    }
}
