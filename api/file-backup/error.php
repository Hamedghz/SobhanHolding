<?php
require __DIR__.'/_bootstrap.php';
try{backup_require_method('POST');$data=backup_request_data();$id=(int)($data['queue_id']??$data['file_id']??0);if($id<=0)throw new RuntimeException('file_not_registered');$status=FileBackupService::markError($id,(string)($data['error_message']??''));backup_json(200,true,['queue_id'=>$id,'status'=>$status,'retryable'=>$status==='error']);}catch(Throwable $e){backup_fail($e);}
