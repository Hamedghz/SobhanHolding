<?php
require_once __DIR__ . '/Database.php';

class WorkPlannerModule
{
    public static function repair(PDO $pdo): void
    {
        foreach (self::schema() as $sql) $pdo->exec($sql);
        foreach (self::missingColumns() as $table=>$columns) {
            foreach ($columns as $column=>$definition) {
                if (Database::tableExists($table) && !Database::columnExists($table,$column)) $pdo->exec("ALTER TABLE `{$table}` ADD `{$column}` {$definition}");
            }
        }
        $index=$pdo->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=?');
        $index->execute(['work_planner_tasks','uq_work_planner_recurrence']);
        if(!(int)$index->fetchColumn())$pdo->exec('ALTER TABLE work_planner_tasks ADD UNIQUE INDEX uq_work_planner_recurrence(recurrence_key)');
        self::seedPermissions($pdo);
        self::seedTemplates($pdo);
    }

    private static function missingColumns(): array
    {
        return [
            'work_planner_templates'=>['description'=>'TEXT NULL','role_key'=>'VARCHAR(100) NULL','department_key'=>'VARCHAR(150) NULL','unit_id'=>'INT UNSIGNED NULL','organizational_role_id'=>'INT UNSIGNED NULL','task_type'=>"VARCHAR(40) NOT NULL DEFAULT 'custom'",'priority'=>"VARCHAR(20) NOT NULL DEFAULT 'normal'",'default_status'=>"VARCHAR(20) NOT NULL DEFAULT 'todo'",'default_due_offset_days'=>'INT NOT NULL DEFAULT 0','recurrence_type'=>"VARCHAR(20) NOT NULL DEFAULT 'none'",'recurrence_rule'=>'VARCHAR(255) NULL','is_required'=>'TINYINT(1) NOT NULL DEFAULT 0','is_visible_on_dashboard'=>'TINYINT(1) NOT NULL DEFAULT 1','is_active'=>'TINYINT(1) NOT NULL DEFAULT 1','sort_order'=>'INT NOT NULL DEFAULT 0','created_by'=>'INT UNSIGNED NULL','created_at'=>'TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP','updated_at'=>'TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'],
            'work_planner_tasks'=>['template_id'=>'INT UNSIGNED NULL','employee_id'=>'INT UNSIGNED NULL','assigned_by'=>'INT UNSIGNED NULL','assigned_to_role_id'=>'INT UNSIGNED NULL','assigned_to_unit_id'=>'INT UNSIGNED NULL','description'=>'TEXT NULL','task_type'=>"VARCHAR(40) NOT NULL DEFAULT 'custom'",'priority'=>"VARCHAR(20) NOT NULL DEFAULT 'normal'",'status'=>"VARCHAR(20) NOT NULL DEFAULT 'todo'",'start_date'=>'DATE NULL','due_date'=>'DATE NULL','completed_at'=>'DATETIME NULL','progress_percent'=>'TINYINT UNSIGNED NOT NULL DEFAULT 0','related_module'=>'VARCHAR(100) NULL','related_record_id'=>'BIGINT UNSIGNED NULL','parent_task_id'=>'BIGINT UNSIGNED NULL','recurrence_key'=>'VARCHAR(100) NULL','is_locked'=>'TINYINT(1) NOT NULL DEFAULT 0','is_personal'=>'TINYINT(1) NOT NULL DEFAULT 0','is_visible_on_dashboard'=>'TINYINT(1) NOT NULL DEFAULT 1','manual_sort_order'=>'INT NOT NULL DEFAULT 0','recurrence_type'=>"VARCHAR(20) NOT NULL DEFAULT 'none'",'recurrence_interval'=>'INT UNSIGNED NOT NULL DEFAULT 1','created_at'=>'TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP','updated_at'=>'TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'],
            'work_planner_user_preferences'=>['default_view'=>"VARCHAR(20) NOT NULL DEFAULT 'list'",'dashboard_widget_enabled'=>'TINYINT(1) NOT NULL DEFAULT 1','show_in_progress_first'=>'TINYINT(1) NOT NULL DEFAULT 1','show_overdue_tasks'=>'TINYINT(1) NOT NULL DEFAULT 1','show_today_tasks'=>'TINYINT(1) NOT NULL DEFAULT 1','show_completed_tasks'=>'TINYINT(1) NOT NULL DEFAULT 0','preferred_grouping'=>"VARCHAR(20) NOT NULL DEFAULT 'status'",'preferred_sorting'=>"VARCHAR(20) NOT NULL DEFAULT 'priority'",'work_style'=>'VARCHAR(40) NULL','compact_mode'=>'TINYINT(1) NOT NULL DEFAULT 0','created_at'=>'TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP','updated_at'=>'TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'],
            'work_planner_task_logs'=>['user_id'=>'INT UNSIGNED NULL','old_value_json'=>'LONGTEXT NULL','new_value_json'=>'LONGTEXT NULL','note'=>'TEXT NULL','created_at'=>'TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP'],
            'work_planner_comments'=>['updated_at'=>'TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'],
        ];
    }

