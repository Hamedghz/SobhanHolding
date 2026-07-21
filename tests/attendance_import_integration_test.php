<?php

$root = dirname(__DIR__);
require_once $root . '/services/UnifiedImportService.php';
require_once $root . '/tests/support/import_integration_bootstrap.php';
require_once $root . '/tests/support/xlsx_fixture.php';

$pdo = importIntegrationPdo($root);
seedImportIntegrationMappings($pdo);
$pdo->exec(
    "INSERT INTO users(id,name,email,username,employee_no,kara_system_code,password_hash,role,status,department,access_scope,admin_panel_enabled)
     VALUES
       (1,'مدیر تست','attendance-admin@example.test','attendance-admin','ADMIN-1','K-ADMIN','test','super_admin','active','مدیریت','all',1),
       (2,'کارمند تست','attendance-user@example.test','attendance-user','EMP-1001','KARA-1001','test','employee','active','اداری','self',0)
     ON DUPLICATE KEY UPDATE
       name=VALUES(name),employee_no=VALUES(employee_no),kara_system_code=VALUES(kara_system_code),role=VALUES(role),status='active'"
);
$pdo->exec(
    "INSERT INTO hr_work_groups(title,code,active,created_at,updated_at)
     VALUES ('اداری و انبار','ADMIN_WAREHOUSE',1,NOW(),NOW())
     ON DUPLICATE KEY UPDATE title=VALUES(title),active=1,updated_at=NOW()"
);

Auth::start();
$_SESSION['user_id'] = 1;

$headers = [
    'کد سیستم کارا','کد پرسنلی','تاریخ','ساعت شروع به کار','ساعت ورود','ساعت پایان کار',
    'ساعت خروج','تاخیر','اضافه کاری','کارکرد','اختلاف ساعت کاری','ماموریت',
    'شرح ماموریت','مرخصی','نوع مرخصی','ساعت کاری روزانه','ستون آینده',
];
$row = [
    'KARA-1001','EMP-1001','1405/04/25','07:30','0.4','16:30',
    '17:00','پاس','00:15','7.5','-','خیر','','خیر','','7.5','raw-attendance',
];

function attendanceWorkbook(array $headers, array $row, string $run): string
{
    $path = tempnam(sys_get_temp_dir(), 'attendance-xlsx-') . '.xlsx';
    $headers[] = 'شناسه اجرای تست';
    $row[] = $run;
    return createXlsxFixture($path, 'کارکرد', 'tblattendance', $headers, [$row]);
}

$path = attendanceWorkbook($headers, $row, 'initial');
$result = UnifiedImportService::upload(
    ['error'=>UPLOAD_ERR_OK,'tmp_name'=>$path,'size'=>filesize($path),'name'=>'attendance-name-does-not-matter.xlsx'],
    'attendance',
    'skip_duplicates',
    1
);
@unlink($path);
if ($result['needs_selection'] || ($result['summary']['ready_rows'] ?? 0) !== 1) {
    throw new RuntimeException('Attendance XLSX staging failed: '.json_encode($result,JSON_UNESCAPED_UNICODE));
}
$staged = Database::fetch(
    'SELECT normalized_json FROM staging_sales_data WHERE import_batch_id=?',
    [(int)$result['batch_id']]
);
$normalized = json_decode((string)($staged['normalized_json'] ?? ''), true) ?: [];
if (($normalized['identity_source'] ?? '') !== 'kara_system_code') {
    throw new RuntimeException('Attendance identity did not prioritize Kara system code.');
}
if (($normalized['actual_in_time'] ?? '') !== '09:36:00' || ($normalized['actual_out_time'] ?? '') !== '17:00:00') {
    throw new RuntimeException('Attendance Excel fractional/text time normalization failed.');
}
if (!str_contains((string)($normalized['import_time_notes'] ?? ''), 'پاس')) {
    throw new RuntimeException('Attendance nonnumeric time note was not preserved.');
}

$commit = UnifiedImportService::commit((int)$result['batch_id'], 1, true);
if ($commit !== ['inserted'=>1,'updated'=>0,'skipped'=>0]) {
    throw new RuntimeException('Attendance initial commit failed.');
}
$entry = Database::fetch(
    'SELECT employee_id,attendance_date,actual_in_time,actual_out_time,work_minutes,day_status,import_time_notes
     FROM hr_attendance_entries WHERE employee_id=2'
);
if (
    (int)($entry['employee_id'] ?? 0) !== 2
    || ($entry['attendance_date'] ?? '') !== '2026-07-16'
    || ($entry['day_status'] ?? '') !== 'present'
    || (int)($entry['work_minutes'] ?? 0) !== 444
) {
    throw new RuntimeException('Attendance committed values are incorrect.');
}

$updated = $row;
$updated[6] = '17:30';
$path = attendanceWorkbook($headers, $updated, 'update');
$update = UnifiedImportService::upload(
    ['error'=>UPLOAD_ERR_OK,'tmp_name'=>$path,'size'=>filesize($path),'name'=>'attendance-update.xlsx'],
    'attendance',
    'update_existing',
    1
);
@unlink($path);
$updateCommit = UnifiedImportService::commit((int)$update['batch_id'], 1, true);
if ($updateCommit !== ['inserted'=>0,'updated'=>1,'skipped'=>0]) {
    throw new RuntimeException('Attendance update-existing failed.');
}
if ((int)Database::fetch('SELECT COUNT(*) c FROM hr_attendance_entries WHERE employee_id=2')['c'] !== 1) {
    throw new RuntimeException('Attendance update created a duplicate employee/day row.');
}
$updatedEntry = Database::fetch('SELECT actual_out_time,work_minutes FROM hr_attendance_entries WHERE employee_id=2');
if (($updatedEntry['actual_out_time'] ?? '') !== '17:30:00' || (int)($updatedEntry['work_minutes'] ?? 0) !== 474) {
    throw new RuntimeException('Attendance update values were not applied.');
}

echo "Attendance import integration: PASS\n";

