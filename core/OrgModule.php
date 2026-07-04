<?php

require_once __DIR__ . '/Database.php';

class OrgModule
{
    public static function repair(PDO $pdo): void
    {
        foreach (self::schema() as $sql) $pdo->exec($sql);

        $userColumns = [
            'org_unit_id' => 'INT UNSIGNED NULL',
            'org_role_id' => 'INT UNSIGNED NULL',
            'parent_user_id' => 'INT UNSIGNED NULL',
            'access_scope' => "VARCHAR(30) NOT NULL DEFAULT 'self'",
            'employee_panel_enabled' => 'TINYINT(1) NOT NULL DEFAULT 1',
            'admin_panel_enabled' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'display_order' => 'INT NOT NULL DEFAULT 0',
            'last_login_at' => 'DATETIME NULL',
        ];
        foreach ($userColumns as $column => $definition) {
            if (Database::tableExists('users') && !Database::columnExists('users', $column)) {
                $pdo->exec("ALTER TABLE users ADD `{$column}` {$definition}");
            }
        }

        if (Database::tableExists('users')) {
            $userIndexes = [
                'idx_users_org_unit' => 'org_unit_id',
                'idx_users_org_role' => 'org_role_id',
                'idx_users_parent' => 'parent_user_id',
                'idx_users_supervisor' => 'supervisor_id',
                'idx_users_organization_manager' => 'organization_manager_id',
                'idx_users_sales_line' => 'sales_line',
            ];
            $indexExists = $pdo->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME="users" AND COLUMN_NAME=? AND SEQ_IN_INDEX=1');
            foreach ($userIndexes as $index => $column) {
                $indexExists->execute([$column]);
                if (!(int)$indexExists->fetchColumn()) {
                    $pdo->exec("ALTER TABLE users ADD INDEX `{$index}` (`{$column}`)");
                }
            }

            $employeeNoIndex = $pdo->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME="users" AND COLUMN_NAME="employee_no"');
            $employeeNoIndex->execute();
            if (!(int)$employeeNoIndex->fetchColumn()) {
                $pdo->exec('ALTER TABLE users ADD INDEX `idx_users_employee_no` (`employee_no`)');
            }
        }

        $assignmentColumns = [
            'batch_key' => 'VARCHAR(80) NULL',
            'scope_type' => 'VARCHAR(40) NULL',
            'scope_value' => 'VARCHAR(190) NULL',
            'initial_status' => "VARCHAR(30) NOT NULL DEFAULT 'assigned'",
            'show_result_to_employee' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'cancel_reason' => 'TEXT NULL',
            'cancelled_by' => 'INT UNSIGNED NULL',
            'cancelled_at' => 'DATETIME NULL',
            'archived_at' => 'DATETIME NULL',
        ];
        foreach ($assignmentColumns as $column => $definition) {
            if (Database::tableExists('hr_assessment_assignments') && !Database::columnExists('hr_assessment_assignments', $column)) {
                $pdo->exec("ALTER TABLE hr_assessment_assignments ADD `{$column}` {$definition}");
            }
        }

        $roleColumns = [
            'org_unit_id' => 'INT UNSIGNED NULL',
            'parent_role_id' => 'INT UNSIGNED NULL',
        ];
        foreach ($roleColumns as $column => $definition) {
            if (Database::tableExists('org_roles') && !Database::columnExists('org_roles', $column)) {
                $pdo->exec("ALTER TABLE org_roles ADD `{$column}` {$definition}");
            }
        }

        if (Database::tableExists('hr_kpi_templates') && !Database::columnExists('hr_kpi_templates', 'org_unit_id')) {
            $pdo->exec('ALTER TABLE hr_kpi_templates ADD `org_unit_id` INT UNSIGNED NULL');
        }

        $jobColumns = [
            'endpoint' => 'VARCHAR(500) NULL',
            'duration_ms' => 'INT UNSIGNED NULL',
            'technical_details' => 'LONGTEXT NULL',
        ];
        foreach ($jobColumns as $column => $definition) {
            if (Database::tableExists('ai_update_jobs') && !Database::columnExists('ai_update_jobs', $column)) {
                $pdo->exec("ALTER TABLE ai_update_jobs ADD `{$column}` {$definition}");
            }
        }

        if (Database::tableExists('users')) {
            try {
                $pdo->exec("ALTER TABLE users MODIFY role ENUM('super_admin','admin','manager','employee') NOT NULL DEFAULT 'employee'");
            } catch (Throwable $e) {
                error_log('OrgModule role expansion: ' . $e->getMessage());
            }
        }

        self::seed($pdo);
    }

