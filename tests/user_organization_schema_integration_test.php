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
    $statement = trim($statement);
    if ($statement === '' || str_starts_with($statement, '--')) {
        $statement = preg_replace('/^(?:--[^\r\n]*(?:\r?\n|$))+/', '', $statement) ?? $statement;
        $statement = trim($statement);
    }
    if ($statement !== '') $pdo->exec($statement);
}

require_once $root . '/core/Database.php';
require_once $root . '/core/SalesStructureModule.php';
SalesStructureModule::repair($pdo);
SalesStructureModule::repair($pdo);

$column = static function (string $table, string $name) use ($pdo): bool {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
    $stmt->execute([$table, $name]);
    return (int)$stmt->fetchColumn() === 1;
};
$index = static function (string $table, string $name) use ($pdo): bool {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=?');
    $stmt->execute([$table, $name]);
    return (int)$stmt->fetchColumn() > 0;
};

foreach (['kara_system_code', 'sales_line_id'] as $name) {
    if (!$column('users', $name)) throw new RuntimeException('Missing users column: ' . $name);
}
foreach (['uq_users_kara_system_code', 'idx_users_sales_line_id'] as $name) {
    if (!$index('users', $name)) throw new RuntimeException('Missing users index: ' . $name);
}

$lineCount = (int)$pdo->query("SELECT COUNT(*) FROM sales_lines WHERE code IN ('A','B','C','D')")->fetchColumn();
$geoCount = (int)$pdo->query("SELECT COUNT(*) FROM sales_geographies WHERE code IN ('ZABOL','ZAHEDAN','KHASH','SARAVAN','IRANSHAHR','NIKSHAHR','KONARAK','CHABAHAR','ZAHEDAN_R1','ZAHEDAN_R2','ZAHEDAN_R3')")->fetchColumn();
if ($lineCount !== 4) throw new RuntimeException('Default sales lines are not idempotent.');
if ($geoCount !== 11) throw new RuntimeException('Default sales geographies are not idempotent.');

$lineA = (int)$pdo->query("SELECT id FROM sales_lines WHERE code='A'")->fetchColumn();
$email = 'phase6-' . bin2hex(random_bytes(5)) . '@example.test';
$username = 'phase6_' . bin2hex(random_bytes(5));
$stmt = $pdo->prepare(
    "INSERT INTO users(name,email,username,password_hash,role,status,sales_line,created_at,updated_at)
     VALUES ('Phase 6 Backfill',?,?,?,'employee','active','A',NOW(),NOW())"
);
$stmt->execute([$email, $username, password_hash('Test-only-password-123!', PASSWORD_DEFAULT)]);
$userId = (int)$pdo->lastInsertId();
SalesStructureModule::repair($pdo);
$backfilled = (int)$pdo->query('SELECT sales_line_id FROM users WHERE id=' . $userId)->fetchColumn();
if ($backfilled !== $lineA) throw new RuntimeException('Legacy sales_line was not linked to sales_line_id.');

echo "User organization schema integration: PASS\n";
