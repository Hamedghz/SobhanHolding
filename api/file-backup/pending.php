<?php
require __DIR__.'/_bootstrap.php';
try{backup_require_method('GET');$limit=(int)($_GET['limit']??100);$files=FileBackupService::pending($limit);backup_json(200,true,$files,null,['limit'=>max(1,min(500,$limit))]);}catch(Throwable $e){backup_fail($e);}
