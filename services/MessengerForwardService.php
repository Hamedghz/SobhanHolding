<?php

require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../lib/NotificationService.php';
require_once __DIR__ . '/SalesReportShareService.php';

class MessengerForwardService
{
    public static function recipientOptions(): array
    {
        return [
            'users' => Database::fetchAll('SELECT id,name,department,sales_line FROM users WHERE status="active" ORDER BY name LIMIT 1000'),
            'groups' => Database::fetchAll('SELECT id,title FROM messenger_groups WHERE active=1 ORDER BY title'),
            'lines' => array_values(array_filter(array_column(Database::fetchAll('SELECT DISTINCT sales_line FROM users WHERE status="active" AND sales_line IS NOT NULL AND sales_line<>"" ORDER BY sales_line'), 'sales_line'))),
        ];
    }

    public static function send(array $input, array $sender): array
    {
        SalesReportShareService::assertCanForward($sender);
        $recipientType = (string)($input['recipient_type'] ?? 'single_user');
        $recipientRef = self::recipientReference($recipientType, $input);
        $recipients = self::resolveRecipients($recipientType, $input);
        if (!$recipients) throw new InvalidArgumentException('حداقل یک گیرنده معتبر انتخاب کنید.');

        $built = SalesReportShareService::build(
            (int)($input['report_id'] ?? 0),
            (string)($input['report_type'] ?? 'summary_cards'),
            is_array($input['filters'] ?? null) ? $input['filters'] : [],
            $sender,
            (string)($input['title'] ?? '')
        );
        $description = sales_snapshot_clean_text((string)($input['description'] ?? ''), 4000);
        $snapshotJson = sales_snapshot_json($built['snapshot']);
        $attachment = !empty($input['include_attachment']) ? SalesReportShareService::createCsv($built['snapshot']) : null;
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            Database::execute('INSERT INTO sales_report_shares(sender_user_id,source_module,source_page,source_report_type,source_record_id,report_title,report_period,filters_json,snapshot_json,attachment_path,attachment_name,attachment_mime,snapshot_hash,created_at) VALUES(?,"manager_dashboard","/admin/manager-dashboard.php",?,?,?,?,?,?,?,?,?,?,NOW())', [
                (int)$sender['id'], $built['type'], (int)$built['report']['id'], $built['snapshot']['title'], $built['snapshot']['period'],
                sales_snapshot_json($built['filters']), $snapshotJson, $attachment['path'] ?? null, $attachment['name'] ?? null,
                $attachment['mime'] ?? null, hash('sha256', $snapshotJson),
            ]);
            $shareId = (int)Database::lastInsertId();
            $payload = sales_snapshot_json(['share_id'=>$shareId,'preview_text'=>self::preview($built['snapshot']),'attachment_path'=>$attachment['path'] ?? null,'created_at'=>date('c')]);
            Database::execute('INSERT INTO messenger_messages(sender_user_id,message_type,title,body,payload_json,created_at,updated_at) VALUES(?,"forwarded_report",?,?,?,?,NOW())', [
                (int)$sender['id'], $built['snapshot']['title'], $description, $payload, date('Y-m-d H:i:s'),
            ]);
            $messageId = (int)Database::lastInsertId();
            $stmt = $pdo->prepare('INSERT IGNORE INTO messenger_message_recipients(message_id,user_id,status,created_at) VALUES(?,? ,"unread",NOW())');
            foreach ($recipients as $recipientId) $stmt->execute([$messageId, $recipientId]);
            Database::execute('INSERT INTO messenger_forwarded_reports(message_id,share_id,sender_user_id,recipient_type,recipient_id,created_at) VALUES(?,?,?,?,?,NOW())', [$messageId,$shareId,(int)$sender['id'],$recipientType,$recipientRef]);
            self::log($shareId,$messageId,(int)$sender['id'],'forward_created','success',['recipient_count'=>count($recipients),'recipient_type'=>$recipientType]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('sales report forward transaction: '.$e->getMessage());
            throw new RuntimeException('forward_failed', 0, $e);
        }

        foreach ($recipients as $recipientId) {
            try {
                NotificationService::notifyForwardedReport($recipientId, $messageId, $shareId, (string)$sender['name']);
            } catch (Throwable $e) { error_log('forward notification: '.$e->getMessage()); }
        }
        Auth::log((int)$sender['id'],'forwarded_report_sent','messenger',$messageId);
        return ['share_id'=>$shareId,'message_id'=>$messageId,'recipient_count'=>count($recipients)];
    }

    private static function preview(array $snapshot): string
    {
        if (!empty($snapshot['summary_cards'])) return implode(' | ', array_map(static fn($card) => $card['label'].': '.$card['value'], array_slice($snapshot['summary_cards'],0,3)));
        if (!empty($snapshot['table']['rows'])) return count($snapshot['table']['rows']).' ردیف گزارش ثبت شده است.';
        if (!empty($snapshot['ai_analysis'])) return mb_substr((string)$snapshot['ai_analysis'],0,240);
        return 'Snapshot فقط‌خواندنی گزارش فروش';
    }

    private static function recipientReference(string $type, array $input): string
    {
        return match ($type) {
            'single_user' => (string)(int)($input['recipient_id'] ?? 0),
            'multiple_users' => implode(',', array_map('intval', (array)($input['recipient_ids'] ?? []))),
            'group' => (string)(int)($input['group_id'] ?? 0),
            'sales_line' => sales_snapshot_clean_text((string)($input['sales_line'] ?? ''), 50),
            'supervisors', 'managers' => $type,
            default => '',
        };
    }

