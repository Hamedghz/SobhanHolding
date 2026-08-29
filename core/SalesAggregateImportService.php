<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/SalesAggregateRepository.php';
require_once __DIR__ . '/SalesDataNormalizer.php';
require_once __DIR__ . '/SalesReferenceRepository.php';
require_once __DIR__ . '/SpreadsheetImportReader.php';

class SalesAggregateImportService
{
    public const SOURCE_MODULE = 'sales_aggregate';
    public const MODES = ['replace_reference','append','update_existing','skip_duplicates','fail_on_duplicate'];
    private const MAX_FILE_SIZE = 26214400;
    private const MAX_UNCOMPRESSED_SIZE = 104857600;
    private const MAX_ROWS = 100000;

    public static function readUploadedFile(array $file, string $importMode, int $actorId, ?string $periodKey = null): array
    {
        if (!in_array($importMode, self::MODES, true)) $importMode = 'replace_reference';
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new InvalidArgumentException('فایل بارگذاری‌شده معتبر نیست.');
        $tmp = (string)($file['tmp_name'] ?? '');
        $isUploaded = is_uploaded_file($tmp) || (PHP_SAPI === 'cli' && is_file($tmp));
        if (!$isUploaded) throw new InvalidArgumentException('فایل بارگذاری‌شده معتبر نیست.');
        $size = (int)($file['size'] ?? filesize($tmp));
        if ($size < 1 || $size > self::MAX_FILE_SIZE) throw new InvalidArgumentException('حجم فایل باید کمتر از ۲۵ مگابایت باشد.');
        $originalName = mb_substr(basename((string)($file['name'] ?? 'data')), 0, 255);
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($extension, ['xlsx','csv'], true)) throw new InvalidArgumentException('فقط فایل XLSX یا CSV مجاز است.');
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp) ?: '';
        $allowed = $extension === 'xlsx'
            ? ['application/zip','application/x-zip-compressed','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','application/octet-stream']
            : ['text/plain','text/csv','application/csv','application/vnd.ms-excel','application/octet-stream'];
        if (!in_array($mime, $allowed, true)) throw new InvalidArgumentException('نوع محتوای فایل با پسوند آن سازگار نیست.');

        $directory = self::storageDirectory();
        $storedName = bin2hex(random_bytes(20)) . '.' . $extension;
        $path = $directory . DIRECTORY_SEPARATOR . $storedName;
        $moved = is_uploaded_file($tmp) ? move_uploaded_file($tmp, $path) : copy($tmp, $path);
        if (!$moved) throw new RuntimeException('ذخیره امن فایل انجام نشد.');
        @chmod($path, 0640);

        $batchId = 0;
        try {
            $workbook = self::readStoredFile($path, $extension);
            $candidates = self::detectWorkbookSource($workbook);
            if (!$candidates) throw new InvalidArgumentException('جدول، شیت یا سرستون‌های معتبر فروش تجمیعی پیدا نشد.');
            $metadata = [
                'stored_name' => $storedName, 'extension' => $extension, 'mime' => $mime,
                'candidates' => array_map([self::class, 'candidateMetadata'], $candidates),
            ];
            $selected = count($candidates) === 1 ? $candidates[0] : null;
            $batchId = SalesAggregateRepository::createBatch([
                'source_type' => $extension === 'xlsx' ? 'excel_upload' : 'csv_upload',
                'file_name' => $originalName, 'file_hash' => hash_file('sha256', $path),
                'detected_sheet' => $selected['sheet_name'] ?? null, 'detected_table' => $selected['table_name'] ?? null,
                'detected_range' => $selected['ref'] ?? null,
                'import_mode' => $importMode, 'status' => $selected ? 'uploaded' : 'awaiting_source_selection',
                'started_by' => $actorId, 'period_key' => self::cleanPeriodKey($periodKey),
                'metadata_json' => json_encode($metadata, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
            ]);
            SalesReferenceRepository::setPeriodKey($batchId, self::cleanPeriodKey($periodKey));
            if ($selected) {
                $summary = self::storeToStaging($batchId, $selected);
                return ['batch_id'=>$batchId,'needs_selection'=>false,'summary'=>$summary];
            }
            return ['batch_id'=>$batchId,'needs_selection'=>true,'candidates'=>$metadata['candidates']];
        } catch (Throwable $e) {
            @unlink($path);
            if ($batchId > 0) {
                try { SalesAggregateRepository::finishBatch($batchId,'failed',0,0,0,'پردازش فایل انجام نشد.'); }
                catch (Throwable $logError) { error_log('Sales aggregate batch failure log: '.$logError->getMessage()); }
            }
            throw $e;
        }
    }

    public static function detectWorkbookSource(array $workbook): array
    {
        $tableMatches = [];
        $sheetMatches = [];
        $headerMatches = [];
        foreach ($workbook['sheets'] ?? [] as $sheet) {
            if (!($sheet['visible'] ?? true)) continue;
            foreach ($sheet['tables'] ?? [] as $table) {
                if (in_array(SalesDataNormalizer::normalizeHeader($table['name'] ?? ''), ['tbltajmii','tbltajmi','tblsales','tblsales_raw'], true)) {
                    $rows = self::cropRows($sheet['rows'], (string)($table['ref'] ?? ''));
                    $tableMatches[] = self::candidate($sheet, $rows, 'table', (string)$table['name'], (string)($table['ref'] ?? ''));
                }
            }
            if (in_array(SalesDataNormalizer::normalizeHeader($sheet['name'] ?? ''), [SalesDataNormalizer::normalizeHeader(SalesDataNormalizer::SALES_AGGREGATE_SHEET),SalesDataNormalizer::normalizeHeader('تجمیعی')], true)) {
                $sheetMatches[] = self::candidate($sheet, $sheet['rows'] ?? [], 'sheet');
            }
            if (self::hasRequiredHeaders(($sheet['rows'][0] ?? []))) {
                $headerMatches[] = self::candidate($sheet, $sheet['rows'] ?? [], 'headers');
            }
        }
        return $tableMatches ?: ($sheetMatches ?: $headerMatches);
    }

    public static function normalizeHeaders(array $headers): array
    {
        return SalesDataNormalizer::normalizeHeaders($headers);
    }

    public static function mapColumns(array $headers, ?array $mappings = null): array
    {
        $mappings = $mappings ?? SalesAggregateRepository::mappings();
        $byHeader = [];
        foreach ($mappings as $mapping) $byHeader[SalesDataNormalizer::normalizeHeader($mapping['source_header'])] = $mapping;
        $columns = [];
        foreach ($headers as $index => $header) {
            $normalizedHeader = SalesDataNormalizer::normalizeHeader($header);
            if ($normalizedHeader !== '' && isset($byHeader[$normalizedHeader])) $columns[$index] = $byHeader[$normalizedHeader];
        }
        $mappedKeys = array_column($columns, 'normalized_key');
        $missing = [];
        foreach (SalesDataNormalizer::REQUIRED as $label => $key) if (!in_array($key, $mappedKeys, true)) $missing[] = $label;
        return ['columns'=>$columns,'missing_required'=>$missing,'contract'=>self::canonicalHeaderReport($headers)];
    }

    public static function canonicalHeaderReport(array $headers): array
    {
        $actual = array_map(static fn($value): string => trim((string)$value), $headers);
        $profiles = [
            [SalesDataNormalizer::SALES_AGGREGATE_PROFILE, SalesDataNormalizer::CANONICAL_HEADERS],
            ['ERP_SALES_AGGREGATE_RAW_V1', SalesDataNormalizer::rawCanonicalHeaders()],
        ];
        $reports = [];
        foreach ($profiles as [$profile, $expected]) {
            $normalizedActual = SalesDataNormalizer::normalizeHeaders($actual);
            $normalizedExpected = SalesDataNormalizer::normalizeHeaders($expected);
            $counts = array_count_values(array_filter($normalizedActual, static fn(string $value): bool => $value !== ''));
            $expectedCounts = array_count_values(array_filter($normalizedExpected, static fn(string $value): bool => $value !== ''));
            $duplicates = [];
            foreach ($counts as $header => $count) if ($count > 1) $duplicates[] = $header;
            $missing = [];
            foreach ($expectedCounts as $header => $count) {
                if (($counts[$header] ?? 0) < $count) $missing[] = $expected[array_search($header, $normalizedExpected, true)];
            }
            $extra = [];
            foreach ($counts as $header => $count) if ($count > ($expectedCounts[$header] ?? 0)) $extra[] = $actual[array_search($header, $normalizedActual, true)];
            $orderMismatch = count($normalizedActual) !== count($normalizedExpected) || $normalizedActual !== $normalizedExpected;
            $matched = 0;
            foreach ($normalizedExpected as $index => $header) if (($normalizedActual[$index] ?? null) === $header) $matched++;
            $reports[] = [
                'source_profile'=>$profile,'expected_count'=>count($expected),'actual_count'=>count($actual),'exact_matched_headers'=>$matched,
                'missing_headers'=>array_values(array_unique($missing)),'extra_headers'=>array_values(array_unique($extra)),'duplicate_headers'=>array_values(array_unique($duplicates)),'order_mismatch'=>$orderMismatch,
                'is_exact'=>$matched === count($expected) && !$missing && !$extra && !$duplicates && !$orderMismatch,
            ];
        }
        usort($reports, static fn(array $a, array $b): int => ($b['is_exact'] <=> $a['is_exact']) ?: (($b['exact_matched_headers'] ?? 0) <=> ($a['exact_matched_headers'] ?? 0)));
        return $reports[0];
    }

    public static function normalizePersianArabicDigits(mixed $value): string
    {
        return SalesDataNormalizer::normalizePersianArabicDigits($value);
    }

    public static function normalizeDate(mixed $value): ?string
    {
        return SalesDataNormalizer::normalizeDate($value);
    }

    public static function normalizeDecimal(mixed $value): ?string
    {
        return SalesDataNormalizer::normalizeDecimal($value);
    }

    public static function validateRow(array $normalized, array $raw = []): array
    {
        $errors = [];
        foreach (SalesDataNormalizer::REQUIRED as $label => $key) {
            if (in_array($key, ['visitor_code', 'visitor_name'], true)) continue;
            $value = $normalized[$key] ?? null;
            if ($value === null || trim((string)$value) === '') $errors[] = ['code'=>'required_field','message'=>"فیلد «{$label}» الزامی است."];
        }
        if (trim((string)($normalized['visitor_code'] ?? '')) === '' && trim((string)($normalized['visitor_name'] ?? '')) === '') {
            $errors[] = ['code'=>'required_field','message'=>'یکی از فیلدهای «کد فروشنده» یا «نام فروشنده» الزامی است.'];
        }
        foreach (['quantity'=>'تعداد کل','gross_amount'=>'مبلغ ناخالص','discount_amount'=>'مجموع مبلغ تخفیف سطری','net_amount'=>'مبلغ خالص'] as $key=>$label) {
            $rawValue = self::rawValueForKey($raw, $key);
            if (trim((string)$rawValue) !== '' && ($normalized[$key] ?? null) === null) $errors[] = ['code'=>'invalid_number','message'=>"مقدار «{$label}» عدد معتبر نیست."];
        }
        $type = SalesDataNormalizer::normalizeHeader($normalized['invoice_type'] ?? '');
        $isReturn = $type !== '' && (str_contains($type, 'برگشت') || str_contains($type, 'مرجوع') || str_contains($type, 'return'));
        if (!$isReturn) {
            foreach (['quantity'=>'تعداد کل','gross_amount'=>'مبلغ ناخالص','discount_amount'=>'تخفیف','net_amount'=>'مبلغ خالص'] as $key=>$label) {
                if (($normalized[$key] ?? null) !== null && (float)$normalized[$key] < 0) $errors[] = ['code'=>'negative_non_return','message'=>"مقدار منفی «{$label}» فقط برای فاکتور برگشتی مجاز است."];
            }
        }
        $invoiceMonth = self::jalaliMonth($normalized['invoice_date_raw'] ?? '');
        $turnoverMonth = self::turnoverMonthNumber($normalized['turnover_month'] ?? '');
        if ($invoiceMonth !== null && $turnoverMonth !== null && $invoiceMonth !== $turnoverMonth) {
            $errors[] = ['code'=>'PERIOD_MISMATCH','severity'=>'warning','message'=>'ماه گردش با ماه تاریخ فاکتور یکسان نیست؛ مقدار ERP بدون تغییر حفظ شد.'];
        }
        return $errors;
    }

    public static function buildSourceUniqueKey(array $normalized): string
    {
        $uniqueCode = trim((string)($normalized['unique_code'] ?? ''));
        if ($uniqueCode !== '') return sha1('sales_aggregate|' . $uniqueCode);
        $sourceIdentifier = trim((string)($normalized['identifier'] ?? ''));
        if ($sourceIdentifier !== '') return sha1('sales_aggregate|identifier|' . $sourceIdentifier);
        $parts = ['invoice_number','invoice_type','sub_invoice_number','product_code','customer_code','visitor_code','invoice_date_raw'];
        return sha1('sales_aggregate|' . implode('|', array_map(static fn($key) => trim((string)($normalized[$key] ?? '')), $parts)));
    }

    public static function detectDuplicate(string $sourceKey, array $seen = []): bool
    {
        return isset($seen[$sourceKey]) || SalesAggregateRepository::sourceKeyExists($sourceKey);
    }

    public static function storeToStaging(int $batchId, array $candidate): array
    {
        $batch = Database::fetch('SELECT import_mode,metadata_json FROM sales_import_batches WHERE id=? AND source_module="sales_aggregate"', [$batchId]);
        if (!$batch) throw new InvalidArgumentException('Batch پیدا نشد.');
        $pdo=Database::connection();$startedHere=!$pdo->inTransaction();if($startedHere)$pdo->beginTransaction();
        try {
        $mode = in_array($batch['import_mode'], self::MODES, true) ? $batch['import_mode'] : 'replace_reference';
        $seen = [];
        $counts = ['total_rows'=>0,'valid_rows'=>0,'warning_rows'=>0,'invalid_rows'=>0,'duplicate_rows'=>0,'ready_rows'=>0];
        $headers = null;
        $mapping = null;
        foreach (SpreadsheetImportReader::candidateRows($candidate) as $item) {
            $values = $item['values'];
            if ($headers === null) {
                $headers = array_map(static fn($v) => trim((string)$v), $values);
                $mapping = self::mapColumns($headers);
                if ($mapping['missing_required']) throw new InvalidArgumentException('سرستون‌های الزامی یافت نشد: ' . implode('، ', $mapping['missing_required']));
                if (self::isCanonicalCandidate($candidate) && empty($mapping['contract']['is_exact'])) {
                    throw new InvalidArgumentException('ساختار ۱۰۹ ستونی فایل ERP معتبر نیست؛ گزارش اختلاف سرستون‌ها را بررسی کنید.');
                }
                continue;
            }
            if ($counts['total_rows'] >= self::MAX_ROWS) throw new InvalidArgumentException('تعداد ردیف‌های فایل بیش از حد مجاز است.');
            if (!array_filter($values, static fn($v) => trim((string)$v) !== '')) continue;
            $rowNumber = (int)$item['row_number'];
            $raw = [];
            foreach ($headers as $index=>$header) if ($header !== '') $raw[$header] = (string)($values[$index] ?? '');
            $normalized = self::normalizeMappedRow($values, $mapping['columns'] ?? []);
            self::enrichPeriodFields($normalized);
            $sourceKey = self::buildSourceUniqueKey($normalized);
            $duplicate = self::detectDuplicate($sourceKey, $seen);
            $seen[$sourceKey] = true;
            $normalized['_duplicate'] = $duplicate;
            $errors = self::validateRow($normalized, $raw);
            if ($duplicate && $mode === 'fail_on_duplicate') $errors[] = ['code'=>'duplicate','severity'=>'error','message'=>'این ردیف با داده موجود یا ردیف دیگری در فایل تکراری است.'];
            $fatalErrors = array_filter($errors, static fn(array $error): bool => ($error['severity'] ?? 'error') !== 'warning');
            $hasWarning = (bool)array_filter($errors, static fn(array $error): bool => ($error['severity'] ?? 'error') === 'warning');
            $status = $fatalErrors ? 'invalid' : (($duplicate && in_array($mode, ['skip_duplicates','append'], true)) ? 'duplicate' : 'valid');
            $sourceRowNumber = self::sourceRowNumber($normalized['source_row_number'] ?? null);
            SalesAggregateRepository::addStagingRow($batchId,$rowNumber,$raw,$normalized,$status,$errors,$sourceKey,$sourceRowNumber);
            $counts['total_rows']++;
            if ($hasWarning) $counts['warning_rows']++;
            if ($duplicate) $counts['duplicate_rows']++;
            if ($status === 'invalid') $counts['invalid_rows']++; else $counts['valid_rows']++;
            if ($status === 'valid') $counts['ready_rows']++;
        }
        if ($headers === null) throw new InvalidArgumentException('منبع انتخاب‌شده فاقد داده است.');
        $metadata = json_decode((string)$batch['metadata_json'], true) ?: [];
        $metadata['selected_candidate'] = self::candidateMetadata($candidate);
        $metadata['source_profile'] = self::isCanonicalCandidate($candidate) ? SalesDataNormalizer::SALES_AGGREGATE_PROFILE : 'legacy_compatible';
        $metadata['header_contract'] = $mapping['contract'] ?? self::canonicalHeaderReport($headers);
        $metadata['warning_rows'] = $counts['warning_rows'];
        SalesAggregateRepository::updateBatchDetection($batchId,$candidate,'preview',$metadata);
        SalesAggregateRepository::updateBatchCounts($batchId,$counts,'preview');
        if($startedHere)$pdo->commit();
        return $counts;
        } catch (Throwable $e) {
            if($startedHere&&$pdo->inTransaction())$pdo->rollBack();
            throw $e;
        }
    }

    public static function generateImportSummary(int $batchId): array
    {
        $batch = Database::fetch('SELECT * FROM sales_import_batches WHERE id=? AND source_module="sales_aggregate"', [$batchId]);
        if (!$batch) throw new InvalidArgumentException('Batch پیدا نشد.');
        $metadata = json_decode((string)$batch['metadata_json'], true) ?: [];
        return [
            'total_rows'=>(int)$batch['total_rows'],'valid_rows'=>(int)$batch['valid_rows'],'invalid_rows'=>(int)$batch['invalid_rows'],
            'duplicate_rows'=>(int)$batch['duplicate_rows'],'ready_rows'=>max(0,(int)$batch['valid_rows']-(in_array($batch['import_mode'], ['skip_duplicates','append'], true)?(int)$batch['duplicate_rows']:0)),
            'warning_rows'=>(int)($metadata['warning_rows'] ?? 0),
        ];
    }

    public static function selectCandidate(int $batchId, string $candidateKey, int $actorId, bool $isAdmin = false): array
    {
        $batch = SalesAggregateRepository::batchForActor($batchId,$actorId,$isAdmin);
        if (!$batch || $batch['status'] !== 'awaiting_source_selection') throw new InvalidArgumentException('Batch قابل انتخاب منبع نیست.');
        $metadata = json_decode((string)$batch['metadata_json'], true) ?: [];
        $storedName = basename((string)($metadata['stored_name'] ?? ''));
        $extension = (string)($metadata['extension'] ?? '');
        if ($storedName === '' || !in_array($extension,['xlsx','csv'],true)) throw new InvalidArgumentException('فایل Batch در دسترس نیست.');
        $workbook = self::readStoredFile(self::storageDirectory().DIRECTORY_SEPARATOR.$storedName,$extension);
        $candidates = self::detectWorkbookSource($workbook);
        foreach ($candidates as $candidate) if (hash_equals($candidate['key'],$candidateKey)) return self::storeToStaging($batchId,$candidate);
        throw new InvalidArgumentException('منبع انتخاب‌شده معتبر نیست.');
    }

    public static function commitValidRows(int $batchId, int $actorId, bool $isAdmin = false): array
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $batch = SalesAggregateRepository::batchForActor($batchId,$actorId,$isAdmin,true);
            if (!$batch || $batch['status'] !== 'preview') throw new InvalidArgumentException('Batch آماده تایید نهایی نیست.');
            if ($batch['import_mode'] === 'fail_on_duplicate' && (int)$batch['duplicate_rows'] > 0) {
                throw new DomainException('داده تکراری یافت شد؛ ورود نهایی لغو شد.');
            }
            $validCount = SalesAggregateRepository::stagingCount($batchId,'valid');
            if ($validCount === 0 && (int)$batch['total_rows'] > 0 && !in_array($batch['import_mode'], ['skip_duplicates','append'], true)) {
                throw new DomainException('هیچ ردیف معتبری برای ورود نهایی وجود ندارد.');
            }
            $imported = $updated = $skipped = 0;
            if (in_array($batch['import_mode'], ['skip_duplicates','append'], true)) {
                $afterDuplicateId = 0;
                while ($duplicates = SalesAggregateRepository::stagingRowsChunk($batchId,'duplicate',$afterDuplicateId)) {
                    foreach ($duplicates as $duplicate) { $afterDuplicateId=(int)$duplicate['id'];SalesAggregateRepository::markStaging($afterDuplicateId,'skipped');$skipped++; }
                }
            }
            $afterValidId = 0;
            while ($rows = SalesAggregateRepository::stagingRowsChunk($batchId,'valid',$afterValidId)) {
                foreach ($rows as $row) {
                    $afterValidId = (int)$row['id'];
                    $data = json_decode((string)$row['normalized_json'], true) ?: [];
                    if (!empty($batch['period_key'])) $data['period_key'] = $batch['period_key'];
                    $raw = json_decode((string)$row['raw_json'], true) ?: [];
                    $existing = SalesAggregateRepository::finalRowBySourceKey((string)$row['source_unique_key'],true);
                    if ($existing && $batch['import_mode'] === 'fail_on_duplicate') throw new DomainException('داده تکراری یافت شد؛ ورود نهایی لغو شد.');
                    if ($existing && in_array($batch['import_mode'], ['skip_duplicates','append'], true)) { SalesAggregateRepository::markStaging($afterValidId,'skipped');$skipped++;continue; }
                    if ($existing) { SalesAggregateRepository::updateFinal((int)$existing['id'],$batchId,(string)$row['source_unique_key'],$data,$raw);SalesAggregateRepository::markStaging($afterValidId,'committed');$updated++; }
                    else { SalesAggregateRepository::insertFinal($batchId,(string)$row['source_unique_key'],$data,$raw);SalesAggregateRepository::markStaging($afterValidId,'committed');$imported++; }
                }
            }
            SalesAggregateRepository::finalizeReferenceAliases($batchId);
            SalesAggregateRepository::finishBatch($batchId,'committed',$imported,$updated,$skipped);
            SalesReferenceRepository::setActiveReferenceBatch($batchId, $actorId);
            $pdo->commit();
            return compact('imported','updated','skipped');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('Sales aggregate commit: '.$e->getMessage());
            if ($e instanceof InvalidArgumentException || $e instanceof DomainException) throw $e;
            throw new RuntimeException('ورود نهایی داده‌ها انجام نشد.');
        }
    }

    public static function rollbackBatch(int $batchId, int $actorId, bool $isAdmin = false): void
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $batch = SalesAggregateRepository::batchForActor($batchId,$actorId,$isAdmin,true);
            if (!$batch || in_array($batch['status'],['completed','committed','cancelled'],true)) throw new InvalidArgumentException('Batch قابل لغو نیست.');
            Database::execute('UPDATE staging_sales_data SET validation_status="cancelled" WHERE import_batch_id=? AND source_module="sales_aggregate"',[$batchId]);
            SalesAggregateRepository::finishBatch($batchId,'cancelled',0,0,0);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    private static function normalizeMappedRow(array $values, array $columns): array
    {
        $normalized = [];
        foreach ($columns as $index=>$mapping) {
            $key = $mapping['normalized_key']; $value = $values[$index] ?? '';
            if ($mapping['data_type'] === 'decimal') $normalized[$key] = SalesDataNormalizer::normalizeDecimal($value);
            else $normalized[$key] = SalesDataNormalizer::normalizePersianArabicDigits($value);
        }
        $normalized['invoice_date_raw'] = SalesDataNormalizer::normalizePersianArabicDigits($normalized['invoice_date_raw'] ?? '');
        $normalized['invoice_date'] = SalesDataNormalizer::normalizeDate($normalized['invoice_date_raw']);
        return $normalized;
    }

    private static function enrichPeriodFields(array &$normalized): void
    {
        $year = null;
        $invoiceRaw = SalesDataNormalizer::normalizePersianArabicDigits($normalized['invoice_date_raw'] ?? '');
        if (preg_match('/^(1[34]\d{2})[\/\-]/', $invoiceRaw, $match)) $year = (int)$match[1];
        $month = self::turnoverMonthNumber($normalized['turnover_month'] ?? '');
        if ($year !== null) $normalized['turnover_year'] = (string)$year;
        if ($year !== null && $month !== null) $normalized['period_key'] = sprintf('%04d-%02d', $year, $month);
    }

    private static function jalaliMonth(mixed $value): ?int
    {
        $value = SalesDataNormalizer::normalizePersianArabicDigits($value);
        return preg_match('/^1[34]\d{2}[\/\-](\d{1,2})[\/\-]/', $value, $match) && (int)$match[1] >= 1 && (int)$match[1] <= 12 ? (int)$match[1] : null;
    }

    private static function turnoverMonthNumber(mixed $value): ?int
    {
        $value = SalesDataNormalizer::normalizePersianArabicDigits($value);
        return preg_match('/(?:^|\s)(\d{1,2})(?:\s*[-\/]|\s|$)/u', $value, $match) && (int)$match[1] >= 1 && (int)$match[1] <= 12 ? (int)$match[1] : null;
    }

    private static function sourceRowNumber(mixed $value): ?int
    {
        $value = SalesDataNormalizer::normalizePersianArabicDigits($value);
        return preg_match('/^\d+$/', $value) && (int)$value > 0 ? (int)$value : null;
    }

    private static function isCanonicalCandidate(array $candidate): bool
    {
        return in_array(SalesDataNormalizer::normalizeHeader($candidate['table_name'] ?? ''), ['tbltajmii','tblsales_raw'], true)
            || SalesDataNormalizer::normalizeHeader($candidate['sheet_name'] ?? '') === SalesDataNormalizer::normalizeHeader(SalesDataNormalizer::SALES_AGGREGATE_SHEET)
            || !empty(self::canonicalHeaderReport($candidate['headers'] ?? [])['is_exact']);
    }

    private static function cleanPeriodKey(?string $periodKey): ?string
    {
        $periodKey = SalesDataNormalizer::normalizePersianArabicDigits((string)$periodKey);
        $periodKey = trim($periodKey);
        return $periodKey === '' ? null : mb_substr($periodKey, 0, 50);
    }

    private static function rawValueForKey(array $raw, string $key): mixed
    {
        foreach (SalesDataNormalizer::mappingDefinitions() as $mapping) {
            if ($mapping['normalized_key'] === $key && array_key_exists($mapping['source_header'],$raw)) return $raw[$mapping['source_header']];
        }
        return null;
    }

    private static function hasRequiredHeaders(array $headers): bool
    {
        $normalized = SalesDataNormalizer::normalizeHeaders($headers);
        foreach (array_keys(SalesDataNormalizer::REQUIRED) as $required) {
            if (!in_array(SalesDataNormalizer::normalizeHeader($required),$normalized,true)) return false;
        }
        return true;
    }

    private static function candidate(array $sheet, array $rows, string $detection, string $tableName = '', string $ref = ''): array
    {
        $identity = $detection.'|'.($sheet['name']??'').'|'.$tableName.'|'.$ref;
        return ['key'=>hash('sha256',$identity),'sheet_name'=>(string)($sheet['name']??''),'table_name'=>$tableName?:null,
            'detection'=>$detection,'ref'=>$ref?:null,'headers'=>$rows[0]??[],'rows'=>$rows,'stream'=>$sheet['stream']??null];
    }

    private static function candidateMetadata(array $candidate): array
    {
        return ['key'=>$candidate['key'],'sheet_name'=>$candidate['sheet_name'],'table_name'=>$candidate['table_name'],
            'detection'=>$candidate['detection'],'ref'=>$candidate['ref'],'headers'=>$candidate['headers'],
            'header_contract'=>self::canonicalHeaderReport($candidate['headers'] ?? [])];
    }

    private static function storageDirectory(): string
    {
        $directory = dirname(__DIR__).DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'sales-imports';
        if (!is_dir($directory) && !mkdir($directory,0750,true) && !is_dir($directory)) throw new RuntimeException('فضای ذخیره امن در دسترس نیست.');
        return $directory;
    }

    private static function readStoredFile(string $path, string $extension): array
    {
        if (!is_file($path)) throw new InvalidArgumentException('فایل Batch در دسترس نیست.');
        return $extension === 'csv' ? self::readCsv($path) : self::readXlsx($path);
    }

    private static function readCsv(string $path): array
    {
        $sample = file_get_contents($path,false,null,0,4096);
        $utf8Sample = $sample === false ? false : preg_replace('/^\xEF\xBB\xBF/','',$sample);
        if ($utf8Sample === false || !mb_check_encoding($utf8Sample,'UTF-8')) throw new InvalidArgumentException('فایل CSV باید با UTF-8 ذخیره شده باشد.');
        $firstLine = strtok($sample,"\r\n") ?: '';
        $delimiters = [','=>substr_count($firstLine,','),';'=>substr_count($firstLine,';'),"\t"=>substr_count($firstLine,"\t")];
        arsort($delimiters); $delimiter = (string)array_key_first($delimiters);
        $handle = fopen($path,'rb'); if (!$handle) throw new RuntimeException('فایل CSV قابل خواندن نیست.');
        $prefix = fread($handle, 3);
        if ($prefix !== "\xEF\xBB\xBF") rewind($handle);
        $rows=[];
        while (($row=fgetcsv($handle,0,$delimiter,'"','\\'))!==false) {
            if (count($rows)>=self::MAX_ROWS+1) { fclose($handle); throw new InvalidArgumentException('تعداد ردیف‌های فایل بیش از حد مجاز است.'); }
            if (isset($row[0])) $row[0]=preg_replace('/^\xEF\xBB\xBF/','',(string)$row[0]);
            $rows[]=$row;
        }
        fclose($handle);
        return ['sheets'=>[['name'=>'CSV','visible'=>true,'rows'=>$rows,'tables'=>[]]]];
    }

    private static function readXlsx(string $path): array
    {
        if (!class_exists('ZipArchive') || !function_exists('simplexml_load_string')) throw new RuntimeException('افزونه‌های ZipArchive و SimpleXML برای XLSX لازم هستند.');
        $zip=new ZipArchive(); if($zip->open($path)!==true) throw new InvalidArgumentException('فایل XLSX قابل خواندن نیست.');
        $total=0; for($i=0;$i<$zip->numFiles;$i++){ $stat=$zip->statIndex($i); $total+=(int)($stat['size']??0); if($total>self::MAX_UNCOMPRESSED_SIZE||$zip->numFiles>2000){$zip->close();throw new InvalidArgumentException('ساختار فایل XLSX بیش از حد مجاز است.');}}
        $shared=self::sharedStrings($zip);
        $workbook=self::xml($zip,'xl/workbook.xml'); $rels=self::relationships(self::xml($zip,'xl/_rels/workbook.xml.rels'));
        $main=$workbook->children('http://schemas.openxmlformats.org/spreadsheetml/2006/main'); $sheets=[];
        foreach($main->sheets->sheet as $sheetNode){
            $attrs=$sheetNode->attributes(); $rattrs=$sheetNode->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
            $target=$rels[(string)$rattrs['id']]??''; if($target==='')continue; $sheetPath=self::resolveZipPath('xl/workbook.xml',$target);
            $sheetXml=self::xml($zip,$sheetPath); $sheetMain=$sheetXml->children('http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $rows=self::parseSheetRows($sheetMain,$shared); $tables=[];
            $sheetRelsPath=dirname($sheetPath).'/_rels/'.basename($sheetPath).'.rels'; $sheetRels=[];
            if($zip->locateName($sheetRelsPath)!==false)$sheetRels=self::relationships(self::xml($zip,$sheetRelsPath));
            if(isset($sheetMain->tableParts)){foreach($sheetMain->tableParts->tablePart as $part){$pa=$part->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');$tableTarget=$sheetRels[(string)$pa['id']]??'';if($tableTarget==='')continue;$tableXml=self::xml($zip,self::resolveZipPath($sheetPath,$tableTarget));$ta=$tableXml->attributes();$tables[]=['name'=>(string)($ta['displayName']??$ta['name']??''),'ref'=>(string)($ta['ref']??'')];}}
            $sheets[]=['name'=>(string)$attrs['name'],'visible'=>((string)($attrs['state']??'visible'))==='visible','rows'=>$rows,'tables'=>$tables];
        }
        $zip->close(); return ['sheets'=>$sheets];
    }

    private static function parseSheetRows(SimpleXMLElement $main, array $shared): array
    {
        $rows=[]; foreach($main->sheetData->row as $row){$values=[];foreach($row->children('http://schemas.openxmlformats.org/spreadsheetml/2006/main')->c as $cell){$a=$cell->attributes();$cm=$cell->children('http://schemas.openxmlformats.org/spreadsheetml/2006/main');$ref=(string)$a['r'];$letters=preg_replace('/\d+/','',$ref);$index=self::columnIndex($letters);$type=(string)($a['t']??'');if($type==='inlineStr'){$value='';foreach($cm->is->xpath('.//*[local-name()="t"]')?:[] as $t)$value.=(string)$t;}elseif($type==='s')$value=$shared[(int)$cm->v]??'';elseif($type==='b')$value=((string)$cm->v)==='1'?'1':'0';else $value=(string)$cm->v;$values[$index]=$value;}if($values){$max=max(array_keys($values));$ordered=[];for($i=0;$i<=$max;$i++)$ordered[]=$values[$i]??'';$rows[]=$ordered;}if(count($rows)>self::MAX_ROWS+1)throw new InvalidArgumentException('تعداد ردیف‌های فایل بیش از حد مجاز است.');}return $rows;
    }

    private static function sharedStrings(ZipArchive $zip): array
    {
        if($zip->locateName('xl/sharedStrings.xml')===false)return[];$xml=self::xml($zip,'xl/sharedStrings.xml');$main=$xml->children('http://schemas.openxmlformats.org/spreadsheetml/2006/main');$out=[];foreach($main->si as $si){$text='';foreach($si->xpath('.//*[local-name()="t"]')?:[] as $t)$text.=(string)$t;$out[]=$text;}return$out;
    }

    private static function xml(ZipArchive $zip,string $name): SimpleXMLElement
    {
        $content=$zip->getFromName($name);if($content===false)throw new InvalidArgumentException('ساختار داخلی XLSX ناقص است.');libxml_use_internal_errors(true);$xml=simplexml_load_string($content,SimpleXMLElement::class,LIBXML_NONET);libxml_clear_errors();if(!$xml)throw new InvalidArgumentException('ساختار XML فایل XLSX معتبر نیست.');return$xml;
    }

    private static function relationships(SimpleXMLElement $xml): array
    {
        $map=[];$children=$xml->children('http://schemas.openxmlformats.org/package/2006/relationships');foreach($children->Relationship as $rel){$a=$rel->attributes();$map[(string)$a['Id']]=(string)$a['Target'];}return$map;
    }

    private static function resolveZipPath(string $baseFile,string $target): string
    {
        if(str_starts_with($target,'/'))return ltrim($target,'/');$parts=explode('/',str_replace('\\','/',dirname($baseFile).'/'.$target));$resolved=[];foreach($parts as $part){if($part===''||$part==='.')continue;if($part==='..'){array_pop($resolved);continue;}$resolved[]=$part;}return implode('/',$resolved);
    }

    private static function cropRows(array $rows,string $ref): array
    {
        if(!preg_match('/^([A-Z]+)(\d+):([A-Z]+)(\d+)$/i',$ref,$m))return$rows;$c1=self::columnIndex($m[1]);$r1=(int)$m[2]-1;$c2=self::columnIndex($m[3]);$r2=(int)$m[4]-1;$out=[];for($r=max(0,$r1);$r<=min($r2,count($rows)-1);$r++)$out[]=array_slice($rows[$r]??[],$c1,$c2-$c1+1);return$out;
    }

    private static function columnIndex(string $letters): int
    {
        $index=0;foreach(str_split(strtoupper($letters)) as $char)$index=$index*26+(ord($char)-64);return max(0,$index-1);
    }
}
