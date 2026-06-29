<?php
require __DIR__.'/_bootstrap.php';
try{backup_require_method('POST');$data=backup_request_data();$id=(int)($data['file_id']??0);if($id<=0)throw new RuntimeException('file_not_registered');FileBackupService::acknowledge($id,isset($data['file_hash'])?(string)$data['file_hash']:null);backup_json(200,true,['file_id'=>$id,'backup_status'=>'synced','backup_confirmed_at'=>date('c')]);}catch(Throwable $e){backup_fail($e);}
