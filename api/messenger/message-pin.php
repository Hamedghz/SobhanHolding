<?php require __DIR__.'/bootstrap.php';messenger_run(function($u){$in=messenger_input();MessengerService::pin((int)($in['message_id']??0),true,$u);return ['pinned'=>true];},true);
