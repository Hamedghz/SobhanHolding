<?php

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/JalaliDate.php';
require_once __DIR__ . '/CeoDashboardExcel.php';
require_once __DIR__ . '/Response.php';
require_once __DIR__ . '/SobhanApiClient.php';
require_once __DIR__ . '/ManagerDashboardCalculator.php';

class ManagerDashboard
{
    private const LINES = ['A', 'B', 'C', 'D', 'A-B', 'C-D'];

    public static function widgets(): array
    {
        return [
            'commission_summary' => ['title' => 'خلاصه پورسانت ویزیتورها', 'table' => 'manager_commission_summary'],
            'team_target_achievement' => ['title' => 'تحقق تارگت تیم فروش', 'table' => 'manager_team_target_achievement'],
            'over_achievement_bonus' => ['title' => 'پاداش اچیو، توتال و بالای ۱۰۰٪', 'table' => 'manager_over_achievement_bonus'],
            'visitor_penalty' => ['title' => 'ضرایب کاهنده ویزیتورها', 'table' => 'manager_visitor_penalty_coefficients'],
            'supervisor_penalty' => ['title' => 'ضرایب کاهنده سرپرستان', 'table' => 'manager_supervisor_penalty_coefficients'],
            'customer_target' => ['title' => 'تحقق هدف مشتری', 'table' => 'manager_customer_target_achievement'],
            'customer_coverage' => ['title' => 'پوشش مشتری', 'table' => 'manager_customer_coverage'],
            'brand_target' => ['title' => 'تحقق برندهای تارگت‌دار', 'table' => 'manager_brand_target_achievement'],
            'line_performance' => ['title' => 'عملکرد لاین‌ها', 'table' => 'manager_line_performance'],
            'supervisor_performance' => ['title' => 'عملکرد سرپرستان', 'table' => 'manager_supervisor_performance'],
            'sales_manager_performance' => ['title' => 'عملکرد مدیران فروش', 'table' => 'manager_sales_manager_performance'],
            'ai_insights' => ['title' => 'بینش هوشمند فروش', 'table' => null],
        ];
    }

