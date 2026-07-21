<?php

require_once __DIR__ . '/EmailCrypto.php';

class EmailHubModule
{
    public static function repair(PDO $pdo): void
    {
        $e = ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        $sql = [
            "CREATE TABLE IF NOT EXISTS email_providers (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,name VARCHAR(190) NOT NULL,code VARCHAR(100) NOT NULL UNIQUE,provider_type VARCHAR(40) NOT NULL DEFAULT 'custom',imap_host VARCHAR(255) NULL,imap_port INT UNSIGNED NOT NULL DEFAULT 993,imap_encryption ENUM('ssl','tls','none') NOT NULL DEFAULT 'ssl',smtp_host VARCHAR(255) NULL,smtp_port INT UNSIGNED NOT NULL DEFAULT 587,smtp_encryption ENUM('ssl','tls','none') NOT NULL DEFAULT 'tls',auth_type ENUM('password','app_password','oauth2') NOT NULL DEFAULT 'password',oauth_config_json LONGTEXT NULL,active TINYINT(1) NOT NULL DEFAULT 1,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,INDEX idx_email_providers_active(active)){$e}",
            "CREATE TABLE IF NOT EXISTS email_accounts (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,provider_id INT UNSIGNED NOT NULL,account_title VARCHAR(190) NOT NULL,email_address VARCHAR(255) NOT NULL,display_name VARCHAR(190) NULL,username VARCHAR(255) NOT NULL,encrypted_password LONGTEXT NULL,encrypted_access_token LONGTEXT NULL,encrypted_refresh_token LONGTEXT NULL,auth_type ENUM('password','app_password','oauth2') NOT NULL DEFAULT 'password',account_scope ENUM('personal','department','role','shared','system') NOT NULL DEFAULT 'personal',owner_user_id INT UNSIGNED NULL,department_id INT UNSIGNED NULL,role_id INT UNSIGNED NULL,is_shared TINYINT(1) NOT NULL DEFAULT 0,sync_enabled TINYINT(1) NOT NULL DEFAULT 1,send_enabled TINYINT(1) NOT NULL DEFAULT 1,last_sync_at DATETIME NULL,sync_status ENUM('never','syncing','ok','error') NOT NULL DEFAULT 'never',last_error TEXT NULL,active TINYINT(1) NOT NULL DEFAULT 1,created_by INT UNSIGNED NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,INDEX idx_email_accounts_scope(account_scope),INDEX idx_email_accounts_sync(active,sync_enabled),CONSTRAINT fk_email_accounts_provider FOREIGN KEY(provider_id) REFERENCES email_providers(id) ON DELETE RESTRICT,CONSTRAINT fk_email_accounts_owner FOREIGN KEY(owner_user_id) REFERENCES users(id) ON DELETE SET NULL,CONSTRAINT fk_email_accounts_department FOREIGN KEY(department_id) REFERENCES org_units(id) ON DELETE SET NULL,CONSTRAINT fk_email_accounts_role FOREIGN KEY(role_id) REFERENCES org_roles(id) ON DELETE SET NULL,CONSTRAINT fk_email_accounts_creator FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL){$e}",
            "CREATE TABLE IF NOT EXISTS email_account_permissions (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,account_id INT UNSIGNED NOT NULL,user_id INT UNSIGNED NULL,role_id INT UNSIGNED NULL,department_id INT UNSIGNED NULL,can_read TINYINT(1) NOT NULL DEFAULT 0,can_send TINYINT(1) NOT NULL DEFAULT 0,can_reply TINYINT(1) NOT NULL DEFAULT 0,can_forward TINYINT(1) NOT NULL DEFAULT 0,can_delete TINYINT(1) NOT NULL DEFAULT 0,can_manage TINYINT(1) NOT NULL DEFAULT 0,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,INDEX idx_email_permissions_account(account_id),INDEX idx_email_permissions_user(user_id),CONSTRAINT fk_email_permissions_account FOREIGN KEY(account_id) REFERENCES email_accounts(id) ON DELETE CASCADE,CONSTRAINT fk_email_permissions_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,CONSTRAINT fk_email_permissions_role FOREIGN KEY(role_id) REFERENCES org_roles(id) ON DELETE CASCADE,CONSTRAINT fk_email_permissions_department FOREIGN KEY(department_id) REFERENCES org_units(id) ON DELETE CASCADE){$e}",
            "CREATE TABLE IF NOT EXISTS email_folders (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,account_id INT UNSIGNED NOT NULL,remote_folder_id VARCHAR(500) NULL,folder_name VARCHAR(255) NOT NULL,folder_path VARCHAR(500) NOT NULL,folder_type ENUM('inbox','sent','drafts','spam','trash','archive','custom') NOT NULL DEFAULT 'custom',total_messages INT UNSIGNED NOT NULL DEFAULT 0,unread_count INT UNSIGNED NOT NULL DEFAULT 0,uid_validity BIGINT UNSIGNED NULL,last_uid BIGINT UNSIGNED NOT NULL DEFAULT 0,sync_enabled TINYINT(1) NOT NULL DEFAULT 1,sort_order INT NOT NULL DEFAULT 0,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,UNIQUE KEY uq_email_folder_path(account_id,folder_path(190)),INDEX idx_email_folders_account(account_id),CONSTRAINT fk_email_folders_account FOREIGN KEY(account_id) REFERENCES email_accounts(id) ON DELETE CASCADE){$e}",
            "CREATE TABLE IF NOT EXISTS email_messages (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,account_id INT UNSIGNED NOT NULL,folder_id BIGINT UNSIGNED NOT NULL,uid_validity BIGINT UNSIGNED NOT NULL DEFAULT 0,remote_uid BIGINT UNSIGNED NOT NULL,outbox_id BIGINT UNSIGNED NULL,message_id VARCHAR(500) NULL,thread_id VARCHAR(500) NULL,subject VARCHAR(500) NULL,from_email VARCHAR(255) NULL,from_name VARCHAR(255) NULL,to_json LONGTEXT NULL,cc_json LONGTEXT NULL,bcc_json LONGTEXT NULL,reply_to_json LONGTEXT NULL,date_received DATETIME NULL,date_sent DATETIME NULL,body_text LONGTEXT NULL,body_html LONGTEXT NULL,snippet VARCHAR(500) NULL,has_attachments TINYINT(1) NOT NULL DEFAULT 0,is_read TINYINT(1) NOT NULL DEFAULT 0,is_starred TINYINT(1) NOT NULL DEFAULT 0,is_flagged TINYINT(1) NOT NULL DEFAULT 0,is_answered TINYINT(1) NOT NULL DEFAULT 0,is_forwarded TINYINT(1) NOT NULL DEFAULT 0,importance ENUM('low','normal','high') NOT NULL DEFAULT 'normal',status ENUM('new','read','pending_reply','replied','forwarded','archived','spam','deleted') NOT NULL DEFAULT 'new',tags_json LONGTEXT NULL,raw_headers_json LONGTEXT NULL,assigned_user_id INT UNSIGNED NULL,assigned_group VARCHAR(190) NULL,internal_note TEXT NULL,related_ticket_id BIGINT UNSIGNED NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,UNIQUE KEY uq_email_remote_uid(account_id,folder_id,uid_validity,remote_uid),UNIQUE KEY uq_email_message_outbox(outbox_id),INDEX idx_email_messages_folder(folder_id,date_received),INDEX idx_email_messages_status(account_id,status),INDEX idx_email_messages_message_id(message_id(190)),CONSTRAINT fk_email_messages_account FOREIGN KEY(account_id) REFERENCES email_accounts(id) ON DELETE CASCADE,CONSTRAINT fk_email_messages_folder FOREIGN KEY(folder_id) REFERENCES email_folders(id) ON DELETE CASCADE,CONSTRAINT fk_email_messages_assignee FOREIGN KEY(assigned_user_id) REFERENCES users(id) ON DELETE SET NULL){$e}",
            "CREATE TABLE IF NOT EXISTS email_attachments (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,message_id BIGINT UNSIGNED NULL,account_id INT UNSIGNED NOT NULL,outbox_id BIGINT UNSIGNED NULL,file_name VARCHAR(255) NOT NULL,mime_type VARCHAR(190) NOT NULL,file_size INT UNSIGNED NOT NULL DEFAULT 0,storage_path VARCHAR(500) NOT NULL,content_id VARCHAR(500) NULL,is_inline TINYINT(1) NOT NULL DEFAULT 0,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,INDEX idx_email_attachments_message(message_id),INDEX idx_email_attachments_outbox(outbox_id),CONSTRAINT fk_email_attachments_message FOREIGN KEY(message_id) REFERENCES email_messages(id) ON DELETE CASCADE,CONSTRAINT fk_email_attachments_account FOREIGN KEY(account_id) REFERENCES email_accounts(id) ON DELETE CASCADE){$e}",
            "CREATE TABLE IF NOT EXISTS email_outbox (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,account_id INT UNSIGNED NOT NULL,sender_user_id INT UNSIGNED NOT NULL,to_json LONGTEXT NOT NULL,cc_json LONGTEXT NULL,bcc_json LONGTEXT NULL,subject VARCHAR(500) NULL,body_html LONGTEXT NULL,body_text LONGTEXT NULL,attachments_json LONGTEXT NULL,related_message_id BIGINT UNSIGNED NULL,send_type ENUM('compose','reply','reply_all','forward') NOT NULL DEFAULT 'compose',status ENUM('draft','queued','sending','sent','failed','cancelled') NOT NULL DEFAULT 'draft',attempts INT UNSIGNED NOT NULL DEFAULT 0,last_error TEXT NULL,scheduled_at DATETIME NULL,sent_at DATETIME NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,INDEX idx_email_outbox_queue(status,scheduled_at),CONSTRAINT fk_email_outbox_account FOREIGN KEY(account_id) REFERENCES email_accounts(id) ON DELETE CASCADE,CONSTRAINT fk_email_outbox_sender FOREIGN KEY(sender_user_id) REFERENCES users(id) ON DELETE RESTRICT,CONSTRAINT fk_email_outbox_related FOREIGN KEY(related_message_id) REFERENCES email_messages(id) ON DELETE SET NULL){$e}",
            "CREATE TABLE IF NOT EXISTS email_logs (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,account_id INT UNSIGNED NULL,message_id BIGINT UNSIGNED NULL,user_id INT UNSIGNED NULL,action VARCHAR(60) NOT NULL,description VARCHAR(500) NULL,technical_details LONGTEXT NULL,ip_address VARCHAR(45) NULL,user_agent VARCHAR(255) NULL,meta_json LONGTEXT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,INDEX idx_email_logs_account(account_id,created_at),INDEX idx_email_logs_action(action),CONSTRAINT fk_email_logs_account FOREIGN KEY(account_id) REFERENCES email_accounts(id) ON DELETE SET NULL,CONSTRAINT fk_email_logs_message FOREIGN KEY(message_id) REFERENCES email_messages(id) ON DELETE SET NULL,CONSTRAINT fk_email_logs_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL){$e}",
            "CREATE TABLE IF NOT EXISTS email_templates (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,title VARCHAR(190) NOT NULL,category VARCHAR(100) NULL,subject_template VARCHAR(500) NULL,body_template LONGTEXT NOT NULL,active TINYINT(1) NOT NULL DEFAULT 1,created_by INT UNSIGNED NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,CONSTRAINT fk_email_templates_user FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL){$e}",
            "CREATE TABLE IF NOT EXISTS email_rules (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,title VARCHAR(190) NOT NULL,account_id INT UNSIGNED NULL,condition_field ENUM('from','subject','body','has_attachment','age_hours') NOT NULL,condition_operator ENUM('contains','equals','domain','greater_than') NOT NULL DEFAULT 'contains',condition_value VARCHAR(500) NULL,action_type ENUM('add_tag','assign_user','assign_group','create_ticket','set_pending_reply','mark_important') NOT NULL,action_value VARCHAR(500) NULL,priority INT NOT NULL DEFAULT 100,active TINYINT(1) NOT NULL DEFAULT 1,created_by INT UNSIGNED NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,INDEX idx_email_rules_active(active,priority),CONSTRAINT fk_email_rules_account FOREIGN KEY(account_id) REFERENCES email_accounts(id) ON DELETE CASCADE,CONSTRAINT fk_email_rules_user FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL){$e}",
            "CREATE TABLE IF NOT EXISTS email_integrations (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,message_id BIGINT UNSIGNED NOT NULL,integration_type ENUM('ticket','cartable') NOT NULL,target_id BIGINT UNSIGNED NULL,status ENUM('pending','completed','failed') NOT NULL DEFAULT 'pending',requested_by INT UNSIGNED NULL,payload_json LONGTEXT NULL,last_error TEXT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,INDEX idx_email_integrations_message(message_id),CONSTRAINT fk_email_integrations_message FOREIGN KEY(message_id) REFERENCES email_messages(id) ON DELETE CASCADE,CONSTRAINT fk_email_integrations_user FOREIGN KEY(requested_by) REFERENCES users(id) ON DELETE SET NULL){$e}",
        ];
        foreach ($sql as $statement) $pdo->exec($statement);

        foreach (['access_token_expires_at'=>'DATETIME NULL','sync_lock_token'=>'CHAR(64) NULL','sync_lock_expires_at'=>'DATETIME NULL'] as $column=>$definition) {
            if (!Database::columnExists('email_accounts', $column)) $pdo->exec("ALTER TABLE email_accounts ADD `{$column}` {$definition}");
        }
        $columnTypeStmt = $pdo->prepare("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='email_accounts' AND COLUMN_NAME='sync_status' LIMIT 1");
        $columnTypeStmt->execute();
        $syncStatusType = (string)$columnTypeStmt->fetchColumn();
        if (!str_contains($syncStatusType, "'partial'") || !str_contains($syncStatusType, "'needs_reauth'")) {
            $pdo->exec("ALTER TABLE email_accounts MODIFY sync_status ENUM('never','syncing','ok','partial','needs_reauth','error') NOT NULL DEFAULT 'never'");
        }

        $providers = [
            ['Gmail','gmail','gmail','imap.gmail.com',993,'ssl','smtp.gmail.com',587,'tls','app_password'],
            ['Yahoo','yahoo','yahoo','imap.mail.yahoo.com',993,'ssl','smtp.mail.yahoo.com',587,'tls','app_password'],
            ['ایمیل هاست','website','website','',993,'ssl','',587,'tls','password'],
            ['mail.ir','mail_ir','custom','',993,'ssl','',587,'tls','password'],
            ['mail.iran.ir','mail_iran_ir','custom','',993,'ssl','',587,'tls','password'],
            ['سرویس سفارشی','custom','custom','',993,'ssl','',587,'tls','password'],
        ];
        $stmt = $pdo->prepare('INSERT IGNORE INTO email_providers(name,code,provider_type,imap_host,imap_port,imap_encryption,smtp_host,smtp_port,smtp_encryption,auth_type,active,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,1,NOW(),NOW())');
        foreach ($providers as $provider) $stmt->execute($provider);
        $cronToken = bin2hex(random_bytes(24));
        $settingStmt=$pdo->prepare("INSERT IGNORE INTO site_settings(setting_key,setting_value,setting_type,updated_at) VALUES('email_cron_token',?,'password',NOW())");$settingStmt->execute([$cronToken]);
        $moduleStmt = $pdo->prepare("INSERT INTO modules(module_key,module_title,sort_order,status,created_at) VALUES(?,?,72,'active',NOW()) ON DUPLICATE KEY UPDATE module_title=VALUES(module_title)");
        foreach ([['email_hub','هاب ایمیل سازمانی'],['email_hub.accounts','مدیریت حساب‌های ایمیل'],['email_hub.providers','مدیریت سرویس‌دهندگان ایمیل'],['email_hub.reports','گزارش ایمیل سازمانی'],['email_hub.rules','قوانین خودکار ایمیل']] as $module) $moduleStmt->execute($module);
    }

