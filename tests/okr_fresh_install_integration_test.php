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
        (1,'مدیر نصب آزمایشی OKR','okr-fresh-admin@example.test','okr-fresh-admin','test','super_admin','active','all',1,NOW(),NOW())
     ON DUPLICATE KEY UPDATE role='super_admin',status='active',access_scope='all',admin_panel_enabled=1"
);

require_once $root . '/core/Installer.php';
require_once $root . '/core/OrgModule.php';
require_once $root . '/core/WorkPlannerModule.php';
require_once $root . '/core/OkrModule.php';
require_once $root . '/core/ActionHubModule.php';
require_once $root . '/core/DailyWorkReportModule.php';

$seedResults = Installer::seedFreshDatabase($pdo, 1);
foreach ($seedResults as $key => $result) {
    if (($result['status'] ?? '') !== 'completed' || (int)($result['errors'] ?? 0) !== 0) {
        throw new RuntimeException('Fresh seed failed: ' . $key);
    }
}

$repairModules = static function () use ($pdo): void {
    OrgModule::repair($pdo);
    WorkPlannerModule::repair($pdo);
    OkrModule::repair($pdo);
    ActionHubModule::repair($pdo);
    DailyWorkReportModule::repair($pdo);
};
$repairModules();

$count = static fn(string $table): int => (int)$pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
$referenceCounts = [
    'action_types' => $count('action_types'),
    'daily_report_templates' => $count('daily_report_templates'),
    'daily_report_template_fields' => $count('daily_report_template_fields'),
    'daily_report_template_assignments' => $count('daily_report_template_assignments'),
    'work_planner_templates' => $count('work_planner_templates'),
];

if ((int)$pdo->query("SELECT COUNT(*) FROM action_types WHERE code='general' AND active=1")->fetchColumn() !== 1) {
    throw new RuntimeException('Action Hub general reference type is missing or duplicated.');
}
if ((int)$pdo->query("SELECT COUNT(*) FROM daily_report_templates WHERE template_code='daily_general' AND active=1")->fetchColumn() !== 1) {
    throw new RuntimeException('Daily report default template is missing or duplicated.');
}
if ((int)$pdo->query("SELECT COUNT(*) FROM daily_report_template_fields f JOIN daily_report_templates t ON t.id=f.template_id WHERE t.template_code='daily_general' AND f.active=1")->fetchColumn() !== 10) {
    throw new RuntimeException('Daily report default template does not have the expected 10 fields.');
}
if ((int)$pdo->query("SELECT COUNT(*) FROM daily_report_template_assignments a JOIN daily_report_templates t ON t.id=a.template_id WHERE t.template_code='daily_general' AND a.scope_type='company' AND a.active=1")->fetchColumn() !== 1) {
    throw new RuntimeException('Daily report company assignment is missing or duplicated.');
}
if ($referenceCounts['work_planner_templates'] < 1) {
    throw new RuntimeException('Role-based work planner reference templates were not seeded.');
}
if ((int)$pdo->query("SELECT COUNT(*) FROM sales_lines WHERE active=1")->fetchColumn() < 4) {
    throw new RuntimeException('Central sales lines reference data is incomplete.');
}
foreach (['okr.view','okr.manage','okr.cycles','action_hub.view','daily_reports.view','work_planner.view'] as $moduleKey) {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM modules WHERE module_key=? AND status="active"');
    $stmt->execute([$moduleKey]);
    if ((int)$stmt->fetchColumn() !== 1) throw new RuntimeException('Required permission module is missing: ' . $moduleKey);
}
foreach (['okr_cycles','okr_objectives','actions','daily_reports','work_planner_tasks'] as $operationalTable) {
    if ($count($operationalTable) !== 0) {
        throw new RuntimeException('Fresh repair fabricated operational rows in ' . $operationalTable);
    }
}

require_once $root . '/lib/OkrService.php';
Auth::start();
$_SESSION['user_id'] = 1;
$owners = OkrService::availableOwners();
if (!in_array(1, array_map('intval', array_column($owners, 'id')), true)) {
    throw new RuntimeException('Fresh admin is not selectable as an OKR owner.');
}

$cycleId = OkrService::saveCycle([
    'title' => 'دوره آزمون نصب تازه OKR',
    'cycle_type' => 'annual',
    'status' => 'active',
    'start_date' => '2026-03-21',
    'end_date' => '2027-03-20',
    'checkin_frequency' => 'weekly',
], 1);

$objectiveInput = [
    'cycle_id' => $cycleId,
    'owner_user_id' => 1,
    'objective_level' => 'company',
    'title' => 'هدف آزمون نصب تازه',
    'description' => 'رکورد عملیاتی فقط برای اثبات مسیر ایجاد در دیتابیس موقت آزمون.',
    'okr_type' => 'committed',
    'priority' => 'high',
    'weight' => 100,
    'start_date' => '2026-03-21',
    'due_date' => '2027-03-20',
];

$invalidRejected = false;
try {
    OkrService::saveObjective($objectiveInput + ['sales_line' => 'FREE-TEXT'], 1);
} catch (InvalidArgumentException $exception) {
    $invalidRejected = str_contains($exception->getMessage(), 'فهرست فعال ساختار فروش');
}
if (!$invalidRejected || $count('okr_objectives') !== 0) {
    throw new RuntimeException('Unknown free-text sales line was not rejected cleanly.');
}

$salesLine = (string)$pdo->query("SELECT code FROM sales_lines WHERE active=1 ORDER BY sort_order,code LIMIT 1")->fetchColumn();
$objectiveId = OkrService::saveObjective($objectiveInput + ['sales_line' => $salesLine], 1);
$stored = $pdo->query('SELECT sales_line FROM okr_objectives WHERE id=' . (int)$objectiveId)->fetchColumn();
if ($stored !== $salesLine) throw new RuntimeException('Valid central sales line was not stored on the objective.');

$repairModules();
foreach ($referenceCounts as $table => $expected) {
    $actual = $count($table);
    if ($actual !== $expected) {
        throw new RuntimeException("Module repair changed reference count for {$table}: {$expected} -> {$actual}");
    }
}
if ($count('okr_cycles') !== 1 || $count('okr_objectives') !== 1) {
    throw new RuntimeException('Idempotent repair changed existing OKR operational records.');
}
foreach (['actions','daily_reports','work_planner_tasks'] as $operationalTable) {
    if ($count($operationalTable) !== 0) {
        throw new RuntimeException('Repair fabricated operational rows after OKR workflow in ' . $operationalTable);
    }
}

echo "OKR fresh-install integration: PASS\n";
