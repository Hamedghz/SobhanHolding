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

require_once $root . '/core/SalesPlanningModule.php';
SalesPlanningModule::repair($pdo);
SalesPlanningModule::repair($pdo);

$column = static function (string $table, string $name) use ($pdo): bool {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
    $stmt->execute([$table,$name]);
    return (int)$stmt->fetchColumn() === 1;
};
$index = static function (string $table, string $name) use ($pdo): bool {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=?');
    $stmt->execute([$table,$name]);
    return (int)$stmt->fetchColumn() > 0;
};
$view = static function (string $name) use ($pdo): bool {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.VIEWS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?");
    $stmt->execute([$name]);
    return (int)$stmt->fetchColumn() === 1;
};

foreach ([
    ['sales_customer_class_coefficients','period_id'],
    ['sales_customer_class_coefficients','guild_identity_key'],
    ['sales_customer_class_coefficients','version_no'],
    ['product_priorities','period_id'],
    ['product_priorities','status'],
    ['sales_targets','period_id'],
    ['sales_targets','visitor_user_id'],
    ['sales_targets','line_id'],
    ['sales_targets','allocation_percent'],
] as [$table,$name]) {
    if (!$column($table, $name)) throw new RuntimeException("Missing column: {$table}.{$name}");
}
foreach ([
    ['sales_customer_class_coefficients','idx_sales_coeff_identity'],
    ['product_priorities','idx_product_priorities_period'],
    ['sales_targets','idx_sales_targets_grain'],
] as [$table,$name]) {
    if (!$index($table, $name)) throw new RuntimeException("Missing index: {$table}.{$name}");
}
foreach ([
    'vw_active_customer_class_coefficients','vw_active_product_priorities','vw_active_sales_targets',
    'vw_sales_target_achievement','vw_sales_target_visitor_totals','vw_sales_target_line_products',
    'vw_sales_target_line_totals','vw_sales_target_brand_totals',
] as $name) {
    if (!$view($name)) throw new RuntimeException('Missing planning view: ' . $name);
}
$permissions = (int)$pdo->query("SELECT COUNT(*) FROM modules WHERE module_key LIKE 'sales_planning.%'")->fetchColumn();
if ($permissions !== count(SalesPlanningModule::PERMISSIONS)) {
    throw new RuntimeException('Sales planning permissions are not idempotent.');
}

echo "Sales planning schema integration: PASS\n";

