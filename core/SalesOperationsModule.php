<?php
require_once __DIR__ . '/Database.php';

class SalesOperationsModule
{
    public static function repair(PDO $pdo): void
    {
        foreach (self::schema() as $sql) {
            $pdo->exec($sql);
        }
        self::seedModules($pdo);
        self::seedSupervisorSections($pdo);
        self::seedDefaultFields($pdo);
    }

    public static function schema(): array
    {
        $engine = ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        return [
            "CREATE TABLE IF NOT EXISTS sales_team_assignments (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, supervisor_id INT UNSIGNED NOT NULL, visitor_id INT UNSIGNED NOT NULL, sales_manager_id INT UNSIGNED NULL, sales_line VARCHAR(50) NULL, active TINYINT(1) NOT NULL DEFAULT 1, created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, UNIQUE KEY uq_sales_team_active (supervisor_id, visitor_id), INDEX idx_sales_team_supervisor (supervisor_id,active), INDEX idx_sales_team_visitor (visitor_id,active), INDEX idx_sales_team_manager (sales_manager_id,active), INDEX idx_sales_team_line (sales_line,active)){$engine}",
            "CREATE TABLE IF NOT EXISTS sales_scripts (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, title VARCHAR(190) NOT NULL, script_code VARCHAR(100) NOT NULL, script_body LONGTEXT NOT NULL, target_scope VARCHAR(60) NOT NULL DEFAULT 'sales_line', sales_line VARCHAR(50) NULL, supervisor_id INT UNSIGNED NULL, visitor_id INT UNSIGNED NULL, brand_id BIGINT UNSIGNED NULL, product_id BIGINT UNSIGNED NULL, customer_type VARCHAR(100) NULL, active TINYINT(1) NOT NULL DEFAULT 1, created_by INT UNSIGNED NULL, created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, UNIQUE KEY uq_sales_script_code (script_code), INDEX idx_sales_scripts_scope (target_scope,sales_line,active), INDEX idx_sales_scripts_supervisor (supervisor_id,active), INDEX idx_sales_scripts_visitor (visitor_id,active)){$engine}",
            "CREATE TABLE IF NOT EXISTS sales_script_fields (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, script_id BIGINT UNSIGNED NOT NULL, field_key VARCHAR(100) NOT NULL, field_label VARCHAR(190) NOT NULL, field_type VARCHAR(40) NOT NULL DEFAULT 'text', options_json LONGTEXT NULL, default_value TEXT NULL, required TINYINT(1) NOT NULL DEFAULT 0, visible_to_supervisor TINYINT(1) NOT NULL DEFAULT 1, visible_to_sales_manager TINYINT(1) NOT NULL DEFAULT 1, sort_order INT NOT NULL DEFAULT 0, active TINYINT(1) NOT NULL DEFAULT 1, created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, UNIQUE KEY uq_sales_script_field (script_id, field_key), INDEX idx_sales_script_fields_script (script_id,active,sort_order)){$engine}",
            "CREATE TABLE IF NOT EXISTS sales_script_assignments (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, script_id BIGINT UNSIGNED NOT NULL, assigned_to_type VARCHAR(40) NOT NULL, assigned_to_id BIGINT UNSIGNED NULL, sales_line VARCHAR(50) NULL, active TINYINT(1) NOT NULL DEFAULT 1, created_by INT UNSIGNED NULL, created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uq_sales_script_assignment (script_id, assigned_to_type, assigned_to_id, sales_line), INDEX idx_sales_script_assignments_lookup (assigned_to_type,assigned_to_id,sales_line,active)){$engine}",
            "CREATE TABLE IF NOT EXISTS sales_supervisor_reports (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, script_id BIGINT UNSIGNED NULL, supervisor_id INT UNSIGNED NOT NULL, sales_manager_id INT UNSIGNED NULL, visitor_id INT UNSIGNED NULL, sales_line VARCHAR(50) NULL, report_type VARCHAR(60) NOT NULL DEFAULT 'script_execution', report_date DATE NOT NULL, title VARCHAR(190) NOT NULL, summary TEXT NULL, status VARCHAR(40) NOT NULL DEFAULT 'submitted_by_supervisor', manager_comment TEXT NULL, reviewed_by INT UNSIGNED NULL, reviewed_at DATETIME NULL, created_by INT UNSIGNED NULL, created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, INDEX idx_sales_supervisor_reports_supervisor (supervisor_id,report_date), INDEX idx_sales_supervisor_reports_manager (sales_manager_id,status,report_date), INDEX idx_sales_supervisor_reports_status (status), INDEX idx_sales_supervisor_reports_line (sales_line,report_date)){$engine}",
            "CREATE TABLE IF NOT EXISTS sales_supervisor_report_values (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, report_id BIGINT UNSIGNED NOT NULL, field_id BIGINT UNSIGNED NULL, field_key VARCHAR(100) NOT NULL, field_label VARCHAR(190) NULL, value_text LONGTEXT NULL, created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uq_sales_report_value (report_id, field_key), INDEX idx_sales_report_values_report (report_id)){$engine}",
            "CREATE TABLE IF NOT EXISTS supervisor_script_sections (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, code VARCHAR(100) NOT NULL UNIQUE, title VARCHAR(190) NOT NULL, description TEXT NULL, sort_order INT NOT NULL DEFAULT 0, active TINYINT(1) NOT NULL DEFAULT 1, created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, INDEX idx_supervisor_sections_active (active,sort_order)){$engine}",
            "CREATE TABLE IF NOT EXISTS supervisor_action_field_templates (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, section_id BIGINT UNSIGNED NOT NULL, field_key VARCHAR(100) NOT NULL, field_label VARCHAR(190) NOT NULL, field_type VARCHAR(40) NOT NULL DEFAULT 'text', options_json LONGTEXT NULL, required TINYINT(1) NOT NULL DEFAULT 0, sort_order INT NOT NULL DEFAULT 0, active TINYINT(1) NOT NULL DEFAULT 1, created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, UNIQUE KEY uq_supervisor_field (section_id, field_key), INDEX idx_supervisor_fields_section (section_id,active,sort_order)){$engine}",
            "CREATE TABLE IF NOT EXISTS supervisor_actions (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, supervisor_id INT UNSIGNED NOT NULL, sales_manager_id INT UNSIGNED NULL, sales_line VARCHAR(50) NULL, section_id BIGINT UNSIGNED NULL, visitor_id INT UNSIGNED NULL, customer_id BIGINT UNSIGNED NULL, title VARCHAR(190) NOT NULL, description TEXT NULL, action_type VARCHAR(60) NULL, priority VARCHAR(20) NOT NULL DEFAULT 'normal', status VARCHAR(40) NOT NULL DEFAULT 'open', due_date DATE NULL, dynamic_values_json LONGTEXT NULL, manager_note TEXT NULL, planner_task_id BIGINT UNSIGNED NULL, created_by INT UNSIGNED NULL, updated_by INT UNSIGNED NULL, created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, completed_at DATETIME NULL, INDEX idx_supervisor_actions_supervisor (supervisor_id,status,due_date), INDEX idx_supervisor_actions_manager (sales_manager_id,status,due_date), INDEX idx_supervisor_actions_visitor (visitor_id), INDEX idx_supervisor_actions_status (status), INDEX idx_supervisor_actions_section (section_id)){$engine}",
            "CREATE TABLE IF NOT EXISTS supervisor_action_logs (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, action_id BIGINT UNSIGNED NOT NULL, action VARCHAR(60) NOT NULL, old_value_json LONGTEXT NULL, new_value_json LONGTEXT NULL, performed_by INT UNSIGNED NULL, created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP, INDEX idx_supervisor_action_logs_action (action_id,created_at), INDEX idx_supervisor_action_logs_user (performed_by)){$engine}",
            "CREATE TABLE IF NOT EXISTS sales_actions (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, source_type VARCHAR(80) NULL, source_id BIGINT UNSIGNED NULL, sales_manager_id INT UNSIGNED NULL, supervisor_id INT UNSIGNED NULL, visitor_id INT UNSIGNED NULL, customer_id BIGINT UNSIGNED NULL, brand_id BIGINT UNSIGNED NULL, product_id BIGINT UNSIGNED NULL, sales_line VARCHAR(50) NULL, assigned_to INT UNSIGNED NULL, title VARCHAR(190) NOT NULL, description TEXT NULL, priority VARCHAR(20) NOT NULL DEFAULT 'normal', status VARCHAR(40) NOT NULL DEFAULT 'open', due_date DATE NULL, result_note TEXT NULL, dynamic_values_json LONGTEXT NULL, planner_task_id BIGINT UNSIGNED NULL, created_by INT UNSIGNED NULL, updated_by INT UNSIGNED NULL, created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, completed_at DATETIME NULL, INDEX idx_sales_actions_manager (sales_manager_id,status,due_date), INDEX idx_sales_actions_assigned (assigned_to,status,due_date), INDEX idx_sales_actions_source (source_type,source_id), INDEX idx_sales_actions_status (status)){$engine}",
            "CREATE TABLE IF NOT EXISTS sales_action_values (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, action_id BIGINT UNSIGNED NOT NULL, field_key VARCHAR(100) NOT NULL, field_label VARCHAR(190) NULL, value_text LONGTEXT NULL, created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uq_sales_action_value (action_id, field_key), INDEX idx_sales_action_values_action (action_id)){$engine}",
            "CREATE TABLE IF NOT EXISTS sales_purchase_suggestions (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, suggestion_date DATE NOT NULL, sales_manager_id INT UNSIGNED NOT NULL, sales_line VARCHAR(50) NULL, brand_id BIGINT UNSIGNED NULL, product_id BIGINT UNSIGNED NULL, suggested_quantity DECIMAL(18,2) NOT NULL DEFAULT 0, reason TEXT NOT NULL, market_demand_note TEXT NULL, requested_by_customers TEXT NULL, current_stock DECIMAL(18,2) NULL, current_month_sales DECIMAL(18,2) NULL, previous_month_sales DECIMAL(18,2) NULL, shortage_risk VARCHAR(40) NULL, priority VARCHAR(20) NOT NULL DEFAULT 'normal', status VARCHAR(40) NOT NULL DEFAULT 'draft', purchasing_comment TEXT NULL, management_comment TEXT NULL, created_by INT UNSIGNED NULL, updated_by INT UNSIGNED NULL, created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, INDEX idx_sales_purchase_manager (sales_manager_id,status,suggestion_date), INDEX idx_sales_purchase_status (status), INDEX idx_sales_purchase_line (sales_line,suggestion_date), INDEX idx_sales_purchase_product (product_id)){$engine}",
            "CREATE TABLE IF NOT EXISTS sales_purchase_suggestion_logs (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, suggestion_id BIGINT UNSIGNED NOT NULL, action VARCHAR(60) NOT NULL, old_value_json LONGTEXT NULL, new_value_json LONGTEXT NULL, performed_by INT UNSIGNED NULL, created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP, INDEX idx_sales_purchase_logs_suggestion (suggestion_id,created_at), INDEX idx_sales_purchase_logs_user (performed_by)){$engine}",
            "CREATE TABLE IF NOT EXISTS sales_manager_daily_work_logs (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, sales_manager_id INT UNSIGNED NOT NULL, log_date DATE NOT NULL, fields_json LONGTEXT NOT NULL, management_note TEXT NULL, created_by INT UNSIGNED NULL, created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, UNIQUE KEY uq_sales_manager_daily_log (sales_manager_id, log_date), INDEX idx_sales_manager_daily_date (log_date), INDEX idx_sales_manager_daily_manager (sales_manager_id)){$engine}"
        ];
    }

