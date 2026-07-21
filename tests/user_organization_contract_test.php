<?php

$root = dirname(__DIR__);
$required = [
    'lib/UserOrganizationService.php',
    'core/SalesStructureModule.php',
    'admin/users.php',
    'admin/users-import-export.php',
    'admin/supervisor-settings.php',
    'admin/sales-data-index.php',
    'admin/ceo-dashboard-settings.php',
    'assets/css/users-organization.css',
    'docs/user-sales-structure.md',
    'tests/fixtures/users-organization.html',
];
foreach ($required as $file) {
    if (!is_file($root . '/' . $file)) throw new RuntimeException('Missing file: ' . $file);
}

$service = file_get_contents($root . '/lib/UserOrganizationService.php');
foreach ([
    'normalizeKaraSystemCode',
    'validateAssignment',
    'applyAssignment',
    'سرپرست انتخاب‌شده زیرمجموعه مدیر فروش این لاین نیست',
    'ویزیتور باید به سرپرست ثبت‌شده همان لاین متصل باشد',
    'sales_line_id',
    'primary_geography_id',
] as $token) {
    if (!str_contains($service, $token)) throw new RuntimeException('Missing service contract: ' . $token);
}
if (preg_match('/\b(DROP|TRUNCATE|RENAME\s+TABLE)\b/i', $service)) {
    throw new RuntimeException('Destructive SQL found in organization service.');
}

$schema = file_get_contents($root . '/database/schema.sql');
foreach ([
    'kara_system_code VARCHAR(100) NULL',
    'sales_line_id INT UNSIGNED NULL',
    'CREATE TABLE IF NOT EXISTS sales_lines',
    'CREATE TABLE IF NOT EXISTS sales_geographies',
    'CREATE TABLE IF NOT EXISTS sales_visitor_territories',
] as $token) {
    if (!str_contains($schema, $token)) throw new RuntimeException('Missing schema contract: ' . $token);
}

$users = file_get_contents($root . '/admin/users.php');
foreach ([
    'name="kara_system_code"',
    'name="sales_line_id"',
    'name="primary_geography_id"',
    'UserOrganizationService::validateAssignment',
    'UserOrganizationService::applyAssignment',
    'window.Motion',
] as $token) {
    if (!str_contains($users, $token)) throw new RuntimeException('Missing users-page contract: ' . $token);
}
if (str_contains($users, 'name="sales_line" list=')) {
    throw new RuntimeException('Free-text sales line input is still present.');
}

$menu = file_get_contents($root . '/lib/admin_menu.php');
if (str_contains($menu, "source=sales_team")) throw new RuntimeException('Legacy visitor import is still exposed in the menu.');

$settings = file_get_contents($root . '/admin/supervisor-settings.php');
if (str_contains($settings, "INSERT INTO sales_team_assignments")) {
    throw new RuntimeException('Supervisor settings still writes a parallel team assignment.');
}

$legacyDashboard = file_get_contents($root . '/admin/ceo-dashboard-settings.php');
foreach (['ویزیتور مرکزی معتبر','COALESCE(sl.code,u.sales_line)','رکوردهای تاریخی عملکرد ویزیتورها'] as $token) {
    if (!str_contains($legacyDashboard, $token)) throw new RuntimeException('Missing legacy dashboard safety contract: ' . $token);
}
if (preg_match('/DELETE FROM ceo_dashboard_(?:lines|visitors)/i', $legacyDashboard)) {
    throw new RuntimeException('Destructive legacy dashboard delete is still present.');
}
if (str_contains($legacyDashboard, 'truncate_and_insert') || str_contains($legacyDashboard, 'replace_same_report_date')) {
    throw new RuntimeException('Destructive dashboard import mode is still exposed.');
}

$import = file_get_contents($root . '/lib/UserImportService.php');
foreach (['kara_system_code','supervisor_employee_no','sales_manager_employee_no','region_code','resolveOrganizationContext','assertActorScope','OrgAccess::canAssignScope'] as $token) {
    if (!str_contains($import, $token)) throw new RuntimeException('Missing user import contract: ' . $token);
}

$operations = file_get_contents($root . '/services/SalesOperationsService.php');
if (strpos($operations, 'SELECT organization_manager_id,parent_user_id,sales_line_id FROM users') === false) {
    throw new RuntimeException('Sales operations does not prefer central user hierarchy.');
}

echo "User organization contract: PASS\n";
