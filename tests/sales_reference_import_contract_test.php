<?php
$root = dirname(__DIR__);
$files = [
    'core/SalesReferenceSchema.php',
    'core/SalesReferenceRepository.php',
    'core/SalesReferenceImportService.php',
    'admin/sales-reference-batches.php',
    'admin/sales-reference-errors.php',
    'admin/sales-reference-status.php',
    'install/sales_reference_import_seed.php',
    'docs/sales-reference-import.md',
];
foreach ($files as $file) {
    if (!is_file($root . '/' . $file)) throw new RuntimeException("Missing {$file}");
}
$schema = file_get_contents($root . '/core/SalesReferenceSchema.php');
foreach ([
    'CREATE TABLE IF NOT EXISTS sales_reference_import_batches',
    'CREATE TABLE IF NOT EXISTS staging_sales_reference_rows',
    'CREATE TABLE IF NOT EXISTS sales_reference_import_errors',
    'vw_active_sales_aggregate_rows',
    'vw_active_inventory_aggregate_rows',
    'vw_sales_reference_summary',
    'vw_inventory_reference_summary',
    'sales_reference_upload',
    'sales_reference_commit',
] as $token) {
    if (!str_contains($schema, $token)) throw new RuntimeException("Missing reference schema token: {$token}");
}
$repo = file_get_contents($root . '/core/SalesReferenceRepository.php');
foreach (['getActiveReferenceBatch','setActiveReferenceBatch','getActiveSalesAggregateQueryScope','getActiveInventoryAggregateQueryScope','is_active_reference=0','is_active_reference=1'] as $token) {
    if (!str_contains($repo, $token)) throw new RuntimeException("Missing active batch token: {$token}");
}
$salesPage = file_get_contents($root . '/admin/sales-aggregate-import.php');
$inventoryPage = file_get_contents($root . '/admin/inventory-aggregate-import.php');
foreach (['period_key','replace_reference','append','تایید و فعال‌سازی به عنوان مرجع محاسبات','sales-reference-errors.php'] as $token) {
    if (!str_contains($salesPage . $inventoryPage, $token)) throw new RuntimeException("Missing upload UI token: {$token}");
}
echo "Sales reference import contract OK\n";
