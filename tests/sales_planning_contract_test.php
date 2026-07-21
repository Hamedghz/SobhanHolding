<?php

$root = dirname(__DIR__);
if (!function_exists('mb_strtolower')) {
    function mb_strtolower(string $value, ?string $encoding = null): string { return strtolower($value); }
}
if (!function_exists('mb_substr')) {
    function mb_substr(string $value, int $start, ?int $length = null, ?string $encoding = null): string {
        return $length === null ? substr($value, $start) : substr($value, $start, $length);
    }
}

$required = [
    'core/SalesPlanningModule.php','services/SalesPlanningService.php','admin/sales-planning.php',
    'assets/css/sales-planning.css','assets/js/sales-planning.js','docs/sales-planning.md',
    'tests/sales_planning_schema_integration_test.php','tests/fixtures/sales-planning.html',
];
foreach ($required as $file) {
    if (!is_file($root . '/' . $file)) throw new RuntimeException('Missing sales planning file: ' . $file);
}

require_once $root . '/services/SalesPlanningService.php';
require_once $root . '/services/UnifiedImportService.php';
foreach ([
    'P1'=>'P1','1'=>'P1','A'=>'P1','فوری'=>'P1',
    'P2'=>'P2','بالا'=>'P2','3'=>'P3','عادی'=>'P3','D'=>'P4','کم'=>'P4',
] as $input=>$expected) {
    if (SalesPlanningService::normalizePriorityCode($input) !== $expected) {
        throw new RuntimeException('Priority normalization failed: ' . $input);
    }
}
if (SalesPlanningService::normalizePriorityCode('نامعتبر') !== null) {
    throw new RuntimeException('Unknown priority was accepted.');
}
if (SalesPlanningService::normalizeStatus('فعال') !== 'active'
    || SalesPlanningService::normalizeStatus('غیرفعال') !== 'inactive'
    || SalesPlanningService::normalizeStatus('نامعتبر') !== null) {
    throw new RuntimeException('Controlled priority status failed.');
}
$coefficientWorkbook = ['sheets'=>[[
    'name'=>'Sheet1','visible'=>true,'tables'=>[],
    'rows'=>[['نام صنف','ضریب'],['داروخانه','1.15']],
]]];
$detectedCoefficient = UnifiedImportService::detectWorkbook($coefficientWorkbook, 'customer_coefficients');
if (!$detectedCoefficient || $detectedCoefficient[0]['source_module'] !== 'customer_coefficients') {
    throw new RuntimeException('Name-based guild coefficient headers were not detected.');
}

$module = (string)file_get_contents($root . '/core/SalesPlanningModule.php');
foreach ([
    'period_id','guild_identity_key','normalized_guild_name','version_no','visitor_user_id','line_id',
    'allocation_percent','vw_sales_target_achievement','vw_sales_target_visitor_totals',
    'vw_sales_target_line_products','vw_sales_target_line_totals','vw_sales_target_brand_totals',
] as $token) {
    if (!str_contains($module, $token)) throw new RuntimeException('Missing planning module contract: ' . $token);
}
if (preg_match('/\b(DROP|TRUNCATE|DELETE\s+FROM)\b/i', $module)) {
    throw new RuntimeException('Destructive sales planning migration found.');
}

$service = (string)file_get_contents($root . '/services/SalesPlanningService.php');
foreach ([
    "'manual-target'",
    'period_id=? AND visitor_user_id=? AND line_id=? AND product_code=?',
    'OrgAccess::canAccessUser',
    'sales_line_id',
    'source_type="manual"',
    'COALESCE(MAX(version_no),0)+1',
] as $token) {
    if (!str_contains($service, $token)) throw new RuntimeException('Missing planning service contract: ' . $token);
}
if (preg_match('/INSERT\s+INTO\s+(sales_target_line|sales_target_brand|sales_target_total)/i', $service)) {
    throw new RuntimeException('Manual aggregate target storage was introduced.');
}

$import = (string)file_get_contents($root . '/services/UnifiedImportService.php');
foreach ([
    'validateTargetContext','normalizePriorityCode','guild_identity_key',
    'invalid_allocation_percent','negative_target','invalid_target_scope',
] as $token) {
    if (!str_contains($import, $token)) throw new RuntimeException('Missing planning import validation: ' . $token);
}

$registry = (string)file_get_contents($root . '/lib/ImportSourceRegistry.php');
foreach (['درصد تخصیص','شناسه دوره','موجودی کالا','وضعیت'] as $token) {
    if (!str_contains($registry, $token)) throw new RuntimeException('Missing planning import mapping: ' . $token);
}

$page = (string)file_get_contents($root . '/admin/sales-planning.php');
foreach ([
    'Interactive grid','download=coefficients','source=product_priorities','source=sales_targets',
    'هدف کالایی ویزیتور','جمع هدف ویزیتور','هدف کالایی لاین','جمع هدف لاین','هدف برند',
    'data-target-line','data-target-visitor',
] as $token) {
    if (!str_contains($page, $token)) throw new RuntimeException('Missing planning UI contract: ' . $token);
}
if (!str_contains((string)file_get_contents($root . '/assets/js/sales-planning.js'), 'window.Motion')) {
    throw new RuntimeException('Motion integration is missing.');
}

$menu = (string)file_get_contents($root . '/lib/admin_menu.php');
if (!str_contains($menu, 'ضرایب، اولویت‌ها و اهداف') || !str_contains($menu, 'sales_planning.reports')) {
    throw new RuntimeException('Sales planning menu exposure is missing.');
}

$schema = (string)file_get_contents($root . '/database/schema.sql');
foreach (['idx_sales_targets_grain','idx_sales_coeff_identity','vw_sales_target_achievement'] as $token) {
    if (!str_contains($schema, $token)) throw new RuntimeException('Missing planning schema contract: ' . $token);
}

echo "Sales planning contract: PASS\n";
