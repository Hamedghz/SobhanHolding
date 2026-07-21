<?php

$root = dirname(__DIR__);
$required = [
    'core/ActionHubModule.php','services/ActionHubService.php','admin/action-hub.php','admin/action-view.php',
    'admin/action-file.php','admin/action-types.php','admin/action-templates.php','assets/css/action-hub.css',
    'assets/js/action-hub.js','docs/action-hub.md','tests/action_hub_schema_integration_test.php',
    'tests/fixtures/action-hub.html','storage/action-files/.htaccess','storage/action-files/web.config',
];
foreach ($required as $file) {
    if (!is_file($root . '/' . $file)) throw new RuntimeException('Missing action hub file: ' . $file);
}

$module = (string)file_get_contents($root . '/core/ActionHubModule.php');
foreach ([
    'action_types','action_templates','action_template_fields','actions','action_field_values','action_links','action_logs',
    'legacy_source_type','legacy_source_id','action_hub.create_own','action_hub.assign','action_hub.approve',
] as $token) {
    if (!str_contains($module, $token)) throw new RuntimeException('Missing action hub module contract: ' . $token);
}
if (preg_match('/\b(DROP|TRUNCATE|DELETE\s+FROM)\b/i', $module)) throw new RuntimeException('Destructive Action Hub migration found.');
if (substr_count($module, 'CREATE TABLE IF NOT EXISTS ') !== 7) throw new RuntimeException('Action Hub must own exactly seven central tables.');

$service = (string)file_get_contents($root . '/services/ActionHubService.php');
foreach ([
    'OrgAccess::accessibleUserIds','OrgAccess::canAccessUser',
    'manager_report','daily_work_report','meeting_decision','ai_suggestion',
    'short_text','long_text','money','percentage','jalali_date','multi_select','org_unit','sales_line',
    'action_link','planner_link','prefers-reduced-motion','work_planner_tasks','mirrorLegacyAction',
    'cleanupUploads','mirrorSourceRecord',
] as $token) {
    if (!str_contains($service . (string)file_get_contents($root . '/assets/js/action-hub.js'), $token)) {
        throw new RuntimeException('Missing Action Hub service contract: ' . $token);
    }
}
foreach (['new','in_progress','paused','needs_review','done','cancelled','overdue'] as $status) {
    if (!str_contains($service, "'{$status}'")) throw new RuntimeException('Missing controlled action status: ' . $status);
}
require_once $root . '/services/ActionHubService.php';
$calculator = new ReflectionMethod(ActionHubService::class, 'calculateExpression');
$calculated = $calculator->invoke(null, '({amount} * {percent} / 100) + 5', ['amount'=>200,'percent'=>10], 'آزمون');
if (abs((float)$calculated - 25.0) > 0.0001) throw new RuntimeException('Safe calculated field evaluation failed.');
$negative = $calculator->invoke(null, '2 * -3', [], 'آزمون');
if (abs((float)$negative + 6.0) > 0.0001) throw new RuntimeException('Unary calculated field evaluation failed.');
try {
    $calculator->invoke(null, 'system(1)', [], 'آزمون');
    throw new RuntimeException('Unsafe calculated expression was accepted.');
} catch (ReflectionException $e) {
    throw $e;
} catch (Throwable $e) {
    if ($e instanceof RuntimeException && $e->getMessage() === 'Unsafe calculated expression was accepted.') throw $e;
}

$hubPage = (string)file_get_contents($root . '/admin/action-hub.php');
$templatePage = (string)file_get_contents($root . '/admin/action-templates.php');
if (!str_contains($hubPage, 'Auth::verifyCsrf')) throw new RuntimeException('Action create CSRF validation is missing.');
foreach (['مرکز اقدامات سازمانی','قالب اقدام','data-action-template','field_files','add_to_planner','isFirstUseEmpty','id="action-composer"','action-submit-row','هنوز اقدامی به شما محول نشده است.'] as $token) {
    if (!str_contains($hubPage, $token)) throw new RuntimeException('Missing Action Hub UI contract: ' . $token);
}
if (str_contains($templatePage, 'گزینه‌ها JSON') || str_contains($templatePage, 'name="options_json"')) {
    throw new RuntimeException('Raw field JSON is exposed in the new template UI.');
}
foreach (['مقدار|عنوان فارسی','FIELD_TYPES','options_text'] as $token) {
    if (!str_contains($templatePage, $token)) throw new RuntimeException('Missing controlled template field UI: ' . $token);
}

$menu = (string)file_get_contents($root . '/lib/admin_menu.php');
foreach (['مرکز اقدامات','همه اقدامات','قالب‌های اقدام','انواع اقدام'] as $token) {
    if (!str_contains($menu, $token)) throw new RuntimeException('Missing Action Hub menu contract: ' . $token);
}
foreach ([
    'admin/sales-actions.php'=>'/admin/action-hub.php',
    'admin/sales-scripts.php'=>'/admin/action-templates.php?legacy=1',
    'admin/sales-script-fields.php'=>'/admin/action-templates.php?legacy=1',
] as $file=>$destination) {
    $legacy = (string)file_get_contents($root . '/' . $file);
    if (!str_contains($legacy, $destination) || !str_contains($legacy, 'exit;')) throw new RuntimeException('Legacy route is not preserved by redirect: ' . $file);
    if (str_contains($legacy, 'name="options_json"') || str_contains($legacy, 'گزینه‌ها JSON')) {
        throw new RuntimeException('Legacy action route still exposes raw JSON: ' . $file);
    }
}
foreach (['legacy_source_type="sales_script"','legacy_source_id','template_id='] as $token) {
    if (!str_contains(
        (string)file_get_contents($root . '/admin/sales-scripts.php')
        . (string)file_get_contents($root . '/admin/sales-script-fields.php'),
        $token
    )) throw new RuntimeException('Legacy sales template selection is not preserved: ' . $token);
}

$sales = (string)file_get_contents($root . '/services/SalesOperationsService.php');
foreach (["mirrorLegacyAction('sales_actions'", "mirrorLegacyAction('supervisor_actions'"] as $token) {
    if (!str_contains($sales, $token)) throw new RuntimeException('Legacy write mirror missing: ' . $token);
}
foreach ([
    'services/WorkPlannerService.php'=>"mirrorSourceRecord('work_planner_tasks'",
    'services/PersonalPlannerService.php'=>"mirrorSourceRecord('personal_planner_tasks'",
    'lib/ManagementMeetingsRepository.php'=>"mirrorSourceRecord('management_decisions'",
    'lib/OkrService.php'=>"mirrorSourceRecord('okr_initiatives'",
] as $file=>$token) {
    if (!str_contains((string)file_get_contents($root . '/' . $file), $token)) throw new RuntimeException('Universal source adapter missing: ' . $file);
}
$schema = (string)file_get_contents($root . '/database/schema.sql');
foreach (['uq_actions_legacy','idx_actions_assigned','uq_action_template_field','idx_action_logs_action'] as $token) {
    if (!str_contains($schema, $token)) throw new RuntimeException('Missing Action Hub schema contract: ' . $token);
}
$css = (string)file_get_contents($root . '/assets/css/action-hub.css');
$js = (string)file_get_contents($root . '/assets/js/action-hub.js');
if (!str_contains($js, 'window.Motion') || !str_contains($css, '@media(max-width:780px)')) throw new RuntimeException('Responsive Motion UI contract is missing.');

echo "Action Hub contract: PASS\n";
