<?php

$root=dirname(__DIR__);
$dsn=getenv('SOBHAN_TEST_DSN')?:'';
$user=getenv('SOBHAN_TEST_DB_USER')?:'root';
$password=getenv('SOBHAN_TEST_DB_PASSWORD')?:'';
if($dsn===''){fwrite(STDERR,"SOBHAN_TEST_DSN is required.\n");exit(2);}
$pdo=new PDO($dsn,$user,$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
$sql=(string)file_get_contents($root.'/database/schema.sql');
$statements=preg_split('/;\s*(?:\r?\n|$)/',$sql,-1,PREG_SPLIT_NO_EMPTY)?:[];
foreach($statements as $statement){$statement=preg_replace('/^(?:\s*--[^\r\n]*(?:\r?\n|$))+/', '', trim($statement))??$statement;if(trim($statement)!=='')$pdo->exec(trim($statement));}
require_once $root.'/core/Database.php';
$reflection=new ReflectionClass(Database::class);$property=$reflection->getProperty('pdo');$property->setValue(null,$pdo);
$reflection->getProperty('migrated')->setValue(null,true);
require_once $root.'/core/WorkPlannerModule.php';
WorkPlannerModule::repair($pdo);WorkPlannerModule::repair($pdo);
$column=static function(string $name)use($pdo):bool{$stmt=$pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME="work_planner_tasks" AND COLUMN_NAME=?');$stmt->execute([$name]);return (int)$stmt->fetchColumn()===1;};
foreach(['started_at','paused_at','client_request_key'] as $name)if(!$column($name))throw new RuntimeException('Missing planner column: '.$name);
$index=(int)$pdo->query("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='work_planner_tasks' AND INDEX_NAME='uq_work_planner_client_request'")->fetchColumn();
if($index<1)throw new RuntimeException('Planner request idempotency index is missing.');
$pdo->exec("INSERT INTO users(id,name,email,username,password_hash,role,status,access_scope,admin_panel_enabled,created_at,updated_at)
VALUES(1,'مدیر تست پلنر','planner-admin@example.test','planner-admin','test','super_admin','active','all',1,NOW(),NOW())
ON DUPLICATE KEY UPDATE name=VALUES(name),role='super_admin',status='active'");
require_once $root.'/services/WorkPlannerService.php';
$initialTaskCount=(int)$pdo->query('SELECT COUNT(*) FROM work_planner_tasks')->fetchColumn();
if($initialTaskCount!==0)throw new RuntimeException('Fresh planner repair must not fabricate operational tasks.');
if(WorkPlannerService::matchingTemplatesForUser(1)!==[])throw new RuntimeException('A user without matching organizational data must not receive role templates.');
$pdo->exec("INSERT INTO work_planner_templates(title,role_key,task_type,priority,default_status,default_due_offset_days,recurrence_type,is_required,is_visible_on_dashboard,is_active,sort_order,created_at,updated_at) VALUES('قالب نقش تست','TEST_ROLE','custom','normal','todo',0,'daily',1,1,1,1,NOW(),NOW())");
$pdo->exec("UPDATE users SET role_key='TEST_ROLE' WHERE id=1");
if(count(WorkPlannerService::matchingTemplatesForUser(1))!==1)throw new RuntimeException('Matching role template was not detected.');
if(WorkPlannerService::generateDailyTasksForUser(1,'2026-07-18')!==1)throw new RuntimeException('Explicit role task generation failed.');
if(WorkPlannerService::generateDailyTasksForUser(1,'2026-07-18')!==0)throw new RuntimeException('Role task generation is not idempotent.');
if((int)$pdo->query("SELECT COUNT(*) FROM work_planner_tasks WHERE template_id IS NOT NULL AND start_date='2026-07-18'")->fetchColumn()!==1)throw new RuntimeException('Unexpected generated role task count.');
$request=['title'=>'وظیفه Quick Add','due_date'=>'1405/04/25','client_request_key'=>'planner-quick-add-test'];
$taskId=WorkPlannerService::createPersonalTask(1,$request,'2026-07-16');
$duplicateId=WorkPlannerService::createPersonalTask(1,$request,'2026-07-16');
if($taskId<1||$duplicateId!==$taskId)throw new RuntimeException('Planner Quick Add is not idempotent.');
$task=$pdo->query('SELECT recurrence_type,recurrence_interval FROM work_planner_tasks WHERE id='.(int)$taskId)->fetch();
if(($task['recurrence_type']??null)!=='none'||(int)($task['recurrence_interval']??0)!==1)throw new RuntimeException('Planner Quick Add default recurrence was not persisted.');
if(!WorkPlannerService::updatePersonalTask($taskId,['title'=>'وظیفه ویرایش‌شده','due_date'=>'1405/04/25'],1))throw new RuntimeException('Planner task update failed.');
$recurrence=(string)$pdo->query('SELECT recurrence_type FROM work_planner_tasks WHERE id='.(int)$taskId)->fetchColumn();
if($recurrence!=='none')throw new RuntimeException('Planner task update default recurrence was not preserved.');
echo "Work planner phase 14 schema integration: PASS\n";
