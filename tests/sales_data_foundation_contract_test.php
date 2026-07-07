<?php
$root = dirname(__DIR__);
$required = [
    'core/SalesDataSchema.php',
    'core/SalesDataRepository.php',
    'install/sales_data_foundation_seed.php',
    'admin/sales-data-index.php',
    'admin/sales-data-batches.php',
    'admin/sales-data-errors.php',
    'admin/sales-data-mapping.php',
    'docs/sales-data-foundation.md',
];
foreach ($required as $file) {
    if (!is_file($root . '/' . $file)) throw new RuntimeException('Missing file: ' . $file);
}

$schema = file_get_contents($root . '/core/SalesDataSchema.php');
if (preg_match('/\b(DROP|TRUNCATE|RENAME\s+TABLE|DELETE\s+FROM)\b/i', $schema)) {
    throw new RuntimeException('Destructive schema operation found.');
}
foreach ([
    'CREATE TABLE IF NOT EXISTS', 'sales_import_batches', 'sales_import_errors',
    'sales_import_column_mappings', 'staging_sales_data', 'raw_json',
    'commission_calculation_results', 'ON DUPLICATE KEY UPDATE module_key=VALUES(module_key)',
] as $token) {
    if (!str_contains($schema, $token)) throw new RuntimeException('Missing schema contract: ' . $token);
}

foreach ([
    'sales_data_view', 'sales_data_import', 'sales_data_manage_mapping', 'sales_data_view_errors',
    'sales_data_sync_ai', 'sales_data_manage_formulas', 'sales_data_view_reports', 'sales_data_run_commission',
] as $permission) {
    if (!str_contains($schema, $permission)) throw new RuntimeException('Missing permission: ' . $permission);
}

$menu = file_get_contents($root . '/lib/admin_menu.php');
foreach (['مدیریت داده فروش', 'ورود اطلاعات فروش تجمیعی', 'آپدیت موجودی انبار', 'وضعیت اتصال SobhanAI', 'Viewهای گزارش‌گیری'] as $token) {
    if (!str_contains($menu, $token)) throw new RuntimeException('Missing menu contract: ' . $token);
}

foreach (array_slice($required, 3, 4) as $file) {
    $page = file_get_contents($root . '/' . $file);
    if (!str_contains($page, 'Auth::requirePermission')) throw new RuntimeException('Page lacks permission check: ' . $file);
    if (!str_contains($page, "admin-header.php") || !str_contains($page, "admin-footer.php")) {
        throw new RuntimeException('Page lacks shared layout: ' . $file);
    }
}

$seed = file_get_contents($root . '/install/sales_data_foundation_seed.php');
foreach (['Auth::canManageSystemTools', 'Auth::verifyCsrf', 'SalesDataSchema::repair', 'error_log'] as $token) {
    if (!str_contains($seed, $token)) throw new RuntimeException('Missing seed safety contract: ' . $token);
}

echo "Sales data foundation contract: PASS\n";
