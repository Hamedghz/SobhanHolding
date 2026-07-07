<?php
require_once __DIR__ . '/Database.php';

class SalesOfferBudgetModule
{
    public static function repair(?PDO $pdo = null): void
    {
        $pdo = $pdo ?: Database::connection();
        foreach (self::schema() as $sql) $pdo->exec($sql);
        self::repairColumns($pdo);
        $stmt = $pdo->prepare('INSERT INTO modules(module_key,module_title,sort_order,status,created_at) VALUES (?,?,?,"active",NOW()) ON DUPLICATE KEY UPDATE module_title=VALUES(module_title),status="active"');
        foreach ([['sales_manager.offer_budget.manage','استعلام بودجه آفر',770],['sales_offer_budget.settings','تنظیمات فرمول بودجه آفر',771]] as $module) $stmt->execute($module);
        $settings = json_encode(['default_offer_rate'=>0], JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
        $pdo->prepare('INSERT INTO sales_offer_formula_settings(formula_key,title,formula_version,settings_json,active,created_at,updated_at) VALUES (?,?,?,?,1,NOW(),NOW()) ON DUPLICATE KEY UPDATE title=VALUES(title),formula_version=VALUES(formula_version),active=1,updated_at=NOW()')
            ->execute(['offer_budget_provisional','فرمول موقت بودجه آفر','provisional_v1',$settings]);
    }

    public static function schema(): array
    {
        $engine=' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        return [
            "CREATE TABLE IF NOT EXISTS sales_offer_budget_requests (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,request_code VARCHAR(50) NOT NULL,requested_by BIGINT UNSIGNED NOT NULL,sales_manager_id BIGINT UNSIGNED NULL,sales_line VARCHAR(100) NULL,period_key VARCHAR(50) NULL,date_from DATE NULL,date_to DATE NULL,product_code VARCHAR(100) NULL,product_name VARCHAR(255) NULL,brand_name VARCHAR(255) NULL,supplier_name VARCHAR(255) NULL,purchase_price DECIMAL(18,2) NOT NULL DEFAULT 0,requested_offer_qty DECIMAL(18,3) NOT NULL DEFAULT 0,sold_qty DECIMAL(18,3) NOT NULL DEFAULT 0,sold_amount DECIMAL(18,2) NOT NULL DEFAULT 0,provisional_offer_rate DECIMAL(10,4) NOT NULL DEFAULT 0,provisional_budget DECIMAL(18,2) NOT NULL DEFAULT 0,formula_version VARCHAR(50) NOT NULL DEFAULT 'provisional_v1',formula_snapshot_json JSON NULL,status VARCHAR(30) NOT NULL DEFAULT 'draft',manager_note TEXT NULL,admin_note TEXT NULL,reviewed_by BIGINT UNSIGNED NULL,reviewed_at DATETIME NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NULL,UNIQUE KEY uq_offer_budget_code(request_code),INDEX idx_offer_budget_status(status),INDEX idx_offer_budget_manager(sales_manager_id),INDEX idx_offer_budget_product(product_code),INDEX idx_offer_budget_period(period_key),INDEX idx_offer_budget_dates(date_from,date_to)){$engine}",
            "CREATE TABLE IF NOT EXISTS sales_offer_budget_logs (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,request_id BIGINT UNSIGNED NOT NULL,action VARCHAR(50) NOT NULL,performed_by BIGINT UNSIGNED NULL,old_value_json JSON NULL,new_value_json JSON NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,INDEX idx_offer_budget_log_request(request_id,created_at)){$engine}",
            "CREATE TABLE IF NOT EXISTS sales_offer_formula_settings (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,formula_key VARCHAR(100) NOT NULL UNIQUE,title VARCHAR(255) NOT NULL,formula_version VARCHAR(50) NOT NULL,settings_json JSON NULL,active TINYINT(1) NOT NULL DEFAULT 1,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NULL){$engine}"
        ];
    }

    private static function repairColumns(PDO $pdo): void
    {
        $columns=['sales_offer_budget_requests'=>['formula_version'=>"VARCHAR(50) NOT NULL DEFAULT 'provisional_v1'",'formula_snapshot_json'=>'JSON NULL','admin_note'=>'TEXT NULL','reviewed_by'=>'BIGINT UNSIGNED NULL','reviewed_at'=>'DATETIME NULL','updated_at'=>'DATETIME NULL'],'sales_offer_budget_logs'=>['old_value_json'=>'JSON NULL','new_value_json'=>'JSON NULL'],'sales_offer_formula_settings'=>['settings_json'=>'JSON NULL','active'=>'TINYINT(1) NOT NULL DEFAULT 1','updated_at'=>'DATETIME NULL']];
        $check=$pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
        foreach($columns as $table=>$defs)foreach($defs as $column=>$definition){$check->execute([$table,$column]);if(!(int)$check->fetchColumn())$pdo->exec("ALTER TABLE `{$table}` ADD `{$column}` {$definition}");}
    }
}
