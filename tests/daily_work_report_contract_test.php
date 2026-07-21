<?php

$root = dirname(__DIR__);
$required = [
    'core/DailyWorkReportModule.php',
    'services/DailyWorkReportService.php',
    'admin/daily-work-report.php',
    'admin/daily-report-templates.php',
    'assets/css/daily-work-report.css',
    'assets/js/daily-work-report.js',
    'docs/daily-work-report.md',
    'tests/daily_work_report_schema_integration_test.php',
    'tests/fixtures/daily-work-report.html',
];
foreach ($required as $file) {
    if (!is_file($root.'/'.$file)) throw new RuntimeException('Missing daily report file: '.$file);
}

$module = (string)file_get_contents($root.'/core/DailyWorkReportModule.php');
foreach ([
    'daily_report_templates','daily_report_template_fields','daily_report_template_assignments',
    'daily_reports','daily_report_values','daily_report_links','daily_report_logs',
    'daily_general','sales_manager_daily_work_logs','legacy_source_type',
    'daily_reports.view','daily_reports.submit','daily_reports.view_team','daily_reports.manage_templates',
] as $token) {
    if (!str_contains($module,$token)) throw new RuntimeException('Missing daily report module contract: '.$token);
}
if (substr_count($module,'CREATE TABLE IF NOT EXISTS ') !== 7) throw new RuntimeException('Daily report module must own exactly seven tables.');
if (preg_match('/\b(DROP|TRUNCATE|DELETE\s+FROM)\b/i',$module)) throw new RuntimeException('Destructive daily report migration found.');

$service = (string)file_get_contents($root.'/services/DailyWorkReportService.php');
foreach ([
    'assigned_actions','planner_tasks','completed_tasks','open_tasks','kpi_values','imported_data','attendance','calculated',
    'OrgAccess::accessibleUserIds','OrgAccess::canAccessUser','ActionHubService::createAction',
    "'source_type'=>'daily_work_report'",'daily_report_links','templateMatchesUser',
    'هر کاربر فقط می‌تواند گزارش روزانه خودش را ثبت کند.',
    'ایجاد اقدام فقط از گزارش روزانه خودتان مجاز است.',
] as $token) {
    if (!str_contains($service,$token)) throw new RuntimeException('Missing daily report service contract: '.$token);
}
require_once $root.'/services/DailyWorkReportService.php';
$calculator = new ReflectionMethod(DailyWorkReportService::class,'calculate');
$value = $calculator->invoke(null,'({done}+{tasks})*2',['done'=>3,'tasks'=>2],'آزمون');
if (abs((float)$value-10.0)>0.0001) throw new RuntimeException('Daily report calculated field failed.');
try {
    $calculator->invoke(null,'system(1)',[],'آزمون');
    throw new RuntimeException('Unsafe daily report formula was accepted.');
} catch (ReflectionException $e) {
    throw $e;
} catch (Throwable $e) {
    if ($e instanceof RuntimeException && $e->getMessage()==='Unsafe daily report formula was accepted.') throw $e;
}

$page = (string)file_get_contents($root.'/admin/daily-work-report.php');
foreach ([
    'گزارش کار روزانه','Auth::verifyCsrf','app_date_input','DailyWorkReportService::saveReport',
    'DailyWorkReportService::createActionFromReport','ساخت و پیوند اقدام','reportLinksByField',
    'قالب فعالی تخصیص ندارد','/admin/daily-report-templates.php?new=1','DailyWorkReportService::canManageTemplates($actor)',
] as $token) {
    if (!str_contains($page,$token)) throw new RuntimeException('Missing daily report UI contract: '.$token);
}
foreach (['fields_json','options_json','name="json"'] as $forbidden) {
    if (str_contains($page,$forbidden)) throw new RuntimeException('Raw JSON is exposed in daily report UI: '.$forbidden);
}

$templatePage = (string)file_get_contents($root.'/admin/daily-report-templates.php');
foreach (['کاربر','نقش سازمانی','واحد سازمانی','لاین فروش','تیم سرپرست','تیم مدیر','کل شرکت','مقدار|عنوان فارسی'] as $token) {
    if (!str_contains($templatePage.(string)file_get_contents($root.'/services/DailyWorkReportService.php'),$token)) {
        throw new RuntimeException('Missing template assignment/source UI: '.$token);
    }
}
if (str_contains($templatePage,'name="options_json"') || str_contains($templatePage,'name="config_json"')) {
    throw new RuntimeException('Template UI exposes editable JSON.');
}

$legacy = (string)file_get_contents($root.'/admin/sales-manager-daily-work-log.php');
if (!str_contains($legacy,'/admin/daily-work-report.php') || !str_contains($legacy,'exit;')) {
    throw new RuntimeException('Legacy daily log route is not preserved by redirect.');
}
foreach (['SalesOperationsService','sales_manager_daily_work_logs','fields_json','گزارش‌کار روزانه مدیر فروش'] as $forbidden) {
    if (str_contains($legacy,$forbidden)) throw new RuntimeException('Unreachable legacy daily report UI remains: '.$forbidden);
}
$menu = (string)file_get_contents($root.'/lib/admin_menu.php');
foreach (['daily_report_capability','گزارش کار روزانه','قالب‌های گزارش کار'] as $token) {
    if (!str_contains($menu,$token)) throw new RuntimeException('Daily report menu contract missing: '.$token);
}
$css = (string)file_get_contents($root.'/assets/css/daily-work-report.css');
$js = (string)file_get_contents($root.'/assets/js/daily-work-report.js');
foreach (['window.Motion','prefers-reduced-motion','@media(max-width:780px)','data-daily-source'] as $token) {
    if (!str_contains($css.$js.$templatePage,$token)) throw new RuntimeException('Daily report responsive Motion contract missing: '.$token);
}

$schema = (string)file_get_contents($root.'/database/schema.sql');
foreach (['uq_daily_report_user_date_template','uq_daily_report_legacy','idx_daily_report_assignment_scope'] as $token) {
    if (!str_contains($schema,$token)) throw new RuntimeException('Fresh schema daily report contract missing: '.$token);
}
if (preg_match('/\b(DROP|TRUNCATE|DELETE\s+FROM)\b/i',$service.$page.$templatePage)) {
    throw new RuntimeException('Destructive statement found in daily report implementation.');
}

echo "Daily work report contract: PASS\n";
