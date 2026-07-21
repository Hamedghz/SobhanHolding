<?php
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../lib/NotificationService.php';

final class OkrReminderService
{
    public static function runMaintenance(int $limit = 500): array
    {
        $limit = max(1, min(2000, $limit));
        $rows = Database::fetchAll(
            'SELECT o.id,o.title,o.owner_user_id,o.status,o.health_status,o.due_date,o.start_date,c.checkin_frequency,
             (SELECT MAX(ci.created_at) FROM okr_checkins ci WHERE ci.objective_id=o.id) last_checkin_at
             FROM okr_objectives o JOIN okr_cycles c ON c.id=o.cycle_id
             WHERE o.status IN ("active","at_risk","off_track") AND c.status IN ("open","active")
             ORDER BY o.due_date,o.id LIMIT ' . $limit
        );
        $summary = ['objectives'=>count($rows),'checkin_reminders'=>0,'due_reminders'=>0,'risk_alerts'=>0];
        foreach ($rows as $objective) {
            $recipients = self::recipients((int)$objective['id'], (int)$objective['owner_user_id']);
            if (self::checkinDue($objective)) {
                $key = self::periodKey((string)$objective['checkin_frequency']);
                foreach ($recipients as $recipientId) {
                    if (self::notifyOnce($objective, $recipientId, 'checkin', $key, 'okr_checkin_reminder', 'یادآوری Check-in هدف', 'برای هدف «'.$objective['title'].'» Check-in جدید ثبت کنید.', 'high')) {
                        $summary['checkin_reminders']++;
                    }
                }
            }
            $days = (int)floor((strtotime((string)$objective['due_date']) - strtotime(date('Y-m-d'))) / 86400);
            if ($days >= 0 && $days <= 3) {
                foreach ($recipients as $recipientId) {
                    if (self::notifyOnce($objective, $recipientId, 'due_date', (string)$objective['due_date'], 'okr_due_date_reminder', 'مهلت OKR نزدیک است', 'تا پایان مهلت هدف «'.$objective['title'].'» '.$days.' روز باقی مانده است.', 'urgent')) {
                        $summary['due_reminders']++;
                    }
                }
            }
            if (in_array($objective['health_status'], ['at_risk','off_track'], true)) {
                foreach ($recipients as $recipientId) {
                    if (self::notifyOnce($objective, $recipientId, 'risk', date('o-W'), 'okr_risk_alert', 'هشدار وضعیت OKR', 'هدف «'.$objective['title'].'» نیازمند بررسی و اقدام اصلاحی است.', 'urgent')) {
                        $summary['risk_alerts']++;
                    }
                }
            }
        }
        return $summary;
    }

    private static function recipients(int $objectiveId, int $ownerId): array
    {
        $ids = [$ownerId];
        foreach (Database::fetchAll('SELECT DISTINCT owner_user_id FROM okr_key_results WHERE objective_id=? AND status<>"cancelled"', [$objectiveId]) as $row) {
            $ids[] = (int)$row['owner_user_id'];
        }
        return array_values(array_unique(array_filter($ids)));
    }

    private static function checkinDue(array $objective): bool
    {
        $frequency = (string)($objective['checkin_frequency'] ?? 'weekly');
        if ($frequency === 'none') return false;
        $days = $frequency === 'monthly' ? 30 : 7;
        $base = (string)($objective['last_checkin_at'] ?: $objective['start_date']);
        return strtotime($base . ' +' . $days . ' days') <= time();
    }

    private static function periodKey(string $frequency): string
    {
        return $frequency === 'monthly' ? date('Y-m') : date('o-W');
    }

    private static function notifyOnce(array $objective, int $recipientId, string $type, string $key, string $event, string $title, string $body, string $priority): bool
    {
        if (Database::fetch('SELECT id FROM okr_reminder_logs WHERE objective_id=? AND recipient_user_id=? AND reminder_type=? AND reminder_key=?', [(int)$objective['id'],$recipientId,$type,$key])) {
            return false;
        }
        $notificationId = NotificationService::safeNotify(static fn() => NotificationService::create(
            $recipientId,
            $event,
            $title,
            $body,
            '/admin/okr-objective.php?id=' . (int)$objective['id'],
            ['module'=>'okr','related_type'=>'okr_objective','related_id'=>(int)$objective['id'],'priority'=>$priority,'safe_push_body'=>'یک یادآوری جدید برای OKR شما ثبت شد.']
        ));
        try {
            Database::execute(
                'INSERT INTO okr_reminder_logs(objective_id,key_result_id,recipient_user_id,reminder_type,reminder_key,notification_id,sent_at) VALUES (?,NULL,?,?,?,?,NOW())',
                [(int)$objective['id'],$recipientId,$type,$key,is_int($notificationId) ? $notificationId : null]
            );
            return true;
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'Duplicate')) return false;
            throw $e;
        }
    }
}
