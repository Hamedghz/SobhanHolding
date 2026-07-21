<?php

$root = dirname(__DIR__);
if (!function_exists('mb_strtolower')) {
    function mb_strtolower(string $value, ?string $encoding = null): string { return strtolower($value); }
}
if (!function_exists('mb_substr')) {
    function mb_substr(string $value, int $start, ?int $length = null, ?string $encoding = null): string { return $length === null ? substr($value, $start) : substr($value, $start, $length); }
}

$required = [
    'core/ImportCenterModule.php','lib/ImportSettings.php','lib/ImportSourceRegistry.php',
    'services/UnifiedImportService.php','admin/import-center.php','assets/css/import-center.css',
    'assets/js/import-center.js','docs/unified-import-center.md','tests/unified_import_schema_integration_test.php',
];
foreach ($required as $file) if (!is_file($root.'/'.$file)) throw new RuntimeException('Missing import center file: '.$file);

require_once $root.'/services/UnifiedImportService.php';
$sources = ImportSourceRegistry::all();
foreach (['sales_aggregate','purchase_aggregate','inventory_aggregate','sales_targets','product_priorities','customer_coefficients','attendance'] as $source) {
    if (!isset($sources[$source])) throw new RuntimeException('Missing import source: '.$source);
}
if (empty($sources['inventory_aggregate']['snapshot_required']) || empty($sources['inventory_aggregate']['period_id_required'])) throw new RuntimeException('Inventory snapshot identity is incomplete.');

if (SalesDataNormalizer::normalizeHeader('نام  تامین کننده') !== SalesDataNormalizer::normalizeHeader('نام تامین کننده')) throw new RuntimeException('Supplier alias failed.');
if (SalesDataNormalizer::normalizeHeader('مدیرفروش') !== SalesDataNormalizer::normalizeHeader('مدیر فروش')) throw new RuntimeException('Manager alias failed.');
if (SalesDataNormalizer::normalizeHeader("مبلغ\nخالص") !== SalesDataNormalizer::normalizeHeader('مبلغ خالص')) throw new RuntimeException('Newline normalization failed.');
$purchaseMappings = $sources['purchase_aggregate']['mappings'];
if (!in_array('source_row_id', array_column($purchaseMappings, 'normalized_key'), true)) throw new RuntimeException('Purchase source row identity mapping is missing.');
if (!in_array('supplier_invoice_number', array_column($purchaseMappings, 'normalized_key'), true)) throw new RuntimeException('Supplier invoice identity mapping is missing.');

$salesHeaders = ['نوع فاکتور','شماره فاکتور','تاریخ','کد فروشنده','کد مشتری','کد کالا','تعداد کل','مبلغ ناخالص','مبلغ خالص'];
$attendanceHeaders = ['کد پرسنلی','ساعت ورود','ساعت خروج','کارکرد'];
$targetHeaders = ['کد لاین','کد کالا','کد فروشنده','تارگت تعداد','تارگت مبلغ'];
$salesWorkbook = ['sheets'=>[[
    'name'=>'گزارش تجمیعی فروش',
    'visible'=>true,
    'rows'=>[$salesHeaders,array_fill(0,count($salesHeaders),'x')],
    'tables'=>[['name'=>'tblsales','ref'=>'A1:I2']],
]]];
$detected = UnifiedImportService::detectWorkbook($salesWorkbook);
if (!$detected || $detected[0]['source_module'] !== 'sales_aggregate' || $detected[0]['detection'] !== 'table') throw new RuntimeException('Excel Table detection priority failed.');
$automatic = new ReflectionMethod(UnifiedImportService::class, 'automaticCandidate');
$selected = $automatic->invoke(null, $detected);
if (!$selected || $selected['detection'] !== 'table') throw new RuntimeException('Exact Table candidate was not selected automatically.');
$multiTableWorkbook = ['sheets'=>[[
    'name'=>'Dashboard',
    'visible'=>true,
    'rows'=>[$attendanceHeaders,['1001','08:00','17:00','08:00'],[], $targetHeaders,['A','10001','V001','100','1000000']],
    'tables'=>[['name'=>'tblattendance','ref'=>'A1:D2'],['name'=>'tbltarget','ref'=>'A4:E5']],
]]];
$multiDetected = UnifiedImportService::detectWorkbook($multiTableWorkbook);
$multiSources = array_unique(array_column($multiDetected, 'source_module'));
if (!in_array('attendance', $multiSources, true) || !in_array('sales_targets', $multiSources, true)) throw new RuntimeException('Independent analytical tables were not recognized separately.');

