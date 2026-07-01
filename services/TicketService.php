<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../lib/NotificationService.php';

final class TicketService
{
    public const STATUSES = [
        'open' => 'باز',
        'assigned' => 'ارجاع‌شده',
        'in_progress' => 'در حال انجام',
        'waiting_user' => 'در انتظار کاربر',
        'waiting_admin' => 'در انتظار پشتیبانی',
        'resolved' => 'حل‌شده',
        'closed' => 'بسته',
        'cancelled' => 'لغوشده',
    ];

    public const PRIORITIES = [
        'low' => 'کم',
        'normal' => 'عادی',
        'high' => 'بالا',
        'urgent' => 'فوری',
    ];

    public static function canCreate(): bool
    {
        return Auth::isAdmin() || Auth::can('ticketing.create') || Auth::can('ticketing.view') || Auth::can('dashboard');
    }

    public static function canManage(): bool
    {
        return Auth::isAdmin() || Auth::can('ticketing.manage', 'edit') || Auth::can('ticketing.manage');
    }

    public static function canManageCategories(): bool
    {
        return self::canManage() || Auth::can('ticketing.categories', 'edit') || Auth::can('ticketing.categories');
    }

    public static function canManageSettings(): bool
    {
        return self::canManage() || Auth::can('ticketing.settings', 'edit') || Auth::can('ticketing.settings');
    }

    public static function categories(bool $activeOnly = true): array
    {
        return Database::fetchAll(
            'SELECT c.*,u.title unit_title,a.name assignee_name
             FROM ticket_categories c
             LEFT JOIN org_units u ON u.id=c.assigned_unit_id
             LEFT JOIN users a ON a.id=c.default_assignee_user_id' .
            ($activeOnly ? ' WHERE c.active=1' : '') .
            ' ORDER BY c.sort_order,c.title'
        );
    }

    public static function list(array $filters = []): array
    {
        $user = Auth::user();
        if (!$user) return [];

        [$scopeSql, $params] = self::scopeSql($user);
        $where = [$scopeSql];

        foreach ([
            'status' => 't.status',
            'priority' => 't.priority',
            'category_id' => 't.category_id',
            'assigned_user_id' => 't.assigned_user_id',
            'assigned_unit_id' => 't.assigned_unit_id',
            'requester_user_id' => 't.requester_user_id',
        ] as $key => $column) {
            if (trim((string)($filters[$key] ?? '')) !== '') {
                $where[] = "$column=?";
                $params[] = $filters[$key];
            }
        }

        if (!empty($filters['overdue'])) {
            $where[] = 't.due_at IS NOT NULL AND t.due_at<NOW() AND t.status NOT IN("resolved","closed","cancelled")';
        }

        if (trim((string)($filters['q'] ?? '')) !== '') {
            $where[] = '(t.ticket_no LIKE ? OR t.subject LIKE ? OR r.name LIKE ?)';
            $q = '%' . self::text($filters['q'], 100) . '%';
            array_push($params, $q, $q, $q);
        }

        return Database::fetchAll(
            'SELECT t.*,c.title category_title,r.name requester_name,a.name assignee_name,u.title unit_title,
                    IF(t.due_at IS NOT NULL AND t.due_at<NOW() AND t.status NOT IN("resolved","closed","cancelled"),1,0) is_overdue,
                    TIMESTAMPDIFF(MINUTE,NOW(),t.due_at) due_minutes_left,
                    (SELECT COUNT(*) FROM ticket_messages m WHERE m.ticket_id=t.id) message_count
             FROM tickets t
             JOIN ticket_categories c ON c.id=t.category_id
             JOIN users r ON r.id=t.requester_user_id
             LEFT JOIN users a ON a.id=t.assigned_user_id
             LEFT JOIN org_units u ON u.id=t.assigned_unit_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY is_overdue DESC,
                      FIELD(t.status,"open","assigned","in_progress","waiting_admin","waiting_user","resolved","closed","cancelled"),
                      FIELD(t.priority,"urgent","high","normal","low"),
                      COALESCE(t.due_at,"9999-12-31"),t.id DESC',
            $params
        );
    }

