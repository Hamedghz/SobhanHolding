<?php

require_once __DIR__ . '/Database.php';

final class DailyWorkReportModule
{
    public const PERMISSIONS = [
        'daily_reports.view' => ['مشاهده گزارش کار روزانه', 787],
        'daily_reports.submit' => ['ثبت گزارش کار روزانه', 788],
        'daily_reports.view_team' => ['مشاهده گزارش کار تیم', 789],
        'daily_reports.manage_templates' => ['مدیریت قالب‌های گزارش کار', 790],
    ];

    public static function repair(PDO $pdo): void
    {
        foreach (self::schema() as $sql) $pdo->exec($sql);
        self::seedPermissions($pdo);
        self::seedDefaultTemplate($pdo);
        self::backfillLegacyReports($pdo);
    }

    public static function schema(): array
    {
        $engine = ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        return [
            "CREATE TABLE IF NOT EXISTS daily_report_templates (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                template_code VARCHAR(100) NOT NULL,
                title VARCHAR(190) NOT NULL,
                description TEXT NULL,
                version_no INT UNSIGNED NOT NULL DEFAULT 1,
                active TINYINT(1) NOT NULL DEFAULT 1,
                created_by INT UNSIGNED NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                UNIQUE KEY uq_daily_report_template_code(template_code),
                INDEX idx_daily_report_templates_active(active,title)
            ){$engine}",
            "CREATE TABLE IF NOT EXISTS daily_report_template_fields (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                template_id BIGINT UNSIGNED NOT NULL,
                field_key VARCHAR(100) NOT NULL,
                field_label VARCHAR(190) NOT NULL,
                input_type VARCHAR(40) NOT NULL DEFAULT 'long_text',
                source_type VARCHAR(40) NOT NULL DEFAULT 'manual',
                source_key VARCHAR(100) NULL,
                aggregation_key VARCHAR(30) NULL,
                formula_expression TEXT NULL,
                help_text TEXT NULL,
                placeholder VARCHAR(255) NULL,
                options_json LONGTEXT NULL,
                required TINYINT(1) NOT NULL DEFAULT 0,
                readonly TINYINT(1) NOT NULL DEFAULT 0,
                active TINYINT(1) NOT NULL DEFAULT 1,
                sort_order INT NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                UNIQUE KEY uq_daily_report_template_field(template_id,field_key),
                INDEX idx_daily_report_fields(template_id,active,sort_order)
            ){$engine}",
            "CREATE TABLE IF NOT EXISTS daily_report_template_assignments (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                template_id BIGINT UNSIGNED NOT NULL,
                scope_type VARCHAR(40) NOT NULL,
                scope_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
                scope_key VARCHAR(150) NOT NULL DEFAULT '',
                active TINYINT(1) NOT NULL DEFAULT 1,
                created_by INT UNSIGNED NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                UNIQUE KEY uq_daily_report_assignment(template_id,scope_type,scope_id,scope_key),
                INDEX idx_daily_report_assignment_scope(scope_type,scope_id,scope_key,active)
            ){$engine}",
            "CREATE TABLE IF NOT EXISTS daily_reports (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                template_id BIGINT UNSIGNED NOT NULL,
                template_version_no INT UNSIGNED NOT NULL DEFAULT 1,
                user_id INT UNSIGNED NOT NULL,
                report_date DATE NOT NULL,
                status VARCHAR(30) NOT NULL DEFAULT 'draft',
                submitted_at DATETIME NULL,
                created_by INT UNSIGNED NOT NULL,
                legacy_source_type VARCHAR(60) NULL,
                legacy_source_id BIGINT UNSIGNED NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                UNIQUE KEY uq_daily_report_user_date_template(user_id,report_date,template_id),
                UNIQUE KEY uq_daily_report_legacy(legacy_source_type,legacy_source_id),
                INDEX idx_daily_reports_user(user_id,report_date,status),
                INDEX idx_daily_reports_date(report_date,status)
            ){$engine}",
            "CREATE TABLE IF NOT EXISTS daily_report_values (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                report_id BIGINT UNSIGNED NOT NULL,
                field_id BIGINT UNSIGNED NULL,
                field_key VARCHAR(100) NOT NULL,
                field_label VARCHAR(190) NOT NULL,
                source_type VARCHAR(40) NOT NULL DEFAULT 'manual',
                value_text LONGTEXT NULL,
                value_number DECIMAL(20,4) NULL,
                value_date DATE NULL,
                display_text LONGTEXT NULL,
                readonly TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                UNIQUE KEY uq_daily_report_value(report_id,field_key),
                INDEX idx_daily_report_values(report_id,source_type)
            ){$engine}",
            "CREATE TABLE IF NOT EXISTS daily_report_links (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                report_id BIGINT UNSIGNED NOT NULL,
                field_id BIGINT UNSIGNED NULL,
                link_type VARCHAR(40) NOT NULL,
                linked_type VARCHAR(80) NOT NULL,
                linked_id BIGINT UNSIGNED NOT NULL,
                link_url VARCHAR(500) NULL,
                label VARCHAR(190) NULL,
                snapshot_text TEXT NULL,
                created_by INT UNSIGNED NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_daily_report_link(report_id,link_type,linked_type,linked_id),
                INDEX idx_daily_report_links_target(linked_type,linked_id),
                INDEX idx_daily_report_links_report(report_id,field_id)
            ){$engine}",
            "CREATE TABLE IF NOT EXISTS daily_report_logs (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                report_id BIGINT UNSIGNED NOT NULL,
                action_key VARCHAR(60) NOT NULL,
                note TEXT NULL,
                performed_by INT UNSIGNED NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_daily_report_logs(report_id,created_at),
                INDEX idx_daily_report_logs_actor(performed_by,created_at)
            ){$engine}",
        ];
    }

    private static function seedPermissions(PDO $pdo): void
    {
        if (!self::tableExists($pdo, 'modules')) return;
        $stmt = $pdo->prepare(
            'INSERT INTO modules(module_key,module_title,sort_order,status,created_at)
             VALUES (?,?,?,"active",NOW())
             ON DUPLICATE KEY UPDATE module_title=VALUES(module_title),sort_order=VALUES(sort_order),status="active"'
        );
        foreach (self::PERMISSIONS as $key => [$title,$sort]) $stmt->execute([$key,$title,$sort]);
    }

    private static function seedDefaultTemplate(PDO $pdo): void
    {
        $pdo->exec(
            "INSERT INTO daily_report_templates(template_code,title,description,version_no,active,created_at,updated_at)
             VALUES ('daily_general','گزارش کار روزانه','قالب عمومی برای ثبت فعالیت‌ها، موانع، پیشنهادات و برنامه روز بعد.',1,1,NOW(),NOW())
             ON DUPLICATE KEY UPDATE title=VALUES(title),description=VALUES(description),active=1,updated_at=NOW()"
        );
        $templateId = (int)$pdo->query("SELECT id FROM daily_report_templates WHERE template_code='daily_general' LIMIT 1")->fetchColumn();
        if (!$templateId) return;
        $fields = [
            ['completed_actions','اقدامات انجام‌شده امروز','readonly_list','assigned_actions','done_today',null,null,'اقدام‌های تکمیل‌شده امروز از مرکز اقدامات.',0,1,10],
            ['open_actions','اقدامات باز','readonly_list','assigned_actions','open',null,null,'اقدام‌های باز محول‌شده به کاربر.',0,1,20],
            ['completed_tasks','تسک‌های تکمیل‌شده','readonly_list','completed_tasks','today',null,null,'وظایف تکمیل‌شده امروز از پلنر.',0,1,30],
            ['attendance_today','حضور و کارکرد امروز','readonly','attendance','today',null,null,'خلاصه کارکرد ثبت‌شده روز.',0,1,40],
            ['kpi_value','وضعیت KPI','readonly','kpi_values','latest',null,null,'میانگین آخرین دوره KPI قابل دسترس.',0,1,50],
            ['import_activity','فعالیت ورود اطلاعات','readonly','imported_data','user_batches',null,null,'Batchهای ثبت‌شده توسط کاربر در روز گزارش.',0,1,60],
            ['blockers','مشکلات و موانع','long_text','manual',null,null,null,'موانع، وابستگی‌ها و موارد نیازمند تصمیم را بنویسید.',0,0,70],
            ['suggestions','اقدامات و پیشنهادات','long_text','manual',null,null,null,'پیشنهاد یا اقدام لازم را ثبت کنید؛ برای واگذاری از سازنده اقدام استفاده کنید.',0,0,80],
            ['tomorrow_plan','برنامه فردا','long_text','manual',null,null,null,'اولویت‌های روز بعد را ثبت کنید.',1,0,90],
            ['daily_progress','جمع پیشرفت روز','readonly','calculated',null,null,'{completed_actions}+{completed_tasks}','جمع تعداد اقدامات و وظایف تکمیل‌شده.',0,1,100],
        ];
        $stmt = $pdo->prepare(
            'INSERT INTO daily_report_template_fields
             (template_id,field_key,field_label,input_type,source_type,source_key,aggregation_key,formula_expression,help_text,required,readonly,sort_order,active,created_at,updated_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,1,NOW(),NOW())
             ON DUPLICATE KEY UPDATE field_label=VALUES(field_label),input_type=VALUES(input_type),source_type=VALUES(source_type),
             source_key=VALUES(source_key),aggregation_key=VALUES(aggregation_key),formula_expression=VALUES(formula_expression),
             help_text=VALUES(help_text),required=VALUES(required),readonly=VALUES(readonly),sort_order=VALUES(sort_order),active=1,updated_at=NOW()'
        );
        foreach ($fields as $field) $stmt->execute(array_merge([$templateId], $field));
        $assign = $pdo->prepare(
            'INSERT INTO daily_report_template_assignments(template_id,scope_type,scope_id,scope_key,active,created_at,updated_at)
             VALUES (?,"company",0,"",1,NOW(),NOW())
             ON DUPLICATE KEY UPDATE active=1,updated_at=NOW()'
        );
        $assign->execute([$templateId]);
    }

    private static function backfillLegacyReports(PDO $pdo): void
    {
        if (!self::tableExists($pdo, 'sales_manager_daily_work_logs')) return;
        $templateId = (int)$pdo->query("SELECT id FROM daily_report_templates WHERE template_code='daily_general' LIMIT 1")->fetchColumn();
        if (!$templateId) return;
        $labels = [
            'time_spent'=>'موضوعات و زمان صرف‌شده',
            'reports_reviewed'=>'گزارش‌های بررسی‌شده',
            'supervisors_reviewed'=>'سرپرستان بررسی‌شده',
            'visitors_followup'=>'پیگیری ویزیتورها',
            'key_customers'=>'مشتریان کلیدی',
            'market_problems'=>'مشکلات بازار',
            'actions_defined'=>'اقدامات تعریف‌شده',
            'purchase_suggestions'=>'پیشنهادات خرید',
            'management_decisions'=>'موارد نیازمند تصمیم مدیریت',
            'tomorrow_plan'=>'برنامه فردا',
        ];
        $rows = $pdo->query(
            "SELECT legacy.* FROM sales_manager_daily_work_logs legacy
             LEFT JOIN daily_reports r ON r.legacy_source_type='sales_manager_daily_work_logs' AND r.legacy_source_id=legacy.id
             WHERE r.id IS NULL ORDER BY legacy.id"
        )->fetchAll(PDO::FETCH_ASSOC);
        $insertReport = $pdo->prepare(
            'INSERT IGNORE INTO daily_reports(template_id,template_version_no,user_id,report_date,status,submitted_at,created_by,
             legacy_source_type,legacy_source_id,created_at,updated_at)
             VALUES (?,1,?,?,"submitted",COALESCE(?,NOW()),?,"sales_manager_daily_work_logs",?,COALESCE(?,NOW()),?)'
        );
        $insertValue = $pdo->prepare(
            'INSERT INTO daily_report_values(report_id,field_id,field_key,field_label,source_type,value_text,display_text,readonly,created_at,updated_at)
             VALUES (?,NULL,?,?, "manual",?,?,0,NOW(),NOW())
             ON DUPLICATE KEY UPDATE value_text=VALUES(value_text),display_text=VALUES(display_text),updated_at=NOW()'
        );
        foreach ($rows as $row) {
            $insertReport->execute([
                $templateId,(int)$row['sales_manager_id'],$row['log_date'],$row['updated_at'] ?? null,
                (int)($row['created_by'] ?: $row['sales_manager_id']),(int)$row['id'],$row['created_at'] ?? null,$row['updated_at'] ?? null,
            ]);
            $reportId = (int)$pdo->lastInsertId();
            if (!$reportId) {
                $find = $pdo->prepare("SELECT id FROM daily_reports WHERE legacy_source_type='sales_manager_daily_work_logs' AND legacy_source_id=?");
                $find->execute([(int)$row['id']]);
                $reportId = (int)$find->fetchColumn();
            }
            $values = json_decode((string)($row['fields_json'] ?? ''), true);
            if (!$reportId || !is_array($values)) continue;
            foreach ($values as $key => $value) {
                if (!is_scalar($value) || trim((string)$value) === '') continue;
                $label = $labels[(string)$key] ?? (string)$key;
                $insertValue->execute([$reportId,(string)$key,$label,(string)$value,(string)$value]);
            }
        }
    }

    private static function tableExists(PDO $pdo, string $table): bool
    {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
        $stmt->execute([$table]);
        return (int)$stmt->fetchColumn() > 0;
    }
}