    public static function definitions(): array
    {
        $date = ['report_date', 'تاریخ گزارش', 'date'];
        return [
            'commission_summary' => self::definition('خلاصه پورسانت ویزیتورها', 'manager_commission_summary', [$date,
                ['visitor_name','ویزیتور','text'],['line_code','شعبه / لاین','line'],['sales_amount','مبلغ فروش','money'],['achievement_percent','درصد تحقق','percent'],['condition_status','شرط دریافت پورسانت','status'],['activity_commission','پورسانت با ضریب فعالیت','money'],['penalty_percent','ضریب کاهنده','percent'],['commission_after_penalty','پورسانت با ضریب کاهنده','money'],['return_loss','خسارت مرجوعی','signed_money'],['quality_bonus','پاداش کیفی','money'],['target_line_count','تعداد لاین تارگت‌دار','number'],['achieved_line_count','تعداد لاین اچیو شده','number'],['target_achievement_percent','درصد تحقق تارگت','percent'],['target_bonus_before_condition','پاداش تارگت قبل از شرط','money'],['target_bonus_status','وضعیت پاداش تارگت','status'],['target_bonus_final_amount','مبلغ نهایی پاداش تارگت','money'],['achievement_below_75_count','تعداد اچیو پایین ۷۵٪','number'],['achievement_between_75_95_count','تعداد اچیو بین ۷۵ تا ۹۵٪','number'],['group_achievement_bonus','پاداش اچیو گروه','money'],['total_achievement_bonus','پاداش اچیو توتال','money'],['coverage_bonus','پاداش پوشش مشتری','money'],['final_commission','پورسانت نهایی','money']], ['report_date','visitor_name']),
            'team_target_achievement' => self::definition('تحقق تارگت تیم فروش', 'manager_team_target_achievement', [$date,['entity_type','نوع شخص','entity'],['line_code','لاین','line'],['person_name','نام','text'],['target_qty','تارگت','number'],['sold_qty','فروش','number'],['achievement_percent','درصد تحقق','percent'],['over_total_bonus','پاداش اور توتال','money']], ['report_date','person_name','line_code']),
            'over_achievement_bonus' => self::definition('پاداش اچیو و توتال', 'manager_over_achievement_bonus', [$date,['entity_type','نوع شخص','entity'],['line_code','لاین','line'],['person_name','نام','text'],['group_achievement_bonus','پاداش اور اچیو گروه','money'],['total_achievement_bonus','پاداش اور توتال','money'],['visitor_100_bonus','پاداش تحقق ویزیتور ۱۰۰٪','money'],['achieved_visitor_count','تعداد ویزیتور زده','number']], ['report_date','person_name','line_code']),
            'visitor_penalty' => self::definition('ضرایب کاهنده ویزیتورها', 'manager_visitor_penalty_coefficients', [$date,['visitor_name','نام ویزیتور','text'],['penalty_percent','درصد جریمه','percent'],['commission_before_penalty','پورسانت با ضریب','money'],['commission_after_penalty','پورسانت بعد از جریمه','money']], ['report_date','visitor_name']),
            'supervisor_penalty' => self::definition('ضرایب کاهنده سرپرستان', 'manager_supervisor_penalty_coefficients', [$date,['supervisor_name','نام سرپرست','text'],['penalty_percent','درصد جریمه','percent'],['commission_before_penalty','پورسانت با ضریب','money'],['commission_after_penalty','پورسانت بعد از جریمه','money']], ['report_date','supervisor_name']),
            'customer_target' => self::definition('تحقق هدف مشتری', 'manager_customer_target_achievement', [$date,['visitor_name','ویزیتور','text'],['customer_target_floor','کف مشتری','number'],['achieved_customer_count','تحقق تعداد هدف مشتری','number'],['achievement_percent','درصد تحقق','percent'],['customer_target_bonus','پاداش هدف مشتری','money'],['remaining_to_entry','مانده تا ورود','signed_number'],['supervisor_name','سرپرست','text']], ['report_date','visitor_name']),
            'customer_coverage' => self::definition('پوشش مشتری', 'manager_customer_coverage', [$date,['visitor_name','نام ویزیتور','text'],['customer_count','تعداد مشتری','number'],['coverage_count','پوشش','number'],['customer_floor','کف مشتری','number'],['reward_or_penalty','پاداش / جریمه','signed_money'],['remaining_to_target','مانده تا هدف','signed_number']], ['report_date','visitor_name']),
            'brand_target' => self::definition('تحقق برند فروشنده', 'manager_brand_target_achievement', [$date,['visitor_name','نام ویزیتور','text'],['target_brand_count','برند تارگت‌دار','number'],['achieved_brand_count','برند تارگت زده','number'],['achievement_percent','درصد تحقق','percent'],['commission_status','وضعیت پورسانت','status']], ['report_date','visitor_name']),
            'line_performance' => self::definition('عملکرد لاین‌ها', 'manager_line_performance', [$date,['line_code','لاین','line'],['line_sales_amount','فروش لاین','money'],['sold_qty','قطعه','number'],['target_qty','تارگت','number'],['achievement_percent','درصد تحقق','percent']], ['report_date','line_code']),
            'supervisor_performance' => self::definition('عملکرد سرپرستان', 'manager_supervisor_performance', [$date,['line_code','لاین','line'],['supervisor_name','سرپرست','text'],['target_qty','تارگت','number'],['sold_qty','فروش','number'],['sales_amount','ریال فروش','money'],['achievement_percent','درصد تحقق','percent']], ['report_date','line_code','supervisor_name']),
            'sales_manager_performance' => self::definition('عملکرد مدیران فروش', 'manager_sales_manager_performance', [$date,['line_group','لاین','line'],['sales_manager_name','مدیر فروش','text'],['target_qty','تارگت','number'],['sold_qty','فروش','number'],['sales_amount','ریال فروش','money'],['achievement_percent','درصد تحقق','percent']], ['report_date','line_group','sales_manager_name']),
        ];
    }

    private static function definition(string $sheet, string $table, array $fields, array $required): array
    {
        return ['sheet' => $sheet, 'table' => $table, 'fields' => $fields, 'required' => $required];
    }

    public static function repair(PDO $pdo): void
    {
        $sql = self::schemaSql();
        foreach (array_filter(array_map('trim', preg_split('/;\s*(?:\r?\n|$)/', $sql))) as $statement) $pdo->exec($statement);
        $stmt = $pdo->prepare('INSERT INTO manager_dashboard_widget_settings (widget_key,widget_title_fa,sort_order,is_enabled,show_in_dashboard,allow_import,allow_export,allow_manual_edit,created_at,updated_at) VALUES (?,?,?,1,1,1,1,1,NOW(),NOW()) ON DUPLICATE KEY UPDATE widget_key=VALUES(widget_key)');
        $order = 10;
        foreach (self::widgets() as $key => $widget) { $stmt->execute([$key, $widget['title'], $order]); $order += 10; }
        $widgetColumns = [
            'allow_image_export' => 'ADD allow_image_export TINYINT(1) NOT NULL DEFAULT 1 AFTER allow_export',
        ];
        foreach ($widgetColumns as $column => $alter) if (!Database::columnExists('manager_dashboard_widget_settings', $column)) $pdo->exec("ALTER TABLE manager_dashboard_widget_settings {$alter}");
        $skillStmt = $pdo->prepare('INSERT INTO manager_dashboard_ai_skills (skill_key,skill_title_fa,skill_description_fa,skill_type,is_enabled,sort_order,system_prompt,input_schema_json,output_schema_json,created_at,updated_at) VALUES (?,?,?,?,1,?,?,?,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE skill_key=VALUES(skill_key)');
        foreach (self::defaultSkills() as $skill) $skillStmt->execute([$skill['key'],$skill['title'],$skill['description'],$skill['type'],$skill['sort_order'],$skill['prompt'],$skill['input_schema'],$skill['output_schema']]);
        if (Database::tableExists('site_settings')) {
            $defaults = self::settingDefaults();
            $settingStmt = $pdo->prepare('INSERT INTO site_settings (setting_key,setting_value,setting_type,updated_at) VALUES (?,?,?,NOW()) ON DUPLICATE KEY UPDATE setting_type=VALUES(setting_type)');
            foreach ($defaults as $key => [$value,$type]) $settingStmt->execute(['manager_dashboard_'.$key,$value,$type]);
        }
    }

