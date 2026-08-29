<?php
require_once __DIR__ . '/JalaliDate.php';

class SalesDataNormalizer
{
    public const SALES_AGGREGATE_PROFILE = 'ERP_SALES_AGGREGATE_V1';
    public const SALES_AGGREGATE_TABLE = 'tbltajmii';
    public const SALES_AGGREGATE_SHEET = 'گزارش تجمیعی فروش';

    public const CANONICAL_HEADERS = [
        'ردیف','نوع فاکتور','شماره فاکتور','تاریخ','کد فروشنده','نام فروشنده','کد لاین','نام لاین','کد مشتری','نام مشتری',
        'کد کالا','نام کالا','تعداد در کارتن','کد تولیدکننده','نام تولیدکننده','کد گروه','نام گروه','تعداد کارتن','تعداد جز','تعداد کل',
        'کارتن خالص','قیمت واحد','مبلغ ناخالص','%تخفیف','مبغ تخفیف 1','%تخفیف 2','مبلغ تخفیف 2','%تخفیف3','مبلغ تخفیف 3','مجموع مبلغ تخفیف سطری',
        'ناخالص - تخفیف','مبلغ مالیات','مبلغ عوارض','جمع مالیات عوارض','مبلغ خالص','وزن','حجم','کد انبار','نام انبار','آدرس مشتری',
        'کد شعبه','نام شعبه','موبایل','تلفن','کد شهر','نام شهر','درجه مشتری','کد ملی','کد صنف','نام صنف',
        'مسیر','نام مسیر','کد مامور پخش','نام مامور پخش','کد راننده','نام راننده','کد سرپرست','نام سرپرست','کد مدیر فروش','نام مدیر فروش',
        'شماره خروجی','کلاس قیمت فروش','کد برند','نام برند','نحوه پرداخت','تاریخ تولد مشتری','تابلو مشتری','شماره ارجاع','واحد مبنا','واحد جز',
        'کد یکتا','% تخفیف 4','مبلغ تخفیف 4','% تخفیف 5','مبلغ تخفیف 5','نوع کلاس خرید','نوع پورسانت','شماره فاکتور فرعی','توضیحات','کد استان',
        'نام استان','کد نقش مشتری','شناسه کالا','وزن کالا','حجم کالا','شناسه','بهای تمام شده FiFO','بهای تمام شده میانگین','درصد مالیات','درصد عوارض',
        'شماره فرمول 1','شماره فرمول 2','شماره فرمول 3','شماره فرمول 4','شماره فرمول 5','اسم فرمول 1','اسم فرمول 2','اسم فرمول 3','اسم فرمول 4','اسم فرمول 5',
        'تاریخ ایجاد مشتری','کد گروه کالا درختی','نام گروه کالا درختی','نام راننده از فاکتور','قیمت تمام شده خرید','ماه گردش','وزنی','مصرف کننده','مصرف کننده کالا',
    ];
    public const REQUIRED = [
        'شماره فاکتور' => 'invoice_number', 'تاریخ' => 'invoice_date_raw',
        'کد فروشنده' => 'visitor_code', 'نام فروشنده' => 'visitor_name',
        'کد مشتری' => 'customer_code', 'نام مشتری' => 'customer_name',
        'کد کالا' => 'product_code', 'نام کالا' => 'product_name',
        'تعداد کل' => 'quantity', 'مبلغ ناخالص' => 'gross_amount',
        'مجموع مبلغ تخفیف سطری' => 'discount_amount', 'مبلغ خالص' => 'net_amount',
    ];

    public static function rawCanonicalHeaders(): array
    {
        $derived = array_values(array_filter(
            self::CANONICAL_HEADERS,
            static fn(string $header): bool => !in_array($header, ['%تخفیف 2','مبلغ تخفیف 2','%تخفیف3','مبلغ تخفیف 3','وزن','حجم'], true)
        ));
        return array_merge($derived, ['Column1','Column2','Column3','Column4','Column5','Column6']);
    }

