<?php

class SmsModule
{
    public static function repair(PDO $pdo): void
    {
        $e=' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        $sql=[
            "CREATE TABLE IF NOT EXISTS sms_settings (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,provider_name VARCHAR(100) NOT NULL DEFAULT 'bazyabpayam',wsdl_url VARCHAR(255) NOT NULL,url_api_base VARCHAR(255) NULL,username VARCHAR(100) NOT NULL,password_encrypted LONGTEXT NOT NULL,default_sender VARCHAR(50) NOT NULL,is_active TINYINT(1) NOT NULL DEFAULT 1,last_credit VARCHAR(100) NULL,last_credit_checked_at DATETIME NULL,last_test_status VARCHAR(50) NULL,last_test_message VARCHAR(255) NULL,created_by INT UNSIGNED NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,INDEX idx_sms_settings_active(is_active),CONSTRAINT fk_sms_settings_creator FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL){$e}",
            "CREATE TABLE IF NOT EXISTS sms_templates (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,code VARCHAR(100) NOT NULL UNIQUE,title VARCHAR(200) NOT NULL,body TEXT NOT NULL,module_key VARCHAR(100) NULL,event_key VARCHAR(100) NULL,is_active TINYINT(1) NOT NULL DEFAULT 1,created_by INT UNSIGNED NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,INDEX idx_sms_template_module(module_key,event_key),CONSTRAINT fk_sms_template_creator FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL){$e}",
            "CREATE TABLE IF NOT EXISTS sms_messages (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,provider_name VARCHAR(100) NOT NULL DEFAULT 'bazyabpayam',sender VARCHAR(50) NOT NULL,message_body TEXT NOT NULL,message_hash VARCHAR(64) NULL,recipients_count INT UNSIGNED NOT NULL DEFAULT 0,valid_recipients_count INT UNSIGNED NOT NULL DEFAULT 0,invalid_recipients_count INT UNSIGNED NOT NULL DEFAULT 0,bulk_code VARCHAR(100) NULL,status VARCHAR(50) NOT NULL DEFAULT 'queued',source_module VARCHAR(100) NULL,source_id BIGINT UNSIGNED NULL,created_by INT UNSIGNED NULL,sent_at DATETIME NULL,last_checked_at DATETIME NULL,error_code VARCHAR(50) NULL,error_message TEXT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,INDEX idx_sms_messages_status(status),INDEX idx_sms_messages_bulk(bulk_code),INDEX idx_sms_messages_source(source_module,source_id),CONSTRAINT fk_sms_messages_creator FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL){$e}",
            "CREATE TABLE IF NOT EXISTS sms_message_recipients (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,message_id BIGINT UNSIGNED NOT NULL,mobile VARCHAR(20) NOT NULL,normalized_mobile VARCHAR(20) NOT NULL,delivery_status VARCHAR(50) NULL,provider_message_id VARCHAR(100) NULL,checked_at DATETIME NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,INDEX idx_sms_recipient_message(message_id),INDEX idx_sms_recipient_mobile(normalized_mobile),INDEX idx_sms_delivery_status(delivery_status),CONSTRAINT fk_sms_recipient_message FOREIGN KEY(message_id) REFERENCES sms_messages(id) ON DELETE CASCADE){$e}",
            "CREATE TABLE IF NOT EXISTS sms_gateway_logs (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,level VARCHAR(30) NOT NULL DEFAULT 'error',error_code VARCHAR(100) NULL,safe_message VARCHAR(255) NOT NULL,provider_raw_masked TEXT NULL,context_json TEXT NULL,created_by INT UNSIGNED NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,INDEX idx_sms_log_code(error_code),INDEX idx_sms_log_created(created_at),CONSTRAINT fk_sms_log_creator FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL){$e}",
        ];foreach($sql as $statement)$pdo->exec($statement);
        $columns=[
            'sms_settings'=>['last_credit'=>'VARCHAR(100) NULL','last_credit_checked_at'=>'DATETIME NULL','last_test_status'=>'VARCHAR(50) NULL','last_test_message'=>'VARCHAR(255) NULL'],
            'sms_messages'=>['message_hash'=>'VARCHAR(64) NULL','valid_recipients_count'=>'INT UNSIGNED NOT NULL DEFAULT 0','invalid_recipients_count'=>'INT UNSIGNED NOT NULL DEFAULT 0'],
            'sms_templates'=>['event_key'=>'VARCHAR(100) NULL'],
        ];foreach($columns as $table=>$items)foreach($items as $column=>$definition)if(!self::columnExists($pdo,$table,$column))$pdo->exec("ALTER TABLE `{$table}` ADD `{$column}` {$definition}");
        $index=(int)$pdo->query("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sms_message_recipients' AND INDEX_NAME='idx_sms_delivery_status'")->fetchColumn();if(!$index)$pdo->exec('ALTER TABLE sms_message_recipients ADD INDEX idx_sms_delivery_status(delivery_status)');
        $modules=['sms_settings_manage'=>'مدیریت تنظیمات پیامک','sms_template_manage'=>'مدیریت قالب‌های پیامک','sms_send'=>'ارسال پیامک','sms_report_view'=>'مشاهده گزارش پیامک','sms_delivery_sync'=>'همگام‌سازی تحویل پیامک'];$stmt=$pdo->prepare("INSERT INTO modules(module_key,module_title,sort_order,status,created_at) VALUES(?,?,74,'active',NOW()) ON DUPLICATE KEY UPDATE module_title=VALUES(module_title),status='active'");foreach($modules as $key=>$title)$stmt->execute([$key,$title]);
        self::seedTemplates($pdo);
    }

    public static function seedTemplates(PDO $pdo,?int $actorId=null): int
    {
        $templates=[
            ['request_created','ثبت درخواست','requests','created','درخواست شما با عنوان «{request_title}» ثبت شد.'."\n".'کد پیگیری: {ticket_code}'."\n".'شرکت سبحان'],
            ['request_updated','بروزرسانی درخواست','requests','updated','وضعیت درخواست «{request_title}» به «{status}» تغییر کرد.'."\n".'شرکت سبحان'],
            ['ticket_assigned','ارجاع تیکت','ticketing','assigned','یک تیکت جدید با کد {ticket_code} به شما ارجاع شد.'."\n".'لطفاً پنل سبحان را بررسی کنید.'],
            ['task_reminder','یادآوری وظیفه','planner','reminder','یادآوری وظیفه: {task_title}'."\n".'تاریخ: {date}'."\n".'سامانه سبحان'],
            ['hr_assessment_assigned','تخصیص آزمون سازمانی','hr_assessment','assigned','{employee_name} عزیز، یک آزمون سازمانی برای شما فعال شده است.'."\n".'لطفاً از پنل کاربری سبحان اقدام کنید.'],
            ['system_alert','هشدار سیستمی','system','alert','هشدار سامانه سبحان:'."\n".'{status}'."\n".'زمان: {date} {time}'],
        ];$stmt=$pdo->prepare('INSERT INTO sms_templates(code,title,module_key,event_key,body,is_active,created_by,created_at,updated_at) VALUES(?,?,?,?,?,1,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE title=VALUES(title),module_key=VALUES(module_key),event_key=VALUES(event_key)');foreach($templates as $row)$stmt->execute([$row[0],$row[1],$row[2],$row[3],$row[4],$actorId]);return count($templates);
    }

    private static function columnExists(PDO $pdo,string $table,string $column): bool
    {
        $stmt=$pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');$stmt->execute([$table,$column]);return (int)$stmt->fetchColumn()>0;
    }
}
