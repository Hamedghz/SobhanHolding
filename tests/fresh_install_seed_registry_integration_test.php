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

$schema = (string)file_get_contents($root . '/database/schema.sql');
$statements = preg_split('/;\s*(?:\r?\n|$)/', $schema, -1, PREG_SPLIT_NO_EMPTY) ?: [];
foreach ($statements as $statement) {
    $statement = preg_replace('/^(?:\s*--[^\r\n]*(?:\r?\n|$))+/', '', trim($statement)) ?? $statement;
    if (trim($statement) !== '') $pdo->exec(trim($statement));
}

require_once $root . '/core/Database.php';
$database = new ReflectionClass(Database::class);
$database->getProperty('pdo')->setValue(null, $pdo);
$database->getProperty('migrated')->setValue(null, true);

$pdo->exec(
    "INSERT INTO users
        (id,name,email,username,password_hash,role,status,access_scope,admin_panel_enabled,created_at,updated_at)
     VALUES
        (1,'مدیر نصب آزمایشی','fresh-install-admin@example.test','fresh-install-admin','test','super_admin','active','all',1,NOW(),NOW())
     ON DUPLICATE KEY UPDATE role='super_admin',status='active',admin_panel_enabled=1"
);
$pdo->exec(
    "INSERT INTO hr_assessment_tests
        (title,code,description,scoring_type,time_limit_minutes,active,sort_order,seeded,seed_key,seed_version,is_seeded,is_archived,created_at,updated_at)
     VALUES
        ('آزمون Seed قدیمی','LEGACY_SEEDED_AUDIT','رکورد آزمون تاریخی','dimensions',20,1,999,1,'legacy_assessment_seed','v0',1,0,NOW(),NOW())"
);
$legacyTestId = (int)$pdo->lastInsertId();
$legacyHash = hash('sha256', 'legacy seeded audit question');
$stmt = $pdo->prepare(
    "INSERT INTO hr_assessment_questions
        (test_id,question_code,question_text,question_hash,answer_type,weight,required,sort_order,active,seeded,seed_key,seed_version,is_seeded,created_at,updated_at)
     VALUES
        (?,?,?,?, 'scale_1_5',1,1,10,1,1,'legacy_assessment_seed','v0',1,NOW(),NOW())"
);
$stmt->execute([$legacyTestId, 'LEGACY-Q1', 'سؤال تاریخی Seed', $legacyHash]);
$pdo->exec(
    "INSERT INTO hr_assessment_packages
        (title,code,description,active,seed_key,seed_version,is_seeded,created_at,updated_at)
     VALUES
        ('بسته Seed قدیمی','LEGACY_PACKAGE_AUDIT','بسته تاریخی',1,'legacy_assessment_seed','v0',1,NOW(),NOW())"
);

require_once $root . '/core/Installer.php';
require_once $root . '/core/SeedManager.php';
$keys = array_keys(SeedManager::registry());
$first = Installer::seedFreshDatabase($pdo, 1);
foreach ($first as $key => $result) {
    if (($result['status'] ?? '') !== 'completed' || (int)($result['errors'] ?? 0) !== 0) {
        throw new RuntimeException('Fresh seed failed: ' . $key);
    }
}

$legacyTest = $pdo->query(
    "SELECT active,is_archived FROM hr_assessment_tests WHERE code='LEGACY_SEEDED_AUDIT'"
)->fetch();
if (!$legacyTest || (int)$legacyTest['active'] !== 0 || (int)$legacyTest['is_archived'] !== 1) {
    throw new RuntimeException('Historical assessment test was not preserved as archived.');
}
$legacyQuestionActive = $pdo->query(
    "SELECT active FROM hr_assessment_questions WHERE question_code='LEGACY-Q1'"
)->fetchColumn();
if ($legacyQuestionActive === false || (int)$legacyQuestionActive !== 0) {
    throw new RuntimeException('Historical assessment question was not preserved as inactive.');
}
$legacyPackageActive = $pdo->query(
    "SELECT active FROM hr_assessment_packages WHERE code='LEGACY_PACKAGE_AUDIT'"
)->fetchColumn();
if ($legacyPackageActive === false || (int)$legacyPackageActive !== 0) {
    throw new RuntimeException('Historical assessment package was not preserved as inactive.');
}

$count = static fn(string $table): int => (int)$pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
$before = [];
foreach ([
    'site_settings',
    'modules',
    'hr_kpi_periods',
    'hr_kpi_templates',
    'hr_kpi_criteria',
    'hr_assessment_tests',
    'hr_assessment_questions',
    'hr_assessment_packages',
    'ai_reporting_sources',
    'dashboard_widget_preferences',
    'sales_import_column_mappings',
    'sales_lines',
    'sales_geographies',
    'sales_line_brands',
] as $table) {
    $before[$table] = $count($table);
}

if ($before['site_settings'] < 10) throw new RuntimeException('Core settings seed is incomplete.');
if ($before['modules'] < 60) throw new RuntimeException('Permission/module seed is incomplete.');
if ($before['hr_kpi_periods'] < 1 || $before['hr_kpi_templates'] < 1 || $before['hr_kpi_criteria'] < 1) {
    throw new RuntimeException('HR KPI seed is incomplete.');
}
if ($before['hr_assessment_tests'] !== 21 || $before['hr_assessment_questions'] !== 401) {
    throw new RuntimeException('Sobhan assessment battery is incomplete.');
}
if ($before['hr_assessment_packages'] < 8) throw new RuntimeException('Assessment role packages are incomplete.');
if ($before['ai_reporting_sources'] < 1) throw new RuntimeException('AI reporting sources seed is incomplete.');
$expectedDashboardPreferences = array_sum(array_map('count', DashboardModule::definitions()));
if ($before['dashboard_widget_preferences'] !== $expectedDashboardPreferences) {
    throw new RuntimeException('Dashboard preferences seed is incomplete.');
}
require_once $root . '/lib/ImportSourceRegistry.php';
$expectedImportMappings = array_sum(array_map(
    static fn(array $source): int => count($source['mappings'] ?? []),
    ImportSourceRegistry::all()
));
if ($before['sales_import_column_mappings'] !== $expectedImportMappings) {
    throw new RuntimeException('Import template mappings seed is incomplete.');
}
if ($before['sales_lines'] < 4 || $before['sales_geographies'] < 1) {
    throw new RuntimeException('Sales structure seed is incomplete.');
}
foreach (['sales_aggregate_rows','purchase_aggregate_rows','inventory_aggregate_rows','hr_attendance_entries'] as $operationalTable) {
    if ($count($operationalTable) !== 0) {
        throw new RuntimeException('Fresh installer fabricated operational rows in ' . $operationalTable);
    }
}

$second = Installer::seedFreshDatabase($pdo, 1);
foreach ($second as $key => $result) {
    if (($result['status'] ?? '') !== 'completed' || (int)($result['errors'] ?? 0) !== 0) {
        throw new RuntimeException('Seed rerun failed: ' . $key);
    }
}
foreach ($before as $table => $expected) {
    $actual = $count($table);
    if ($actual !== $expected) {
        throw new RuntimeException("Seed rerun changed row count for {$table}: {$expected} -> {$actual}");
    }
}

$runs = (int)$pdo->query('SELECT COUNT(*) FROM seed_runs WHERE status="completed"')->fetchColumn();
if ($runs !== count($keys) * 2) throw new RuntimeException('Seed execution history is incomplete.');

echo "Fresh install seed registry integration: PASS\n";
