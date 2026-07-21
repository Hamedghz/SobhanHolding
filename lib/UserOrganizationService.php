<?php

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/OrgModule.php';
require_once __DIR__ . '/../core/SalesStructureModule.php';

class UserOrganizationService
{
    public static function normalizeKaraSystemCode(?string $value): ?string
    {
        $value = strtr(trim((string)$value), [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);
        if ($value === '') return null;
        $length = function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
        if ($length > 100) {
            throw new InvalidArgumentException('کد سیستم کارا نباید بیشتر از ۱۰۰ نویسه باشد.');
        }
        return $value;
    }

    public static function activeLines(): array
    {
        return Database::fetchAll(
            "SELECT sl.id,sl.code,sl.title,sl.manager_user_id,sl.supervisor_user_id,
                    manager.name manager_name,supervisor.name supervisor_name
             FROM sales_lines sl
             LEFT JOIN users manager ON manager.id=sl.manager_user_id
             LEFT JOIN users supervisor ON supervisor.id=sl.supervisor_user_id
             WHERE sl.active=1
             ORDER BY sl.sort_order,sl.code"
        );
    }

    public static function activeGeographies(): array
    {
        return Database::fetchAll(
            "SELECT g.id,g.code,g.title,g.type,g.parent_id,p.title parent_title
             FROM sales_geographies g
             LEFT JOIN sales_geographies p ON p.id=g.parent_id
             WHERE g.active=1
             ORDER BY COALESCE(p.sort_order,g.sort_order),g.parent_id IS NOT NULL,g.sort_order,g.title"
        );
    }

    public static function primaryGeographyId(int $userId): ?int
    {
        $row = Database::fetch(
            'SELECT geography_id FROM sales_visitor_territories WHERE visitor_user_id=? AND active=1 ORDER BY is_primary DESC,id LIMIT 1',
            [$userId]
        );
        return $row ? (int)$row['geography_id'] : null;
    }

    public static function validateAssignment(array $input, ?int $userId = null, bool $requireVisitorRegion = true): array
    {
        $orgUnitId = (int)($input['org_unit_id'] ?? 0) ?: null;
        $orgRoleId = (int)($input['org_role_id'] ?? 0) ?: null;
        $lineId = (int)($input['sales_line_id'] ?? 0) ?: null;
        $selectedSupervisorId = (int)($input['supervisor_id'] ?? 0) ?: null;
        $selectedManagerId = (int)($input['organization_manager_id'] ?? 0) ?: null;
        $geographyId = (int)($input['primary_geography_id'] ?? 0) ?: null;

        $unit = $orgUnitId
            ? Database::fetch('SELECT id,title,unit_type FROM org_units WHERE id=? AND active=1', [$orgUnitId])
            : null;
        $role = $orgRoleId
            ? Database::fetch('SELECT id,title,code,is_sales_role FROM org_roles WHERE id=? AND active=1', [$orgRoleId])
            : null;

        if ($orgUnitId && !$unit) throw new InvalidArgumentException('واحد سازمانی معتبر نیست.');
        if ($orgRoleId && !$role) throw new InvalidArgumentException('نقش سازمانی معتبر نیست.');

        $roleCode = (string)($role['code'] ?? '');
        $isSalesUnit = $orgUnitId ? OrgModule::salesBranch($orgUnitId) : false;
        $result = [
            'department' => (string)($unit['title'] ?? ''),
            'role_key' => $roleCode,
            'role_code' => $roleCode,
            'is_sales_unit' => $isSalesUnit,
            'sales_line_id' => null,
            'sales_line' => '',
            'supervisor_id' => null,
            'organization_manager_id' => null,
            'parent_user_id' => (int)($input['parent_user_id'] ?? 0) ?: null,
            'primary_geography_id' => null,
        ];
        self::assertNoCentralLineOwnership($userId, $roleCode);

        if (in_array($roleCode, ['SALES_MANAGER', 'SALES_SUPERVISOR', 'VISITOR'], true) && !$isSalesUnit) {
            throw new InvalidArgumentException('نقش‌های فروش فقط در واحد یا زیرشاخه فروش قابل انتخاب هستند.');
        }

        if (!$isSalesUnit || !in_array($roleCode, ['SALES_MANAGER', 'SALES_SUPERVISOR', 'VISITOR'], true)) {
            return $result;
        }

        if ($roleCode === 'SALES_MANAGER') {
            $result['parent_user_id'] = null;
            return $result;
        }

        if (!$lineId) {
            throw new InvalidArgumentException('برای ویزیتور و سرپرست فروش، انتخاب لاین مرکزی الزامی است.');
        }
        $line = Database::fetch(
            'SELECT id,code,title,manager_user_id,supervisor_user_id FROM sales_lines WHERE id=? AND active=1',
            [$lineId]
        );
        if (!$line) throw new InvalidArgumentException('لاین فروش انتخاب‌شده فعال یا معتبر نیست.');

        $lineManagerId = (int)($line['manager_user_id'] ?? 0) ?: null;
        $lineSupervisorId = (int)($line['supervisor_user_id'] ?? 0) ?: null;

        if ($roleCode === 'SALES_SUPERVISOR') {
            $managerId = $selectedManagerId ?: $lineManagerId;
            if (!$managerId) throw new InvalidArgumentException('برای این لاین ابتدا مدیر فروش را انتخاب کنید.');
            self::assertActiveSalesRole($managerId, 'SALES_MANAGER', 'مدیر فروش انتخاب‌شده معتبر نیست.');
            if ($lineManagerId && $lineManagerId !== $managerId) {
                throw new InvalidArgumentException('مدیر فروش انتخاب‌شده با مدیر ثبت‌شده لاین یکسان نیست.');
            }
            if ($lineSupervisorId && (!$userId || $lineSupervisorId !== $userId)) {
                throw new InvalidArgumentException('این لاین قبلاً به سرپرست فروش دیگری تخصیص یافته است.');
            }
            if ($userId && Database::fetch(
                'SELECT id FROM sales_lines WHERE supervisor_user_id=? AND id<>? AND active=1 LIMIT 1',
                [$userId, $lineId]
            )) {
                throw new InvalidArgumentException('این سرپرست در حال حاضر مسئول یک لاین فعال دیگر است.');
            }

            return array_merge($result, [
                'sales_line_id' => $lineId,
                'sales_line' => (string)$line['code'],
                'organization_manager_id' => $managerId,
                'parent_user_id' => $managerId,
            ]);
        }

        if (!$lineSupervisorId) {
            throw new InvalidArgumentException('برای این لاین هنوز سرپرست فروش فعال تعیین نشده است.');
        }
        if (!$lineManagerId) {
            throw new InvalidArgumentException('برای این لاین هنوز مدیر فروش فعال تعیین نشده است.');
        }
        if ($selectedSupervisorId && $selectedSupervisorId !== $lineSupervisorId) {
            throw new InvalidArgumentException('ویزیتور باید به سرپرست ثبت‌شده همان لاین متصل باشد.');
        }
        if ($selectedManagerId && $selectedManagerId !== $lineManagerId) {
            throw new InvalidArgumentException('مدیر فروش انتخاب‌شده با مدیر ثبت‌شده لاین یکسان نیست.');
        }
        $supervisor = self::assertActiveSalesRole(
            $lineSupervisorId,
            'SALES_SUPERVISOR',
            'سرپرست ثبت‌شده لاین فعال یا معتبر نیست.'
        );
        self::assertActiveSalesRole($lineManagerId, 'SALES_MANAGER', 'مدیر ثبت‌شده لاین فعال یا معتبر نیست.');
        $supervisorManagerId = (int)($supervisor['organization_manager_id'] ?? 0)
            ?: ((int)($supervisor['parent_user_id'] ?? 0) ?: null);
        if ($supervisorManagerId !== $lineManagerId) {
            throw new InvalidArgumentException('سرپرست انتخاب‌شده زیرمجموعه مدیر فروش این لاین نیست.');
        }

        if ($requireVisitorRegion && !$geographyId) {
            throw new InvalidArgumentException('برای ویزیتور، انتخاب شهر یا منطقه فروش الزامی است.');
        }
        if ($geographyId && !Database::fetch('SELECT id FROM sales_geographies WHERE id=? AND active=1', [$geographyId])) {
            throw new InvalidArgumentException('شهر یا منطقه فروش انتخاب‌شده معتبر نیست.');
        }

        return array_merge($result, [
            'sales_line_id' => $lineId,
            'sales_line' => (string)$line['code'],
            'supervisor_id' => $lineSupervisorId,
            'organization_manager_id' => $lineManagerId,
            'parent_user_id' => $lineSupervisorId,
            'primary_geography_id' => $geographyId,
        ]);
    }

    public static function applyAssignment(int $userId, array $assignment, ?int $actorId = null): void
    {
        $roleCode = (string)($assignment['role_code'] ?? '');
        $lineId = (int)($assignment['sales_line_id'] ?? 0);

        if ($roleCode === 'SALES_SUPERVISOR' && $lineId > 0) {
            self::deactivateLegacyVisitorAssignments($userId);
            Database::execute(
                'UPDATE sales_lines SET manager_user_id=?,supervisor_user_id=?,updated_at=NOW() WHERE id=?',
                [(int)$assignment['organization_manager_id'], $userId, $lineId]
            );
            SalesStructureModule::syncSupervisorCompatibilityFields($userId, $lineId, $actorId);
            return;
        }

        if ($roleCode === 'VISITOR' && $lineId > 0) {
            $geographyId = (int)($assignment['primary_geography_id'] ?? 0);
            Database::execute(
                'UPDATE sales_visitor_territories SET is_primary=0,updated_at=NOW() WHERE visitor_user_id=? AND active=1',
                [$userId]
            );
            Database::execute(
                'INSERT INTO sales_visitor_territories(visitor_user_id,line_id,geography_id,is_primary,active,created_at,updated_at)
                 VALUES (?,?,?,1,1,NOW(),NOW())
                 ON DUPLICATE KEY UPDATE line_id=VALUES(line_id),is_primary=1,active=1,updated_at=NOW()',
                [$userId, $lineId, $geographyId]
            );
            SalesStructureModule::syncUserCompatibilityFields($userId, $lineId, $actorId);
            self::syncLegacyTeamAssignment(
                $userId,
                (int)$assignment['supervisor_id'],
                (int)$assignment['organization_manager_id'],
                (string)$assignment['sales_line']
            );
            return;
        }

        self::deactivateLegacyVisitorAssignments($userId);
    }

    private static function assertNoCentralLineOwnership(?int $userId, string $newRoleCode): void
    {
        if (!$userId) return;
        if ($newRoleCode !== 'SALES_SUPERVISOR'
            && Database::fetch('SELECT id FROM sales_lines WHERE supervisor_user_id=? AND active=1 LIMIT 1', [$userId])) {
            throw new InvalidArgumentException('پیش از تغییر نقش این سرپرست، مسئولیت لاین او را در صفحه ساختار فروش منتقل کنید.');
        }
        if ($newRoleCode !== 'SALES_MANAGER'
            && Database::fetch('SELECT id FROM sales_lines WHERE manager_user_id=? AND active=1 LIMIT 1', [$userId])) {
            throw new InvalidArgumentException('پیش از تغییر نقش این مدیر، مدیریت لاین‌های فعال او را در صفحه ساختار فروش منتقل کنید.');
        }
    }

    private static function assertActiveSalesRole(int $userId, string $roleCode, string $message): array
    {
        $user = Database::fetch(
            "SELECT u.id,u.status,u.parent_user_id,u.organization_manager_id,r.code org_role_code
             FROM users u
             LEFT JOIN org_roles r ON r.id=u.org_role_id
             WHERE u.id=?",
            [$userId]
        );
        if (!$user || $user['status'] !== 'active' || ($user['org_role_code'] ?? '') !== $roleCode) {
            throw new InvalidArgumentException($message);
        }
        return $user;
    }

    private static function syncLegacyTeamAssignment(int $visitorId, int $supervisorId, int $managerId, string $salesLine): void
    {
        if (!Database::tableExists('sales_team_assignments')) return;
        Database::execute(
            'UPDATE sales_team_assignments SET active=0,updated_at=NOW() WHERE visitor_id=? AND supervisor_id<>? AND active=1',
            [$visitorId, $supervisorId]
        );
        Database::execute(
            'INSERT INTO sales_team_assignments(supervisor_id,visitor_id,sales_manager_id,sales_line,active,created_at,updated_at)
             VALUES (?,?,?,?,1,NOW(),NOW())
             ON DUPLICATE KEY UPDATE sales_manager_id=VALUES(sales_manager_id),sales_line=VALUES(sales_line),active=1,updated_at=NOW()',
            [$supervisorId, $visitorId, $managerId, $salesLine]
        );
    }

    private static function deactivateLegacyVisitorAssignments(int $userId): void
    {
        if (Database::tableExists('sales_team_assignments')) {
            Database::execute(
                'UPDATE sales_team_assignments SET active=0,updated_at=NOW() WHERE visitor_id=? AND active=1',
                [$userId]
            );
        }
        if (Database::tableExists('sales_visitor_territories')) {
            Database::execute(
                'UPDATE sales_visitor_territories SET active=0,is_primary=0,updated_at=NOW() WHERE visitor_user_id=? AND active=1',
                [$userId]
            );
        }
    }
}
