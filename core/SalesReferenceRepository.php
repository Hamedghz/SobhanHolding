<?php
require_once __DIR__ . '/Database.php';

class SalesReferenceRepository
{
    public static function mirrorBatch(int $batchId, array $data): void
    {
        Database::execute(
            'INSERT INTO sales_reference_import_batches
             (id,source_module,source_type,original_file_name,stored_file_path,file_hash,detected_sheet,detected_table,detected_range,period_key,import_mode,status,total_rows,valid_rows,invalid_rows,duplicate_rows,inserted_rows,updated_rows,skipped_rows,started_by,started_at,finished_at,error_message,metadata_json,created_at,updated_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())
             ON DUPLICATE KEY UPDATE source_type=VALUES(source_type),original_file_name=VALUES(original_file_name),stored_file_path=VALUES(stored_file_path),file_hash=VALUES(file_hash),detected_sheet=VALUES(detected_sheet),detected_table=VALUES(detected_table),detected_range=VALUES(detected_range),period_key=VALUES(period_key),import_mode=VALUES(import_mode),status=VALUES(status),total_rows=VALUES(total_rows),valid_rows=VALUES(valid_rows),invalid_rows=VALUES(invalid_rows),duplicate_rows=VALUES(duplicate_rows),inserted_rows=VALUES(inserted_rows),updated_rows=VALUES(updated_rows),skipped_rows=VALUES(skipped_rows),finished_at=VALUES(finished_at),error_message=VALUES(error_message),metadata_json=VALUES(metadata_json),updated_at=NOW()',
            [
                $batchId,
                $data['source_module'],
                $data['source_type'] ?? 'excel_upload',
                $data['original_file_name'] ?? $data['file_name'] ?? null,
                $data['stored_file_path'] ?? null,
                $data['file_hash'] ?? null,
                $data['detected_sheet'] ?? null,
                $data['detected_table'] ?? null,
                $data['detected_range'] ?? null,
                $data['period_key'] ?? null,
                $data['import_mode'] ?? 'replace_reference',
                $data['status'] ?? 'uploaded',
                (int)($data['total_rows'] ?? 0),
                (int)($data['valid_rows'] ?? 0),
                (int)($data['invalid_rows'] ?? 0),
                (int)($data['duplicate_rows'] ?? 0),
                (int)($data['inserted_rows'] ?? $data['imported_rows'] ?? 0),
                (int)($data['updated_rows'] ?? 0),
                (int)($data['skipped_rows'] ?? 0),
                $data['started_by'] ?? null,
                $data['started_at'] ?? null,
                $data['finished_at'] ?? null,
                $data['error_message'] ?? null,
                $data['metadata_json'] ?? null,
            ]
        );
        Database::execute(
            'UPDATE sales_reference_import_batches
             SET pipeline_status=?,snapshot_date=?,period_id=?,retry_of_batch_id=?,source_confidence=?,updated_at=NOW()
             WHERE id=?',
            [
                $data['pipeline_status'] ?? self::publicStatus((string)($data['status'] ?? 'uploaded')),
                $data['snapshot_date'] ?? null,
                $data['period_id'] ?? null,
                $data['retry_of_batch_id'] ?? null,
                $data['source_confidence'] ?? null,
                $batchId,
            ]
        );
    }

    public static function mirrorBatchFromLegacy(int $batchId): void
    {
        $batch = Database::fetch('SELECT * FROM sales_import_batches WHERE id=?', [$batchId]);
        if (!$batch) return;
        self::mirrorBatch($batchId, [
            'source_module' => $batch['source_module'],
            'source_type' => $batch['source_type'],
            'original_file_name' => $batch['file_name'] ?? null,
            'stored_file_path' => $batch['stored_file_path'] ?? null,
            'file_hash' => $batch['file_hash'],
            'detected_sheet' => $batch['detected_sheet'],
            'detected_table' => $batch['detected_table'],
            'detected_range' => $batch['detected_range'] ?? null,
            'period_key' => $batch['period_key'] ?? null,
            'pipeline_status' => $batch['pipeline_status'] ?? self::publicStatus((string)$batch['status']),
            'snapshot_date' => $batch['snapshot_date'] ?? null,
            'period_id' => $batch['period_id'] ?? null,
            'retry_of_batch_id' => $batch['retry_of_batch_id'] ?? null,
            'source_confidence' => $batch['source_confidence'] ?? null,
            'import_mode' => $batch['import_mode'],
            'status' => self::publicStatus((string)$batch['status']),
            'total_rows' => $batch['total_rows'],
            'valid_rows' => $batch['valid_rows'],
            'invalid_rows' => $batch['invalid_rows'],
            'duplicate_rows' => $batch['duplicate_rows'],
            'inserted_rows' => $batch['imported_rows'] ?? 0,
            'updated_rows' => $batch['updated_rows'],
            'skipped_rows' => $batch['skipped_rows'],
            'started_by' => $batch['started_by'],
            'started_at' => $batch['started_at'],
            'finished_at' => $batch['finished_at'],
            'error_message' => $batch['error_message'],
            'metadata_json' => $batch['metadata_json'],
        ]);
    }

