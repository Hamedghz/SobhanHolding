<?php

$root = dirname(__DIR__);
$dsn = getenv('SOBHAN_TEST_DSN') ?: '';
$user = getenv('SOBHAN_TEST_DB_USER') ?: 'root';
$password = getenv('SOBHAN_TEST_DB_PASSWORD') ?: '';
if ($dsn === '') {
    fwrite(STDERR, "SOBHAN_TEST_DSN is required.\n");
    exit(2);
}

$pdo = new PDO($dsn, $user, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);

$sql = (string)file_get_contents($root.'/database/schema.sql');
$statements = preg_split('/;\s*(?:\r?\n|$)/', $sql, -1, PREG_SPLIT_NO_EMPTY) ?: [];
for ($pass = 1; $pass <= 2; $pass++) {
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if ($statement === '' || str_starts_with($statement, '--')) {
            $statement = preg_replace('/^(?:--[^\r\n]*(?:\r?\n|$))+/', '', $statement) ?? $statement;
            $statement = trim($statement);
        }
        if ($statement !== '') $pdo->exec($statement);
    }
}

require_once $root.'/core/ImportCenterModule.php';
ImportCenterModule::repair($pdo);
ImportCenterModule::repair($pdo);

$table = static function (string $name) use ($pdo): bool {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
    $stmt->execute([$name]);
    return (int)$stmt->fetchColumn() === 1;
};
$column = static function (string $table, string $name) use ($pdo): bool {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
    $stmt->execute([$table,$name]);
    return (int)$stmt->fetchColumn() === 1;
};

foreach (['purchase_aggregate_rows','sales_reference_import_batches','staging_sales_reference_rows'] as $name) {
    if (!$table($name)) throw new RuntimeException('Missing import table: '.$name);
}
foreach ([
    ['sales_import_batches','pipeline_status'],
    ['sales_import_batches','snapshot_date'],
    ['sales_import_batches','period_id'],
    ['staging_sales_data','source_row_number'],
    ['staging_sales_data','source_row_hash'],
    ['inventory_aggregate_rows','snapshot_date'],
    ['hr_attendance_entries','import_batch_id'],
] as [$tableName,$columnName]) {
    if (!$column($tableName,$columnName)) throw new RuntimeException("Missing column: {$tableName}.{$columnName}");
}
foreach (['vw_active_purchase_aggregate_rows','vw_active_sales_targets','vw_active_product_priorities','vw_active_customer_class_coefficients'] as $view) {
    if (!$table($view)) throw new RuntimeException('Missing active import view: '.$view);
}
$permissions = (int)$pdo->query("SELECT COUNT(*) FROM modules WHERE module_key LIKE 'import_center.%'")->fetchColumn();
if ($permissions !== count(ImportCenterModule::PERMISSIONS)) throw new RuntimeException('Import permissions are not idempotent.');

echo "Unified import schema integration: PASS\n";
