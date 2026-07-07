<?php
$root=dirname(__DIR__);$failures=[];$read=static fn(string $path)=>(string)file_get_contents($root.'/'.$path);$expect=static function(bool $ok,string $message)use(&$failures){if(!$ok)$failures[]=$message;};
$service=$read('core/SmsGatewayService.php');$module=$read('core/SmsModule.php');$settings=$read('admin/sms-settings.php');$send=$read('admin/sms-send.php');$database=$read('core/Database.php');$schema=$read('database/schema.sql');
$expect(str_contains($service,'SendSimpleSMS'),'SendSimpleSMS is missing.');$expect(str_contains($service,'GetCredit'),'GetCredit is missing.');$expect(str_contains($service,'GetStatus'),'GetStatus is missing.');$expect(str_contains($service,'array_chunk')===false,'Batch orchestration must stay in the send workflow.');
$expect(str_contains($service,'aes-256-gcm')&&str_contains($service,'SOBHAN_SMS_ENCRYPTION_KEY'),'Secure SMS credential storage is missing.');$expect(str_contains($send,'array_chunk($mobiles,90)'),'90-recipient batching is missing.');
$expect(str_contains($send,'beginTransaction()')&&str_contains($send,'rollBack()'),'Atomic message and recipient logging is missing.');
$expect(str_contains($settings,"Auth::requirePermission('sms.manage')")&&str_contains($send,"Auth::requirePermission('sms.manage')"),'Direct URL permission guards are missing.');$expect(str_contains($settings,'verifyCsrf')&&str_contains($send,'verifyCsrf'),'CSRF checks are missing.');
foreach(['sms_settings','sms_messages','sms_message_recipients'] as $table){$expect(str_contains($module,"CREATE TABLE IF NOT EXISTS {$table}"),"Runtime schema missing {$table}.");$expect(str_contains($schema,"CREATE TABLE IF NOT EXISTS {$table}"),"Fresh schema missing {$table}.");}
$expect(str_contains($database,'SmsModule::repair($pdo)'),'SmsModule is not registered in Database migration.');
if($failures){fwrite(STDERR,implode(PHP_EOL,$failures).PHP_EOL);exit(1);}echo "SMS_MODULE_CONTRACT_OK\n";