    public static function mirrorStagingRow(int $legacyId, int $batchId, string $sourceModule, int $rowNumber, array $raw, array $normalized, string $status, array $errors, string $sourceKey, ?int $sourceRowNumber = null): void
    {
        $rawJson = json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $normalizedJson = json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $rowHash = hash('sha256', $sourceModule . '|' . $rowNumber . '|' . $rawJson);
        Database::execute(
            'INSERT INTO staging_sales_reference_rows
             (id,import_batch_id,source_module,`row_number`,source_row_number,source_row_hash,source_unique_key,raw_json,normalized_json,validation_status,validation_errors_json,created_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW())
             ON DUPLICATE KEY UPDATE source_row_number=VALUES(source_row_number),source_row_hash=VALUES(source_row_hash),source_unique_key=VALUES(source_unique_key),raw_json=VALUES(raw_json),normalized_json=VALUES(normalized_json),validation_status=VALUES(validation_status),validation_errors_json=VALUES(validation_errors_json)',
            [
                $legacyId,
                $batchId,
                $sourceModule,
                $rowNumber,
                $sourceRowNumber ?? $rowNumber,
                $rowHash,
                $sourceKey,
                $rawJson,
                $normalizedJson,
                $status,
                $errors ? json_encode($errors, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            ]
        );
    }

    public static function mirrorError(int $batchId, string $sourceModule, ?int $rowNumber, ?string $code, string $message, array $raw, array $normalized): void
    {
        Database::execute(
            'INSERT INTO sales_reference_import_errors
             (import_batch_id,source_module,`row_number`,error_code,error_message,raw_json,normalized_json,created_at)
             VALUES (?,?,?,?,?,?,?,NOW())',
            [
                $batchId,
                $sourceModule,
                $rowNumber,
                $code,
                $message,
                json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]
        );
    }

    public static function setPeriodKey(int $batchId, ?string $periodKey): void
    {
        Database::execute('UPDATE sales_import_batches SET period_key=?,updated_at=NOW() WHERE id=?', [$periodKey, $batchId]);
        Database::execute('UPDATE sales_reference_import_batches SET period_key=?,updated_at=NOW() WHERE id=?', [$periodKey, $batchId]);
    }

    public static function setActiveReferenceBatch(int $batchId, ?int $actorId = null): void
    {
        $batch = Database::fetch('SELECT id,source_module,period_key FROM sales_import_batches WHERE id=? FOR UPDATE', [$batchId]);
        if (!$batch) throw new InvalidArgumentException('Batch مرجع پیدا نشد.');
        $periodKey = $batch['period_key'] ?? null;
        $params = [$batch['source_module']];
        $periodSql = ' AND period_key IS NULL';
        if ($periodKey !== null && $periodKey !== '') {
            $periodSql = ' AND period_key=?';
            $params[] = $periodKey;
        }
        Database::execute('UPDATE sales_import_batches SET is_active_reference=0,updated_at=NOW() WHERE source_module=?' . $periodSql, $params);
        Database::execute('UPDATE sales_reference_import_batches SET is_active_reference=0,updated_at=NOW() WHERE source_module=?' . $periodSql, $params);
        Database::execute('UPDATE sales_import_batches SET is_active_reference=1,activated_at=NOW(),activated_by=?,status="committed",pipeline_status="activated",updated_at=NOW() WHERE id=?', [$actorId, $batchId]);
        Database::execute('UPDATE sales_reference_import_batches SET is_active_reference=1,activated_at=NOW(),activated_by=?,status="committed",pipeline_status="activated",updated_at=NOW() WHERE id=?', [$actorId, $batchId]);
    }

    public static function getActiveReferenceBatch(string $sourceModule, ?string $periodKey = null): ?array
    {
        $params = [$sourceModule];
        $where = 'source_module=? AND is_active_reference=1 AND status="committed"';
        if ($periodKey !== null && $periodKey !== '') {
            $where .= ' AND period_key=?';
            $params[] = $periodKey;
        }
        return Database::fetch("SELECT * FROM sales_import_batches WHERE {$where} ORDER BY activated_at DESC,id DESC LIMIT 1", $params);
    }

    public static function getActiveSalesAggregateQueryScope(?string $periodKey = null): array
    {
        $batch = self::getActiveReferenceBatch('sales_aggregate', $periodKey);
        return ['sql' => 'import_batch_id = ?', 'params' => [(int)($batch['id'] ?? 0)], 'batch' => $batch];
    }

    public static function getActiveInventoryAggregateQueryScope(?string $periodKey = null): array
    {
        $batch = self::getActiveReferenceBatch('inventory_aggregate', $periodKey);
        return ['sql' => 'import_batch_id = ?', 'params' => [(int)($batch['id'] ?? 0)], 'batch' => $batch];
    }

    public static function statusSummary(): array
    {
        return [
            'sales_aggregate' => self::getActiveReferenceBatch('sales_aggregate'),
            'inventory_aggregate' => self::getActiveReferenceBatch('inventory_aggregate'),
        ];
    }

    public static function recentBatches(int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        return Database::fetchAll(
            "SELECT id,source_module,source_type,original_file_name,period_key,status,pipeline_status,is_active_reference,total_rows,valid_rows,invalid_rows,duplicate_rows,inserted_rows,updated_rows,skipped_rows,started_by,started_at,finished_at,created_at
             FROM sales_reference_import_batches ORDER BY id DESC LIMIT {$limit}"
        );
    }

    public static function recentErrors(int $limit = 100, int $batchId = 0): array
    {
        $limit = max(1, min(500, $limit));
        if ($batchId > 0) {
            return Database::fetchAll("SELECT * FROM sales_reference_import_errors WHERE import_batch_id=? ORDER BY id DESC LIMIT {$limit}", [$batchId]);
        }
        return Database::fetchAll("SELECT * FROM sales_reference_import_errors ORDER BY id DESC LIMIT {$limit}");
    }

    private static function publicStatus(string $status): string
    {
        return match ($status) {
            'preview' => 'validated',
            'completed' => 'committed',
            default => $status,
        };
    }
}
