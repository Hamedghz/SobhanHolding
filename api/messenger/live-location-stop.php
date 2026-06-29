<?php require __DIR__.'/bootstrap.php';messenger_run(function($u){$in=messenger_input();MessengerLocationService::stop((int)($in['location_id']??0),$u);return ['stopped'=>true];},true);
