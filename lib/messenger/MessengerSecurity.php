<?php
require_once __DIR__ . '/../../core/Auth.php';

final class MessengerSecurity
{
    public static function can(string $permission, string $action = 'view'): bool
    {
        $user = Auth::user();
        if (!$user) return false;
        if (in_array($user['role'] ?? '', ['admin','super_admin'], true)) return true;
        if ($permission === 'messenger.view') return Auth::can('messenger.view');
        return Auth::can($permission, $action);
    }

    public static function requirePermission(string $permission, string $action = 'view'): void
    {
        if (!self::can($permission, $action)) throw new DomainException('دسترسی لازم برای این عملیات را ندارید.', 403);
    }

    public static function participant(int $conversationId, int $userId): array
    {
        $row = Database::fetch('SELECT p.*,c.type,c.is_readonly,c.is_active,c.deleted_at conversation_deleted FROM chat_participants p JOIN chat_conversations c ON c.id=p.conversation_id WHERE p.conversation_id=? AND p.user_id=? AND p.deleted_at IS NULL AND p.left_at IS NULL', [$conversationId,$userId]);
        if (!$row || !$row['is_active'] || $row['conversation_deleted']) throw new DomainException('گفتگو در دسترس نیست.', 403);
        return $row;
    }

    public static function assertSend(int $conversationId, int $userId): array
    {
        $p = self::participant($conversationId,$userId);
        if (!(int)$p['can_send'] || ((int)$p['is_readonly'] && !in_array($p['participant_role'],['owner','admin','moderator'],true))) throw new DomainException('ارسال پیام در این گفتگو مجاز نیست.',403);
        return $p;
    }

    public static function csrf(): void
    {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['_csrf'] ?? null);
        if (!Auth::verifyCsrf(is_string($token) ? $token : null)) throw new DomainException('نشست امنیتی منقضی شده است.',419);
    }

    public static function rate(string $key, int $limit, int $seconds = 60): void
    {
        $key = substr(hash('sha256',$key),0,64);
        $row = Database::fetch('SELECT * FROM chat_rate_limits WHERE rate_key=?',[$key]);
        if (!$row || strtotime($row['window_started_at']) <= time()-$seconds) {
            Database::execute('INSERT INTO chat_rate_limits(rate_key,hits,window_started_at,updated_at) VALUES(?,1,NOW(),NOW()) ON DUPLICATE KEY UPDATE hits=1,window_started_at=NOW(),updated_at=NOW()',[$key]);
            return;
        }
        if ((int)$row['hits'] >= $limit) throw new DomainException('تعداد درخواست‌ها بیش از حد مجاز است؛ کمی بعد تلاش کنید.',429);
        Database::execute('UPDATE chat_rate_limits SET hits=hits+1,updated_at=NOW() WHERE rate_key=?',[$key]);
    }

    public static function text(mixed $value, int $max): string
    {
        $value = trim(strip_tags((string)$value));
        return function_exists('mb_substr') ? mb_substr($value,0,$max,'UTF-8') : substr($value,0,$max);
    }

    public static function uuid(): string
    {
        $d=random_bytes(16);$d[6]=chr((ord($d[6])&0x0f)|0x40);$d[8]=chr((ord($d[8])&0x3f)|0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s',str_split(bin2hex($d),4));
    }
}