    public static function normalizePersianArabicDigits(mixed $value): string
    {
        return strtr(trim((string)$value), [
            '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
            '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9',
        ]);
    }

    public static function normalizeHeader(mixed $value): string
    {
        $value = self::normalizePersianArabicDigits($value);
        $value = preg_replace('/^(?:\xEF\xBB\xBF|\x{FEFF})/u', '', $value) ?? $value;
        $value = strtr($value, ['ي'=>'ی','ى'=>'ی','ك'=>'ک','ۀ'=>'ه','ة'=>'ه','‌'=>' ','‍'=>' ','ـ'=>'',"\r"=>' ',"\n"=>' ']);
        $value = preg_replace('/\s*%\s*/u', '%', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        $normalized = function_exists('mb_strtolower') ? mb_strtolower(trim($value), 'UTF-8') : strtolower(trim($value));
        return self::headerAliases()[$normalized] ?? $normalized;
    }

    public static function headerAliases(): array
    {
        static $aliases;
        if ($aliases !== null) return $aliases;
        $raw = [
            'مبغ تخفیف 1' => 'مبلغ تخفیف 1',
            'نام  تامین کننده' => 'نام تامین کننده',
            'mobile' => 'موبایل',
            'مبلغ مالیات صارده' => 'مبلغ مالیات صادره',
            'تعدادفروش کارتن' => 'تعداد فروش کارتن',
            'مدیرفروش' => 'مدیر فروش',
            'کدپرسنلی' => 'کد پرسنلی',
            'نام خانوادگي' => 'نام خانوادگی',
            'ساعت شروع بكار' => 'ساعت شروع به کار',
            'ساعت پايان كار' => 'ساعت پایان کار',
            'اضافه كاري' => 'اضافه کاری',
        ];
        $aliases = [];
        foreach ($raw as $from => $to) {
            $from = trim(preg_replace('/\s+/u', ' ', strtr($from, ['ي'=>'ی','ى'=>'ی','ك'=>'ک','‌'=>' '])) ?? $from);
            $to = trim(preg_replace('/\s+/u', ' ', strtr($to, ['ي'=>'ی','ى'=>'ی','ك'=>'ک','‌'=>' '])) ?? $to);
            $from = function_exists('mb_strtolower') ? mb_strtolower($from, 'UTF-8') : strtolower($from);
            $to = function_exists('mb_strtolower') ? mb_strtolower($to, 'UTF-8') : strtolower($to);
            $aliases[$from] = $to;
        }
        return $aliases;
    }

    public static function normalizeHeaders(array $headers): array
    {
        return array_map([self::class, 'normalizeHeader'], $headers);
    }

    public static function normalizeDate(mixed $value): ?string
    {
        $value = self::normalizePersianArabicDigits($value);
        if ($value === '') return null;
        if (is_numeric($value)) {
            $serial = (float)$value;
            if ($serial >= 1 && $serial <= 100000) {
                $seconds = (int)round($serial * 86400);
                return (new DateTimeImmutable('1899-12-30', new DateTimeZone('UTC')))->modify("+{$seconds} seconds")->format('Y-m-d');
            }
        }
        $normalized = JalaliDate::normalize($value);
        return JalaliDate::toGregorian($normalized);
    }

    public static function normalizeDecimal(mixed $value): ?string
    {
        $value = self::normalizePersianArabicDigits($value);
        if ($value === '') return null;
        $value = strtr($value, ['٬'=>'', '،'=>'', ','=>'', ' '=>'', "\xC2\xA0"=>'', '−'=>'-', '%'=>'']);
        if (!preg_match('/^[+-]?(?:(?:\d+(?:\.\d*)?|\.\d+)(?:[eE][+-]?\d+)?)$/', $value)) return null;
        if (stripos($value, 'e') !== false) {
            $value = sprintf('%.10F', (float)$value);
        }
        $number = str_contains($value, '.') ? rtrim(rtrim($value, '0'), '.') : $value;
        if ($number === '' || $number === '-' || $number === '+') return '0';
        return $number;
    }

    public static function mappingDefinitions(): array
    {
        $canonical = [
            'ردیف'=>'source_row_number','نوع فاکتور'=>'invoice_type','شماره فاکتور'=>'invoice_number','تاریخ'=>'invoice_date_raw','کد فروشنده'=>'visitor_code','نام فروشنده'=>'visitor_name',
            'کد لاین'=>'line_code','نام لاین'=>'line_name','کد مشتری'=>'customer_code','نام مشتری'=>'customer_name','کد کالا'=>'product_code','نام کالا'=>'product_name',
            'تعداد در کارتن'=>'units_per_carton','کد تولیدکننده'=>'manufacturer_code','نام تولیدکننده'=>'manufacturer_name','کد گروه'=>'group_code','نام گروه'=>'group_name',
            'تعداد کارتن'=>'carton_quantity','تعداد جز'=>'unit_quantity','تعداد کل'=>'quantity','کارتن خالص'=>'net_carton_quantity','قیمت واحد'=>'unit_price',
            'مبلغ ناخالص'=>'gross_amount','%تخفیف'=>'discount_percent_1','مبغ تخفیف 1'=>'discount_amount_1','%تخفیف 2'=>'discount_percent_2',
            'مبلغ تخفیف 2'=>'discount_amount_2','%تخفیف3'=>'discount_percent_3','مبلغ تخفیف 3'=>'discount_amount_3',
            'مجموع مبلغ تخفیف سطری'=>'discount_amount','ناخالص - تخفیف'=>'gross_after_discount','مبلغ مالیات'=>'tax_amount','مبلغ عوارض'=>'duty_amount',
            'جمع مالیات عوارض'=>'tax_duty_amount','مبلغ خالص'=>'net_amount','وزن'=>'weight','حجم'=>'volume','کد انبار'=>'warehouse_code','نام انبار'=>'warehouse_name',
            'آدرس مشتری'=>'customer_address','کد شعبه'=>'branch_code','نام شعبه'=>'branch_name','موبایل'=>'mobile','تلفن'=>'phone','کد شهر'=>'city_code','نام شهر'=>'city_name',
            'درجه مشتری'=>'customer_grade','کد ملی'=>'national_code','کد صنف'=>'customer_class_code','نام صنف'=>'customer_class_name','مسیر'=>'route_code','نام مسیر'=>'route_name',
            'کد مامور پخش'=>'distributor_code','نام مامور پخش'=>'distributor_name','کد راننده'=>'driver_code','نام راننده'=>'driver_name','کد سرپرست'=>'supervisor_code',
            'نام سرپرست'=>'supervisor_name','کد مدیر فروش'=>'sales_manager_code','نام مدیر فروش'=>'sales_manager_name','شماره خروجی'=>'dispatch_number',
            'کلاس قیمت فروش'=>'sales_price_class','کد برند'=>'brand_code','نام برند'=>'brand_name','نحوه پرداخت'=>'payment_method','تاریخ تولد مشتری'=>'customer_birth_date',
            'تابلو مشتری'=>'customer_sign','شماره ارجاع'=>'reference_number','واحد مبنا'=>'base_unit','واحد جز'=>'sub_unit','کد یکتا'=>'unique_code',
            '% تخفیف 4'=>'discount_percent_4','مبلغ تخفیف 4'=>'discount_amount_4','% تخفیف 5'=>'discount_percent_5','مبلغ تخفیف 5'=>'discount_amount_5',
            'نوع کلاس خرید'=>'purchase_class_type','نوع پورسانت'=>'commission_type','شماره فاکتور فرعی'=>'sub_invoice_number','توضیحات'=>'description','کد استان'=>'province_code','نام استان'=>'province_name',
            'کد نقش مشتری'=>'customer_role_code','شناسه کالا'=>'product_identifier','وزن کالا'=>'product_weight','حجم کالا'=>'product_volume','شناسه'=>'identifier',
            'بهای تمام شده FiFO'=>'fifo_cost','بهای تمام شده میانگین'=>'average_cost','درصد مالیات'=>'tax_percent','درصد عوارض'=>'duty_percent',
            'شماره فرمول 1'=>'formula_number_1','شماره فرمول 2'=>'formula_number_2','شماره فرمول 3'=>'formula_number_3','شماره فرمول 4'=>'formula_number_4','شماره فرمول 5'=>'formula_number_5',
            'اسم فرمول 1'=>'formula_name_1','اسم فرمول 2'=>'formula_name_2','اسم فرمول 3'=>'formula_name_3','اسم فرمول 4'=>'formula_name_4','اسم فرمول 5'=>'formula_name_5',
            'تاریخ ایجاد مشتری'=>'customer_created_date','کد گروه کالا درختی'=>'product_tree_group_code','نام گروه کالا درختی'=>'product_tree_group_name','نام راننده از فاکتور'=>'invoice_driver_name',
            'قیمت تمام شده خرید'=>'purchase_cost','ماه گردش'=>'turnover_month','وزنی'=>'is_weighted','مصرف کننده'=>'consumer','مصرف کننده کالا'=>'product_consumer',
        ];
        $legacyAliases = [
            'Mobile'=>'mobile',
            'Code P1'=>'code_p1','Name P1'=>'name_p1','Kind1'=>'kind_1','Maliat1'=>'maliat_1','codep2'=>'code_p2','namep2'=>'name_p2',
            'number1'=>'number_1','name1'=>'name_1','number2'=>'number_2','name2'=>'name_2','number3'=>'number_3','name3'=>'name_3',
            'number4'=>'number_4','name4'=>'name_4','number5'=>'number_5','name5'=>'name_5','codeg'=>'code_g','nameg1'=>'name_g1','glevel'=>'group_level',
            'lastlevel'=>'last_level','topcode'=>'top_code','vaz'=>'vaz',
            'formula Num11'=>'formula_num_11','formula Num21'=>'formula_num_21','formula Num31'=>'formula_num_31','formula Num41'=>'formula_num_41','formula Num51'=>'formula_num_51',
            'groupcode1'=>'group_code_1','ضریب'=>'coefficient','ضریب فروش'=>'sales_coefficient','اولویت'=>'priority',
        ];
        $result = [];
        $requiredKeys = array_values(self::REQUIRED);
        $numericKeys = ['source_row_number','quantity','units_per_carton','carton_quantity','unit_quantity','net_carton_quantity','unit_price','gross_amount','discount_amount','gross_after_discount','net_amount','tax_amount','duty_amount','tax_duty_amount','weight','volume','product_weight','product_volume','fifo_cost','average_cost','purchase_cost','coefficient','sales_coefficient'];
        foreach ($canonical + $legacyAliases as $header => $key) {
            $result[] = [
                'source_header' => $header,
                'normalized_key' => $key,
                'required' => in_array($key, $requiredKeys, true) ? 1 : 0,
                'data_type' => $key === 'invoice_date_raw' ? 'date' : (in_array($key, $numericKeys, true) || str_contains($key, 'percent') || str_contains($key, 'amount') ? 'decimal' : 'string'),
                'status' => isset($canonical[$header]) ? 'mapped' : 'optional',
                'reason' => isset($canonical[$header]) ? 'در raw_json حفظ و به کلید داخلی نگاشت می‌شود.' : 'نام قدیمی فقط برای سازگاری ورودی پذیرفته می‌شود.',
            ];
        }
        return $result;
    }

    public static function canonicalMappingDefinitions(): array
    {
        return array_values(array_filter(
            self::mappingDefinitions(),
            static fn(array $mapping): bool => in_array($mapping['source_header'], self::CANONICAL_HEADERS, true)
        ));
    }
}
