<?php
$root = dirname(__DIR__);
$fail = static function(string $message): never { fwrite(STDERR, $message . PHP_EOL); exit(1); };
$read = static fn(string $path): string => (string)file_get_contents($root . '/' . $path);

$required = [
    'core/OkrModule.php','lib/OkrService.php','lib/OkrDataSourceRegistry.php','lib/OkrMeetingIntegration.php','services/OkrReminderService.php','services/OkrAiAnalysisService.php',
    'admin/okr.php','admin/okr-objective.php','admin/okr-cycles.php','admin/okr-evidence.php','admin/cron/okr.php',
    'assets/css/okr.css','assets/js/okr.js','assets/js/okr-decision-link.js','storage/okr-evidence/.htaccess','docs/okr.md'
];
foreach ($required as $file) if (!is_file($root . '/' . $file)) $fail('Missing OKR file: ' . $file);

$module = $read('core/OkrModule.php');
$schema = $read('database/schema.sql');
$tables = ['okr_cycles','okr_objectives','okr_key_results','okr_alignments','okr_approvals','okr_checkins','okr_initiatives','okr_task_links','okr_evidence','okr_score_history','okr_audit_logs','okr_reminder_logs','okr_ai_analyses'];
foreach ($tables as $table) {
    if (!str_contains($module, 'CREATE TABLE IF NOT EXISTS ' . $table)) $fail('Runtime repair table missing: ' . $table);
    if (!str_contains($schema, 'CREATE TABLE IF NOT EXISTS ' . $table)) $fail('Fresh-install schema table missing: ' . $table);
}
foreach (['okr_decision_links'] as $table) {
    if (!str_contains($module, 'CREATE TABLE IF NOT EXISTS ' . $table)) $fail('Runtime integration repair table missing: ' . $table);
    if (!str_contains($schema, 'CREATE TABLE IF NOT EXISTS ' . $table)) $fail('Fresh-install integration table missing: ' . $table);
}
if (!str_contains($read('core/Database.php'), 'OkrModule::repair($pdo)')) $fail('Database repair hook missing.');
if (!str_contains($read('core/Database.php'), 'OkrModule::repairIntegrations($pdo)')) $fail('OKR integration repair hook missing.');

$service = $read('lib/OkrService.php');
foreach (['OrgAccess::accessibleUserIds','canViewObjective','canManageObjective','canApproveObjective','Auth::can(\'okr.checkin\'','progressPercent','createLinkedTask','prepareEvidence','5 * 1024 * 1024','okr_audit_logs','saveAlignment','alignmentWouldCycle','refreshKeyResult','OkrDataSourceRegistry::configFromInput'] as $token) {
    if (!str_contains($service, $token)) $fail('OKR service/security contract missing: ' . $token);
}
if (!str_contains($service, 'if (OrgAccess::isAdmin($user)) return $rows;')) $fail('Admin cannot select active OKR owners on a fresh installation.');
if (!str_contains($service, "\$direction === 'decrease'")) $fail('Decreasing KR calculation is missing.');
foreach (['SELECT code FROM sales_lines WHERE code=? AND active=1','لاین فروش باید از فهرست فعال ساختار فروش انتخاب شود','legacyLineUnchanged'] as $token) {
    if (!str_contains($service, $token)) $fail('OKR central sales-line validation missing: ' . $token);
}

