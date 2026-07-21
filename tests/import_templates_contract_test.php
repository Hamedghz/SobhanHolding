<?php

$root = dirname(__DIR__);
if (!function_exists('mb_strtolower')) {
    function mb_strtolower(string $value, ?string $encoding = null): string { return strtolower($value); }
}
if (!function_exists('mb_substr')) {
    function mb_substr(string $value, int $start, ?int $length = null, ?string $encoding = null): string { return $length === null ? substr($value, $start) : substr($value, $start, $length); }
}
if (!function_exists('mb_strlen')) {
    function mb_strlen(string $value, ?string $encoding = null): int { return strlen($value); }
}

require_once $root . '/lib/ImportTemplateService.php';

$sources = ImportSourceRegistry::all();
$workbook = ImportTemplateService::workbook(array_keys($sources));
if (!isset($workbook['راهنما'])) throw new RuntimeException('Template guide sheet is missing.');
if (count($workbook) !== count($sources) + 1) throw new RuntimeException('Not every unified import source has a template sheet.');

foreach ($sources as $key => $source) {
    $columns = ImportTemplateService::columns($source);
    if (!$columns) throw new RuntimeException('Empty template columns for '.$key);
    $normalizedKeys = array_column($columns, 'normalized_key');
    if (count($normalizedKeys) !== count(array_unique($normalizedKeys))) throw new RuntimeException('Duplicate canonical column in '.$key);
    foreach ($source['mappings'] as $mapping) {
        if (!empty($mapping['required']) && !in_array($mapping['normalized_key'], $normalizedKeys, true)) {
            throw new RuntimeException('Required template column missing for '.$key.': '.$mapping['normalized_key']);
        }
    }
}

$endpoint = (string)file_get_contents($root . '/admin/import-template.php');
foreach (['Auth::requireLogin()', 'assertTemplatePermission', 'CeoDashboardExcel::output', "download_import_template"] as $token) {
    if (!str_contains($endpoint, $token)) throw new RuntimeException('Template endpoint contract missing: '.$token);
}

$pages = [
    'admin/import-center.php'=>'source=',
    'admin/sales-aggregate-import.php'=>'source=sales_aggregate',
    'admin/inventory-aggregate-import.php'=>'source=inventory_aggregate',
    'admin/users-import-export.php'=>'action=template',
    'admin/payroll-import.php'=>'value="template"',
    'admin/manager-dashboard-import.php'=>'manager-dashboard-export.php?template=1',
    'admin/ceo-dashboard-settings.php'=>'export=template',
    'admin/pharmacy-settings.php'=>'export=template',
];
foreach ($pages as $file => $token) {
    if (!str_contains((string)file_get_contents($root.'/'.$file), $token)) throw new RuntimeException('Import template link missing in '.$file);
}

$managerExport = (string)file_get_contents($root . '/admin/manager-dashboard-export.php');
if (!str_contains($managerExport, "Auth::can('manager_dashboard.import')")) throw new RuntimeException('Manager import permission cannot download its template.');

echo "Import templates contract: PASS\n";
