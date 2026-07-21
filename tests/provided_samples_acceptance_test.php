<?php

if (!function_exists('mb_strtolower')) {
    function mb_strtolower(string $value, ?string $encoding = null): string { return strtolower($value); }
}
if (!function_exists('mb_substr')) {
    function mb_substr(string $value, int $start, ?int $length = null, ?string $encoding = null): string
    {
        return $length === null ? substr($value, $start) : substr($value, $start, $length);
    }
}

require_once __DIR__ . '/../services/UnifiedImportService.php';

$samples = [
    'purchase' => [
        'sheet' => 'گزارش تجمیعی خرید',
        'headers' => ['ردیف','نوع فاکتور','شماره فاکتور','شماره فاکتور تامین کننده','تاریخ','کد تامین کننده','نام  تامین کننده','کد تولیدکننده کالا','نام تولیدکننده کالا','کد لاین','نام لاین','کد کالا','نام کالا','تعداد در کارتن','کد گروه','نام گروه','تعداد کارتن','تعداد جز','تعداد کل','قیمت واحد','ناخالص','%تخفیف','%تخفیف 2','%تخفیف3','مبلغ تخفیف سطری','ناخالص - تخفیف','مبلغ مالیات','مبلغ عوارض','مبلغ خالص','وزن','حجم','کد انبار','نام انبار','نام شعبه','کد برند','نام برند','سایر توضیحات','درصد مالیات','درصد عوارض','تاریخ فاکتور تامین کننده'],
        'expected' => 'purchase_aggregate',
    ],
    'sales' => [
        'sheet' => ' گزارش تجمیعی فروش',
        'headers' => ['ردیف','نوع فاکتور','شماره فاکتور','تاریخ','کد فروشنده','نام فروشنده','کد لاین','نام لاین','کد مشتری','نام مشتری','کد کالا','نام کالا','تعداد در کارتن','کد تولیدکننده','نام تولیدکننده','کد گروه','نام گروه','تعداد کارتن','تعداد جز','تعداد کل','کارتن خالص','قیمت واحد','مبلغ ناخالص','%تخفیف','مبغ تخفیف 1','%تخفیف 2','مبلغ تخفیف 2','%تخفیف3','مبلغ تخفیف 3','مجموع مبلغ تخفیف سطری','ناخالص - تخفیف','مبلغ مالیات','مبلغ عوارض','جمع مالیات عوارض','مبلغ خالص'],
        'expected' => 'sales_aggregate',
    ],
    'attendance' => [
        'sheet' => 'Sheet',
        'headers' => ['ردیف','کد پرسنلی','نام','نام خانوادگی','ساعت شروع به کار','ساعت ورود','ساعت پایان کار','ساعت خروج','تاخیر (دقیقه)','اضافه کاری (دقیقه)','کارکرد','اختلاف ساعت کاری','ماموریت','مرخصی','ساعت کاری روزانه'],
        'expected' => 'attendance',
    ],
];

foreach ($samples as $name => $sample) {
    $workbook = ['sheets'=>[[
        'name'=>$sample['sheet'],
        'visible'=>true,
        'rows'=>[$sample['headers'],array_fill(0,count($sample['headers']),'x')],
        'tables'=>[],
    ]]];
    $detected = UnifiedImportService::detectWorkbook($workbook);
    if (!$detected || $detected[0]['source_module'] !== $sample['expected']) {
        throw new RuntimeException("Provided {$name} sample detection failed.");
    }
}

$partialAttendance = ['sheets'=>[['name'=>'Sheet','visible'=>true,'rows'=>[
    ['1405/04/27','کد پرسنلی','نام','نام خانوادگی','ساعت ورود','تاخیر (دقیقه)'],
    ['49','54','سبحان','کلانتری','08:13','0.019444444444444'],
],'tables'=>[]]]];
$partialDetected = UnifiedImportService::detectWorkbook($partialAttendance);
if (!$partialDetected || $partialDetected[0]['source_module'] !== 'attendance') throw new RuntimeException('Partial daily attendance sample detection failed.');
if (UnifiedImportService::inferWorkbookDate($partialAttendance) !== '2026-07-18') throw new RuntimeException('Partial daily attendance date inference failed.');

$sourceKey = new ReflectionMethod(UnifiedImportService::class, 'sourceKey');
$purchaseA = [
    'source_row_id'=>'3','invoice_number'=>'34','supplier_invoice_number'=>'14059086071',
    'invoice_type'=>'خرید','product_code'=>'4071079','supplier_code'=>'400007','line_code'=>'1',
    'invoice_date_raw'=>'1405/04/06','quantity'=>'24','gross_amount'=>'116880000','net_amount'=>'115711200',
];
$purchaseB = $purchaseA;
$purchaseB['source_row_id'] = '16';
$purchaseB['quantity'] = '8';
$purchaseB['gross_amount'] = '38960000';
$purchaseB['net_amount'] = '38570400';
if ($sourceKey->invoke(null, 'purchase_aggregate', $purchaseA, []) === $sourceKey->invoke(null, 'purchase_aggregate', $purchaseB, [])) {
    throw new RuntimeException('Independent purchase lines were collapsed into one source key.');
}
$purchaseARepeat = $purchaseA;
if ($sourceKey->invoke(null, 'purchase_aggregate', $purchaseA, []) !== $sourceKey->invoke(null, 'purchase_aggregate', $purchaseARepeat, [])) {
    throw new RuntimeException('Purchase source key is not idempotent.');
}

$salesA = [
    'unique_code'=>'','identifier'=>'171547','invoice_number'=>'4104','invoice_type'=>'فروش',
    'sub_invoice_number'=>'3753','product_code'=>'4050012','customer_code'=>'10101020',
    'visitor_code'=>'203','invoice_date_raw'=>'1405/04/25',
];
$salesB = $salesA;
$salesB['identifier'] = '171548';
if (SalesAggregateImportService::buildSourceUniqueKey($salesA) === SalesAggregateImportService::buildSourceUniqueKey($salesB)) {
    throw new RuntimeException('Independent sales rows with source identifiers were collapsed.');
}
if (SalesAggregateImportService::buildSourceUniqueKey($salesA) !== SalesAggregateImportService::buildSourceUniqueKey($salesA)) {
    throw new RuntimeException('Sales source identifier key is not idempotent.');
}

$questionBank = json_decode((string)file_get_contents(__DIR__ . '/../install/data/sobhan_assessment_20_battery.json'), true);
if (($questionBank['meta']['test_count'] ?? 0) !== 20 || ($questionBank['meta']['question_count'] ?? 0) !== 400) {
    throw new RuntimeException('Provided assessment bank metadata is incomplete.');
}
if (count($questionBank['tests'] ?? []) !== 20) throw new RuntimeException('Assessment test count mismatch.');
foreach ($questionBank['tests'] as $test) {
    if (count($test['questions'] ?? []) !== 20) throw new RuntimeException('Each assessment must contain 20 questions.');
}

echo "Provided sample acceptance contract: PASS\n";