foreach (['admin/okr.php','admin/okr-objective.php','admin/okr-cycles.php'] as $page) {
    $source = $read($page);
    if (!str_contains($source, 'Auth::verifyCsrf')) $fail('CSRF contract missing in ' . $page);
    if (!str_contains($source, 'jalali-date-input')) $fail('Jalali input contract missing in ' . $page);
}
$objectivePage = $read('admin/okr-objective.php');
$dashboardPage = $read('admin/okr.php');
foreach (['$openCycles','SELECT code,title FROM sales_lines WHERE active=1','<select name="sales_line">','ایجاد اولین دوره OKR'] as $token) {
    if (!str_contains($dashboardPage, $token)) $fail('OKR fresh-state/central-line UI contract missing: ' . $token);
}
foreach (['save_alignment','disable_alignment','refresh_kr','refresh_all_automatic','data-okr-source-key','نمودار روند','مصوبات مرتبط'] as $token) {
    if (!str_contains($objectivePage, $token)) $fail('OKR phase 2 UI contract missing: ' . $token);
}
$meetingIntegration = $read('lib/OkrMeetingIntegration.php') . $read('admin/management-decision-view.php');
foreach (['linkDecision','canEditDecision','canManageObjective','create_initiative','create_task','okr_decision_links','link_okr'] as $token) {
    if (!str_contains($meetingIntegration, $token)) $fail('OKR meeting integration contract missing: ' . $token);
}
$reminders = $read('services/OkrReminderService.php') . $read('admin/cron/okr.php') . $read('lib/NotificationService.php');
foreach (['okr_checkin_reminder','okr_due_date_reminder','okr_risk_alert','okr_reminder_logs','hash_equals','safeNotify'] as $token) {
    if (!str_contains($reminders, $token)) $fail('OKR reminder contract missing: ' . $token);
}
$ai = $read('services/OkrAiAnalysisService.php') . $objectivePage;
foreach (['executive_summary','risk_detection','corrective_actions','okr_improvement','cycle_comparison','SobhanApiClient','/ai/ask','deterministicAnalysis','okr_ai_analyses','run_ai_analysis'] as $token) {
    if (!str_contains($ai, $token)) $fail('OKR AI analysis contract missing: ' . $token);
}
if (preg_match('/\b(UPDATE|DELETE|INSERT)\s+(okr_objectives|okr_key_results|okr_initiatives)\b/i', $read('services/OkrAiAnalysisService.php'))) {
    $fail('OKR AI service must remain read-only for business records.');
}
$registry = $read('lib/OkrDataSourceRegistry.php');
foreach (['ReportingViewRepository::fetch','vw_sales_by_period','vw_attendance_period_summary','vw_action_workload','employee_id=?','source_key'] as $token) {
    if (!str_contains($registry, $token)) $fail('OKR automatic source contract missing: ' . $token);
}
if (preg_match('/\b(eval|exec|shell_exec|passthru)\s*\(/i', $registry)) $fail('Unsafe executable formula contract found in OKR source registry.');
$evidence = $read('admin/okr-evidence.php');
foreach (['OkrService::evidence','Content-Disposition','X-Content-Type-Options','str_starts_with'] as $token) if (!str_contains($evidence, $token)) $fail('Protected evidence contract missing: ' . $token);

$planner = $read('services/WorkPlannerService.php') . $read('employee/work-planner-simple.php') . $read('admin/work-planner.php');
foreach (['createLinkedTask','related_module="okr"','مرتبط با OKR'] as $token) if (!str_contains($planner, $token)) $fail('Planner integration missing: ' . $token);
$menu = $read('lib/admin_menu.php');
foreach (['OKR و اهداف سازمانی','okr_capability','/admin/okr.php'] as $token) if (!str_contains($menu, $token)) $fail('OKR menu integration missing: ' . $token);

$scope = $module . $service . $read('admin/okr.php') . $read('admin/okr-objective.php') . $read('admin/okr-cycles.php');
if (preg_match('/\b(DROP|TRUNCATE|RENAME\s+TABLE)\b/i', $scope)) $fail('Destructive SQL token found in OKR scope.');

require_once $root . '/lib/OkrService.php';
require_once $root . '/lib/OkrDataSourceRegistry.php';
foreach (OkrDataSourceRegistry::definitions() as $key => $definition) {
    if (isset($definition['view']) && !in_array($definition['view'], ReportingViewsModule::VIEW_NAMES, true)) {
        $fail('Automatic source uses an unapproved reporting view: ' . $key);
    }
}
$assertClose = static function(float $actual, float $expected, string $label) use ($fail): void {
    if (abs($actual - $expected) > 0.01) $fail($label . ': expected ' . $expected . ', got ' . $actual);
};
$assertClose(OkrService::progressPercent(20, 100, 60, 'increase'), 50, 'Increasing progress');
$assertClose(OkrService::progressPercent(10, 2, 6, 'decrease'), 50, 'Decreasing progress');
$assertClose(OkrService::progressPercent(0, 100, 250, 'increase'), 200, 'Progress safety cap');
$assertClose(OkrDataSourceRegistry::sumRows([['value'=>'12.5'],['value'=>7],['other'=>100]], 'value'), 19.5, 'Automatic source aggregation');

echo "OKR module contract: PASS\n";
