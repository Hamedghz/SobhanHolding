<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/SalesReferenceRepository.php';

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
        $id = (int)Database::lastInsertId();
        SalesReferenceRepository::mirrorBatch($id, [
            'source_module' => 'sales_aggregate',
            'source_type' => $data['source_type'],
            'original_file_name' => $data['file_name'],
            'stored_file_path' => $data['stored_file_path'] ?? null,
            'file_hash' => $data['file_hash'],
            'detected_sheet' => $data['detected_sheet'] ?? null,
            'detected_table' => $data['detected_table'] ?? null,
            'detected_range' => $data['detected_range'] ?? null,
            'period_key' => $data['period_key'] ?? null,
            'import_mode' => $data['import_mode'],
            'status' => $data['status'],
            'started_by' => $data['started_by'],
            'metadata_json' => $data['metadata_json'],
        ]);
        return $id;
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
        SalesReferenceRepository::mirrorBatchFromLegacy($batchId);
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
        $stagingId = (int)Database::lastInsertId();
        SalesReferenceRepository::mirrorStagingRow($stagingId, $batchId, 'sales_aggregate', $rowNumber, $raw, $normalized, $status, $errors, $sourceKey);
        foreach ($errors as $error) {
            Database::execute(
                'INSERT INTO sales_import_errors
                 (import_batch_id,source_module,`row_number`,error_code,error_message,raw_json,normalized_json,created_at)
                 VALUES (?,"sales_aggregate",?,?,?,?,?,NOW())',
                [$batchId,$rowNumber,$error['code']??null,$error['message'],json_encode($raw,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
                 json_encode($normalized,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]
            );
            SalesReferenceRepository::mirrorError($batchId, 'sales_aggregate', $rowNumber, $error['code'] ?? null, (string)$error['message'], $raw, $normalized);
        }
    }

    public static function updateBatchCounts(int $batchId, array $summary, string $status = 'preview'): void
    {
        Database::execute(
            'UPDATE sales_import_batches SET status=?,total_rows=?,valid_rows=?,invalid_rows=?,duplicate_rows=?,updated_at=NOW() WHERE id=?',
            [$status,$summary['total_rows'],$summary['valid_rows'],$summary['invalid_rows'],$summary['duplicate_rows'],$batchId]
        );
        SalesReferenceRepository::mirrorBatchFromLegacy($batchId);
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
        self::updateReferenceFields($sourceKey, $data);
        self::syncReferenceAliases($batchId);
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
        self::updateReferenceFields($sourceKey, $data);
        self::syncReferenceAliases($batchId);
    }

    public static function markStaging(int $id, string $status): void
    {
        Database::execute('UPDATE staging_sales_data SET validation_status=? WHERE id=?', [$status,$id]);
        Database::execute('UPDATE staging_sales_reference_rows SET validation_status=? WHERE id=?', [$status,$id]);
    }

    public static function finishBatch(int $batchId, string $status, int $imported, int $updated, int $skipped, ?string $error = null): void
    {
        Database::execute(
            'UPDATE sales_import_batches SET status=?,imported_rows=?,updated_rows=?,skipped_rows=?,error_message=?,finished_at=NOW(),updated_at=NOW() WHERE id=?',
            [$status,$imported,$updated,$skipped,$error,$batchId]
        );
        SalesReferenceRepository::mirrorBatchFromLegacy($batchId);
    }

    private static function syncReferenceAliases(int $batchId): void
    {
        try {
            Database::execute(
                'UPDATE sales_aggregate_rows
                 SET invoice_sub_number=COALESCE(invoice_sub_number,sub_invoice_number),
                     total_qty=COALESCE(total_qty,quantity),
                     discount_total=COALESCE(discount_total,discount_amount),
                     amount_after_discount=COALESCE(amount_after_discount, gross_amount - COALESCE(discount_amount,0))
                 WHERE import_batch_id=?',
                [$batchId]
            );
        } catch (Throwable $ignored) {
        }
    }

    private static function updateReferenceFields(string $sourceKey, array $data): void
    {
        $map = [
            'period_key'=>'period_key','invoice_sub_number'=>'sub_invoice_number','visitor_name'=>'visitor_name','line_name'=>'line_name',
            'customer_address'=>'customer_address','customer_mobile'=>'mobile','customer_phone'=>'phone','customer_grade'=>'customer_grade',
            'customer_national_code'=>'national_code','customer_guild_code'=>'customer_class_code','customer_guild_name'=>'customer_class_name',
            'customer_role_code'=>'customer_role_code','city_code'=>'city_code','city_name'=>'city_name','province_code'=>'province_code',
            'province_name'=>'province_name','route_code'=>'route_code','route_name'=>'route_name','product_identifier'=>'product_identifier',
            'product_weight'=>'product_weight','product_volume'=>'product_volume','carton_size'=>'units_per_carton','carton_qty'=>'carton_quantity',
            'part_qty'=>'unit_quantity','total_qty'=>'quantity','net_carton_qty'=>'net_carton_quantity','manufacturer_code'=>'manufacturer_code',
            'manufacturer_name'=>'manufacturer_name','group_code'=>'group_code','group_name'=>'group_name','product_tree_group_code'=>'product_tree_group_code',
            'product_tree_group_name'=>'product_tree_group_name','unit_price'=>'unit_price','discount_percent_1'=>'discount_percent_1',
            'discount_amount_1'=>'discount_amount_1','discount_percent_2'=>'discount_percent_2','discount_amount_2'=>'discount_amount_2',
            'discount_percent_3'=>'discount_percent_3','discount_amount_3'=>'discount_amount_3','discount_percent_4'=>'discount_percent_4',
            'discount_amount_4'=>'discount_amount_4','discount_percent_5'=>'discount_percent_5','discount_amount_5'=>'discount_amount_5',
            'discount_total'=>'discount_amount','amount_after_discount'=>'gross_after_discount','tax_amount'=>'tax_amount','duty_amount'=>'duty_amount',
            'tax_duty_total'=>'tax_duty_amount','warehouse_code'=>'warehouse_code','warehouse_name'=>'warehouse_name','branch_code'=>'branch_code',
            'branch_name'=>'branch_name','distributor_code'=>'distributor_code','distributor_name'=>'distributor_name','driver_code'=>'driver_code',
            'driver_name'=>'driver_name','driver_name_from_invoice'=>'invoice_driver_name','supervisor_code'=>'supervisor_code','supervisor_name'=>'supervisor_name',
            'sales_manager_code'=>'sales_manager_code','sales_manager_name'=>'sales_manager_name','output_number'=>'dispatch_number',
            'sale_price_class'=>'sales_price_class','brand_code'=>'brand_code','brand_name'=>'brand_name','payment_method'=>'payment_method',
            'customer_birth_date_raw'=>'customer_birth_date','customer_signboard'=>'customer_sign','reference_number'=>'reference_number',
            'base_unit'=>'base_unit','part_unit'=>'sub_unit','fifo_cost'=>'fifo_cost','average_cost'=>'average_cost','tax_percent'=>'tax_percent',
            'duty_percent'=>'duty_percent','purchase_cost_price'=>'purchase_cost','formula_number_1'=>'formula_number_1',
            'formula_number_2'=>'formula_number_2','formula_number_3'=>'formula_number_3','formula_number_4'=>'formula_number_4',
            'formula_number_5'=>'formula_number_5','formula_name_1'=>'formula_name_1','formula_name_2'=>'formula_name_2',
            'formula_name_3'=>'formula_name_3','formula_name_4'=>'formula_name_4','formula_name_5'=>'formula_name_5',
            'circulation_month'=>'turnover_month','weighted_flag'=>'is_weighted','consumer_flag'=>'consumer',
            'product_consumer_flag'=>'product_consumer','purchase_class_type'=>'purchase_class_type','commission_type'=>'commission_type',
            'coefficient'=>'coefficient','coefficient_sales_amount'=>'sales_coefficient','product_priority'=>'priority',
        ];
        $sets = [];
        $params = [];
        foreach ($map as $column => $key) {
            if (array_key_exists($key, $data)) {
                $sets[] = "`{$column}`=?";
                $params[] = $data[$key];
            }
        }
        if (!$sets) return;
        $params[] = $sourceKey;
        Database::execute('UPDATE sales_aggregate_rows SET ' . implode(',', $sets) . ',updated_at=NOW() WHERE source_unique_key=?', $params);
    }
}