    private static function seedModules(PDO $pdo): void
    {
        $stmt = $pdo->prepare('INSERT IGNORE INTO modules(module_key,module_title,sort_order,status,created_at) VALUES (?,?,?,"active",NOW())');
        foreach ([
            ['supervisor.panel.view','مشاهده پنل سرپرست فروش',760],
            ['supervisor.sales.view','مشاهده فروش تیم سرپرست',761],
            ['supervisor.actions.manage','مدیریت اقدامات سرپرست فروش',762],
            ['sales_manager.supervisors.view','مشاهده عملکرد سرپرستان فروش',763],
            ['sales_manager.supervisor_actions.review','بررسی اقدامات سرپرستان',764],
            ['sales_manager.scripts.manage','مدیریت اسکریپت‌های فروش',765],
            ['sales_manager.actions.manage','مدیریت اقدامات فروش',766],
            ['sales_manager.purchase_suggestions.manage','مدیریت پیشنهاد اردر خرید',767],
            ['sales_manager.daily_logs.manage','ثبت گزارش‌کار روزانه مدیر فروش',768],
            ['admin.supervisor_settings.manage','تنظیمات پنل سرپرستان فروش',769],
        ] as $module) {
            $stmt->execute($module);
        }
    }

    private static function seedSupervisorSections(PDO $pdo): void
    {
        $sections = [
            ['sales_target','تحقق هدف فروش تیم','بررسی وضعیت تحقق هدف روزانه و ماهانه تیم، علت عقب‌ماندگی و برنامه جبران.',10],
            ['visitor_daily_control','کنترل عملکرد روزانه ویزیتورها','کنترل حضور، مسیر، فاکتور، مشتریان بدون خرید و کیفیت ثبت سفارش.',20],
            ['market_presence','حضور در بازار و تحلیل رقبا','ثبت مشاهدات بازار، فعالیت رقبا، تغییر قیمت و فرصت‌های فروش.',30],
            ['visitor_training','آموزش و حمایت از ویزیتورها','ثبت نیاز آموزشی، همراهی بازار و حمایت عملیاتی از ویزیتورها.',40],
            ['coverage_growth','افزایش پوشش مشتری و جذب مشتری جدید','پیگیری پوشش مسیر، مشتریان جدید، مشتریان راکد و مشتریان کلیدی.',50],
            ['returns_control','کنترل مرجوعی و خطاهای سفارش‌گیری','تحلیل خطاهای سفارش، مرجوعی، علت و اقدام اصلاحی.',60],
            ['receivables_followup','پیگیری وصول مطالبات','پیگیری حساب‌های باز، چک‌ها و موارد نیازمند هماهنگی مالی.',70],
            ['problem_solving','حل مشکلات مشتریان و ویزیتورها','ثبت مشکل، ریشه مشکل، اقدام انجام‌شده و نیاز به تصمیم مدیر فروش.',80],
            ['daily_report','گزارش روزانه به مدیر فروش','جمع‌بندی عملکرد روز و ارسال موارد مهم به مدیر فروش.',90],
            ['next_day_plan','برنامه اصلاحی روز بعد','برنامه اقدام، اولویت‌ها و مسئول پیگیری برای روز بعد.',100],
        ];
        $stmt = $pdo->prepare('INSERT INTO supervisor_script_sections(code,title,description,sort_order,active,created_at,updated_at) VALUES (?,?,?,?,1,NOW(),NOW()) ON DUPLICATE KEY UPDATE title=VALUES(title),description=VALUES(description),sort_order=VALUES(sort_order),active=1,updated_at=NOW()');
        foreach ($sections as $section) $stmt->execute($section);
    }

