<?php require __DIR__.'/bootstrap.php';messenger_run(function($u){$in=messenger_input();MessengerLocationService::update((int)($in['location_id']??0),$in,$u);return ['updated'=>true];},true);
