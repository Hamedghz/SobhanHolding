<?php

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/SpreadsheetImportReader.php';
require_once __DIR__ . '/../core/SalesDataNormalizer.php';
require_once __DIR__ . '/../core/SalesAggregateImportService.php';
require_once __DIR__ . '/../core/InventoryImportService.php';
require_once __DIR__ . '/../core/SalesReferenceRepository.php';
require_once __DIR__ . '/../lib/ImportSourceRegistry.php';
require_once __DIR__ . '/SalesPlanningService.php';

final class UnifiedImportService
{
    public static function assertTemplatePermission(string $source): void
    {
        ImportSourceRegistry::get($source);
        self::assertSourcePermission($source, 'upload');
    }

    public const MODES = ['replace_reference','append','update_existing','skip_duplicates','fail_on_duplicate'];

    public static function upload(array $file, string $sourceHint, string $mode, int $actorId, array $context = [], bool $allowStoredFile = false): array
    {
        $mode = in_array($mode, self::MODES, true) ? $mode : 'replace_reference';
        if ($sourceHint !== '' && !isset(ImportSourceRegistry::all()[$sourceHint])) {
            throw new InvalidArgumentException('نوع منبع انتخاب‌شده معتبر نیست.');
        }
        if ($sourceHint !== '') self::assertSourcePermission($sourceHint, 'upload');
        $stored = SpreadsheetImportReader::store($file, 'unified-imports', $allowStoredFile);
        $batchId = 0;
        try {
            $workbook = SpreadsheetImportReader::read($stored['path'], $stored['extension']);
            $candidates = self::detectWorkbook($workbook, $sourceHint);
            if (!$candidates) throw new InvalidArgumentException('هیچ جدول یا محدوده قابل تشخیصی در فایل پیدا نشد.');
            $selected = self::automaticCandidate($candidates);
            $snapshotDate = self::optionalDate($context['snapshot_date'] ?? '');
            if (
                !$snapshotDate
                && $selected
                && !empty(ImportSourceRegistry::get($selected['source_module'])['snapshot_fallback'])
            ) {
                $snapshotDate = self::inferWorkbookDate($workbook);
            }
            if ($selected) self::assertSourcePermission($selected['source_module'], 'upload');
            if ($selected && self::alreadyImported($stored['file_hash'], $selected['source_module'])) {
                throw new DomainException('این فایل با همین نوع منبع قبلاً با موفقیت وارد شده است. برای retry از Batch قبلی استفاده کنید.');
            }
            $metadata = [
                'stored_name' => $stored['stored_name'],
                'extension' => $stored['extension'],
                'mime' => $stored['mime'],
                'candidates' => array_map([self::class, 'candidateMetadata'], $candidates),
                'detection_priority' => ['table','sheet','headers','confidence'],
            ];
            $sourceModule = $selected['source_module'] ?? ($candidates[0]['source_module'] ?? $sourceHint);
            $batchId = self::createBatch([
                'source_type' => $stored['source_type'],
                'source_module' => $sourceModule,
                'file_name' => $stored['file_name'],
                'stored_file_path' => 'storage/unified-imports/' . $stored['stored_name'],
                'file_hash' => $stored['file_hash'],
                'detected_sheet' => $selected['sheet_name'] ?? null,
                'detected_table' => $selected['table_name'] ?? null,
                'detected_range' => $selected['ref'] ?? null,
                'source_confidence' => $selected['confidence'] ?? null,
                'period_key' => self::text($context['period_key'] ?? '', 50) ?: null,
                'snapshot_date' => $snapshotDate,
                'period_id' => max(0, (int)($context['period_id'] ?? 0)) ?: null,
                'import_mode' => $mode,
                'status' => $selected ? 'uploaded' : 'awaiting_source_selection',
                'pipeline_status' => $selected ? 'detected' : 'detected',
                'started_by' => $actorId,
                'metadata_json' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
            if (!$selected) {
                return ['batch_id'=>$batchId,'needs_selection'=>true,'candidates'=>$metadata['candidates']];
            }
            $summary = self::stageCandidate($batchId, $selected);
            return ['batch_id'=>$batchId,'needs_selection'=>false,'summary'=>$summary];
        } catch (Throwable $e) {
            if ($batchId > 0) self::markFailed($batchId);
            else @unlink($stored['path']);
            throw $e;
        }
    }

    public static function selectCandidate(int $batchId, string $candidateKey, int $actorId, bool $isAdmin): array
    {
        $batch = self::batchForActor($batchId, $actorId, $isAdmin);
        if (!$batch || ($batch['pipeline_status'] ?? '') !== 'detected') throw new InvalidArgumentException('Batch قابل انتخاب منبع نیست.');
        $metadata = json_decode((string)$batch['metadata_json'], true) ?: [];
        $path = SpreadsheetImportReader::resolveStored('unified-imports', (string)($metadata['stored_name'] ?? ''));
        $workbook = SpreadsheetImportReader::read($path, (string)($metadata['extension'] ?? ''));
        foreach (self::detectWorkbook($workbook, '') as $candidate) {
            if (!hash_equals($candidate['key'], $candidateKey)) continue;
            self::assertSourcePermission($candidate['source_module'], 'upload');
            if (self::alreadyImported((string)$batch['file_hash'], $candidate['source_module'])) {
                throw new DomainException('این فایل با همین نوع منبع قبلاً فعال شده است.');
            }
            Database::execute(
                'UPDATE sales_import_batches SET source_module=?,detected_sheet=?,detected_table=?,detected_range=?,source_confidence=?,status="uploaded",updated_at=NOW() WHERE id=?',
                [$candidate['source_module'],$candidate['sheet_name'],$candidate['table_name'],$candidate['ref'],$candidate['confidence'],$batchId]
            );
            SalesReferenceRepository::mirrorBatchFromLegacy($batchId);
            return self::stageCandidate($batchId, $candidate);
        }
        throw new InvalidArgumentException('منبع انتخاب‌شده معتبر نیست.');
    }

    public static function commit(int $batchId, int $actorId, bool $isAdmin): array
    {
        $batch = self::batchForActor($batchId, $actorId, $isAdmin);
        if (!$batch || ($batch['pipeline_status'] ?? '') !== 'ready_to_commit') {
            throw new InvalidArgumentException('Batch آماده ثبت نهایی نیست.');
        }
        self::assertSourcePermission((string)$batch['source_module'], 'commit');
        if ($batch['source_module'] === 'sales_aggregate') {
            $result = SalesAggregateImportService::commitValidRows($batchId, $actorId, $isAdmin);
            self::setPipelineStatus($batchId, 'activated');
            return ['inserted'=>$result['imported'],'updated'=>$result['updated'],'skipped'=>$result['skipped']];
        }
        if ($batch['source_module'] === 'inventory_aggregate') {
            $result = InventoryImportService::commitValidRows($batchId, $actorId, $isAdmin);
            self::setPipelineStatus($batchId, 'activated');
            return $result;
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $locked = Database::fetch('SELECT * FROM sales_import_batches WHERE id=? FOR UPDATE', [$batchId]);
            if (!$locked || ($locked['pipeline_status'] ?? '') !== 'ready_to_commit') throw new InvalidArgumentException('Batch هم‌زمان تغییر کرده است.');
            $rows = Database::fetchAll(
                'SELECT * FROM staging_sales_data WHERE import_batch_id=? AND validation_status="valid" ORDER BY `row_number`,id',
                [$batchId]
            );
            if (!$rows) throw new DomainException('ردیف معتبری برای ثبت نهایی وجود ندارد.');
            $inserted = $updated = $skipped = 0;
            foreach (Database::fetchAll('SELECT id FROM staging_sales_data WHERE import_batch_id=? AND validation_status="duplicate"', [$batchId]) as $duplicate) {
                Database::execute('UPDATE staging_sales_data SET validation_status="skipped" WHERE id=?', [(int)$duplicate['id']]);
                Database::execute('UPDATE staging_sales_reference_rows SET validation_status="skipped" WHERE id=?', [(int)$duplicate['id']]);
                $skipped++;
            }
            foreach ($rows as $row) {
                $data = json_decode((string)$row['normalized_json'], true) ?: [];
                $raw = json_decode((string)$row['raw_json'], true) ?: [];
                [$wasInserted, $wasUpdated, $wasSkipped] = array_pad(
                    self::commitRow($locked, (string)$row['source_unique_key'], $data, $raw, $actorId),
                    3,
                    false
                );
                $rowStatus = $wasSkipped ? 'skipped' : 'committed';
                Database::execute('UPDATE staging_sales_data SET validation_status=? WHERE id=?', [$rowStatus,(int)$row['id']]);
                Database::execute('UPDATE staging_sales_reference_rows SET validation_status=? WHERE id=?', [$rowStatus,(int)$row['id']]);
                $inserted += $wasInserted ? 1 : 0;
                $updated += $wasUpdated ? 1 : 0;
                $skipped += $wasSkipped ? 1 : 0;
            }
            Database::execute(
                'UPDATE sales_import_batches SET status="committed",pipeline_status="committed",imported_rows=?,updated_rows=?,skipped_rows=?,finished_at=NOW(),updated_at=NOW() WHERE id=?',
                [$inserted,$updated,$skipped,$batchId]
            );
            SalesReferenceRepository::mirrorBatchFromLegacy($batchId);
            SalesReferenceRepository::setActiveReferenceBatch($batchId, $actorId);
            self::setPipelineStatus($batchId, 'activated');
            $pdo->commit();
            return compact('inserted','updated','skipped');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ($e instanceof InvalidArgumentException || $e instanceof DomainException) throw $e;
            error_log('Unified import commit: ' . $e->getMessage());
            throw new RuntimeException('ثبت نهایی اطلاعات انجام نشد.');
        }
    }

    public static function rollback(int $batchId, int $actorId, bool $isAdmin): void
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $batch = self::batchForActor($batchId, $actorId, $isAdmin, true);
            if (!$batch || in_array($batch['pipeline_status'] ?? '', ['committed','activated','rolled_back'], true)) {
                throw new InvalidArgumentException('Batch قابل بازگردانی نیست.');
            }
            Database::execute('UPDATE staging_sales_data SET validation_status="rejected" WHERE import_batch_id=?', [$batchId]);
            Database::execute('UPDATE staging_sales_reference_rows SET validation_status="rejected" WHERE import_batch_id=?', [$batchId]);
            Database::execute('UPDATE sales_import_batches SET status="cancelled",pipeline_status="rolled_back",finished_at=NOW(),updated_at=NOW() WHERE id=?', [$batchId]);
            SalesReferenceRepository::mirrorBatchFromLegacy($batchId);
            self::setPipelineStatus($batchId, 'rolled_back');
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public static function retry(int $batchId, int $actorId, bool $isAdmin): array
    {
        $batch = self::batchForActor($batchId, $actorId, $isAdmin);
        if (!$batch) throw new InvalidArgumentException('Batch پیدا نشد.');
        $metadata = json_decode((string)$batch['metadata_json'], true) ?: [];
        $path = SpreadsheetImportReader::resolveStored('unified-imports', (string)($metadata['stored_name'] ?? ''));
        $file = [
            'error' => UPLOAD_ERR_OK,
            'tmp_name' => $path,
            'size' => filesize($path),
            'name' => (string)$batch['file_name'],
        ];
        $result = self::upload($file, (string)$batch['source_module'], (string)$batch['import_mode'], $actorId, [
            'period_key' => $batch['period_key'] ?? '',
            'snapshot_date' => $batch['snapshot_date'] ?? '',
            'period_id' => $batch['period_id'] ?? 0,
        ], true);
        Database::execute('UPDATE sales_import_batches SET retry_of_batch_id=? WHERE id=?', [$batchId,(int)$result['batch_id']]);
        SalesReferenceRepository::mirrorBatchFromLegacy((int)$result['batch_id']);
        return $result;
    }

    public static function batchForActor(int $batchId, int $actorId, bool $isAdmin, bool $forUpdate = false): ?array
    {
        $sql = 'SELECT * FROM sales_import_batches WHERE id=?';
        $params = [$batchId];
        if (!$isAdmin) {
            $sql .= ' AND started_by=?';
            $params[] = $actorId;
        }
        if ($forUpdate) $sql .= ' FOR UPDATE';
        return Database::fetch($sql, $params);
    }

    public static function summary(int $batchId): array
    {
        $batch = Database::fetch('SELECT * FROM sales_import_batches WHERE id=?', [$batchId]);
        if (!$batch) throw new InvalidArgumentException('Batch پیدا نشد.');
        return [
            'total_rows'=>(int)$batch['total_rows'],
            'valid_rows'=>(int)$batch['valid_rows'],
            'invalid_rows'=>(int)$batch['invalid_rows'],
            'duplicate_rows'=>(int)$batch['duplicate_rows'],
            'ready_rows'=>(int)Database::fetch('SELECT COUNT(*) c FROM staging_sales_data WHERE import_batch_id=? AND validation_status="valid"', [$batchId])['c'],
        ];
    }

    public static function attendanceMappingUsers(): array
    {
        self::assertSourcePermission('attendance', 'upload');
        return Database::fetchAll(
            'SELECT id,name,employee_no,kara_system_code
             FROM users WHERE status="active" ORDER BY display_order,name'
        );
    }

    public static function mapAttendanceIdentity(
        int $batchId,
        int $stagingId,
        int $userId,
        int $actorId,
        bool $isAdmin
    ): array {
        self::assertSourcePermission('attendance', 'upload');
        $batch = self::batchForActor($batchId, $actorId, $isAdmin);
        if (!$batch || ($batch['source_module'] ?? '') !== 'attendance') {
            throw new InvalidArgumentException('Batch کارکرد معتبر نیست.');
        }
        if (in_array($batch['pipeline_status'] ?? '', ['committed','activated','rolled_back'], true)) {
            throw new DomainException('نگاشت هویت این Batch دیگر قابل تغییر نیست.');
        }
        $row = Database::fetch(
            'SELECT * FROM staging_sales_data WHERE id=? AND import_batch_id=? AND source_module="attendance"',
            [$stagingId,$batchId]
        );
        if (!$row) throw new InvalidArgumentException('ردیف حل‌نشده پیدا نشد.');
        $user = Database::fetch(
            'SELECT id,name,employee_no,kara_system_code FROM users WHERE id=? AND status="active"',
            [$userId]
        );
        if (!$user) throw new InvalidArgumentException('کاربر فعال انتخاب‌شده معتبر نیست.');

        $normalized = json_decode((string)$row['normalized_json'], true) ?: [];
        [$sourceField,$externalCode] = self::attendanceExternalIdentity($normalized);
        if ($externalCode === '') throw new InvalidArgumentException('این ردیف کد سیستم کارا یا کد پرسنلی ندارد.');

        Database::execute(
            'INSERT INTO hr_attendance_identity_mappings(source_field,external_code,user_id,active,created_by,created_at,updated_at)
             VALUES (?,?,?,1,?,NOW(),NOW())
             ON DUPLICATE KEY UPDATE user_id=VALUES(user_id),active=1,created_by=VALUES(created_by),updated_at=NOW()',
            [$sourceField,$externalCode,$userId,$actorId]
        );

        $raw = json_decode((string)$row['raw_json'], true) ?: [];
        $errors = self::validateGeneric('attendance', $normalized, $raw, $batch);
        $sourceKey = self::sourceKey('attendance', $normalized, $batch);
        if (Database::fetch(
            'SELECT id FROM staging_sales_data
             WHERE import_batch_id=? AND id<>? AND source_unique_key=?
               AND validation_status IN("valid","duplicate","committed") LIMIT 1',
            [$batchId,$stagingId,$sourceKey]
        )) {
            $errors[] = ['code'=>'duplicate','message'=>'این کاربر و تاریخ در Batch تکراری است.'];
        }
        $status = $errors ? 'invalid' : 'valid';
        Database::execute(
            'UPDATE staging_sales_data
             SET normalized_json=?,validation_status=?,validation_errors_json=?,source_unique_key=?
             WHERE id=?',
            [
                json_encode($normalized,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
                $status,
                $errors?json_encode($errors,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES):null,
                $sourceKey,
                $stagingId,
            ]
        );
        SalesReferenceRepository::mirrorStagingRow(
            $stagingId,
            $batchId,
            'attendance',
            (int)$row['row_number'],
            $raw,
            $normalized,
            $status,
            $errors,
            $sourceKey
        );
        self::refreshBatchSummary($batchId);
        Auth::log($actorId, 'hr_attendance_identity_mapped', 'hr_attendance_identity_mappings', $userId);
        return ['status'=>$status,'user'=>$user,'errors'=>$errors];
    }

    public static function normalizeAttendanceClock(mixed $value): array
    {
        $value = trim(SalesDataNormalizer::normalizePersianArabicDigits($value));
        if ($value === '') return ['time'=>null,'note'=>null];
        if (is_numeric($value)) {
            $number = (float)$value;
            if ($number >= 0 && $number < 1) {
                $minutes = (int)round($number * 1440);
                if ($minutes >= 1440) throw new InvalidArgumentException('ساعت اکسل خارج از بازه یک روز است.');
                return ['time'=>sprintf('%02d:%02d:00',intdiv($minutes,60),$minutes%60),'note'=>null];
            }
            throw new InvalidArgumentException('ساعت عددی باید کسر اکسل بین صفر و یک باشد.');
        }
        if (preg_match('/^(\d{1,2}):(\d{1,2})(?::(\d{1,2}))?$/', $value, $match)) {
            $hour=(int)$match[1];$minute=(int)$match[2];$second=(int)($match[3]??0);
            if($hour>23||$minute>59||$second>59)throw new InvalidArgumentException('ساعت خارج از بازه معتبر است.');
            return ['time'=>sprintf('%02d:%02d:00',$hour,$minute),'note'=>null];
        }
        return ['time'=>null,'note'=>self::text($value,190)];
    }

    public static function normalizeAttendanceDuration(mixed $value, string $numericUnit = 'hours'): array
    {
        $value = trim(SalesDataNormalizer::normalizePersianArabicDigits($value));
        if ($value === '') return ['minutes'=>0,'note'=>null];
        if (preg_match('/^(\d{1,3}):(\d{1,2})(?::(\d{1,2}))?$/', $value, $match)) {
            $hours=(int)$match[1];$minutes=(int)$match[2];$seconds=(int)($match[3]??0);
            if($minutes>59||$seconds>59||$hours>24||($hours===24&&($minutes>0||$seconds>0))) {
                throw new InvalidArgumentException('مدت زمان خارج از بازه معتبر است.');
            }
            return ['minutes'=>(int)round(($hours*60)+$minutes+($seconds/60)),'note'=>null];
        }
        if (is_numeric($value)) {
            $number=(float)$value;
            if($number<0)throw new InvalidArgumentException('مدت زمان نمی‌تواند منفی باشد.');
            if($number>0&&$number<1)return ['minutes'=>(int)round($number*1440),'note'=>null];
            $minutes=$numericUnit==='minutes'?$number:$number*60;
            if($minutes>1440)throw new InvalidArgumentException('مدت زمان بیشتر از ۲۴ ساعت است.');
            return ['minutes'=>(int)round($minutes),'note'=>null];
        }
        return ['minutes'=>0,'note'=>self::text($value,190)];
    }

    public static function detectWorkbook(array $workbook, string $sourceHint = ''): array
    {
        $sources = ImportSourceRegistry::all();
        if ($sourceHint !== '' && isset($sources[$sourceHint])) $sources = [$sourceHint => $sources[$sourceHint]];
        $candidates = [];
        foreach ($workbook['sheets'] ?? [] as $sheet) {
            if (!($sheet['visible'] ?? true)) continue;
            foreach ($sheet['tables'] ?? [] as $table) {
                $rows = SpreadsheetImportReader::crop($sheet['rows'] ?? [], (string)($table['ref'] ?? ''));
                foreach ($sources as $key => $source) {
                    $tableMatch = in_array(SalesDataNormalizer::normalizeHeader($table['name'] ?? ''), ImportSourceRegistry::normalizedAliases($source['tables']), true);
                    $confidence = self::sourceHeaderConfidence($rows[0] ?? [], $source);
                    if (!$tableMatch && $confidence < 0.6) continue;
                    $score = $tableMatch ? 100.0 : 60.0 + ($confidence * 30.0);
                    $candidates[] = self::candidate($key, $sheet, $rows, 'table', (string)($table['name'] ?? ''), (string)($table['ref'] ?? ''), $score, $confidence);
                }
            }
            foreach ($sources as $key => $source) {
                $sheetMatch = in_array(SalesDataNormalizer::normalizeHeader($sheet['name'] ?? ''), ImportSourceRegistry::normalizedAliases($source['sheets']), true);
                $confidence = self::sourceHeaderConfidence($sheet['rows'][0] ?? [], $source);
                if (!$sheetMatch && $confidence < 0.72) continue;
                $score = $sheetMatch ? 90.0 + ($confidence * 5.0) : 60.0 + ($confidence * 30.0);
                $candidates[] = self::candidate($key, $sheet, $sheet['rows'] ?? [], $sheetMatch ? 'sheet' : 'headers', '', '', $score, $confidence);
            }
        }
        $unique = [];
        foreach ($candidates as $candidate) {
            $identity = $candidate['source_module'].'|'.$candidate['sheet_name'].'|'.($candidate['table_name'] ?? '').'|'.($candidate['ref'] ?? '');
            if (!isset($unique[$identity]) || $candidate['score'] > $unique[$identity]['score']) $unique[$identity] = $candidate;
        }
        $candidates = array_values($unique);
        usort($candidates, static fn(array $a, array $b): int => $b['score'] <=> $a['score']);
        return $candidates;
    }

    public static function inferWorkbookDate(array $workbook): ?string
    {
        $dates = [];
        foreach ($workbook['sheets'] ?? [] as $sheet) {
            if (!($sheet['visible'] ?? true)) continue;
            foreach (array_slice($sheet['rows'] ?? [], 0, 12) as $row) {
                foreach (array_slice($row, 0, 12) as $value) {
                    $raw = trim(SalesDataNormalizer::normalizePersianArabicDigits($value));
                    if (!preg_match('/^(?:1[34]\d{2})[\/\-](?:0?[1-9]|1[0-2])[\/\-](?:0?[1-9]|[12]\d|3[01])$/', $raw)) continue;
                    try {
                        $date = SalesDataNormalizer::normalizeDate($raw);
                        if ($date) $dates[$date] = ($dates[$date] ?? 0) + 1;
                    } catch (Throwable) {
                        continue;
                    }
                }
            }
        }
        if (!$dates) return null;
        arsort($dates);
        if (count($dates) > 1) {
            $counts = array_values($dates);
            if (($counts[0] ?? 0) === ($counts[1] ?? 0)) return null;
        }
        return (string)array_key_first($dates);
    }

    private static function automaticCandidate(array $candidates): ?array
    {
        if (!$candidates) return null;
        $first = $candidates[0];
        if (($first['score'] ?? 0) < 72) return null;
        if (count($candidates) === 1) return $first;
        $second = $candidates[1];
        if (
            ($first['detection'] ?? '') === 'table'
            && ($second['source_module'] ?? '') === ($first['source_module'] ?? '')
            && ($second['sheet_name'] ?? '') === ($first['sheet_name'] ?? '')
            && ($second['detection'] ?? '') !== 'table'
        ) return $first;
        if (abs((float)$first['score'] - (float)$second['score']) < 8.0) return null;
        return $first;
    }

    private static function stageCandidate(int $batchId, array $candidate): array
    {
        $definition = ImportSourceRegistry::get($candidate['source_module']);
        $batch = Database::fetch('SELECT snapshot_date,period_id FROM sales_import_batches WHERE id=?', [$batchId]);
        $missingContext = [];
        if (!empty($definition['snapshot_required']) && empty($batch['snapshot_date'])) $missingContext[] = 'تاریخ snapshot';
        if (!empty($definition['period_id_required']) && empty($batch['period_id'])) $missingContext[] = 'شناسه دوره';
        if ($missingContext) {
            self::setPipelineStatus($batchId, 'validation_failed', ['context_required'=>true,'missing_context'=>$missingContext]);
            return ['total_rows'=>0,'valid_rows'=>0,'invalid_rows'=>0,'duplicate_rows'=>0,'ready_rows'=>0,'context_required'=>true,'missing_context'=>$missingContext];
        }
        if ($candidate['source_module'] === 'sales_aggregate') {
            $summary = SalesAggregateImportService::storeToStaging($batchId, $candidate);
            self::setReadyStatus($batchId, $summary);
            return $summary;
        }
        if ($candidate['source_module'] === 'inventory_aggregate') {
            $summary = InventoryImportService::storeStreamToStaging($batchId, $candidate);
            self::setReadyStatus($batchId, $summary);
            return $summary;
        }
        return self::stageGeneric($batchId, $candidate);
    }

    private static function stageGeneric(int $batchId, array $candidate): array
    {
        $source = ImportSourceRegistry::get($candidate['source_module']);
        $batch = Database::fetch('SELECT * FROM sales_import_batches WHERE id=?', [$batchId]);
        $seen = [];
        $summary = ['total_rows'=>0,'valid_rows'=>0,'invalid_rows'=>0,'duplicate_rows'=>0,'ready_rows'=>0];
        $headers = null;
        $mapping = null;
        foreach (SpreadsheetImportReader::candidateRows($candidate) as $item) {
                $values = $item['values'];
                if ($headers === null) {
                    $headers = array_map(static fn($value): string => trim((string)$value), $values);
                    $mapping = self::mapColumns($candidate['source_module'], $headers, $source['mappings']);
                    if ($mapping['missing_required']) {
                        self::setPipelineStatus($batchId, 'validation_failed', ['mapping_required'=>true,'missing_required'=>$mapping['missing_required']]);
                        return ['total_rows'=>0,'valid_rows'=>0,'invalid_rows'=>0,'duplicate_rows'=>0,'ready_rows'=>0,'mapping_required'=>true,'missing_required'=>$mapping['missing_required']];
                    }
                    continue;
                }
                if (!array_filter($values, static fn($value): bool => trim((string)$value) !== '')) continue;
                $rowNumber = (int)$item['row_number'];
                $raw = [];
                foreach ($headers as $index => $header) if ($header !== '') $raw[$header] = (string)($values[$index] ?? '');
                $normalized = self::normalizeMappedRow($values, $mapping['columns'] ?? []);
                if ($candidate['source_module'] === 'attendance' && empty($normalized['attendance_date_raw'])) {
                    $normalized['attendance_date'] = $batch['snapshot_date'] ?? null;
                }
                $errors = self::validateGeneric($candidate['source_module'], $normalized, $raw, $batch);
                $sourceKey = self::sourceKey($candidate['source_module'], $normalized, $batch);
                $duplicate = isset($seen[$sourceKey]) || self::finalExists($source['target_table'], $sourceKey);
                $seen[$sourceKey] = true;
                if ($duplicate && ($batch['import_mode'] ?? '') === 'fail_on_duplicate') $errors[] = ['code'=>'duplicate','message'=>'این ردیف تکراری است.'];
                $status = $errors ? 'invalid' : (($duplicate && in_array($batch['import_mode'], ['append','skip_duplicates'], true)) ? 'duplicate' : 'valid');
                self::insertStaging($batchId, $candidate, $rowNumber, $raw, $normalized, $status, $errors, $sourceKey);
                $summary['total_rows']++;
                if ($duplicate) $summary['duplicate_rows']++;
                if ($status === 'invalid') $summary['invalid_rows']++;
                else $summary['valid_rows']++;
                if ($status === 'valid') $summary['ready_rows']++;
        }
        if ($headers === null) throw new InvalidArgumentException('محدوده انتخاب‌شده داده‌ای ندارد.');
        Database::execute(
            'UPDATE sales_import_batches SET source_module=?,detected_sheet=?,detected_table=?,detected_range=?,source_confidence=?,status="preview",pipeline_status=?,total_rows=?,valid_rows=?,invalid_rows=?,duplicate_rows=?,updated_at=NOW() WHERE id=?',
            [
                $candidate['source_module'],$candidate['sheet_name'],$candidate['table_name'],$candidate['ref'],$candidate['confidence'],
                $summary['ready_rows'] > 0 ? 'ready_to_commit' : 'validation_failed',
                $summary['total_rows'],$summary['valid_rows'],$summary['invalid_rows'],$summary['duplicate_rows'],$batchId,
            ]
        );
        SalesReferenceRepository::mirrorBatchFromLegacy($batchId);
        self::setPipelineStatus($batchId, $summary['ready_rows'] > 0 ? 'ready_to_commit' : 'validation_failed');
        return $summary;
    }

    private static function mapColumns(string $sourceModule, array $headers, array $defaults): array
    {
        $mappings = $defaults;
        try {
            $overrides = Database::fetchAll(
                'SELECT source_header,normalized_key,required,data_type FROM sales_import_column_mappings WHERE source_module=? AND active=1 ORDER BY id',
                [$sourceModule]
            );
            if ($overrides) $mappings = array_merge($mappings, $overrides);
        } catch (Throwable) {
        }
        $byHeader = [];
        foreach ($mappings as $mapping) $byHeader[SalesDataNormalizer::normalizeHeader($mapping['source_header'])] = $mapping;
        $columns = [];
        foreach ($headers as $index => $header) {
            $normalized = SalesDataNormalizer::normalizeHeader($header);
            if ($normalized !== '' && isset($byHeader[$normalized])) $columns[$index] = $byHeader[$normalized];
        }
        $keys = array_column($columns, 'normalized_key');
        $required = [];
        foreach ($mappings as $mapping) if (!empty($mapping['required'])) $required[$mapping['normalized_key']] = $mapping['source_header'];
        $missing = [];
        foreach ($required as $key => $label) if (!in_array($key, $keys, true)) $missing[] = $label;
        return ['columns'=>$columns,'missing_required'=>array_values(array_unique($missing))];
    }

    private static function normalizeMappedRow(array $values, array $columns): array
    {
        $normalized = [];
        foreach ($columns as $index => $mapping) {
            $value = $values[$index] ?? '';
            $key = $mapping['normalized_key'];
            $normalized[$key] = $mapping['data_type'] === 'decimal'
                ? SalesDataNormalizer::normalizeDecimal($value)
                : SalesDataNormalizer::normalizePersianArabicDigits($value);
        }
        foreach (['invoice_date','effective_from','effective_to','attendance_date'] as $key) {
            $rawKey = $key . '_raw';
            if (array_key_exists($rawKey, $normalized)) $normalized[$key] = SalesDataNormalizer::normalizeDate($normalized[$rawKey]);
        }
        return $normalized;
    }

    private static function validateGeneric(string $source, array &$data, array $raw, array $batch): array
    {
        $errors = [];
        $definition = ImportSourceRegistry::get($source);
        foreach ($definition['mappings'] as $mapping) {
            if (empty($mapping['required'])) continue;
            $value = $data[$mapping['normalized_key']] ?? null;
            if ($value === null || trim((string)$value) === '') $errors[] = ['code'=>'required_field','message'=>'فیلد «'.$mapping['source_header'].'» الزامی است.'];
        }
        foreach ($definition['mappings'] as $mapping) {
            if ($mapping['data_type'] !== 'decimal') continue;
            $rawValue = self::rawForMapping($raw, $mapping['source_header']);
            if (trim((string)$rawValue) !== '' && ($data[$mapping['normalized_key']] ?? null) === null) {
                $errors[] = ['code'=>'invalid_number','message'=>'مقدار «'.$mapping['source_header'].'» عدد معتبر نیست.'];
            }
        }
        if (!empty($definition['snapshot_required']) && empty($batch['snapshot_date'])) {
            $errors[] = ['code'=>'snapshot_date_required','message'=>'تاریخ snapshot برای این منبع الزامی است.'];
        }
        if ($source === 'sales_targets') {
            try {
                $periodId = max(0, (int)($data['period_id'] ?? 0)) ?: max(0, (int)($batch['period_id'] ?? 0));
                if (!$periodId && (int)($data['target_year'] ?? 0) > 0 && (int)($data['target_month'] ?? 0) > 0) {
                    $legacyPeriod = Database::fetch(
                        "SELECT id FROM system_periods
                         WHERE is_active=1 AND period_type='monthly' AND jalali_year=? AND jalali_month=?
                         ORDER BY is_current DESC,id DESC LIMIT 1",
                        [(int)$data['target_year'],(int)$data['target_month']]
                    );
                    $periodId = (int)($legacyPeriod['id'] ?? 0);
                }
                $actor = Database::fetch('SELECT * FROM users WHERE id=? AND status="active"', [(int)($batch['started_by'] ?? 0)]);
                if (!$actor) throw new DomainException('کاربر آغازکننده Batch فعال نیست.');
                $context = SalesPlanningService::validateTargetContext(
                    $periodId,
                    (string)($data['visitor_code'] ?? ''),
                    (string)($data['line_code'] ?? ''),
                    (string)($data['product_code'] ?? ''),
                    $actor
                );
                $data['period_id'] = (int)$context['period']['id'];
                $data['visitor_user_id'] = (int)$context['visitor']['id'];
                $data['line_id'] = (int)$context['line']['id'];
                $data['line_code'] = (string)$context['line']['code'];
                $data['target_year'] = $context['period']['jalali_year'] ?: ($data['target_year'] ?? null);
                $data['target_month'] = $context['period']['jalali_month'] ?: ($data['target_month'] ?? null);
                $product = SalesPlanningService::productReference($context['productCode'], $periodId);
                $data['product_name'] = trim((string)($data['product_name'] ?? '')) ?: ($product['product_name'] ?? null);
                $data['brand_code'] = trim((string)($data['brand_code'] ?? '')) ?: ($product['brand_code'] ?? null);
                $data['brand_name'] = trim((string)($data['brand_name'] ?? '')) ?: ($product['brand_name'] ?? null);
            } catch (InvalidArgumentException|DomainException $e) {
                $errors[] = ['code'=>'invalid_target_scope','message'=>$e->getMessage()];
            }
            if (($data['target_quantity'] ?? null) === null && ($data['target_amount'] ?? null) === null) {
                $errors[] = ['code'=>'target_value_required','message'=>'حداقل یکی از تارگت تعداد یا مبلغ الزامی است.'];
            }
            foreach (['target_quantity'=>'هدف تعداد','target_amount'=>'هدف مبلغ'] as $key=>$label) {
                if (($data[$key] ?? null) !== null && (float)$data[$key] < 0) {
                    $errors[] = ['code'=>'negative_target','message'=>$label.' نمی‌تواند منفی باشد.'];
                }
            }
            if (($data['allocation_percent'] ?? null) !== null
                && ((float)$data['allocation_percent'] < 0 || (float)$data['allocation_percent'] > 100)) {
                $errors[] = ['code'=>'invalid_allocation_percent','message'=>'درصد تخصیص باید بین صفر تا صد باشد.'];
            }
        }
        if ($source === 'product_priorities') {
            $periodId = max(0, (int)($data['period_id'] ?? 0)) ?: max(0, (int)($batch['period_id'] ?? 0));
            try {
                SalesPlanningService::period($periodId);
                $data['period_id'] = $periodId;
            } catch (InvalidArgumentException $e) {
                $errors[] = ['code'=>'invalid_period','message'=>$e->getMessage()];
            }
            $priority = SalesPlanningService::normalizePriorityCode($data['priority_code'] ?? '');
            if (!$priority) {
                $errors[] = ['code'=>'invalid_priority','message'=>'اولویت باید یکی از P1، P2، P3 یا P4 باشد.'];
            } else {
                $data['priority_code'] = $priority;
                $data['priority_rank'] = (int)substr($priority, 1);
            }
            $status = SalesPlanningService::normalizeStatus($data['status'] ?? 'active');
            if (!$status) $errors[] = ['code'=>'invalid_status','message'=>'وضعیت اولویت باید فعال یا غیرفعال باشد.'];
            else $data['status'] = $status;
        }
        if ($source === 'customer_coefficients') {
            $periodId = max(0, (int)($data['period_id'] ?? 0)) ?: max(0, (int)($batch['period_id'] ?? 0));
            if (!$periodId && !empty($data['effective_from']) && !empty($data['effective_to'])) {
                $matchedPeriod = Database::fetch(
                    'SELECT id FROM system_periods
                     WHERE is_active=1 AND start_date=? AND end_date=?
                     ORDER BY is_current DESC,id DESC LIMIT 1',
                    [$data['effective_from'],$data['effective_to']]
                );
                $periodId = (int)($matchedPeriod['id'] ?? 0);
            }
            try {
                $period = SalesPlanningService::period($periodId);
                $data['period_id'] = $periodId;
                $data['effective_from'] = $data['effective_from'] ?? $period['start_date'];
                $data['effective_to'] = $data['effective_to'] ?? $period['end_date'];
            } catch (InvalidArgumentException $e) {
                $errors[] = ['code'=>'invalid_period','message'=>$e->getMessage()];
            }
            $code = trim((string)($data['customer_class_code'] ?? ''));
            $normalized = SalesPlanningService::normalizeGuildName($data['customer_class_title'] ?? '');
            if ($code === '' && $normalized === '') {
                $errors[] = ['code'=>'guild_identity_required','message'=>'کد صنف یا نام صنف الزامی است.'];
            } else {
                $data['normalized_guild_name'] = $normalized ?: null;
                $data['guild_identity_key'] = $code !== '' ? 'code:'.strtolower($code) : 'name:'.$normalized;
            }
            if (($data['coefficient'] ?? null) !== null && (float)$data['coefficient'] < 0) {
                $errors[] = ['code'=>'negative_coefficient','message'=>'ضریب صنف نمی‌تواند منفی باشد.'];
            }
        }
        if ($source === 'attendance') {
            if (empty($data['attendance_date']) && empty($batch['snapshot_date'])) $errors[] = ['code'=>'attendance_date_required','message'=>'تاریخ کارکرد در فایل یا فرم الزامی است.'];
            if (self::attendanceCode($data['kara_system_code'] ?? '') === '' && self::attendanceCode($data['employee_no'] ?? '') === '') {
                $errors[] = ['code'=>'attendance_identity_required','message'=>'کد سیستم کارا یا کد پرسنلی الزامی است.'];
            }
            $employee = self::resolveAttendanceEmployee($data);
            if (!$employee) {
                $errors[] = ['code'=>'employee_not_found','message'=>'کاربر فعال با ترتیب کد کارا، کد پرسنلی، نگاشت موجود یا نگاشت دستی پیدا نشد.'];
            } else {
                $data['employee_id'] = (int)$employee['id'];
                $data['identity_source'] = (string)$employee['identity_source'];
                $data['resolved_employee_no'] = $employee['employee_no'] ?? null;
                $data['resolved_kara_system_code'] = $employee['kara_system_code'] ?? null;
            }

            $timeNotes = [];
            foreach ([
                'approved_start_time'=>'ساعت شروع مصوب',
                'approved_end_time'=>'ساعت پایان مصوب',
                'actual_in_time'=>'ساعت ورود',
                'actual_out_time'=>'ساعت خروج',
            ] as $key=>$label) {
                try {
                    $normalizedTime = self::normalizeAttendanceClock($data[$key] ?? '');
                    $data[$key] = $normalizedTime['time'];
                    if ($normalizedTime['note']) $timeNotes[] = $label.': '.$normalizedTime['note'];
                } catch (InvalidArgumentException $e) {
                    $errors[] = ['code'=>'invalid_time','message'=>$label.' معتبر نیست: '.$e->getMessage()];
                }
            }
            foreach ([
                'late_minutes'=>['تأخیر','minutes'],
                'overtime_minutes'=>['اضافه‌کاری','minutes'],
                'work_minutes'=>['کارکرد','hours'],
                'work_difference_minutes'=>['اختلاف کارکرد','hours'],
                'daily_work_minutes'=>['ساعت کاری روزانه','hours'],
            ] as $key=>[$label,$unit]) {
                try {
                    $duration = self::normalizeAttendanceDuration($data[$key] ?? '', $unit);
                    $data[$key] = $duration['minutes'];
                    if ($duration['note']) $timeNotes[] = $label.': '.$duration['note'];
                } catch (InvalidArgumentException $e) {
                    $errors[] = ['code'=>'invalid_duration','message'=>$label.' معتبر نیست: '.$e->getMessage()];
                }
            }
            $data['import_time_notes'] = $timeNotes ? implode(' | ', $timeNotes) : null;

            $hasIn = !empty($data['actual_in_time']);
            $hasOut = !empty($data['actual_out_time']);
            if ($hasIn xor $hasOut) $errors[] = ['code'=>'incomplete_attendance_time','message'=>'ساعت ورود و خروج باید هر دو ثبت شوند.'];
            if ($hasIn && $hasOut) {
                $inMinutes = self::clockMinutes((string)$data['actual_in_time']);
                $outMinutes = self::clockMinutes((string)$data['actual_out_time']);
                if ($outMinutes < $inMinutes) {
                    $errors[] = ['code'=>'invalid_time_order','message'=>'ساعت خروج نمی‌تواند قبل از ساعت ورود باشد.'];
                } else {
                    $data['work_minutes'] = $outMinutes - $inMinutes;
                }
            }

            $mission = self::truthy($data['mission_value'] ?? '');
            $leave = self::truthy($data['leave_value'] ?? '');
            if ($mission && $leave) $errors[] = ['code'=>'conflicting_day_status','message'=>'یک ردیف نمی‌تواند هم‌زمان مرخصی و مأموریت باشد.'];
            if (($mission || $leave) && ($hasIn || $hasOut)) {
                $errors[] = ['code'=>'status_time_conflict','message'=>'مرخصی و مأموریت نباید ساعت ورود یا خروج داشته باشند.'];
            }
            if ($leave && trim((string)($data['leave_type'] ?? '')) === '') {
                $errors[] = ['code'=>'leave_type_required','message'=>'نوع مرخصی برای ردیف مرخصی الزامی است.'];
            }
            if ($mission && trim((string)($data['mission_details'] ?? '')) === '') {
                $errors[] = ['code'=>'mission_details_required','message'=>'شرح مأموریت برای ردیف مأموریت الزامی است.'];
            }
            $data['day_status'] = $mission ? 'mission' : ($leave ? 'leave' : (($hasIn&&$hasOut)?'present':'absent'));

            if ($employee && !empty($data['attendance_date'])) {
                $groupCode = trim((string)($employee['sales_line'] ?? '')) !== '' || (int)($employee['is_sales_role'] ?? 0) === 1
                    ? 'SALES'
                    : 'ADMIN_WAREHOUSE';
                $data['work_group_code'] = $groupCode;
                $holiday = self::attendanceHoliday((string)$data['attendance_date'], $groupCode);
                if ($holiday) {
                    $data['holiday_id'] = (int)$holiday['id'];
                    $data['holiday_title'] = (string)$holiday['title'];
                    if ($hasIn && $hasOut && !$mission && !$leave) {
                        $data['day_status'] = 'holiday_work';
                    } elseif (!$hasIn && !$hasOut && !$mission && !$leave) {
                        $data['day_status'] = 'holiday';
                        $data['skip_derived_holiday'] = 1;
                    } else {
                        $errors[] = ['code'=>'holiday_status_conflict','message'=>'تعطیلی از تقویم استخراج می‌شود؛ فقط حضور واقعی در تعطیل قابل ثبت است.'];
                    }
                }
            }
        }
        return $errors;
    }

    private static function sourceKey(string $source, array $data, array $batch): string
    {
        $parts = match ($source) {
            'purchase_aggregate' => trim((string)($data['source_row_id'] ?? '')) !== ''
                ? ['source_row_id','invoice_number','supplier_code','invoice_date_raw']
                : ['supplier_invoice_number','invoice_number','invoice_type','product_code','supplier_code','line_code','invoice_date_raw','quantity','gross_amount','net_amount'],
            'sales_targets' => ['period_id','visitor_user_id','line_id','product_code'],
            'product_priorities' => ['period_id','product_code'],
            'customer_coefficients' => ['period_id','guild_identity_key'],
            'attendance' => ['employee_id','attendance_date'],
            default => array_keys($data),
        };
        $values = [];
        foreach ($parts as $part) $values[] = trim((string)($data[$part] ?? ($part === 'attendance_date' ? ($batch['snapshot_date'] ?? '') : '')));
        return hash('sha256', $source . '|' . implode('|', $values));
    }

    private static function insertStaging(int $batchId, array $candidate, int $rowNumber, array $raw, array $normalized, string $status, array $errors, string $sourceKey): void
    {
        $rawJson = json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $normalizedJson = json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $rowHash = hash('sha256', $candidate['source_module'].'|'.$candidate['sheet_name'].'|'.($candidate['table_name'] ?? '').'|'.$rowNumber.'|'.$rawJson);
        Database::execute(
            'INSERT INTO staging_sales_data(import_batch_id,source_module,`row_number`,source_row_number,source_sheet,source_table,source_row_hash,raw_json,normalized_json,validation_status,validation_errors_json,source_unique_key,created_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NOW())',
            [$batchId,$candidate['source_module'],$rowNumber,$rowNumber,$candidate['sheet_name'],$candidate['table_name'],$rowHash,$rawJson,$normalizedJson,$status,$errors?json_encode($errors,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES):null,$sourceKey]
        );
        $id = (int)Database::lastInsertId();
        SalesReferenceRepository::mirrorStagingRow($id,$batchId,$candidate['source_module'],$rowNumber,$raw,$normalized,$status,$errors,$sourceKey);
        Database::execute('UPDATE staging_sales_reference_rows SET source_sheet=?,source_table=?,source_row_hash=? WHERE id=?', [$candidate['sheet_name'],$candidate['table_name'],$rowHash,$id]);
        foreach ($errors as $error) {
            Database::execute(
                'INSERT INTO sales_import_errors(import_batch_id,source_module,`row_number`,error_code,error_message,raw_json,normalized_json,created_at)
                 VALUES (?,?,?,?,?,?,?,NOW())',
                [$batchId,$candidate['source_module'],$rowNumber,$error['code']??null,$error['message'],$rawJson,$normalizedJson]
            );
            SalesReferenceRepository::mirrorError($batchId,$candidate['source_module'],$rowNumber,$error['code']??null,(string)$error['message'],$raw,$normalized);
        }
    }

    private static function commitRow(array $batch, string $sourceKey, array $data, array $raw, int $actorId): array
    {
        return match ($batch['source_module']) {
            'purchase_aggregate' => self::commitPurchase($batch, $sourceKey, $data, $raw),
            'sales_targets' => self::commitTarget($batch, $sourceKey, $data, $raw),
            'product_priorities' => self::commitPriority($batch, $sourceKey, $data, $raw),
            'customer_coefficients' => self::commitCoefficient($batch, $sourceKey, $data, $raw),
            'attendance' => self::commitAttendance($batch, $data, $actorId),
            default => throw new InvalidArgumentException('مصرف‌کننده نهایی برای این نوع منبع تعریف نشده است.'),
        };
    }

    private static function commitPurchase(array $batch, string $key, array $data, array $raw): array
    {
        $batchId = (int)$batch['id'];
        $values = [
            $batchId,$data['invoice_type']??null,$data['invoice_number']??null,$data['invoice_date_raw']??null,
            $data['invoice_date']??null,$data['supplier_code']??null,$data['supplier_name']??null,
            $data['manufacturer_code']??null,$data['manufacturer_name']??null,$data['line_code']??null,
            $data['line_name']??null,$data['product_code']??null,$data['product_name']??null,
            $data['quantity']??null,$data['gross_amount']??null,$data['discount_amount']??null,
            $data['net_amount']??null,$data['brand_code']??null,$data['brand_name']??null,
            json_encode($raw,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
        ];
        if (($batch['import_mode'] ?? '') === 'update_existing') {
            $existing = Database::fetch(
                'SELECT id FROM purchase_aggregate_rows WHERE source_unique_key=? ORDER BY id DESC LIMIT 1 FOR UPDATE',
                [$key]
            );
            if ($existing) {
                Database::execute(
                    'UPDATE purchase_aggregate_rows
                     SET import_batch_id=?,invoice_type=?,invoice_number=?,invoice_date_raw=?,invoice_date=?,
                         supplier_code=?,supplier_name=?,manufacturer_code=?,manufacturer_name=?,line_code=?,line_name=?,
                         product_code=?,product_name=?,quantity=?,gross_amount=?,discount_amount=?,net_amount=?,
                         brand_code=?,brand_name=?,raw_json=?,updated_at=NOW()
                     WHERE id=?',
                    [...$values,(int)$existing['id']]
                );
                return [false,true];
            }
        }
        Database::execute(
            'INSERT INTO purchase_aggregate_rows(import_batch_id,source_unique_key,invoice_type,invoice_number,invoice_date_raw,invoice_date,supplier_code,supplier_name,manufacturer_code,manufacturer_name,line_code,line_name,product_code,product_name,quantity,gross_amount,discount_amount,net_amount,brand_code,brand_name,raw_json,created_at,updated_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())',
            [$batchId,$key,...array_slice($values,1)]
        );
        return [true,false];
    }

    private static function commitTarget(array $batch, string $key, array $data, array $raw): array
    {
        $batchId = (int)$batch['id'];
        if (($batch['import_mode'] ?? '') === 'update_existing') {
            $existing = Database::fetch('SELECT id FROM sales_targets WHERE source_unique_key=? ORDER BY id DESC LIMIT 1 FOR UPDATE', [$key]);
            if ($existing) {
                Database::execute(
                    'UPDATE sales_targets SET import_batch_id=?,period_id=?,visitor_user_id=?,line_id=?,target_year=?,target_month=?,
                        line_code=?,product_code=?,product_name=?,brand_code=?,brand_name=?,priority_code=?,visitor_code=?,supervisor_code=?,
                        target_quantity=?,target_amount=?,allocation_percent=?,active=1,source_type="import",raw_json=?,updated_at=NOW()
                     WHERE id=?',
                    [$batchId,$data['period_id']??null,$data['visitor_user_id']??null,$data['line_id']??null,$data['target_year']??null,$data['target_month']??null,$data['line_code']??null,$data['product_code']??null,$data['product_name']??null,$data['brand_code']??null,$data['brand_name']??null,$data['priority_code']??null,$data['visitor_code']??null,$data['supervisor_code']??null,$data['target_quantity']??null,$data['target_amount']??null,$data['allocation_percent']??null,json_encode($raw,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),(int)$existing['id']]
                );
                return [false,true];
            }
        }
        Database::execute(
            'INSERT INTO sales_targets(import_batch_id,source_unique_key,period_id,visitor_user_id,line_id,target_year,target_month,line_code,
                product_code,product_name,brand_code,brand_name,priority_code,visitor_code,supervisor_code,target_quantity,target_amount,
                allocation_percent,active,source_type,raw_json,created_at,updated_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1,"import",?,NOW(),NOW())',
            [$batchId,$key,$data['period_id']??null,$data['visitor_user_id']??null,$data['line_id']??null,$data['target_year']??null,$data['target_month']??null,$data['line_code']??null,$data['product_code']??null,$data['product_name']??null,$data['brand_code']??null,$data['brand_name']??null,$data['priority_code']??null,$data['visitor_code']??null,$data['supervisor_code']??null,$data['target_quantity']??null,$data['target_amount']??null,$data['allocation_percent']??null,json_encode($raw,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]
        );
        return [true,false];
    }

    private static function commitPriority(array $batch, string $key, array $data, array $raw): array
    {
        $batchId = (int)$batch['id'];
        if (($batch['import_mode'] ?? '') === 'update_existing') {
            $existing = Database::fetch('SELECT id FROM product_priorities WHERE source_unique_key=? ORDER BY id DESC LIMIT 1 FOR UPDATE', [$key]);
            if ($existing) {
                Database::execute(
                    'UPDATE product_priorities SET import_batch_id=?,period_id=?,product_code=?,product_name=?,brand_code=?,brand_name=?,
                        priority_code=?,priority_rank=?,inventory_quantity=?,inventory_value=?,status=?,active=IF(?="active",1,0),
                        raw_json=?,updated_at=NOW() WHERE id=?',
                    [$batchId,$data['period_id']??null,$data['product_code']??null,$data['product_name']??null,$data['brand_code']??null,$data['brand_name']??null,$data['priority_code']??null,$data['priority_rank']??null,$data['inventory_quantity']??null,$data['inventory_value']??null,$data['status']??'active',$data['status']??'active',json_encode($raw,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),(int)$existing['id']]
                );
                return [false,true];
            }
        }
        Database::execute(
            'INSERT INTO product_priorities(import_batch_id,source_unique_key,period_id,product_code,product_name,brand_code,brand_name,priority_code,priority_rank,inventory_quantity,inventory_value,status,active,raw_json,created_at,updated_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?, ?,IF(?="active",1,0),?,NOW(),NOW())',
            [$batchId,$key,$data['period_id']??null,$data['product_code']??null,$data['product_name']??null,$data['brand_code']??null,$data['brand_name']??null,$data['priority_code']??null,$data['priority_rank']??null,$data['inventory_quantity']??null,$data['inventory_value']??null,$data['status']??'active',$data['status']??'active',json_encode($raw,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]
        );
        return [true,false];
    }

    private static function commitCoefficient(array $batch, string $key, array $data, array $raw): array
    {
        $batchId = (int)$batch['id'];
        $version = (int)(Database::fetch(
            'SELECT COALESCE(MAX(version_no),0)+1 next_version
             FROM sales_customer_class_coefficients
             WHERE guild_identity_key=? AND period_id=? FOR UPDATE',
            [$data['guild_identity_key']??null,$data['period_id']??null]
        )['next_version'] ?? 1);
        Database::execute(
            'INSERT INTO sales_customer_class_coefficients(import_batch_id,source_unique_key,period_id,guild_identity_key,customer_class_code,customer_class_title,normalized_guild_name,coefficient,effective_from,effective_to,version_no,source_type,active,raw_json,created_at,updated_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,"import",1,?,NOW(),NOW())',
            [$batchId,$key,$data['period_id']??null,$data['guild_identity_key']??null,$data['customer_class_code']??null,$data['customer_class_title']??null,$data['normalized_guild_name']??null,$data['coefficient']??null,$data['effective_from']??null,$data['effective_to']??null,$version,json_encode($raw,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]
        );
        return [true,false];
    }

    private static function commitAttendance(array $batch, array $data, int $actorId): array
    {
        if (!empty($data['skip_derived_holiday'])) return [false,false,true];
        $employee = Database::fetch(
            'SELECT u.id,u.sales_line,u.employee_no,u.kara_system_code,COALESCE(r.is_sales_role,0) is_sales_role
             FROM users u LEFT JOIN org_roles r ON r.id=u.org_role_id
             WHERE u.id=? AND u.status="active" LIMIT 1',
            [(int)($data['employee_id'] ?? 0)]
        );
        if (!$employee) throw new DomainException('کاربر کارکرد پیدا نشد.');
        $groupCode = in_array($data['work_group_code'] ?? '', ['SALES','ADMIN_WAREHOUSE'], true)
            ? (string)$data['work_group_code']
            : (trim((string)($employee['sales_line'] ?? '')) !== '' || (int)$employee['is_sales_role'] === 1 ? 'SALES' : 'ADMIN_WAREHOUSE');
        $group = Database::fetch('SELECT id FROM hr_work_groups WHERE code=? AND active=1 LIMIT 1', [$groupCode]);
        if (!$group) throw new DomainException('گروه کاری فعال پیدا نشد.');
        $date = $data['attendance_date'] ?? $batch['snapshot_date'] ?? null;
        if (!$date) throw new DomainException('تاریخ کارکرد مشخص نیست.');
        $existing = Database::fetch('SELECT id FROM hr_attendance_entries WHERE employee_id=? AND attendance_date=? FOR UPDATE', [(int)$employee['id'],$date]);
        $dayStatus = in_array($data['day_status'] ?? '', ['present','absent','leave','mission','holiday_work'], true)
            ? (string)$data['day_status']
            : 'absent';
        $in = in_array($dayStatus, ['present','holiday_work'], true) ? ($data['actual_in_time'] ?? null) : null;
        $out = in_array($dayStatus, ['present','holiday_work'], true) ? ($data['actual_out_time'] ?? null) : null;
        $approvedStart = $data['approved_start_time'] ?? null;
        $approvedEnd = $data['approved_end_time'] ?? null;
        $late = $dayStatus === 'present' ? max(0,(int)($data['late_minutes'] ?? 0)) : 0;
        $normalOvertime = $dayStatus === 'present' ? max(0,(int)($data['overtime_minutes'] ?? 0)) : 0;
        $work = ($in&&$out) ? max(0,self::clockMinutes((string)$out)-self::clockMinutes((string)$in)) : 0;
        $holidayOvertime = $dayStatus === 'holiday_work' ? $work : 0;
        $overtime = $normalOvertime + $holidayOvertime;
        $holidayId = max(0,(int)($data['holiday_id'] ?? 0)) ?: null;
        $leaveType = $dayStatus === 'leave' ? self::text($data['leave_type'] ?? '',100) : '';
        $missionDetails = $dayStatus === 'mission' ? self::text($data['mission_details'] ?? '',5000) : '';
        $timeNotes = self::text($data['import_time_notes'] ?? '',5000);
        if ($existing) {
            Database::execute(
                'UPDATE hr_attendance_entries SET import_batch_id=?,work_group_id=?,is_holiday=?,holiday_id=?,approved_start_time=?,approved_end_time=?,actual_in_time=?,actual_out_time=?,late_minutes=?,early_leave_minutes=0,normal_overtime_minutes=?,holiday_overtime_minutes=?,work_minutes=?,day_status=?,leave_type=?,mission_details=?,overtime_status=?,import_time_notes=?,updated_at=NOW() WHERE id=?',
                [(int)$batch['id'],(int)$group['id'],$dayStatus==='holiday_work'?1:0,$holidayId,$approvedStart,$approvedEnd,$in,$out,$late,$normalOvertime,$holidayOvertime,$work,$dayStatus,$leaveType?:null,$missionDetails?:null,$overtime>0?'pending':'none',$timeNotes?:null,(int)$existing['id']]
            );
            return [false,true];
        }
        Database::execute(
            'INSERT INTO hr_attendance_entries(import_batch_id,employee_id,work_group_id,attendance_date,is_holiday,holiday_id,approved_start_time,approved_end_time,actual_in_time,actual_out_time,late_minutes,early_leave_minutes,normal_overtime_minutes,holiday_overtime_minutes,work_minutes,day_status,leave_type,mission_details,overtime_status,import_time_notes,created_by,created_at,updated_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,0,?,?,?,?,?,?,?,?,?,NOW(),NOW())',
            [(int)$batch['id'],(int)$employee['id'],(int)$group['id'],$date,$dayStatus==='holiday_work'?1:0,$holidayId,$approvedStart,$approvedEnd,$in,$out,$late,$normalOvertime,$holidayOvertime,$work,$dayStatus,$leaveType?:null,$missionDetails?:null,$overtime>0?'pending':'none',$timeNotes?:null,$actorId]
        );
        return [true,false];
    }

    private static function createBatch(array $data): int
    {
        Database::execute(
            'INSERT INTO sales_import_batches(source_type,source_module,file_name,stored_file_path,file_hash,detected_sheet,detected_table,detected_range,source_confidence,period_key,snapshot_date,period_id,import_mode,status,pipeline_status,started_by,started_at,metadata_json,created_at,updated_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),?,NOW(),NOW())',
            [$data['source_type'],$data['source_module'],$data['file_name'],$data['stored_file_path'],$data['file_hash'],$data['detected_sheet'],$data['detected_table'],$data['detected_range'],$data['source_confidence'],$data['period_key'],$data['snapshot_date'],$data['period_id'],$data['import_mode'],$data['status'],$data['pipeline_status'],$data['started_by'],$data['metadata_json']]
        );
        $id = (int)Database::lastInsertId();
        SalesReferenceRepository::mirrorBatchFromLegacy($id);
        return $id;
    }

    private static function setReadyStatus(int $batchId, array $summary): void
    {
        $status = (int)($summary['ready_rows'] ?? 0) > 0 ? 'ready_to_commit' : 'validation_failed';
        Database::execute('UPDATE sales_import_batches SET pipeline_status=?,status="preview",updated_at=NOW() WHERE id=?', [$status,$batchId]);
        SalesReferenceRepository::mirrorBatchFromLegacy($batchId);
        self::setPipelineStatus($batchId, $status);
    }

    private static function setPipelineStatus(int $batchId, string $status, array $metadataPatch = []): void
    {
        if ($metadataPatch) {
            $row = Database::fetch('SELECT metadata_json FROM sales_import_batches WHERE id=?', [$batchId]);
            $metadata = json_decode((string)($row['metadata_json'] ?? ''), true) ?: [];
            $metadata = array_replace_recursive($metadata, $metadataPatch);
            Database::execute('UPDATE sales_import_batches SET pipeline_status=?,metadata_json=?,updated_at=NOW() WHERE id=?', [$status,json_encode($metadata,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),$batchId]);
        } else {
            Database::execute('UPDATE sales_import_batches SET pipeline_status=?,updated_at=NOW() WHERE id=?', [$status,$batchId]);
        }
        Database::execute('UPDATE sales_reference_import_batches SET pipeline_status=?,updated_at=NOW() WHERE id=?', [$status,$batchId]);
    }

    private static function markFailed(int $batchId): void
    {
        try {
            Database::execute('UPDATE sales_import_batches SET status="failed",pipeline_status="rejected",error_message="پردازش فایل انجام نشد.",finished_at=NOW(),updated_at=NOW() WHERE id=?', [$batchId]);
            SalesReferenceRepository::mirrorBatchFromLegacy($batchId);
            self::setPipelineStatus($batchId, 'rejected');
        } catch (Throwable $error) {
            error_log('Unified import failure log: ' . $error->getMessage());
        }
    }

    private static function alreadyImported(string $fileHash, string $sourceModule): bool
    {
        return Database::fetch(
            'SELECT id FROM sales_import_batches WHERE file_hash=? AND source_module=? AND pipeline_status IN("committed","activated") LIMIT 1',
            [$fileHash,$sourceModule]
        ) !== null;
    }

    private static function finalExists(string $table, string $sourceKey): bool
    {
        $allowed = array_column(ImportSourceRegistry::all(), 'target_table');
        if (!in_array($table, $allowed, true) || $table === 'hr_attendance_entries') return false;
        return Database::fetch("SELECT id FROM `{$table}` WHERE source_unique_key=? LIMIT 1", [$sourceKey]) !== null;
    }

    private static function headerConfidence(array $headers, array $signature): float
    {
        $normalized = array_flip(SalesDataNormalizer::normalizeHeaders($headers));
        $matched = 0;
        foreach ($signature as $header) if (isset($normalized[SalesDataNormalizer::normalizeHeader($header)])) $matched++;
        return $signature ? $matched / count($signature) : 0.0;
    }

    private static function sourceHeaderConfidence(array $headers, array $source): float
    {
        $signatures = $source['signatures'] ?? [$source['signature'] ?? []];
        $confidence = 0.0;
        foreach ($signatures as $signature) {
            $confidence = max(
                $confidence,
                self::headerConfidence($headers, is_array($signature) ? $signature : [])
            );
        }
        return $confidence;
    }

    private static function candidate(string $source, array $sheet, array $rows, string $detection, string $table, string $ref, float $score, float $confidence): array
    {
        $identity = $source.'|'.($sheet['name']??'').'|'.$table.'|'.$ref;
        return [
            'key'=>hash('sha256',$identity),
            'source_module'=>$source,
            'sheet_name'=>(string)($sheet['name']??''),
            'table_name'=>$table ?: null,
            'detection'=>$detection,
            'ref'=>$ref ?: null,
            'headers'=>$rows[0]??[],
            'rows'=>$rows,
            'stream'=>$sheet['stream']??null,
            'score'=>round($score,2),
            'confidence'=>round($confidence*100,2),
        ];
    }

    private static function candidateMetadata(array $candidate): array
    {
        return [
            'key'=>$candidate['key'],'source_module'=>$candidate['source_module'],'source_title'=>ImportSourceRegistry::get($candidate['source_module'])['title'],
            'sheet_name'=>$candidate['sheet_name'],'table_name'=>$candidate['table_name'],'detection'=>$candidate['detection'],
            'ref'=>$candidate['ref'],'headers'=>$candidate['headers'],'score'=>$candidate['score'],'confidence'=>$candidate['confidence'],
        ];
    }

    private static function refreshBatchSummary(int $batchId): void
    {
        $counts = Database::fetch(
            'SELECT COUNT(*) total_rows,
                    SUM(validation_status="valid") valid_rows,
                    SUM(validation_status="invalid") invalid_rows,
                    SUM(validation_status="duplicate") duplicate_rows
             FROM staging_sales_data WHERE import_batch_id=?',
            [$batchId]
        ) ?: [];
        $ready = (int)($counts['valid_rows'] ?? 0);
        $pipeline = $ready > 0 ? 'ready_to_commit' : 'validation_failed';
        Database::execute(
            'UPDATE sales_import_batches
             SET total_rows=?,valid_rows=?,invalid_rows=?,duplicate_rows=?,status="preview",pipeline_status=?,updated_at=NOW()
             WHERE id=?',
            [
                (int)($counts['total_rows']??0),
                $ready,
                (int)($counts['invalid_rows']??0),
                (int)($counts['duplicate_rows']??0),
                $pipeline,
                $batchId,
            ]
        );
        SalesReferenceRepository::mirrorBatchFromLegacy($batchId);
        self::setPipelineStatus($batchId,$pipeline);
    }

    private static function attendanceExternalIdentity(array $data): array
    {
        $kara = self::attendanceCode($data['kara_system_code'] ?? '');
        if ($kara !== '') return ['kara_system_code',$kara];
        return ['employee_no',self::attendanceCode($data['employee_no'] ?? '')];
    }

    private static function resolveAttendanceEmployee(array $data): ?array
    {
        $kara = self::attendanceCode($data['kara_system_code'] ?? '');
        $personnel = self::attendanceCode($data['employee_no'] ?? '');
        if ($kara !== '') {
            $row = Database::fetch(
                'SELECT u.id,u.employee_no,u.kara_system_code,u.sales_line,COALESCE(r.is_sales_role,0) is_sales_role
                 FROM users u LEFT JOIN org_roles r ON r.id=u.org_role_id
                 WHERE u.kara_system_code=? AND u.status="active" LIMIT 1',
                [$kara]
            );
            if ($row) return $row + ['identity_source'=>'kara_system_code'];
        }
        if ($personnel !== '') {
            $row = Database::fetch(
                'SELECT u.id,u.employee_no,u.kara_system_code,u.sales_line,COALESCE(r.is_sales_role,0) is_sales_role
                 FROM users u LEFT JOIN org_roles r ON r.id=u.org_role_id
                 WHERE u.employee_no=? AND u.status="active" LIMIT 1',
                [$personnel]
            );
            if ($row) return $row + ['identity_source'=>'employee_no'];
        }
        foreach (array_values(array_unique(array_filter([$personnel,$kara]))) as $code) {
            try {
                $row = Database::fetch(
                    'SELECT u.id,u.employee_no,u.kara_system_code,u.sales_line,COALESCE(r.is_sales_role,0) is_sales_role
                     FROM sales_team_members m
                     JOIN users u ON u.id=m.user_id AND u.status="active"
                     LEFT JOIN org_roles r ON r.id=u.org_role_id
                     WHERE m.personnel_code=? AND m.active=1
                     ORDER BY m.id DESC LIMIT 1',
                    [$code]
                );
                if ($row) return $row + ['identity_source'=>'existing_employee_mapping'];
            } catch (Throwable) {
            }
        }
        foreach ([['kara_system_code',$kara],['employee_no',$personnel]] as [$field,$code]) {
            if ($code === '') continue;
            $row = Database::fetch(
                'SELECT u.id,u.employee_no,u.kara_system_code,u.sales_line,COALESCE(r.is_sales_role,0) is_sales_role
                 FROM hr_attendance_identity_mappings m
                 JOIN users u ON u.id=m.user_id AND u.status="active"
                 LEFT JOIN org_roles r ON r.id=u.org_role_id
                 WHERE m.source_field=? AND m.external_code=? AND m.active=1 LIMIT 1',
                [$field,$code]
            );
            if ($row) return $row + ['identity_source'=>'manual_mapping'];
        }
        return null;
    }

    private static function attendanceCode(mixed $value): string
    {
        return self::text(SalesDataNormalizer::normalizePersianArabicDigits($value),100);
    }

    private static function attendanceHoliday(string $date,string $groupCode): ?array
    {
        $scope=$groupCode==='SALES'?'sales':'admin_warehouse';
        return Database::fetch(
            'SELECT id,title,is_half_day FROM hr_month_holidays
             WHERE holiday_date=? AND active=1 AND applies_to_group IN("all",?)
             ORDER BY applies_to_group="all" ASC,id DESC LIMIT 1',
            [$date,$scope]
        ) ?: null;
    }

    private static function clockMinutes(string $time): int
    {
        [$hours,$minutes]=array_pad(array_map('intval',explode(':',$time)),2,0);
        return ($hours*60)+$minutes;
    }

    private static function rawForMapping(array $raw, string $header): mixed
    {
        $needle = SalesDataNormalizer::normalizeHeader($header);
        foreach ($raw as $key => $value) if (SalesDataNormalizer::normalizeHeader($key) === $needle) return $value;
        return null;
    }

    private static function optionalDate(mixed $value): ?string
    {
        $value = trim((string)$value);
        if ($value === '') return null;
        $date = SalesDataNormalizer::normalizeDate($value);
        if (!$date) throw new InvalidArgumentException('تاریخ snapshot معتبر نیست.');
        return $date;
    }

    private static function text(mixed $value, int $max): string
    {
        return mb_substr(trim((string)$value), 0, $max, 'UTF-8');
    }

    private static function timeValue(mixed $value): ?string
    {
        $value = SalesDataNormalizer::normalizePersianArabicDigits($value);
        if ($value === '') return null;
        if (is_numeric($value) && (float)$value >= 0 && (float)$value < 1) {
            $minutes = (int)round((float)$value * 1440);
            return sprintf('%02d:%02d:00', intdiv($minutes, 60) % 24, $minutes % 60);
        }
        if (!preg_match('/^(?:[01]?\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', $value)) return null;
        $parts = explode(':', $value);
        return sprintf('%02d:%02d:00', (int)$parts[0], (int)$parts[1]);
    }

    private static function minutesValue(mixed $value): int
    {
        $value = SalesDataNormalizer::normalizePersianArabicDigits($value);
        if ($value === '') return 0;
        if (str_contains($value, ':')) {
            [$hours, $minutes] = array_pad(array_map('intval', explode(':', $value)), 2, 0);
            return max(0, $hours * 60 + $minutes);
        }
        if (!is_numeric($value)) return 0;
        $number = (float)$value;
        return $number > 0 && $number < 1 ? (int)round($number * 1440) : max(0, (int)round($number));
    }

    private static function truthy(mixed $value): bool
    {
        $value = SalesDataNormalizer::normalizeHeader($value);
        return $value !== '' && !in_array($value, ['0','خیر','نه','false','none'], true);
    }

    private static function assertSourcePermission(string $source, string $action): void
    {
        if (in_array($source, ['sales_targets','product_priorities','customer_coefficients'], true)) {
            $allowed = Auth::isAdmin()
                || Auth::can('sales_planning.manage', $action === 'commit' ? 'edit' : 'create')
                || Auth::can('sales_planning.manage')
                || Auth::can('sales_data_import', $action === 'commit' ? 'edit' : 'create')
                || Auth::can('sales_data_import');
            if (!$allowed) throw new DomainException('ورود اهداف و تنظیمات برنامه فروش فقط برای کاربران دارای مجوز مدیریت برنامه فروش مجاز است.');
            return;
        }
        if ($source !== 'attendance') return;
        $allowed = Auth::isAdmin()
            || Auth::can('hr_attendance', $action === 'commit' ? 'edit' : 'create')
            || Auth::can('hr_attendance.settings', 'edit');
        if (!$allowed) throw new DomainException('ورود کارکرد فقط برای کاربران دارای مجوز منابع انسانی مجاز است.');
    }
}
