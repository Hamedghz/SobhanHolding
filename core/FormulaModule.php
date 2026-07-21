<?php

final class FormulaModule
{
    public const PERMISSIONS = [
        'formula_builder.view' => ['مشاهده فرمول‌ساز تصویری', 768],
        'formula_builder.manage' => ['مدیریت پیش‌نویس فرمول‌ها', 769],
        'formula_builder.publish' => ['انتشار نسخه فرمول‌ها', 770],
        'formula_builder.test' => ['آزمون فرمول‌ها', 771],
        'formula_builder.rollback' => ['بازگردانی نسخه فرمول‌ها', 772],
    ];

    public static function repair(PDO $pdo): void
    {
        $engine = ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        foreach ([
            "CREATE TABLE IF NOT EXISTS formula_definitions (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                formula_key VARCHAR(100) NOT NULL,
                title VARCHAR(190) NOT NULL,
                category_key VARCHAR(60) NOT NULL,
                description TEXT NULL,
                owner_scope VARCHAR(60) NULL,
                active TINYINT(1) NOT NULL DEFAULT 1,
                created_by INT UNSIGNED NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_formula_definition_key(formula_key),
                INDEX idx_formula_definition_category(category_key,active),
                CONSTRAINT fk_formula_definition_creator FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL
            ){$engine}",
            "CREATE TABLE IF NOT EXISTS formula_versions (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                definition_id BIGINT UNSIGNED NOT NULL,
                version_no INT UNSIGNED NOT NULL,
                status VARCHAR(30) NOT NULL DEFAULT 'draft',
                effective_from DATE NULL,
                effective_to DATE NULL,
                data_source_key VARCHAR(100) NOT NULL,
                metric_key VARCHAR(100) NOT NULL,
                comparison_metric_key VARCHAR(100) NULL,
                aggregation_key VARCHAR(30) NOT NULL,
                operator_key VARCHAR(30) NOT NULL,
                condition_value_json LONGTEXT NULL,
                result_type VARCHAR(40) NOT NULL,
                result_value DECIMAL(20,6) NOT NULL DEFAULT 0,
                priority INT NOT NULL DEFAULT 100,
                user_note TEXT NULL,
                rule_json LONGTEXT NOT NULL,
                created_by INT UNSIGNED NULL,
                published_by INT UNSIGNED NULL,
                published_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_formula_definition_version(definition_id,version_no),
                INDEX idx_formula_version_status(status,effective_from,effective_to),
                INDEX idx_formula_version_source(data_source_key,metric_key,priority),
                CONSTRAINT fk_formula_version_definition FOREIGN KEY(definition_id) REFERENCES formula_definitions(id) ON DELETE RESTRICT,
                CONSTRAINT fk_formula_version_creator FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
                CONSTRAINT fk_formula_version_publisher FOREIGN KEY(published_by) REFERENCES users(id) ON DELETE SET NULL
            ){$engine}",
            "CREATE TABLE IF NOT EXISTS formula_filters (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                formula_version_id BIGINT UNSIGNED NOT NULL,
                field_key VARCHAR(100) NOT NULL,
                operator_key VARCHAR(30) NOT NULL,
                value_json LONGTEXT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_formula_filter_version(formula_version_id,sort_order),
                CONSTRAINT fk_formula_filter_version FOREIGN KEY(formula_version_id) REFERENCES formula_versions(id) ON DELETE CASCADE
            ){$engine}",
            "CREATE TABLE IF NOT EXISTS formula_dependencies (
                formula_version_id BIGINT UNSIGNED NOT NULL,
                depends_on_definition_id BIGINT UNSIGNED NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY(formula_version_id,depends_on_definition_id),
                INDEX idx_formula_dependency_target(depends_on_definition_id),
                CONSTRAINT fk_formula_dependency_version FOREIGN KEY(formula_version_id) REFERENCES formula_versions(id) ON DELETE CASCADE,
                CONSTRAINT fk_formula_dependency_definition FOREIGN KEY(depends_on_definition_id) REFERENCES formula_definitions(id) ON DELETE RESTRICT
            ){$engine}",
            "CREATE TABLE IF NOT EXISTS formula_audit_logs (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                definition_id BIGINT UNSIGNED NULL,
                formula_version_id BIGINT UNSIGNED NULL,
                actor_user_id INT UNSIGNED NULL,
                action VARCHAR(60) NOT NULL,
                old_value_json LONGTEXT NULL,
                new_value_json LONGTEXT NULL,
                note VARCHAR(500) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_formula_audit_definition(definition_id,created_at),
                INDEX idx_formula_audit_version(formula_version_id,created_at),
                INDEX idx_formula_audit_actor(actor_user_id,created_at),
                CONSTRAINT fk_formula_audit_definition FOREIGN KEY(definition_id) REFERENCES formula_definitions(id) ON DELETE SET NULL,
                CONSTRAINT fk_formula_audit_version FOREIGN KEY(formula_version_id) REFERENCES formula_versions(id) ON DELETE SET NULL,
                CONSTRAINT fk_formula_audit_actor FOREIGN KEY(actor_user_id) REFERENCES users(id) ON DELETE SET NULL
            ){$engine}",
            "CREATE TABLE IF NOT EXISTS formula_test_runs (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                formula_version_id BIGINT UNSIGNED NOT NULL,
                actor_user_id INT UNSIGNED NULL,
                context_json LONGTEXT NULL,
                input_values_json LONGTEXT NULL,
                trace_json LONGTEXT NOT NULL,
                matched TINYINT(1) NOT NULL DEFAULT 0,
                final_result DECIMAL(20,6) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_formula_test_version(formula_version_id,created_at),
                INDEX idx_formula_test_actor(actor_user_id,created_at),
                CONSTRAINT fk_formula_test_version FOREIGN KEY(formula_version_id) REFERENCES formula_versions(id) ON DELETE RESTRICT,
                CONSTRAINT fk_formula_test_actor FOREIGN KEY(actor_user_id) REFERENCES users(id) ON DELETE SET NULL
            ){$engine}",
        ] as $statement) {
            $pdo->exec($statement);
        }

        if (self::tableExists($pdo, 'modules')) {
            $statement = $pdo->prepare(
                'INSERT INTO modules(module_key,module_title,sort_order,status,created_at)
                 VALUES (?,?,?,"active",NOW())
                 ON DUPLICATE KEY UPDATE module_title=VALUES(module_title),sort_order=VALUES(sort_order),status="active"'
            );
            foreach (self::PERMISSIONS as $key => [$title, $sortOrder]) {
                $statement->execute([$key, $title, $sortOrder]);
            }
        }
        self::seedLegacyOfferDraft($pdo);
        self::seedAdapterDrafts($pdo);
    }

    private static function tableExists(PDO $pdo, string $table): bool
    {
        $statement = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?'
        );
        $statement->execute([$table]);
        return (int)$statement->fetchColumn() > 0;
    }

    private static function seedLegacyOfferDraft(PDO $pdo): void
    {
        if (!self::tableExists($pdo, 'sales_offer_formula_settings')) return;
        $exists = $pdo->query("SELECT id FROM formula_definitions WHERE formula_key='offer_budget_provisional' LIMIT 1")->fetchColumn();
        if ($exists !== false) return;
        $legacy = $pdo->query(
            "SELECT settings_json FROM sales_offer_formula_settings
             WHERE formula_key='offer_budget_provisional' LIMIT 1"
        )->fetch(PDO::FETCH_ASSOC);
        $settings = json_decode((string)($legacy['settings_json'] ?? ''), true) ?: [];
        $ratePercent = max(0, (float)($settings['default_offer_rate'] ?? 0) * 100);
        $pdo->exec(
            "INSERT INTO formula_definitions
             (formula_key,title,category_key,description,owner_scope,active,created_at,updated_at)
             VALUES ('offer_budget_provisional','فرمول مقدماتی بودجه آفر','offer_budget',
                     'نسخه مهاجرت‌یافته از تنظیم قدیمی؛ تا زمان انتشار اثری روی محاسبات ندارد.',
                     'sales',1,NOW(),NOW())"
        );
        $definitionId = (int)$pdo->lastInsertId();
        $rule = json_encode([
            'data_source_key' => 'sample_input',
            'metric_key' => 'purchase_base',
            'comparison_metric_key' => null,
            'aggregation_key' => 'SUM',
            'filters' => [],
            'condition' => ['operator' => '>=', 'values' => [0]],
            'result' => ['type' => 'percent_of_metric', 'value' => $ratePercent],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
        $statement = $pdo->prepare(
            'INSERT INTO formula_versions
             (definition_id,version_no,status,data_source_key,metric_key,aggregation_key,operator_key,
              condition_value_json,result_type,result_value,priority,user_note,rule_json,created_at,updated_at)
             VALUES (?,1,"draft","sample_input","purchase_base","SUM",">=","[0]",
                     "percent_of_metric",?,100,"مهاجرت خودکار از تنظیم قدیمی",?,NOW(),NOW())'
        );
        $statement->execute([$definitionId, $ratePercent, $rule]);
    }

    private static function seedAdapterDrafts(PDO $pdo): void
    {
        $templates = [
            ['manager_penalty', 'ضریب کاهنده مدیر فروش', 'penalty', 'penalty_percent', 0],
            ['manager_commission', 'پورسانت نهایی مدیر فروش', 'commission', 'final_commission', 0],
            ['manager_customer_coverage', 'پاداش یا جریمه پوشش مشتری', 'customer_coverage', 'reward_or_penalty', -999999999],
            ['manager_brand_bonus', 'پاداش تحقق برند', 'brand_bonus', 'bonus_total', 0],
        ];
        $definitionStatement = $pdo->prepare(
            'INSERT INTO formula_definitions
             (formula_key,title,category_key,description,owner_scope,active,created_at,updated_at)
             VALUES (?,?,?,"قالب Draft سازگار با محاسبه قدیمی؛ پیش از انتشار قابل ویرایش و آزمون است.","sales",1,NOW(),NOW())'
        );
        $versionStatement = $pdo->prepare(
            'INSERT INTO formula_versions
             (definition_id,version_no,status,data_source_key,metric_key,aggregation_key,operator_key,
              condition_value_json,result_type,result_value,priority,user_note,rule_json,created_at,updated_at)
             VALUES (?,1,"draft","sample_input",?,"SUM",">=",?,"metric",0,100,
                     "قالب سازگار؛ انتشار بدون تغییر، خروجی قدیمی را حفظ می‌کند.",?,NOW(),NOW())'
        );
        $existsStatement = $pdo->prepare('SELECT id FROM formula_definitions WHERE formula_key=? LIMIT 1');
        foreach ($templates as [$formulaKey, $title, $category, $metric, $condition]) {
            $existsStatement->execute([$formulaKey]);
            if ($existsStatement->fetchColumn() !== false) continue;
            $definitionStatement->execute([$formulaKey, $title, $category]);
            $definitionId = (int)$pdo->lastInsertId();
            $conditionJson = json_encode([$condition], JSON_PRESERVE_ZERO_FRACTION);
            $rule = json_encode([
                'data_source_key' => 'sample_input',
                'metric_key' => $metric,
                'comparison_metric_key' => null,
                'aggregation_key' => 'SUM',
                'filters' => [],
                'condition' => ['operator' => '>=', 'values' => [$condition]],
                'result' => ['type' => 'metric', 'value' => 0],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
            $versionStatement->execute([$definitionId, $metric, $conditionJson, $rule]);
        }
    }
}
