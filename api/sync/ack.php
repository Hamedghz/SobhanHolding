<?php
require __DIR__.'/_bootstrap.php';
sync_run(function():array{json_require_method('POST');$input=sync_input();$id=filter_var($input['queue_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);if($id===false)throw new RuntimeException('queue_not_found');$status=SyncQueueService::markSynced((int)$id);return ['data'=>['queue_id'=>(int)$id,'status'=>'synced','idempotent'=>$status==='already_synced'],'message'=>$status==='already_synced'?'این آیتم قبلاً همگام شده است.':'همگام‌سازی تأیید شد.'];});
