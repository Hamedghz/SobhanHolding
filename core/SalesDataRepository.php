<?php
require_once __DIR__ . '/Database.php';

class SalesDataRepository
{
    public static function batchSummary(): array
    {
        return Database::fetch(
            'SELECT COUNT(*) total_batches,
                    COALESCE(SUM(status="completed"),0) completed_batches,
                    COALESCE(SUM(status="failed"),0) failed_batches
             FROM sales_import_batches'
        ) ?: ['total_batches' => 0, 'completed_batches' => 0, 'failed_batches' => 0];
    }

    public static function recentBatches(int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        return Database::fetchAll(
            "SELECT id,source_type,source_module,file_name,status,total_rows,valid_rows,invalid_rows,
                    imported_rows,updated_rows,skipped_rows,started_at,finished_at,created_at
             FROM sales_import_batches ORDER BY id DESC LIMIT {$limit}"
        );
    }

    public static function recentErrors(int $limit = 100, int $batchId = 0): array
    {
        $limit = max(1, min(500, $limit));
        if ($batchId > 0) {
            return Database::fetchAll(
                "SELECT e.id,e.import_batch_id,e.source_module,e.`row_number`,e.error_code,e.error_message,e.created_at
                 FROM sales_import_errors e WHERE e.import_batch_id=? ORDER BY e.id DESC LIMIT {$limit}",
                [$batchId]
            );
        }
        return Database::fetchAll(
            "SELECT e.id,e.import_batch_id,e.source_module,e.`row_number`,e.error_code,e.error_message,e.created_at
             FROM sales_import_errors e ORDER BY e.id DESC LIMIT {$limit}"
        );
    }

    public static function mappings(): array
    {
        return Database::fetchAll(
            'SELECT id,source_module,source_header,normalized_key,required,data_type,active,created_at,updated_at
             FROM sales_import_column_mappings ORDER BY source_module,source_header,id'
        );
    }
}