    public static function dashboardStats(array $filters = []): array
    {
        $rows = self::list($filters);
        $stats = ['total'=>0,'open'=>0,'in_progress'=>0,'waiting'=>0,'resolved'=>0,'closed'=>0,'overdue'=>0,'urgent'=>0,'unassigned'=>0];
        foreach ($rows as $row) {
            $stats['total']++;
            if (in_array($row['status'], ['open','assigned'], true)) $stats['open']++;
            if ($row['status'] === 'in_progress') $stats['in_progress']++;
            if (in_array($row['status'], ['waiting_user','waiting_admin'], true)) $stats['waiting']++;
            if ($row['status'] === 'resolved') $stats['resolved']++;
            if ($row['status'] === 'closed') $stats['closed']++;
            if ((int)($row['is_overdue'] ?? 0) === 1) $stats['overdue']++;
            if ($row['priority'] === 'urgent') $stats['urgent']++;
            if (empty($row['assigned_user_id']) && empty($row['assigned_unit_id'])) $stats['unassigned']++;
        }
        return $stats;
    }

    public static function find(int $id): ?array
    {
        foreach (self::list() as $row) {
            if ((int)$row['id'] !== $id) continue;
            $row['messages'] = Database::fetchAll(
                'SELECT m.*,u.name user_name
                 FROM ticket_messages m
                 JOIN users u ON u.id=m.user_id
                 WHERE m.ticket_id=?' . (self::canManage() ? '' : ' AND m.is_internal=0') . '
                 ORDER BY m.id',
                [$id]
            );
            $row['attachments'] = Database::fetchAll('SELECT * FROM ticket_attachments WHERE ticket_id=? ORDER BY id', [$id]);
            $row['logs'] = Database::fetchAll('SELECT l.*,u.name actor_name FROM ticket_status_logs l LEFT JOIN users u ON u.id=l.actor_user_id WHERE l.ticket_id=? ORDER BY l.id DESC', [$id]);
            return $row;
        }
        return null;
    }

    public static function create(array $input, int $userId): int
    {
        if (!self::canCreate()) throw new DomainException('دسترسی ثبت تیکت برای شما فعال نیست.');
        $subject = self::text($input['subject'] ?? '', 255);
        $message = self::text($input['message'] ?? '', 20000);
        $categoryId = (int)($input['category_id'] ?? 0);
        $category = Database::fetch('SELECT * FROM ticket_categories WHERE id=? AND active=1', [$categoryId]);
        if ($subject === '' || $message === '' || !$category) throw new InvalidArgumentException('عنوان، متن و دسته‌بندی معتبر الزامی است.');

        $priority = in_array($input['priority'] ?? '', array_keys(self::PRIORITIES), true) ? $input['priority'] : 'normal';
        $assigned = (int)($input['assigned_user_id'] ?? 0) ?: ((int)($category['default_assignee_user_id'] ?? 0) ?: null);
        $unit = (int)($input['assigned_unit_id'] ?? 0) ?: ((int)($category['assigned_unit_id'] ?? 0) ?: null);
        if ($assigned && !Database::fetch('SELECT id FROM users WHERE id=? AND status="active"', [$assigned])) throw new InvalidArgumentException('عضو گیرنده معتبر نیست.');
        if ($unit && !Database::fetch('SELECT id FROM org_units WHERE id=? AND active=1', [$unit])) throw new InvalidArgumentException('واحد گیرنده معتبر نیست.');
        $status = ($assigned || $unit) ? 'assigned' : 'open';
        $sla = Database::fetch('SELECT resolution_minutes FROM ticket_sla_rules WHERE active=1 AND priority=? AND (category_id=? OR category_id IS NULL) ORDER BY category_id IS NULL LIMIT 1', [$priority, $categoryId]);
        $due = $sla ? date('Y-m-d H:i:s', time() + (int)$sla['resolution_minutes'] * 60) : null;

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            Database::execute(
                'INSERT INTO tickets(subject,category_id,priority,requester_user_id,assigned_user_id,assigned_unit_id,due_at,status,last_message_at,created_at,updated_at)
                 VALUES(?,?,?,?,?,?,?,?,NOW(),NOW(),NOW())',
                [$subject,$categoryId,$priority,$userId,$assigned,$unit,$due,$status]
            );
            $id = (int)Database::lastInsertId();
            $number = 'T-' . date('ym') . '-' . str_pad((string)$id, 6, '0', STR_PAD_LEFT);
            Database::execute('UPDATE tickets SET ticket_no=? WHERE id=?', [$number,$id]);
            Database::execute('INSERT INTO ticket_messages(ticket_id,user_id,message,is_internal,created_at) VALUES(?,?,?,0,NOW())', [$id,$userId,$message]);
            Database::execute('INSERT INTO ticket_status_logs(ticket_id,actor_user_id,old_status,new_status,note,created_at) VALUES(?,?,NULL,?,"ایجاد تیکت",NOW())', [$id,$userId,$status]);
            if ($assigned || $unit) {
                Database::execute('INSERT INTO ticket_assignments(ticket_id,assigned_user_id,assigned_unit_id,assigned_by,note,created_at) VALUES(?,?,?,?,?,NOW())', [$id,$assigned,$unit,$userId,'ارجاع هنگام ثبت تیکت']);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }

        self::notifyTicketAudience($id, $subject, $userId, $assigned, $unit, 'created');
        Auth::log($userId, 'ticket_created', 'tickets', $id);
        return $id;
    }

