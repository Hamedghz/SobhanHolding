<?php

class SmsModule
{
    public static function repair(PDO $pdo): void
    {
        $engine = ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        $statements = [
            "CREATE TABLE IF NOT EXISTS sms_settings (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,provider_name VARCHAR(100) NOT NULL DEFAULT 'bazyabpayam',wsdl_url VARCHAR(255) NOT NULL,url_api_base VARCHAR(255) NULL,username VARCHAR(100) NOT NULL,password_encrypted LONGTEXT NOT NULL,default_sender VARCHAR(50) NOT NULL,is_active TINYINT(1) NOT NULL DEFAULT 1,created_by INT UNSIGNED NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,INDEX idx_sms_settings_active(is_active),CONSTRAINT fk_sms_settings_creator FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL){$engine}",
            "CREATE TABLE IF NOT EXISTS sms_messages (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,provider_name VARCHAR(100) NOT NULL,sender VARCHAR(50) NOT NULL,message_body TEXT NOT NULL,recipients_count INT UNSIGNED NOT NULL DEFAULT 0,bulk_code VARCHAR(100) NULL,status VARCHAR(50) NOT NULL DEFAULT 'queued',source_module VARCHAR(100) NULL,source_id BIGINT UNSIGNED NULL,created_by INT UNSIGNED NULL,sent_at DATETIME NULL,last_checked_at DATETIME NULL,error_code VARCHAR(50) NULL,error_message TEXT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,INDEX idx_sms_messages_status(status),INDEX idx_sms_messages_bulk(bulk_code),INDEX idx_sms_messages_source(source_module,source_id),CONSTRAINT fk_sms_messages_creator FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL){$engine}",
            "CREATE TABLE IF NOT EXISTS sms_message_recipients (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,message_id BIGINT UNSIGNED NOT NULL,mobile VARCHAR(20) NOT NULL,normalized_mobile VARCHAR(20) NOT NULL,delivery_status VARCHAR(50) NULL,provider_message_id VARCHAR(100) NULL,checked_at DATETIME NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,INDEX idx_sms_recipient_message(message_id),INDEX idx_sms_recipient_mobile(normalized_mobile),CONSTRAINT fk_sms_recipient_message FOREIGN KEY(message_id) REFERENCES sms_messages(id) ON DELETE CASCADE){$engine}",
        ];
        foreach ($statements as $statement) $pdo->exec($statement);
        $module = $pdo->prepare("INSERT INTO modules(module_key,module_title,sort_order,status,created_at) VALUES(?,?,74,'active',NOW()) ON DUPLICATE KEY UPDATE module_title=VALUES(module_title),status='active'");
        $module->execute(['sms.manage', 'مدیریت سامانه پیامکی']);
    }
}
