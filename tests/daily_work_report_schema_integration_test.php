<?php

$root = dirname(__DIR__);
$dsn = getenv('SOBHAN_TEST_DSN') ?: '';
$user = getenv('SOBHAN_TEST_DB_USER') ?: 'root';
$password = getenv('SOBHAN_TEST_DB_PASSWORD') ?: '';
if ($dsn === '') {
    fwrite(STDERR,"SOBHAN_TEST_DSN is required.\n");
    exit(2);
}
$pdo = new PDO($dsn,$user,$password,[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES=>false,
]);
$sql = (string)file_get_contents($root.'/database/schema.sql');
$statements = preg_split('/;\s*(?:\r?\n|$)/',$sql,-1,PREG_SPLIT_NO_EMPTY) ?: [];
foreach($statements as $statement){
    $statement=preg_replace('/^(?:\s*--[^\r\n]*(?:\r?\n|$))+/', '', trim($statement)) ?? $statement;
    if(trim($statement)!=='')$pdo->exec(trim($statement));
}
require_once $root.'/core/DailyWorkReportModule.php';
DailyWorkReportModule::repair($pdo);
DailyWorkReportModule::repair($pdo);
$table=static function(string $name)use($pdo):bool{
    $stmt=$pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
    $stmt->execute([$name]);
    return (int)$stmt->fetchColumn()===1;
};
$index=static function(string $table,string $name)use($pdo):bool{
    $stmt=$pdo->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=?');
    $stmt->execute([$table,$name]);
    return (int)$stmt->fetchColumn()>0;
};
foreach(['daily_report_templates','daily_report_template_fields','daily_report_template_assignments','daily_reports','daily_report_values','daily_report_links','daily_report_logs'] as $name){
    if(!$table($name))throw new RuntimeException('Missing daily report table: '.$name);
}
foreach([
    ['daily_reports','uq_daily_report_user_date_template'],
    ['daily_reports','uq_daily_report_legacy'],
    ['daily_report_template_assignments','idx_daily_report_assignment_scope'],
    ['daily_report_links','uq_daily_report_link'],
] as [$tableName,$indexName]){
    if(!$index($tableName,$indexName))throw new RuntimeException("Missing index: {$tableName}.{$indexName}");
}
if((int)$pdo->query("SELECT COUNT(*) FROM daily_report_templates WHERE template_code='daily_general'")->fetchColumn()!==1)throw new RuntimeException('Default daily report template is not idempotent.');
if((int)$pdo->query("SELECT COUNT(*) FROM daily_report_template_assignments a JOIN daily_report_templates t ON t.id=a.template_id WHERE t.template_code='daily_general' AND a.scope_type='company'")->fetchColumn()!==1)throw new RuntimeException('Default company assignment is not idempotent.');
if((int)$pdo->query("SELECT COUNT(*) FROM modules WHERE module_key LIKE 'daily_reports.%'")->fetchColumn()!==count(DailyWorkReportModule::PERMISSIONS))throw new RuntimeException('Daily report permissions are not idempotent.');
echo "Daily work report schema integration: PASS\n";
