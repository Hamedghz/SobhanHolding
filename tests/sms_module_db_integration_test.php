<?php
require_once dirname(__DIR__).'/core/SmsModule.php';
$dsn=getenv('SMS_TEST_DSN');if(!$dsn){fwrite(STDERR,"SMS_TEST_DSN is required\n");exit(2);}$pdo=new PDO($dsn,getenv('SMS_TEST_USER')?:'root',getenv('SMS_TEST_PASS')?:'test',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
SmsModule::repair($pdo);SmsModule::repair($pdo);SmsModule::seedTemplates($pdo);SmsModule::seedTemplates($pdo);
$tables=['sms_settings','sms_templates','sms_messages','sms_message_recipients','sms_gateway_logs'];foreach($tables as $table){$stmt=$pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');$stmt->execute([$table]);if((int)$stmt->fetchColumn()!==1)throw new RuntimeException("Missing {$table}");}
$count=(int)$pdo->query("SELECT COUNT(*) FROM sms_templates WHERE code IN ('request_created','request_updated','ticket_assigned','task_reminder','hr_assessment_assigned','system_alert')")->fetchColumn();if($count!==6)throw new RuntimeException("Expected 6 idempotent templates, got {$count}");
echo "SMS_MODULE_DB_INTEGRATION_OK templates={$count}\n";
