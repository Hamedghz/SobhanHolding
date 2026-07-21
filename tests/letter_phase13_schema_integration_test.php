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
require_once $root . '/core/LetterModule.php';
LetterModule::repair($pdo);
LetterModule::repair($pdo);
$column = static function (string $table, string $name) use ($pdo): bool {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
    $stmt->execute([$table, $name]);
    return (int)$stmt->fetchColumn() === 1;
};
foreach (['background_mime','margin_top_mm','margin_right_mm','margin_bottom_mm','margin_left_mm','header_position_mm','footer_position_mm','is_default'] as $name) {
    if (!$column('letter_letterheads', $name)) throw new RuntimeException('Missing letterhead column: ' . $name);
}
if (!$column('letter_templates', 'default_delta_json')) throw new RuntimeException('Missing template Delta column.');
if (!$column('organizational_letters', 'body_delta_json')) throw new RuntimeException('Missing letter Delta column.');

echo "Letter phase 13 schema integration: PASS\n";
