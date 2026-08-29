<?php

$root = dirname(__DIR__);
require_once $root . '/core/SalesAggregateImportService.php';
require_once $root . '/core/InventoryImportService.php';
require_once $root . '/lib/ImportSourceRegistry.php';
require_once $root . '/lib/ImportTemplateService.php';
require_once $root . '/services/UnifiedImportService.php';

$sources = ImportSourceRegistry::all();
$expected = [
    'sales_aggregate' => ['profile' => 'ERP_SALES_AGGREGATE_V1', 'count' => 109, 'table' => 'tblsales_raw', 'sheet' => 'گزارش تجمیعی فروش'],
    'purchase_aggregate' => ['profile' => 'ERP_PURCHASE_AGGREGATE_RAW_V1', 'count' => 40, 'table' => 'tblbuy_raw', 'sheet' => 'گزارش تجمیعی خرید'],
    'inventory_aggregate' => ['profile' => 'ERP_INVENTORY_AGGREGATE_RAW_V1', 'count' => 132, 'table' => 'tblwh_raw', 'sheet' => 'گزارش موجودی انبار1'],
];
foreach ($expected as $key => $contract) {
    $source = $sources[$key] ?? [];
    if (($source['source_profile'] ?? '') !== $contract['profile']) throw new RuntimeException('Missing profile for '.$key);
    if (count($source['canonical_headers'] ?? []) !== $contract['count']) throw new RuntimeException('Wrong header count for '.$key);
    if (!in_array($contract['table'], $source['tables'] ?? [], true)) throw new RuntimeException('Raw table alias missing for '.$key);
    if (!in_array($contract['sheet'], $source['sheets'] ?? [], true)) throw new RuntimeException('Raw sheet alias missing for '.$key);
    $template = ImportTemplateService::workbook([$key]);
    $spec = array_values($template)[0] ?? [];
    if (($spec['rows'] ?? []) !== [array_values($source['canonical_headers'])]) throw new RuntimeException('Canonical template mismatch for '.$key);
}
$rawReport = SalesAggregateImportService::canonicalHeaderReport(SalesDataNormalizer::rawCanonicalHeaders());
if (empty($rawReport['is_exact']) || ($rawReport['source_profile'] ?? '') !== 'ERP_SALES_AGGREGATE_RAW_V1') throw new RuntimeException('Raw sales contract comparison failed.');
$inventoryReport = ImportSourceRegistry::canonicalHeaderReport(
    ImportSourceRegistry::get('inventory_aggregate')['canonical_headers'],
    ImportSourceRegistry::get('inventory_aggregate')
);
if (empty($inventoryReport['is_exact']) || ($inventoryReport['expected_count'] ?? 0) !== 132) throw new RuntimeException('Inventory contract comparison failed.');
$bundleTemplate = ImportTemplateService::workbook(UnifiedImportService::BUDGET_INPUT_SOURCES);
if (count($bundleTemplate) !== 4 || !isset($bundleTemplate['راهنما'])) throw new RuntimeException('Budget bundle template mismatch.');

echo "ERP input contract: PASS\n";
