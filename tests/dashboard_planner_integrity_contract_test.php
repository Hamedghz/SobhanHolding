<?php
$root=dirname(__DIR__);$fail=static function(string $message):never{fwrite(STDERR,$message.PHP_EOL);exit(1);};
$read=static fn(string $path):string=>(string)file_get_contents($root.'/'.$path);
$dashboard=$read('admin/index.php');if(!str_contains($dashboard,'work-planner-widget.php')||!str_contains($dashboard,'planner_quick_add'))$fail('Main dashboard planner integration is missing.');
$planner=$read('services/WorkPlannerService.php');foreach(['createPersonalTask','ensureOccurrencesForUser','recurrence_key'] as $token)if(!str_contains($planner,$token))$fail('Planner contract missing: '.$token);
$module=$read('core/WorkPlannerModule.php');if(!str_contains($module,'uq_work_planner_recurrence'))$fail('Planner recurrence unique index is missing.');
$attendance=$read('lib/HrAttendanceRepository.php');foreach(['effective_to','هم‌پوشانی'] as $token)if(!str_contains($attendance,$token))$fail('Attendance range contract missing: '.$token);
$notifications=$read('lib/NotificationService.php');if(!str_contains($notifications,"str_starts_with(\$eventType, 'ticket_')"))$fail('Ticket system notification authorization is missing.');
$kpi=$read('admin/hr-kpi-scores.php');if(!str_contains($kpi,'assign_template')||!str_contains($kpi,'پیش از امتیازدهی'))$fail('KPI assignment-before-score contract is missing.');
$meetings=$read('admin/management-meetings.php');if(str_contains($meetings,'type="date" name="meeting_date"')||!str_contains($meetings,'class="jalali-date-input" name="meeting_date"'))$fail('Meeting create/edit date must use the Jalali input contract.');
$emailAccounts=$read('admin/email-accounts.php');foreach(['$testLogs','نتیجه تست و همگام‌سازی','connect_test_failed'] as $token)if(!str_contains($emailAccounts,$token))$fail('Email account test-result log UI is missing: '.$token);
$jalaliPages=['employee/work-planner.php','employee/work-planner-simple.php','admin/management-meetings.php','admin/hr-kpi-periods.php','admin/hr-attendance-settings.php','admin/management-report-prepare.php'];foreach($jalaliPages as $page){$source=$read($page);if(str_contains($source,'type="date"'))$fail('Native Gregorian date input remains in '.$page);}
$backup=$read('lib/FileBackupService.php');if(!str_contains($backup,'file_backup_api_key_rotated_at')||!str_contains($backup,"hash('sha256',\$plain)"))$fail('Backup key rotation/hash contract is missing.');
$backupPage=$read('admin/uploaded-files-backup.php');foreach(['file_backup_api_key_once','اثر انگشت','مقدار خام فقط در لحظه ساخت قابل نمایش است','X-Backup-Api-Key'] as $token)if(!str_contains($backupPage,$token))$fail('Backup key UX contract is missing: '.$token);
$backupApi=$read('api/file-backup/_bootstrap.php');if(!str_contains($backupApi,"hash_equals(\$stored,hash('sha256',\$provided))"))$fail('Backup API key hash verification is missing.');
$schema=$read('database/schema.sql');if(preg_match('/\b(DROP|TRUNCATE|RENAME\s+TABLE)\b/i',$schema))$fail('Destructive schema statement detected.');
echo "Dashboard/planner integrity contract: PASS\n";
