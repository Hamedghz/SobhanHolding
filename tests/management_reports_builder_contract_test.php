<?php

$root=dirname(__DIR__);
$required=[
    'core/ManagementReportsModule.php',
    'lib/ManagementReportsRepository.php',
    'admin/management-report-template-settings.php',
    'admin/management-report-prepare.php',
    'admin/management-report-view.php',
    'assets/css/management-reports.css',
    'assets/js/management-reports.js',
    'docs/management-reports.md',
    'tests/management_reports_schema_integration_test.php',
    'tests/fixtures/management-report-builder.html',
];
foreach($required as $file)if(!is_file($root.'/'.$file))throw new RuntimeException('Missing management report file: '.$file);

$module=(string)file_get_contents($root.'/core/ManagementReportsModule.php');
foreach(['version_no','template_version_no','schema_snapshot_json','management_report_links','backfillSubmissionSnapshots','upgradeActionFields'] as $token){
    if(!str_contains($module,$token))throw new RuntimeException('Missing management report migration contract: '.$token);
}
if(preg_match('/\b(DROP|TRUNCATE|DELETE\s+FROM)\b/i',$module))throw new RuntimeException('Destructive management report migration found.');

$repository=(string)file_get_contents($root.'/lib/ManagementReportsRepository.php');
foreach([
    "'editable'","'readonly_calculated'","'task_selector'","'action_selector'","'user_selector'",
    "'jalali_date'","'jalali_deadline'","'percentage'","'amount'","'attachment'",
    'templateForSubmission','templateSnapshot','ActionHubService::createAction','ActionHubService::assignableUsers',
    'WorkPlannerService::canUserAccessTask','OrgAccess::canAccessUser',"'source_type'=>'manager_report'",
    'تبدیل پیشنهاد به اقدام فقط در پیش‌نویس یا گزارش برگشتی مجاز است.',
] as $token){
    if(!str_contains($repository,$token))throw new RuntimeException('Missing management report repository contract: '.$token);
}

$settings=(string)file_get_contents($root.'/admin/management-report-template-settings.php');
foreach(['سازنده تعاملی قالب گزارش','ذخیره و ساخت نسخه جدید','key|عنوان فارسی','data-field-builder'] as $token){
    if(!str_contains($settings,$token))throw new RuntimeException('Missing form builder UI contract: '.$token);
}
foreach(['گزینه‌ها / ساختار JSON','name="options_json"','name="schema_snapshot_json"'] as $forbidden){
    if(str_contains($settings,$forbidden))throw new RuntimeException('Raw configuration is exposed in management report UI: '.$forbidden);
}

$prepare=(string)file_get_contents($root.'/admin/management-report-prepare.php');
foreach(['تبدیل پیشنهاد به اقدام','مسئول مجاز','jalali-date-input','ActionHubService::actions','OrgAccess::accessibleUserIds'] as $token){
    if(!str_contains($prepare,$token))throw new RuntimeException('Missing report completion contract: '.$token);
}
$view=(string)file_get_contents($root.'/admin/management-report-view.php');
foreach(['template_version_no','generated_action','management_report_value_html'] as $token){
    if(!str_contains($view,$token))throw new RuntimeException('Missing snapshot view contract: '.$token);
}

$js=(string)file_get_contents($root.'/assets/js/management-reports.js');
$css=(string)file_get_contents($root.'/assets/css/management-reports.css');
foreach(['window.Motion','prefers-reduced-motion','data-field-preview','@media(max-width:650px)','mr-builder-preview'] as $token){
    if(!str_contains($js.$css,$token))throw new RuntimeException('Missing responsive Motion UI contract: '.$token);
}
$schema=(string)file_get_contents($root.'/database/schema.sql');
foreach(['schema_snapshot_json','uq_management_report_field_link','idx_management_report_links_target'] as $token){
    if(!str_contains($schema,$token))throw new RuntimeException('Fresh schema management report contract missing: '.$token);
}

echo "Management reports builder contract: PASS\n";