    public static function canManage(): bool { return Auth::isAdmin() || Auth::can('email_hub.accounts', 'edit'); }
    public static function canProviders(): bool { return Auth::isAdmin() || Auth::can('email_hub.providers', 'edit'); }

    public static function account(int $id): ?array
    {
        return Database::fetch('SELECT a.*,p.name provider_name,p.code provider_code,p.provider_type,p.imap_host,p.imap_port,p.imap_encryption,p.smtp_host,p.smtp_port,p.smtp_encryption,p.oauth_config_json FROM email_accounts a JOIN email_providers p ON p.id=a.provider_id WHERE a.id=? LIMIT 1', [$id]);
    }

    public static function access(array $account, string $action = 'read', ?array $user = null): bool
    {
        $user ??= Auth::user() ?: [];
        if (!$user) return false;
        if (in_array($user['role'] ?? '', ['admin','super_admin'], true)) return true;
        $uid=(int)$user['id']; $unit=(int)($user['org_unit_id']??0); $role=(int)($user['org_role_id']??0);
        if($action==='manage'&&(int)($account['created_by']??0)===$uid)return true;
        if(in_array($action,['send','reply','forward'],true)&&(int)($account['send_enabled']??0)!==1)return false;
        $base = (($account['account_scope']??'')==='personal' && (int)$account['owner_user_id']===$uid)
            || (($account['account_scope']??'')==='department' && $unit>0 && (int)$account['department_id']===$unit)
            || (($account['account_scope']??'')==='role' && $role>0 && (int)$account['role_id']===$role);
        $column = ['read'=>'can_read','send'=>'can_send','reply'=>'can_reply','forward'=>'can_forward','delete'=>'can_delete','manage'=>'can_manage'][$action] ?? 'can_read';
        $permission = Database::fetch("SELECT MAX({$column}) allowed FROM email_account_permissions WHERE account_id=? AND ((user_id IS NOT NULL AND user_id=?) OR (role_id IS NOT NULL AND role_id=?) OR (department_id IS NOT NULL AND department_id=?))", [(int)$account['id'],$uid,$role,$unit]);
        if ((int)($permission['allowed']??0)===1) return true;
        $shared=in_array($account['account_scope']??'',['shared','system'],true);
        if ($action==='read') return $base || ($shared && Auth::can('email_hub','view'));
        if ($action==='send') return ($base && (int)($account['send_enabled']??0)===1) || ($shared && Auth::can('email_hub','create') && (int)($account['send_enabled']??0)===1);
        if (in_array($action,['reply','forward'],true)) return $base || ($shared && Auth::can('email_hub','edit'));
        return false;
    }

