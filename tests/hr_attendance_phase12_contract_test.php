<?php

$root=dirname(__DIR__);
$read=static fn(string $path):string=>(string)file_get_contents($root.'/'.$path);
$module=$read('core/HrAttendanceModule.php');
$repo=$read('lib/HrAttendanceRepository.php');
$service=$read('services/UnifiedImportService.php');
$registry=$read('lib/ImportSourceRegistry.php');
$own=$read('employee/my-attendance.php');
$entry=$read('admin/hr-attendance.php');
$importPage=$read('admin/import-center.php');
$script=$read('assets/js/hr-attendance.js');
$schema=$read('database/schema.sql');

foreach(['holiday_work','leave_type','mission_details','import_time_notes','hr_attendance_identity_mappings'] as $token){
    if(!str_contains($module.$schema,$token))throw new RuntimeException('Missing attendance schema token: '.$token);
}
if(!str_contains($module,"MODIFY day_status VARCHAR(30)"))throw new RuntimeException('Legacy day-status compatibility migration is missing.');
foreach(['ENTRY_STATUSES','حضور در روز تعطیل','holidayForDateContext'] as $token){
    if(!str_contains($repo,$token))throw new RuntimeException('Missing attendance status contract: '.$token);
}
foreach(['نوع مرخصی برای','شرح مأموریت برای'] as $token){
    if(!str_contains($repo,$token))throw new RuntimeException('Missing required attendance detail validation: '.$token);
}
if(str_contains($entry,'HrAttendanceRepository::DAY_STATUSES as'))throw new RuntimeException('Legacy holiday statuses are still offered in the daily entry form.');
if(!str_contains($entry,"app_date_input('date'"))throw new RuntimeException('Daily attendance date does not use the shared Jalali component.');
foreach(['app_period_select','AppDate::resolvePeriod','attendance-custom-period'] as $token){
    if(!str_contains($own,$token))throw new RuntimeException('Period selector contract is missing: '.$token);
}
foreach(['name="year"','name="month"'] as $legacy){
    if(str_contains($own,$legacy))throw new RuntimeException('Legacy simultaneous year/month filter remains: '.$legacy);
}
foreach(['kara_system_code','employee_no','existing_employee_mapping','manual_mapping','mapAttendanceIdentity'] as $token){
    if(!str_contains($service,$token))throw new RuntimeException('Attendance identity order token is missing: '.$token);
}
if(!str_contains($registry,"'کد سیستم کارا'=>'kara_system_code'"))throw new RuntimeException('Kara header mapping is missing.');
if(preg_match('/WHERE\s+(?:u\.)?(?:name|first_name|last_name)\s*=/i',$service))throw new RuntimeException('Attendance import must not resolve identity by name.');
foreach(['normalizeAttendanceClock','normalizeAttendanceDuration','skip_derived_holiday','work_minutes'] as $token){
    if(!str_contains($service,$token))throw new RuntimeException('Attendance normalization contract is missing: '.$token);
}
foreach(['leave_type_required','mission_details_required'] as $token){
    if(!str_contains($service,$token))throw new RuntimeException('Attendance import detail validation is missing: '.$token);
}
foreach(['map_attendance_identity','staging_id','user_id'] as $token){
    if(!str_contains($importPage,$token))throw new RuntimeException('Manual unresolved mapping UI is missing: '.$token);
}
foreach(['window.Motion','prefers-reduced-motion','data-attendance-status'] as $token){
    if(!str_contains($script,$token))throw new RuntimeException('Motion/accessibility token is missing: '.$token);
}
foreach([$module,$repo,$service,$schema] as $scope){
    if(preg_match('/\b(?:DROP|TRUNCATE)\b/i',$scope))throw new RuntimeException('Destructive SQL token found.');
}

echo "HR attendance phase 12 contract: PASS\n";
