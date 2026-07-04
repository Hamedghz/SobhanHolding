<?php

class SyncModule
{
    public static function repair(PDO $pdo): void
    {
        $engine = ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        $pdo->exec("CREATE TABLE IF NOT EXISTS sync_queue (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            entity_type VARCHAR(100) NOT NULL,
            entity_id BIGINT UNSIGNED NOT NULL,
            operation VARCHAR(20) NOT NULL DEFAULT 'upsert',
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            attempts INT UNSIGNED NOT NULL DEFAULT 0,
            last_error TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL,
            synced_at DATETIME NULL,
            locked_at DATETIME NULL,
            locked_by VARCHAR(100) NULL,
            INDEX idx_sync_status(status),
            INDEX idx_sync_entity(entity_type,entity_id),
            INDEX idx_sync_created(created_at),
            INDEX idx_sync_retry(status,attempts,created_at)
        ){$engine}");
        $pdo->exec("CREATE TABLE IF NOT EXISTS sync_api_logs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            endpoint VARCHAR(100) NOT NULL,
            method VARCHAR(10) NOT NULL,
            remote_ip VARCHAR(64) NULL,
            entity_type VARCHAR(100) NULL,
            entity_id BIGINT UNSIGNED NULL,
            queue_id BIGINT UNSIGNED NULL,
            status_code INT NULL,
            success TINYINT(1) NOT NULL DEFAULT 0,
            error_message TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_sync_log_created(created_at),
            INDEX idx_sync_log_queue(queue_id,created_at)
        ){$engine}");

        $columns = [
            'operation' => "VARCHAR(20) NOT NULL DEFAULT 'upsert'",
            'status' => "VARCHAR(20) NOT NULL DEFAULT 'pending'",
            'attempts' => 'INT UNSIGNED NOT NULL DEFAULT 0',
            'last_error' => 'TEXT NULL', 'updated_at' => 'DATETIME NULL', 'synced_at' => 'DATETIME NULL',
            'locked_at' => 'DATETIME NULL', 'locked_by' => 'VARCHAR(100) NULL',
        ];
        foreach ($columns as $name => $definition) {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME="sync_queue" AND COLUMN_NAME=?');
            $stmt->execute([$name]);
            if (!(int)$stmt->fetchColumn()) $pdo->exec("ALTER TABLE sync_queue ADD {$name} {$definition}");
        }
        $indexes = [
            'idx_sync_status' => '(status)', 'idx_sync_entity' => '(entity_type,entity_id)',
            'idx_sync_created' => '(created_at)', 'idx_sync_retry' => '(status,attempts,created_at)',
        ];
        foreach ($indexes as $name => $definition) {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME="sync_queue" AND INDEX_NAME=?');
            $stmt->execute([$name]);
            if (!(int)$stmt->fetchColumn()) $pdo->exec("ALTER TABLE sync_queue ADD INDEX {$name} {$definition}");
        }

        $settings = [
            ['sync_api_enabled','0','boolean'], ['sync_api_key_hash','','password'], ['sync_ip_allowlist','','text'],
            ['sync_batch_default','50','number'], ['sync_batch_max','100','number'], ['sync_max_attempts','5','number'],
            ['sync_allowed_entities','users,reports','text'],
        ];
        $stmt = $pdo->prepare('INSERT IGNORE INTO site_settings(setting_key,setting_value,setting_type,updated_at) VALUES(?,?,?,NOW())');
        foreach ($settings as $setting) $stmt->execute($setting);
    }
}
