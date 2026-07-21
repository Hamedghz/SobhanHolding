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
$sql = (string)file_get_contents($root . '/database/schema.sql');
$statements = preg_split('/;\s*(?:\r?\n|$)/', $sql, -1, PREG_SPLIT_NO_EMPTY) ?: [];
foreach ($statements as $statement) {
    $statement = preg_replace('/^(?:\s*--[^\r\n]*(?:\r?\n|$))+/', '', trim($statement)) ?? $statement;
    if (trim($statement) !== '') $pdo->exec(trim($statement));
}
require_once $root . '/core/ReportingViewsModule.php';
ReportingViewsModule::repair($pdo);
ReportingViewsModule::repair($pdo);

$stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.VIEWS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
foreach (ReportingViewsModule::VIEW_NAMES as $view) {
    $stmt->execute([$view]);
    if ((int)$stmt->fetchColumn() !== 1) throw new RuntimeException('Missing reporting view: ' . $view);
    $pdo->query("SELECT * FROM `{$view}` LIMIT 1");
}

$inactiveLeak = (int)$pdo->query(
    "SELECT COUNT(*) FROM vw_sales_active v
     LEFT JOIN sales_import_batches b ON b.id=v.import_batch_id
     WHERE b.id IS NULL OR b.status<>'committed' OR b.is_active_reference<>1"
)->fetchColumn();
if ($inactiveLeak !== 0) throw new RuntimeException('Inactive sales batch leaked into vw_sales_active.');

echo "Reporting views phase 16 schema integration: PASS\n";
