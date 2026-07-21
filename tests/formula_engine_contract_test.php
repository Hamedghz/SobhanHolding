<?php

$root = dirname(__DIR__);
require_once $root . '/lib/FormulaEngine.php';

$fail = static function (string $message): never {
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
};

foreach ([
    'commission', 'penalty', 'target', 'brand_bonus', 'customer_coverage',
    'return', 'three_three_three', 'kpi', 'attendance', 'management_report',
] as $category) {
    if (!isset(FormulaEngine::CATEGORIES[$category])) $fail('Missing category: ' . $category);
}
foreach (['SUM','COUNT','COUNT_DISTINCT','AVERAGE','MIN','MAX','PERCENT','RATIO'] as $aggregation) {
    if (!isset(FormulaEngine::AGGREGATIONS[$aggregation])) $fail('Missing aggregation: ' . $aggregation);
}
foreach (['=','!=','>','>=','<','<=','BETWEEN','IN','NOT_IN'] as $operator) {
    if (!isset(FormulaEngine::OPERATORS[$operator])) $fail('Missing operator: ' . $operator);
}

$normalized = FormulaEngine::normalizeBuilderInput([
    'formula_key' => 'commission_sample',
    'title' => 'پورسانت نمونه',
    'category_key' => 'commission',
    'data_source_key' => 'sample_input',
    'metric_key' => 'net_amount',
    'aggregation_key' => 'SUM',
    'operator_key' => '>=',
    'condition_value' => '۱۰۰',
    'result_type' => 'percent_of_metric',
    'result_value' => '۱۰',
    'priority' => 100,
    'active' => 1,
    'filter_field' => ['line_code'],
    'filter_operator' => ['IN'],
    'filter_value' => ['A،B'],
]);
$result = FormulaEngine::evaluate($normalized['rule'], [
    ['net_amount' => 80, 'line_code' => 'A'],
    ['net_amount' => 40, 'line_code' => 'B'],
    ['net_amount' => 900, 'line_code' => 'C'],
]);
if (!$result['matched'] || (float)$result['aggregate_value'] !== 120.0 || (float)$result['final_result'] !== 12.0) {
    $fail('Structured formula evaluation is incorrect.');
}

$ratio = FormulaEngine::normalizeBuilderInput([
    'formula_key' => 'target_percent',
    'title' => 'درصد تحقق',
    'category_key' => 'target',
    'data_source_key' => 'sample_input',
    'metric_key' => 'net_amount',
    'comparison_metric_key' => 'target_amount',
    'aggregation_key' => 'PERCENT',
    'operator_key' => 'BETWEEN',
    'condition_value' => '۹۰،۱۱۰',
    'result_type' => 'metric',
    'result_value' => 0,
    'active' => 1,
]);
$ratioResult = FormulaEngine::evaluate($ratio['rule'], [
    ['net_amount' => 95, 'target_amount' => 100],
]);
if (!$ratioResult['matched'] || (float)$ratioResult['final_result'] !== 95.0) {
    $fail('PERCENT aggregation or BETWEEN condition is incorrect.');
}

$sources = [
    $root . '/lib/FormulaEngine.php',
    $root . '/lib/FormulaRepository.php',
    $root . '/admin/formula-builder.php',
];
foreach ($sources as $file) {
    $contents = (string)file_get_contents($file);
    if (preg_match('/\beval\s*\(/i', $contents)) $fail('eval() is forbidden: ' . basename($file));
    if (preg_match('/\b(DROP|TRUNCATE|RENAME\s+TABLE)\b/i', $contents)) $fail('Destructive SQL found: ' . basename($file));
}
$page = (string)file_get_contents($root . '/admin/formula-builder.php');
foreach (['data_source_key','metric_key','aggregation_key','operator_key','condition_value','result_type','result_value','priority','effective_from','effective_to','dependency_ids'] as $field) {
    if (!str_contains($page, $field)) $fail('Missing visual formula field: ' . $field);
}
if (str_contains($page, 'name="rule_json"') || str_contains($page, 'name="settings_json"')) {
    $fail('Raw JSON must not be editable.');
}
$payrollPage = (string)file_get_contents($root . '/admin/payroll-fields.php');
if (str_contains($payrollPage, 'name="formula"')) $fail('Payroll raw formula editing is still exposed.');
foreach ([
    $root . '/services/SalesOfferBudgetService.php' => 'FormulaRuntime::evaluateByKey',
    $root . '/core/ManagerDashboardCalculator.php' => 'FormulaRuntime::evaluateByKey',
    $root . '/lib/PayrollImportService.php' => 'FormulaRuntime::evaluateDefinition',
] as $file => $token) {
    if (!str_contains((string)file_get_contents($file), $token)) $fail('Missing formula adapter: ' . basename($file));
}
$payrollImporter = (string)file_get_contents($root . '/lib/PayrollImportService.php');
if (!str_contains($payrollImporter, 'فرمول فعال فیلد') || !str_contains($payrollImporter, 'throw new RuntimeException')) {
    $fail('Payroll formula adapter must fail closed when no active version exists.');
}

echo "Formula engine contract: PASS\n";
