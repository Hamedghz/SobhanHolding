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
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES=>false,
]);
$sql = (string)file_get_contents($root . '/database/schema.sql');
$statements = preg_split('/;\s*(?:\r?\n|$)/', $sql, -1, PREG_SPLIT_NO_EMPTY) ?: [];
foreach ($statements as $statement) {
    $statement = preg_replace('/^(?:\s*--[^\r\n]*(?:\r?\n|$))+/', '', trim($statement)) ?? $statement;
    if (trim($statement) !== '') $pdo->exec(trim($statement));
}
require_once $root . '/core/ActionHubModule.php';
ActionHubModule::repair($pdo);
ActionHubModule::repair($pdo);

$table = static function(string $name) use ($pdo): bool {
    $stmt=$pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
    $stmt->execute([$name]);
    return (int)$stmt->fetchColumn()===1;
};
$index = static function(string $table,string $name) use ($pdo): bool {
    $stmt=$pdo->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=?');
    $stmt->execute([$table,$name]);
    return (int)$stmt->fetchColumn()>0;
};
foreach (['action_types','action_templates','action_template_fields','actions','action_field_values','action_links','action_logs'] as $name) {
    if (!$table($name)) throw new RuntimeException('Missing Action Hub table: ' . $name);
}
foreach ([['actions','uq_actions_legacy'],['actions','idx_actions_assigned'],['action_template_fields','uq_action_template_field'],['action_logs','idx_action_logs_action']] as [$tableName,$indexName]) {
    if (!$index($tableName,$indexName)) throw new RuntimeException("Missing index: {$tableName}.{$indexName}");
}
if ((int)$pdo->query("SELECT COUNT(*) FROM action_types WHERE code='general'")->fetchColumn() !== 1) {
    throw new RuntimeException('General action type is not idempotent.');
}
if ((int)$pdo->query("SELECT COUNT(*) FROM modules WHERE module_key LIKE 'action_hub.%'")->fetchColumn() !== count(ActionHubModule::PERMISSIONS)) {
    throw new RuntimeException('Action Hub permissions are not idempotent.');
}
echo "Action Hub schema integration: PASS\n";