    public static function accessibleAccounts(string $action='read'): array
    {
        $rows=Database::fetchAll('SELECT a.*,p.name provider_name,p.imap_host,p.smtp_host FROM email_accounts a JOIN email_providers p ON p.id=a.provider_id WHERE a.active=1 AND p.active=1 ORDER BY a.account_title');
        return array_values(array_filter($rows, static fn($row)=>self::access($row,$action)));
    }

    public static function requireAccount(int $id,string $action='read'): array
    {
        Auth::requireLogin(); $account=self::account($id);
        if(!$account||!self::access($account,$action)){http_response_code(403);throw new RuntimeException('email_account_access_denied');}
        return $account;
    }

    public static function validateConnectionConfig(array $account,string $channel): void
    {
        if($channel==='imap'&&trim((string)($account['imap_host']??''))==='')throw new InvalidArgumentException('نشانی سرور IMAP تنظیم نشده است. ابتدا سرویس‌دهنده ایمیل را تکمیل کنید.');
        if($channel==='smtp'&&trim((string)($account['smtp_host']??''))==='')throw new InvalidArgumentException('نشانی سرور SMTP تنظیم نشده است. ابتدا سرویس‌دهنده ایمیل را تکمیل کنید.');
    }

    public static function credentials(array $account): array
    {
        return ['password'=>EmailCrypto::decrypt($account['encrypted_password']??null),'access_token'=>EmailCrypto::decrypt($account['encrypted_access_token']??null),'refresh_token'=>EmailCrypto::decrypt($account['encrypted_refresh_token']??null)];
    }

