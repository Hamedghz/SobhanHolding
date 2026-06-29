<?php
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../lib/OrgAccess.php';

function work_planner_status_label(string $value): string { return ['todo'=>'انجام‌نشده','in_progress'=>'در حال انجام','waiting'=>'منتظر اقدام','blocked'=>'مسدود','done'=>'تکمیل‌شده','cancelled'=>'لغوشده','overdue'=>'عقب‌افتاده'][$value]??'نامشخص'; }
function work_planner_priority_label(string $value): string { return ['low'=>'کم','normal'=>'عادی','high'=>'بالا','urgent'=>'فوری'][$value]??'عادی'; }
function work_planner_style_label(string $value): string { return ['field_sales'=>'فروش میدانی','supervisor'=>'سرپرستی','sales_manager'=>'مدیریت فروش','warehouse_operation'=>'عملیات انبار','finance_admin'=>'مالی و اداری','it_planning'=>'فناوری و برنامه‌ریزی','executive'=>'مدیریتی','general_employee'=>'کارمند عمومی'][$value]??'کارمند عمومی'; }
function work_planner_action_label(string $value): string { return ['created'=>'ایجاد','updated'=>'ویرایش','status_changed'=>'تغییر وضعیت','progress_changed'=>'تغییر پیشرفت','assigned'=>'تخصیص','completed'=>'تکمیل','cancelled'=>'لغو','comment_added'=>'ثبت پیگیری'][$value]??'بروزرسانی'; }

class WorkPlannerService
{
    public static function createTaskFromTemplate(int $templateId,int $userId,string $date): ?int
    {
        $template=Database::fetch('SELECT * FROM work_planner_templates WHERE id=? AND is_active=1',[$templateId]);
        $user=Database::fetch('SELECT id,org_unit_id,org_role_id FROM users WHERE id=? AND status="active"',[$userId]);
        if(!$template||!$user)return null;
        $exists=Database::fetch('SELECT id FROM work_planner_tasks WHERE template_id=? AND user_id=? AND start_date=? LIMIT 1',[$templateId,$userId,$date]);
        if($exists)return (int)$exists['id'];
        $due=date('Y-m-d',strtotime($date.' +'.max(0,(int)$template['default_due_offset_days']).' days'));
        try{
            Database::execute('INSERT INTO work_planner_tasks(template_id,user_id,employee_id,assigned_to_role_id,assigned_to_unit_id,title,description,task_type,priority,status,start_date,due_date,progress_percent,is_locked,is_personal,is_visible_on_dashboard,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,0,?,0,?,NOW(),NOW())',[$templateId,$userId,$userId,$user['org_role_id'],$user['org_unit_id'],$template['title'],$template['description'],$template['task_type'],$template['priority'],$template['default_status'],$date,$due,(int)$template['is_required'],(int)$template['is_visible_on_dashboard']]);
            $id=(int)Database::lastInsertId();self::logTaskAction($id,$userId,'created',null,['template_id'=>$templateId,'date'=>$date]);return $id;
        }catch(Throwable $e){if(str_contains($e->getMessage(),'Duplicate'))return null;throw $e;}
    }

    public static function generateDailyTasksForUser(int $userId,?string $date=null): int
    {
        $date=$date?:date('Y-m-d');$user=Database::fetch('SELECT id,org_unit_id,org_role_id,role_key,department FROM users WHERE id=? AND status="active"',[$userId]);if(!$user)return 0;
        $templates=Database::fetchAll('SELECT * FROM work_planner_templates WHERE is_active=1 AND (organizational_role_id=? OR (organizational_role_id IS NULL AND role_key=?) OR unit_id=? OR (unit_id IS NULL AND department_key=?)) ORDER BY sort_order,id',[(int)($user['org_role_id']??0),(string)($user['role_key']??''),(int)($user['org_unit_id']??0),(string)($user['department']??'')]);$created=0;
        foreach($templates as $template){$recurrence=$template['recurrence_type'];$eligible=$recurrence==='daily'||($recurrence==='weekly'&&date('N',strtotime($date))==='1')||($recurrence==='monthly'&&date('j',strtotime($date))==='1')||($recurrence==='custom'&&self::customRecurrenceMatches((string)$template['recurrence_rule'],$date));if(!$eligible)continue;$before=Database::fetch('SELECT id FROM work_planner_tasks WHERE template_id=? AND user_id=? AND start_date=?',[(int)$template['id'],$userId,$date]);self::createTaskFromTemplate((int)$template['id'],$userId,$date);if(!$before)$created++;}
        return $created;
    }

    public static function generateTasksByRole(int $roleId,string $date): int
    { $count=0;foreach(Database::fetchAll('SELECT id FROM users WHERE status="active" AND org_role_id=?',[$roleId]) as $user)$count+=self::generateDailyTasksForUser((int)$user['id'],$date);return $count; }

