<?php

class FileBackupModule
{
    public static function repair(PDO $pdo): void
    {
        $engine=' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        $pdo->exec("CREATE TABLE IF NOT EXISTS uploaded_files_backup (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            file_key CHAR(64) NOT NULL,
            original_name VARCHAR(255) NOT NULL,
            relative_path VARCHAR(500) NOT NULL,
            file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
            file_hash CHAR(64) NULL,
            mime_type VARCHAR(190) NOT NULL DEFAULT 'application/octet-stream',
            backup_status ENUM('pending','synced','error') NOT NULL DEFAULT 'pending',
            backup_confirmed_at DATETIME NULL,
            deleted_from_host TINYINT(1) NOT NULL DEFAULT 0,
            deleted_from_host_at DATETIME NULL,
            last_error TEXT NULL,
            download_attempts INT UNSIGNED NOT NULL DEFAULT 0,
            last_attempt_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_uploaded_files_backup_key(file_key),
            UNIQUE KEY uq_uploaded_files_backup_path(relative_path),
            INDEX idx_uploaded_files_backup_queue(backup_status,deleted_from_host,created_at)
        ){$engine}");
        $pdo->exec("CREATE TABLE IF NOT EXISTS uploaded_files_backup_logs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            file_id BIGINT UNSIGNED NULL,
            action VARCHAR(50) NOT NULL,
            status VARCHAR(30) NOT NULL,
            message TEXT NULL,
            actor_type VARCHAR(30) NOT NULL DEFAULT 'system',
            actor_user_id INT UNSIGNED NULL,
            ip_address VARCHAR(45) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_uploaded_files_backup_logs_file(file_id,created_at),
            INDEX idx_uploaded_files_backup_logs_action(action,created_at),
            CONSTRAINT fk_uploaded_files_backup_logs_file FOREIGN KEY(file_id) REFERENCES uploaded_files_backup(id) ON DELETE SET NULL,
            CONSTRAINT fk_uploaded_files_backup_logs_user FOREIGN KEY(actor_user_id) REFERENCES users(id) ON DELETE SET NULL
        ){$engine}");
        $module=$pdo->prepare("INSERT INTO modules(module_key,module_title,sort_order,status,created_at) VALUES('file_backup.manage','مدیریت بکاپ فایل‌های سایت',92,'active',NOW()) ON DUPLICATE KEY UPDATE module_title=VALUES(module_title)");$module->execute();
        $settings=[['file_backup_api_key_hash','','password'],['file_backup_api_key_rotated_at','','text'],['file_backup_allowed_ips','','text']];$stmt=$pdo->prepare('INSERT IGNORE INTO site_settings(setting_key,setting_value,setting_type,updated_at) VALUES(?,?,?,NOW())');foreach($settings as $setting)$stmt->execute($setting);
    }
}
