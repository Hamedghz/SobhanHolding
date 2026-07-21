<?php

require_once __DIR__ . '/Database.php';

final class SalesPlanningModule
{
    public const PERMISSIONS = [
        'sales_planning.view' => ['مشاهده ضرایب، اولویت‌ها و اهداف فروش', 778],
        'sales_planning.manage' => ['مدیریت ضرایب و اهداف فروش', 779],
        'sales_planning.reports' => ['مشاهده گزارش تحقق اهداف فروش', 780],
    ];

    public static function repair(PDO $pdo): void
    {
        self::repairCoefficientColumns($pdo);
        self::repairPriorityColumns($pdo);
        self::repairTargetColumns($pdo);
        self::backfillCanonicalReferences($pdo);
        self::seedPermissions($pdo);
        self::repairViews($pdo);
    }

    private static function repairCoefficientColumns(PDO $pdo): void
    {
        foreach ([
            'period_id' => 'BIGINT UNSIGNED NULL',
            'guild_identity_key' => 'VARCHAR(191) NULL',
            'normalized_guild_name' => 'VARCHAR(191) NULL',
            'version_no' => 'INT UNSIGNED NOT NULL DEFAULT 1',
            'source_type' => "VARCHAR(30) NOT NULL DEFAULT 'import'",
            'created_by' => 'INT UNSIGNED NULL',
        ] as $column => $definition) {
            self::addColumn($pdo, 'sales_customer_class_coefficients', $column, $definition);
        }
        foreach ([
            'idx_sales_coeff_period' => 'period_id',
            'idx_sales_coeff_identity' => 'guild_identity_key',
            'idx_sales_coeff_active_version' => 'active,version_no',
        ] as $index => $columns) {
            self::addIndex($pdo, 'sales_customer_class_coefficients', $index, $columns);
        }
    }

    private static function repairPriorityColumns(PDO $pdo): void
    {
        foreach ([
            'period_id' => 'BIGINT UNSIGNED NULL',
            'status' => "VARCHAR(30) NOT NULL DEFAULT 'active'",
            'created_by' => 'INT UNSIGNED NULL',
        ] as $column => $definition) {
            self::addColumn($pdo, 'product_priorities', $column, $definition);
        }
        foreach ([
            'idx_product_priorities_period' => 'period_id',
            'idx_product_priorities_status' => 'status,active',
        ] as $index => $columns) {
            self::addIndex($pdo, 'product_priorities', $index, $columns);
        }
    }

    private static function repairTargetColumns(PDO $pdo): void
    {
        foreach ([
            'period_id' => 'BIGINT UNSIGNED NULL',
            'visitor_user_id' => 'INT UNSIGNED NULL',
            'line_id' => 'INT UNSIGNED NULL',
            'product_name' => 'VARCHAR(255) NULL',
            'brand_code' => 'VARCHAR(100) NULL',
            'brand_name' => 'VARCHAR(255) NULL',
            'allocation_percent' => 'DECIMAL(8,4) NULL',
            'active' => 'TINYINT(1) NOT NULL DEFAULT 1',
            'source_type' => "VARCHAR(30) NOT NULL DEFAULT 'import'",
            'created_by' => 'INT UNSIGNED NULL',
        ] as $column => $definition) {
            self::addColumn($pdo, 'sales_targets', $column, $definition);
        }
        foreach ([
            'idx_sales_targets_period_id' => 'period_id',
            'idx_sales_targets_visitor_user' => 'visitor_user_id',
            'idx_sales_targets_line_id' => 'line_id',
            'idx_sales_targets_grain' => 'period_id,visitor_user_id,line_id,product_code',
            'idx_sales_targets_active' => 'active',
        ] as $index => $columns) {
            self::addIndex($pdo, 'sales_targets', $index, $columns);
        }
    }

