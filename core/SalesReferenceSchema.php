<?php
require_once __DIR__ . '/Database.php';

class SalesReferenceSchema
{
    public const SOURCE_SALES = 'sales_aggregate';
    public const SOURCE_INVENTORY = 'inventory_aggregate';

    public const PERMISSIONS = [
        'sales_reference_upload' => ['ورود اطلاعات مرجع فروش', 768],
        'sales_reference_commit' => ['تایید اطلاعات مرجع فروش', 769],
        'sales_reference_view_batches' => ['مشاهده تاریخچه اطلاعات مرجع', 770],
        'sales_reference_view_errors' => ['مشاهده خطاهای اطلاعات مرجع', 771],
        'sales_reference_manage_active_batch' => ['مدیریت Batch فعال مرجع', 772],
        'sales_reference_view_status' => ['مشاهده وضعیت دیتای مرجع', 773],
    ];

    public static function repair(PDO $pdo): void
    {
        foreach (self::statements() as $statement) {
            $pdo->exec($statement);
        }

        self::repairBatchColumns($pdo);
        self::repairStagingColumns($pdo);
        self::repairFinalColumns($pdo);
        self::seedPermissions($pdo);
        self::repairViews($pdo);
    }

    public static function tableNames(): array
    {
        return [
            'sales_reference_import_batches',
            'staging_sales_reference_rows',
            'sales_reference_import_errors',
            'sales_aggregate_rows',
            'inventory_aggregate_rows',
        ];
    }

