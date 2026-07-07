<?php

class SalesDataSchema
{
    public const PERMISSIONS = [
        'sales_data_view' => ['مدیریت داده فروش', 760],
        'sales_data_import' => ['ورود داده فروش', 761],
        'sales_data_manage_mapping' => ['مدیریت نگاشت ستون‌های فروش', 762],
        'sales_data_view_errors' => ['مشاهده خطاهای ورود داده فروش', 763],
        'sales_data_sync_ai' => ['همگام‌سازی داده فروش با SobhanAI', 764],
        'sales_data_manage_formulas' => ['مدیریت فرمول‌های پورسانت', 765],
        'sales_data_view_reports' => ['مشاهده Viewهای گزارش فروش', 766],
        'sales_data_run_commission' => ['اجرای محاسبات پورسانت', 767],
    ];

    public static function repair(PDO $pdo): void
    {
        foreach (self::statements() as $statement) {
            $pdo->exec($statement);
        }

        self::repairSalesAggregateColumns($pdo);

        if (self::tableExists($pdo, 'modules')) {
            $stmt = $pdo->prepare(
                'INSERT INTO modules (module_key,module_title,sort_order,status,created_at)
                 VALUES (?,?,?,"active",NOW())
                 ON DUPLICATE KEY UPDATE module_key=VALUES(module_key)'
            );
            foreach (self::PERMISSIONS as $key => [$title, $sortOrder]) {
                $stmt->execute([$key, $title, $sortOrder]);
            }
        }
    }

    public static function tableNames(): array
    {
        return [
            'sales_import_batches',
            'sales_import_errors',
            'sales_import_column_mappings',
            'staging_sales_data',
            'sales_aggregate_rows',
            'inventory_aggregate_rows',
            'sales_team_members',
            'sales_customer_class_coefficients',
            'product_priorities',
            'sales_targets',
            'commission_formula_settings',
            'commission_calculation_runs',
            'commission_calculation_results',
        ];
    }

