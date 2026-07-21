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
require_once $root.'/core/HrAttendanceModule.php';
HrAttendanceModule::repair($pdo);
HrAttendanceModule::repair($pdo);

$column=static function(string $table,string $name)use($pdo):bool{
    $stmt=$pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
    $stmt->execute([$table,$name]);
    return (int)$stmt->fetchColumn()===1;
};
$table=static function(string $name)use($pdo):bool{
    $stmt=$pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
    $stmt->execute([$name]);
    return (int)$stmt->fetchColumn()===1;
};
foreach(['leave_type','mission_details','import_time_notes'] as $name){
    if(!$column('hr_attendance_entries',$name))throw new RuntimeException('Missing attendance column: '.$name);
}
if(!$table('hr_attendance_identity_mappings'))throw new RuntimeException('Attendance identity mapping table is missing.');
$type=$pdo->query("SELECT DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hr_attendance_entries' AND COLUMN_NAME='day_status'")->fetchColumn();
if(strtolower((string)$type)!=='varchar')throw new RuntimeException('Attendance day status must be extensible varchar.');
$index=(int)$pdo->query("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hr_attendance_identity_mappings' AND INDEX_NAME='uq_hr_attendance_identity'")->fetchColumn();
if($index<1)throw new RuntimeException('Attendance identity mapping uniqueness is missing.');

echo "HR attendance phase 12 schema integration: PASS\n";
