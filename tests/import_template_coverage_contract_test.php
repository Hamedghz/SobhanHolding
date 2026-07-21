<?php

$root = dirname(__DIR__);

require_once $root . '/lib/ImportSourceRegistry.php';
require_once $root . '/lib/ImportTemplateService.php';

$fail = static function (string $message): never {
    throw new RuntimeException($message);
};

$pageContracts = [
    'admin/import-center.php' => ['type="file"', '/admin/import-template.php?source='],
    'admin/sales-aggregate-import.php' => ['type="file"', 'source=sales_aggregate'],
    'admin/inventory-aggregate-import.php' => ['type="file"', 'source=inventory_aggregate'],
    'admin/ceo-dashboard-settings.php' => ['name="summary_file"', 'export=summary_template', 'name="excel_file"', 'export=template'],
    'admin/manager-dashboard-import.php' => ['type="file"', 'manager-dashboard-export.php?template=1'],
    'admin/payroll-import.php' => ['type="file"', 'action=template'],
    'admin/pharmacy-settings.php' => ['type="file"', 'export=template'],
    'admin/users-import-export.php' => ['type="file"', 'action=template'],
    'admin/sales-planning.php' => ['download=coefficients', 'download=priorities', 'download=targets'],
];

foreach ($pageContracts as $relativePath => $tokens) {
    $path = $root . '/' . $relativePath;
    if (!is_file($path)) $fail('Import/export page is missing: ' . $relativePath);
    $contents = (string)file_get_contents($path);
    foreach ($tokens as $token) {
        if (!str_contains($contents, $token)) {
            $fail('Import template coverage is missing in ' . $relativePath . ': ' . $token);
        }
    }
}

$sources = ImportSourceRegistry::all();
$expectedSources = [
    'sales_aggregate',
    'purchase_aggregate',
    'inventory_aggregate',
    'sales_targets',
    'product_priorities',
    'customer_coefficients',
    'attendance',
];
foreach ($expectedSources as $source) {
    if (!isset($sources[$source])) $fail('Import source has no registry entry: ' . $source);
    $workbook = ImportTemplateService::workbook([$source]);
    if (count($workbook) < 2) $fail('Template workbook must include a data sheet and guide: ' . $source);
    $firstSheet = array_key_first($workbook);
    $rows = $workbook[$firstSheet] ?? [];
    if (count($rows) < 2 || count($rows[0] ?? []) < 1) {
        $fail('Template workbook has no header/sample row: ' . $source);
    }
    if (!isset($workbook['راهنما'])) $fail('Template workbook has no Persian guide: ' . $source);
}

$allWorkbook = ImportTemplateService::workbook($expectedSources);
if (count($allWorkbook) !== count($expectedSources) + 1) {
    $fail('Combined import template does not include every source plus the guide sheet.');
}

echo "Import template coverage contract: PASS\n";
