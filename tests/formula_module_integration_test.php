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
]);
$engine = ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
$pdo->exec(
    "CREATE TABLE IF NOT EXISTS users (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(150) NOT NULL,
        email VARCHAR(190) NOT NULL UNIQUE,
        username VARCHAR(100) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        role VARCHAR(30) NOT NULL DEFAULT 'employee',
        status VARCHAR(30) NOT NULL DEFAULT 'active',
        employee_no VARCHAR(50) NULL
    ){$engine}"
);
$pdo->exec(
    "CREATE TABLE IF NOT EXISTS modules (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        module_key VARCHAR(100) NOT NULL UNIQUE,
        module_title VARCHAR(190) NOT NULL,
        sort_order INT NOT NULL DEFAULT 0,
        status VARCHAR(30) NOT NULL DEFAULT 'active',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ){$engine}"
);
$pdo->exec(
    "CREATE TABLE IF NOT EXISTS sales_offer_formula_settings (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        formula_key VARCHAR(100) NOT NULL UNIQUE,
        title VARCHAR(255) NOT NULL,
        formula_version VARCHAR(50) NOT NULL,
        settings_json LONGTEXT NULL,
        active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL
    ){$engine}"
);
$pdo->exec(
    "INSERT INTO sales_offer_formula_settings(formula_key,title,formula_version,settings_json,active)
     VALUES ('offer_budget_provisional','فرمول قدیمی','provisional_v1','{\"default_offer_rate\":0.05}',1)
     ON DUPLICATE KEY UPDATE settings_json=VALUES(settings_json)"
);
$pdo->exec(
    "INSERT INTO users(id,name,email,username,password_hash,role,status)
     VALUES (1,'مدیر آزمون','formula-admin@example.test','formula-admin','test','admin','active')
     ON DUPLICATE KEY UPDATE name=VALUES(name)"
);

require_once $root . '/core/Database.php';
$reflection = new ReflectionClass(Database::class);
$pdoProperty = $reflection->getProperty('pdo');
$pdoProperty->setValue(null, $pdo);
$migratedProperty = $reflection->getProperty('migrated');
$migratedProperty->setValue(null, true);

require_once $root . '/core/FormulaModule.php';
require_once $root . '/lib/FormulaRepository.php';
require_once $root . '/services/FormulaRuntime.php';
FormulaModule::repair($pdo);
FormulaModule::repair($pdo);
$seededOffer = $pdo->query(
    "SELECT v.status,v.result_value
     FROM formula_definitions d JOIN formula_versions v ON v.definition_id=d.id
     WHERE d.formula_key='offer_budget_provisional' ORDER BY v.version_no DESC LIMIT 1"
)->fetch();
if (!$seededOffer || $seededOffer['status'] !== 'draft' || (float)$seededOffer['result_value'] !== 5.0) {
    throw new RuntimeException('Legacy offer formula was not migrated to a safe draft.');
}

$baseInput = [
    'formula_key' => 'integration_commission',
    'title' => 'پورسانت یکپارچه',
    'category_key' => 'commission',
    'data_source_key' => 'sample_input',
    'metric_key' => 'net_amount',
    'aggregation_key' => 'SUM',
    'operator_key' => '>=',
    'condition_value' => '100',
    'result_type' => 'percent_of_metric',
    'result_value' => '10',
    'priority' => 100,
    'effective_from' => '1405/01/01',
    'effective_to' => '1405/12/29',
    'active' => 1,
];
$versionA = FormulaRepository::saveDraft(FormulaEngine::normalizeBuilderInput($baseInput), 1);
FormulaRepository::publish($versionA, 1);
$active = FormulaRepository::version($versionA);
if (($active['status'] ?? '') !== 'active') throw new RuntimeException('Formula was not published.');
$runtime = FormulaRuntime::evaluateByKey('integration_commission', ['net_amount' => 250], '2026-07-01');
if ($runtime === null || (float)$runtime['final_result'] !== 25.0) {
    throw new RuntimeException('Published formula runtime adapter is incorrect.');
}

$conflictInput = $baseInput;
$conflictInput['formula_key'] = 'integration_commission_conflict';
$conflictInput['title'] = 'پورسانت متداخل';
$conflictVersion = FormulaRepository::saveDraft(FormulaEngine::normalizeBuilderInput($conflictInput), 1);
$conflictBlocked = false;
try {
    FormulaRepository::publish($conflictVersion, 1);
} catch (InvalidArgumentException) {
    $conflictBlocked = true;
}
if (!$conflictBlocked) throw new RuntimeException('Formula conflict was not blocked.');

$dependentInput = $baseInput;
$dependentInput['formula_key'] = 'integration_target';
$dependentInput['title'] = 'تارگت وابسته';
$dependentInput['category_key'] = 'target';
$dependentInput['priority'] = 200;
$dependentInput['dependency_ids'] = [(int)$active['definition_id']];
$dependentVersion = FormulaRepository::saveDraft(FormulaEngine::normalizeBuilderInput($dependentInput), 1);
$dependent = FormulaRepository::version($dependentVersion);

$cycleInput = $baseInput;
$cycleInput['dependency_ids'] = [(int)$dependent['definition_id']];
$cycleBlocked = false;
try {
    FormulaRepository::saveDraft(
        FormulaEngine::normalizeBuilderInput($cycleInput),
        1,
        (int)$active['definition_id']
    );
} catch (InvalidArgumentException) {
    $cycleBlocked = true;
}
if (!$cycleBlocked) throw new RuntimeException('Circular dependency was not blocked.');

$test = FormulaRepository::runTest($versionA, [
    'sample_values' => ['net_amount' => 250],
], 1);
if (!$test['matched'] || (float)$test['final_result'] !== 25.0) {
    throw new RuntimeException('Formula test result is incorrect.');
}

$rollbackDraft = FormulaRepository::rollbackToVersion($versionA, 1);
$rollback = FormulaRepository::version($rollbackDraft);
if (($rollback['status'] ?? '') !== 'draft' || (int)$rollback['version_no'] <= (int)$active['version_no']) {
    throw new RuntimeException('Rollback did not create a new draft version.');
}

$duplicates = (int)$pdo->query(
    'SELECT COUNT(*) FROM (
        SELECT definition_id,version_no FROM formula_versions
        GROUP BY definition_id,version_no HAVING COUNT(*)>1
    ) duplicates'
)->fetchColumn();
$auditCount = (int)$pdo->query('SELECT COUNT(*) FROM formula_audit_logs')->fetchColumn();
$testCount = (int)$pdo->query('SELECT COUNT(*) FROM formula_test_runs')->fetchColumn();
$permissionCount = (int)$pdo->query("SELECT COUNT(*) FROM modules WHERE module_key LIKE 'formula_builder.%'")->fetchColumn();
if ($duplicates !== 0) throw new RuntimeException('Duplicate formula versions found.');
if ($auditCount < 4 || $testCount !== 1 || $permissionCount !== count(FormulaModule::PERMISSIONS)) {
    throw new RuntimeException('Formula audit, test, or permission repair is incomplete.');
}

echo "Formula module integration: PASS\n";