    public static function getDashboardTasks(int $userId): array
    {
        self::generateDailyTasksForUser($userId);
        $prefs=self::getUserPlannerPreferences($userId);if(!(int)$prefs['dashboard_widget_enabled'])return ['enabled'=>false,'counts'=>[],'tasks'=>[],'completion'=>0];
        $rows=Database::fetchAll('SELECT * FROM work_planner_tasks WHERE user_id=? AND is_visible_on_dashboard=1 AND status NOT IN("done","cancelled") ORDER BY CASE WHEN due_date<CURDATE() THEN 0 WHEN priority="urgent" THEN 1 WHEN status="in_progress" THEN 2 WHEN due_date=CURDATE() THEN 3 ELSE 4 END,due_date,id LIMIT 5',[$userId]);
        $counts=Database::fetch('SELECT SUM(due_date=CURDATE() AND status NOT IN("done","cancelled")) today_count,SUM(status="in_progress") in_progress_count,SUM(due_date<CURDATE() AND status NOT IN("done","cancelled")) overdue_count,SUM(priority="urgent" AND status NOT IN("done","cancelled")) urgent_count,SUM(due_date=CURDATE()) today_total,SUM(due_date=CURDATE() AND status="done") today_done FROM work_planner_tasks WHERE user_id=?',[$userId])?:[];
        $total=(int)($counts['today_total']??0);$completion=$total?round((int)($counts['today_done']??0)/$total*100):0;return ['enabled'=>true,'counts'=>$counts,'tasks'=>$rows,'completion'=>$completion];
    }

    public static function getUserPlannerPreferences(int $userId): array
    {
        $defaults=['default_view'=>'list','dashboard_widget_enabled'=>1,'show_in_progress_first'=>1,'show_overdue_tasks'=>1,'show_today_tasks'=>1,'show_completed_tasks'=>0,'preferred_grouping'=>'status','preferred_sorting'=>'priority','work_style'=>self::inferWorkStyle($userId),'compact_mode'=>0];
        return array_replace($defaults,Database::fetch('SELECT * FROM work_planner_user_preferences WHERE user_id=?',[$userId])?:[]);
    }

    public static function updateTaskStatus(int $taskId,string $status,int $userId): bool
    {
        if(!in_array($status,['todo','in_progress','waiting','blocked','done','cancelled','overdue'],true)||!self::canUserAccessTask($userId,$taskId))return false;$task=Database::fetch('SELECT status,progress_percent,user_id,is_locked FROM work_planner_tasks WHERE id=?',[$taskId]);if(!$task)return false;$actor=Database::fetch('SELECT role FROM users WHERE id=?',[$userId]);if($status==='cancelled'&&(int)$task['is_locked']&&($actor['role']??'')!=='super_admin')return false;
        Database::execute('UPDATE work_planner_tasks SET status=?,progress_percent=IF(?="done",100,progress_percent),completed_at=IF(?="done",NOW(),NULL),updated_at=NOW() WHERE id=?',[$status,$status,$status,$taskId]);self::logTaskAction($taskId,$userId,$status==='done'?'completed':'status_changed',['status'=>$task['status']],['status'=>$status]);return true;
    }

    public static function updateTaskProgress(int $taskId,int $progress,int $userId): bool
    {
        if(!self::canUserAccessTask($userId,$taskId))return false;$progress=max(0,min(100,$progress));$task=Database::fetch('SELECT progress_percent,status FROM work_planner_tasks WHERE id=?',[$taskId]);if(!$task)return false;$status=$progress===100?'done':($progress>0&&$task['status']==='todo'?'in_progress':$task['status']);Database::execute('UPDATE work_planner_tasks SET progress_percent=?,status=?,completed_at=IF(?="done",NOW(),NULL),updated_at=NOW() WHERE id=?',[$progress,$status,$status,$taskId]);self::logTaskAction($taskId,$userId,'progress_changed',['progress'=>$task['progress_percent']],['progress'=>$progress]);return true;
    }

    public static function canUserAccessTask(int $userId,int $taskId): bool
    {
        $task=Database::fetch('SELECT user_id FROM work_planner_tasks WHERE id=?',[$taskId]);if(!$task)return false;if((int)$task['user_id']===$userId)return true;$actor=Database::fetch('SELECT * FROM users WHERE id=? AND status="active"',[$userId]);if(!$actor)return false;if(in_array($actor['role'],['super_admin','admin'],true))return true;return in_array((int)$task['user_id'],OrgAccess::accessibleUserIds($actor),true);
    }

    public static function logTaskAction(int $taskId,int $userId,string $action,$oldValue,$newValue,string $note=''): void
    { Database::execute('INSERT INTO work_planner_task_logs(task_id,user_id,action,old_value_json,new_value_json,note,created_at) VALUES (?,?,?,?,?,?,NOW())',[$taskId,$userId,$action,json_encode($oldValue,JSON_UNESCAPED_UNICODE),json_encode($newValue,JSON_UNESCAPED_UNICODE),$note]); }

    public static function inferWorkStyle(int $userId): string
    { $row=Database::fetch('SELECT r.code,u.department FROM users u LEFT JOIN org_roles r ON r.id=u.org_role_id WHERE u.id=?',[$userId])?:[];$code=$row['code']??'';return match($code){'VISITOR'=>'field_sales','SALES_SUPERVISOR'=>'supervisor','SALES_MANAGER'=>'sales_manager','CEO'=>'executive','IT_STAFF','PLANNING_STAFF'=>'it_planning','FINANCE_MANAGER','TREASURY','TAX_INSURANCE'=>'finance_admin','WAREHOUSE_MANAGER','WAREHOUSE_SUPERVISOR','WAREHOUSE_STAFF'=>'warehouse_operation',default=>'general_employee'}; }

    private static function customRecurrenceMatches(string $rule,string $date): bool
    { $days=array_filter(array_map('intval',explode(',',$rule)));return !$days||in_array((int)date('N',strtotime($date)),$days,true); }
}
