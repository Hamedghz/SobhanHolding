<?php

require_once __DIR__ . '/Database.php';

final class ImportCenterModule
{
    public const PERMISSIONS = [
        'import_center.view' => ['مشاهده مرکز ورود اطلاعات', 774],
        'import_center.upload' => ['بارگذاری در مرکز ورود اطلاعات', 775],
        'import_center.commit' => ['تایید و فعال‌سازی ورود اطلاعات', 776],
        'import_center.manage' => ['مدیریت پیشرفته ورود اطلاعات', 777],
    ];

    public static function repair(PDO $pdo): void
    {
        $engine = ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS purchase_aggregate_rows (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                import_batch_id BIGINT UNSIGNED NOT NULL,
                source_unique_key VARCHAR(191) NOT NULL,
                invoice_type VARCHAR(100) NULL,
                invoice_number VARCHAR(100) NULL,
                invoice_date_raw VARCHAR(100) NULL,
                invoice_date DATE NULL,
                supplier_code VARCHAR(100) NULL,
                supplier_name VARCHAR(255) NULL,
                manufacturer_code VARCHAR(100) NULL,
                manufacturer_name VARCHAR(255) NULL,
                line_code VARCHAR(100) NULL,
                line_name VARCHAR(255) NULL,
                product_code VARCHAR(100) NULL,
                product_name VARCHAR(255) NULL,
                quantity DECIMAL(18,4) NULL,
                gross_amount DECIMAL(20,2) NULL,
                discount_amount DECIMAL(20,2) NULL,
                net_amount DECIMAL(20,2) NULL,
                brand_code VARCHAR(100) NULL,
                brand_name VARCHAR(255) NULL,
                raw_json LONGTEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                INDEX idx_purchase_batch(import_batch_id),
                INDEX idx_purchase_key(source_unique_key),
                INDEX idx_purchase_date(invoice_date),
                INDEX idx_purchase_supplier(supplier_code),
                INDEX idx_purchase_product(product_code)
            ){$engine}"
        );

        foreach ([
            'sales_import_batches' => [
                'stored_file_path' => 'VARCHAR(500) NULL',
                'detected_range' => 'VARCHAR(100) NULL',
                'period_key' => 'VARCHAR(50) NULL',
                'pipeline_status' => "VARCHAR(40) NOT NULL DEFAULT 'uploaded'",
                'snapshot_date' => 'DATE NULL',
                'period_id' => 'BIGINT UNSIGNED NULL',
                'retry_of_batch_id' => 'BIGINT UNSIGNED NULL',
                'source_confidence' => 'DECIMAL(6,2) NULL',
            ],
            'sales_reference_import_batches' => [
                'pipeline_status' => "VARCHAR(40) NOT NULL DEFAULT 'uploaded'",
                'snapshot_date' => 'DATE NULL',
                'period_id' => 'BIGINT UNSIGNED NULL',
                'retry_of_batch_id' => 'BIGINT UNSIGNED NULL',
                'source_confidence' => 'DECIMAL(6,2) NULL',
            ],
            'staging_sales_data' => [
                'source_row_number' => 'INT NULL',
                'source_sheet' => 'VARCHAR(255) NULL',
                'source_table' => 'VARCHAR(255) NULL',
                'source_row_hash' => 'CHAR(64) NULL',
            ],
            'staging_sales_reference_rows' => [
                'source_row_number' => 'INT NULL',
                'source_sheet' => 'VARCHAR(255) NULL',
                'source_table' => 'VARCHAR(255) NULL',
                'source_row_hash' => 'CHAR(64) NULL',
            ],
            'inventory_aggregate_rows' => [
                'period_key' => 'VARCHAR(50) NULL',
                'snapshot_date' => 'DATE NULL',
                'period_id' => 'BIGINT UNSIGNED NULL',
            ],
            'hr_attendance_entries' => [
                'import_batch_id' => 'BIGINT UNSIGNED NULL',
            ],
        ] as $table => $columns) {
            foreach ($columns as $column => $definition) self::addColumn($pdo, $table, $column, $definition);
        }