    public static function reply(int $id, string $message, int $userId, bool $internal = false): int
    {
        $ticket = self::find($id);
        if (!$ticket) throw new DomainException('تیکت در دسترس نیست.');
        if ($internal && !self::canManage()) throw new DomainException('یادداشت داخلی مجاز نیست.');
        $message = self::text($message, 20000);
        if ($message === '') throw new InvalidArgumentException('متن پاسخ الزامی است.');

        Database::execute('INSERT INTO ticket_messages(ticket_id,user_id,message,is_internal,created_at) VALUES(?,?,?,?,NOW())', [$id,$userId,$message,$internal?1:0]);
        $messageId = (int)Database::lastInsertId();
        if (!$internal) {
            $nextStatus = $userId === (int)$ticket['requester_user_id'] ? 'waiting_admin' : 'waiting_user';
            Database::execute('UPDATE tickets SET last_message_at=NOW(),status=IF(status IN("resolved","closed","cancelled"),status,?),updated_at=NOW() WHERE id=?', [$nextStatus,$id]);
            $targets = $userId === (int)$ticket['requester_user_id'] ? self::ticketAudience((int)($ticket['assigned_user_id'] ?? 0), (int)($ticket['assigned_unit_id'] ?? 0), $userId) : [(int)$ticket['requester_user_id']];
            foreach ($targets as $target) if ($target && $target !== $userId) self::notify(static fn() => NotificationService::notifyTicketReply($target, $id, $ticket['subject']));
        }
        Auth::log($userId, 'ticket_reply', 'tickets', $id);
        return $messageId;
    }

