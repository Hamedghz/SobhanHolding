<?php require __DIR__.'/bootstrap.php';messenger_run(function($u){$in=messenger_input();$in['live']=true;return MessengerLocationService::start((int)($in['conversation_id']??0),$in,$u);},true);
