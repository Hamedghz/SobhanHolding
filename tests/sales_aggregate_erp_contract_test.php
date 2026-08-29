<?php

$root = dirname(__DIR__);
require_once $root . '/core/CeoDashboardExcel.php';
require_once $root . '/core/SpreadsheetImportReader.php';
require_once $root . '/core/SalesAggregateImportService.php';
require_once $root . '/lib/ImportSourceRegistry.php';
require_once $root . '/lib/ImportTemplateService.php';

$expected = SalesDataNormalizer::CANONICAL_HEADERS;
if (count($expected) !== 109 || count(array_unique($expected)) !== 109) throw new RuntimeException('Canonical header list must contain 109 unique headers.');

$source = ImportSourceRegistry::get('sales_aggregate');
if (($source['source_profile'] ?? '') !== 'ERP_SALES_AGGREGATE_V1') throw new RuntimeException('Canonical source profile is missing.');
if (($source['canonical_table'] ?? '') !== 'tbltajmii' || ($source['canonical_sheet'] ?? '') !== 'گزارش تجمیعی فروش') throw new RuntimeException('Worksheet/table contract is wrong.');

$mappings = SalesDataNormalizer::canonicalMappingDefinitions();
if (count($mappings) !== 109) throw new RuntimeException('All 109 canonical headers must have mappings.');
foreach ($mappings as $index => $mapping) {
    if (($mapping['source_header'] ?? '') !== $expected[$index]) throw new RuntimeException('Canonical mapping order mismatch at column '.($index + 1));
    if (!in_array($mapping['status'] ?? '', ['stored','mapped','ignored_with_reason','derived','optional'], true)) throw new RuntimeException('Mapping status is missing.');
}

$template = ImportTemplateService::workbook(['sales_aggregate']);
if (array_keys($template) !== ['گزارش تجمیعی فروش']) throw new RuntimeException('Sales template must contain exactly one canonical worksheet.');
$spec = $template['گزارش تجمیعی فروش'];
if (($spec['table_name'] ?? '') !== 'tbltajmii' || ($spec['rows'] ?? []) !== [$expected]) throw new RuntimeException('Sales template must contain only the canonical header row.');

$templatePath = CeoDashboardExcel::write($template);
try {
    $workbook = SpreadsheetImportReader::read($templatePath, 'xlsx');
    $sheet = $workbook['sheets'][0] ?? [];
    if (trim((string)($sheet['name'] ?? '')) !== 'گزارش تجمیعی فروش') throw new RuntimeException('Generated worksheet name mismatch.');
    if (($sheet['tables'][0]['name'] ?? '') !== 'tbltajmii' || ($sheet['tables'][0]['ref'] ?? '') !== 'A1:DE1') throw new RuntimeException('Generated Excel Table contract mismatch.');
    if (($sheet['rows'][0] ?? []) !== $expected || count($sheet['rows'] ?? []) !== 1) throw new RuntimeException('Generated template has extra data or wrong headers.');
} finally {
    @unlink($templatePath);
}

$report = SalesAggregateImportService::canonicalHeaderReport($expected);
if (empty($report['is_exact']) || ($report['exact_matched_headers'] ?? 0) !== 109) throw new RuntimeException('109 / 109 exact header comparison failed.');
$malformed = $expected;
$malformed[1] = $malformed[0];
$bad = SalesAggregateImportService::canonicalHeaderReport($malformed);
if (!empty($bad['is_exact']) || empty($bad['duplicate_headers']) || empty($bad['missing_headers']) || empty($bad['order_mismatch'])) throw new RuntimeException('Malformed header report failed.');

$warning = SalesAggregateImportService::validateRow([
    'invoice_number'=>'1','invoice_date_raw'=>'1405/04/01','invoice_date'=>'2026-06-22','visitor_code'=>'V','visitor_name'=>'',
    'customer_code'=>'C','customer_name'=>'Customer','product_code'=>'P','product_name'=>'Product','quantity'=>'1',
    'gross_amount'=>'1','discount_amount'=>'0','net_amount'=>'1','invoice_type'=>'فروش','turnover_month'=>'5- مرداد',
]);
if (!array_filter($warning, static fn(array $item): bool => ($item['code'] ?? '') === 'PERIOD_MISMATCH' && ($item['severity'] ?? '') === 'warning')) throw new RuntimeException('PERIOD_MISMATCH warning contract failed.');

$referencePath = getenv('SOBHAN_ERP_SALES_XLSX') ?: '';
if ($referencePath !== '' && is_file($referencePath)) {
    $realWorkbook = SpreadsheetImportReader::read($referencePath, 'xlsx');
    $candidates = SalesAggregateImportService::detectWorkbookSource($realWorkbook);
    $candidate = $candidates[0] ?? [];
    if (($candidate['table_name'] ?? '') !== 'tbltajmii' || trim((string)($candidate['sheet_name'] ?? '')) !== 'گزارش تجمیعی فروش') throw new RuntimeException('Real ERP workbook detection failed.');
    $realReport = SalesAggregateImportService::canonicalHeaderReport($candidate['headers'] ?? []);
    if (empty($realReport['is_exact'])) throw new RuntimeException('Real ERP workbook headers are not 109 / 109 exact.');
}

echo "Sales aggregate ERP contract: PASS\n";