    public static function schema(): array
    {
        $engine=' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        return [
            "CREATE TABLE IF NOT EXISTS work_planner_templates (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,title VARCHAR(190) NOT NULL,description TEXT NULL,role_key VARCHAR(100) NULL,department_key VARCHAR(150) NULL,unit_id INT UNSIGNED NULL,organizational_role_id INT UNSIGNED NULL,task_type VARCHAR(40) NOT NULL DEFAULT 'custom',priority VARCHAR(20) NOT NULL DEFAULT 'normal',default_status VARCHAR(20) NOT NULL DEFAULT 'todo',default_due_offset_days INT NOT NULL DEFAULT 0,recurrence_type VARCHAR(20) NOT NULL DEFAULT 'none',recurrence_rule VARCHAR(255) NULL,is_required TINYINT(1) NOT NULL DEFAULT 0,is_visible_on_dashboard TINYINT(1) NOT NULL DEFAULT 1,is_active TINYINT(1) NOT NULL DEFAULT 1,sort_order INT NOT NULL DEFAULT 0,created_by INT UNSIGNED NULL,created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,UNIQUE KEY uq_work_planner_template(title,role_key,department_key,organizational_role_id,unit_id),INDEX idx_work_planner_template_match(organizational_role_id,unit_id,is_active),INDEX idx_work_planner_template_recurrence(recurrence_type,is_active)){$engine}",
            "CREATE TABLE IF NOT EXISTS work_planner_tasks (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,template_id INT UNSIGNED NULL,user_id INT UNSIGNED NOT NULL,employee_id INT UNSIGNED NULL,assigned_by INT UNSIGNED NULL,assigned_to_role_id INT UNSIGNED NULL,assigned_to_unit_id INT UNSIGNED NULL,title VARCHAR(190) NOT NULL,description TEXT NULL,task_type VARCHAR(40) NOT NULL DEFAULT 'custom',priority VARCHAR(20) NOT NULL DEFAULT 'normal',status VARCHAR(20) NOT NULL DEFAULT 'todo',start_date DATE NULL,due_date DATE NULL,completed_at DATETIME NULL,progress_percent TINYINT UNSIGNED NOT NULL DEFAULT 0,related_module VARCHAR(100) NULL,related_record_id BIGINT UNSIGNED NULL,parent_task_id BIGINT UNSIGNED NULL,recurrence_key VARCHAR(100) NULL,is_locked TINYINT(1) NOT NULL DEFAULT 0,is_personal TINYINT(1) NOT NULL DEFAULT 0,is_visible_on_dashboard TINYINT(1) NOT NULL DEFAULT 1,manual_sort_order INT NOT NULL DEFAULT 0,recurrence_type VARCHAR(20) NOT NULL DEFAULT 'none',recurrence_interval INT UNSIGNED NOT NULL DEFAULT 1,created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,UNIQUE KEY uq_work_planner_generated(template_id,user_id,start_date),UNIQUE KEY uq_work_planner_recurrence(recurrence_key),INDEX idx_work_planner_user_status(user_id,status,due_date),INDEX idx_work_planner_scope(assigned_to_unit_id,assigned_to_role_id),INDEX idx_work_planner_related(related_module,related_record_id)){$engine}",
            "CREATE TABLE IF NOT EXISTS work_planner_user_preferences (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id INT UNSIGNED NOT NULL UNIQUE,default_view VARCHAR(20) NOT NULL DEFAULT 'list',dashboard_widget_enabled TINYINT(1) NOT NULL DEFAULT 1,show_in_progress_first TINYINT(1) NOT NULL DEFAULT 1,show_overdue_tasks TINYINT(1) NOT NULL DEFAULT 1,show_today_tasks TINYINT(1) NOT NULL DEFAULT 1,show_completed_tasks TINYINT(1) NOT NULL DEFAULT 0,preferred_grouping VARCHAR(20) NOT NULL DEFAULT 'status',preferred_sorting VARCHAR(20) NOT NULL DEFAULT 'priority',work_style VARCHAR(40) NULL,compact_mode TINYINT(1) NOT NULL DEFAULT 0,created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP){$engine}",
            "CREATE TABLE IF NOT EXISTS work_planner_task_logs (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,task_id BIGINT UNSIGNED NOT NULL,user_id INT UNSIGNED NULL,action VARCHAR(40) NOT NULL,old_value_json LONGTEXT NULL,new_value_json LONGTEXT NULL,note TEXT NULL,created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,INDEX idx_work_planner_logs_task(task_id,created_at),INDEX idx_work_planner_logs_user(user_id)){$engine}",
            "CREATE TABLE IF NOT EXISTS work_planner_comments (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,task_id BIGINT UNSIGNED NOT NULL,user_id INT UNSIGNED NOT NULL,comment_text TEXT NOT NULL,created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,INDEX idx_work_planner_comments_task(task_id,created_at)){$engine}",
        ];
    }

    private static function seedPermissions(PDO $pdo): void
    {
        $stmt=$pdo->prepare('INSERT IGNORE INTO modules(module_key,module_title,sort_order,status,created_at) VALUES (?,?,?,"active",NOW())');
        foreach ([['work_planner.view','مشاهده پلنر کاری',715],['work_planner.assign','تخصیص وظیفه',716],['work_planner.manage','مدیریت پلنر کاری',717],['work_planner.templates','مدیریت قالب‌های پلنر',718]] as $module) $stmt->execute($module);
    }

    private static function seedTemplates(PDO $pdo): void
    {
        $sets=[
            'VISITOR'=>['field_sales',['بررسی مسیر روزانه','ثبت ویزیت‌های روزانه','ثبت سفارش مشتریان','پیگیری وصول مطالبات','ارسال گزارش پایان روز','ارسال لوکیشن یا گزارش مسیر','ثبت مشتری جدید','پیگیری مشتریان بدون خرید']],
            'SALES_SUPERVISOR'=>['supervisor',['بررسی عملکرد روزانه ویزیتورها','کنترل پوشش مسیر','بررسی فاکتورهای ثبت‌شده','پیگیری وصول مطالبات تیم','بررسی مشکلات مشتریان','ارسال گزارش تیم به مدیر فروش','آموزش و حمایت از ویزیتورها','تحلیل بازار و رقبا']],
            'SALES_MANAGER'=>['sales_manager',['بررسی فروش روزانه لاین‌ها','تحلیل تحقق تارگت','بررسی تخفیفات','بررسی عملکرد سرپرستان','ثبت برنامه عملیاتی ماهانه','ثبت صورتجلسه فروش','پیگیری مشتریان کلیدی','بررسی گزارش کالاهای کم‌فروش و پرفروش']],
            'WAREHOUSE_STAFF'=>['warehouse_operation',['بررسی ورودی کالا','کنترل مغایرت موجودی','آماده‌سازی سفارش‌ها','کنترل بارگیری','بررسی کالاهای نزدیک انقضا','گزارش ضایعات یا کسری','کنترل نظم و نظافت محیط انبار']],
            'FINANCE_MANAGER'=>['finance_admin',['ثبت اسناد روزانه','بررسی چک‌ها و وصولی‌ها','مغایرت‌گیری حساب‌ها','پیگیری پرداخت‌ها','ثبت گزارش مالی دوره‌ای','پیگیری بیمه و مالیات','بررسی حساب باز مشتریان']],
            'IT_STAFF'=>['it_planning',['بررسی بکاپ روزانه','بررسی سلامت سرور و اینترنت','رسیدگی به تیکت‌ها','ثبت تغییرات سیستم','بررسی گزارش‌های عملیاتی','کنترل صحت داده‌های واردشده','تحلیل خطاهای تکراری','پیشنهاد بهبود فرآیند']],
            'CEO'=>['executive',['بررسی خلاصه فروش','بررسی تحقق تارگت','بررسی وضعیت نقدینگی و مطالبات','بررسی KPI واحدها','پیگیری مصوبات جلسات','بررسی گزارش AI Insight','بررسی موارد نیازمند اقدام فوری']],
        ];
        foreach (['WAREHOUSE_MANAGER'=>'WAREHOUSE_STAFF','WAREHOUSE_SUPERVISOR'=>'WAREHOUSE_STAFF','TREASURY'=>'FINANCE_MANAGER','SALES_ACCOUNTING'=>'FINANCE_MANAGER','TAX_INSURANCE'=>'FINANCE_MANAGER','PLANNING_STAFF'=>'IT_STAFF','INTERNAL_MANAGER'=>'CEO'] as $alias=>$source) $sets[$alias]=$sets[$source];
        $stmt=$pdo->prepare('INSERT INTO work_planner_templates(title,role_key,organizational_role_id,task_type,priority,default_status,default_due_offset_days,recurrence_type,is_required,is_visible_on_dashboard,is_active,sort_order,created_at,updated_at) SELECT ?,r.code,r.id,?,?,"todo",0,?,1,1,1,?,NOW(),NOW() FROM org_roles r WHERE r.code=? AND NOT EXISTS(SELECT 1 FROM work_planner_templates t WHERE t.title=? AND t.organizational_role_id=r.id) LIMIT 1');
        foreach($sets as $roleCode=>[$style,$titles]){foreach($titles as $index=>$title){$type=str_contains($title,'گزارش')?'report':(str_contains($title,'پیگیری')?'follow_up':'daily');$recurrence=in_array($type,['report','follow_up'],true)?'weekly':'daily';$stmt->execute([$title,$type,$index<2?'high':'normal',$recurrence,($index+1)*10,$roleCode,$title]);}}
    }
}
