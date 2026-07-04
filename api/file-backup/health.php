<?php
require __DIR__.'/_bootstrap.php';
try{backup_require_method('GET');$counts=['pending'=>0,'synced'=>0,'error'=>0];foreach(Database::fetchAll('SELECT backup_status,COUNT(*) count FROM uploaded_files_backup GROUP BY backup_status') as $row)$counts[$row['backup_status']]=(int)$row['count'];backup_json(200,true,['service'=>'sobhan-file-backup','enabled'=>true,'counts'=>$counts,'max_batch_size'=>(int)FileBackupService::setting('file_backup_batch_max','100'),'server_time'=>gmdate('c')]);}catch(Throwable $e){backup_fail($e);}
