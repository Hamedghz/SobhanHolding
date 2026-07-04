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
        if (self::isAdmin($user)) return array_map('intval', array_column(Database::fetchAll('SELECT id FROM users'), 'id'));

        $ids = [$userId => true];
        $context = self::userContext($userId) ?: $user;
        $isManager = ($user['role'] ?? '') === 'manager' || in_array($context['org_role_type'] ?? '', ['manager', 'supervisor'], true);
        if (!$isManager) return [$userId];

        foreach (self::teamUserIds($userId) as $id) $ids[$id] = true;

        if (($user['access_scope'] ?? '') === 'unit' && (int)($user['org_unit_id'] ?? 0) > 0) {
            foreach (self::unitUserIds((int)$user['org_unit_id']) as $id) $ids[$id] = true;
        }
        if (in_array($context['org_role_code'] ?? '', ['SALES_MANAGER', 'SALES_SUPERVISOR'], true) && trim((string)($user['sales_line'] ?? '')) !== '') {
            foreach (self::lineUserIds((string)$user['sales_line']) as $id) $ids[$id] = true;
        }
        return array_keys($ids);
    }

    public static function directReports(int $managerId): array
    {
        if ($managerId <= 0) return [];
        $rows = Database::fetchAll('SELECT DISTINCT u.id FROM users u LEFT JOIN manager_employees me ON me.employee_id=u.id AND me.manager_id=? WHERE u.status="active" AND (u.parent_user_id=? OR u.supervisor_id=? OR u.organization_manager_id=? OR me.manager_id IS NOT NULL)', [$managerId, $managerId, $managerId, $managerId]);
        return array_map('intval', array_column($rows, 'id'));
    }

    public static function teamUserIds(int $managerId, int $maxDepth = 8): array
    {
        $ids = [];
        $frontier = [$managerId];
        for ($depth = 0; $depth < $maxDepth && $frontier; $depth++) {
            $next = [];
            foreach ($frontier as $parentId) {
                foreach (self::directReports((int)$parentId) as $id) {
                    if (!isset($ids[$id]) && $id !== $managerId) { $ids[$id] = true; $next[] = $id; }
                }
            }
            $frontier = $next;
        }
        return array_keys($ids);
    }

    public static function unitUserIds(int $unitId): array
    {
        if ($unitId <= 0) return [];
        return array_map('intval', array_column(Database::fetchAll('SELECT id FROM users WHERE status="active" AND org_unit_id=?', [$unitId]), 'id'));
    }

    public static function lineUserIds(string $salesLine): array
    {
        $salesLine = trim($salesLine);
        if ($salesLine === '') return [];
        return array_map('intval', array_column(Database::fetchAll('SELECT id FROM users WHERE status="active" AND sales_line=?', [$salesLine]), 'id'));
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
        return Database::fetch('SELECT u.*,ou.title org_unit_title,ou.code org_unit_code,ou.unit_type,orr.title org_role_title,orr.code org_role_code,orr.role_type org_role_type,p.name parent_name FROM users u LEFT JOIN org_units ou ON ou.id=u.org_unit_id LEFT JOIN org_roles orr ON orr.id=u.org_role_id LEFT JOIN users p ON p.id=u.parent_user_id WHERE u.id=?', [$userId]);
    }

    public static function scopeSql(array $user, string $column = 'u.id'): array
    {
        $ids = self::accessibleUserIds($user);
        if (!$ids) $ids = [-1];
        return ["{$column} IN (" . implode(',', array_fill(0, count($ids), '?')) . ')', $ids];
    }
}
