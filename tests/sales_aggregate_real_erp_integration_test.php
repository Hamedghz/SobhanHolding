<?php

$root = dirname(__DIR__);
require_once $root . '/core/SalesAggregateImportService.php';
require_once $root . '/core/SalesDataSchema.php';
require_once $root . '/core/SalesReferenceSchema.php';
require_once $root . '/tests/support/import_integration_bootstrap.php';

$path = getenv('SOBHAN_ERP_SALES_XLSX') ?: '';
if ($path === '' || !is_file($path)) {
    fwrite(STDERR, "SOBHAN_ERP_SALES_XLSX is required.\n");
    exit(2);
}

$pdo = importIntegrationPdo($root);
SalesDataSchema::repair($pdo);
SalesReferenceSchema::repair($pdo);
SalesDataSchema::repair($pdo);
SalesReferenceSchema::repair($pdo);
seedImportIntegrationMappings($pdo);

$result = SalesAggregateImportService::readUploadedFile([
    'error'=>UPLOAD_ERR_OK,
    'tmp_name'=>$path,
    'size'=>filesize($path),
    'name'=>'erp-file-name-must-not-matter.xlsx',
], 'update_existing', 1);

if (!empty($result['needs_selection'])) throw new RuntimeException('Real ERP workbook should be selected automatically.');
$summary = $result['summary'] ?? [];
if ((int)($summary['total_rows'] ?? 0) < 1) throw new RuntimeException('Real ERP workbook staged no rows.');
if ((int)($summary['invalid_rows'] ?? 0) !== 0) throw new RuntimeException('Real ERP workbook contains rejected rows: '.json_encode($summary, JSON_UNESCAPED_UNICODE));
if ((int)($summary['ready_rows'] ?? 0) !== (int)$summary['total_rows']) throw new RuntimeException('Not every real ERP row is ready to commit.');

$batch = Database::fetch('SELECT * FROM sales_import_batches WHERE id=?', [$result['batch_id']]);
$metadata = json_decode((string)($batch['metadata_json'] ?? ''), true) ?: [];
if (($batch['detected_table'] ?? '') !== 'tbltajmii' || trim((string)($batch['detected_sheet'] ?? '')) !== 'گزارش تجمیعی فروش') throw new RuntimeException('Real ERP table/sheet detection failed.');
if (($metadata['source_profile'] ?? '') !== 'ERP_SALES_AGGREGATE_V1' || empty($metadata['header_contract']['is_exact'])) throw new RuntimeException('Real ERP source profile/header contract failed.');

$staged = Database::fetch('SELECT normalized_json,raw_json,source_row_number FROM staging_sales_data WHERE import_batch_id=? ORDER BY id LIMIT 1', [$result['batch_id']]);
$normalized = json_decode((string)($staged['normalized_json'] ?? ''), true) ?: [];
$raw = json_decode((string)($staged['raw_json'] ?? ''), true) ?: [];
if (!array_key_exists('ماه گردش', $raw) || trim((string)($normalized['turnover_month'] ?? '')) === '') throw new RuntimeException('turnover_month was not preserved in staging.');
if (empty($normalized['turnover_year']) || empty($normalized['period_key'])) throw new RuntimeException('Turnover year/period key were not derived.');

$commit = SalesAggregateImportService::commitValidRows((int)$result['batch_id'], 1, true);
if (($commit['imported'] + $commit['updated']) !== (int)$summary['total_rows']) throw new RuntimeException('Committed row count does not match staged row count.');
$final = Database::fetch('SELECT turnover_month,turnover_year,period_key,raw_json FROM sales_aggregate_rows WHERE import_batch_id=? ORDER BY id LIMIT 1', [$result['batch_id']]);
if (!$final || trim((string)$final['turnover_month']) === '' || empty($final['turnover_year']) || empty($final['period_key'])) throw new RuntimeException('Turnover fields were not preserved in final data.');
$active = SalesReferenceRepository::getActiveReferenceBatch('sales_aggregate');
if ((int)($active['id'] ?? 0) !== (int)$result['batch_id']) throw new RuntimeException('Committed ERP batch was not activated.');

echo json_encode([
    'batch_id'=>(int)$result['batch_id'],
    'detected_sheet'=>$batch['detected_sheet'],
    'detected_table'=>$batch['detected_table'],
    'detected_range'=>$batch['detected_range'] ?? null,
    'summary'=>$summary,
    'commit'=>$commit,
    'activated'=>true,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), "\n";
