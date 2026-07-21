<?php

$root = dirname(__DIR__);
require_once $root . '/core/ReportingViewsModule.php';

$definitions = ReportingViewsModule::definitions();
$expected = ReportingViewsModule::VIEW_NAMES;
$repository = file_get_contents($root . '/services/ReportingViewRepository.php');
$database = file_get_contents($root . '/core/Database.php');
$schema = file_get_contents($root . '/database/schema.sql');

$checks = [
    'all canonical views have definitions' => array_keys($definitions) === $expected,
    'all canonical views exist in fresh schema' => count(array_filter($expected, static fn(string $view): bool => str_contains($schema, 'VIEW ' . $view . ' AS'))) === count($expected),
    'repair runs after action and daily report modules' => strpos($database, 'ReportingViewsModule::repair($pdo)') > strpos($database, 'DailyWorkReportModule::repair($pdo)'),
    'repository whitelists canonical views' => str_contains($repository, 'ReportingViewsModule::VIEW_NAMES'),
    'repository applies organization scope' => str_contains($repository, 'OrgAccess::scopeSql') && str_contains($repository, 'OrgAccess::accessibleUserIds'),
    'employee cannot open line aggregates' => str_contains($repository, "count(\$ids) === 1"),
    'inventory views require management scope' => str_contains($repository, "'vw_inventory_current'") && str_contains($repository, 'ADMIN_ONLY'),
    'view name cannot be injected' => str_contains($repository, "in_array(\$view, ReportingViewsModule::VIEW_NAMES, true)"),
];

foreach ([
    'vw_sales_active' => 'vw_active_sales_aggregate_rows',
    'vw_purchase_active' => 'vw_active_purchase_aggregate_rows',
    'vw_inventory_current' => 'vw_active_inventory_aggregate_rows',
    'vw_target_achievement' => 'vw_sales_target_achievement',
] as $view => $source) {
    $checks[$view . ' uses active source'] = str_contains($definitions[$view] ?? '', $source);
}
$attendance = $definitions['vw_attendance_period_summary'] ?? '';
$checks['imported attendance requires active committed batch'] =
    str_contains($attendance, "b.is_active_reference=1") && str_contains($attendance, "b.status='committed'");

$failed = array_keys(array_filter($checks, static fn(bool $passed): bool => !$passed));
if ($failed) {
    fwrite(STDERR, "Reporting views phase 16 contract FAILED:\n- " . implode("\n- ", $failed) . "\n");
    exit(1);
}

echo 'Reporting views phase 16 contract PASS (' . count($checks) . " checks)\n";
