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
SalesReferenceSchema::repair($pdo);
seedImportIntegrationMappings($pdo);
Auth::start();
$_SESSION['user_id'] = 1;

$beforeBatches = (int)$pdo->query('SELECT COUNT(*) FROM sales_import_batches')->fetchColumn();
$beforeFiles = count(glob($root . '/storage/unified-imports/*') ?: []);
$blocked = false;
try {
    UnifiedImportService::upload([
        'error'=>UPLOAD_ERR_OK,
        'tmp_name'=>$path,
        'size'=>filesize($path),
        'name'=>'duplicate-name-must-not-matter.xlsx',
    ], 'sales_aggregate', 'skip_duplicates', 1, [], true);
} catch (DomainException $error) {
    $blocked = str_contains($error->getMessage(), 'قبلاً');
}

$afterBatches = (int)$pdo->query('SELECT COUNT(*) FROM sales_import_batches')->fetchColumn();
$afterFiles = count(glob($root . '/storage/unified-imports/*') ?: []);
if (!$blocked || $afterBatches !== $beforeBatches || $afterFiles !== $beforeFiles) {
    throw new RuntimeException('Duplicate file guard did not reject cleanly.');
}

echo "Large workbook duplicate guard acceptance: PASS\n";
