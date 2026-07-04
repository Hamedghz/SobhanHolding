<?php
require __DIR__.'/_bootstrap.php';
sync_run(function():array{json_require_method('POST');$input=sync_input();$id=filter_var($input['queue_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);if($id===false)throw new RuntimeException('queue_not_found');$attempts=SyncQueueService::markError((int)$id,(string)($input['error_message']??''));$max=(int)SyncQueueService::setting('sync_max_attempts','5');return ['data'=>['queue_id'=>(int)$id,'status'=>'error','attempts'=>$attempts,'retryable'=>$attempts<$max],'message'=>'خطای worker ثبت شد.'];});