    public static function settingDefaults(): array
    {
        $defaults = [
            'dashboard_title' => ['داشبورد مدیران فروش','text'], 'default_report_id' => ['0','number'],
            'default_report_mode' => ['latest_report','select'], 'number_format' => ['thousands','select'],
            'currency_label' => ['ریال','text'], 'date_format' => ['jalali','select'],
            'show_latest_report_by_default' => ['1','boolean'], 'excel_strict_default' => ['0','boolean'],
            'excel_max_size_mb' => ['10','number'], 'image_export_enabled' => ['1','boolean'],
            'image_export_format' => ['png','select'], 'image_export_include_title' => ['1','boolean'],
            'image_export_include_report_date' => ['1','boolean'], 'image_export_include_company_name' => ['1','boolean'],
            'image_export_watermark_enabled' => ['0','boolean'], 'image_export_watermark_text' => ['داشبورد مدیران فروش','text'],
            'ai_enabled' => ['0','boolean'], 'ai_skills_enabled' => ['1','boolean'],
            'ai_show_buttons' => ['1','boolean'], 'ai_read_latest_report' => ['1','boolean'],
            'ai_read_selected_report' => ['1','boolean'], 'ai_read_history' => ['0','boolean'],
            'ai_enabled_skills' => ['', 'text'],
        ];
        foreach (ManagerDashboardCalculator::ruleDefaults() as $key => $value) {
            $defaults['rule_' . $key] = [(string)$value, 'number'];
        }
        return $defaults;
    }

    public static function setting(string $key): string
    {
        $default = self::settingDefaults()[$key][0] ?? '';
        return setting('manager_dashboard_'.$key, $default);
    }

    public static function saveSettings(array $values): void
    {
        $defaults = self::settingDefaults();
        $stmt = Database::connection()->prepare('INSERT INTO site_settings (setting_key,setting_value,setting_type,updated_at) VALUES (?,?,?,NOW()) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),setting_type=VALUES(setting_type),updated_at=NOW()');
        foreach ($values as $key => $value) if (isset($defaults[$key])) $stmt->execute(['manager_dashboard_'.$key,(string)$value,$defaults[$key][1]]);
    }