    public static function sanitizeHtml(?string $html): string
    {
        $html=trim((string)$html); if($html==='')return '';
        if(strlen($html)>5000000)throw new InvalidArgumentException('حجم متن ایمیل بیش از حد مجاز است.');
        $html=preg_replace('#<(script|style|iframe|object|embed|form|input|button|textarea|select|meta|link|base)[^>]*>.*?</\\1>#is','',$html)??'';
        $html=preg_replace('/\\son[a-z]+\\s*=\\s*("[^"]*"|\'[^\']*\'|[^\\s>]+)/iu','',$html)??'';
        $html=preg_replace('/\\s(href|src)\\s*=\\s*(["\'])\\s*(javascript:|vbscript:|data:text\\/html)[^"\']*\\2/iu','',$html)??'';
        return strip_tags($html,'<p><br><div><span><strong><b><em><i><u><s><h1><h2><h3><h4><blockquote><ul><ol><li><table><thead><tbody><tfoot><tr><th><td><img><hr><sub><sup><a>');
    }

    public static function log(?int $accountId,?int $messageId,string $action,string $description='',?string $technical=null,array $meta=[]): void
    {
        try{Database::execute('INSERT INTO email_logs(account_id,message_id,user_id,action,description,technical_details,ip_address,user_agent,meta_json,created_at) VALUES(?,?,?,?,?,?,?,?,?,NOW())',[$accountId,$messageId,(int)(Auth::user()['id']??0)?:null,$action,self::truncate($description,500),self::safeTechnical($technical),substr((string)($_SERVER['REMOTE_ADDR']??''),0,45),substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,255),$meta?json_encode(self::redact($meta),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES):null]);}catch(Throwable $ignored){}
    }

    private static function safeTechnical(?string $value): ?string
    {
        if ($value === null || trim($value) === '') return null;
        $value = preg_replace('/(?:access|refresh|id)[_-]?token\s*[=:]\s*[^\s,;]+/iu', 'token=[redacted]', $value) ?? $value;
        $value = preg_replace('/(?:password|client[_-]?secret)\s*[=:]\s*[^\s,;]+/iu', 'secret=[redacted]', $value) ?? $value;
        return self::truncate($value, 1000);
    }

    private static function redact(array $meta): array
    {
        foreach ($meta as $key => $value) {
            if (preg_match('/token|password|secret|body|content/i', (string)$key)) $meta[$key] = '[redacted]';
            elseif (is_array($value)) $meta[$key] = self::redact($value);
        }
        return $meta;
    }

    private static function truncate(string $value, int $length): string
    {
        return function_exists('mb_substr') ? mb_substr($value, 0, $length) : substr($value, 0, $length);
    }
}
