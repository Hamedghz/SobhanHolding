<?php
require __DIR__.'/_bootstrap.php';
try{backup_require_method('GET');$id=(int)($_GET['queue_id']??$_GET['file_id']??0);if($id<=0)throw new RuntimeException('file_not_registered');backup_json(200,true,FileBackupService::metadata($id));}catch(Throwable $e){backup_fail($e);}