    public static function schemaSql(): string
    {
        $common = 'id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, report_id INT UNSIGNED NOT NULL, report_date DATE NOT NULL';
        $tail = 'created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, INDEX idx_report (report_id), INDEX idx_date (report_date), CONSTRAINT %s FOREIGN KEY (report_id) REFERENCES manager_dashboard_reports(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        $tables = [];
        $tables[] = "CREATE TABLE IF NOT EXISTS manager_dashboard_reports (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, report_title VARCHAR(190) NOT NULL, report_date DATE NOT NULL, report_month TINYINT UNSIGNED NOT NULL DEFAULT 0, report_year SMALLINT UNSIGNED NOT NULL DEFAULT 0, source_file_name VARCHAR(255) NULL, imported_by INT UNSIGNED NULL, import_status VARCHAR(30) NOT NULL DEFAULT 'success', notes TEXT NULL, is_default TINYINT(1) NOT NULL DEFAULT 0, created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, INDEX idx_report_date (report_date), INDEX idx_default (is_default)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $cols = [
            'manager_commission_summary' => 'visitor_name VARCHAR(190) NOT NULL,line_code VARCHAR(20) NOT NULL,sales_amount DECIMAL(18,2) NOT NULL DEFAULT 0,achievement_percent DECIMAL(8,2) NOT NULL DEFAULT 0,condition_status VARCHAR(30) NULL,activity_commission DECIMAL(18,2) NOT NULL DEFAULT 0,penalty_percent DECIMAL(8,2) NOT NULL DEFAULT 0,commission_after_penalty DECIMAL(18,2) NOT NULL DEFAULT 0,return_loss DECIMAL(18,2) NOT NULL DEFAULT 0,quality_bonus DECIMAL(18,2) NOT NULL DEFAULT 0,target_line_count INT NOT NULL DEFAULT 0,achieved_line_count INT NOT NULL DEFAULT 0,target_achievement_percent DECIMAL(8,2) NOT NULL DEFAULT 0,target_bonus_before_condition DECIMAL(18,2) NOT NULL DEFAULT 0,target_bonus_status VARCHAR(30) NULL,target_bonus_final_amount DECIMAL(18,2) NOT NULL DEFAULT 0,achievement_below_75_count INT NOT NULL DEFAULT 0,achievement_between_75_95_count INT NOT NULL DEFAULT 0,group_achievement_bonus DECIMAL(18,2) NOT NULL DEFAULT 0,total_achievement_bonus DECIMAL(18,2) NOT NULL DEFAULT 0,coverage_bonus DECIMAL(18,2) NOT NULL DEFAULT 0,final_commission DECIMAL(18,2) NOT NULL DEFAULT 0',
            'manager_team_target_achievement' => 'entity_type VARCHAR(20) NOT NULL,line_code VARCHAR(20) NOT NULL,person_name VARCHAR(190) NOT NULL,target_qty DECIMAL(18,2) NOT NULL DEFAULT 0,sold_qty DECIMAL(18,2) NOT NULL DEFAULT 0,achievement_percent DECIMAL(8,2) NOT NULL DEFAULT 0,over_total_bonus DECIMAL(18,2) NOT NULL DEFAULT 0',
            'manager_over_achievement_bonus' => 'entity_type VARCHAR(20) NOT NULL,line_code VARCHAR(20) NOT NULL,person_name VARCHAR(190) NOT NULL,group_achievement_bonus DECIMAL(18,2) NOT NULL DEFAULT 0,total_achievement_bonus DECIMAL(18,2) NOT NULL DEFAULT 0,visitor_100_bonus DECIMAL(18,2) NOT NULL DEFAULT 0,achieved_visitor_count INT NOT NULL DEFAULT 0',
            'manager_visitor_penalty_coefficients' => 'visitor_name VARCHAR(190) NOT NULL,penalty_percent DECIMAL(8,2) NOT NULL DEFAULT 0,commission_before_penalty DECIMAL(18,2) NOT NULL DEFAULT 0,commission_after_penalty DECIMAL(18,2) NOT NULL DEFAULT 0',
            'manager_supervisor_penalty_coefficients' => 'supervisor_name VARCHAR(190) NOT NULL,penalty_percent DECIMAL(8,2) NOT NULL DEFAULT 0,commission_before_penalty DECIMAL(18,2) NOT NULL DEFAULT 0,commission_after_penalty DECIMAL(18,2) NOT NULL DEFAULT 0',
            'manager_customer_target_achievement' => 'visitor_name VARCHAR(190) NOT NULL,customer_target_floor INT NOT NULL DEFAULT 0,achieved_customer_count INT NOT NULL DEFAULT 0,achievement_percent DECIMAL(8,2) NOT NULL DEFAULT 0,customer_target_bonus DECIMAL(18,2) NOT NULL DEFAULT 0,remaining_to_entry INT NOT NULL DEFAULT 0,supervisor_name VARCHAR(190) NULL',
            'manager_customer_coverage' => 'visitor_name VARCHAR(190) NOT NULL,customer_count INT NOT NULL DEFAULT 0,coverage_count INT NOT NULL DEFAULT 0,customer_floor INT NOT NULL DEFAULT 0,reward_or_penalty DECIMAL(18,2) NOT NULL DEFAULT 0,remaining_to_target INT NOT NULL DEFAULT 0',
            'manager_brand_target_achievement' => 'visitor_name VARCHAR(190) NOT NULL,target_brand_count INT NOT NULL DEFAULT 0,achieved_brand_count INT NOT NULL DEFAULT 0,achievement_percent DECIMAL(8,2) NOT NULL DEFAULT 0,commission_status VARCHAR(30) NULL',
            'manager_line_performance' => 'line_code VARCHAR(20) NOT NULL,line_sales_amount DECIMAL(18,2) NOT NULL DEFAULT 0,sold_qty DECIMAL(18,2) NOT NULL DEFAULT 0,target_qty DECIMAL(18,2) NOT NULL DEFAULT 0,achievement_percent DECIMAL(8,2) NOT NULL DEFAULT 0',
            'manager_supervisor_performance' => 'line_code VARCHAR(20) NOT NULL,supervisor_name VARCHAR(190) NOT NULL,target_qty DECIMAL(18,2) NOT NULL DEFAULT 0,sold_qty DECIMAL(18,2) NOT NULL DEFAULT 0,sales_amount DECIMAL(18,2) NOT NULL DEFAULT 0,achievement_percent DECIMAL(8,2) NOT NULL DEFAULT 0',
            'manager_sales_manager_performance' => 'line_group VARCHAR(20) NOT NULL,sales_manager_name VARCHAR(190) NOT NULL,target_qty DECIMAL(18,2) NOT NULL DEFAULT 0,sold_qty DECIMAL(18,2) NOT NULL DEFAULT 0,sales_amount DECIMAL(18,2) NOT NULL DEFAULT 0,achievement_percent DECIMAL(8,2) NOT NULL DEFAULT 0',
        ];
        foreach ($cols as $name => $columns) $tables[] = "CREATE TABLE IF NOT EXISTS {$name} ({$common},{$columns}," . sprintf($tail, 'fk_' . substr(md5($name), 0, 12));
        $tables[] = "CREATE TABLE IF NOT EXISTS manager_dashboard_widget_settings (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,widget_key VARCHAR(80) NOT NULL UNIQUE,widget_title_fa VARCHAR(190) NOT NULL,widget_description_fa TEXT NULL,is_enabled TINYINT(1) NOT NULL DEFAULT 1,sort_order INT NOT NULL DEFAULT 0,show_in_dashboard TINYINT(1) NOT NULL DEFAULT 1,allow_import TINYINT(1) NOT NULL DEFAULT 1,allow_export TINYINT(1) NOT NULL DEFAULT 1,allow_image_export TINYINT(1) NOT NULL DEFAULT 1,allow_manual_edit TINYINT(1) NOT NULL DEFAULT 1,created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $tables[] = "CREATE TABLE IF NOT EXISTS manager_dashboard_ai_skills (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,skill_key VARCHAR(100) NOT NULL UNIQUE,skill_title_fa VARCHAR(190) NOT NULL,skill_description_fa TEXT NULL,skill_type VARCHAR(50) NOT NULL DEFAULT 'analysis',is_enabled TINYINT(1) NOT NULL DEFAULT 1,sort_order INT NOT NULL DEFAULT 0,system_prompt TEXT NULL,input_schema_json LONGTEXT NULL,output_schema_json LONGTEXT NULL,created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,INDEX idx_manager_ai_skills_enabled (is_enabled),INDEX idx_manager_ai_skills_sort (sort_order)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $tables[] = "CREATE TABLE IF NOT EXISTS manager_dashboard_ai_logs (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,report_id INT UNSIGNED NULL,user_id INT UNSIGNED NULL,skill_key VARCHAR(100) NULL,request_summary TEXT NULL,response_text LONGTEXT NULL,status VARCHAR(30) NOT NULL DEFAULT 'success',error_message TEXT NULL,created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,INDEX idx_manager_ai_logs_report (report_id),INDEX idx_manager_ai_logs_user (user_id),INDEX idx_manager_ai_logs_created (created_at),CONSTRAINT fk_manager_ai_logs_report FOREIGN KEY (report_id) REFERENCES manager_dashboard_reports(id) ON DELETE SET NULL,CONSTRAINT fk_manager_ai_logs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        return implode(";\n", $tables) . ';';
    }

