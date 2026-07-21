<?php

$root=dirname(__DIR__);
$dsn=getenv('SOBHAN_TEST_DSN')?:'';
$user=getenv('SOBHAN_TEST_DB_USER')?:'root';
$password=getenv('SOBHAN_TEST_DB_PASSWORD')?:'';
if($dsn===''){
    fwrite(STDERR,"SOBHAN_TEST_DSN is required.\n");
    exit(2);
}
$pdo=new PDO($dsn,$user,$password,[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES=>false,
]);
$sql=(string)file_get_contents($root.'/database/schema.sql');
$statements=preg_split('/;\s*(?:\r?\n|$)/',$sql,-1,PREG_SPLIT_NO_EMPTY)?:[];
foreach($statements as $statement){
    $statement=preg_replace('/^(?:\s*--[^\r\n]*(?:\r?\n|$))+/', '', trim($statement))??$statement;
    if(trim($statement)!=='')$pdo->exec(trim($statement));
}
require_once $root.'/core/ManagementReportsModule.php';
ManagementReportsModule::repair($pdo);
ManagementReportsModule::repair($pdo);

$column=static function(string $table,string $name)use($pdo):bool{
    $stmt=$pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
    $stmt->execute([$table,$name]);
    return (int)$stmt->fetchColumn()===1;
};
$index=static function(string $table,string $name)use($pdo):bool{
    $stmt=$pdo->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=?');
    $stmt->execute([$table,$name]);
    return (int)$stmt->fetchColumn()>0;
};
foreach([
    ['management_report_templates','version_no'],
    ['management_report_submissions','template_version_no'],
    ['management_report_submissions','schema_snapshot_json'],
    ['management_report_links','active'],
] as [$table,$name]){
    if(!$column($table,$name))throw new RuntimeException("Missing column: {$table}.{$name}");
}
if(!$index('management_report_links','uq_management_report_field_link'))throw new RuntimeException('Missing management report link uniqueness.');
$type=$pdo->query("SELECT DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='management_report_fields' AND COLUMN_NAME='field_type'")->fetchColumn();
if(strtolower((string)$type)!=='varchar')throw new RuntimeException('Management report field type must be extensible varchar.');
$duplicates=(int)$pdo->query('SELECT COUNT(*) FROM (SELECT report_type,COUNT(*) c FROM management_report_templates GROUP BY report_type HAVING c>1) x')->fetchColumn();
if($duplicates!==0)throw new RuntimeException('Template repair is not idempotent.');

echo "Management reports schema integration: PASS\n";
