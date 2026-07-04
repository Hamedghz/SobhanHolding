<?php
require __DIR__.'/_bootstrap.php';
sync_run(function():array{json_require_method('GET');$type=trim((string)($_GET['entity_type']??''));$id=filter_var($_GET['entity_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);if($id===false)throw new InvalidArgumentException('invalid_entity_id');$record=SyncQueueService::record($type,(int)$id);$queueId=(int)($_GET['queue_id']??0);if($queueId>0)$record['queue_id']=$queueId;return ['data'=>$record];});
