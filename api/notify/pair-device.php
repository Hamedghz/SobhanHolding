<?php require __DIR__.'/_bootstrap.php';notify_method('POST');try{notify_json(true,NotificationHubService::pair(notify_input()));}catch(Throwable $e){notify_fail($e);}
