<?php

// Run only against a disposable database whose schema.sql has already been loaded.
$dsn = getenv('SOBHAN_TEST_DSN');
if (!$dsn) { fwrite(STDERR, "SOBHAN_TEST_DSN is required\n"); exit(2); }
$pdo = new PDO($dsn, getenv('SOBHAN_TEST_DB_USER') ?: 'root', getenv('SOBHAN_TEST_DB_PASS') ?: '', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
require_once __DIR__.'/../../core/Database.php';
$pdoProperty = new ReflectionProperty(Database::class, 'pdo'); $pdoProperty->setValue(null,$pdo);
$migratedProperty = new ReflectionProperty(Database::class, 'migrated'); $migratedProperty->setValue(null,true);
require_once __DIR__.'/../../services/MessengerForwardService.php';
require_once __DIR__.'/../../core/MessengerModule.php';
require_once __DIR__.'/../../lib/NotificationService.php';
ManagerDashboard::repair($pdo);
MessengerModule::repair($pdo);
NotificationService::repair($pdo);

$pdo->exec('SET FOREIGN_KEY_CHECKS=0');
foreach (['messenger_forward_logs','messenger_forwarded_reports','messenger_message_recipients','messenger_messages','sales_report_shares','messenger_group_members','messenger_groups','sobhan_notifications','sobhan_user_notification_settings','manager_commission_summary','manager_line_performance','manager_dashboard_reports','user_permissions','users'] as $table) $pdo->exec("DELETE FROM `{$table}`");
$pdo->exec('SET FOREIGN_KEY_CHECKS=1');
$password=password_hash('test',PASSWORD_DEFAULT);
$user=$pdo->prepare('INSERT INTO users(id,name,email,username,password_hash,role,status,sales_line,role_key,access_scope,admin_panel_enabled) VALUES(?,?,?,?,?,?,"active",?,?,"all",1)');
$user->execute([1,'مدیر تست','manager@example.test','manager',$password,'admin','A','SALES_MANAGER']);
$user->execute([2,'گیرنده یک','one@example.test','one',$password,'employee','A','VISITOR']);
$user->execute([3,'گیرنده دو','two@example.test','two',$password,'employee','A','VISITOR']);
$user->execute([4,'کاربر غیرگیرنده','other@example.test','other',$password,'employee','B','VISITOR']);
$pdo->exec("INSERT INTO manager_dashboard_reports(id,report_title,report_date,report_month,report_year,import_status) VALUES(1,'گزارش تست','2026-06-01',6,1405,'success')");
$pdo->exec("INSERT INTO manager_commission_summary(report_id,report_date,visitor_name,line_code,sales_amount,achievement_percent,final_commission) VALUES(1,'2026-06-01','گیرنده یک','A',1200000,91,80000),(1,'2026-06-01','گیرنده دو','A',1400000,104,110000)");
$pdo->exec("INSERT INTO manager_line_performance(report_id,report_date,line_code,line_sales_amount,sold_qty,target_qty,achievement_percent) VALUES(1,'2026-06-01','A',2600000,195,200,97.5)");
$pdo->exec("INSERT INTO messenger_groups(id,title,created_by,active) VALUES(1,'گروه تست',1,1)");
$pdo->exec('INSERT INTO messenger_group_members(group_id,user_id) VALUES(1,2),(1,3)');

Auth::start(); $_SESSION['user_id']=1;
$sender=Auth::user();
$single=MessengerForwardService::send(['report_id'=>1,'report_type'=>'summary_cards','title'=>'خلاصه تست','recipient_type'=>'single_user','recipient_id'=>2],$sender);
$multi=MessengerForwardService::send(['report_id'=>1,'report_type'=>'visitor_performance','title'=>'جدول تست','recipient_type'=>'multiple_users','recipient_ids'=>[2,3]],$sender);
$group=MessengerForwardService::send(['report_id'=>1,'report_type'=>'line_performance','title'=>'گروه تست','recipient_type'=>'group','group_id'=>1,'include_attachment'=>1],$sender);
$share=MessengerForwardService::shareForUser((int)$single['share_id'],['id'=>2,'role'=>'employee']);
$denied=MessengerForwardService::shareForUser((int)$single['share_id'],['id'=>4,'role'=>'employee']);
$attachment=MessengerForwardService::attachment((int)$group['share_id'],['id'=>2,'role'=>'employee']);
$chart=SalesReportShareService::build(1,'chart:line_performance',[], $sender, 'نمودار تست');
$unauthorizedForwardHidden=!SalesReportShareService::canForward(['id'=>4,'role'=>'employee','role_key'=>'VISITOR']);
$counts=$pdo->query('SELECT (SELECT COUNT(*) FROM sales_report_shares) shares,(SELECT COUNT(*) FROM messenger_messages) messages,(SELECT COUNT(*) FROM messenger_message_recipients) recipients,(SELECT COUNT(*) FROM sobhan_notifications WHERE event_type="forwarded_report") notifications')->fetch();
$attachmentExists=is_file($attachment['path']);$chartReady=!empty($chart['snapshot']['chart']['data']);
$ok=$single['recipient_count']===1&&$multi['recipient_count']===2&&$group['recipient_count']===2&&$share&&$denied===null&&$attachmentExists&&$chartReady&&$unauthorizedForwardHidden&&(int)$counts['shares']===3&&(int)$counts['messages']===3&&(int)$counts['recipients']===5&&(int)$counts['notifications']===5;
echo json_encode(['ok'=>$ok,'single'=>$single,'multi'=>$multi,'group'=>$group,'access_granted'=>(bool)$share,'non_recipient_denied'=>$denied===null,'unauthorized_forward_hidden'=>$unauthorizedForwardHidden,'attachment_exists'=>$attachmentExists,'chart_ready'=>$chartReady,'counts'=>$counts],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL;
if($attachmentExists) @unlink($attachment['path']);
exit($ok?0:1);