    private static function seedDefaultFields(PDO $pdo): void
    {
        $fields = [
            ['action_title','عنوان اقدام','text',1,10],
            ['action_type','نوع اقدام','select',1,20],
            ['status_description','توضیح وضعیت','textarea',0,30],
            ['related_visitor','ویزیتور مرتبط','visitor_select',0,40],
            ['related_customer','مشتری مرتبط','customer_select',0,50],
            ['problem_reason','علت مشکل','textarea',0,60],
            ['suggested_action','اقدام پیشنهادی','textarea',1,70],
            ['due_date','مهلت انجام','date',0,80],
            ['priority','اولویت','select',1,90],
            ['status','وضعیت','select',1,100],
            ['result_note','نتیجه اقدام','textarea',0,110],
            ['add_to_planner','نیاز به اضافه‌شدن به پلنر','checkbox',0,120],
        ];
        $sections = $pdo->query('SELECT id FROM supervisor_script_sections')->fetchAll(PDO::FETCH_ASSOC);
        $stmt = $pdo->prepare('INSERT INTO supervisor_action_field_templates(section_id,field_key,field_label,field_type,options_json,required,sort_order,active,created_at,updated_at) VALUES (?,?,?,?,?,?,?,1,NOW(),NOW()) ON DUPLICATE KEY UPDATE field_label=VALUES(field_label),field_type=VALUES(field_type),options_json=VALUES(options_json),required=VALUES(required),sort_order=VALUES(sort_order),active=1,updated_at=NOW()');
        foreach ($sections as $section) {
            foreach ($fields as $field) {
                $options = null;
                if ($field[0] === 'priority') $options = json_encode(['low'=>'پایین','normal'=>'متوسط','high'=>'بالا','urgent'=>'فوری'], JSON_UNESCAPED_UNICODE);
                if ($field[0] === 'status') $options = json_encode(['draft'=>'پیش‌نویس','in_progress'=>'در حال پیگیری','done'=>'انجام‌شده','cancelled'=>'لغوشده','needs_manager_review'=>'نیازمند بررسی مدیر فروش'], JSON_UNESCAPED_UNICODE);
                if ($field[0] === 'action_type') $options = json_encode(['sales'=>'فروش','collection'=>'وصول','training'=>'آموزش','customer'=>'مشتری','market'=>'بازار','other'=>'سایر'], JSON_UNESCAPED_UNICODE);
                $stmt->execute([(int)$section['id'], $field[0], $field[1], $field[2], $options, $field[3], $field[4]]);
            }
        }
    }
}
