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
            if (self::tableExists($pdo, 'users') && !self::columnExists($pdo, 'users', $column)) {
                $pdo->exec("ALTER TABLE users ADD `{$column}` {$definition}");
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
            if (self::tableExists($pdo, 'hr_assessment_assignments') && !self::columnExists($pdo, 'hr_assessment_assignments', $column)) {
                $pdo->exec("ALTER TABLE hr_assessment_assignments ADD `{$column}` {$definition}");
            }
        }

        $roleColumns = [
            'org_unit_id' => 'INT UNSIGNED NULL',
            'parent_role_id' => 'INT UNSIGNED NULL',
        ];
        foreach ($roleColumns as $column => $definition) {
            if (self::tableExists($pdo, 'org_roles') && !self::columnExists($pdo, 'org_roles', $column)) {
                $pdo->exec("ALTER TABLE org_roles ADD `{$column}` {$definition}");
            }
        }

        if (self::tableExists($pdo, 'hr_kpi_templates') && !self::columnExists($pdo, 'hr_kpi_templates', 'org_unit_id')) {
            $pdo->exec('ALTER TABLE hr_kpi_templates ADD `org_unit_id` INT UNSIGNED NULL');
        }

        $jobColumns = [
            'endpoint' => 'VARCHAR(500) NULL',
            'duration_ms' => 'INT UNSIGNED NULL',
            'technical_details' => 'LONGTEXT NULL',
        ];
        foreach ($jobColumns as $column => $definition) {
            if (self::tableExists($pdo, 'ai_update_jobs') && !self::columnExists($pdo, 'ai_update_jobs', $column)) {
                $pdo->exec("ALTER TABLE ai_update_jobs ADD `{$column}` {$definition}");
            }
        }

        if (self::tableExists($pdo, 'users')) {
            try {
                $pdo->exec("ALTER TABLE users MODIFY role ENUM('super_admin','admin','manager','employee') NOT NULL DEFAULT 'employee'");
            } catch (Throwable $e) {
                error_log('OrgModule role expansion: ' . $e->getMessage());
            }
        }

        self::seed($pdo);
    }

    private static function tableExists(PDO $pdo, string $table): bool
    {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
        $stmt->execute([$table]);
        return (int)$stmt->fetchColumn() > 0;
    }

    private static function columnExists(PDO $pdo, string $table, string $column): bool
    {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
        $stmt->execute([$table, $column]);
        return (int)$stmt->fetchColumn() > 0;
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
}
