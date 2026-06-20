<?php
require_once __DIR__ . '/Database.php';

class SystemMaintenance
{
    public static function repair(PDO $pdo): array
    {
        $before = [];
        foreach (self::tables() as $table => $sql) {
            $before[$table] = Database::tableExists($table);
            $pdo->exec($sql);
        }

        $columnsAdded = 0;
        foreach (['deleted_at' => 'DATETIME NULL', 'deleted_by' => 'INT UNSIGNED NULL'] as $column => $definition) {
            if (Database::tableExists('accounting_collections') && !Database::columnExists('accounting_collections', $column)) {
                $pdo->exec("ALTER TABLE accounting_collections ADD `{$column}` {$definition}");
                $columnsAdded++;
            }
        }
        $stmt=$pdo->prepare('INSERT IGNORE INTO schema_migrations(migration_key,version,status,message,applied_at,created_at) VALUES ("sobhan_maintenance_foundation","1.0.0","completed","ساختار job، cache و seed manager",NOW(),NOW())');$stmt->execute();

        return [
            'tables_created' => count(array_filter($before, static fn($exists) => !$exists)),
            'columns_added' => $columnsAdded,
        ];
    }

    public static function requiredTables(): array
    {
        return array_keys(self::tables());
    }

    private static function tables(): array
    {
        $engine = ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        return [
            'schema_migrations' => "CREATE TABLE IF NOT EXISTS schema_migrations (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,migration_key VARCHAR(150) NOT NULL UNIQUE,version VARCHAR(40) NOT NULL,status VARCHAR(30) NOT NULL DEFAULT 'completed',message TEXT NULL,applied_at DATETIME NULL,created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP){$engine}",
            'seed_runs' => "CREATE TABLE IF NOT EXISTS seed_runs (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,seed_group VARCHAR(100) NOT NULL,mode VARCHAR(30) NOT NULL,status VARCHAR(30) NOT NULL DEFAULT 'pending',requested_by INT UNSIGNED NULL,started_at DATETIME NULL,finished_at DATETIME NULL,inserted_count INT NOT NULL DEFAULT 0,updated_count INT NOT NULL DEFAULT 0,skipped_count INT NOT NULL DEFAULT 0,error_count INT NOT NULL DEFAULT 0,message TEXT NULL,created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,INDEX idx_seed_runs_group(seed_group),INDEX idx_seed_runs_status(status)){$engine}",
            'seed_run_items' => "CREATE TABLE IF NOT EXISTS seed_run_items (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,seed_run_id BIGINT UNSIGNED NOT NULL,seed_key VARCHAR(150) NOT NULL,action VARCHAR(40) NOT NULL,status VARCHAR(30) NOT NULL,table_name VARCHAR(150) NULL,record_key VARCHAR(190) NULL,message TEXT NULL,created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,INDEX idx_seed_items_run(seed_run_id),CONSTRAINT fk_seed_items_run FOREIGN KEY(seed_run_id) REFERENCES seed_runs(id) ON DELETE CASCADE){$engine}",
            'maintenance_logs' => "CREATE TABLE IF NOT EXISTS maintenance_logs (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,action_key VARCHAR(100) NOT NULL,status VARCHAR(30) NOT NULL,requested_by INT UNSIGNED NULL,message TEXT NULL,result_json LONGTEXT NULL,created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,INDEX idx_maintenance_action(action_key)){$engine}",
            'ai_update_jobs' => "CREATE TABLE IF NOT EXISTS ai_update_jobs (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,remote_job_id VARCHAR(190) NULL,job_type VARCHAR(60) NOT NULL,requested_by INT UNSIGNED NULL,status VARCHAR(30) NOT NULL DEFAULT 'pending',progress INT NOT NULL DEFAULT 0,message TEXT NULL,started_at DATETIME NULL,finished_at DATETIME NULL,result_json LONGTEXT NULL,error_message TEXT NULL,created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,INDEX idx_ai_jobs_status(status)){$engine}",
            'dashboard_data_cache' => "CREATE TABLE IF NOT EXISTS dashboard_data_cache (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,dashboard_key VARCHAR(80) NOT NULL,scope_key VARCHAR(150) NOT NULL DEFAULT 'all',source VARCHAR(60) NOT NULL,payload_json LONGTEXT NULL,updated_by INT UNSIGNED NULL,updated_at DATETIME NULL,created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE KEY uq_dashboard_cache(dashboard_key,scope_key)){$engine}",
            'knowledge_index_jobs' => "CREATE TABLE IF NOT EXISTS knowledge_index_jobs (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,remote_job_id VARCHAR(190) NULL,source_type VARCHAR(30) NOT NULL DEFAULT 'all',source_id INT UNSIGNED NULL,status VARCHAR(30) NOT NULL DEFAULT 'pending',progress INT NOT NULL DEFAULT 0,message TEXT NULL,requested_by INT UNSIGNED NULL,started_at DATETIME NULL,finished_at DATETIME NULL,result_json LONGTEXT NULL,error_message TEXT NULL,created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,INDEX idx_knowledge_jobs_status(status)){$engine}",
        ];
    }
}
