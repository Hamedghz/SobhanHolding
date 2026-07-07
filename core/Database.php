<?php
require_once __DIR__ . '/Config.php';

class Database
{
    private static ?PDO $pdo = null;
    private static bool $migrated = false;

    public static function connection(): PDO
    {
        if (self::$pdo === null) {
            Config::ensureInstalled();
            $config = Config::db();
            foreach (['host', 'name', 'user'] as $key) {
                if (trim((string)($config[$key] ?? '')) === '') {
                    throw new RuntimeException('Database configuration is incomplete. Run install.php again.');
                }
            }
            $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $config['host'], $config['name'], $config['charset'] ?? 'utf8mb4');
            self::$pdo = new PDO($dsn, $config['user'], $config['pass'] ?? '', [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            self::migrate();
        }
        return self::$pdo;
    }

    public static function tableExists(string $table): bool
    {
        $stmt = self::$pdo->prepare('SELECT COUNT(*) c FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
        $stmt->execute([$table]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public static function columnExists(string $table, string $column): bool
    {
        $stmt = self::$pdo->prepare('SELECT COUNT(*) c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
        $stmt->execute([$table, $column]);
        return (int)$stmt->fetchColumn() > 0;
    }

    private static function migrate(): void
    {
        // Runtime bootstrap: normal admin/page requests reach this method through Database::connection().
        // install.php executes structure-only schema.sql. Seed data is run explicitly through SeedManager.
        if (self::$migrated) return;
        self::$migrated = true;
        $pdo = self::$pdo;
        if (!$pdo instanceof PDO) return;

        $statements = [
            "CREATE TABLE IF NOT EXISTS manager_employees (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                manager_id INT UNSIGNED NOT NULL,
                employee_id INT UNSIGNED NOT NULL,
                assigned_by INT UNSIGNED NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_manager_employee (manager_id, employee_id),
                INDEX idx_manager_employees_manager (manager_id),
                INDEX idx_manager_employees_employee (employee_id),
                CONSTRAINT fk_manager_employees_manager FOREIGN KEY (manager_id) REFERENCES users(id) ON DELETE CASCADE,
                CONSTRAINT fk_manager_employees_employee FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE CASCADE,
                CONSTRAINT fk_manager_employees_assigned_by FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS modules (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                module_key VARCHAR(100) NOT NULL UNIQUE,
                module_title VARCHAR(190) NOT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                status ENUM('active','disabled') NOT NULL DEFAULT 'active',
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS user_permissions (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                module_key VARCHAR(100) NOT NULL,
                can_view TINYINT(1) NOT NULL DEFAULT 0,
                can_create TINYINT(1) NOT NULL DEFAULT 0,
                can_edit TINYINT(1) NOT NULL DEFAULT 0,
                can_delete TINYINT(1) NOT NULL DEFAULT 0,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_user_module (user_id, module_key),
                INDEX idx_permissions_user (user_id),
                CONSTRAINT fk_permissions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS file_shares (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                file_id INT UNSIGNED NOT NULL,
                shared_with_user_id INT UNSIGNED NOT NULL,
                shared_by INT UNSIGNED NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_file_share_user (file_id, shared_with_user_id),
                INDEX idx_file_shares_user (shared_with_user_id),
                CONSTRAINT fk_file_shares_file FOREIGN KEY (file_id) REFERENCES user_files(id) ON DELETE CASCADE,
                CONSTRAINT fk_file_shares_user FOREIGN KEY (shared_with_user_id) REFERENCES users(id) ON DELETE CASCADE,
                CONSTRAINT fk_file_shares_by FOREIGN KEY (shared_by) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS accounting_collections (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                collector_role VARCHAR(80) NOT NULL,
                full_name VARCHAR(190) NOT NULL,
                invoice_number VARCHAR(120) NOT NULL,
                description TEXT NULL,
                city VARCHAR(120) NOT NULL,
                image_path VARCHAR(255) NOT NULL,
                original_name VARCHAR(255) NOT NULL,
                mime_type VARCHAR(120) NOT NULL,
                file_size INT UNSIGNED NOT NULL,
                status ENUM('sent','registered','needs_followup') NOT NULL DEFAULT 'sent',
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_accounting_status (status),
                INDEX idx_accounting_invoice (invoice_number),
                INDEX idx_accounting_city (city)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS accounting_roles (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(120) NOT NULL UNIQUE,
                sort_order INT NOT NULL DEFAULT 0,
                status ENUM('active','disabled') NOT NULL DEFAULT 'active',
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS accounting_cities (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(120) NOT NULL UNIQUE,
                sort_order INT NOT NULL DEFAULT 0,
                status ENUM('active','disabled') NOT NULL DEFAULT 'active',
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS ceo_dashboard_lines (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                report_date DATE NULL,
                line_code VARCHAR(10) NOT NULL,
                line_title VARCHAR(100) NULL,
                sales_amount BIGINT NOT NULL DEFAULT 0,
                qty INT NOT NULL DEFAULT 0,
                target_qty INT NOT NULL DEFAULT 0,
                target_amount BIGINT NOT NULL DEFAULT 0,
                supervisor_name VARCHAR(150) NULL,
                sales_manager_name VARCHAR(150) NULL,
                supervisor_user_id INT UNSIGNED NULL,
                sales_manager_user_id INT UNSIGNED NULL,
                sort_order INT NOT NULL DEFAULT 0,
                active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_ceo_lines_report (report_date),
                INDEX idx_ceo_lines_code (line_code),
                INDEX idx_ceo_lines_active (active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS ceo_dashboard_visitors (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                report_date DATE NULL,
                line_code VARCHAR(10) NOT NULL,
                visitor_name VARCHAR(150) NOT NULL,
                target_qty INT NOT NULL DEFAULT 0,
                qty INT NOT NULL DEFAULT 0,
                target_amount BIGINT NOT NULL DEFAULT 0,
                sales_amount BIGINT NOT NULL DEFAULT 0,
                user_id INT UNSIGNED NULL,
                sort_order INT NOT NULL DEFAULT 0,
                active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_ceo_visitors_report (report_date),
                INDEX idx_ceo_visitors_code (line_code),
                INDEX idx_ceo_visitors_active (active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS ceo_dashboard_periods (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(150) NOT NULL,
                from_date DATE NULL,
                to_date DATE NULL,
                active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_ceo_periods_active (active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS ceo_dashboard_manual_metrics (
                id INT AUTO_INCREMENT PRIMARY KEY,
                period_key VARCHAR(50) NOT NULL,
                gross_sales DECIMAL(18,2) DEFAULT 0,
                discounts DECIMAL(18,2) DEFAULT 0,
                net_sales DECIMAL(18,2) DEFAULT 0,
                source VARCHAR(50) DEFAULT 'excel_import',
                uploaded_file_name VARCHAR(255) NULL,
                imported_by INT NULL,
                imported_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_ceo_dashboard_manual_period (period_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS pharmacies (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(150) NOT NULL,
                slug VARCHAR(100) NOT NULL UNIQUE,
                sort_order INT NOT NULL DEFAULT 0,
                active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_pharmacies_active (active),
                INDEX idx_pharmacies_sort (sort_order)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS pharmacy_dashboard_metrics (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                pharmacy_id INT UNSIGNED NOT NULL,
                report_date DATE NULL,
                daily_sales BIGINT NOT NULL DEFAULT 0,
                monthly_sales BIGINT NOT NULL DEFAULT 0,
                supplier_purchase_amount BIGINT NOT NULL DEFAULT 0,
                supplier_sales_amount BIGINT NOT NULL DEFAULT 0,
                open_invoice_amount BIGINT NOT NULL DEFAULT 0,
                expenses_amount BIGINT NOT NULL DEFAULT 0,
                pending_checks_amount BIGINT NOT NULL DEFAULT 0,
                sort_order INT NOT NULL DEFAULT 0,
                active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_pharmacy_metrics_pharmacy (pharmacy_id),
                INDEX idx_pharmacy_metrics_report (report_date),
                INDEX idx_pharmacy_metrics_active (active),
                CONSTRAINT fk_pharmacy_metrics_pharmacy FOREIGN KEY (pharmacy_id) REFERENCES pharmacies(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS knowledge_documents (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uploaded_by INT UNSIGNED NULL,
                original_name VARCHAR(255) NOT NULL,
                stored_name VARCHAR(255) NOT NULL,
                file_path VARCHAR(255) NOT NULL,
                extension VARCHAR(10) NOT NULL,
                mime_type VARCHAR(120) NOT NULL,
                file_size INT UNSIGNED NOT NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_knowledge_documents_uploaded_by (uploaded_by),
                INDEX idx_knowledge_documents_created_at (created_at),
                CONSTRAINT fk_knowledge_documents_uploaded_by FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        ];
        foreach ($statements as $statement) {
            $pdo->exec($statement);
        }

        $manualMetricColumns = [
            'period_key' => 'VARCHAR(50) NULL',
            'gross_sales' => 'DECIMAL(18,2) DEFAULT 0',
            'discounts' => 'DECIMAL(18,2) DEFAULT 0',
            'net_sales' => 'DECIMAL(18,2) DEFAULT 0',
            'source' => "VARCHAR(50) DEFAULT 'excel_import'",
            'uploaded_file_name' => 'VARCHAR(255) NULL',
            'imported_by' => 'INT NULL',
            'imported_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
        ];
        foreach ($manualMetricColumns as $column => $definition) {
            if (self::tableExists('ceo_dashboard_manual_metrics') && !self::columnExists('ceo_dashboard_manual_metrics', $column)) {
                $pdo->exec("ALTER TABLE ceo_dashboard_manual_metrics ADD `{$column}` {$definition}");
            }
        }
        if (self::tableExists('ceo_dashboard_manual_metrics') && self::columnExists('ceo_dashboard_manual_metrics', 'period_key')) {
            $indexStmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=?');
            $indexStmt->execute(['ceo_dashboard_manual_metrics', 'uniq_ceo_dashboard_manual_period']);
            if ((int)$indexStmt->fetchColumn() === 0) {
                $duplicate = $pdo->query('SELECT period_key FROM ceo_dashboard_manual_metrics WHERE period_key IS NOT NULL GROUP BY period_key HAVING COUNT(*)>1 LIMIT 1')->fetchColumn();
                if ($duplicate === false) {
                    try {
                        $pdo->exec('ALTER TABLE ceo_dashboard_manual_metrics ADD UNIQUE KEY uniq_ceo_dashboard_manual_period (period_key)');
                    } catch (Throwable $e) {
                        error_log('CEO manual metrics unique index: ' . $e->getMessage());
                    }
                }
            }
        }

        require_once __DIR__ . '/ManagerDashboard.php';
        ManagerDashboard::repair($pdo);
        require_once __DIR__ . '/HrModule.php';
        HrModule::repair($pdo);
        require_once __DIR__ . '/SystemMaintenance.php';
        SystemMaintenance::repair($pdo);
        require_once __DIR__ . '/OrgModule.php';
        OrgModule::repair($pdo);
        require_once __DIR__ . '/WorkPlannerModule.php';
        WorkPlannerModule::repair($pdo);
        require_once __DIR__ . '/PersonalPlannerModule.php';
        PersonalPlannerModule::repair($pdo);
        require_once __DIR__ . '/ThemeProfile.php';
        ThemeProfile::repair($pdo);
        require_once __DIR__ . '/LetterModule.php';
        LetterModule::repair($pdo);
        require_once __DIR__ . '/EmailHubModule.php';
        EmailHubModule::repair($pdo);
        require_once __DIR__ . '/WorkforceModule.php';
        WorkforceModule::repair($pdo);
        require_once __DIR__ . '/ManagementReportsModule.php';
        ManagementReportsModule::repair($pdo);
        require_once __DIR__ . '/ManagementMeetingsModule.php';
        ManagementMeetingsModule::repair($pdo);
        require_once __DIR__ . '/HrAttendanceModule.php';
        HrAttendanceModule::repair($pdo);
        require_once __DIR__ . '/FileBackupModule.php';
        FileBackupModule::repair($pdo);
        require_once __DIR__ . '/MessengerModule.php';
        MessengerModule::repair($pdo);
        require_once __DIR__ . '/../lib/NotificationService.php';
        NotificationService::repair($pdo);
        require_once __DIR__ . '/WindowsNotificationHubModule.php';
        WindowsNotificationHubModule::repair($pdo);
        require_once __DIR__ . '/SalesOfferBudgetModule.php';
        SalesOfferBudgetModule::repair($pdo);
        require_once __DIR__ . '/SalesDataSchema.php';
        SalesDataSchema::repair($pdo);

        if (self::tableExists('users')) {
            if (!self::columnExists('users', 'description')) {
                $pdo->exec('ALTER TABLE users ADD description TEXT NULL AFTER status');
            }
            if (!self::columnExists('users', 'upload_quota_mb')) {
                $pdo->exec('ALTER TABLE users ADD upload_quota_mb INT NULL DEFAULT NULL AFTER description');
            }
            $pdo->exec("ALTER TABLE users MODIFY role ENUM('super_admin','admin','manager','employee') NOT NULL DEFAULT 'employee'");
        }
        if (self::tableExists('survey_results') && !self::columnExists('survey_results', 'employee_id')) {
            $pdo->exec('ALTER TABLE survey_results ADD employee_id INT UNSIGNED NULL AFTER user_id, ADD INDEX idx_survey_results_employee (employee_id)');
            try {
                $pdo->exec('ALTER TABLE survey_results ADD CONSTRAINT fk_result_employee FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE SET NULL');
            } catch (Throwable $ignored) {}
        }
        if (self::tableExists('survey_results') && self::columnExists('survey_results', 'employee_name')) {
            try {
                $pdo->exec('ALTER TABLE survey_results MODIFY employee_name VARCHAR(190) NULL');
            } catch (Throwable $ignored) {}
        }
        if (self::tableExists('user_files') && !self::columnExists('user_files', 'visibility')) {
            $pdo->exec("ALTER TABLE user_files ADD visibility ENUM('private','shared') NOT NULL DEFAULT 'private' AFTER file_size");
        }
        $ceoLineColumns = [
            'report_date' => 'ADD report_date DATE NULL AFTER id',
            'line_code' => 'ADD line_code VARCHAR(10) NOT NULL DEFAULT "" AFTER report_date',
            'line_title' => 'ADD line_title VARCHAR(100) NULL AFTER line_code',
            'sales_amount' => 'ADD sales_amount BIGINT NOT NULL DEFAULT 0 AFTER line_title',
            'qty' => 'ADD qty INT NOT NULL DEFAULT 0 AFTER sales_amount',
            'target_qty' => 'ADD target_qty INT NOT NULL DEFAULT 0 AFTER qty',
            'target_amount' => 'ADD target_amount BIGINT NOT NULL DEFAULT 0 AFTER target_qty',
            'supervisor_name' => 'ADD supervisor_name VARCHAR(150) NULL AFTER target_amount',
            'sales_manager_name' => 'ADD sales_manager_name VARCHAR(150) NULL AFTER supervisor_name',
            'supervisor_user_id' => 'ADD supervisor_user_id INT UNSIGNED NULL AFTER sales_manager_name',
            'sales_manager_user_id' => 'ADD sales_manager_user_id INT UNSIGNED NULL AFTER supervisor_user_id',
            'sort_order' => 'ADD sort_order INT NOT NULL DEFAULT 0 AFTER sales_manager_user_id',
            'active' => 'ADD active TINYINT(1) NOT NULL DEFAULT 1 AFTER sort_order',
            'created_at' => 'ADD created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP AFTER active',
            'updated_at' => 'ADD updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at',
        ];
        foreach ($ceoLineColumns as $column => $alter) {
            if (self::tableExists('ceo_dashboard_lines') && !self::columnExists('ceo_dashboard_lines', $column)) {
                $pdo->exec("ALTER TABLE ceo_dashboard_lines {$alter}");
            }
        }
        $ceoVisitorColumns = [
            'report_date' => 'ADD report_date DATE NULL AFTER id',
            'line_code' => 'ADD line_code VARCHAR(10) NOT NULL DEFAULT "" AFTER report_date',
            'visitor_name' => 'ADD visitor_name VARCHAR(150) NOT NULL DEFAULT "" AFTER line_code',
            'target_qty' => 'ADD target_qty INT NOT NULL DEFAULT 0 AFTER visitor_name',
            'qty' => 'ADD qty INT NOT NULL DEFAULT 0 AFTER target_qty',
            'target_amount' => 'ADD target_amount BIGINT NOT NULL DEFAULT 0 AFTER qty',
            'sales_amount' => 'ADD sales_amount BIGINT NOT NULL DEFAULT 0 AFTER target_amount',
            'user_id' => 'ADD user_id INT UNSIGNED NULL AFTER sales_amount',
            'sort_order' => 'ADD sort_order INT NOT NULL DEFAULT 0 AFTER user_id',
            'active' => 'ADD active TINYINT(1) NOT NULL DEFAULT 1 AFTER sort_order',
            'created_at' => 'ADD created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP AFTER active',
            'updated_at' => 'ADD updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at',
        ];
        foreach ($ceoVisitorColumns as $column => $alter) {
            if (self::tableExists('ceo_dashboard_visitors') && !self::columnExists('ceo_dashboard_visitors', $column)) {
                $pdo->exec("ALTER TABLE ceo_dashboard_visitors {$alter}");
            }
        }
        $ceoPeriodColumns = [
            'title' => 'ADD title VARCHAR(150) NOT NULL DEFAULT "" AFTER id',
            'from_date' => 'ADD from_date DATE NULL AFTER title',
            'to_date' => 'ADD to_date DATE NULL AFTER from_date',
            'active' => 'ADD active TINYINT(1) NOT NULL DEFAULT 1 AFTER to_date',
            'created_at' => 'ADD created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP AFTER active',
            'updated_at' => 'ADD updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at',
        ];
        foreach ($ceoPeriodColumns as $column => $alter) {
            if (self::tableExists('ceo_dashboard_periods') && !self::columnExists('ceo_dashboard_periods', $column)) {
                $pdo->exec("ALTER TABLE ceo_dashboard_periods {$alter}");
            }
        }
        $pharmacyColumns = [
            'title' => 'ADD title VARCHAR(150) NOT NULL DEFAULT "" AFTER id',
            'slug' => 'ADD slug VARCHAR(100) NOT NULL DEFAULT "" AFTER title',
            'sort_order' => 'ADD sort_order INT NOT NULL DEFAULT 0 AFTER slug',
            'active' => 'ADD active TINYINT(1) NOT NULL DEFAULT 1 AFTER sort_order',
            'created_at' => 'ADD created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP AFTER active',
            'updated_at' => 'ADD updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at',
        ];
        foreach ($pharmacyColumns as $column => $alter) {
            if (self::tableExists('pharmacies') && !self::columnExists('pharmacies', $column)) {
                $pdo->exec("ALTER TABLE pharmacies {$alter}");
            }
        }
        $pharmacyMetricColumns = [
            'pharmacy_id' => 'ADD pharmacy_id INT UNSIGNED NOT NULL DEFAULT 0 AFTER id',
            'report_date' => 'ADD report_date DATE NULL AFTER pharmacy_id',
            'daily_sales' => 'ADD daily_sales BIGINT NOT NULL DEFAULT 0 AFTER report_date',
            'monthly_sales' => 'ADD monthly_sales BIGINT NOT NULL DEFAULT 0 AFTER daily_sales',
            'supplier_purchase_amount' => 'ADD supplier_purchase_amount BIGINT NOT NULL DEFAULT 0 AFTER monthly_sales',
            'supplier_sales_amount' => 'ADD supplier_sales_amount BIGINT NOT NULL DEFAULT 0 AFTER supplier_purchase_amount',
            'open_invoice_amount' => 'ADD open_invoice_amount BIGINT NOT NULL DEFAULT 0 AFTER supplier_sales_amount',
            'expenses_amount' => 'ADD expenses_amount BIGINT NOT NULL DEFAULT 0 AFTER open_invoice_amount',
            'pending_checks_amount' => 'ADD pending_checks_amount BIGINT NOT NULL DEFAULT 0 AFTER expenses_amount',
            'sort_order' => 'ADD sort_order INT NOT NULL DEFAULT 0 AFTER pending_checks_amount',
            'active' => 'ADD active TINYINT(1) NOT NULL DEFAULT 1 AFTER sort_order',
            'created_at' => 'ADD created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP AFTER active',
            'updated_at' => 'ADD updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at',
        ];
        foreach ($pharmacyMetricColumns as $column => $alter) {
            if (self::tableExists('pharmacy_dashboard_metrics') && !self::columnExists('pharmacy_dashboard_metrics', $column)) {
                $pdo->exec("ALTER TABLE pharmacy_dashboard_metrics {$alter}");
            }
        }
        $modules = [
            ['dashboard', 'داشبورد', 10],
            ['users', 'کاربران', 20],
            ['kpis', 'شاخص‌ها', 30],
            ['surveys', 'نظرسنجی‌ها', 40],
            ['survey_results', 'نتایج ارزیابی', 50],
            ['files', 'فایل‌ها', 60],
            ['accounting', 'حسابداری', 65],
            ['ceo_dashboard', 'داشبورد مدیرعامل', 68],
            ['view_ceo_dashboard', 'مشاهده داشبورد مدیرعامل', 681],
            ['manager_dashboard.view', 'مشاهده پنل مدیران فروش', 691],
            ['manager_dashboard.edit', 'ویرایش پنل مدیران فروش', 692],
            ['manager_dashboard.import', 'ورود اکسل پنل مدیران فروش', 693],
            ['manager_dashboard.export', 'خروجی اکسل پنل مدیران فروش', 694],
            ['manager_dashboard.settings', 'تنظیمات پنل مدیران فروش', 695],
            ['manager_dashboard.ai', 'بینش هوشمند پنل مدیران فروش', 696],
            ['manager_dashboard.image_export', 'خروجی تصویری داشبورد مدیران', 697],
            ['manager_dashboard.ai_settings', 'تنظیمات هوش مصنوعی داشبورد مدیران', 698],
            ['manager_dashboard.ai_run', 'اجرای تحلیل هوش مصنوعی داشبورد مدیران', 699],
            ['hr_kpi.view', 'مشاهده داشبورد KPI منابع انسانی', 700],
            ['hr_kpi.manage', 'مدیریت قالب‌ها و دوره‌های KPI', 701],
            ['hr_kpi.score', 'ثبت امتیاز KPI پرسنل', 702],
            ['hr_kpi.results', 'مشاهده نتایج KPI', 703],
            ['hr_assessments.manage', 'مدیریت آزمون‌های سازمانی', 704],
            ['hr_assessments.results', 'مشاهده نتایج آزمون‌های سازمانی', 705],
            ['hr_assessments.recalculate', 'محاسبه مجدد نتیجه آزمون', 706],
            ['hr_tests.own', 'مشاهده و انجام آزمون‌های خود', 707],
            ['ai_insights', 'مدیریت منابع گزارشی AI', 708],
            ['system_maintenance', 'بروزرسانی SQL و Seed', 709],
            ['ai_updates', 'اجرای بروزرسانی هوش مصنوعی', 710],
            ['view_sobhan_api_settings', 'مشاهده تنظیمات API سبحان', 682],
            ['manage_sobhan_api_settings', 'مدیریت تنظیمات API سبحان', 683],
            ['use_ai_assistant', 'استفاده از دستیار هوش مصنوعی', 684],
            ['view_ai_chat', 'مشاهده گفتگوی هوش مصنوعی', 685],
            ['manage_ai_chat_settings', 'مدیریت تنظیمات گفتگوی هوش مصنوعی', 686],
            ['manage_knowledge', 'مدیریت منابع دانش هوش مصنوعی', 6865],
            ['view_data_source_settings', 'مشاهده تنظیمات منبع داده', 687],
            ['manage_data_source_settings', 'مدیریت تنظیمات منبع داده', 688],
            ['toggle_ai_autofill', 'فعال‌سازی تکمیل خودکار هوش مصنوعی', 689],
            ['allow_ai_overwrite_manual_data', 'اجازه بازنویسی داده دستی با هوش مصنوعی', 690],
            ['pharmacy_settings', 'تنظیمات داروخانه', 69],
            ['carousel', 'اسلایدر صفحه اصلی', 70],
            ['settings', 'تنظیمات سایت', 80],
        ];
        $stmt = $pdo->prepare('INSERT IGNORE INTO modules (module_key,module_title,sort_order,status,created_at) VALUES (?,?,?,"active",NOW())');
        foreach ($modules as $module) {
            $stmt->execute($module);
        }

        $stmt = $pdo->prepare('INSERT IGNORE INTO accounting_roles (title,sort_order,status,created_at,updated_at) VALUES (?,?,"active",NOW(),NOW())');
        foreach (['موزع', 'تحصیلدار', 'ویزیتور'] as $index => $title) {
            $stmt->execute([$title, ($index + 1) * 10]);
        }

        $stmt = $pdo->prepare('INSERT IGNORE INTO accounting_cities (title,sort_order,status,created_at,updated_at) VALUES (?,?,"active",NOW(),NOW())');
        foreach (['تهران'] as $index => $title) {
            $stmt->execute([$title, ($index + 1) * 10]);
        }

        $stmt = $pdo->prepare('INSERT IGNORE INTO pharmacies (title,slug,sort_order,active,created_at,updated_at) VALUES (?,?,?,1,NOW(),NOW())');
        foreach ([['داروخانه سبحان', 'sobhan', 10], ['داروخانه سنجری', 'sanjari', 20], ['داروخانه اعلایی', 'alaei', 30]] as $pharmacy) {
            $stmt->execute($pharmacy);
        }

        if (self::tableExists('site_settings')) {
            $settings = [
                ['hero_subtitle', 'سامانه هلدینگ سبحان و بخش های وابسته.', 'textarea'],
                ['pwa_name', 'شرکت پخش سبحان', 'text'],
                ['pwa_short_name', 'سبحان', 'text'],
                ['pwa_description', 'سامانه هلدینگ سبحان', 'textarea'],
                ['pwa_theme_color', '#004647', 'color'],
                ['pwa_background_color', '#ffffff', 'color'],
                ['pwa_start_url', '/', 'text'],
                ['pwa_display', 'standalone', 'select'],
                ['pwa_orientation', 'portrait', 'select'],
                ['pwa_icon_192', '', 'image'],
                ['pwa_icon_512', '', 'image'],
                ['pwa_favicon', '', 'image'],
                ['ceo_dashboard_page_title', 'داشبورد مدیرعامل', 'text'],
                ['ceo_dashboard_gross_sales_title', 'فروش ناخالص', 'text'],
                ['ceo_dashboard_discounts_title', 'تخفیفات', 'text'],
                ['ceo_dashboard_discount_percent_title', 'درصد', 'text'],
                ['ceo_dashboard_net_sales_title', 'فروش خالص', 'text'],
                ['ceo_dashboard_line_sales_chart_title', 'ریال فروش لاین', 'text'],
                ['ceo_dashboard_line_table_title', 'اطلاعات لاین', 'text'],
                ['ceo_dashboard_visitor_table_title', 'اطلاعات ویزیتورها', 'text'],
                ['ceo_dashboard_line_share_chart_title', 'سهم فروش هر لاین', 'text'],
                ['ceo_dashboard_line_achievement_chart_title', 'درصد تحقق لاین', 'text'],
                ['ceo_dashboard_visitor_achievement_chart_title', 'درصد تحقق ویزیتور', 'text'],
                ['ceo_dashboard_discounts_amount', '0', 'number'],
                ['ceo_dashboard_show_charts', '1', 'boolean'],
                ['ceo_dashboard_show_line_table', '1', 'boolean'],
                ['ceo_dashboard_show_visitor_table', '1', 'boolean'],
                ['sobhan_api_base_url', 'http://178.131.83.26:18000', 'text'],
                ['sobhan_api_key', '', 'password'],
                ['sobhan_api_timeout', '10', 'number'],
                ['sobhan_api_enabled', '0', 'boolean'],
                ['sobhan_windows_api_url', '', 'text'],
                ['sobhan_reporting_api_url', '', 'text'],
                ['sobhan_ai_model_api_url', '', 'text'],
                ['sobhan_windows_api_enabled', '0', 'boolean'],
                ['sobhan_reporting_api_enabled', '0', 'boolean'],
                ['sobhan_ai_model_api_enabled', '0', 'boolean'],
                ['sobhan_api_retry_count', '1', 'number'],
                ['sobhan_ai_model', '', 'text'],
                ['sobhan_distribution_data_mode', 'import_file', 'select'],
                ['sobhan_ai_autofill_enabled', '0', 'boolean'],
                ['sobhan_ai_overwrite_manual_data', '0', 'boolean'],
                ['sobhan_static_pharmacy_mode', '1', 'boolean'],
                ['knowledge_upload_max_mb', '10', 'number'],
            ];
            $stmt = $pdo->prepare('INSERT IGNORE INTO site_settings (setting_key,setting_value,setting_type,updated_at) VALUES (?,?,?,NOW())');
            foreach ($settings as $setting) {
                $stmt->execute($setting);
            }
            $legacyMap = [
                'page_title' => 'ceo_dashboard_page_title',
                'gross_sales_title' => 'ceo_dashboard_gross_sales_title',
                'discounts_title' => 'ceo_dashboard_discounts_title',
                'discount_percent_title' => 'ceo_dashboard_discount_percent_title',
                'net_sales_title' => 'ceo_dashboard_net_sales_title',
                'line_sales_chart_title' => 'ceo_dashboard_line_sales_chart_title',
                'line_table_title' => 'ceo_dashboard_line_table_title',
                'visitor_table_title' => 'ceo_dashboard_visitor_table_title',
                'line_share_chart_title' => 'ceo_dashboard_line_share_chart_title',
                'line_achievement_chart_title' => 'ceo_dashboard_line_achievement_chart_title',
                'visitor_achievement_chart_title' => 'ceo_dashboard_visitor_achievement_chart_title',
            ];
            $copyStmt = $pdo->prepare(
                'UPDATE site_settings target
                 JOIN site_settings legacy ON legacy.setting_key = ?
                 SET target.setting_value = legacy.setting_value
                 WHERE target.setting_key = ? AND (target.setting_value IS NULL OR target.setting_value = "")'
            );
            foreach ($legacyMap as $legacyKey => $newKey) {
                $copyStmt->execute([$legacyKey, $newKey]);
            }
        }
    }

    public static function fetch(string $sql, array $params = []): ?array
    {
        $stmt = self::connection()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function fetchAll(string $sql, array $params = []): array
    {
        $stmt = self::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function execute(string $sql, array $params = []): bool
    {
        $stmt = self::connection()->prepare($sql);
        return $stmt->execute($params);
    }

    public static function lastInsertId(): string
    {
        return self::connection()->lastInsertId();
    }
}
