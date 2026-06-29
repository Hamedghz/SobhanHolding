<?php

require_once __DIR__.'/Database.php';

class WindowsNotificationHubModule
{
    public static function repair(PDO $pdo): void
    {
        self::notificationColumns($pdo);
        $engine=' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        $tables=[
            "CREATE TABLE IF NOT EXISTS sobhan_notification_devices (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id INT UNSIGNED NOT NULL,device_uid CHAR(36) NOT NULL,
                device_name VARCHAR(190) NOT NULL,device_type VARCHAR(30) NOT NULL DEFAULT 'windows',app_version VARCHAR(30) NOT NULL,
                machine_fingerprint_hash CHAR(64) NOT NULL,token_hash CHAR(64) NOT NULL,last_seen_at DATETIME NULL,
                active TINYINT(1) NOT NULL DEFAULT 1,revoked_at DATETIME NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,UNIQUE KEY uq_notification_device_uid(device_uid),
                INDEX idx_notification_devices_user(user_id,active),INDEX idx_notification_devices_fingerprint(user_id,machine_fingerprint_hash),
                CONSTRAINT fk_notification_devices_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
            ){$engine}",
            "CREATE TABLE IF NOT EXISTS sobhan_notification_delivery_logs (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,notification_id BIGINT UNSIGNED NOT NULL,device_id BIGINT UNSIGNED NOT NULL,
                status VARCHAR(30) NOT NULL,action VARCHAR(40) NULL,reply_text TEXT NULL,delivered_at DATETIME NULL,clicked_at DATETIME NULL,
                error_message VARCHAR(1000) NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_notification_delivery(notification_id,device_id),INDEX idx_notification_delivery_device(device_id,status,created_at),
                CONSTRAINT fk_notification_delivery_notification FOREIGN KEY(notification_id) REFERENCES sobhan_notifications(id) ON DELETE CASCADE,
                CONSTRAINT fk_notification_delivery_device FOREIGN KEY(device_id) REFERENCES sobhan_notification_devices(id) ON DELETE CASCADE
            ){$engine}",
            "CREATE TABLE IF NOT EXISTS sobhan_user_notification_module_settings (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id INT UNSIGNED NOT NULL,module VARCHAR(50) NOT NULL,
                enabled TINYINT(1) NOT NULL DEFAULT 1,show_body TINYINT(1) NOT NULL DEFAULT 1,sound VARCHAR(50) NOT NULL DEFAULT 'default',
                priority VARCHAR(30) NOT NULL DEFAULT 'normal',allow_quick_reply TINYINT(1) NOT NULL DEFAULT 0,direct_action_enabled TINYINT(1) NOT NULL DEFAULT 0,
                desktop_enabled TINYINT(1) NOT NULL DEFAULT 1,mobile_enabled TINYINT(1) NOT NULL DEFAULT 1,email_enabled TINYINT(1) NOT NULL DEFAULT 0,
                sms_enabled TINYINT(1) NOT NULL DEFAULT 0,silent_hours_enabled TINYINT(1) NOT NULL DEFAULT 0,silent_from TIME NULL,silent_to TIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_user_notification_module(user_id,module),INDEX idx_user_notification_module_enabled(user_id,desktop_enabled,enabled),
                CONSTRAINT fk_user_notification_module_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
            ){$engine}",
            "CREATE TABLE IF NOT EXISTS sobhan_notification_pairing_codes (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id INT UNSIGNED NOT NULL,code_hash CHAR(64) NOT NULL,
                expires_at DATETIME NOT NULL,used_at DATETIME NULL,attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
                created_ip VARCHAR(45) NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_notification_pair_code_hash(code_hash),INDEX idx_notification_pair_user(user_id,expires_at),
                CONSTRAINT fk_notification_pair_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
            ){$engine}",
            "CREATE TABLE IF NOT EXISTS sobhan_notification_pairing_attempts (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,ip_hash CHAR(64) NOT NULL,success TINYINT(1) NOT NULL DEFAULT 0,
                attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,INDEX idx_notification_pair_attempt(ip_hash,attempted_at)
            ){$engine}",
        ];
        foreach($tables as $sql)$pdo->exec($sql);
        $module=$pdo->prepare('INSERT INTO modules(module_key,module_title,sort_order,status,created_at) VALUES(?,?,?,"active",NOW()) ON DUPLICATE KEY UPDATE module_title=VALUES(module_title)');
        $module->execute(['notification_hub.devices','دستگاه‌های Sobhan Notification Hub',713]);
        if(Database::tableExists('site_settings')){
            $setting=$pdo->prepare('INSERT INTO site_settings(setting_key,setting_value,setting_type,updated_at) VALUES(?,?,?,NOW()) ON DUPLICATE KEY UPDATE setting_type=VALUES(setting_type)');
            foreach(self::defaults() as $key=>[$value,$type])$setting->execute([$key,$value,$type]);
        }
    }

    private static function notificationColumns(PDO $pdo): void
    {
        $columns=[
            'module'=>'ADD module VARCHAR(50) NOT NULL DEFAULT "system" AFTER event_type',
            'type'=>'ADD type VARCHAR(80) NOT NULL DEFAULT "general" AFTER module',
            'safe_body'=>'ADD safe_body VARCHAR(255) NULL AFTER body',
            'sender_user_id'=>'ADD sender_user_id INT UNSIGNED NULL AFTER actor_user_id',
            'conversation_id'=>'ADD conversation_id BIGINT UNSIGNED NULL AFTER sender_user_id',
            'related_module'=>'ADD related_module VARCHAR(60) NULL AFTER related_type',
            'actions_json'=>'ADD actions_json LONGTEXT NULL AFTER action_url',
            'is_read'=>'ADD is_read TINYINT(1) NOT NULL DEFAULT 0 AFTER status',
        ];
        foreach($columns as $name=>$definition)if(!Database::columnExists('sobhan_notifications',$name))$pdo->exec("ALTER TABLE sobhan_notifications {$definition}");
        try{$pdo->exec('UPDATE sobhan_notifications SET safe_body=COALESCE(safe_body,safe_push_body),sender_user_id=COALESCE(sender_user_id,actor_user_id),is_read=IF(status="read",1,is_read)');}catch(Throwable $e){error_log('notification hub backfill: '.$e->getMessage());}
    }

    public static function defaults(): array
    {
        return [
            'notification_hub_poll_seconds'=>['20','number'],'notification_hub_max_per_poll'=>['20','number'],
            'notification_hub_enable_realtime'=>['0','boolean'],'notification_hub_enable_sound'=>['1','boolean'],
            'notification_hub_enable_quick_reply'=>['1','boolean'],'notification_hub_enable_action_buttons'=>['1','boolean'],
            'notification_hub_latest_version'=>['1.0.0','text'],'notification_hub_download_url'=>['','url'],
            'notification_hub_force_update'=>['0','boolean'],'notification_hub_update_message'=>['','text'],
        ];
    }
}