    public static function schema(): array
    {
        $engine = ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        return [
            "CREATE TABLE IF NOT EXISTS org_units (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,parent_id INT UNSIGNED NULL,title VARCHAR(190) NOT NULL,code VARCHAR(100) NOT NULL UNIQUE,unit_type VARCHAR(50) NOT NULL DEFAULT 'general',active TINYINT(1) NOT NULL DEFAULT 1,sort_order INT NOT NULL DEFAULT 0,description TEXT NULL,created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,INDEX idx_org_units_parent(parent_id),INDEX idx_org_units_active(active),CONSTRAINT fk_org_units_parent FOREIGN KEY(parent_id) REFERENCES org_units(id) ON DELETE SET NULL){$engine}",
            "CREATE TABLE IF NOT EXISTS org_roles (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,title VARCHAR(190) NOT NULL,code VARCHAR(100) NOT NULL UNIQUE,org_unit_id INT UNSIGNED NULL,parent_role_id INT UNSIGNED NULL,role_type VARCHAR(50) NOT NULL DEFAULT 'staff',is_sales_role TINYINT(1) NOT NULL DEFAULT 0,hierarchy_level INT NOT NULL DEFAULT 0,active TINYINT(1) NOT NULL DEFAULT 1,sort_order INT NOT NULL DEFAULT 0,description TEXT NULL,created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,INDEX idx_org_roles_active(active),INDEX idx_org_roles_sales(is_sales_role),INDEX idx_org_roles_unit(org_unit_id),INDEX idx_org_roles_parent(parent_role_id)){$engine}",
            "CREATE TABLE IF NOT EXISTS hr_kpi_template_roles (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,template_id INT UNSIGNED NOT NULL,role_id INT UNSIGNED NOT NULL,is_default TINYINT(1) NOT NULL DEFAULT 0,active TINYINT(1) NOT NULL DEFAULT 1,created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,UNIQUE KEY uq_hr_kpi_template_role(template_id,role_id),INDEX idx_hr_kpi_template_roles_role(role_id),INDEX idx_hr_kpi_template_roles_active(active)){$engine}",
            "CREATE TABLE IF NOT EXISTS hr_assessment_assignment_logs (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,assignment_id INT UNSIGNED NULL,action VARCHAR(40) NOT NULL,reason TEXT NULL,performed_by INT UNSIGNED NULL,old_status VARCHAR(30) NULL,new_status VARCHAR(30) NULL,created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,INDEX idx_assignment_logs_assignment(assignment_id),INDEX idx_assignment_logs_actor(performed_by)){$engine}",
        ];
    }

    private static function seed(PDO $pdo): void
    {
        $units = [
            [null, 'شرکت سبحان', 'COMPANY', 'root', 1],
            [null, 'مدیریت', 'MANAGEMENT', 'general', 10],
            [null, 'مالی', 'FINANCE', 'general', 20],
            [null, 'انبار', 'WAREHOUSE', 'general', 30],
            [null, 'IT', 'IT', 'general', 40],
            [null, 'برنامه‌ریزی', 'PLANNING', 'general', 50],
            [null, 'اداری', 'ADMINISTRATION', 'general', 60],
            [null, 'فروش', 'SALES', 'sales', 70],
        ];
        $stmt = $pdo->prepare('INSERT IGNORE INTO org_units(parent_id,title,code,unit_type,active,sort_order,created_at,updated_at) VALUES (?,?,?,?,1,?,NOW(),NOW())');
        foreach ($units as $unit) $stmt->execute($unit);

        $roles = [
            ['مدیرعامل','CEO','executive',0,0], ['مدیر فروش','SALES_MANAGER','manager',1,1], ['سرپرست فروش','SALES_SUPERVISOR','supervisor',1,2], ['ویزیتور','VISITOR','staff',1,3],
            ['مدیر مالی','FINANCE_MANAGER','manager',0,1], ['خزانه','TREASURY','staff',0,2], ['حسابداری فروش','SALES_ACCOUNTING','staff',0,2], ['مسئول مالیات و بیمه','TAX_INSURANCE','staff',0,2],
            ['مدیر انبار','WAREHOUSE_MANAGER','manager',0,1], ['سرپرست انبار','WAREHOUSE_SUPERVISOR','supervisor',0,2], ['نیروی انبار','WAREHOUSE_STAFF','staff',0,2], ['موزع','DISTRIBUTOR','staff',0,2], ['راننده','DRIVER','staff',0,2],
            ['IT','IT_STAFF','staff',0,1], ['برنامه‌ریزی','PLANNING_STAFF','staff',0,1], ['مسئول دفتر','OFFICE_MANAGER','staff',0,1], ['مدیر داخلی','INTERNAL_MANAGER','manager',0,1], ['کارمند اداری','ADMIN_STAFF','staff',0,2],
        ];
        $stmt = $pdo->prepare('INSERT IGNORE INTO org_roles(title,code,role_type,is_sales_role,hierarchy_level,active,sort_order,created_at,updated_at) VALUES (?,?,?,?,?,1,?,NOW(),NOW())');
        foreach ($roles as $index => $role) $stmt->execute([...$role, ($index + 1) * 10]);

        $moduleStmt = $pdo->prepare('INSERT IGNORE INTO modules(module_key,module_title,sort_order,status,created_at) VALUES (?,?,?,"active",NOW())');
        foreach ([['hr_settings','تنظیمات منابع انسانی',698],['install_tools','ابزارهای نصب و بروزرسانی',711],['employee_portal','پنل کارمند',712]] as $module) $moduleStmt->execute($module);
    }

