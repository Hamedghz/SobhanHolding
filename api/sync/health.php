<?php
require __DIR__.'/_bootstrap.php';
sync_run(function():array{json_require_method('GET');return ['data'=>['service'=>'sobhan-pull-sync','enabled'=>true,'server_time'=>gmdate('c'),'supported_entities'=>SyncQueueService::allowedEntities(),'batch_limit'=>(int)SyncQueueService::setting('sync_batch_max','100')]];});