        self::addIndex($pdo, 'sales_import_batches', 'idx_sales_import_pipeline', 'pipeline_status');
        self::addIndex($pdo, 'staging_sales_data', 'idx_staging_source_row_hash', 'source_row_hash');
        self::addIndex($pdo, 'inventory_aggregate_rows', 'idx_inventory_snapshot', 'snapshot_date');
        self::addIndex($pdo, 'hr_attendance_entries', 'idx_hr_attendance_import_batch', 'import_batch_id');
        self::seedPermissions($pdo);
        self::seedSettings($pdo);
        self::repairViews($pdo);
    }

    private static function seedPermissions(PDO $pdo): void
    {
        if (!self::tableExists($pdo, 'modules')) return;
        $stmt = $pdo->prepare(
            'INSERT INTO modules(module_key,module_title,sort_order,status,created_at)
             VALUES (?,?,?,"active",NOW())
             ON DUPLICATE KEY UPDATE module_title=VALUES(module_title),sort_order=VALUES(sort_order),status="active"'
        );
        foreach (self::PERMISSIONS as $key => [$title, $sort]) $stmt->execute([$key, $title, $sort]);
    }

    private static function seedSettings(PDO $pdo): void
    {
        if (!self::tableExists($pdo, 'site_settings')) return;
        $stmt = $pdo->prepare(
            'INSERT INTO site_settings(setting_key,setting_value,setting_type,updated_at)
             VALUES (?,?,?,NOW()) ON DUPLICATE KEY UPDATE setting_key=VALUES(setting_key)'
        );
        foreach ([
            ['max_excel_upload_mb', '50', 'number'],
            ['max_letter_attachment_mb', '50', 'number'],
            ['max_letterhead_upload_mb', '50', 'number'],
            ['allowed_import_extensions', 'xlsx,csv', 'text'],
        ] as $row) $stmt->execute($row);
    }

    private static function repairViews(PDO $pdo): void
    {
        foreach ([
            'vw_active_purchase_aggregate_rows' => "SELECT r.* FROM purchase_aggregate_rows r JOIN sales_import_batches b ON b.id=r.import_batch_id AND b.source_module='purchase_aggregate' AND b.is_active_reference=1 AND b.status='committed'",
            'vw_active_sales_targets' => "SELECT r.* FROM sales_targets r JOIN sales_import_batches b ON b.id=r.import_batch_id AND b.source_module='sales_targets' AND b.is_active_reference=1 AND b.status='committed'",
            'vw_active_product_priorities' => "SELECT r.* FROM product_priorities r JOIN sales_import_batches b ON b.id=r.import_batch_id AND b.source_module='product_priorities' AND b.is_active_reference=1 AND b.status='committed'",
            'vw_active_customer_class_coefficients' => "SELECT r.* FROM sales_customer_class_coefficients r JOIN sales_import_batches b ON b.id=r.import_batch_id AND b.source_module='customer_coefficients' AND b.is_active_reference=1 AND b.status='committed'",
        ] as $name => $select) {
            try {
                $pdo->exec("CREATE OR REPLACE VIEW `{$name}` AS {$select}");
            } catch (Throwable $e) {
                error_log('Import center view repair: '.$name.': '.$e->getMessage());
            }
        }
    }

    private static function addColumn(PDO $pdo, string $table, string $column, string $definition): void
    {
        if (!self::tableExists($pdo, $table) || self::columnExists($pdo, $table, $column)) return;
        $pdo->exec("ALTER TABLE `{$table}` ADD `{$column}` {$definition}");
    }

    private static function addIndex(PDO $pdo, string $table, string $index, string $column): void
    {
        if (!self::tableExists($pdo, $table) || !self::columnExists($pdo, $table, $column) || self::indexExists($pdo, $table, $index)) return;
        $pdo->exec("ALTER TABLE `{$table}` ADD INDEX `{$index}` (`{$column}`)");
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

    private static function indexExists(PDO $pdo, string $table, string $index): bool
    {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=?');
        $stmt->execute([$table, $index]);
        return (int)$stmt->fetchColumn() > 0;
    }
}