    private static function statements(): array
    {
        $engine = ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        return [
            "CREATE TABLE IF NOT EXISTS sales_reference_import_batches (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                source_module VARCHAR(50) NOT NULL,
                source_type VARCHAR(50) NOT NULL DEFAULT 'excel_upload',
                original_file_name VARCHAR(255) NULL,
                stored_file_path VARCHAR(500) NULL,
                file_hash VARCHAR(128) NULL,
                detected_sheet VARCHAR(255) NULL,
                detected_table VARCHAR(255) NULL,
                detected_range VARCHAR(100) NULL,
                period_key VARCHAR(50) NULL,
                import_mode VARCHAR(50) NOT NULL DEFAULT 'replace_reference',
                status VARCHAR(50) NOT NULL DEFAULT 'uploaded',
                is_active_reference TINYINT NOT NULL DEFAULT 0,
                activated_at DATETIME NULL,
                activated_by BIGINT UNSIGNED NULL,
                total_rows INT NOT NULL DEFAULT 0,
                valid_rows INT NOT NULL DEFAULT 0,
                invalid_rows INT NOT NULL DEFAULT 0,
                duplicate_rows INT NOT NULL DEFAULT 0,
                inserted_rows INT NOT NULL DEFAULT 0,
                updated_rows INT NOT NULL DEFAULT 0,
                skipped_rows INT NOT NULL DEFAULT 0,
                started_by BIGINT UNSIGNED NULL,
                started_at DATETIME NULL,
                finished_at DATETIME NULL,
                error_message TEXT NULL,
                metadata_json LONGTEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                INDEX idx_ref_batches_source_module (source_module),
                INDEX idx_ref_batches_source_type (source_type),
                INDEX idx_ref_batches_status (status),
                INDEX idx_ref_batches_period (period_key),
                INDEX idx_ref_batches_active (is_active_reference),
                INDEX idx_ref_batches_hash (file_hash),
                INDEX idx_ref_batches_created (created_at)
            ){$engine}",
            "CREATE TABLE IF NOT EXISTS staging_sales_reference_rows (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                import_batch_id BIGINT UNSIGNED NOT NULL,
                source_module VARCHAR(50) NOT NULL,
                `row_number` INT NOT NULL,
                source_unique_key VARCHAR(191) NULL,
                raw_json LONGTEXT NOT NULL,
                normalized_json LONGTEXT NULL,
                validation_status VARCHAR(50) NOT NULL DEFAULT 'pending',
                validation_errors_json LONGTEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_ref_staging_batch (import_batch_id),
                INDEX idx_ref_staging_source (source_module),
                INDEX idx_ref_staging_key (source_unique_key),
                INDEX idx_ref_staging_status (validation_status)
            ){$engine}",
            "CREATE TABLE IF NOT EXISTS sales_reference_import_errors (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                import_batch_id BIGINT UNSIGNED NOT NULL,
                source_module VARCHAR(50) NOT NULL,
                `row_number` INT NULL,
                error_code VARCHAR(100) NULL,
                error_message TEXT NOT NULL,
                raw_json LONGTEXT NULL,
                normalized_json LONGTEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_ref_errors_batch (import_batch_id),
                INDEX idx_ref_errors_source (source_module),
                INDEX idx_ref_errors_code (error_code)
            ){$engine}",
        ];
    }

    private static function repairBatchColumns(PDO $pdo): void
    {
        if (!self::tableExists($pdo, 'sales_import_batches')) return;
        $columns = [
            'period_key' => 'VARCHAR(50) NULL AFTER detected_table',
            'is_active_reference' => 'TINYINT NOT NULL DEFAULT 0 AFTER status',
            'activated_at' => 'DATETIME NULL AFTER is_active_reference',
            'activated_by' => 'BIGINT UNSIGNED NULL AFTER activated_at',
            'stored_file_path' => 'VARCHAR(500) NULL AFTER file_name',
            'detected_range' => 'VARCHAR(100) NULL AFTER detected_table',
        ];
        foreach ($columns as $column => $definition) {
            self::addColumn($pdo, 'sales_import_batches', $column, $definition);
        }
        self::addIndex($pdo, 'sales_import_batches', 'idx_sales_import_batches_period', 'period_key');
        self::addIndex($pdo, 'sales_import_batches', 'idx_sales_import_batches_active_ref', 'is_active_reference');
    }

    private static function repairStagingColumns(PDO $pdo): void
    {
        if (!self::tableExists($pdo, 'staging_sales_data')) return;
        self::addColumn($pdo, 'staging_sales_data', 'reference_synced_at', 'DATETIME NULL');
    }

    private static function repairFinalColumns(PDO $pdo): void
    {
        if (self::tableExists($pdo, 'sales_aggregate_rows')) {
            $salesText = [
                'period_key'=>'VARCHAR(50) NULL','invoice_sub_number'=>'VARCHAR(100) NULL','visitor_name'=>'VARCHAR(255) NULL',
                'line_name'=>'VARCHAR(100) NULL','customer_address'=>'TEXT NULL','customer_mobile'=>'VARCHAR(100) NULL',
                'customer_phone'=>'VARCHAR(100) NULL','customer_grade'=>'VARCHAR(100) NULL','customer_national_code'=>'VARCHAR(100) NULL',
                'customer_guild_code'=>'VARCHAR(100) NULL','customer_guild_name'=>'VARCHAR(255) NULL','customer_role_code'=>'VARCHAR(100) NULL',
                'city_code'=>'VARCHAR(100) NULL','city_name'=>'VARCHAR(255) NULL','province_code'=>'VARCHAR(100) NULL','province_name'=>'VARCHAR(255) NULL',
                'route_code'=>'VARCHAR(100) NULL','route_name'=>'VARCHAR(255) NULL','product_identifier'=>'VARCHAR(191) NULL',
                'manufacturer_code'=>'VARCHAR(100) NULL','manufacturer_name'=>'VARCHAR(255) NULL','group_code'=>'VARCHAR(100) NULL','group_name'=>'VARCHAR(255) NULL',
                'product_tree_group_code'=>'VARCHAR(100) NULL','product_tree_group_name'=>'VARCHAR(255) NULL','warehouse_code'=>'VARCHAR(100) NULL',
                'warehouse_name'=>'VARCHAR(255) NULL','branch_code'=>'VARCHAR(100) NULL','branch_name'=>'VARCHAR(255) NULL',
                'distributor_code'=>'VARCHAR(100) NULL','distributor_name'=>'VARCHAR(255) NULL','driver_code'=>'VARCHAR(100) NULL',
                'driver_name'=>'VARCHAR(255) NULL','driver_name_from_invoice'=>'VARCHAR(255) NULL','supervisor_code'=>'VARCHAR(100) NULL',
                'supervisor_name'=>'VARCHAR(255) NULL','sales_manager_code'=>'VARCHAR(100) NULL','sales_manager_name'=>'VARCHAR(255) NULL',
                'output_number'=>'VARCHAR(100) NULL','sale_price_class'=>'VARCHAR(255) NULL','brand_code'=>'VARCHAR(100) NULL','brand_name'=>'VARCHAR(255) NULL',
                'payment_method'=>'VARCHAR(255) NULL','customer_birth_date_raw'=>'VARCHAR(50) NULL','customer_signboard'=>'VARCHAR(255) NULL',
                'reference_number'=>'VARCHAR(100) NULL','base_unit'=>'VARCHAR(100) NULL','part_unit'=>'VARCHAR(100) NULL',
                'formula_number_1'=>'VARCHAR(100) NULL','formula_number_2'=>'VARCHAR(100) NULL','formula_number_3'=>'VARCHAR(100) NULL',
                'formula_number_4'=>'VARCHAR(100) NULL','formula_number_5'=>'VARCHAR(100) NULL','formula_name_1'=>'VARCHAR(255) NULL',
                'formula_name_2'=>'VARCHAR(255) NULL','formula_name_3'=>'VARCHAR(255) NULL','formula_name_4'=>'VARCHAR(255) NULL',
                'formula_name_5'=>'VARCHAR(255) NULL','circulation_month'=>'VARCHAR(100) NULL','weighted_flag'=>'VARCHAR(50) NULL',
                'consumer_flag'=>'VARCHAR(50) NULL','product_consumer_flag'=>'VARCHAR(50) NULL','purchase_class_type'=>'VARCHAR(255) NULL',
                'commission_type'=>'VARCHAR(255) NULL','product_priority'=>'VARCHAR(50) NULL',
            ];
            $salesDecimal = ['product_weight','product_volume','carton_size','carton_qty','part_qty','total_qty','net_carton_qty','unit_price','discount_percent_1','discount_amount_1','discount_percent_2','discount_amount_2','discount_percent_3','discount_amount_3','discount_percent_4','discount_amount_4','discount_percent_5','discount_amount_5','discount_total','amount_after_discount','tax_amount','duty_amount','tax_duty_total','fifo_cost','average_cost','tax_percent','duty_percent','purchase_cost_price','coefficient','coefficient_sales_amount'];
            foreach ($salesText as $column => $definition) self::addColumn($pdo, 'sales_aggregate_rows', $column, $definition);
            foreach ($salesDecimal as $column) self::addColumn($pdo, 'sales_aggregate_rows', $column, 'DECIMAL(18,4) NULL');
            self::addColumn($pdo, 'sales_aggregate_rows', 'customer_birth_date', 'DATE NULL');
            self::aliasColumn($pdo, 'sales_aggregate_rows', 'discount_total', 'discount_amount');
            self::aliasColumn($pdo, 'sales_aggregate_rows', 'total_qty', 'quantity');
            foreach (['period_key','invoice_number','visitor_code','visitor_name','customer_code','product_code','brand_code','brand_name','line_code','line_name','supervisor_code','supervisor_name','sales_manager_code','sales_manager_name','city_code','city_name','route_code','route_name'] as $column) {
                self::addIndex($pdo, 'sales_aggregate_rows', 'idx_sales_ref_' . $column, $column);
            }
        }

        if (self::tableExists($pdo, 'inventory_aggregate_rows')) {
            $aliases = [
                'period_key'=>'VARCHAR(50) NULL','row_index'=>'INT NULL','tree_group_code'=>'VARCHAR(100) NULL',
                'tree_group_name'=>'VARCHAR(255) NULL','period_carton_stock'=>'DECIMAL(18,4) NULL',
                'period_part_stock'=>'DECIMAL(18,4) NULL','period_total_stock'=>'DECIMAL(18,4) NULL',
                'outbound_pricee'=>'DECIMAL(18,4) NULL','sudozian'=>'DECIMAL(18,4) NULL',
            ];
            foreach ($aliases as $column => $definition) self::addColumn($pdo, 'inventory_aggregate_rows', $column, $definition);
            $extraAmounts = ['sales_return_total_amount','sales_return_discount_amount','sales_return_tax_amount','sales_return_duty_amount','sales_return_payable_amount','purchase_return_total_amount','purchase_return_discount_amount','purchase_return_tax_amount','purchase_return_duty_amount','purchase_return_payable_amount','opening_total_amount','opening_discount_amount','opening_tax_amount','opening_duty_amount','opening_payable_amount','inbound_total_amount','inbound_discount_amount','inbound_tax_amount','inbound_duty_amount','inbound_payable_amount','outbound_total_amount','outbound_discount_amount','outbound_tax_amount','outbound_duty_amount','outbound_payable_amount'];
            foreach ($extraAmounts as $column) self::addColumn($pdo, 'inventory_aggregate_rows', $column, 'DECIMAL(18,4) NULL');
            self::aliasColumn($pdo, 'inventory_aggregate_rows', 'period_carton_stock', 'current_period_carton_qty');
            self::aliasColumn($pdo, 'inventory_aggregate_rows', 'period_part_stock', 'current_period_part_qty');
            self::aliasColumn($pdo, 'inventory_aggregate_rows', 'period_total_stock', 'current_period_total_qty');
            self::aliasColumn($pdo, 'inventory_aggregate_rows', 'tree_group_code', 'product_tree_group_code');
            self::aliasColumn($pdo, 'inventory_aggregate_rows', 'tree_group_name', 'product_tree_group_name');
            foreach (['period_key','product_code','product_name','brand_name','manufacturer_code','manufacturer_name','group_code','group_name','barcode','current_total_stock','period_total_stock','last_purchase_date','expire_date'] as $column) {
                self::addIndex($pdo, 'inventory_aggregate_rows', 'idx_inventory_ref_' . $column, $column);
            }
        }
    }

    private static function seedPermissions(PDO $pdo): void
    {
        if (!self::tableExists($pdo, 'modules')) return;
        $stmt = $pdo->prepare(
            'INSERT INTO modules (module_key,module_title,sort_order,status,created_at)
             VALUES (?,?,?,"active",NOW())
             ON DUPLICATE KEY UPDATE module_title=VALUES(module_title),sort_order=VALUES(sort_order),status="active"'
        );
        foreach (self::PERMISSIONS as $key => [$title, $sortOrder]) {
            $stmt->execute([$key, $title, $sortOrder]);
        }
    }

    private static function repairViews(PDO $pdo): void
    {
        $views = [
            'vw_active_sales_aggregate_rows' => "SELECT r.* FROM sales_aggregate_rows r INNER JOIN sales_import_batches b ON b.id=r.import_batch_id AND b.source_module='sales_aggregate' AND b.is_active_reference=1 AND b.status='committed'",
            'vw_active_inventory_aggregate_rows' => "SELECT r.* FROM inventory_aggregate_rows r INNER JOIN sales_import_batches b ON b.id=r.import_batch_id AND b.source_module='inventory_aggregate' AND b.is_active_reference=1 AND b.status='committed'",
            'vw_sales_reference_summary' => "SELECT COUNT(DISTINCT invoice_number) total_invoice_count,COUNT(DISTINCT customer_code) total_customer_count,COUNT(DISTINCT product_code) total_product_count,COUNT(DISTINCT brand_name) total_brand_count,COUNT(DISTINCT visitor_code) total_visitor_count,COALESCE(SUM(gross_amount),0) gross_amount,COALESCE(SUM(discount_total),0) discount_total,COALESCE(SUM(amount_after_discount),0) amount_after_discount,COALESCE(SUM(tax_amount),0) tax_amount,COALESCE(SUM(net_amount),0) net_amount,COALESCE(SUM(total_qty),0) total_quantity FROM vw_active_sales_aggregate_rows",
            'vw_inventory_reference_summary' => "SELECT COUNT(DISTINCT product_code) total_product_count,COUNT(DISTINCT brand_name) total_brand_count,COUNT(DISTINCT group_code) total_group_count,COALESCE(SUM(COALESCE(current_total_stock,period_total_stock)),0) total_stock_quantity,COALESCE(SUM(stock_value_by_last_cost),0) stock_value_by_last_cost,COALESCE(SUM(stock_value_by_sale_price_1),0) stock_value_by_sale_price_1 FROM vw_active_inventory_aggregate_rows",
            'vw_sales_by_manager_reference' => "SELECT sales_manager_code,sales_manager_name,COUNT(DISTINCT invoice_number) invoice_count,COALESCE(SUM(net_amount),0) net_amount,COALESCE(SUM(total_qty),0) total_qty FROM vw_active_sales_aggregate_rows GROUP BY sales_manager_code,sales_manager_name",
            'vw_sales_by_supervisor_reference' => "SELECT supervisor_code,supervisor_name,COUNT(DISTINCT invoice_number) invoice_count,COALESCE(SUM(net_amount),0) net_amount,COALESCE(SUM(total_qty),0) total_qty FROM vw_active_sales_aggregate_rows GROUP BY supervisor_code,supervisor_name",
            'vw_sales_by_visitor_reference' => "SELECT visitor_code,visitor_name,COUNT(DISTINCT invoice_number) invoice_count,COALESCE(SUM(net_amount),0) net_amount,COALESCE(SUM(total_qty),0) total_qty FROM vw_active_sales_aggregate_rows GROUP BY visitor_code,visitor_name",
            'vw_sales_by_line_reference' => "SELECT line_code,line_name,COUNT(DISTINCT invoice_number) invoice_count,COALESCE(SUM(net_amount),0) net_amount,COALESCE(SUM(total_qty),0) total_qty FROM vw_active_sales_aggregate_rows GROUP BY line_code,line_name",
            'vw_sales_by_brand_reference' => "SELECT brand_code,brand_name,COUNT(DISTINCT product_code) product_count,COALESCE(SUM(net_amount),0) net_amount,COALESCE(SUM(total_qty),0) total_qty FROM vw_active_sales_aggregate_rows GROUP BY brand_code,brand_name",
            'vw_sales_by_customer_reference' => "SELECT customer_code,customer_name,COUNT(DISTINCT invoice_number) invoice_count,COALESCE(SUM(net_amount),0) net_amount,COALESCE(SUM(total_qty),0) total_qty FROM vw_active_sales_aggregate_rows GROUP BY customer_code,customer_name",
            'vw_sales_by_product_reference' => "SELECT product_code,product_name,brand_name,COALESCE(SUM(net_amount),0) net_amount,COALESCE(SUM(total_qty),0) total_qty FROM vw_active_sales_aggregate_rows GROUP BY product_code,product_name,brand_name",
            'vw_inventory_by_brand_reference' => "SELECT brand_name,COUNT(DISTINCT product_code) product_count,COALESCE(SUM(COALESCE(current_total_stock,period_total_stock)),0) total_stock_quantity,COALESCE(SUM(stock_value_by_last_cost),0) stock_value_by_last_cost FROM vw_active_inventory_aggregate_rows GROUP BY brand_name",
            'vw_inventory_by_product_reference' => "SELECT product_code,product_name,brand_name,group_code,group_name,COALESCE(SUM(COALESCE(current_total_stock,period_total_stock)),0) total_stock_quantity,COALESCE(SUM(stock_value_by_last_cost),0) stock_value_by_last_cost FROM vw_active_inventory_aggregate_rows GROUP BY product_code,product_name,brand_name,group_code,group_name",
        ];
        foreach ($views as $name => $select) {
            try {
                $pdo->exec("CREATE OR REPLACE VIEW `{$name}` AS {$select}");
            } catch (Throwable $e) {
                error_log('Sales reference view repair: ' . $name . ': ' . $e->getMessage());
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
        try {
            $pdo->exec("ALTER TABLE `{$table}` ADD INDEX `{$index}` (`{$column}`)");
        } catch (Throwable $e) {
            error_log('Sales reference index repair: ' . $table . '.' . $index . ': ' . $e->getMessage());
        }
    }

    private static function aliasColumn(PDO $pdo, string $table, string $target, string $source): void
    {
        if (!self::columnExists($pdo, $table, $target) || !self::columnExists($pdo, $table, $source)) return;
        try {
            $pdo->exec("UPDATE `{$table}` SET `{$target}`=`{$source}` WHERE `{$target}` IS NULL AND `{$source}` IS NOT NULL");
        } catch (Throwable $e) {
            error_log('Sales reference alias repair: ' . $table . '.' . $target . ': ' . $e->getMessage());
        }
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
