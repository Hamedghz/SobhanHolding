<?php

require_once __DIR__ . '/../lib/AppDate.php';

final class AppDateModule
{
    public static function repair(PDO $pdo): void
    {
        $engine = ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS system_periods (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                period_key VARCHAR(100) NOT NULL,
                title VARCHAR(190) NOT NULL,
                period_type ENUM('daily','weekly','monthly','quarterly','half_yearly','yearly','custom') NOT NULL,
                start_date DATE NULL,
                end_date DATE NULL,
                jalali_year SMALLINT UNSIGNED NULL,
                jalali_month TINYINT UNSIGNED NULL,
                scope_key VARCHAR(100) NOT NULL DEFAULT 'global',
                is_current TINYINT(1) NOT NULL DEFAULT 0,
                is_system TINYINT(1) NOT NULL DEFAULT 1,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                sort_order INT NOT NULL DEFAULT 0,
                created_by INT UNSIGNED NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_system_period_key (period_key),
                INDEX idx_system_period_type (period_type,is_active,start_date,end_date),
                INDEX idx_system_period_scope (scope_key,is_active,sort_order),
                INDEX idx_system_period_current (is_current,is_active)
            ){$engine}"
        );

        $pdo->exec('UPDATE system_periods SET is_current=0 WHERE is_system=1');
        $statement = $pdo->prepare(
            'INSERT INTO system_periods
             (period_key,title,period_type,start_date,end_date,jalali_year,jalali_month,scope_key,is_current,is_system,is_active,sort_order,created_at,updated_at)
             VALUES (?,?,?,?,?,?,?,?,?,1,1,?,NOW(),NOW())
             ON DUPLICATE KEY UPDATE
                title=VALUES(title),
                period_type=VALUES(period_type),
                start_date=VALUES(start_date),
                end_date=VALUES(end_date),
                jalali_year=VALUES(jalali_year),
                jalali_month=VALUES(jalali_month),
                scope_key=VALUES(scope_key),
                is_current=VALUES(is_current),
                is_system=1,
                is_active=1,
                sort_order=VALUES(sort_order),
                updated_at=NOW()'
        );
        foreach (AppDate::defaultPeriodCatalog() as $period) {
            $statement->execute([
                $period['period_key'],
                $period['title'],
                $period['period_type'],
                $period['start_date'],
                $period['end_date'],
                $period['jalali_year'],
                $period['jalali_month'],
                $period['scope_key'],
                $period['is_current'],
                $period['sort_order'],
            ]);
        }
    }
}
