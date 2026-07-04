<?php

class ManagementReportsModule
{
    public static function repair(PDO $pdo): void
    {
        $engine = ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        $schema = [
            "CREATE TABLE IF NOT EXISTS management_report_templates (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                report_type VARCHAR(40) NOT NULL,
                title VARCHAR(190) NOT NULL,
                description TEXT NULL,
                active TINYINT(1) NOT NULL DEFAULT 1,
                created_by INT UNSIGNED NULL,
                updated_by INT UNSIGNED NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_management_report_template_type(report_type),
                INDEX idx_management_report_templates_active(active),
                CONSTRAINT fk_management_report_templates_creator FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
                CONSTRAINT fk_management_report_templates_updater FOREIGN KEY(updated_by) REFERENCES users(id) ON DELETE SET NULL
            ){$engine}",
            "CREATE TABLE IF NOT EXISTS management_report_sections (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                template_id INT UNSIGNED NOT NULL,
                section_key VARCHAR(100) NOT NULL,
                title VARCHAR(190) NOT NULL,
                description TEXT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_management_report_section_key(template_id,section_key),
                INDEX idx_management_report_sections_template(template_id,active,sort_order),
                CONSTRAINT fk_management_report_sections_template FOREIGN KEY(template_id) REFERENCES management_report_templates(id) ON DELETE CASCADE
            ){$engine}",
            "CREATE TABLE IF NOT EXISTS management_report_fields (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                section_id INT UNSIGNED NOT NULL,
                field_key VARCHAR(100) NOT NULL,
                label VARCHAR(190) NOT NULL,
                field_type ENUM('text','textarea','number','currency','percent','date','select','checkbox','table','repeater','file','readonly_metric') NOT NULL DEFAULT 'text',
                placeholder VARCHAR(255) NULL,
                help_text TEXT NULL,
                options_json LONGTEXT NULL,
                validation_json LONGTEXT NULL,
                default_value LONGTEXT NULL,
                linked_source_key VARCHAR(190) NULL,
                is_required TINYINT(1) NOT NULL DEFAULT 0,
                sort_order INT NOT NULL DEFAULT 0,
                active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_management_report_field_key(section_id,field_key),
                INDEX idx_management_report_fields_section(section_id,active,sort_order),
                INDEX idx_management_report_fields_linked(linked_source_key),
                CONSTRAINT fk_management_report_fields_section FOREIGN KEY(section_id) REFERENCES management_report_sections(id) ON DELETE CASCADE
            ){$engine}",
            "CREATE TABLE IF NOT EXISTS management_report_submissions (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                template_id INT UNSIGNED NOT NULL,
                report_type VARCHAR(40) NOT NULL,
                period_key VARCHAR(80) NOT NULL,
                period_title VARCHAR(190) NOT NULL,
                period_start DATE NULL,
                period_end DATE NULL,
                submitter_id INT UNSIGNED NOT NULL,
                unit_id INT UNSIGNED NULL,
                status ENUM('draft','submitted','returned','approved','archived') NOT NULL DEFAULT 'draft',
                submitted_at DATETIME NULL,
                returned_at DATETIME NULL,
                approved_at DATETIME NULL,
                approved_by INT UNSIGNED NULL,
                archived_at DATETIME NULL,
                return_note TEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_management_report_period_user(template_id,period_key,submitter_id),
                INDEX idx_management_report_submissions_type(report_type),
                INDEX idx_management_report_submissions_period(period_key),
                INDEX idx_management_report_submissions_status(status),
                INDEX idx_management_report_submissions_submitter(submitter_id),
                INDEX idx_management_report_submissions_unit(unit_id),
                CONSTRAINT fk_management_report_submissions_template FOREIGN KEY(template_id) REFERENCES management_report_templates(id) ON DELETE RESTRICT,
                CONSTRAINT fk_management_report_submissions_submitter FOREIGN KEY(submitter_id) REFERENCES users(id) ON DELETE RESTRICT,
                CONSTRAINT fk_management_report_submissions_unit FOREIGN KEY(unit_id) REFERENCES org_units(id) ON DELETE SET NULL,
                CONSTRAINT fk_management_report_submissions_approver FOREIGN KEY(approved_by) REFERENCES users(id) ON DELETE SET NULL
            ){$engine}",
            "CREATE TABLE IF NOT EXISTS management_report_values (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                submission_id BIGINT UNSIGNED NOT NULL,
                field_id INT UNSIGNED NOT NULL,
                value_text LONGTEXT NULL,
                value_number DECIMAL(20,4) NULL,
                value_json LONGTEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_management_report_value(submission_id,field_id),
                INDEX idx_management_report_values_submission(submission_id),
                CONSTRAINT fk_management_report_values_submission FOREIGN KEY(submission_id) REFERENCES management_report_submissions(id) ON DELETE CASCADE,
                CONSTRAINT fk_management_report_values_field FOREIGN KEY(field_id) REFERENCES management_report_fields(id) ON DELETE RESTRICT
            ){$engine}",
            "CREATE TABLE IF NOT EXISTS management_report_attachments (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                submission_id BIGINT UNSIGNED NOT NULL,
                field_id INT UNSIGNED NULL,
                original_name VARCHAR(255) NOT NULL,
                storage_path VARCHAR(500) NOT NULL,
                mime_type VARCHAR(190) NOT NULL,
                file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
                created_by INT UNSIGNED NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_management_report_attachments_submission(submission_id),
                CONSTRAINT fk_management_report_attachments_submission FOREIGN KEY(submission_id) REFERENCES management_report_submissions(id) ON DELETE CASCADE,
                CONSTRAINT fk_management_report_attachments_field FOREIGN KEY(field_id) REFERENCES management_report_fields(id) ON DELETE SET NULL,
                CONSTRAINT fk_management_report_attachments_user FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL
            ){$engine}",
            "CREATE TABLE IF NOT EXISTS management_report_reviews (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                submission_id BIGINT UNSIGNED NOT NULL,
                action ENUM('created','draft_saved','submitted','returned','approved','archived','reopened') NOT NULL,
                old_status VARCHAR(30) NULL,
                new_status VARCHAR(30) NULL,
                note TEXT NULL,
                created_by INT UNSIGNED NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_management_report_reviews_submission(submission_id,created_at),
                CONSTRAINT fk_management_report_reviews_submission FOREIGN KEY(submission_id) REFERENCES management_report_submissions(id) ON DELETE CASCADE,
                CONSTRAINT fk_management_report_reviews_user FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL
            ){$engine}",
        ];
        foreach ($schema as $sql) $pdo->exec($sql);

        if (Database::tableExists('management_report_fields') && !Database::columnExists('management_report_fields', 'linked_source_key')) {
            $pdo->exec('ALTER TABLE management_report_fields ADD linked_source_key VARCHAR(190) NULL AFTER default_value');
        }

        $modules = [
            ['management_reports.sales', 'آماده‌سازی گزارش مدیران فروش'],
            ['management_reports.finance', 'آماده‌سازی گزارش مدیر مالی'],
            ['management_reports.warehouse', 'آماده‌سازی گزارش مدیر انبار'],
            ['management_reports.technology', 'آماده‌سازی گزارش مدیر فناوری'],
            ['management_reports.review', 'بررسی و تأیید گزارشات مدیران'],
            ['management_reports.aggregate', 'مشاهده گزارشات تجمیعی مدیران'],
            ['management_reports.templates', 'تنظیمات قالب گزارشات مدیران'],
        ];
        $moduleStmt = $pdo->prepare("INSERT INTO modules(module_key,module_title,sort_order,status,created_at) VALUES(?,?,68,'active',NOW()) ON DUPLICATE KEY UPDATE module_title=VALUES(module_title)");
        foreach ($modules as $module) $moduleStmt->execute($module);

        self::seedTemplates($pdo);
    }

    private static function seedTemplates(PDO $pdo): void
    {
        $templates = [
            'sales' => ['گزارش مدیران فروش', 'گزارش تحلیلی عملکرد، هدف‌گذاری و برنامه اقدام مدیر فروش'],
            'finance' => ['گزارش مدیر مالی', 'گزارش دوره‌ای وضعیت مالی، مطالبات و جریان نقدی'],
            'warehouse' => ['گزارش مدیر انبار', 'گزارش دوره‌ای موجودی، دقت انبار و آسیب کالا'],
            'technology' => ['گزارش مدیر فناوری', 'گزارش دوره‌ای زیرساخت، پشتیبان‌گیری و خدمات فناوری'],
        ];
        $templateStmt = $pdo->prepare('INSERT IGNORE INTO management_report_templates(report_type,title,description,active,created_at,updated_at) VALUES(?,?,?,1,NOW(),NOW())');
        foreach ($templates as $type => $meta) $templateStmt->execute([$type,$meta[0],$meta[1]]);

        $salesSections = [
            ['performance_analysis','تحلیل عملکرد مدیر فروش'],
            ['brand_sku_focus','تحلیل تمرکز فروش، برند و SKU'],
            ['proposed_strategies','استراتژی‌های فروش پیشنهادی'],
            ['next_month_summary','جمع‌بندی عملکرد و برنامه ماه آینده'],
            ['target_deviation','تحلیل انحراف از تارگت'],
            ['execution_planning','قالب تحلیل، هدف‌گذاری و برنامه اجرایی'],
            ['next_month_action','استراتژی ماه آینده / برنامه اقدام'],
        ];
        self::seedSections($pdo, 'sales', $salesSections, true);
        self::seedSections($pdo, 'finance', [['financial_overview','خلاصه وضعیت مالی'],['receivables','مطالبات و جریان نقدی']], false);
        self::seedSections($pdo, 'warehouse', [['inventory_overview','وضعیت موجودی و عملیات انبار'],['warehouse_risks','ریسک‌ها و اقدامات اصلاحی']], false);
        self::seedSections($pdo, 'technology', [['infrastructure_status','وضعیت زیرساخت و سرویس‌ها'],['technology_actions','ریسک‌ها و برنامه اقدام فناوری']], false);
    }

    private static function seedSections(PDO $pdo, string $type, array $sections, bool $sales): void
    {
        $templateId = (int)$pdo->query('SELECT id FROM management_report_templates WHERE report_type='.$pdo->quote($type).' LIMIT 1')->fetchColumn();
        if (!$templateId) return;
        $sectionStmt = $pdo->prepare('INSERT IGNORE INTO management_report_sections(template_id,section_key,title,sort_order,active,created_at,updated_at) VALUES(?,?,?,?,1,NOW(),NOW())');
        $fieldStmt = $pdo->prepare('INSERT IGNORE INTO management_report_fields(section_id,field_key,label,field_type,placeholder,help_text,options_json,linked_source_key,is_required,sort_order,active,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,1,NOW(),NOW())');
        foreach ($sections as $index => $section) {
            $sectionStmt->execute([$templateId,$section[0],$section[1],($index+1)*10]);
            $find = $pdo->prepare('SELECT id FROM management_report_sections WHERE template_id=? AND section_key=?');$find->execute([$templateId,$section[0]]);$sectionId=(int)$find->fetchColumn();
            if (!$sectionId) continue;
            $fieldStmt->execute([$sectionId,$section[0].'_summary','شرح و تحلیل','textarea','تحلیل این بخش را بنویسید.','این فیلد در تنظیمات قالب قابل تغییر است.',null,null,1,10]);
            $fieldStmt->execute([$sectionId,$section[0].'_actions','اقدامات و پیشنهادها','repeater',null,'برای هر اقدام یک ردیف اضافه کنید.',json_encode(['columns'=>[['key'=>'action','label'=>'اقدام'],['key'=>'owner','label'=>'مسئول'],['key'=>'due','label'=>'مهلت']]],JSON_UNESCAPED_UNICODE),null,0,20]);
        }
        if ($sales) {
            $first=(int)$pdo->query("SELECT id FROM management_report_sections WHERE template_id={$templateId} ORDER BY sort_order,id LIMIT 1")->fetchColumn();
            if($first){
                $fieldStmt->execute([$first,'sales_gross_amount','فروش ناخالص','readonly_metric',null,'آماده اتصال به منبع داده فروش.',null,'sales_gross_amount',0,1]);
                $fieldStmt->execute([$first,'sales_net_amount','فروش خالص','readonly_metric',null,'آماده اتصال به منبع داده فروش.',null,'sales_net_amount',0,2]);
                $fieldStmt->execute([$first,'target_achievement_percent','درصد تحقق تارگت','percent','برای مثال ۸۵',null,null,'target_achievement_percent',0,3]);
                $fieldStmt->execute([$first,'sales_what','چه اتفاقی افتاد؟ (What)','textarea','نتیجه و واقعیت قابل سنجش را بنویسید.','خلاصه روشن وضعیت یا تغییر اصلی.',null,null,1,30]);
                $fieldStmt->execute([$first,'sales_why','چرا اتفاق افتاد؟ (Why)','textarea','علت‌ها و شواهد را بنویسید.','علت ریشه‌ای را از حدس جدا کنید.',null,null,1,40]);
                $fieldStmt->execute([$first,'sales_action','اقدام بعدی چیست؟ (Action)','repeater',null,'اقدام، مسئول و مهلت را ثبت کنید.',json_encode(['columns'=>[['key'=>'action','label'=>'اقدام'],['key'=>'owner','label'=>'مسئول'],['key'=>'due','label'=>'مهلت']]],JSON_UNESCAPED_UNICODE),null,1,50]);
            }
        } else {
            $first=(int)$pdo->query("SELECT id FROM management_report_sections WHERE template_id={$templateId} ORDER BY sort_order,id LIMIT 1")->fetchColumn();
            $metrics=[
                'finance'=>[['finance_receivables_amount','مبلغ مطالبات','currency']],
                'warehouse'=>[['inventory_accuracy','دقت موجودی','percent'],['warehouse_damage_rate','نرخ آسیب انبار','percent']],
                'technology'=>[['it_backup_status','وضعیت پشتیبان‌گیری فناوری','text']],
            ];
            foreach($metrics[$type]??[] as $index=>$metric)if($first)$fieldStmt->execute([$first,$metric[0],$metric[1],'readonly_metric',null,'آماده اتصال به منبع داده سیستمی.',null,$metric[0],0,$index+1]);
        }
    }
}
