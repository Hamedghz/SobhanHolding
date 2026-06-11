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
        ];
        foreach ($statements as $statement) {
            $pdo->exec($statement);
        }

        if (self::tableExists('users')) {
            if (!self::columnExists('users', 'description')) {
                $pdo->exec('ALTER TABLE users ADD description TEXT NULL AFTER status');
            }
            if (!self::columnExists('users', 'upload_quota_mb')) {
                $pdo->exec('ALTER TABLE users ADD upload_quota_mb INT NULL DEFAULT NULL AFTER description');
            }
            $pdo->exec("ALTER TABLE users MODIFY role ENUM('admin','manager','employee') NOT NULL DEFAULT 'employee'");
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
            'sort_order' => 'ADD sort_order INT NOT NULL DEFAULT 0 AFTER target_qty',
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
            'sort_order' => 'ADD sort_order INT NOT NULL DEFAULT 0 AFTER qty',
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

        $modules = [
            ['dashboard', 'داشبورد', 10],
            ['users', 'کاربران', 20],
            ['kpis', 'شاخص‌ها', 30],
            ['surveys', 'نظرسنجی‌ها', 40],
            ['survey_results', 'نتایج ارزیابی', 50],
            ['files', 'فایل‌ها', 60],
            ['accounting', 'حسابداری', 65],
            ['ceo_dashboard', 'داشبورد مدیرعامل', 68],
            ['carousel', 'اسلایدر صفحه اصلی', 70],
            ['settings', 'تنظیمات سایت', 80],
        ];
        $stmt = $pdo->prepare('INSERT INTO modules (module_key,module_title,sort_order,status,created_at) VALUES (?,?,?,"active",NOW()) ON DUPLICATE KEY UPDATE module_title=VALUES(module_title), sort_order=VALUES(sort_order), status=VALUES(status)');
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

        if (self::tableExists('site_settings')) {
            $settings = [
                ['hero_subtitle', 'سامانه هلدینگ سبحان و بخش های وابسته.', 'textarea'],
                ['page_title', 'داشبورد مدیرعامل', 'text'],
                ['gross_sales_title', 'فروش ناخالص', 'text'],
                ['discounts_title', 'تخفیفات', 'text'],
                ['discount_percent_title', 'درصد', 'text'],
                ['net_sales_title', 'فروش خالص', 'text'],
                ['line_sales_chart_title', 'ریال فروش لاین', 'text'],
                ['line_table_title', 'اطلاعات لاین', 'text'],
                ['visitor_table_title', 'اطلاعات ویزیتورها', 'text'],
                ['line_share_chart_title', 'سهم فروش هر لاین', 'text'],
                ['line_achievement_chart_title', 'درصد تحقق لاین', 'text'],
                ['visitor_achievement_chart_title', 'درصد تحقق ویزیتور', 'text'],
                ['ceo_dashboard_discounts_amount', '0', 'number'],
            ];
            $stmt = $pdo->prepare('INSERT INTO site_settings (setting_key,setting_value,setting_type,updated_at) VALUES (?,?,?,NOW()) ON DUPLICATE KEY UPDATE setting_type=VALUES(setting_type)');
            foreach ($settings as $setting) {
                $stmt->execute($setting);
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
