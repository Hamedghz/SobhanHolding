<?php

$root = dirname(__DIR__);
require_once $root . '/core/DashboardModule.php';

$fail = static function (string $message): never {
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
};

$definitions = DashboardModule::definitions();
foreach (array_keys(DashboardModule::SCOPES) as $scope) {
    if (empty($definitions[$scope])) $fail('Missing dashboard scope: ' . $scope);
}
foreach (['ceo', 'sales_manager', 'supervisor'] as $scope) {
    if (!isset($definitions[$scope]['summary_kpis'])) $fail('Missing summary widget for ' . $scope);
}

$module = (string)file_get_contents($root . '/core/DashboardModule.php');
$schema = (string)file_get_contents($root . '/database/schema.sql');
$database = (string)file_get_contents($root . '/core/Database.php');
$page = (string)file_get_contents($root . '/admin/dashboard-settings.php');
$menu = (string)file_get_contents($root . '/lib/admin_menu.php');
$preferences = (string)file_get_contents($root . '/lib/DashboardPreferences.php');
$ceoDashboard = (string)file_get_contents($root . '/admin/ceo-dashboard.php');
$managerDashboard = (string)file_get_contents($root . '/admin/manager-dashboard.php');
$supervisorDashboard = (string)file_get_contents($root . '/admin/supervisor-dashboard.php');
$supervisorService = (string)file_get_contents($root . '/services/SalesOperationsService.php');
$legacyCeoSettings = (string)file_get_contents($root . '/admin/ceo-dashboard-settings.php');
$dashboardScript = (string)file_get_contents($root . '/assets/js/app-dashboard-preferences.js');

foreach ([$module, $schema] as $source) {
    if (!str_contains($source, 'CREATE TABLE IF NOT EXISTS dashboard_widget_preferences')) $fail('Dashboard preference DDL is missing.');
}
if (!str_contains($database, 'DashboardModule::repair($pdo)')) $fail('Dashboard repair hook is missing.');
if (!str_contains($page, 'DashboardPreferences::save')) $fail('Shared settings page is not connected to the preference service.');
if (str_contains($page, 'settings_json[') || str_contains($page, 'default_filters_json[')) $fail('Raw JSON must not be user editable.');
if (!str_contains($preferences, 'dashboard-widget-layout')) $fail('Shared dashboard renderer is missing.');
foreach (['data-dashboard-refresh-seconds', 'data-dashboard-drilldown-enabled', 'data-dashboard-filter-mode', 'data-dashboard-default-period'] as $attribute) {
    if (!str_contains($preferences, $attribute)) $fail('Shared renderer control is missing: ' . $attribute);
}
if (!str_contains($dashboardScript, 'window.location.reload')) $fail('Configured dashboard refresh behavior is missing.');
foreach ([$ceoDashboard, $managerDashboard, $supervisorDashboard] as $dashboardSource) {
    if (!str_contains($dashboardSource, 'DashboardPreferences::render')) $fail('A role dashboard is not using the shared renderer.');
}
if (!str_contains($supervisorService, 'vw_active_sales_aggregate_rows')) $fail('Supervisor dashboard is not connected to the active sales reference view.');
if (!str_contains($supervisorService, "data_source' => 'active_sales_reference_view")) $fail('Supervisor dashboard source marker is missing.');
if (!str_contains($legacyCeoSettings, 'ورود و ویرایش دستی قدیمی غیرفعال است')) $fail('Legacy CEO manual entry is not deprecated.');
if (!str_contains($managerDashboard, '$legacyManualMode')) $fail('Legacy manager manual entry is not gated.');
foreach (['scope=ceo', 'scope=sales_manager', 'scope=supervisor'] as $route) {
    if (!str_contains($menu, $route)) $fail('Missing shared settings menu route: ' . $route);
}
if (preg_match('/\b(DROP|TRUNCATE|RENAME\s+TABLE)\b/i', $module)) $fail('Destructive SQL found in dashboard module.');

echo "Dashboard preferences contract: PASS\n";
