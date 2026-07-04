<?php
$root=dirname(__DIR__);$fail=static function(string $message):never{fwrite(STDERR,$message.PHP_EOL);exit(1);};
$read=static fn(string $path):string=>(string)file_get_contents($root.'/'.$path);
$dashboard=$read('admin/index.php');if(!str_contains($dashboard,'work-planner-widget.php')||!str_contains($dashboard,'planner_quick_add'))$fail('Main dashboard planner integration is missing.');
$planner=$read('services/WorkPlannerService.php');foreach(['createPersonalTask','ensureOccurrencesForUser','recurrence_key'] as $token)if(!str_contains($planner,$token))$fail('Planner contract missing: '.$token);
$module=$read('core/WorkPlannerModule.php');if(!str_contains($module,'uq_work_planner_recurrence'))$fail('Planner recurrence unique index is missing.');
$attendance=$read('lib/HrAttendanceRepository.php');foreach(['effective_to','هم‌پوشانی'] as $token)if(!str_contains($attendance,$token))$fail('Attendance range contract missing: '.$token);
$notifications=$read('lib/NotificationService.php');if(!str_contains($notifications,"str_starts_with(\$eventType, 'ticket_')"))$fail('Ticket system notification authorization is missing.');
$kpi=$read('admin/hr-kpi-scores.php');if(!str_contains($kpi,'assign_template')||!str_contains($kpi,'پیش از امتیازدهی'))$fail('KPI assignment-before-score contract is missing.');
$backup=$read('lib/FileBackupService.php');if(!str_contains($backup,'file_backup_api_key_rotated_at')||!str_contains($backup,"hash('sha256',\$plain)"))$fail('Backup key rotation/hash contract is missing.');
$schema=$read('database/schema.sql');if(preg_match('/\b(DROP|TRUNCATE|RENAME\s+TABLE)\b/i',$schema))$fail('Destructive schema statement detected.');
echo "Dashboard/planner integrity contract: PASS\n";
