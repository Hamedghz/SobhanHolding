<?php
require_once __DIR__ . '/JalaliDate.php';

class SalesDataNormalizer
{
    public const REQUIRED = [
        'شماره فاکتور' => 'invoice_number', 'تاریخ' => 'invoice_date_raw',
        'کد فروشنده' => 'visitor_code', 'نام فروشنده' => 'visitor_name',
        'کد مشتری' => 'customer_code', 'نام مشتری' => 'customer_name',
        'کد کالا' => 'product_code', 'نام کالا' => 'product_name',
        'تعداد کل' => 'quantity', 'مبلغ ناخالص' => 'gross_amount',
        'مجموع مبلغ تخفیف سطری' => 'discount_amount', 'مبلغ خالص' => 'net_amount',
    ];

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
        $value = strtr($value, ['ي'=>'ی','ى'=>'ی','ك'=>'ک','ۀ'=>'ه','ة'=>'ه','‌'=>' ','ـ'=>'']);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        return mb_strtolower(trim($value));
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
        if (!preg_match('/^[+-]?(?:\d+(?:\.\d*)?|\.\d+)$/', $value)) return null;
        $number = str_contains($value, '.') ? rtrim(rtrim($value, '0'), '.') : $value;
        if ($number === '' || $number === '-' || $number === '+') return '0';
        return $number;
    }

    public static function mappingDefinitions(): array
    {
        $headers = [
            'نوع فاکتور'=>'invoice_type','شماره فاکتور'=>'invoice_number','تاریخ'=>'invoice_date_raw','کد فروشنده'=>'visitor_code','نام فروشنده'=>'visitor_name',
            'کد لاین'=>'line_code','نام لاین'=>'line_name','کد مشتری'=>'customer_code','نام مشتری'=>'customer_name','کد کالا'=>'product_code','نام کالا'=>'product_name',
            'تعداد در کارتن'=>'units_per_carton','کد تولیدکننده'=>'manufacturer_code','نام تولیدکننده'=>'manufacturer_name','کد گروه'=>'group_code','نام گروه'=>'group_name',
            'تعداد کارتن'=>'carton_quantity','تعداد جز'=>'unit_quantity','تعداد کل'=>'quantity','کارتن خالص'=>'net_carton_quantity','قیمت واحد'=>'unit_price',
            'مبلغ ناخالص'=>'gross_amount','%تخفیف'=>'discount_percent_1','مبغ تخفیف 1'=>'discount_amount_1','%تخفیف 2'=>'discount_percent_2',
            'مبلغ تخفیف 2'=>'discount_amount_2','%تخفیف3'=>'discount_percent_3','مبلغ تخفیف 3'=>'discount_amount_3',
            'مجموع مبلغ تخفیف سطری'=>'discount_amount','ناخالص - تخفیف'=>'gross_after_discount','مبلغ مالیات'=>'tax_amount','مبلغ عوارض'=>'duty_amount',
            'جمع مالیات عوارض'=>'tax_duty_amount','مبلغ خالص'=>'net_amount','وزن'=>'weight','حجم'=>'volume','کد انبار'=>'warehouse_code','نام انبار'=>'warehouse_name',
            'آدرس مشتری'=>'customer_address','کد شعبه'=>'branch_code','نام شعبه'=>'branch_name','Mobile'=>'mobile','موبایل'=>'mobile','تلفن'=>'phone','کد شهر'=>'city_code','نام شهر'=>'city_name',
            'درجه مشتری'=>'customer_grade','کد ملی'=>'national_code','کد صنف'=>'customer_class_code','نام صنف'=>'customer_class_name','مسیر'=>'route_code','نام مسیر'=>'route_name',
            'کد مامور پخش'=>'distributor_code','نام مامور پخش'=>'distributor_name','کد راننده'=>'driver_code','نام راننده'=>'driver_name','کد سرپرست'=>'supervisor_code',
            'نام سرپرست'=>'supervisor_name','کد مدیر فروش'=>'sales_manager_code','نام مدیر فروش'=>'sales_manager_name','شماره خروجی'=>'dispatch_number',
            'کلاس قیمت فروش'=>'sales_price_class','کد برند'=>'brand_code','نام برند'=>'brand_name','نحوه پرداخت'=>'payment_method','تاریخ تولد مشتری'=>'customer_birth_date',
            'تابلو مشتری'=>'customer_sign','شماره ارجاع'=>'reference_number','واحد مبنا'=>'base_unit','واحد جز'=>'sub_unit','کد یکتا'=>'unique_code',
            '% تخفیف 4'=>'discount_percent_4','مبلغ تخفیف 4'=>'discount_amount_4','% تخفیف 5'=>'discount_percent_5','مبلغ تخفیف 5'=>'discount_amount_5',
            'gn'=>'gn','pn'=>'pn','شماره فاکتور فرعی'=>'sub_invoice_number','توضیحات'=>'description','کد استان'=>'province_code','نام استان'=>'province_name',
            'کد نقش مشتری'=>'customer_role_code','شناسه کالا'=>'product_identifier','وزن کالا'=>'product_weight','حجم کالا'=>'product_volume','شناسه'=>'identifier',
            'بهای تمام شده FiFO'=>'fifo_cost','بهای تمام شده میانگین'=>'average_cost','درصد مالیات'=>'tax_percent','درصد عوارض'=>'duty_percent',
            'شماره فرمول 1'=>'formula_number_1','شماره فرمول 2'=>'formula_number_2','شماره فرمول 3'=>'formula_number_3','شماره فرمول 4'=>'formula_number_4','شماره فرمول 5'=>'formula_number_5',
            'تاریخ ایجاد مشتری'=>'customer_created_date','کد گروه کالا درختی'=>'product_tree_group_code','نام راننده از فاکتور'=>'invoice_driver_name',
            'قیمت تمام شده خرید'=>'purchase_cost','ماه گردش'=>'turnover_month','وزنی'=>'is_weighted','مصرف کننده'=>'consumer','مصرف کننده کالا'=>'product_consumer',
            'Code P1'=>'code_p1','Name P1'=>'name_p1','Kind1'=>'kind_1','Maliat1'=>'maliat_1','codep2'=>'code_p2','namep2'=>'name_p2',
            'number1'=>'number_1','name1'=>'name_1','number2'=>'number_2','name2'=>'name_2','number3'=>'number_3','name3'=>'name_3',
            'number4'=>'number_4','name4'=>'name_4','number5'=>'number_5','name5'=>'name_5','codeg'=>'code_g','nameg1'=>'name_g1','glevel'=>'group_level',
            'lastlevel'=>'last_level','topcode'=>'top_code','vaz'=>'vaz','نوع کلاس خرید'=>'purchase_class_type','نوع پورسانت'=>'commission_type',
            'formula Num11'=>'formula_num_11','formula Num21'=>'formula_num_21','formula Num31'=>'formula_num_31','formula Num41'=>'formula_num_41','formula Num51'=>'formula_num_51',
            'اسم فرمول 1'=>'formula_name_1','اسم فرمول 2'=>'formula_name_2','اسم فرمول 3'=>'formula_name_3','اسم فرمول 4'=>'formula_name_4','اسم فرمول 5'=>'formula_name_5',
            'groupcode1'=>'group_code_1','نام گروه کالا درختی'=>'product_tree_group_name','ضریب'=>'coefficient','ضریب فروش'=>'sales_coefficient','اولویت'=>'priority',
        ];
        $result = [];
        $requiredKeys = array_values(self::REQUIRED);
        $numericKeys = ['quantity','carton_quantity','unit_quantity','net_carton_quantity','unit_price','gross_amount','discount_amount','net_amount','tax_amount','duty_amount','tax_duty_amount','weight','volume','product_weight','product_volume','fifo_cost','average_cost','purchase_cost','coefficient','sales_coefficient'];
        foreach ($headers as $header => $key) {
            $result[] = [
                'source_header' => $header,
                'normalized_key' => $key,
                'required' => in_array($key, $requiredKeys, true) ? 1 : 0,
                'data_type' => $key === 'invoice_date_raw' ? 'date' : (in_array($key, $numericKeys, true) || str_contains($key, 'percent') || str_contains($key, 'amount') ? 'decimal' : 'string'),
            ];
        }
        return $result;
    }
}