    public static function manage(int $id, array $input, int $actorId): void
    {
        if (!self::canManage()) throw new DomainException('مدیریت تیکت مجاز نیست.');
        $ticket = self::find($id);
        if (!$ticket) throw new DomainException('تیکت پیدا نشد.');

        $status = in_array($input['status'] ?? '', array_keys(self::STATUSES), true) ? $input['status'] : $ticket['status'];
        $assigned = (int)($input['assigned_user_id'] ?? 0) ?: null;
        $unit = (int)($input['assigned_unit_id'] ?? 0) ?: null;
        $due = trim((string)($input['due_at'] ?? '')) ?: null;
        $note = self::text($input['note'] ?? '', 2000);

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            Database::execute(
                'UPDATE tickets SET status=?,assigned_user_id=?,assigned_unit_id=?,due_at=?,resolved_at=IF(?="resolved",COALESCE(resolved_at,NOW()),resolved_at),closed_at=IF(?="closed",COALESCE(closed_at,NOW()),closed_at),updated_at=NOW() WHERE id=?',
                [$status,$assigned,$unit,$due,$status,$status,$id]
            );
            if ($status !== $ticket['status']) {
                Database::execute('INSERT INTO ticket_status_logs(ticket_id,actor_user_id,old_status,new_status,note,created_at) VALUES(?,?,?,?,?,NOW())', [$id,$actorId,$ticket['status'],$status,$note]);
            }
            if ($assigned !== (int)($ticket['assigned_user_id'] ?? 0) || $unit !== (int)($ticket['assigned_unit_id'] ?? 0)) {
                Database::execute('UPDATE ticket_assignments SET ended_at=COALESCE(ended_at,NOW()) WHERE ticket_id=? AND ended_at IS NULL', [$id]);
                Database::execute('INSERT INTO ticket_assignments(ticket_id,assigned_user_id,assigned_unit_id,assigned_by,note,created_at) VALUES(?,?,?,?,?,NOW())', [$id,$assigned,$unit,$actorId,$note]);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }

        if ($assigned !== (int)($ticket['assigned_user_id'] ?? 0) || $unit !== (int)($ticket['assigned_unit_id'] ?? 0)) self::notifyTicketAudience($id, $ticket['subject'], $actorId, $assigned, $unit, 'reassigned');
        if ($status !== $ticket['status']) self::notify(static fn() => NotificationService::notifyTicketStatusChanged((int)$ticket['requester_user_id'], $id, self::STATUSES[$status]));
        Auth::log($actorId, 'ticket_managed', 'tickets', $id);
    }

    public static function addAttachment(int $ticketId, int $messageId, int $userId, array $file): void
    {
        $ticket = self::find($ticketId);
        if (!$ticket) throw new DomainException('تیکت در دسترس نیست.');
        Database::execute(
            'INSERT INTO ticket_attachments(ticket_id,message_id,uploaded_by,original_name,file_path,mime_type,file_size,created_at) VALUES(?,?,?,?,?,?,?,NOW())',
            [$ticketId,$messageId?:null,$userId,$file['original_name'],$file['file_path'],$file['mime_type'],$file['file_size']]
        );
    }

    private static function scopeSql(array $user): array
    {
        if (self::canManage()) return ['1=1', []];
        $conditions = ['t.requester_user_id=?', 't.assigned_user_id=?'];
        $params = [(int)$user['id'], (int)$user['id']];
        if ((int)($user['org_unit_id'] ?? 0) > 0) {
            $conditions[] = 't.assigned_unit_id=?';
            $params[] = (int)$user['org_unit_id'];
        }
        return ['(' . implode(' OR ', $conditions) . ')', $params];
    }

    private static function notifyTicketAudience(int $ticketId, string $subject, int $actorId, ?int $assigned, ?int $unit, string $mode): void
    {
        foreach (self::ticketAudience($assigned, $unit, $actorId) as $target) {
            if ($target === $actorId) continue;
            self::notify(static function () use ($target, $ticketId, $subject, $mode) {
                if ($mode === 'reassigned') NotificationService::notifyTicketReassigned($target, $ticketId, $subject);
                else NotificationService::notifyTicketAssigned($target, $ticketId, $subject);
            });
        }
    }

    private static function ticketAudience(?int $assigned, ?int $unit, int $excludeUserId = 0): array
    {
        $ids = [];
        if ($assigned) $ids[] = $assigned;
        if ($unit) {
            $rows = Database::fetchAll('SELECT id FROM users WHERE status="active" AND org_unit_id=?', [$unit]);
            foreach ($rows as $row) $ids[] = (int)$row['id'];
        }
        if (!$ids) {
            $admins = Database::fetchAll('SELECT id FROM users WHERE status="active" AND role IN("admin","super_admin")');
            foreach ($admins as $row) $ids[] = (int)$row['id'];
        }
        return array_values(array_unique(array_filter(array_map('intval', $ids), static fn($id) => $id > 0 && $id !== $excludeUserId)));
    }

    private static function notify(callable $callback): void
    {
        try { $callback(); } catch (Throwable $e) { error_log('Ticket notification: ' . $e->getMessage()); }
    }

    private static function text(mixed $value, int $max): string
    {
        $value = trim(strip_tags((string)$value));
        return mb_substr($value, 0, $max, 'UTF-8');
    }
}