    private static function resolveRecipients(string $type, array $input): array
    {
        $ids = [];
        if ($type === 'single_user') $ids = [(int)($input['recipient_id'] ?? 0)];
        elseif ($type === 'multiple_users') $ids = array_map('intval', (array)($input['recipient_ids'] ?? []));
        elseif ($type === 'group') $ids = array_map('intval', array_column(Database::fetchAll('SELECT gm.user_id FROM messenger_group_members gm JOIN messenger_groups g ON g.id=gm.group_id WHERE gm.group_id=? AND g.active=1', [(int)($input['group_id'] ?? 0)]), 'user_id'));
        elseif ($type === 'sales_line') $ids = array_map('intval', array_column(Database::fetchAll('SELECT id FROM users WHERE status="active" AND sales_line=?', [sales_snapshot_clean_text((string)($input['sales_line'] ?? ''),50)]), 'id'));
        elseif ($type === 'supervisors') $ids = array_map('intval', array_column(Database::fetchAll('SELECT DISTINCT u.id FROM users u LEFT JOIN org_roles r ON r.id=u.org_role_id WHERE u.status="active" AND (LOWER(COALESCE(u.role_key,"")) LIKE "%supervisor%" OR LOWER(COALESCE(r.code,"")) LIKE "%supervisor%")'), 'id'));
        elseif ($type === 'managers') $ids = array_map('intval', array_column(Database::fetchAll('SELECT DISTINCT u.id FROM users u LEFT JOIN org_roles r ON r.id=u.org_role_id WHERE u.status="active" AND (u.role IN ("manager","admin","super_admin") OR LOWER(COALESCE(u.role_key,"")) LIKE "%manager%" OR LOWER(COALESCE(r.role_type,""))="manager")'), 'id'));
        else throw new InvalidArgumentException('نوع گیرنده معتبر نیست.');
        $ids = array_values(array_unique(array_filter($ids, static fn($id) => $id > 0)));
        if (!$ids) return [];
        $active = Database::fetchAll('SELECT id FROM users WHERE status="active" AND id IN ('.implode(',',array_fill(0,count($ids),'?')).')', $ids);
        return array_map('intval', array_column($active,'id'));
    }

    public static function inbox(int $userId, int $limit = 100): array
    {
        $limit = max(1,min(250,$limit));
        return Database::fetchAll("SELECT m.*,mr.status,mr.read_at,u.name sender_name,fr.share_id,s.report_period,s.attachment_path FROM messenger_message_recipients mr JOIN messenger_messages m ON m.id=mr.message_id JOIN users u ON u.id=m.sender_user_id LEFT JOIN messenger_forwarded_reports fr ON fr.message_id=m.id LEFT JOIN sales_report_shares s ON s.id=fr.share_id WHERE mr.user_id=? ORDER BY m.id DESC LIMIT {$limit}", [$userId]);
    }

    public static function shareForUser(int $shareId, array $user): ?array
    {
        $params = [$shareId];
        $access = 's.id=? AND (s.sender_user_id=? OR EXISTS(SELECT 1 FROM messenger_forwarded_reports fr JOIN messenger_message_recipients mr ON mr.message_id=fr.message_id WHERE fr.share_id=s.id AND mr.user_id=?))';
        $params[] = (int)$user['id']; $params[] = (int)$user['id'];
        if (in_array($user['role'] ?? '', ['admin','super_admin'], true)) { $access = 's.id=?'; $params = [$shareId]; }
        $share = Database::fetch("SELECT s.*,u.name sender_name,m.id message_id,m.body description,m.created_at sent_at FROM sales_report_shares s JOIN users u ON u.id=s.sender_user_id LEFT JOIN messenger_forwarded_reports fr ON fr.share_id=s.id LEFT JOIN messenger_messages m ON m.id=fr.message_id WHERE {$access} LIMIT 1", $params);
        if (!$share) return null;
        $snapshot = json_decode((string)$share['snapshot_json'], true);
        if (!is_array($snapshot) || !hash_equals((string)$share['snapshot_hash'], hash('sha256',(string)$share['snapshot_json']))) throw new RuntimeException('snapshot_integrity_failed');
        $share['snapshot'] = $snapshot;
        return $share;
    }

    public static function markRead(int $messageId, int $userId): void
    {
        Database::execute('UPDATE messenger_message_recipients SET status="read",read_at=COALESCE(read_at,NOW()) WHERE message_id=? AND user_id=?', [$messageId,$userId]);
    }

    public static function attachment(int $shareId, array $user): array
    {
        $share = self::shareForUser($shareId,$user);
        if (!$share || empty($share['attachment_path'])) throw new RuntimeException('attachment_not_found');
        $relative = str_replace('\\','/',trim((string)$share['attachment_path'],'/'));
        if ($relative==='' || str_contains($relative,'..') || !str_starts_with($relative,'messenger-reports/')) throw new RuntimeException('attachment_path_invalid');
        $root = realpath(dirname(__DIR__).'/uploads/messenger-reports');
        $path = realpath(dirname(__DIR__).'/uploads/'.$relative);
        if (!$root || !$path || !is_file($path) || is_link($path) || !str_starts_with(strtolower($path),strtolower($root.DIRECTORY_SEPARATOR))) throw new RuntimeException('attachment_not_found');
        return ['path'=>$path,'name'=>(string)($share['attachment_name'] ?: basename($path)),'mime'=>(string)($share['attachment_mime'] ?: 'application/octet-stream')];
    }

    private static function log(?int $shareId, ?int $messageId, ?int $actorId, string $action, string $status, array $details=[]): void
    {
        Database::execute('INSERT INTO messenger_forward_logs(share_id,message_id,actor_user_id,action,status,details_json,ip_address,created_at) VALUES(?,?,?,?,?,?,?,NOW())', [$shareId,$messageId,$actorId,$action,$status,sales_snapshot_json($details),mb_substr((string)($_SERVER['REMOTE_ADDR']??''),0,45)]);
    }
}
