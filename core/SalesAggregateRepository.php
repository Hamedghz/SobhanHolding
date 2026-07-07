<?php
require_once __DIR__ . '/Database.php';

class SalesAggregateRepository
{
    public static function createBatch(array $data): int
    {
        Database::execute(
            'INSERT INTO sales_import_batches
             (source_type,source_module,file_name,file_hash,detected_sheet,detected_table,import_mode,status,started_by,started_at,metadata_json,created_at,updated_at)
             VALUES (?,"sales_aggregate",?,?,?,?,?,?,?,NOW(),?,NOW(),NOW())',
            [$data['source_type'],$data['file_name'],$data['file_hash'],$data['detected_sheet']??null,$data['detected_table']??null,
             $data['import_mode'],$data['status'],$data['started_by'],$data['metadata_json']]
        );
        return (int)Database::lastInsertId();
    }

    public static function batchForActor(int $batchId, int $actorId, bool $isAdmin = false, bool $forUpdate = false): ?array
    {
        $sql = 'SELECT * FROM sales_import_batches WHERE id=? AND source_module="sales_aggregate"';
        $params = [$batchId];
        if (!$isAdmin) { $sql .= ' AND started_by=?'; $params[] = $actorId; }
        if ($forUpdate) $sql .= ' FOR UPDATE';
        return Database::fetch($sql, $params);
    }

    public static function mappings(): array
    {
        return Database::fetchAll(
            'SELECT source_header,normalized_key,required,data_type FROM sales_import_column_mappings
             WHERE source_module="sales_aggregate" AND active=1 ORDER BY id'
        );
    }

    public static function updateBatchDetection(int $batchId, array $candidate, string $status, array $metadata): void
    {
        Database::execute(
            'UPDATE sales_import_batches SET detected_sheet=?,detected_table=?,status=?,metadata_json=?,updated_at=NOW() WHERE id=?',
            [$candidate['sheet_name']??null,$candidate['table_name']??null,$status,json_encode($metadata,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),$batchId]
        );
    }

    public static function addStagingRow(int $batchId, int $rowNumber, array $raw, array $normalized, string $status, array $errors, string $sourceKey): void
    {
        Database::execute(
            'INSERT INTO staging_sales_data
             (import_batch_id,source_module,`row_number`,raw_json,normalized_json,validation_status,validation_errors_json,source_unique_key,created_at)
             VALUES (?,"sales_aggregate",?,?,?,?,?,?,NOW())',
            [$batchId,$rowNumber,json_encode($raw,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
             json_encode($normalized,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),$status,
             $errors?json_encode($errors,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES):null,$sourceKey]
        );
        foreach ($errors as $error) {
            Database::execute(
                'INSERT INTO sales_import_errors
                 (import_batch_id,source_module,`row_number`,error_code,error_message,raw_json,normalized_json,created_at)
                 VALUES (?,"sales_aggregate",?,?,?,?,?,NOW())',
                [$batchId,$rowNumber,$error['code']??null,$error['message'],json_encode($raw,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
                 json_encode($normalized,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]
            );
        }
    }

    public static function updateBatchCounts(int $batchId, array $summary, string $status = 'preview'): void
    {
        Database::execute(
            'UPDATE sales_import_batches SET status=?,total_rows=?,valid_rows=?,invalid_rows=?,duplicate_rows=?,updated_at=NOW() WHERE id=?',
            [$status,$summary['total_rows'],$summary['valid_rows'],$summary['invalid_rows'],$summary['duplicate_rows'],$batchId]
        );
    }

    public static function stagingRows(int $batchId, string $status = 'valid'): array
    {
        return Database::fetchAll(
            'SELECT * FROM staging_sales_data WHERE import_batch_id=? AND source_module="sales_aggregate" AND validation_status=? ORDER BY `row_number`,id',
            [$batchId,$status]
        );
    }

    public static function sourceKeyExists(string $sourceKey): bool
    {
        return Database::fetch('SELECT id FROM sales_aggregate_rows WHERE source_unique_key=? LIMIT 1', [$sourceKey]) !== null;
    }

    public static function finalRowBySourceKey(string $sourceKey, bool $forUpdate = false): ?array
    {
        return Database::fetch('SELECT id FROM sales_aggregate_rows WHERE source_unique_key=? LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : ''), [$sourceKey]);
    }

    public static function insertFinal(int $batchId, string $sourceKey, array $data, array $raw): void
    {
        Database::execute(
            'INSERT INTO sales_aggregate_rows
             (import_batch_id,source_unique_key,unique_code,invoice_type,invoice_number,sub_invoice_number,invoice_date_raw,invoice_date,
              customer_code,customer_name,product_code,product_name,visitor_code,line_code,quantity,gross_amount,discount_amount,net_amount,raw_json,created_at,updated_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())',
            [$batchId,$sourceKey,$data['unique_code']??null,$data['invoice_type']??null,$data['invoice_number']??null,$data['sub_invoice_number']??null,
             $data['invoice_date_raw']??null,$data['invoice_date']??null,$data['customer_code']??null,$data['customer_name']??null,
             $data['product_code']??null,$data['product_name']??null,$data['visitor_code']??null,$data['line_code']??null,
             $data['quantity']??null,$data['gross_amount']??null,$data['discount_amount']??null,$data['net_amount']??null,
             json_encode($raw,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]
        );
    }

    public static function updateFinal(int $id, int $batchId, string $sourceKey, array $data, array $raw): void
    {
        Database::execute(
            'UPDATE sales_aggregate_rows SET import_batch_id=?,source_unique_key=?,unique_code=?,invoice_type=?,invoice_number=?,sub_invoice_number=?,
             invoice_date_raw=?,invoice_date=?,customer_code=?,customer_name=?,product_code=?,product_name=?,visitor_code=?,line_code=?,quantity=?,
             gross_amount=?,discount_amount=?,net_amount=?,raw_json=?,updated_at=NOW() WHERE id=?',
            [$batchId,$sourceKey,$data['unique_code']??null,$data['invoice_type']??null,$data['invoice_number']??null,$data['sub_invoice_number']??null,
             $data['invoice_date_raw']??null,$data['invoice_date']??null,$data['customer_code']??null,$data['customer_name']??null,
             $data['product_code']??null,$data['product_name']??null,$data['visitor_code']??null,$data['line_code']??null,$data['quantity']??null,
             $data['gross_amount']??null,$data['discount_amount']??null,$data['net_amount']??null,
             json_encode($raw,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),$id]
        );
    }

    public static function markStaging(int $id, string $status): void
    {
        Database::execute('UPDATE staging_sales_data SET validation_status=? WHERE id=?', [$status,$id]);
    }

    public static function finishBatch(int $batchId, string $status, int $imported, int $updated, int $skipped, ?string $error = null): void
    {
        Database::execute(
            'UPDATE sales_import_batches SET status=?,imported_rows=?,updated_rows=?,skipped_rows=?,error_message=?,finished_at=NOW(),updated_at=NOW() WHERE id=?',
            [$status,$imported,$updated,$skipped,$error,$batchId]
        );
    }
}
