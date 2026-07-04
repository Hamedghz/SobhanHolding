<?php
require __DIR__.'/_bootstrap.php';
try{backup_require_method('GET');$limit=(int)($_GET['batch_size']??$_GET['limit']??100);$files=FileBackupService::pending($limit);backup_json(200,true,['items'=>$files],null,['count'=>count($files)]);}catch(Throwable $e){backup_fail($e);}
