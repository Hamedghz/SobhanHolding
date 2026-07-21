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

require_once $root . '/core/SalesOperationsModule.php';
require_once $root . '/core/ActionHubModule.php';
SalesOperationsModule::repair($pdo);

$pdo->exec(
    "INSERT INTO sales_scripts(id,title,script_code,script_body,target_scope,active,created_at,updated_at)
     VALUES (501,'پیگیری مشتری غیرفعال','FOLLOWUP_501','مراحل پیگیری و نتیجه تماس','sales_line',1,NOW(),NOW())
     ON DUPLICATE KEY UPDATE title=VALUES(title),script_body=VALUES(script_body),active=1,updated_at=NOW()"
);
$field = $pdo->prepare(
    'INSERT INTO sales_script_fields
     (script_id,field_key,field_label,field_type,options_json,default_value,required,sort_order,active,created_at,updated_at)
     VALUES (501,?,?,?,?,?,?,?,1,NOW(),NOW())
     ON DUPLICATE KEY UPDATE field_label=VALUES(field_label),field_type=VALUES(field_type),
       options_json=VALUES(options_json),default_value=VALUES(default_value),required=VALUES(required),
       sort_order=VALUES(sort_order),active=1,updated_at=NOW()'
);
$field->execute(['followup_status','وضعیت پیگیری','select','{"open":"باز","done":"انجام‌شده"}','open',1,10]);
$field->execute(['followup_date','تاریخ پیگیری','date',null,null,0,20]);

ActionHubModule::repair($pdo);
ActionHubModule::repair($pdo);

$template = $pdo->query(
    "SELECT * FROM action_templates
     WHERE legacy_source_type='sales_script' AND legacy_source_id=501"
)->fetch();
if (!$template || ($template['title'] ?? '') !== 'پیگیری مشتری غیرفعال') {
    throw new RuntimeException('Legacy sales script was not backfilled as an action template.');
}
if ((int)$pdo->query(
    "SELECT COUNT(*) FROM action_templates
     WHERE legacy_source_type='sales_script' AND legacy_source_id=501"
)->fetchColumn() !== 1) {
    throw new RuntimeException('Legacy action template backfill is not idempotent.');
}
$fields = $pdo->query(
    'SELECT field_key,field_label,field_type,options_json,required
     FROM action_template_fields WHERE template_id=' . (int)$template['id'] . ' ORDER BY sort_order,id'
)->fetchAll();
if (count($fields) !== 2) throw new RuntimeException('Legacy action template fields were not backfilled.');
if (($fields[0]['field_type'] ?? '') !== 'single_select' || (int)($fields[0]['required'] ?? 0) !== 1) {
    throw new RuntimeException('Legacy select field was not converted to the controlled Action Hub type.');
}
if (json_decode((string)$fields[0]['options_json'], true) !== ['open'=>'باز','done'=>'انجام‌شده']) {
    throw new RuntimeException('Legacy select options were not preserved internally.');
}
if (($fields[1]['field_type'] ?? '') !== 'jalali_date') {
    throw new RuntimeException('Legacy date field was not converted to the shared Jalali type.');
}

$user = $pdo->prepare(
    'INSERT INTO users(id,name,email,username,password_hash,role,status,role_key,admin_panel_enabled,created_at,updated_at)
     VALUES (?,?,?,?,?,"employee","active",?,1,NOW(),NOW())
     ON DUPLICATE KEY UPDATE name=VALUES(name),status="active",role_key=VALUES(role_key),updated_at=NOW()'
);
$user->execute([901,'مدیر تست','action-manager-901@example.test','action_manager_901','test','SALES_MANAGER']);
$user->execute([902,'سرپرست تست','action-supervisor-902@example.test','action_supervisor_902','test','SALES_SUPERVISOR']);
$pdo->exec(
    "INSERT INTO supervisor_actions
     (id,supervisor_id,sales_manager_id,title,description,priority,status,due_date,manager_note,created_by,updated_by,created_at,updated_at)
     VALUES (701,902,901,'اقدام قدیمی اولیه','شرح اولیه','normal','open','2026-08-01',NULL,902,902,NOW(),NOW())
     ON DUPLICATE KEY UPDATE supervisor_id=902,sales_manager_id=901,title=VALUES(title),description=VALUES(description),
       priority=VALUES(priority),status=VALUES(status),due_date=VALUES(due_date),manager_note=NULL,
       created_by=902,updated_by=902,completed_at=NULL,updated_at=NOW()"
);
ActionHubModule::repair($pdo);

require_once $root . '/core/Database.php';
require_once $root . '/services/ActionHubService.php';
$databasePdo = new ReflectionProperty(Database::class, 'pdo');
$databasePdo->setValue(null, $pdo);

$pdo->exec(
    "UPDATE supervisor_actions SET title='اقدام بازبینی‌شده',description='شرح بازبینی‌شده',
     priority='urgent',status='done',manager_note='تأیید مدیر',completed_at=NOW(),updated_at=NOW() WHERE id=701"
);
$universalId = ActionHubService::mirrorLegacyAction('supervisor_actions', 701);
if (!$universalId) throw new RuntimeException('Legacy supervisor action did not map to the universal action.');
$universal = $pdo->query(
    "SELECT title,description,priority,status,due_date,completed_at
     FROM actions WHERE legacy_source_type='supervisor_actions' AND legacy_source_id=701"
)->fetch();
if (
    !$universal
    || ($universal['title'] ?? '') !== 'اقدام بازبینی‌شده'
    || ($universal['description'] ?? '') !== 'شرح بازبینی‌شده'
    || ($universal['priority'] ?? '') !== 'urgent'
    || ($universal['status'] ?? '') !== 'done'
    || ($universal['due_date'] ?? '') !== '2026-08-01'
    || empty($universal['completed_at'])
) {
    throw new RuntimeException('Legacy supervisor action changes were not synchronized to Action Hub.');
}

echo "Legacy action template and supervisor sync integration: PASS\n";
