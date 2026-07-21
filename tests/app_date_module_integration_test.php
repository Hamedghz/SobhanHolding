<?php

$root = dirname(__DIR__);
$dsn = getenv('SOBHAN_TEST_DSN') ?: '';
$user = getenv('SOBHAN_TEST_DB_USER') ?: 'root';
$password = getenv('SOBHAN_TEST_DB_PASSWORD') ?: '';
if ($dsn === '') {
    fwrite(STDERR, "SOBHAN_TEST_DSN is required.\n");
    exit(2);
}

require_once $root . '/core/AppDateModule.php';

$pdo = new PDO($dsn, $user, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

AppDateModule::repair($pdo);
$pdo->exec(
    "INSERT INTO system_periods
     (period_key,title,period_type,start_date,end_date,scope_key,is_current,is_system,is_active,sort_order)
     VALUES ('custom:test-team','دوره آزمایشی تیم','custom','2026-01-01','2026-01-31','team:1',0,0,1,10)
     ON DUPLICATE KEY UPDATE
        title=VALUES(title),
        start_date=VALUES(start_date),
        end_date=VALUES(end_date),
        scope_key=VALUES(scope_key),
        is_system=0,
        is_active=1,
        sort_order=VALUES(sort_order)"
);
AppDateModule::repair($pdo);

$systemCount = (int)$pdo->query('SELECT COUNT(*) FROM system_periods WHERE is_system=1')->fetchColumn();
$customCount = (int)$pdo->query("SELECT COUNT(*) FROM system_periods WHERE period_key='custom:test-team' AND is_system=0")->fetchColumn();
$duplicateCount = (int)$pdo->query('SELECT COUNT(*) FROM (SELECT period_key FROM system_periods GROUP BY period_key HAVING COUNT(*)>1) d')->fetchColumn();
$currentTypes = (int)$pdo->query('SELECT COUNT(DISTINCT period_type) FROM system_periods WHERE is_system=1 AND is_current=1')->fetchColumn();

if ($systemCount !== 53) throw new RuntimeException('Expected 53 system periods, got ' . $systemCount);
if ($customCount !== 1) throw new RuntimeException('Custom period was not preserved.');
if ($duplicateCount !== 0) throw new RuntimeException('Duplicate period keys found.');
if ($currentTypes !== 6) throw new RuntimeException('Expected six current system period types, got ' . $currentTypes);

echo "AppDate module integration: PASS\n";