    public static function latestReport(?int $id = null): ?array
    {
        if ($id) return Database::fetch('SELECT * FROM manager_dashboard_reports WHERE id=?', [$id]);
        return Database::fetch("SELECT * FROM manager_dashboard_reports WHERE import_status='success' ORDER BY is_default DESC, report_date DESC, id DESC LIMIT 1");
    }

    public static function cleanNumber($value): float
    {
        $value = JalaliDate::normalize(trim((string)$value));
        $value = strtr($value, ['٬'=>'','،'=>'',','=>'',' '=>'', '%'=>'']);
        return $value === '' ? 0 : (float)$value;
    }

    public static function normalizeRow(array $input, array $definition): array
    {
        $row = [];
        foreach ($definition['fields'] as [$key,,$type]) {
            $value = trim((string)($input[$key] ?? ''));
            if ($type === 'date') $value = JalaliDate::toGregorian($value) ?: $value;
            elseif (in_array($type, ['money','signed_money','number','signed_number','percent'], true)) $value = self::cleanNumber($value);
            elseif ($type === 'entity') $value = ['ویزیتور'=>'visitor','سرپرست'=>'supervisor','مدیر فروش'=>'manager'][$value] ?? $value;
            elseif ($type === 'status') $value = ['واجد شرایط'=>'ok'][$value] ?? $value;
            $row[$key] = $value;
        }
        self::calculate($definition['table'], $row, $input);
        return $row;
    }

    private static function calculate(string $table, array &$row, array $raw): void
    {
        if (trim((string)($raw['achievement_percent'] ?? '')) === '') {
            if (isset($row['sold_qty'], $row['target_qty'])) $row['achievement_percent'] = ManagerDashboardCalculator::calculateAchievement((float)$row['target_qty'], (float)$row['sold_qty']);
            elseif (isset($row['achieved_customer_count'], $row['customer_target_floor'])) $row['achievement_percent'] = ManagerDashboardCalculator::calculateAchievement((float)$row['customer_target_floor'], (float)$row['achieved_customer_count']);
            elseif (isset($row['achieved_brand_count'], $row['target_brand_count'])) $row['achievement_percent'] = ManagerDashboardCalculator::calculateAchievement((float)$row['target_brand_count'], (float)$row['achieved_brand_count']);
        }
        if (isset($row['commission_before_penalty']) && trim((string)($raw['commission_after_penalty'] ?? '')) === '') $row['commission_after_penalty'] = $row['commission_before_penalty'] * (1 - $row['penalty_percent'] / 100);
        if ($table === 'manager_customer_target_achievement' && trim((string)($raw['remaining_to_entry'] ?? '')) === '') $row['remaining_to_entry'] = ManagerDashboardCalculator::calculateRemainingToTarget((float)$row['customer_target_floor'], (float)$row['achieved_customer_count']);
        if ($table === 'manager_customer_coverage' && trim((string)($raw['remaining_to_target'] ?? '')) === '') $row['remaining_to_target'] = ManagerDashboardCalculator::calculateRemainingToTarget((float)$row['customer_floor'], (float)$row['coverage_count']);
    }