    private static function tableExists(PDO $pdo, string $table): bool
    {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?'
        );
        $stmt->execute([$table]);
        return (int)$stmt->fetchColumn() > 0;
    }

    private static function columnExists(PDO $pdo, string $table, string $column): bool
    {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?'
        );
        $stmt->execute([$table, $column]);
        return (int)$stmt->fetchColumn() > 0;
    }

    private static function indexExists(PDO $pdo, string $table, string $index): bool
    {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=?'
        );
        $stmt->execute([$table, $index]);
        return (int)$stmt->fetchColumn() > 0;
    }

    private static function repairSalesAggregateColumns(PDO $pdo): void
    {
        if (!self::tableExists($pdo, 'sales_aggregate_rows')) return;
        $columns = [
            'unique_code' => 'VARCHAR(191) NULL AFTER source_unique_key',
            'invoice_type' => 'VARCHAR(100) NULL AFTER unique_code',
            'sub_invoice_number' => 'VARCHAR(100) NULL AFTER invoice_number',
            'invoice_date_raw' => 'VARCHAR(100) NULL AFTER sub_invoice_number',
        ];
        foreach ($columns as $column => $definition) {
            if (!self::columnExists($pdo, 'sales_aggregate_rows', $column)) {
                $pdo->exec("ALTER TABLE sales_aggregate_rows ADD `{$column}` {$definition}");
            }
        }
        if (!self::indexExists($pdo, 'sales_aggregate_rows', 'uq_sales_aggregate_source_key')) {
            $duplicates = $pdo->query(
                'SELECT source_unique_key FROM sales_aggregate_rows WHERE source_unique_key IS NOT NULL AND source_unique_key<>""
                 GROUP BY source_unique_key HAVING COUNT(*)>1 LIMIT 1'
            )->fetchColumn();
            if ($duplicates === false) {
                $pdo->exec('ALTER TABLE sales_aggregate_rows ADD UNIQUE KEY uq_sales_aggregate_source_key (source_unique_key)');
            }
        }
    }

    private static function statements(): array
    {
        $engine = ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        return [
            "CREATE TABLE IF NOT EXISTS sales_import_batches (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                source_type VARCHAR(30) NOT NULL,
                source_module VARCHAR(50) NOT NULL,
                file_name VARCHAR(255) NULL,
                file_hash VARCHAR(128) NULL,
                detected_sheet VARCHAR(255) NULL,
                detected_table VARCHAR(255) NULL,
                import_mode VARCHAR(30) NOT NULL DEFAULT 'skip_duplicates',
                status VARCHAR(30) NOT NULL DEFAULT 'uploaded',
                total_rows INT NOT NULL DEFAULT 0,
                valid_rows INT NOT NULL DEFAULT 0,
                invalid_rows INT NOT NULL DEFAULT 0,
                duplicate_rows INT NOT NULL DEFAULT 0,
                imported_rows INT NOT NULL DEFAULT 0,
                updated_rows INT NOT NULL DEFAULT 0,
                skipped_rows INT NOT NULL DEFAULT 0,
                started_by BIGINT UNSIGNED NULL,
                started_at DATETIME NULL,
                finished_at DATETIME NULL,
                error_message TEXT NULL,
                metadata_json LONGTEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                INDEX idx_sales_import_batches_status (status),
                INDEX idx_sales_import_batches_source_type (source_type),
                INDEX idx_sales_import_batches_source_module (source_module),
                INDEX idx_sales_import_batches_file_hash (file_hash),
                INDEX idx_sales_import_batches_started_by (started_by),
                INDEX idx_sales_import_batches_created_at (created_at)
            ){$engine}",
            "CREATE TABLE IF NOT EXISTS sales_import_errors (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                import_batch_id BIGINT UNSIGNED NOT NULL,
                source_module VARCHAR(50) NOT NULL,
                `row_number` INT NULL,
                error_code VARCHAR(100) NULL,
                error_message TEXT NOT NULL,
                raw_json LONGTEXT NULL,
                normalized_json LONGTEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_sales_import_errors_batch (import_batch_id),
                INDEX idx_sales_import_errors_source (source_module),
                INDEX idx_sales_import_errors_code (error_code),
                CONSTRAINT fk_sales_import_errors_batch FOREIGN KEY (import_batch_id) REFERENCES sales_import_batches(id) ON DELETE RESTRICT
            ){$engine}",
            "CREATE TABLE IF NOT EXISTS sales_import_column_mappings (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                source_module VARCHAR(50) NOT NULL,
                source_header VARCHAR(255) NOT NULL,
                normalized_key VARCHAR(191) NOT NULL,
                required TINYINT(1) NOT NULL DEFAULT 0,
                data_type VARCHAR(50) NOT NULL DEFAULT 'string',
                active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                UNIQUE KEY uq_sales_import_mapping_source_header (source_module,source_header),
                INDEX idx_sales_import_mappings_source (source_module),
                INDEX idx_sales_import_mappings_key (normalized_key),
                INDEX idx_sales_import_mappings_active (active)
            ){$engine}",
            "CREATE TABLE IF NOT EXISTS staging_sales_data (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                import_batch_id BIGINT UNSIGNED NOT NULL,
                source_module VARCHAR(50) NOT NULL,
                `row_number` INT NOT NULL,
                raw_json LONGTEXT NOT NULL,
                normalized_json LONGTEXT NULL,
                validation_status VARCHAR(30) NOT NULL DEFAULT 'pending',
                validation_errors_json LONGTEXT NULL,
                source_unique_key VARCHAR(191) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_staging_sales_batch (import_batch_id),
                INDEX idx_staging_sales_source (source_module),
                INDEX idx_staging_sales_validation (validation_status),
                INDEX idx_staging_sales_unique_key (source_unique_key),
                CONSTRAINT fk_staging_sales_batch FOREIGN KEY (import_batch_id) REFERENCES sales_import_batches(id) ON DELETE RESTRICT
            ){$engine}",
            "CREATE TABLE IF NOT EXISTS sales_aggregate_rows (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                import_batch_id BIGINT UNSIGNED NULL,
                source_unique_key VARCHAR(191) NULL,
                unique_code VARCHAR(191) NULL,
                invoice_type VARCHAR(100) NULL,
                invoice_number VARCHAR(100) NULL,
                sub_invoice_number VARCHAR(100) NULL,
                invoice_date_raw VARCHAR(100) NULL,
                invoice_date DATE NULL,
                customer_code VARCHAR(100) NULL,
                customer_name VARCHAR(255) NULL,
                product_code VARCHAR(100) NULL,
                product_name VARCHAR(255) NULL,
                visitor_code VARCHAR(100) NULL,
                line_code VARCHAR(100) NULL,
                quantity DECIMAL(18,4) NULL,
                gross_amount DECIMAL(20,2) NULL,
                discount_amount DECIMAL(20,2) NULL,
                net_amount DECIMAL(20,2) NULL,
                return_quantity DECIMAL(18,4) NULL,
                return_amount DECIMAL(20,2) NULL,
                raw_json LONGTEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                INDEX idx_sales_aggregate_batch (import_batch_id),
                UNIQUE KEY uq_sales_aggregate_source_key (source_unique_key),
                INDEX idx_sales_aggregate_date (invoice_date),
                INDEX idx_sales_aggregate_customer (customer_code),
                INDEX idx_sales_aggregate_product (product_code),
                INDEX idx_sales_aggregate_visitor (visitor_code)
            ){$engine}",
            "CREATE TABLE IF NOT EXISTS inventory_aggregate_rows (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                import_batch_id BIGINT UNSIGNED NULL,
                source_unique_key VARCHAR(191) NULL,
                snapshot_date DATE NULL,
                warehouse_code VARCHAR(100) NULL,
                product_code VARCHAR(100) NULL,
                product_name VARCHAR(255) NULL,
                quantity DECIMAL(18,4) NULL,
                inventory_value DECIMAL(20,2) NULL,
                raw_json LONGTEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                INDEX idx_inventory_aggregate_batch (import_batch_id),
                INDEX idx_inventory_aggregate_unique_key (source_unique_key),
                INDEX idx_inventory_aggregate_snapshot (snapshot_date),
                INDEX idx_inventory_aggregate_product (product_code)
            ){$engine}",
            "CREATE TABLE IF NOT EXISTS sales_team_members (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                import_batch_id BIGINT UNSIGNED NULL,
                source_unique_key VARCHAR(191) NULL,
                user_id INT UNSIGNED NULL,
                personnel_code VARCHAR(100) NULL,
                full_name VARCHAR(255) NULL,
                role_type VARCHAR(50) NULL,
                line_code VARCHAR(100) NULL,
                supervisor_code VARCHAR(100) NULL,
                sales_manager_code VARCHAR(100) NULL,
                region_code VARCHAR(100) NULL,
                share_percent DECIMAL(8,4) NULL,
                active TINYINT(1) NOT NULL DEFAULT 1,
                raw_json LONGTEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                INDEX idx_sales_team_batch (import_batch_id),
                INDEX idx_sales_team_unique_key (source_unique_key),
                INDEX idx_sales_team_user (user_id),
                INDEX idx_sales_team_line (line_code),
                INDEX idx_sales_team_role (role_type)
            ){$engine}",
            "CREATE TABLE IF NOT EXISTS sales_customer_class_coefficients (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                import_batch_id BIGINT UNSIGNED NULL,
                source_unique_key VARCHAR(191) NULL,
                customer_class_code VARCHAR(100) NULL,
                customer_class_title VARCHAR(255) NULL,
                coefficient DECIMAL(12,6) NULL,
                effective_from DATE NULL,
                effective_to DATE NULL,
                active TINYINT(1) NOT NULL DEFAULT 1,
                raw_json LONGTEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                INDEX idx_sales_coeff_batch (import_batch_id),
                INDEX idx_sales_coeff_unique_key (source_unique_key),
                INDEX idx_sales_coeff_class (customer_class_code),
                INDEX idx_sales_coeff_effective (effective_from,effective_to)
            ){$engine}",
            "CREATE TABLE IF NOT EXISTS product_priorities (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                import_batch_id BIGINT UNSIGNED NULL,
                source_unique_key VARCHAR(191) NULL,
                product_code VARCHAR(100) NULL,
                product_name VARCHAR(255) NULL,
                brand_code VARCHAR(100) NULL,
                brand_name VARCHAR(255) NULL,
                priority_code VARCHAR(100) NULL,
                priority_rank INT NULL,
                inventory_quantity DECIMAL(18,4) NULL,
                inventory_value DECIMAL(20,2) NULL,
                active TINYINT(1) NOT NULL DEFAULT 1,
                raw_json LONGTEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                INDEX idx_product_priorities_batch (import_batch_id),
                INDEX idx_product_priorities_unique_key (source_unique_key),
                INDEX idx_product_priorities_product (product_code),
                INDEX idx_product_priorities_brand (brand_code),
                INDEX idx_product_priorities_priority (priority_code)
            ){$engine}",
            "CREATE TABLE IF NOT EXISTS sales_targets (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                import_batch_id BIGINT UNSIGNED NULL,
                source_unique_key VARCHAR(191) NULL,
                target_year SMALLINT NULL,
                target_month TINYINT NULL,
                line_code VARCHAR(100) NULL,
                product_code VARCHAR(100) NULL,
                priority_code VARCHAR(100) NULL,
                visitor_code VARCHAR(100) NULL,
                supervisor_code VARCHAR(100) NULL,
                target_quantity DECIMAL(18,4) NULL,
                target_amount DECIMAL(20,2) NULL,
                raw_json LONGTEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                INDEX idx_sales_targets_batch (import_batch_id),
                INDEX idx_sales_targets_unique_key (source_unique_key),
                INDEX idx_sales_targets_period (target_year,target_month),
                INDEX idx_sales_targets_line (line_code),
                INDEX idx_sales_targets_product (product_code),
                INDEX idx_sales_targets_visitor (visitor_code)
            ){$engine}",
            "CREATE TABLE IF NOT EXISTS commission_formula_settings (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                formula_key VARCHAR(100) NOT NULL,
                title VARCHAR(255) NULL,
                formula_expression TEXT NULL,
                settings_json LONGTEXT NULL,
                version_no INT NOT NULL DEFAULT 1,
                status VARCHAR(30) NOT NULL DEFAULT 'draft',
                effective_from DATE NULL,
                effective_to DATE NULL,
                created_by BIGINT UNSIGNED NULL,
                published_by BIGINT UNSIGNED NULL,
                published_at DATETIME NULL,
                raw_json LONGTEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                UNIQUE KEY uq_commission_formula_version (formula_key,version_no),
                INDEX idx_commission_formula_status (status),
                INDEX idx_commission_formula_effective (effective_from,effective_to)
            ){$engine}",
            "CREATE TABLE IF NOT EXISTS commission_calculation_runs (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                run_key VARCHAR(100) NOT NULL,
                period_year SMALLINT NULL,
                period_month TINYINT NULL,
                formula_version VARCHAR(100) NULL,
                status VARCHAR(30) NOT NULL DEFAULT 'pending',
                started_by BIGINT UNSIGNED NULL,
                started_at DATETIME NULL,
                finished_at DATETIME NULL,
                input_summary_json LONGTEXT NULL,
                result_summary_json LONGTEXT NULL,
                error_message TEXT NULL,
                raw_json LONGTEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                UNIQUE KEY uq_commission_run_key (run_key),
                INDEX idx_commission_runs_period (period_year,period_month),
                INDEX idx_commission_runs_status (status),
                INDEX idx_commission_runs_started_by (started_by)
            ){$engine}",
            "CREATE TABLE IF NOT EXISTS commission_calculation_results (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                calculation_run_id BIGINT UNSIGNED NOT NULL,
                result_type VARCHAR(30) NOT NULL,
                subject_key VARCHAR(191) NULL,
                user_id INT UNSIGNED NULL,
                gross_commission DECIMAL(20,2) NULL,
                reduction_amount DECIMAL(20,2) NULL,
                reward_amount DECIMAL(20,2) NULL,
                penalty_amount DECIMAL(20,2) NULL,
                final_commission DECIMAL(20,2) NULL,
                breakdown_json LONGTEXT NULL,
                raw_json LONGTEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                INDEX idx_commission_results_run (calculation_run_id),
                INDEX idx_commission_results_type (result_type),
                INDEX idx_commission_results_subject (subject_key),
                INDEX idx_commission_results_user (user_id),
                CONSTRAINT fk_commission_results_run FOREIGN KEY (calculation_run_id) REFERENCES commission_calculation_runs(id) ON DELETE RESTRICT
            ){$engine}",
        ];
    }
}
