<?php require __DIR__.'/_bootstrap.php';notify_method('GET');try{$device=notify_device();notify_json(true,NotificationHubService::clientConfig($device));}catch(Throwable $e){notify_fail($e);}
