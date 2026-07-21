<?php
require_once __DIR__ . '/Database.php';

final class HrAttendanceModule
{
    public static function repair(PDO $pdo): void
    {
        foreach (self::schema() as $sql) $pdo->exec($sql);
        foreach (['effective_to'=>'DATE NULL AFTER effective_from','allowed_checkin_from'=>'TIME NULL','allowed_checkin_to'=>'TIME NULL','allowed_checkout_from'=>'TIME NULL','allowed_checkout_to'=>'TIME NULL'] as $column=>$definition) self::ensureColumn($pdo,'hr_attendance_settings',$column,$definition);
        foreach ([
            'leave_type' => 'VARCHAR(100) NULL AFTER day_status',
            'mission_details' => 'TEXT NULL AFTER leave_type',
            'import_time_notes' => 'TEXT NULL AFTER notes',
        ] as $column => $definition) {
            self::ensureColumn($pdo, 'hr_attendance_entries', $column, $definition);
        }
        self::ensureDayStatusCompatibility($pdo);
        self::seed($pdo);
        try { self::repairView($pdo); } catch (Throwable $e) { error_log('HR attendance summary view: '.$e->getMessage()); }
    }

    public static function schema(): array
    {
        $engine = ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        return [
            "CREATE TABLE IF NOT EXISTS hr_work_groups (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(190) NOT NULL,
                code VARCHAR(60) NOT NULL,
                description TEXT NULL,
                active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_hr_work_groups_code(code),
                INDEX idx_hr_work_groups_active(active)
            ){$engine}",
            "CREATE TABLE IF NOT EXISTS hr_attendance_settings (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                work_group_id INT UNSIGNED NOT NULL,
                effective_from DATE NOT NULL,
                effective_to DATE NULL,
                default_start_time TIME NOT NULL,
                default_end_time TIME NOT NULL,
                late_tolerance_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                early_leave_tolerance_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                allowed_checkin_from TIME NULL,
                allowed_checkin_to TIME NULL,
                allowed_checkout_from TIME NULL,
                allowed_checkout_to TIME NULL,
                allow_before_shift_overtime TINYINT(1) NOT NULL DEFAULT 0,
                allow_after_shift_overtime TINYINT(1) NOT NULL DEFAULT 1,
                require_overtime_approval TINYINT(1) NOT NULL DEFAULT 1,
                active TINYINT(1) NOT NULL DEFAULT 1,
                created_by INT UNSIGNED NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_hr_attendance_setting_version(work_group_id,effective_from),
                INDEX idx_hr_attendance_setting_active(work_group_id,active,effective_from),
                CONSTRAINT fk_hr_attendance_setting_group FOREIGN KEY(work_group_id) REFERENCES hr_work_groups(id),
                CONSTRAINT fk_hr_attendance_setting_creator FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL
            ){$engine}",
            "CREATE TABLE IF NOT EXISTS hr_month_holidays (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                holiday_date DATE NOT NULL,
                jalali_year SMALLINT UNSIGNED NOT NULL,
                jalali_month TINYINT UNSIGNED NOT NULL,
                title VARCHAR(190) NOT NULL,
                holiday_type ENUM('official','company','internal','half_day') NOT NULL DEFAULT 'official',
                applies_to_group ENUM('all','sales','admin_warehouse') NOT NULL DEFAULT 'all',
                is_half_day TINYINT(1) NOT NULL DEFAULT 0,
                description TEXT NULL,
                active TINYINT(1) NOT NULL DEFAULT 1,
                created_by INT UNSIGNED NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_hr_holiday_date_group(holiday_date,applies_to_group),
                INDEX idx_hr_holiday_jalali(jalali_year,jalali_month),
                INDEX idx_hr_holiday_active(holiday_date,active),
                CONSTRAINT fk_hr_holiday_creator FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL
            ){$engine}",
            "CREATE TABLE IF NOT EXISTS hr_attendance_entries (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                employee_id INT UNSIGNED NOT NULL,
                work_group_id INT UNSIGNED NOT NULL,
                attendance_date DATE NOT NULL,
                is_holiday TINYINT(1) NOT NULL DEFAULT 0,
                holiday_id INT UNSIGNED NULL,
                approved_start_time TIME NULL,
                approved_end_time TIME NULL,
                actual_in_time TIME NULL,
                actual_out_time TIME NULL,
                break_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                late_minutes INT UNSIGNED NOT NULL DEFAULT 0,
                early_leave_minutes INT UNSIGNED NOT NULL DEFAULT 0,
                normal_overtime_minutes INT UNSIGNED NOT NULL DEFAULT 0,
                holiday_overtime_minutes INT UNSIGNED NOT NULL DEFAULT 0,
                work_minutes INT UNSIGNED NOT NULL DEFAULT 0,
                day_status VARCHAR(30) NOT NULL DEFAULT 'present',
                leave_type VARCHAR(100) NULL,
                mission_details TEXT NULL,
                overtime_status ENUM('none','pending','approved','rejected') NOT NULL DEFAULT 'none',
                notes TEXT NULL,
                import_time_notes TEXT NULL,
                attachment_path VARCHAR(500) NULL,
                created_by INT UNSIGNED NULL,
                approved_by INT UNSIGNED NULL,
                approved_at DATETIME NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_hr_attendance_employee_date(employee_id,attendance_date),
                INDEX idx_hr_attendance_date(attendance_date),
                INDEX idx_hr_attendance_group(work_group_id,attendance_date),
                INDEX idx_hr_attendance_status(day_status,overtime_status),
                CONSTRAINT fk_hr_attendance_employee FOREIGN KEY(employee_id) REFERENCES users(id),
                CONSTRAINT fk_hr_attendance_group FOREIGN KEY(work_group_id) REFERENCES hr_work_groups(id),
                CONSTRAINT fk_hr_attendance_holiday FOREIGN KEY(holiday_id) REFERENCES hr_month_holidays(id) ON DELETE SET NULL,
                CONSTRAINT fk_hr_attendance_creator FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
                CONSTRAINT fk_hr_attendance_approver FOREIGN KEY(approved_by) REFERENCES users(id) ON DELETE SET NULL
            ){$engine}",
            "CREATE TABLE IF NOT EXISTS hr_attendance_logs (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                attendance_entry_id BIGINT UNSIGNED NOT NULL,
                action ENUM('create','update','approve_overtime','reject_overtime','delete_soft','manual_override') NOT NULL,
                old_value_json LONGTEXT NULL,
                new_value_json LONGTEXT NULL,
                performed_by INT UNSIGNED NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_hr_attendance_log_entry(attendance_entry_id,created_at),
                INDEX idx_hr_attendance_log_actor(performed_by,created_at),
                CONSTRAINT fk_hr_attendance_log_entry FOREIGN KEY(attendance_entry_id) REFERENCES hr_attendance_entries(id),
                CONSTRAINT fk_hr_attendance_log_actor FOREIGN KEY(performed_by) REFERENCES users(id) ON DELETE SET NULL
            ){$engine}",
            "CREATE TABLE IF NOT EXISTS hr_attendance_identity_mappings (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                source_field VARCHAR(40) NOT NULL,
                external_code VARCHAR(100) NOT NULL,
                user_id INT UNSIGNED NOT NULL,
                active TINYINT(1) NOT NULL DEFAULT 1,
                created_by INT UNSIGNED NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_hr_attendance_identity(source_field,external_code),
                INDEX idx_hr_attendance_identity_user(user_id,active),
                CONSTRAINT fk_hr_attendance_identity_user FOREIGN KEY(user_id) REFERENCES users(id),
                CONSTRAINT fk_hr_attendance_identity_creator FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL
            ){$engine}",
        ];
    }

    private static function seed(PDO $pdo): void
    {
        $group = $pdo->prepare('INSERT IGNORE INTO hr_work_groups(title,code,description,active,created_at,updated_at) VALUES(?,?,?,1,NOW(),NOW())');
        $group->execute(['فروش', 'SALES', 'گروه کاری پرسنل فروش']);
        $group->execute(['اداری و انبار', 'ADMIN_WAREHOUSE', 'گروه کاری اداری، پشتیبانی و انبار']);
        $module = $pdo->prepare('INSERT IGNORE INTO modules(module_key,module_title,sort_order,status,created_at) VALUES(?,?,?,"active",NOW())');
        foreach ([
            ['hr_attendance', 'حضور و کارکرد پرسنل', 145],
            ['hr_attendance.settings', 'تنظیمات حضور و کارکرد', 146],
            ['hr_attendance.reports', 'گزارش حضور و کارکرد', 147],
            ['hr_attendance.own', 'مشاهده کارکرد شخصی', 148],
        ] as $row) $module->execute($row);
    }

    private static function ensureColumn(PDO $pdo,string $table,string $column,string $definition): void
    {
        $stmt=$pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');$stmt->execute([$table,$column]);if(!(int)$stmt->fetchColumn())$pdo->exec("ALTER TABLE `{$table}` ADD `{$column}` {$definition}");
    }

    private static function ensureDayStatusCompatibility(PDO $pdo): void
    {
        $stmt = $pdo->prepare(
            'SELECT DATA_TYPE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?'
        );
        $stmt->execute(['hr_attendance_entries', 'day_status']);
        if (strtolower((string)$stmt->fetchColumn()) === 'enum') {
            $pdo->exec("ALTER TABLE hr_attendance_entries MODIFY day_status VARCHAR(30) NOT NULL DEFAULT 'present'");
        }
    }

    private static function repairView(PDO $pdo): void
    {
        $pdo->exec("CREATE OR REPLACE VIEW vw_hr_attendance_monthly_summary AS
            SELECT employee_id,YEAR(attendance_date) AS `year`,MONTH(attendance_date) AS `month`,
                SUM(late_minutes) AS total_late_minutes,
                SUM(early_leave_minutes) AS total_early_leave_minutes,
                SUM(CASE WHEN overtime_status='approved' THEN normal_overtime_minutes ELSE 0 END) AS total_normal_overtime_minutes,
                SUM(CASE WHEN overtime_status='approved' THEN holiday_overtime_minutes ELSE 0 END) AS total_holiday_overtime_minutes,
                SUM(day_status='absent') AS absent_days,
                SUM(day_status='leave') AS leave_days,
                SUM(day_status='mission') AS mission_days,
                SUM(day_status IN ('present','half_day','holiday_work')) AS present_days,
                ROUND(GREATEST(0,10-(((SUM(late_minutes)+SUM(early_leave_minutes))/30)*0.5)-(SUM(day_status='absent')*2)),2) AS attendance_score_suggestion
            FROM hr_attendance_entries
            GROUP BY employee_id,YEAR(attendance_date),MONTH(attendance_date)");
    }
}