$attendanceSampleWorkbook = ['sheets'=>[
    ['name'=>'Sheet','visible'=>true,'rows'=>[
        ['ردیف','کد پرسنلی','نام','نام خانوادگی','ساعت شروع به کار','ساعت ورود','ساعت پایان کار','ساعت خروج','تاخیر (دقیقه)','اضافه کاری (دقیقه)','کارکرد','اختلاف ساعت کاری','ماموریت','مرخصی','ساعت کاری روزانه'],
        ['38','43','محمدحسین','خیاطی','07:30:00','09:37','00:15:00','07:45','01:52','','14','-6.5','','','7.5'],
    ],'tables'=>[]],
    ['name'=>'Sheet1','visible'=>true,'rows'=>[
        ['۱۴۰۵/۰۴/۲۴'],
        ['کد پرسنلی','نام','نام خانوادگی','ساعت ورود','تاخیر (دقیقه)','-'],
    ],'tables'=>[]],
]];
$attendanceDetected = UnifiedImportService::detectWorkbook($attendanceSampleWorkbook);
if (!$attendanceDetected || $attendanceDetected[0]['source_module'] !== 'attendance') throw new RuntimeException('Real attendance sample was misclassified.');
if (UnifiedImportService::inferWorkbookDate($attendanceSampleWorkbook) !== '2026-07-15') throw new RuntimeException('Attendance snapshot date was not inferred from auxiliary sheet.');
$ambiguousDateWorkbook = ['sheets'=>[['name'=>'Sheet','visible'=>true,'rows'=>[['1405/04/24','1405/04/25']],'tables'=>[]]]];
if (UnifiedImportService::inferWorkbookDate($ambiguousDateWorkbook) !== null) throw new RuntimeException('Ambiguous workbook date must require user selection.');

$cropped = SpreadsheetImportReader::crop([
    ['A','B','C','D'],
    ['1','2','3','4'],
    ['5','6','7','8'],
], 'A1:D1048576');
if (count($cropped) !== 3) throw new RuntimeException('Worksheet dimension was trusted over actual populated rows.');

$service = (string)file_get_contents($root.'/services/UnifiedImportService.php');
$reader = (string)file_get_contents($root.'/core/SpreadsheetImportReader.php');
$importCss = (string)file_get_contents($root.'/assets/css/import-center.css');
$salesRepository = (string)file_get_contents($root.'/core/SalesAggregateRepository.php');
$normalizer = (string)file_get_contents($root.'/core/SalesDataNormalizer.php');
foreach (['uploaded','detected','validation_failed','ready_to_commit','committed','activated','rejected','rolled_back'] as $status) {
    if (!str_contains($service, $status)) throw new RuntimeException('Missing pipeline status: '.$status);
}
if (preg_match('/\b(DROP|TRUNCATE|eval\s*\()\b/i', $service)) throw new RuntimeException('Unsafe unified import operation found.');
if (str_contains($service, 'pathinfo($fileName') || str_contains($service, 'detectByFilename')) throw new RuntimeException('Filename-based source detection found.');
if (!str_contains($service, 'assertSourcePermission') || !str_contains($service, "Auth::can('hr_attendance'")) throw new RuntimeException('Attendance import permission gate is missing.');
if (!str_contains($service, 'allowStoredFile') || !str_contains($reader, 'trustedStoredPath')) throw new RuntimeException('Retry cannot safely reuse its stored batch file.');
if (!str_contains($reader, 'new XMLReader()') || !str_contains($reader, 'candidateRows') || !str_contains($reader, 'SAMPLE_ROWS=500')) throw new RuntimeException('Large XLSX worksheets are not streamed with a bounded discovery sample.');
foreach (['grid-template-columns:minmax(0,1fr)', '@media(max-width:1100px)', '.import-page .table-responsive{', 'direction:ltr'] as $token) {
    if (!str_contains($importCss, $token)) throw new RuntimeException('Import center responsive overflow contract missing: '.$token);
}
if (!str_contains($service, 'SpreadsheetImportReader::candidateRows($candidate)')) throw new RuntimeException('Unified staging does not consume the complete streamed worksheet.');
if (!str_contains($salesRepository, 'SalesDataNormalizer::mappingDefinitions()')) throw new RuntimeException('Sales import has no safe mapping fallback for older databases.');
if (!str_contains($salesRepository, 'is_string($value) && trim($value) === \'\' ? null : $value')) throw new RuntimeException('Sales aggregate commit does not convert blank optional reference fields to SQL NULL.');
if (!str_contains($normalizer, "'units_per_carton'") || !str_contains($normalizer, "'gross_after_discount'")) throw new RuntimeException('Sales aggregate decimal mapping omits optional numeric fields used by the final reference table.');

$page = (string)file_get_contents($root.'/admin/import-center.php');
foreach (['source_hint','snapshot_date','period_id','candidate_key','ready_to_commit','Retry','نگاشت'] as $token) {
    if (!str_contains($page, $token)) throw new RuntimeException('Missing import center UI token: '.$token);
}
$menu = (string)file_get_contents($root.'/lib/admin_menu.php');
if (!str_contains($menu, 'مرکز یکپارچه ورود اطلاعات')) throw new RuntimeException('Import center menu entry missing.');
if (!str_contains($menu, "'super_admin' => true") || !str_contains($menu, 'نگاشت پیشرفته ستون‌ها')) throw new RuntimeException('Advanced mapping is not restricted to Super Admin menu.');

$schema = (string)file_get_contents($root.'/database/schema.sql');
foreach (['purchase_aggregate_rows','pipeline_status','source_row_number','source_row_hash','snapshot_date','vw_active_purchase_aggregate_rows'] as $token) {
    if (!str_contains($schema, $token)) throw new RuntimeException('Missing schema contract: '.$token);
}

echo "Unified import center contract: PASS\n";
