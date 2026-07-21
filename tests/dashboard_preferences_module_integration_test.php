<?php

$root = dirname(__DIR__);
$dsn = getenv('SOBHAN_TEST_DSN') ?: '';
$user = getenv('SOBHAN_TEST_DB_USER') ?: 'root';
$password = getenv('SOBHAN_TEST_DB_PASSWORD') ?: '';
if ($dsn === '') {
    fwrite(STDERR, "SOBHAN_TEST_DSN is required.\n");
    exit(2);
}

require_once $root . '/core/DashboardModule.php';
$pdo = new PDO($dsn, $user, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

DashboardModule::repair($pdo);
$pdo->exec(
    "UPDATE dashboard_widget_preferences
     SET visible=0,title_override='عنوان سفارشی'
     WHERE scope_type='supervisor' AND scope_id=0 AND user_id=0 AND widget_key='actions'"
);
DashboardModule::repair($pdo);

$expected = array_sum(array_map('count', DashboardModule::definitions()));
$count = (int)$pdo->query('SELECT COUNT(*) FROM dashboard_widget_preferences WHERE scope_id=0 AND user_id=0')->fetchColumn();
$custom = $pdo->query("SELECT visible,title_override FROM dashboard_widget_preferences WHERE scope_type='supervisor' AND widget_key='actions' LIMIT 1")->fetch();
$duplicates = (int)$pdo->query('SELECT COUNT(*) FROM (SELECT scope_type,scope_id,user_id,widget_key FROM dashboard_widget_preferences GROUP BY scope_type,scope_id,user_id,widget_key HAVING COUNT(*)>1) d')->fetchColumn();

if ($count !== $expected) throw new RuntimeException("Expected {$expected} defaults, got {$count}");
if ((int)$custom['visible'] !== 0 || $custom['title_override'] !== 'عنوان سفارشی') throw new RuntimeException('User preference was not preserved.');
if ($duplicates !== 0) throw new RuntimeException('Duplicate dashboard preferences found.');

echo "Dashboard preferences integration: PASS\n";
