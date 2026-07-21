<?php

$root = dirname(__DIR__);
$service = (string)file_get_contents($root . '/services/CanonicalSalesDashboardService.php');
$page = (string)file_get_contents($root . '/admin/manager-dashboard.php');
$dashboardModule = (string)file_get_contents($root . '/core/DashboardModule.php');
$fail = static function (string $message): void {
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
};

foreach ([
    "ReportingViewRepository::fetch('vw_sales_by_period'",
    "ReportingViewRepository::fetch('vw_target_by_visitor'",
    "ReportingViewRepository::fetch('vw_target_achievement'",
    'ManagerDashboardCalculator::calculateAchievement',
    'ManagerDashboardCalculator::calculatePenalty',
    'ManagerDashboardCalculator::calculateCommission',
] as $required) {
    if (!str_contains($service, $required)) $fail('Missing canonical manager dashboard contract: ' . $required);
}
if (!str_contains($page, 'CanonicalSalesDashboardService::managerSnapshot')) {
    $fail('Manager dashboard does not prefer the canonical dashboard adapter.');
}
if (!str_contains($service, 'active_import_views_formulas') || !str_contains($page, '$canonicalMode')) {
    $fail('Manager dashboard canonical source/fallback contract is incomplete.');
}
if (!str_contains($page, 'ManagerDashboard::latestReport')) {
    $fail('Legacy manager report fallback was removed.');
}
foreach ([
    "'/admin/import-center.php?source=sales_aggregate'",
    '$canCanonicalImport',
    '$useCanonicalImport',
] as $importContract) {
    if (!str_contains($page, $importContract)) {
        $fail('Canonical dashboard import action is not wired to the controlled import pipeline: ' . $importContract);
    }
}
$emptyStateStart = strpos($page, '<?php if(!$report):?>');
$emptyStateEnd = strpos($page, '<?php else:?>', $emptyStateStart ?: 0);
$emptyState = $emptyStateStart !== false && $emptyStateEnd !== false
    ? substr($page, $emptyStateStart, $emptyStateEnd - $emptyStateStart)
    : '';
if ($emptyState === '' || str_contains($emptyState, 'href="/admin/manager-dashboard-import.php"')) {
    $fail('Fresh canonical empty state still links directly to the legacy importer.');
}
foreach (['vw_sales_by_period','vw_target_by_visitor','vw_commission_inputs','vw_target_by_line'] as $source) {
    if (!str_contains($dashboardModule, "'" . $source . "'")) {
        $fail('Manager dashboard preference source is not canonical: ' . $source);
    }
}
if (preg_match('/DELETE\s+FROM\s+(?:sales_aggregate_rows|sales_targets)/i', $service . $page)) {
    $fail('Canonical dashboard adapter must remain read-only.');
}

echo "Canonical manager dashboard contract: PASS\n";
