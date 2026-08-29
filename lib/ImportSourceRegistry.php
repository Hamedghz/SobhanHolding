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
                'source_profile' => SalesDataNormalizer::SALES_AGGREGATE_PROFILE,
                'canonical_table' => SalesDataNormalizer::SALES_AGGREGATE_TABLE,
                'canonical_sheet' => SalesDataNormalizer::SALES_AGGREGATE_SHEET,
                'canonical_headers' => SalesDataNormalizer::CANONICAL_HEADERS,
                'tables' => [SalesDataNormalizer::SALES_AGGREGATE_TABLE, 'tblsales', 'tbltajmi', 'tblsales_raw'],
                'sheets' => ['گزارش تجمیعی فروش', 'تجمیعی'],
                'signature' => ['نوع فاکتور','شماره فاکتور','تاریخ','کد فروشنده','کد مشتری','کد کالا','تعداد کل','مبلغ ناخالص','مبلغ خالص'],
                'mappings' => SalesDataNormalizer::mappingDefinitions(),
                'target_table' => 'sales_aggregate_rows',
            ],
            'purchase_aggregate' => [
                'title' => 'ورود تجمیعی خرید',
                'source_profile' => 'ERP_PURCHASE_AGGREGATE_RAW_V1',
                'canonical_table' => 'tblbuy_raw',
                'canonical_sheet' => 'گزارش تجمیعی خرید',
                'canonical_headers' => self::purchaseCanonicalHeaders(),
                'tables' => ['tblbuy_raw', 'tblbuy'],
                'sheets' => ['گزارش تجمیعی خرید', 'tblbuy'],
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
                'source_profile' => 'ERP_INVENTORY_AGGREGATE_RAW_V1',
                'canonical_table' => 'tblwh_raw',
                'canonical_sheet' => 'گزارش موجودی انبار1',
                'canonical_headers' => self::inventoryCanonicalHeaders(),
                'tables' => ['tblwh_raw', 'tblwh', 'tblanbar'],
                'sheets' => ['گزارش موجودی انبار1', 'tblwh', 'tblanbar'],
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

    public static function canonicalHeaderReport(array $headers, array $source): array
    {
        $expected = array_values(array_map('strval', $source['canonical_headers'] ?? []));
        $actual = array_values(array_map('strval', $headers));
        $expectedNormalized = array_map([SalesDataNormalizer::class, 'normalizeHeader'], $expected);
        $actualNormalized = array_map([SalesDataNormalizer::class, 'normalizeHeader'], $actual);
        $expectedCounts = array_count_values(array_filter($expectedNormalized, static fn(string $value): bool => $value !== ''));
        $actualCounts = array_count_values(array_filter($actualNormalized, static fn(string $value): bool => $value !== ''));
        $missing = [];
        foreach ($expectedCounts as $normalized => $count) {
            $remaining = $count - ($actualCounts[$normalized] ?? 0);
            if ($remaining > 0) {
                for ($i = 0; $i < $remaining; $i++) {
                    $index = array_search($normalized, $expectedNormalized, true);
                    $missing[] = $index === false ? $normalized : $expected[$index];
                }
            }
        }
        $extra = [];
        foreach ($actualCounts as $normalized => $count) {
            $remaining = $count - ($expectedCounts[$normalized] ?? 0);
            if ($remaining > 0) {
                for ($i = 0; $i < $remaining; $i++) {
                    $index = array_search($normalized, $actualNormalized, true);
                    $extra[] = $index === false ? $normalized : $actual[$index];
                }
            }
        }
        $duplicates = [];
        foreach ($actualCounts as $normalized => $count) {
            if ($count > 1) {
                $index = array_search($normalized, $actualNormalized, true);
                $duplicates[] = $index === false ? $normalized : $actual[$index];
            }
        }
        $matched = 0;
        foreach ($expectedNormalized as $index => $header) if (($actualNormalized[$index] ?? null) === $header) $matched++;
        return [
            'source_profile' => (string)($source['source_profile'] ?? ''),
            'expected_count' => count($expected),
            'actual_count' => count($actual),
            'exact_matched_headers' => $matched,
            'missing_headers' => array_values(array_unique($missing)),
            'extra_headers' => array_values(array_unique($extra)),
            'duplicate_headers' => array_values(array_unique($duplicates)),
            'order_mismatch' => $expectedNormalized !== $actualNormalized,
            'is_exact' => $matched === count($expected) && !$missing && !$extra && !$duplicates,
        ];
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

    private static function purchaseCanonicalHeaders(): array
    {
        return ['ردیف','نوع فاکتور','شماره فاکتور','شماره فاکتور تامین کننده','تاریخ','کد تامین کننده','نام  تامین کننده','کد تولیدکننده کالا','نام تولیدکننده کالا','کد لاین','نام لاین','کد کالا','نام کالا','تعداد در کارتن','کد گروه','نام گروه','تعداد کارتن','تعداد جز','تعداد کل','قیمت واحد','ناخالص','%تخفیف','%تخفیف 2','%تخفیف3','مبلغ تخفیف سطری','ناخالص - تخفیف','مبلغ مالیات','مبلغ عوارض','مبلغ خالص','وزن','حجم','کد انبار','نام انبار','نام شعبه','کد برند','نام برند','سایر توضیحات','درصد مالیات','درصد عوارض','تاریخ فاکتور تامین کننده'];
    }

    private static function inventoryCanonicalHeaders(): array
    {
        return ['ردیف','کد کالا','نام کالا','کد تولیدکننده','نام تولیدکننده','تعدادفروش کارتن','تعداد فروش جز','تعداد کل فروش','مبلغ کل فروش','مبلغ تخفیف فروش','مبلغ مالیات فروش','مبلغ عوارض فروش','تعداد کارتن برگشت از فروش','مبلغ قابل پرداخت فروش','تعداد جز برگشت از فروش','تعداد کل برگشت از فروش','مبلغ کل برگشت از فروش','مبلغ تخفیف برگشت از فروش','مبلغ مالیات برگشت از فروش','مبلغ عوارض برگشت از فروش','مبلغ قابل پرداخت برگشت از فروش','تعداد کارتن خرید','تعداد جز خرید','تعداد کل خرید','مبلغ کل خرید','مبلغ تخفیف خرید','مبلغ مالیات خرید','مبلغ عوارض خرید','مبلغ قابل پرداخت خرید','تعداد کارتن برگشت از خرید','تعداد جز برگشت از خرید','تعداد کل برگشت از خرید','مبلغ کل برگشت از خرید','مبلغ تخفیف برگشت از خرید','مبلغ مالیات برگشت از خرید','مبلغ عوارض برگشت از خرید','مبلغ قابل پرداخت برگشت از خرید','تعداد کارتن ابتدای دوره','تعداد جز ابتدای دوره','تعداد کل ابتدای دوره','مبلغ کل ابتدای دوره','مبلغ تخفیف ابتدای دوره','مبلغ مالیات ابتدای دوره','مبلغ عوارض ابتدای دوره','مبلغ قابل پرداخت ابتدای دوره','تعداد کارتن وارده','تعداد جز وارده','تعداد کل وارده','مبلغ کل وارده','مبلغ تخفیف وارده','مبلغ مالیات وارده','مبلغ عوارض وارده','مبلغ قابل پرداخت وارده','تعداد کارتن صادره','تعداد جز صادره','تعداد کل صادره','مبلغ کل صادره','مبلغ تخفیف صادره','مبلغ مالیات صارده','مبلغ عوارض صادره','مبلغ قابل پرداخت صادره','موجودی دوره کارتن','موجودی دوره جز','موجودی دوره کل','تعداد در کارتن','آخرین قیمت تمام شده','آخرین قیمت خرید','ارزش ریالی بر اساس آخرین قیمت تمام شده','ارزش ریالی بر اساس قیمت فروش 1','قیمت مصرف کننده','قیمت فروش خرده','قیمت فروش عمده','قیمت فروش 3','قیمت فروش 4','قیمت فروش 5','قیمت فروش 6','قیمت فروش 7','قیمت فروش 8','قیمت فروش 9','قیمت فروش 10','قیمت فروش 11','قیمت فروش 12','پورسانت خورده','پورسانت عمده','پورسانت 3','پورسانت 4','پورسانت 5','پورسانت 6','پورسانت 7','پورسانت 8','پورسانت 9','پورسانت 10','پورسانت 11','پورسانت 12','وزن موجود','حجم موجود','کد گروه درختی','نام گروه درختی','بارکد','کد کنترلی','کد گروه','نام گروه','مدت وصول خرده','موجودی فعلی مبنا','موجودی فعلی جز','موجودی فعلی کل','قیمت مصرف کننده خرده','ورود','خروج','تعداد کارتن آخرین خرید','تاریخ آخرین خرید','اختلاف موجودی فعلی با موجودی کلی مبنا','اختلاف موجودی فعلی با موجودی کلی جز','اختلاف موجودی فعلی با موجودی کلی کل','واحد مبنا','واحد جز','درصد تخفیف نقدی','درصد تخفیف حواله','درصد تخفیف چک','قیمت تولید کننده','برند','قیمت فروش 1 با مالیات','قیمت فروش 2 با مالیات','قیمت فروش 3 با مالیات','سایز','سایر توضیحات','شناسه','کشور سازنده','نام گروه درختی سطح 1','نام گروه درختی سطح 2','نام گروه درختی سطح 3','نام گروه درختی سطح 4'];
    }
}
