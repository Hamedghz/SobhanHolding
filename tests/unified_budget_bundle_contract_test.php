<?php

$root = dirname(__DIR__);
require_once $root . '/core/SpreadsheetImportReader.php';
require_once $root . '/services/UnifiedImportService.php';
require_once $root . '/lib/ImportSourceRegistry.php';

$sources = ImportSourceRegistry::all();
$workbook = ['sheets' => []];
foreach (UnifiedImportService::BUDGET_INPUT_SOURCES as $sourceKey) {
    $source = $sources[$sourceKey];
    $workbook['sheets'][] = [
        'name' => $source['canonical_sheet'],
        'visible' => true,
        'rows' => [$source['canonical_headers'], []],
        'tables' => [],
    ];
}

$candidates = UnifiedImportService::detectWorkbook($workbook, 'sales_aggregate');
$detected = array_values(array_unique(array_column($candidates, 'source_module')));
foreach (UnifiedImportService::BUDGET_INPUT_SOURCES as $sourceKey) {
    if (!in_array($sourceKey, $detected, true)) throw new RuntimeException('Combined budget source was not detected: '.$sourceKey);
}

$service = file_get_contents($root . '/services/UnifiedImportService.php');
foreach (['BUDGET_INPUT_SOURCES','commitBundle','bundle_batch_ids','stageBudgetInputBundle'] as $token) {
    if (!str_contains($service, $token)) throw new RuntimeException('Missing bundle contract: '.$token);
}

echo "Unified budget bundle contract: PASS\n";
