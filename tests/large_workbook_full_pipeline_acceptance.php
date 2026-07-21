<?php

$root = dirname(__DIR__);
$path = getenv('SOBHAN_LARGE_XLSX') ?: '';
if ($path === '' || !is_file($path)) {
    fwrite(STDERR, "SOBHAN_LARGE_XLSX is required.\n");
    exit(2);
}

require_once $root . '/services/UnifiedImportService.php';
require_once $root . '/tests/support/import_integration_bootstrap.php';
require_once $root . '/core/SalesReferenceSchema.php';

$pdo = importIntegrationPdo($root);
// Mirror the real application bootstrap: compatibility columns and active
// reference views are installed by Database::repair() before imports run.
SalesReferenceSchema::repair($pdo);
seedImportIntegrationMappings($pdo);
$pdo->exec(
    "INSERT INTO users(id,name,email,username,password_hash,role,status,access_scope,admin_panel_enabled,created_at,updated_at)
     VALUES(1,'مدیر پذیرش فایل بزرگ','large-import@example.test','large-import','test','super_admin','active','all',1,NOW(),NOW())
     ON DUPLICATE KEY UPDATE role='super_admin',status='active',access_scope='all',admin_panel_enabled=1"
);

Auth::start();
$_SESSION['user_id'] = 1;
$storedPath = null;

try {
    $upload = UnifiedImportService::upload([
        'error'=>UPLOAD_ERR_OK,
        'tmp_name'=>$path,
        'size'=>filesize($path),
        'name'=>'large-source-name-is-irrelevant.xlsx',
    ], 'sales_aggregate', 'skip_duplicates', 1);
    if (!empty($upload['needs_selection'])) throw new RuntimeException('Large workbook source remained ambiguous.');
    $batchId = (int)($upload['batch_id'] ?? 0);
    $summary = $upload['summary'] ?? [];
    if ($batchId < 1 || (int)($summary['total_rows'] ?? 0) <= SpreadsheetImportReader::SAMPLE_ROWS) {
        throw new RuntimeException('Large workbook staging was truncated to the discovery sample.');
    }
    if ((int)($summary['ready_rows'] ?? 0) < 1) throw new RuntimeException('Large workbook has no committable rows.');

    $batch = Database::fetch('SELECT stored_file_path,pipeline_status FROM sales_import_batches WHERE id=?', [$batchId]);
    $storedPath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, (string)($batch['stored_file_path'] ?? ''));
    if (($batch['pipeline_status'] ?? '') !== 'ready_to_commit') throw new RuntimeException('Large workbook did not reach ready_to_commit.');

    $committed = UnifiedImportService::commit($batchId, 1, true);
    $finalCount = (int)$pdo->query('SELECT COUNT(*) FROM sales_aggregate_rows WHERE import_batch_id=' . $batchId)->fetchColumn();
    if ($finalCount !== (int)$committed['inserted'] || $finalCount < 1) throw new RuntimeException('Large workbook final row count does not match commit result.');
    $status = (string)$pdo->query('SELECT pipeline_status FROM sales_import_batches WHERE id=' . $batchId)->fetchColumn();
    if ($status !== 'activated') throw new RuntimeException('Large workbook batch was not activated.');

    echo json_encode([
        'batch_id'=>$batchId,
        'staged'=>$summary,
        'committed'=>$committed,
        'final_rows'=>$finalCount,
        'peak_memory_mb'=>round(memory_get_peak_usage(true) / 1048576, 1),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    echo "Large workbook full pipeline acceptance: PASS\n";
} finally {
    if ($storedPath && is_file($storedPath) && str_starts_with(realpath($storedPath) ?: '', realpath($root . '/storage/unified-imports') ?: '#')) {
        @unlink($storedPath);
    }
}
