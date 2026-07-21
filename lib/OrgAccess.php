<?php

require_once __DIR__ . '/../core/Database.php';

class OrgAccess
{
    public static function isAdmin(array $user): bool
    {
        return in_array($user['role'] ?? '', ['admin', 'super_admin'], true);
    }

    public static function isSuperAdmin(array $user): bool
    {
        return ($user['role'] ?? '') === 'super_admin';
    }

    public static function accessibleUserIds(array $user): array
    {
        $userId = (int)($user['id'] ?? 0);
        if ($userId <= 0) return [];
        if (self::isAdmin($user)) return array_map('intval', array_column(Database::fetchAll('SELECT id FROM users WHERE status="active"'), 'id'));

        $ids = [$userId => true];
        $frontier = [$userId];
        for ($depth = 0; $depth < 4 && $frontier; $depth++) {
            $placeholders = implode(',', array_fill(0, count($frontier), '?'));
            $rows = Database::fetchAll(
                "SELECT id FROM users
                 WHERE status='active'
                   AND (parent_user_id IN ({$placeholders}) OR supervisor_id IN ({$placeholders}))",
                array_merge($frontier, $frontier)
            );
            $next = [];
            foreach ($rows as $row) {
                $id = (int)$row['id'];
                if (!isset($ids[$id])) { $ids[$id] = true; $next[] = $id; }
            }
            $frontier = $next;
        }

        foreach (Database::fetchAll('SELECT employee_id FROM manager_employees WHERE manager_id=?', [$userId]) as $row) $ids[(int)$row['employee_id']] = true;
        if (Database::tableExists('sales_team_assignments')) {
            foreach (Database::fetchAll('SELECT visitor_id FROM sales_team_assignments WHERE supervisor_id=? AND active=1', [$userId]) as $row) {
                $ids[(int)$row['visitor_id']] = true;
            }
        }

        if (($user['access_scope'] ?? '') === 'unit' && (int)($user['org_unit_id'] ?? 0) > 0) {
            foreach (Database::fetchAll('SELECT id FROM users WHERE status="active" AND org_unit_id=?', [(int)$user['org_unit_id']]) as $row) $ids[(int)$row['id']] = true;
        }
        return array_keys($ids);
    }

    public static function canAccessUser(array $user, int $targetUserId): bool
    {
        return in_array($targetUserId, self::accessibleUserIds($user), true);
    }

    public static function canAssignScope(array $user, array $targetIds): bool
    {
        if (self::isAdmin($user)) return true;
        $allowed = array_flip(self::accessibleUserIds($user));
        foreach ($targetIds as $id) if (!isset($allowed[(int)$id])) return false;
        return true;
    }

    public static function userContext(int $userId): ?array
    {
        return Database::fetch('SELECT u.*,ou.title org_unit_title,ou.code org_unit_code,ou.unit_type,orr.title org_role_title,orr.code org_role_code,orr.role_type,p.name parent_name FROM users u LEFT JOIN org_units ou ON ou.id=u.org_unit_id LEFT JOIN org_roles orr ON orr.id=u.org_role_id LEFT JOIN users p ON p.id=u.parent_user_id WHERE u.id=?', [$userId]);
    }

    public static function scopeSql(array $user, string $column = 'u.id'): array
    {
        $ids = self::accessibleUserIds($user);
        if (!$ids) $ids = [-1];
        return ["{$column} IN (" . implode(',', array_fill(0, count($ids), '?')) . ')', $ids];
    }
}
