<?php
require __DIR__.'/_bootstrap.php';
sync_run(function():array{json_require_method('GET');$result=SyncQueueService::getPending(['batch_size'=>(int)($_GET['batch_size']??0)?:null,'entity_type'=>trim((string)($_GET['entity_type']??'')),'since_id'=>(int)($_GET['since_id']??0)]);return ['data'=>['items'=>$result['items']],'meta'=>['limit'=>$result['limit'],'count'=>count($result['items'])]];});
