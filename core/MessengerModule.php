<?php

require_once __DIR__ . '/Database.php';

class MessengerModule
{
    public static function repair(PDO $pdo): void
    {
        $engine = ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        $statements = [
            "CREATE TABLE IF NOT EXISTS messenger_groups (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(190) NOT NULL,
                created_by INT UNSIGNED NULL,
                active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_messenger_groups_active (active),
                CONSTRAINT fk_messenger_groups_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
            ){$engine}",
            "CREATE TABLE IF NOT EXISTS messenger_group_members (
                group_id INT UNSIGNED NOT NULL,
                user_id INT UNSIGNED NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (group_id,user_id),
                INDEX idx_messenger_group_members_user (user_id),
                CONSTRAINT fk_messenger_group_members_group FOREIGN KEY (group_id) REFERENCES messenger_groups(id) ON DELETE CASCADE,
                CONSTRAINT fk_messenger_group_members_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ){$engine}",
            "CREATE TABLE IF NOT EXISTS sales_report_shares (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                sender_user_id INT UNSIGNED NOT NULL,
                source_module VARCHAR(80) NOT NULL DEFAULT 'manager_dashboard',
                source_page VARCHAR(190) NOT NULL,
                source_report_type VARCHAR(100) NOT NULL,
                source_record_id BIGINT UNSIGNED NULL,
                report_title VARCHAR(190) NOT NULL,
                report_period VARCHAR(100) NULL,
                filters_json LONGTEXT NULL,
                snapshot_json LONGTEXT NOT NULL,
                attachment_path VARCHAR(500) NULL,
                attachment_name VARCHAR(255) NULL,
                attachment_mime VARCHAR(120) NULL,
                snapshot_hash CHAR(64) NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_sales_report_shares_sender (sender_user_id,created_at),
                INDEX idx_sales_report_shares_source (source_module,source_record_id),
                INDEX idx_sales_report_shares_hash (snapshot_hash),
                CONSTRAINT fk_sales_report_shares_sender FOREIGN KEY (sender_user_id) REFERENCES users(id) ON DELETE RESTRICT
            ){$engine}",
            "CREATE TABLE IF NOT EXISTS messenger_messages (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                sender_user_id INT UNSIGNED NOT NULL,
                message_type VARCHAR(50) NOT NULL DEFAULT 'text',
                title VARCHAR(190) NOT NULL,
                body TEXT NULL,
                payload_json LONGTEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_messenger_messages_sender (sender_user_id,created_at),
                INDEX idx_messenger_messages_type (message_type,created_at),
                CONSTRAINT fk_messenger_messages_sender FOREIGN KEY (sender_user_id) REFERENCES users(id) ON DELETE RESTRICT
            ){$engine}",
            "CREATE TABLE IF NOT EXISTS messenger_message_recipients (
                message_id BIGINT UNSIGNED NOT NULL,
                user_id INT UNSIGNED NOT NULL,
                status ENUM('unread','read','archived') NOT NULL DEFAULT 'unread',
                read_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (message_id,user_id),
                INDEX idx_messenger_recipient_inbox (user_id,status,created_at),
                CONSTRAINT fk_messenger_recipients_message FOREIGN KEY (message_id) REFERENCES messenger_messages(id) ON DELETE CASCADE,
                CONSTRAINT fk_messenger_recipients_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ){$engine}",
            "CREATE TABLE IF NOT EXISTS messenger_forwarded_reports (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                message_id BIGINT UNSIGNED NOT NULL,
                share_id BIGINT UNSIGNED NOT NULL,
                sender_user_id INT UNSIGNED NOT NULL,
                recipient_type VARCHAR(40) NOT NULL,
                recipient_id VARCHAR(190) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_messenger_forwarded_message (message_id),
                INDEX idx_messenger_forwarded_share (share_id),
                CONSTRAINT fk_messenger_forwarded_message FOREIGN KEY (message_id) REFERENCES messenger_messages(id) ON DELETE CASCADE,
                CONSTRAINT fk_messenger_forwarded_share FOREIGN KEY (share_id) REFERENCES sales_report_shares(id) ON DELETE RESTRICT,
                CONSTRAINT fk_messenger_forwarded_sender FOREIGN KEY (sender_user_id) REFERENCES users(id) ON DELETE RESTRICT
            ){$engine}",
            "CREATE TABLE IF NOT EXISTS messenger_forward_logs (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                share_id BIGINT UNSIGNED NULL,
                message_id BIGINT UNSIGNED NULL,
                actor_user_id INT UNSIGNED NULL,
                action VARCHAR(60) NOT NULL,
                status VARCHAR(30) NOT NULL DEFAULT 'success',
                details_json LONGTEXT NULL,
                ip_address VARCHAR(45) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_messenger_forward_logs_share (share_id,created_at),
                INDEX idx_messenger_forward_logs_actor (actor_user_id,created_at)
            ){$engine}",
        ];
        foreach ($statements as $statement) $pdo->exec($statement);

        $chatStatements = [
            "CREATE TABLE IF NOT EXISTS chat_settings (setting_key VARCHAR(100) PRIMARY KEY,setting_value LONGTEXT NULL,is_secret TINYINT(1) NOT NULL DEFAULT 0,updated_by INT UNSIGNED NULL,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,INDEX idx_chat_settings_updated_by(updated_by)){$engine}",
            "CREATE TABLE IF NOT EXISTS chat_conversations (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,uuid CHAR(36) NOT NULL,title VARCHAR(190) NULL,description TEXT NULL,type ENUM('private','group','channel','broadcast') NOT NULL DEFAULT 'private',scope_type ENUM('custom','department','role','sales_line','supervisor_team','manager_team','company') NOT NULL DEFAULT 'custom',scope_value VARCHAR(190) NULL,avatar_path VARCHAR(500) NULL,created_by INT UNSIGNED NOT NULL,owner_id INT UNSIGNED NULL,is_official TINYINT(1) NOT NULL DEFAULT 0,is_readonly TINYINT(1) NOT NULL DEFAULT 0,is_active TINYINT(1) NOT NULL DEFAULT 1,last_message_id BIGINT UNSIGNED NULL,last_message_at DATETIME NULL,metadata_json LONGTEXT NULL,deleted_at DATETIME NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,UNIQUE KEY uq_chat_conversation_uuid(uuid),INDEX idx_chat_conversation_type(type,is_active),INDEX idx_chat_conversation_last(last_message_at),INDEX idx_chat_conversation_scope(scope_type,scope_value),CONSTRAINT fk_chat_conversation_creator FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE RESTRICT,CONSTRAINT fk_chat_conversation_owner FOREIGN KEY(owner_id) REFERENCES users(id) ON DELETE SET NULL){$engine}",
            "CREATE TABLE IF NOT EXISTS chat_participants (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,conversation_id BIGINT UNSIGNED NOT NULL,user_id INT UNSIGNED NOT NULL,participant_role ENUM('owner','admin','moderator','member','subscriber') NOT NULL DEFAULT 'member',can_send TINYINT(1) NOT NULL DEFAULT 1,is_muted TINYINT(1) NOT NULL DEFAULT 0,is_archived TINYINT(1) NOT NULL DEFAULT 0,is_pinned TINYINT(1) NOT NULL DEFAULT 0,last_read_message_id BIGINT UNSIGNED NULL,last_read_at DATETIME NULL,joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,left_at DATETIME NULL,deleted_at DATETIME NULL,UNIQUE KEY uq_chat_participant(conversation_id,user_id),INDEX idx_chat_participant_user(user_id,deleted_at,is_archived),CONSTRAINT fk_chat_participant_conversation FOREIGN KEY(conversation_id) REFERENCES chat_conversations(id) ON DELETE CASCADE,CONSTRAINT fk_chat_participant_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE){$engine}",
            "CREATE TABLE IF NOT EXISTS chat_messages (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,uuid CHAR(36) NOT NULL,conversation_id BIGINT UNSIGNED NOT NULL,sender_id INT UNSIGNED NULL,parent_message_id BIGINT UNSIGNED NULL,forwarded_from_message_id BIGINT UNSIGNED NULL,message_type ENUM('text','file','image','location','system','report_card','official_notice') NOT NULL DEFAULT 'text',body TEXT NULL,payload_json LONGTEXT NULL,is_edited TINYINT(1) NOT NULL DEFAULT 0,is_pinned TINYINT(1) NOT NULL DEFAULT 0,delete_scope ENUM('none','everyone') NOT NULL DEFAULT 'none',expires_at DATETIME NULL,deleted_at DATETIME NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,UNIQUE KEY uq_chat_message_uuid(uuid),INDEX idx_chat_message_conversation(conversation_id,id),INDEX idx_chat_message_sender(sender_id,created_at),INDEX idx_chat_message_parent(parent_message_id),FULLTEXT KEY ft_chat_message_body(body),CONSTRAINT fk_chat_message_conversation FOREIGN KEY(conversation_id) REFERENCES chat_conversations(id) ON DELETE CASCADE,CONSTRAINT fk_chat_message_sender FOREIGN KEY(sender_id) REFERENCES users(id) ON DELETE SET NULL,CONSTRAINT fk_chat_message_parent FOREIGN KEY(parent_message_id) REFERENCES chat_messages(id) ON DELETE SET NULL){$engine}",
            "CREATE TABLE IF NOT EXISTS chat_message_status (message_id BIGINT UNSIGNED NOT NULL,user_id INT UNSIGNED NOT NULL,status ENUM('sent','delivered','read') NOT NULL DEFAULT 'sent',delivered_at DATETIME NULL,read_at DATETIME NULL,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY(message_id,user_id),INDEX idx_chat_status_user(user_id,status),CONSTRAINT fk_chat_status_message FOREIGN KEY(message_id) REFERENCES chat_messages(id) ON DELETE CASCADE,CONSTRAINT fk_chat_status_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE){$engine}",
            "CREATE TABLE IF NOT EXISTS chat_message_user_state (message_id BIGINT UNSIGNED NOT NULL,user_id INT UNSIGNED NOT NULL,hidden_at DATETIME NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(message_id,user_id),CONSTRAINT fk_chat_state_message FOREIGN KEY(message_id) REFERENCES chat_messages(id) ON DELETE CASCADE,CONSTRAINT fk_chat_state_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE){$engine}",
            "CREATE TABLE IF NOT EXISTS chat_files (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,uuid CHAR(36) NOT NULL,message_id BIGINT UNSIGNED NULL,conversation_id BIGINT UNSIGNED NOT NULL,uploaded_by INT UNSIGNED NOT NULL,original_name VARCHAR(255) NOT NULL,storage_path VARCHAR(500) NOT NULL,mime_type VARCHAR(150) NOT NULL,file_size BIGINT UNSIGNED NOT NULL,file_hash CHAR(64) NOT NULL,width INT UNSIGNED NULL,height INT UNSIGNED NULL,scan_status ENUM('pending','clean','rejected','error') NOT NULL DEFAULT 'pending',deleted_at DATETIME NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE KEY uq_chat_file_uuid(uuid),INDEX idx_chat_file_message(message_id),INDEX idx_chat_file_conversation(conversation_id),CONSTRAINT fk_chat_file_message FOREIGN KEY(message_id) REFERENCES chat_messages(id) ON DELETE SET NULL,CONSTRAINT fk_chat_file_conversation FOREIGN KEY(conversation_id) REFERENCES chat_conversations(id) ON DELETE CASCADE,CONSTRAINT fk_chat_file_user FOREIGN KEY(uploaded_by) REFERENCES users(id) ON DELETE RESTRICT){$engine}",
            "CREATE TABLE IF NOT EXISTS chat_reactions (message_id BIGINT UNSIGNED NOT NULL,user_id INT UNSIGNED NOT NULL,reaction VARCHAR(32) NOT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(message_id,user_id,reaction),CONSTRAINT fk_chat_reaction_message FOREIGN KEY(message_id) REFERENCES chat_messages(id) ON DELETE CASCADE,CONSTRAINT fk_chat_reaction_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE){$engine}",
            "CREATE TABLE IF NOT EXISTS chat_mentions (message_id BIGINT UNSIGNED NOT NULL,user_id INT UNSIGNED NOT NULL,read_at DATETIME NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(message_id,user_id),INDEX idx_chat_mention_user(user_id,read_at),CONSTRAINT fk_chat_mention_message FOREIGN KEY(message_id) REFERENCES chat_messages(id) ON DELETE CASCADE,CONSTRAINT fk_chat_mention_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE){$engine}",
            "CREATE TABLE IF NOT EXISTS chat_notifications (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id INT UNSIGNED NOT NULL,conversation_id BIGINT UNSIGNED NULL,message_id BIGINT UNSIGNED NULL,event_type VARCHAR(80) NOT NULL,priority ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',payload_json LONGTEXT NULL,status ENUM('pending','processing','sent','error','cancelled') NOT NULL DEFAULT 'pending',attempts INT UNSIGNED NOT NULL DEFAULT 0,available_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,last_error TEXT NULL,processed_at DATETIME NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,INDEX idx_chat_notification_queue(status,available_at),INDEX idx_chat_notification_user(user_id,created_at),CONSTRAINT fk_chat_notification_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE){$engine}",
            "CREATE TABLE IF NOT EXISTS chat_push_subscriptions (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id INT UNSIGNED NOT NULL,endpoint VARCHAR(700) NOT NULL,p256dh VARCHAR(255) NOT NULL,auth_secret VARCHAR(255) NOT NULL,user_agent VARCHAR(255) NULL,active TINYINT(1) NOT NULL DEFAULT 1,last_seen_at DATETIME NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,UNIQUE KEY uq_chat_push_endpoint(endpoint(190)),INDEX idx_chat_push_user(user_id,active),CONSTRAINT fk_chat_push_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE){$engine}",
            "CREATE TABLE IF NOT EXISTS chat_windows_devices (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id INT UNSIGNED NOT NULL,device_uuid CHAR(36) NOT NULL,device_name VARCHAR(190) NOT NULL,notification_device_id BIGINT UNSIGNED NULL,active TINYINT(1) NOT NULL DEFAULT 1,last_seen_at DATETIME NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,UNIQUE KEY uq_chat_windows_device(device_uuid),INDEX idx_chat_windows_user(user_id,active),CONSTRAINT fk_chat_windows_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE){$engine}",
            "CREATE TABLE IF NOT EXISTS chat_live_locations (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,conversation_id BIGINT UNSIGNED NOT NULL,message_id BIGINT UNSIGNED NULL,user_id INT UNSIGNED NOT NULL,latitude DECIMAL(10,7) NOT NULL,longitude DECIMAL(10,7) NOT NULL,accuracy_m DECIMAL(10,2) NULL,location_type ENUM('fixed','live') NOT NULL DEFAULT 'fixed',started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,expires_at DATETIME NULL,stopped_at DATETIME NULL,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,INDEX idx_chat_location_conversation(conversation_id,expires_at),INDEX idx_chat_location_user(user_id,expires_at),CONSTRAINT fk_chat_location_conversation FOREIGN KEY(conversation_id) REFERENCES chat_conversations(id) ON DELETE CASCADE,CONSTRAINT fk_chat_location_message FOREIGN KEY(message_id) REFERENCES chat_messages(id) ON DELETE SET NULL,CONSTRAINT fk_chat_location_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE){$engine}",
            "CREATE TABLE IF NOT EXISTS chat_audit_logs (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,actor_id INT UNSIGNED NULL,conversation_id BIGINT UNSIGNED NULL,message_id BIGINT UNSIGNED NULL,action VARCHAR(100) NOT NULL,target_type VARCHAR(80) NULL,target_id VARCHAR(100) NULL,details_json LONGTEXT NULL,ip_address VARCHAR(45) NULL,user_agent VARCHAR(255) NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,INDEX idx_chat_audit_actor(actor_id,created_at),INDEX idx_chat_audit_conversation(conversation_id,created_at),INDEX idx_chat_audit_action(action,created_at)){$engine}",
            "CREATE TABLE IF NOT EXISTS chat_reports (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,message_id BIGINT UNSIGNED NOT NULL,conversation_id BIGINT UNSIGNED NOT NULL,reported_by INT UNSIGNED NOT NULL,reason VARCHAR(190) NOT NULL,description TEXT NULL,status ENUM('open','reviewing','resolved','dismissed') NOT NULL DEFAULT 'open',reviewed_by INT UNSIGNED NULL,resolution_note TEXT NULL,reviewed_at DATETIME NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,INDEX idx_chat_report_status(status,created_at),CONSTRAINT fk_chat_report_message FOREIGN KEY(message_id) REFERENCES chat_messages(id) ON DELETE CASCADE,CONSTRAINT fk_chat_report_user FOREIGN KEY(reported_by) REFERENCES users(id) ON DELETE RESTRICT){$engine}",
            "CREATE TABLE IF NOT EXISTS chat_presence (user_id INT UNSIGNED PRIMARY KEY,status ENUM('online','away','offline') NOT NULL DEFAULT 'offline',socket_id VARCHAR(190) NULL,last_seen_at DATETIME NULL,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,CONSTRAINT fk_chat_presence_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE){$engine}",
            "CREATE TABLE IF NOT EXISTS chat_rate_limits (rate_key VARCHAR(190) PRIMARY KEY,hits INT UNSIGNED NOT NULL DEFAULT 0,window_started_at DATETIME NOT NULL,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP){$engine}",
        ];
        foreach ($chatStatements as $statement) $pdo->exec($statement);

        $columns = [
            'chat_settings'=>['id'=>'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT UNIQUE FIRST','setting_type'=>'VARCHAR(30) NOT NULL DEFAULT "string" AFTER setting_value','description'=>'VARCHAR(500) NULL AFTER setting_type','created_at'=>'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER updated_by'],
            'chat_conversations'=>['conversation_uuid'=>'CHAR(36) NULL AFTER uuid','organization_scope'=>'VARCHAR(40) NULL AFTER scope_value','department_id'=>'INT UNSIGNED NULL AFTER organization_scope','role_id'=>'INT UNSIGNED NULL AFTER department_id','sales_line'=>'VARCHAR(50) NULL AFTER role_id','supervisor_id'=>'INT UNSIGNED NULL AFTER sales_line','manager_id'=>'INT UNSIGNED NULL AFTER supervisor_id','is_archived'=>'TINYINT(1) NOT NULL DEFAULT 0 AFTER is_readonly','is_read_only'=>'TINYINT(1) NOT NULL DEFAULT 0 AFTER is_archived'],
            'chat_participants'=>['can_send_message'=>'TINYINT(1) NOT NULL DEFAULT 1 AFTER can_send','can_send_file'=>'TINYINT(1) NOT NULL DEFAULT 1 AFTER can_send_message','can_send_voice'=>'TINYINT(1) NOT NULL DEFAULT 1 AFTER can_send_file','can_send_video'=>'TINYINT(1) NOT NULL DEFAULT 1 AFTER can_send_voice','can_send_location'=>'TINYINT(1) NOT NULL DEFAULT 1 AFTER can_send_video','can_add_member'=>'TINYINT(1) NOT NULL DEFAULT 0 AFTER can_send_location','can_remove_member'=>'TINYINT(1) NOT NULL DEFAULT 0 AFTER can_add_member','can_pin_message'=>'TINYINT(1) NOT NULL DEFAULT 0 AFTER can_remove_member','can_delete_others_message'=>'TINYINT(1) NOT NULL DEFAULT 0 AFTER can_pin_message','muted_until'=>'DATETIME NULL AFTER is_muted','notification_level'=>'VARCHAR(20) NOT NULL DEFAULT "all" AFTER muted_until','is_active'=>'TINYINT(1) NOT NULL DEFAULT 1 AFTER is_pinned','created_at'=>'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER joined_at','updated_at'=>'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at'],
            'chat_messages'=>['message_uuid'=>'CHAR(36) NULL AFTER uuid','reply_to_message_id'=>'BIGINT UNSIGNED NULL AFTER parent_message_id','body_plain'=>'TEXT NULL AFTER body','file_id'=>'BIGINT UNSIGNED NULL AFTER body_plain','latitude'=>'DECIMAL(10,7) NULL AFTER file_id','longitude'=>'DECIMAL(10,7) NULL AFTER latitude','location_title'=>'VARCHAR(190) NULL AFTER longitude','location_address'=>'VARCHAR(500) NULL AFTER location_title','live_location_expires_at'=>'DATETIME NULL AFTER location_address','pinned_by'=>'INT UNSIGNED NULL AFTER is_pinned','pinned_at'=>'DATETIME NULL AFTER pinned_by','edited_at'=>'DATETIME NULL AFTER is_edited','is_deleted'=>'TINYINT(1) NOT NULL DEFAULT 0 AFTER edited_at','deleted_by'=>'INT UNSIGNED NULL AFTER is_deleted'],
            'chat_files'=>['file_uuid'=>'CHAR(36) NULL AFTER uuid','stored_name'=>'VARCHAR(255) NULL AFTER original_name','storage_disk'=>'VARCHAR(20) NOT NULL DEFAULT "local" AFTER stored_name','extension'=>'VARCHAR(20) NULL AFTER mime_type','duration_seconds'=>'INT UNSIGNED NULL AFTER height','is_public'=>'TINYINT(1) NOT NULL DEFAULT 0 AFTER duration_seconds','is_deleted'=>'TINYINT(1) NOT NULL DEFAULT 0 AFTER is_public','deleted_by'=>'INT UNSIGNED NULL AFTER is_deleted','updated_at'=>'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at'],
            'chat_live_locations'=>['speed'=>'DECIMAL(10,2) NULL AFTER accuracy_m','heading'=>'DECIMAL(10,2) NULL AFTER speed'],
            'chat_audit_logs'=>['actor_user_id'=>'INT UNSIGNED NULL AFTER actor_id','entity_type'=>'VARCHAR(80) NULL AFTER action','entity_id'=>'BIGINT UNSIGNED NULL AFTER entity_type','target_user_id'=>'INT UNSIGNED NULL AFTER entity_id','old_value_json'=>'LONGTEXT NULL AFTER details_json','new_value_json'=>'LONGTEXT NULL AFTER old_value_json'],
            'chat_reports'=>['updated_at'=>'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at'],
        ];
        foreach ($columns as $table=>$defs) foreach ($defs as $column=>$definition) self::ensureColumn($pdo,$table,$column,$definition);
        $pdo->exec('UPDATE chat_conversations SET conversation_uuid=uuid WHERE conversation_uuid IS NULL');
        $pdo->exec('UPDATE chat_messages SET message_uuid=uuid,reply_to_message_id=parent_message_id,body_plain=body WHERE message_uuid IS NULL OR body_plain IS NULL');
        $pdo->exec('UPDATE chat_files SET file_uuid=uuid,stored_name=SUBSTRING_INDEX(storage_path,"/",-1),extension=SUBSTRING_INDEX(storage_path,".",-1) WHERE file_uuid IS NULL OR stored_name IS NULL');
        self::ensureEnumContains($pdo,'chat_conversations','type','system',"ENUM('private','group','channel','broadcast','system') NOT NULL DEFAULT 'private'");
        self::ensureEnumContains($pdo,'chat_messages','message_type','voice',"ENUM('text','file','image','video','voice','location','live_location','system','report_card','official_notice','notice') NOT NULL DEFAULT 'text'");
        self::ensureEnumContains($pdo,'chat_participants','participant_role','viewer',"ENUM('owner','admin','moderator','member','subscriber','viewer') NOT NULL DEFAULT 'member'");
        self::ensureEnumContains($pdo,'chat_reports','status','rejected',"ENUM('open','reviewing','resolved','dismissed','rejected') NOT NULL DEFAULT 'open'");

        $defaults = ['messenger.enabled'=>'1','messenger.realtime_enabled'=>'1','messenger.max_file_mb'=>'25','messenger.allowed_mimes'=>'image/jpeg,image/png,image/webp,video/mp4,video/webm,audio/mpeg,audio/ogg,audio/webm,application/pdf,text/plain,application/zip','messenger.voice_enabled'=>'1','messenger.video_enabled'=>'1','messenger.location_enabled'=>'1','messenger.live_location_enabled'=>'1','messenger.web_push_enabled'=>'1','messenger.windows_agent_enabled'=>'1','messenger.edit_window_minutes'=>'30','messenger.delete_window_minutes'=>'30','messenger.live_location_max_minutes'=>'480','messenger.rate_messages_per_minute'=>'60','messenger.retention_days'=>'0','messenger.realtime_url'=>'','messenger.log_level'=>'standard','messenger.welcome_message'=>'به پیام‌رسان سازمانی سبحان خوش آمدید.','messenger.usage_rules'=>'استفاده از پیام‌رسان تابع سیاست‌های محرمانگی سازمان است.'];
        $setting = $pdo->prepare('INSERT IGNORE INTO chat_settings(setting_key,setting_value,is_secret,updated_at) VALUES(?,?,0,NOW())');
        foreach ($defaults as $key=>$value) $setting->execute([$key,$value]);
        foreach (['messenger.realtime_secret','messenger.realtime_internal_key'] as $secretKey) {
            $setting->execute([$secretKey,bin2hex(random_bytes(32))]);
            $pdo->prepare('UPDATE chat_settings SET is_secret=1 WHERE setting_key=?')->execute([$secretKey]);
        }

        $module = $pdo->prepare('INSERT INTO modules(module_key,module_title,sort_order,status,created_at) VALUES(?,?,?,"active",NOW()) ON DUPLICATE KEY UPDATE module_title=VALUES(module_title)');
        $module->execute(['messenger.view','پیام‌رسان سازمانی',704]);
        $module->execute(['manager_dashboard.forward','ارسال خروجی پنل مدیران فروش به پیام‌رسان',705]);
        foreach (self::permissions() as $key=>$title) $module->execute([$key,$title,706]);
    }

    public static function permissions(): array
    {
        return ['messenger.private.send'=>'ارسال پیام خصوصی','messenger.group.create'=>'ایجاد گروه','messenger.group.manage'=>'مدیریت گروه','messenger.channel.create'=>'ایجاد کانال','messenger.channel.manage'=>'مدیریت کانال','messenger.broadcast.send'=>'ارسال سراسری','messenger.official_notice.send'=>'ارسال اطلاعیه رسمی','messenger.files.upload'=>'بارگذاری فایل','messenger.files.download'=>'دریافت فایل','messenger.location.send'=>'ارسال موقعیت','messenger.location.live'=>'اشتراک موقعیت زنده','messenger.live_location.send'=>'اشتراک موقعیت زنده','messenger.admin.dashboard'=>'داشبورد پیام‌رسان','messenger.admin.reports'=>'گزارش‌های پیام‌رسان','messenger.admin.audit'=>'ممیزی پیام‌رسان','messenger.admin.settings'=>'تنظیمات پیام‌رسان','messenger.message.delete_any'=>'حذف پیام دیگران','messenger.message.pin'=>'سنجاق پیام','messenger.moderate'=>'مدیریت محتوا','messenger.message.moderate'=>'مدیریت محتوای پیام'];
    }

    private static function ensureColumn(PDO $pdo,string $table,string $column,string $definition): void
    { $stmt=$pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');$stmt->execute([$table,$column]);if((int)$stmt->fetchColumn()===0)$pdo->exec("ALTER TABLE `{$table}` ADD `{$column}` {$definition}"); }

    private static function ensureEnumContains(PDO $pdo,string $table,string $column,string $needle,string $definition): void
    { $stmt=$pdo->prepare('SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');$stmt->execute([$table,$column]);$type=(string)$stmt->fetchColumn();if($type!==''&&!str_contains($type,"'{$needle}'"))$pdo->exec("ALTER TABLE `{$table}` MODIFY `{$column}` {$definition}"); }
}
