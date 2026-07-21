<?php

$root = dirname(__DIR__);
require_once $root . '/services/UnifiedImportService.php';
require_once $root . '/tests/support/import_integration_bootstrap.php';
require_once $root . '/tests/support/xlsx_fixture.php';

$pdo = importIntegrationPdo($root);
seedImportIntegrationMappings($pdo);

$headers = [
    'ردیف','نوع فاکتور','شماره فاکتور','شماره فاکتور تامین کننده','تاریخ',
    'کد تامین کننده','نام  تامین کننده','کد تولیدکننده کالا','نام تولیدکننده کالا',
    'کد لاین','نام لاین','کد کالا','نام کالا','تعداد کل','ناخالص',
    'مبلغ تخفیف سطری','مبلغ خالص','کد برند','نام برند','ستون آینده',
];
$rows = [
    ['1','خرید','BUY-1001','SUP-INV-1','1405/04/25','SUP-1','تأمین‌کننده یک','M-1','تولیدکننده یک','A','لاین A','P-1','کالای یک','10','1000000','50000','950000','BR-1','برند یک','raw-a'],
    ['2','خرید','BUY-1001','SUP-INV-1','1405/04/25','SUP-1','تأمین‌کننده یک','M-1','تولیدکننده یک','A','لاین A','P-1','کالای یک','20','2000000','100000','1900000','BR-1','برند یک','raw-b'],
];

function purchaseWorkbook(array $headers, array $rows, string $suffix): string
{
    $path = tempnam(sys_get_temp_dir(), 'purchase-xlsx-') . '.xlsx';
    foreach ($rows as &$row) $row[] = $suffix;
    unset($row);
    $headers[] = 'شناسه اجرای تست';
    return createXlsxFixture($path, 'گزارش تجمیعی خرید', 'tblbuy', $headers, $rows);
}

$path = purchaseWorkbook($headers, $rows, 'initial');
$result = UnifiedImportService::upload(
    ['error'=>UPLOAD_ERR_OK,'tmp_name'=>$path,'size'=>filesize($path),'name'=>'filename-is-irrelevant.xlsx'],
    'purchase_aggregate',
    'skip_duplicates',
    1,
    ['period_key'=>'1405-04']
);
@unlink($path);
if ($result['needs_selection'] || ($result['summary']['ready_rows'] ?? 0) !== 2) {
    throw new RuntimeException('Purchase XLSX staging failed.');
}
$commit = UnifiedImportService::commit((int)$result['batch_id'], 1, true);
if ($commit !== ['inserted'=>2,'updated'=>0,'skipped'=>0]) {
    throw new RuntimeException('Purchase initial commit failed.');
}
$saved = Database::fetchAll('SELECT source_unique_key,quantity,net_amount,raw_json FROM purchase_aggregate_rows ORDER BY id');
if (count($saved) !== 2 || (float)$saved[0]['quantity'] !== 10.0 || (float)$saved[1]['quantity'] !== 20.0) {
    throw new RuntimeException('Distinct source rows in one purchase invoice were merged.');
}
$raw = json_decode((string)$saved[0]['raw_json'], true) ?: [];
if (($raw['ستون آینده'] ?? '') !== 'raw-a') {
    throw new RuntimeException('Purchase raw JSON was not preserved.');
}

$updated = $rows;
$updated[0][16] = '975000';
$updated[1][16] = '1950000';
$path = purchaseWorkbook($headers, $updated, 'update');
$update = UnifiedImportService::upload(
    ['error'=>UPLOAD_ERR_OK,'tmp_name'=>$path,'size'=>filesize($path),'name'=>'another-name.xlsx'],
    'purchase_aggregate',
    'update_existing',
    1,
    ['period_key'=>'1405-04']
);
@unlink($path);
$updatedCommit = UnifiedImportService::commit((int)$update['batch_id'], 1, true);
if ($updatedCommit !== ['inserted'=>0,'updated'=>2,'skipped'=>0]) {
    throw new RuntimeException('Purchase update-existing did not perform an upsert.');
}
if ((int)Database::fetch('SELECT COUNT(*) c FROM purchase_aggregate_rows')['c'] !== 2) {
    throw new RuntimeException('Purchase update-existing created duplicate final rows.');
}
$amounts = array_map('floatval', array_column(Database::fetchAll('SELECT net_amount FROM purchase_aggregate_rows ORDER BY id'), 'net_amount'));
if ($amounts !== [975000.0,1950000.0]) {
    throw new RuntimeException('Purchase update-existing values were not saved.');
}

$cancelRows = [$rows[0]];
$cancelRows[0][0] = '3';
$cancelRows[0][2] = 'BUY-2001';
$path = purchaseWorkbook($headers, $cancelRows, 'cancel');
$cancel = UnifiedImportService::upload(
    ['error'=>UPLOAD_ERR_OK,'tmp_name'=>$path,'size'=>filesize($path),'name'=>'cancel.xlsx'],
    'purchase_aggregate',
    'skip_duplicates',
    1
);
@unlink($path);
UnifiedImportService::rollback((int)$cancel['batch_id'], 1, true);
$batch = Database::fetch('SELECT pipeline_status FROM sales_import_batches WHERE id=?', [(int)$cancel['batch_id']]);
if (($batch['pipeline_status'] ?? '') !== 'rolled_back') {
    throw new RuntimeException('Purchase batch rollback failed.');
}

echo "Purchase aggregate import integration: PASS\n";