    public static function unitDepth(?int $unitId): int
    {
        $depth = 0;
        $seen = [];
        while ($unitId && $depth < 10 && !isset($seen[$unitId])) {
            $seen[$unitId] = true;
            $row = Database::fetch('SELECT parent_id FROM org_units WHERE id=?', [$unitId]);
            if (!$row) break;
            $depth++;
            $unitId = (int)($row['parent_id'] ?? 0) ?: null;
        }
        return $depth;
    }

    public static function salesBranch(?int $unitId): bool
    {
        $seen = [];
        while ($unitId && !isset($seen[$unitId])) {
            $seen[$unitId] = true;
            $row = Database::fetch('SELECT parent_id,unit_type,code FROM org_units WHERE id=?', [$unitId]);
            if (!$row) return false;
            if ($row['unit_type'] === 'sales' || $row['code'] === 'SALES') return true;
            $unitId = (int)($row['parent_id'] ?? 0) ?: null;
        }
        return false;
    }

    public static function normalizeUserOrganization(array $input, int $userId = 0): array
    {
        $errors = [];
        $orgUnitId = (int)($input['org_unit_id'] ?? 0) ?: null;
        $orgRoleId = (int)($input['org_role_id'] ?? 0) ?: null;
        $parentUserId = (int)($input['parent_user_id'] ?? 0) ?: null;
        $supervisorId = (int)($input['supervisor_id'] ?? 0) ?: null;
        $organizationManagerId = (int)($input['organization_manager_id'] ?? 0) ?: null;
        $salesLine = trim((string)($input['sales_line'] ?? ''));

        $unit = $orgUnitId ? Database::fetch('SELECT id,title,unit_type FROM org_units WHERE id=? AND active=1', [$orgUnitId]) : null;
        $role = $orgRoleId ? Database::fetch('SELECT id,code,is_sales_role FROM org_roles WHERE id=? AND active=1', [$orgRoleId]) : null;
        if ($orgUnitId && !$unit) $errors['org_unit_id'] = 'واحد سازمانی معتبر نیست.';
        if ($orgRoleId && !$role) $errors['org_role_id'] = 'نقش سازمانی معتبر نیست.';

        $roleCode = (string)($role['code'] ?? '');
        $isSalesUser = $unit && $role && self::salesBranch($orgUnitId) && (int)$role['is_sales_role'] === 1;
        if (!$isSalesUser) {
            return [
                'org_unit' => $unit, 'org_role' => $role, 'role_code' => $roleCode,
                'sales_line' => '', 'supervisor_id' => null, 'organization_manager_id' => null,
                'parent_user_id' => $parentUserId, 'errors' => $errors,
            ];
        }

        if (in_array($roleCode, ['VISITOR', 'SALES_SUPERVISOR'], true) && $salesLine === '') {
            $errors['sales_line'] = 'انتخاب لاین فروش الزامی است.';
        }

        if ($roleCode === 'VISITOR') {
            $supervisorId = $supervisorId ?: $parentUserId;
            $supervisor = self::activeSalesUser($supervisorId, 'SALES_SUPERVISOR');
            if (!$supervisor || $supervisorId === $userId) {
                $errors['supervisor_id'] = 'برای ویزیتور واحد فروش، انتخاب سرپرست فروش فعال الزامی است.';
            } else {
                $parentUserId = $supervisorId;
                $managerId = (int)($supervisor['parent_user_id'] ?? 0) ?: (int)($supervisor['organization_manager_id'] ?? 0) ?: null;
                $organizationManagerId = self::activeSalesUser($managerId, 'SALES_MANAGER') ? $managerId : null;
            }
        } elseif ($roleCode === 'SALES_SUPERVISOR') {
            $organizationManagerId = $organizationManagerId ?: $parentUserId;
            $manager = self::activeSalesUser($organizationManagerId, 'SALES_MANAGER');
            if (!$manager || $organizationManagerId === $userId) {
                $errors['organization_manager_id'] = 'برای سرپرست واحد فروش، انتخاب مدیر فروش فعال الزامی است.';
            } else {
                $parentUserId = $organizationManagerId;
            }
            $supervisorId = null;
        } elseif ($roleCode === 'SALES_MANAGER') {
            $parentUserId = null;
            $supervisorId = null;
            $organizationManagerId = null;
        }

        return [
            'org_unit' => $unit, 'org_role' => $role, 'role_code' => $roleCode,
            'sales_line' => $salesLine, 'supervisor_id' => $supervisorId,
            'organization_manager_id' => $organizationManagerId, 'parent_user_id' => $parentUserId,
            'errors' => $errors,
        ];
    }

    private static function activeSalesUser(?int $userId, string $roleCode): ?array
    {
        if (!$userId) return null;
        $user = Database::fetch('SELECT u.*,r.code org_role_code,r.is_sales_role FROM users u JOIN org_roles r ON r.id=u.org_role_id AND r.active=1 WHERE u.id=? AND u.status="active" AND r.code=? AND r.is_sales_role=1', [$userId, $roleCode]);
        if (!$user || !self::salesBranch((int)($user['org_unit_id'] ?? 0))) return null;
        return $user;
    }
}