    public static function validate(array $row, array $definition): array
    {
        $errors = [];
        foreach ($definition['required'] as $key) if (trim((string)($row[$key] ?? '')) === '') $errors[] = $key . ' الزامی است.';
        foreach ($definition['fields'] as [$key,$label,$type]) {
            $v = $row[$key] ?? 0;
            if ($type === 'date' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$v)) $errors[] = $label . ' معتبر نیست.';
            if ($type === 'percent' && ($v < 0 || $v > 500)) $errors[] = $label . ' باید بین ۰ و ۵۰۰ باشد.';
            if (in_array($type, ['money','number'], true) && $v < 0) $errors[] = $label . ' نمی‌تواند منفی باشد.';
            if ($type === 'line' && !in_array($v, self::LINES, true)) $errors[] = $label . ' معتبر نیست.';
        }
        return $errors;
    }

    public static function saveRow(string $key, array $input, int $reportId, ?int $id = null): array
    {
        $def = self::definitions()[$key] ?? null;
        if (!$def) return ['جدول معتبر نیست.'];
        $row = self::normalizeRow($input, $def);
        $errors = self::validate($row, $def);
        if ($errors) return $errors;
        $fields = array_column($def['fields'], 0);
        if ($id) {
            $set = implode(',', array_map(fn($f) => "`{$f}`=?", $fields));
            Database::execute("UPDATE `{$def['table']}` SET {$set},updated_at=NOW() WHERE id=? AND report_id=?", array_merge(array_values($row), [$id,$reportId]));
        } else {
            $cols = implode(',', array_map(fn($f) => "`{$f}`", $fields));
            $marks = implode(',', array_fill(0, count($fields), '?'));
            Database::execute("INSERT INTO `{$def['table']}` (report_id,{$cols},created_at,updated_at) VALUES (?,{$marks},NOW(),NOW())", array_merge([$reportId], array_values($row)));
        }
        return [];
    }

    public static function sheets(?int $reportId = null, bool $template = false, ?string $only = null): array
    {
        $result = [];
        foreach (self::definitions() as $key => $def) {
            if ($only && $key !== $only) continue;
            $rows = [array_column($def['fields'], 1)];
            if (!$template && $reportId) {
                $fields = array_column($def['fields'], 0);
                foreach (Database::fetchAll('SELECT ' . implode(',', $fields) . " FROM `{$def['table']}` WHERE report_id=? ORDER BY id", [$reportId]) as $item) {
                    $out = [];
                    foreach ($def['fields'] as [$field,,$type]) {
                        $value = $item[$field];
                        if ($type === 'date') $value = format_jalali_date($value);
                        elseif ($type === 'percent') $value .= '%';
                        elseif ($type === 'entity') $value = ['visitor'=>'ویزیتور','supervisor'=>'سرپرست','manager'=>'مدیر فروش'][$value] ?? $value;
                        elseif ($type === 'status') $value = ['ok'=>'واجد شرایط'][$value] ?? $value;
                        $out[] = $value;
                    }
                    $rows[] = $out;
                }
            }
            $result[$def['sheet']] = $rows;
        }
        return $result;
    }

    public static function import(string $path, string $fileName, string $mode, int $userId, string $title, bool $strict = false): array
    {
        $book = CeoDashboardExcel::read($path);
        $summary = ['total'=>0,'imported'=>0,'updated'=>0,'skipped'=>0,'errors'=>[]];
        $all = [];
        $reportDate = '';
        foreach (self::definitions() as $key => $def) {
            $rows = $book[$def['sheet']] ?? [];
            if (!$rows) continue;
            $headers = array_map('trim', $rows[0]);
            $expected = array_column($def['fields'], 1);
            if ($headers !== $expected) { $summary['errors'][] = ['sheet'=>$def['sheet'],'row'=>1,'message'=>'نام یا ترتیب ستون‌ها با قالب معتبر یکسان نیست.']; continue; }
            foreach (array_slice($rows, 1) as $offset => $values) {
                if (!array_filter($values, fn($v) => trim((string)$v) !== '')) continue;
                $summary['total']++;
                $assoc = array_combine(array_column($def['fields'], 0), array_pad($values, count($expected), ''));
                $row = self::normalizeRow($assoc, $def);
                $errors = self::validate($row, $def);
                if ($errors) { $summary['errors'][] = ['sheet'=>$def['sheet'],'row'=>$offset+2,'message'=>implode(' ', $errors)]; $summary['skipped']++; continue; }
                $reportDate = $reportDate ?: $row['report_date'];
                $all[] = [$key,$def,$row,$assoc];
            }
        }
        if (!$all || ($strict && $summary['errors'])) return $summary;
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $existing = Database::fetch('SELECT * FROM manager_dashboard_reports WHERE report_date=? ORDER BY id DESC LIMIT 1', [$reportDate]);
            if ($mode === 'numeric' && !$existing) throw new RuntimeException('برای این تاریخ گزارش قبلی جهت به‌روزرسانی عددی پیدا نشد.');
            if ($mode === 'replace' && $existing) { $reportId = (int)$existing['id']; foreach (self::definitions() as $def) Database::execute("DELETE FROM `{$def['table']}` WHERE report_id=?", [$reportId]); }
            elseif ($mode === 'numeric' && $existing) $reportId = (int)$existing['id'];
            else { $jalaliParts=array_map('intval',explode('/',JalaliDate::toJalali($reportDate))); Database::execute('INSERT INTO manager_dashboard_reports (report_title,report_date,report_month,report_year,source_file_name,imported_by,import_status,created_at,updated_at) VALUES (?,?,?,?,?, ?,"processing",NOW(),NOW())', [$title ?: ('گزارش '.$reportDate),$reportDate,$jalaliParts[1]??0,$jalaliParts[0]??0,$fileName,$userId]); $reportId=(int)Database::lastInsertId(); }
            foreach ($all as [$key,$def,$row,$raw]) {
                $where=[];$params=[];
                foreach ($def['required'] as $field) { if ($field === 'report_date') continue; $where[]="`{$field}`=?";$params[]=$row[$field]; }
                $found = $where ? Database::fetch("SELECT id FROM `{$def['table']}` WHERE report_id=? AND ".implode(' AND ',$where).' LIMIT 1', array_merge([$reportId],$params)) : null;
                if ($mode === 'numeric' && $found) {
                    $numericFields = [];
                    foreach ($def['fields'] as [$field,,$type]) if (in_array($type, ['money','signed_money','number','signed_number','percent'], true)) $numericFields[$field] = $row[$field];
                    if ($numericFields) {
                        $set = implode(',', array_map(fn($field) => "`{$field}`=?", array_keys($numericFields)));
                        Database::execute("UPDATE `{$def['table']}` SET {$set},updated_at=NOW() WHERE id=? AND report_id=?", array_merge(array_values($numericFields), [(int)$found['id'],$reportId]));
                    }
                    $summary['updated']++;
                }
                elseif ($mode === 'numeric') { $summary['skipped']++; }
                else { self::saveRow($key,$row,$reportId); $summary['imported']++; }
            }
            Database::execute('UPDATE manager_dashboard_reports SET source_file_name=?,imported_by=?,import_status="success",updated_at=NOW() WHERE id=?', [$fileName,$userId,$reportId]);
            $pdo->commit(); $summary['report_id']=$reportId;
        } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); $summary['errors'][]=['sheet'=>'-','row'=>0,'message'=>$e->getMessage()]; }
        return $summary;
    }

    public static function defaultSkills(): array
    {
        $output = json_encode(['executive_summary'=>'string','strengths'=>[],'weaknesses'=>[],'risks'=>[],'visitor_actions'=>[],'line_actions'=>[],'data_warnings'=>[],'recommended_next_steps'=>[]], JSON_UNESCAPED_UNICODE);
        $input = json_encode(['module'=>'manager_dashboard','report_id'=>'integer','report_date'=>'date','business_rules'=>'object','summary'=>'object','tables'=>'object'], JSON_UNESCAPED_UNICODE);
        $rows = [
            ['calculate_achievement','محاسبه درصد تحقق','محاسبه درصد تحقق بر اساس تارگت، فروش، تعداد فروش یا مبلغ فروش.','calculation'],
            ['calculate_commission','محاسبه پورسانت','محاسبه پورسانت ویزیتور بر اساس فروش، درصد تحقق، ضرایب، خسارت مرجوعی و پاداش‌ها.','calculation'],
            ['calculate_penalty','محاسبه ضریب کاهنده','محاسبه جریمه و ضریب کاهنده بر اساس قوانین تحقق، لاین‌های زیر ۷۵٪ و وضعیت ویزیتور.','calculation'],
            ['calculate_customer_coverage','تحلیل پوشش مشتری','محاسبه پوشش مشتری، مانده تا هدف و پاداش یا جریمه پوشش.','calculation'],
            ['calculate_brand_target','تحلیل تحقق برند','محاسبه تحقق برندهای تارگت‌دار و وضعیت واجد شرایط بودن پاداش برند.','calculation'],
            ['analyze_visitor_performance','تحلیل عملکرد ویزیتور','تحلیل عملکرد هر ویزیتور از نظر فروش، تحقق، پورسانت، جریمه، پوشش مشتری و برند.','analysis'],
            ['analyze_line_performance','تحلیل عملکرد لاین','تحلیل وضعیت لاین‌های A، B، C و D و شناسایی لاین‌های پرریسک.','analysis'],
            ['analyze_supervisor_performance','تحلیل عملکرد سرپرست','تحلیل عملکرد سرپرستان بر اساس فروش، تحقق تیم، تعداد ویزیتورهای موفق و پاداش‌ها.','analysis'],
            ['detect_anomalies','شناسایی مغایرت و خطا','شناسایی داده‌های غیرعادی، درصدهای نامعتبر، فروش صفر، تارگت خالی و پورسانت غیرمنطقی.','validation'],
            ['generate_management_recommendations','پیشنهاد اقدام مدیریتی','تولید پیشنهادهای عملی برای مدیر فروش بر اساس داده‌های داشبورد.','recommendation'],
        ];
        $skills = [];
        foreach ($rows as $index => [$key,$title,$description,$type]) {
            $skills[] = ['key'=>$key,'title'=>$title,'description'=>$description,'type'=>$type,'sort_order'=>($index+1)*10,'prompt'=>'داده‌های محاسبه‌شده را تغییر نده. نتیجه را به فارسی، بدون کدبلاک و با پیشنهادهای عملی ارائه کن.','input_schema'=>$input,'output_schema'=>$output];
        }
        return $skills;
    }

    public static function aiSettings(): array
    {
        return [
            'ai_enabled' => self::setting('ai_enabled') === '1' ? 1 : 0,
            'ai_skills_enabled' => self::setting('ai_skills_enabled') === '1' ? 1 : 0,
            'ai_show_buttons' => self::setting('ai_show_buttons') === '1' ? 1 : 0,
            'ai_read_latest_report' => self::setting('ai_read_latest_report') === '1' ? 1 : 0,
            'ai_read_selected_report' => self::setting('ai_read_selected_report') === '1' ? 1 : 0,
            'ai_read_history' => self::setting('ai_read_history') === '1' ? 1 : 0,
            'sobhan_api_enabled' => setting('sobhan_api_enabled', '0') === '1' ? 1 : 0,
        ];
    }

    public static function enabledSkills(bool $respectDashboardSelection = true): array
    {
        if ($respectDashboardSelection && self::setting('ai_skills_enabled') !== '1') return [];
        $selected = $respectDashboardSelection ? array_filter(array_map('trim', explode(',', self::setting('ai_enabled_skills')))) : [];
        $sql = 'SELECT * FROM manager_dashboard_ai_skills WHERE is_enabled=1';
        $params = [];
        if ($selected) {
            $sql .= ' AND skill_key IN (' . implode(',', array_fill(0, count($selected), '?')) . ')';
            $params = array_values($selected);
        }
        return Database::fetchAll($sql . ' ORDER BY sort_order,id', $params);
    }

    public static function filteredRows(string $widgetKey, int $reportId, array $filters = [], int $limit = 0, int $offset = 0): array
    {
        $definition = self::definitions()[$widgetKey] ?? null;
        if (!$definition || $reportId < 1) return [];
        $columns = array_column($definition['fields'], 0);
        $where = ['report_id=?']; $params = [$reportId];
        $search = trim((string)($filters['search'] ?? ''));
        if ($search !== '') {
            $parts = [];
            foreach ($columns as $column) { $parts[] = "CAST(`{$column}` AS CHAR) LIKE ?"; $params[] = '%'.$search.'%'; }
            $where[] = '('.implode(' OR ', $parts).')';
        }
        foreach (['line_code','line_group','visitor_name','supervisor_name'] as $column) {
            $filterKey = match ($column) { 'visitor_name' => 'visitor', 'supervisor_name' => 'supervisor', default => 'line_code' };
            $value = trim((string)($filters[$filterKey] ?? ''));
            if ($value !== '' && in_array($column, $columns, true)) { $where[] = "`{$column}`=?"; $params[] = $value; }
        }
        $order = in_array($widgetKey, ['team_target_achievement','over_achievement_bonus'], true) ? "FIELD(entity_type,'visitor','supervisor','manager'),id DESC" : 'id DESC';
        $paging = $limit > 0 ? ' LIMIT '.max(1,$limit).' OFFSET '.max(0,$offset) : '';
        return Database::fetchAll("SELECT * FROM `{$definition['table']}` WHERE ".implode(' AND ',$where)." ORDER BY {$order}{$paging}", $params);
    }

    public static function callAi(array $context, ?array $settings = null): array
    {
        $settings = $settings ?: self::aiSettings();
        if (!(int)($settings['ai_enabled'] ?? 0) || setting('sobhan_api_enabled', '0') !== '1') return ['ok'=>false,'message'=>'ارتباط با سرویس هوش مصنوعی برقرار نشد.'];
        $json = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) return ['ok'=>false,'message'=>'ساخت داده تحلیل ناموفق بود.'];
        $payload = $context;
        $payload['question'] = ($context['instruction'] ?? 'داده‌های داشبورد مدیران را تحلیل کن.') . "\n" . $json;
        $result = (new SobhanApiClient())->post('/ai/ask', $payload);
        $answer = ai_answer_payload_from_result($result, (string)$payload['question'], '');
        return $result['ok'] && $answer['answer'] !== ''
            ? ['ok'=>true,'content'=>$answer['answer'],'knowledge_sources'=>$answer['knowledge_sources']]
            : ['ok'=>false,'message'=>$result['error']['message_fa'] ?? 'ارتباط با سرویس هوش مصنوعی برقرار نشد.'];
    }

}