    private static function backfillCanonicalReferences(PDO $pdo): void
    {
        if (self::tableExists($pdo, 'sales_customer_class_coefficients')) {
            $pdo->exec(
                "UPDATE sales_customer_class_coefficients
                 SET normalized_guild_name=LOWER(TRIM(REPLACE(REPLACE(customer_class_title,'ي','ی'),'ك','ک')))
                 WHERE normalized_guild_name IS NULL AND customer_class_title IS NOT NULL AND customer_class_title<>''"
            );
            $pdo->exec(
                "UPDATE sales_customer_class_coefficients
                 SET guild_identity_key=CASE
                    WHEN customer_class_code IS NOT NULL AND customer_class_code<>'' THEN CONCAT('code:',LOWER(TRIM(customer_class_code)))
                    WHEN normalized_guild_name IS NOT NULL AND normalized_guild_name<>'' THEN CONCAT('name:',normalized_guild_name)
                    ELSE NULL END
                 WHERE guild_identity_key IS NULL"
            );
            if (self::tableExists($pdo, 'sales_import_batches')) {
                $pdo->exec(
                    'UPDATE sales_customer_class_coefficients c
                     JOIN sales_import_batches b ON b.id=c.import_batch_id
                     SET c.period_id=b.period_id
                     WHERE c.period_id IS NULL AND b.period_id IS NOT NULL'
                );
            }
        }

        if (self::tableExists($pdo, 'product_priorities') && self::tableExists($pdo, 'sales_import_batches')) {
            $pdo->exec(
                'UPDATE product_priorities p
                 JOIN sales_import_batches b ON b.id=p.import_batch_id
                 SET p.period_id=b.period_id
                 WHERE p.period_id IS NULL AND b.period_id IS NOT NULL'
            );
        }

        if (!self::tableExists($pdo, 'sales_targets')) return;
        if (self::tableExists($pdo, 'sales_import_batches')) {
            $pdo->exec(
                'UPDATE sales_targets t
                 JOIN sales_import_batches b ON b.id=t.import_batch_id
                 SET t.period_id=b.period_id
                 WHERE t.period_id IS NULL AND b.period_id IS NOT NULL'
            );
        }
        if (self::tableExists($pdo, 'system_periods')) {
            $pdo->exec(
                "UPDATE sales_targets t
                 JOIN system_periods p ON p.period_type='monthly'
                    AND p.jalali_year=t.target_year AND p.jalali_month=t.target_month
                 SET t.period_id=p.id
                 WHERE t.period_id IS NULL AND t.target_year IS NOT NULL AND t.target_month IS NOT NULL"
            );
        }
        if (self::tableExists($pdo, 'sales_lines')) {
            $pdo->exec(
                'UPDATE sales_targets t
                 JOIN sales_lines l ON l.code=t.line_code
                 SET t.line_id=l.id
                 WHERE t.line_id IS NULL AND t.line_code IS NOT NULL AND t.line_code<>""'
            );
        }
        if (self::tableExists($pdo, 'users')) {
            $pdo->exec(
                "UPDATE sales_targets t
                 JOIN users u ON u.employee_no=t.visitor_code OR u.kara_system_code=t.visitor_code
                 SET t.visitor_user_id=u.id
                 WHERE t.visitor_user_id IS NULL AND t.visitor_code IS NOT NULL AND t.visitor_code<>''"
            );
        }
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

    private static function repairViews(PDO $pdo): void
    {
        $views = [
            'vw_active_customer_class_coefficients' => "
                SELECT c.*
                FROM sales_customer_class_coefficients c
                LEFT JOIN sales_import_batches b ON b.id=c.import_batch_id
                WHERE c.active=1
                  AND (
                    c.import_batch_id IS NULL
                    OR (b.source_module='customer_coefficients' AND b.is_active_reference=1 AND b.status='committed')
                  )
                  AND (
                    c.import_batch_id IS NULL
                    OR NOT EXISTS (
                        SELECT 1 FROM sales_customer_class_coefficients manual
                        WHERE manual.import_batch_id IS NULL AND manual.active=1
                          AND manual.period_id <=> c.period_id
                          AND manual.guild_identity_key=c.guild_identity_key
                    )
                  )",
            'vw_active_product_priorities' => "
                SELECT p.*
                FROM product_priorities p
                LEFT JOIN sales_import_batches b ON b.id=p.import_batch_id
                WHERE p.active=1 AND p.status='active'
                  AND (
                    p.import_batch_id IS NULL
                    OR (b.source_module='product_priorities' AND b.is_active_reference=1 AND b.status='committed')
                  )",
            'vw_active_sales_targets' => "
                SELECT t.*
                FROM sales_targets t
                LEFT JOIN sales_import_batches b ON b.id=t.import_batch_id
                WHERE t.active=1
                  AND (
                    t.import_batch_id IS NULL
                    OR (b.source_module='sales_targets' AND b.is_active_reference=1 AND b.status='committed')
                  )
                  AND (
                    t.import_batch_id IS NULL
                    OR NOT EXISTS (
                        SELECT 1 FROM sales_targets manual
                        WHERE manual.import_batch_id IS NULL AND manual.active=1
                          AND manual.period_id=t.period_id
                          AND manual.visitor_user_id=t.visitor_user_id
                          AND manual.line_id=t.line_id
                          AND manual.product_code=t.product_code
                    )
                  )",
            'vw_sales_target_achievement' => "
                SELECT
                    t.id target_id,t.period_id,p.period_key,p.title period_title,p.start_date,p.end_date,
                    t.visitor_user_id,u.name visitor_name,u.employee_no visitor_code,
                    t.line_id,l.code line_code,l.title line_title,
                    t.product_code,t.product_name,t.brand_code,t.brand_name,
                    t.target_quantity,t.target_amount,t.allocation_percent,
                    COALESCE((
                        SELECT SUM(COALESCE(s.total_qty,s.quantity,0)-COALESCE(s.return_quantity,0))
                        FROM vw_active_sales_aggregate_rows s
                        WHERE s.invoice_date BETWEEN p.start_date AND p.end_date
                          AND s.product_code=t.product_code
                          AND s.line_code=l.code
                          AND (s.visitor_code=u.employee_no OR s.visitor_code=u.kara_system_code)
                    ),0) achievement_quantity,
                    COALESCE((
                        SELECT SUM(COALESCE(s.net_amount,0)-COALESCE(s.return_amount,0))
                        FROM vw_active_sales_aggregate_rows s
                        WHERE s.invoice_date BETWEEN p.start_date AND p.end_date
                          AND s.product_code=t.product_code
                          AND s.line_code=l.code
                          AND (s.visitor_code=u.employee_no OR s.visitor_code=u.kara_system_code)
                    ),0) achievement_amount
                FROM vw_active_sales_targets t
                JOIN system_periods p ON p.id=t.period_id
                JOIN users u ON u.id=t.visitor_user_id
                JOIN sales_lines l ON l.id=t.line_id",
            'vw_sales_target_visitor_totals' => "
                SELECT period_id,period_key,period_title,visitor_user_id,visitor_name,line_id,line_code,line_title,
                    SUM(target_quantity) target_quantity,SUM(target_amount) target_amount,
                    SUM(achievement_quantity) achievement_quantity,SUM(achievement_amount) achievement_amount
                FROM vw_sales_target_achievement
                GROUP BY period_id,period_key,period_title,visitor_user_id,visitor_name,line_id,line_code,line_title",
            'vw_sales_target_line_products' => "
                SELECT period_id,period_key,period_title,line_id,line_code,line_title,product_code,
                    MAX(product_name) product_name,MAX(brand_code) brand_code,MAX(brand_name) brand_name,
                    SUM(target_quantity) target_quantity,SUM(target_amount) target_amount,
                    SUM(achievement_quantity) achievement_quantity,SUM(achievement_amount) achievement_amount
                FROM vw_sales_target_achievement
                GROUP BY period_id,period_key,period_title,line_id,line_code,line_title,product_code",
            'vw_sales_target_line_totals' => "
                SELECT period_id,period_key,period_title,line_id,line_code,line_title,
                    SUM(target_quantity) target_quantity,SUM(target_amount) target_amount,
                    SUM(achievement_quantity) achievement_quantity,SUM(achievement_amount) achievement_amount
                FROM vw_sales_target_achievement
                GROUP BY period_id,period_key,period_title,line_id,line_code,line_title",
            'vw_sales_target_brand_totals' => "
                SELECT period_id,period_key,period_title,
                    COALESCE(NULLIF(brand_code,''),CONCAT('name:',COALESCE(NULLIF(brand_name,''),'بدون برند'))) brand_key,
                    COALESCE(NULLIF(brand_name,''),'بدون برند') brand_name,
                    SUM(target_quantity) target_quantity,SUM(target_amount) target_amount,
                    SUM(achievement_quantity) achievement_quantity,SUM(achievement_amount) achievement_amount
                FROM vw_sales_target_achievement
                GROUP BY period_id,period_key,period_title,brand_key,brand_name",
        ];
        foreach ($views as $name => $select) {
            try {
                $pdo->exec("CREATE OR REPLACE VIEW `{$name}` AS {$select}");
            } catch (Throwable $e) {
                error_log('Sales planning view repair: ' . $name . ': ' . $e->getMessage());
            }
        }
    }

    private static function addColumn(PDO $pdo, string $table, string $column, string $definition): void
    {
        if (!self::tableExists($pdo, $table) || self::columnExists($pdo, $table, $column)) return;
        $pdo->exec("ALTER TABLE `{$table}` ADD `{$column}` {$definition}");
    }

    private static function addIndex(PDO $pdo, string $table, string $index, string $columns): void
    {
        if (!self::tableExists($pdo, $table) || self::indexExists($pdo, $table, $index)) return;
        foreach (array_map('trim', explode(',', $columns)) as $column) {
            if (!self::columnExists($pdo, $table, $column)) return;
        }
        $quoted = implode(',', array_map(static fn(string $column): string => "`{$column}`", array_map('trim', explode(',', $columns))));
        $pdo->exec("ALTER TABLE `{$table}` ADD INDEX `{$index}` ({$quoted})");
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
