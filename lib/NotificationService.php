<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/PushNotificationService.php';

class NotificationService
{
    public const EVENTS = [
        'ticket_assigned' => 'ثبت یا تخصیص تیکت',
        'ticket_reply' => 'پاسخ جدید تیکت',
        'ticket_status_changed' => 'تغییر وضعیت تیکت',
        'ticket_reassigned' => 'ارجاع دوباره تیکت',
        'approval_request' => 'درخواست تأیید',
        'approval_decision' => 'تأیید یا رد درخواست',
        'cartable_item' => 'آیتم جدید کارتابل',
        'sla_warning' => 'هشدار نزدیک شدن SLA',
        'sla_breach' => 'عبور از SLA',
        'ticket_reopened' => 'بازگشایی تیکت',
        'internal_message' => 'پیام داخلی',
        'messenger_message' => 'پیام جدید پیام‌رسان',
        'messenger_official_notice' => 'اطلاعیه رسمی پیام‌رسان',
        'personal_planner_reminder' => 'یادآوری برنامه کاری من',
        'forwarded_report' => 'گزارش فورواردشده',
        'due_date_reminder' => 'یادآوری مهلت انجام',
        'test' => 'اعلان آزمایشی',
    ];

    public static function repair(PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS sobhan_notifications (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            actor_user_id INT UNSIGNED NULL,
            event_type VARCHAR(80) NOT NULL,
            title VARCHAR(190) NOT NULL,
            body TEXT NULL,
            safe_push_body VARCHAR(255) NULL,
            related_type VARCHAR(60) NULL,
            related_id BIGINT UNSIGNED NULL,
            action_url VARCHAR(255) NULL,
            priority ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
            status ENUM('unread','read','archived') NOT NULL DEFAULT 'unread',
            channel_in_app TINYINT(1) NOT NULL DEFAULT 1,
            channel_push_requested TINYINT(1) NOT NULL DEFAULT 0,
            channel_push_sent TINYINT(1) NOT NULL DEFAULT 0,
            channel_email_requested TINYINT(1) NOT NULL DEFAULT 0,
            channel_sms_requested TINYINT(1) NOT NULL DEFAULT 0,
            push_attempts INT UNSIGNED NOT NULL DEFAULT 0,
            push_sent_at DATETIME NULL,
            read_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_sobhan_notifications_user_status (user_id, status, created_at),
            INDEX idx_sobhan_notifications_event (event_type),
            INDEX idx_sobhan_notifications_related (related_type, related_id),
            CONSTRAINT fk_sobhan_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_sobhan_notifications_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS sobhan_push_subscriptions (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            endpoint TEXT NOT NULL,
            endpoint_hash CHAR(64) NOT NULL,
            p256dh VARCHAR(255) NULL,
            auth_key VARCHAR(255) NULL,
            content_encoding VARCHAR(40) NOT NULL DEFAULT 'aes128gcm',
            user_agent VARCHAR(255) NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            last_success_at DATETIME NULL,
            last_error VARCHAR(255) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_sobhan_push_endpoint (endpoint_hash),
            INDEX idx_sobhan_push_user (user_id, active),
            CONSTRAINT fk_sobhan_push_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS sobhan_user_notification_settings (
            user_id INT UNSIGNED NOT NULL PRIMARY KEY,
            in_app_enabled TINYINT(1) NOT NULL DEFAULT 1,
            push_enabled TINYINT(1) NOT NULL DEFAULT 1,
            email_enabled TINYINT(1) NOT NULL DEFAULT 0,
            sms_enabled TINYINT(1) NOT NULL DEFAULT 0,
            quiet_hours_start TIME NULL,
            quiet_hours_end TIME NULL,
            event_settings_json LONGTEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_sobhan_notification_settings_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        self::ensureColumns($pdo);

        $moduleStmt = $pdo->prepare('INSERT IGNORE INTO modules (module_key,module_title,sort_order,status,created_at) VALUES (?,?,?,"active",NOW())');
        $moduleStmt->execute(['notification_center', 'مرکز اعلان‌ها', 711]);
        $moduleStmt->execute(['notification_admin', 'مدیریت و تست اعلان‌ها', 712]);

        $settingStmt = $pdo->prepare('INSERT IGNORE INTO site_settings (setting_key,setting_value,setting_type,updated_at) VALUES (?,?,?,NOW())');
        $settingStmt->execute(['notification_vapid_subject', 'mailto:admin@sobhan.local', 'text']);
        PushNotificationService::publicKey();
    }

    private static function ensureColumns(PDO $pdo): void
    {
        $columns = [
            'sobhan_notifications' => [
                'actor_user_id' => 'INT UNSIGNED NULL AFTER user_id',
                'event_type' => 'VARCHAR(80) NOT NULL DEFAULT "general" AFTER actor_user_id',
                'title' => 'VARCHAR(190) NOT NULL DEFAULT "" AFTER event_type',
                'body' => 'TEXT NULL AFTER title',
                'safe_push_body' => 'VARCHAR(255) NULL AFTER body',
                'related_type' => 'VARCHAR(60) NULL AFTER safe_push_body',
                'related_id' => 'BIGINT UNSIGNED NULL AFTER related_type',
                'action_url' => 'VARCHAR(255) NULL AFTER related_id',
                'priority' => "ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal' AFTER action_url",
                'status' => "ENUM('unread','read','archived') NOT NULL DEFAULT 'unread' AFTER priority",
                'channel_in_app' => 'TINYINT(1) NOT NULL DEFAULT 1 AFTER status',
                'channel_push_requested' => 'TINYINT(1) NOT NULL DEFAULT 0 AFTER channel_in_app',
                'channel_push_sent' => 'TINYINT(1) NOT NULL DEFAULT 0 AFTER channel_push_requested',
                'channel_email_requested' => 'TINYINT(1) NOT NULL DEFAULT 0 AFTER channel_push_sent',
                'channel_sms_requested' => 'TINYINT(1) NOT NULL DEFAULT 0 AFTER channel_email_requested',
                'push_attempts' => 'INT UNSIGNED NOT NULL DEFAULT 0 AFTER channel_sms_requested',
                'push_sent_at' => 'DATETIME NULL AFTER push_attempts',
                'read_at' => 'DATETIME NULL AFTER push_sent_at',
                'created_at' => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER read_at',
                'updated_at' => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at',
            ],
            'sobhan_push_subscriptions' => [
                'endpoint_hash' => 'CHAR(64) NOT NULL DEFAULT "" AFTER endpoint',
                'p256dh' => 'VARCHAR(255) NULL AFTER endpoint_hash',
                'auth_key' => 'VARCHAR(255) NULL AFTER p256dh',
                'content_encoding' => "VARCHAR(40) NOT NULL DEFAULT 'aes128gcm' AFTER auth_key",
                'user_agent' => 'VARCHAR(255) NULL AFTER content_encoding',
                'active' => 'TINYINT(1) NOT NULL DEFAULT 1 AFTER user_agent',
                'last_success_at' => 'DATETIME NULL AFTER active',
                'last_error' => 'VARCHAR(255) NULL AFTER last_success_at',
                'created_at' => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER last_error',
                'updated_at' => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at',
            ],
            'sobhan_user_notification_settings' => [
                'in_app_enabled' => 'TINYINT(1) NOT NULL DEFAULT 1 AFTER user_id',
                'push_enabled' => 'TINYINT(1) NOT NULL DEFAULT 1 AFTER in_app_enabled',
                'email_enabled' => 'TINYINT(1) NOT NULL DEFAULT 0 AFTER push_enabled',
                'sms_enabled' => 'TINYINT(1) NOT NULL DEFAULT 0 AFTER email_enabled',
                'quiet_hours_start' => 'TIME NULL AFTER sms_enabled',
                'quiet_hours_end' => 'TIME NULL AFTER quiet_hours_start',
                'event_settings_json' => 'LONGTEXT NULL AFTER quiet_hours_end',
                'created_at' => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER event_settings_json',
                'updated_at' => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at',
            ],
        ];

        foreach ($columns as $table => $tableColumns) {
            foreach ($tableColumns as $column => $definition) {
                if (!self::columnExists($pdo, $table, $column)) {
                    try {
                        $pdo->exec("ALTER TABLE {$table} ADD {$column} {$definition}");
                    } catch (Throwable $e) {
                        error_log("notification column repair {$table}.{$column}: " . $e->getMessage());
                    }
                }
            }
        }

        if (self::columnExists($pdo, 'sobhan_push_subscriptions', 'endpoint') && self::columnExists($pdo, 'sobhan_push_subscriptions', 'endpoint_hash')) {
            try {
                $pdo->exec('UPDATE sobhan_push_subscriptions SET endpoint_hash = SHA2(endpoint, 256) WHERE endpoint_hash = "" OR endpoint_hash IS NULL');
            } catch (Throwable $e) {
                error_log('notification endpoint hash repair: ' . $e->getMessage());
            }
        }

        $indexes = [
            ['sobhan_notifications', 'idx_sobhan_notifications_user_status', 'ADD INDEX idx_sobhan_notifications_user_status (user_id, status, created_at)'],
            ['sobhan_notifications', 'idx_sobhan_notifications_event', 'ADD INDEX idx_sobhan_notifications_event (event_type)'],
            ['sobhan_notifications', 'idx_sobhan_notifications_related', 'ADD INDEX idx_sobhan_notifications_related (related_type, related_id)'],
            ['sobhan_push_subscriptions', 'uq_sobhan_push_endpoint', 'ADD UNIQUE KEY uq_sobhan_push_endpoint (endpoint_hash)'],
            ['sobhan_push_subscriptions', 'idx_sobhan_push_user', 'ADD INDEX idx_sobhan_push_user (user_id, active)'],
        ];
        foreach ($indexes as [$table, $index, $alter]) {
            if (!self::indexExists($pdo, $table, $index)) {
                try {
                    $pdo->exec("ALTER TABLE {$table} {$alter}");
                } catch (Throwable $e) {
                    error_log("notification index repair {$table}.{$index}: " . $e->getMessage());
                }
            }
        }
    }

    private static function columnExists(PDO $pdo, string $table, string $column): bool
    {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
        $stmt->execute([$table, $column]);
        return (int)$stmt->fetchColumn() > 0;
    }

    private static function indexExists(PDO $pdo, string $table, string $index): bool
    {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?');
        $stmt->execute([$table, $index]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public static function eventLabels(): array
    {
        return self::EVENTS;
    }

    public static function defaultEventSettings(): array
    {
        $settings = [];
        foreach (array_keys(self::EVENTS) as $event) {
            $settings[$event] = ['in_app' => true, 'push' => true, 'email' => false, 'sms' => false];
        }
        return $settings;
    }

    public static function settings(int $userId): array
    {
        self::ensureDefaultSettings($userId);
        $row = Database::fetch('SELECT * FROM sobhan_user_notification_settings WHERE user_id = ?', [$userId]) ?: [];
        $eventSettings = json_decode((string)($row['event_settings_json'] ?? ''), true);
        if (!is_array($eventSettings)) $eventSettings = [];

        return [
            'in_app_enabled' => (int)($row['in_app_enabled'] ?? 1),
            'push_enabled' => (int)($row['push_enabled'] ?? 1),
            'email_enabled' => (int)($row['email_enabled'] ?? 0),
            'sms_enabled' => (int)($row['sms_enabled'] ?? 0),
            'quiet_hours_start' => $row['quiet_hours_start'] ?? null,
            'quiet_hours_end' => $row['quiet_hours_end'] ?? null,
            'events' => array_replace_recursive(self::defaultEventSettings(), $eventSettings),
        ];
    }

    public static function saveSettings(int $userId, array $data): void
    {
        $events = [];
        foreach (array_keys(self::EVENTS) as $event) {
            $events[$event] = [
                'in_app' => !empty($data['events'][$event]['in_app']),
                'push' => !empty($data['events'][$event]['push']),
                'email' => !empty($data['events'][$event]['email']),
                'sms' => !empty($data['events'][$event]['sms']),
            ];
        }

        Database::execute(
            'INSERT INTO sobhan_user_notification_settings
             (user_id,in_app_enabled,push_enabled,email_enabled,sms_enabled,quiet_hours_start,quiet_hours_end,event_settings_json,created_at,updated_at)
             VALUES (?,?,?,?,?,?,?,?,NOW(),NOW())
             ON DUPLICATE KEY UPDATE in_app_enabled=VALUES(in_app_enabled),push_enabled=VALUES(push_enabled),email_enabled=VALUES(email_enabled),sms_enabled=VALUES(sms_enabled),quiet_hours_start=VALUES(quiet_hours_start),quiet_hours_end=VALUES(quiet_hours_end),event_settings_json=VALUES(event_settings_json),updated_at=NOW()',
            [
                $userId,
                !empty($data['in_app_enabled']) ? 1 : 0,
                !empty($data['push_enabled']) ? 1 : 0,
                !empty($data['email_enabled']) ? 1 : 0,
                !empty($data['sms_enabled']) ? 1 : 0,
                self::timeOrNull((string)($data['quiet_hours_start'] ?? '')),
                self::timeOrNull((string)($data['quiet_hours_end'] ?? '')),
                json_encode($events, JSON_UNESCAPED_UNICODE),
            ]
        );
    }

    public static function create(int $userId, string $eventType, string $title, string $body = '', ?string $actionUrl = null, array $options = []): ?int
    {
        $recipient = Database::fetch('SELECT id,status FROM users WHERE id = ? AND status = "active"', [$userId]);
        if (!$recipient) return null;

        $actor = $options['actor_user'] ?? Auth::user();
        $systemAuthorized = !empty($options['system_authorized']) && ($eventType === 'forwarded_report' || str_starts_with($eventType, 'ticket_'));
        if (!$systemAuthorized && !self::canCreateForUser($userId, is_array($actor) ? $actor : null)) return null;

        $settings = self::settings($userId);
        $eventSettings = $settings['events'][$eventType] ?? ['in_app' => true, 'push' => true, 'email' => false, 'sms' => false];
        if (!$settings['in_app_enabled'] && !$settings['push_enabled'] && !$settings['email_enabled'] && !$settings['sms_enabled']) {
            return null;
        }

        $title = self::limit(strip_tags($title), 190);
        $body = self::limit(strip_tags($body), 4000);
        $safePushBody = self::limit(strip_tags((string)($options['safe_push_body'] ?? 'یک اعلان جدید در پنل سبحان دارید.')), 255);
        $requestedPriority = (string)($options['priority'] ?? 'normal');
        $priority = in_array($requestedPriority, ['low', 'normal', 'high', 'urgent'], true) ? $requestedPriority : 'normal';
        $eventType = preg_replace('/[^a-z0-9_.-]/i', '', $eventType) ?: 'general';
        $actionUrl = self::safeActionUrl($actionUrl);
        $actorId = is_array($actor) ? (int)($actor['id'] ?? 0) : 0;
        $hubModule = self::hubModule((string)($options['module'] ?? $eventType));
        $notificationType = self::limit((string)($options['type'] ?? $eventType), 80);
        $actions = $options['actions'] ?? self::hubActions($hubModule, $eventType);
        $actionsJson = json_encode(is_array($actions) ? array_slice($actions, 0, 5) : [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        Database::execute(
            'INSERT INTO sobhan_notifications
             (user_id,actor_user_id,sender_user_id,event_type,module,type,title,body,safe_push_body,safe_body,related_type,related_module,related_id,conversation_id,action_url,actions_json,priority,channel_in_app,channel_push_requested,channel_email_requested,channel_sms_requested,created_at,updated_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())',
            [
                $userId,
                $actorId ?: null,
                (int)($options['sender_user_id'] ?? $actorId) ?: null,
                $eventType,
                $hubModule,
                $notificationType,
                $title,
                $body,
                $safePushBody,
                $safePushBody,
                self::limit((string)($options['related_type'] ?? ''), 60) ?: null,
                self::limit((string)($options['related_module'] ?? $hubModule), 60) ?: null,
                (int)($options['related_id'] ?? 0) ?: null,
                (int)($options['conversation_id'] ?? 0) ?: null,
                $actionUrl,
                $actionsJson ?: '[]',
                $priority,
                !empty($eventSettings['in_app']) && $settings['in_app_enabled'] ? 1 : 0,
                !empty($eventSettings['push']) && $settings['push_enabled'] ? 1 : 0,
                !empty($eventSettings['email']) && $settings['email_enabled'] ? 1 : 0,
                !empty($eventSettings['sms']) && $settings['sms_enabled'] ? 1 : 0,
            ]
        );
        $notificationId = (int)Database::lastInsertId();

        if (!empty($eventSettings['push']) && $settings['push_enabled'] && !self::quietHoursActive($settings)) {
            $result = PushNotificationService::sendToUser($userId, $notificationId);
            Database::execute(
                'UPDATE sobhan_notifications SET push_attempts = ?, channel_push_sent = ?, push_sent_at = IF(? = 1, NOW(), push_sent_at) WHERE id = ? AND user_id = ?',
                [(int)$result['attempted'], (int)$result['sent'] > 0 ? 1 : 0, (int)$result['sent'] > 0 ? 1 : 0, $notificationId, $userId]
            );
        }

        self::queueFutureChannels($notificationId);
        return $notificationId;
    }

    public static function listForUser(int $userId, int $limit = 10, bool $unreadOnly = false): array
    {
        $limit = max(1, min(50, $limit));
        $where = 'user_id = ? AND channel_in_app = 1';
        $params = [$userId];
        if ($unreadOnly) $where .= ' AND status = "unread"';

        return Database::fetchAll(
            "SELECT id,event_type,title,body,related_type,related_id,action_url,priority,status,read_at,created_at
             FROM sobhan_notifications WHERE {$where} ORDER BY id DESC LIMIT {$limit}",
            $params
        );
    }

    public static function unreadCount(int $userId): int
    {
        $row = Database::fetch('SELECT COUNT(*) c FROM sobhan_notifications WHERE user_id = ? AND status = "unread" AND channel_in_app = 1', [$userId]);
        return (int)($row['c'] ?? 0);
    }

    public static function markAsRead(int $userId, int $notificationId): bool
    {
        return Database::execute(
            'UPDATE sobhan_notifications SET status = "read", read_at = COALESCE(read_at, NOW()), updated_at = NOW() WHERE id = ? AND user_id = ?',
            [$notificationId, $userId]
        );
    }

    public static function markAllAsRead(int $userId): bool
    {
        return Database::execute(
            'UPDATE sobhan_notifications SET status = "read", read_at = COALESCE(read_at, NOW()), updated_at = NOW() WHERE user_id = ? AND status = "unread"',
            [$userId]
        );
    }

    public static function adminLogs(int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));
        return Database::fetchAll(
            "SELECT n.*,u.name user_name,a.name actor_name
             FROM sobhan_notifications n
             JOIN users u ON u.id = n.user_id
             LEFT JOIN users a ON a.id = n.actor_user_id
             ORDER BY n.id DESC LIMIT {$limit}"
        );
    }

    public static function activeSubscriptions(int $userId): array
    {
        return Database::fetchAll(
            'SELECT id,endpoint,content_encoding,active,last_success_at,last_error,created_at,updated_at
             FROM sobhan_push_subscriptions WHERE user_id = ? ORDER BY active DESC, updated_at DESC',
            [$userId]
        );
    }

    public static function notifyTicketAssigned(int $userId, int $ticketId, string $ticketTitle): ?int
    {
        return self::create($userId, 'ticket_assigned', 'تیکت جدید برای شما ثبت شد', $ticketTitle, '/employee/ticket-view.php?id=' . $ticketId, ['related_type' => 'ticket', 'related_id' => $ticketId, 'safe_push_body' => 'یک تیکت جدید به شما تخصیص داده شد.', 'system_authorized'=>true]);
    }

    public static function notifyTicketReply(int $userId, int $ticketId, string $ticketTitle): ?int
    {
        return self::create($userId, 'ticket_reply', 'پاسخ جدید روی تیکت', $ticketTitle, '/employee/ticket-view.php?id=' . $ticketId, ['related_type' => 'ticket', 'related_id' => $ticketId, 'safe_push_body' => 'یک پاسخ جدید روی تیکت شما ثبت شد.', 'system_authorized'=>true]);
    }

    public static function notifyTicketStatusChanged(int $userId, int $ticketId, string $status): ?int
    {
        return self::create($userId, 'ticket_status_changed', 'وضعیت تیکت تغییر کرد', 'وضعیت جدید: ' . $status, '/employee/ticket-view.php?id=' . $ticketId, ['related_type' => 'ticket', 'related_id' => $ticketId, 'safe_push_body' => 'وضعیت یکی از تیکت‌های شما تغییر کرد.', 'system_authorized'=>true]);
    }

    public static function notifyTicketReassigned(int $userId, int $ticketId, string $ticketTitle): ?int
    {
        return self::create($userId, 'ticket_reassigned', 'تیکت به شما ارجاع شد', $ticketTitle, '/employee/ticket-view.php?id=' . $ticketId, ['related_type' => 'ticket', 'related_id' => $ticketId, 'safe_push_body' => 'یک تیکت به شما ارجاع شد.', 'system_authorized'=>true]);
    }

    public static function notifyCartableItem(int $userId, int $itemId, string $title, string $actionUrl): ?int
    {
        return self::create($userId, 'cartable_item', 'آیتم جدید در کارتابل', $title, $actionUrl, ['related_type' => 'cartable', 'related_id' => $itemId, 'safe_push_body' => 'یک آیتم جدید در کارتابل شما ثبت شد.']);
    }

    public static function notifyApprovalRequest(int $userId, int $itemId, string $title, string $actionUrl): ?int
    {
        return self::create($userId, 'approval_request', 'درخواست تأیید جدید', $title, $actionUrl, ['related_type' => 'approval', 'related_id' => $itemId, 'priority' => 'high', 'safe_push_body' => 'یک درخواست تأیید جدید برای شما ثبت شد.']);
    }

    public static function notifyApprovalDecision(int $userId, int $itemId, string $decision, string $actionUrl): ?int
    {
        return self::create($userId, 'approval_decision', 'نتیجه درخواست تأیید', $decision, $actionUrl, ['related_type' => 'approval', 'related_id' => $itemId, 'safe_push_body' => 'نتیجه یکی از درخواست‌های شما ثبت شد.']);
    }

    public static function notifySlaWarning(int $userId, int $ticketId): ?int
    {
        return self::create($userId, 'sla_warning', 'هشدار SLA', 'مهلت پاسخ یا انجام تیکت رو به پایان است.', '/employee/ticket-view.php?id=' . $ticketId, ['related_type' => 'ticket', 'related_id' => $ticketId, 'priority' => 'high']);
    }

    public static function notifySlaBreach(int $userId, int $ticketId): ?int
    {
        return self::create($userId, 'sla_breach', 'عبور از SLA', 'تیکت از زمان SLA عبور کرده است.', '/employee/ticket-view.php?id=' . $ticketId, ['related_type' => 'ticket', 'related_id' => $ticketId, 'priority' => 'urgent']);
    }

    public static function notifyDueDateReminder(int $userId, int $itemId, string $title, string $actionUrl): ?int
    {
        return self::create($userId, 'due_date_reminder', 'یادآوری مهلت انجام', $title, $actionUrl, ['related_type' => 'due_date', 'related_id' => $itemId, 'priority' => 'high']);
    }

    public static function notifyTicketReopened(int $userId, int $ticketId): ?int
    {
        return self::create($userId, 'ticket_reopened', 'تیکت بسته‌شده بازگشایی شد', 'یک تیکت دوباره باز شده است.', '/employee/ticket-view.php?id=' . $ticketId, ['related_type' => 'ticket', 'related_id' => $ticketId, 'priority' => 'high']);
    }

    public static function notifyInternalMessage(int $userId, int $messageId, string $actionUrl): ?int
    {
        return self::create($userId, 'internal_message', 'پیام داخلی جدید', 'یک پیام داخلی جدید برای شما ثبت شد.', $actionUrl, ['related_type' => 'internal_message', 'related_id' => $messageId]);
    }

    public static function notifyForwardedReport(int $userId, int $messageId, int $shareId, string $senderName): ?int
    {
        $senderName = self::limit(strip_tags($senderName), 150);
        return self::create($userId, 'forwarded_report', 'گزارش فروش جدید', 'یک گزارش فروش جدید از طرف '.$senderName.' برای شما ارسال شد.', '/messenger/report-view.php?id='.$shareId, [
            'related_type'=>'forwarded_report', 'related_id'=>$messageId, 'safe_push_body'=>'یک گزارش فروش جدید برای شما ارسال شد.', 'system_authorized'=>true,
        ]);
    }

    private static function ensureDefaultSettings(int $userId): void
    {
        Database::execute(
            'INSERT IGNORE INTO sobhan_user_notification_settings (user_id,event_settings_json,created_at,updated_at) VALUES (?, ?, NOW(), NOW())',
            [$userId, json_encode(self::defaultEventSettings(), JSON_UNESCAPED_UNICODE)]
        );
    }

    private static function canCreateForUser(int $userId, ?array $actor): bool
    {
        if (!$actor) return true;
        if ((int)($actor['id'] ?? 0) === $userId) return true;
        if (in_array($actor['role'] ?? '', ['admin', 'super_admin'], true)) return true;
        require_once __DIR__ . '/OrgAccess.php';
        return OrgAccess::canAccessUser($actor, $userId);
    }

    private static function hubModule(string $event): string
    {
        $event = strtolower($event);
        return match (true) {
            str_contains($event,'ticket'),str_contains($event,'sla')=>'ticketing',str_contains($event,'cartable')=>'cartable',
            str_contains($event,'approval')=>'approval',str_contains($event,'group_message')=>'messenger_group',str_contains($event,'channel')=>'messenger_channel',
            str_contains($event,'message'),str_contains($event,'messenger'),str_contains($event,'forwarded_report')=>'messenger',
            str_contains($event,'hr'),str_contains($event,'assessment'),str_contains($event,'payroll')=>'hr',str_contains($event,'sale')=>'sales',
            str_contains($event,'management'),str_contains($event,'meeting'),str_contains($event,'resolution'),str_contains($event,'finance')=>'management',default=>'system',
        };
    }

    private static function hubActions(string $module, string $event): array
    {
        $actions=[['id'=>'open','label'=>'باز کردن'],['id'=>'mark_read','label'=>'خوانده شد']];
        if($module==='messenger'&&str_contains($event,'message'))$actions[]=['id'=>'reply','label'=>'پاسخ سریع'];
        return $actions;
    }

    private static function safeActionUrl(?string $url): ?string
    {
        $url = trim((string)$url);
        if ($url === '') return null;
        $parts = parse_url($url);
        if ($parts === false || isset($parts['scheme']) || isset($parts['host']) || str_starts_with($url, '//')) {
            return null;
        }
        return str_starts_with($url, '/') ? self::limit($url, 255) : '/' . self::limit(ltrim($url, '/'), 254);
    }

    private static function quietHoursActive(array $settings): bool
    {
        $start = $settings['quiet_hours_start'] ?? null;
        $end = $settings['quiet_hours_end'] ?? null;
        if (!$start || !$end) return false;

        $now = date('H:i:s');
        if ($start < $end) return $now >= $start && $now <= $end;
        return $now >= $start || $now <= $end;
    }

    private static function timeOrNull(string $value): ?string
    {
        $value = trim($value);
        return preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', $value) ? substr($value, 0, 5) : null;
    }

    private static function limit(string $value, int $length): string
    {
        $value = trim($value);
        if (function_exists('mb_substr')) return mb_substr($value, 0, $length, 'UTF-8');
        return substr($value, 0, $length);
    }

    private static function queueFutureChannels(int $notificationId): void
    {
        // Email, SMS, WhatsApp and Telegram delivery workers can consume the channel flags later.
    }
}
