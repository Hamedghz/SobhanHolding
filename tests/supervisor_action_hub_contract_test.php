<?php

$root = dirname(__DIR__);
$required = [
    'admin/supervisor-dashboard.php',
    'admin/supervisor-actions.php',
    'admin/supervisor-action-view.php',
    'services/ActionHubService.php',
    'lib/OrgAccess.php',
    'assets/css/supervisor-action-hub.css',
    'assets/js/action-hub.js',
    'docs/supervisor-dashboard-actions.md',
    'tests/fixtures/supervisor-action-hub.html',
];
foreach ($required as $file) {
    if (!is_file($root . '/' . $file)) throw new RuntimeException('Missing supervisor Action Hub file: ' . $file);
}

$dashboard = (string)file_get_contents($root . '/admin/supervisor-dashboard.php');
foreach ([
    'ActionHubService::createAction',
    'ActionHubService::teamActions',
    'ActionHubService::teamStats',
    'SalesOperationsService::getSupervisorVisitors',
    'SalesOperationsService::assertVisitorBelongsToSupervisor',
    'Auth::verifyCsrf',
    "Auth::can('supervisor.actions.manage')",
    '$actionInput[\'source_type\'] = \'manual\'',
    '$actionInput[\'status\'] = \'new\'',
    "app_date_input('due_date'",
    'data-action-type',
    'data-action-template',
    'data-action-dynamic',
    'add_to_planner',
    'سررسید گذشته',
    'تکمیل‌شده',
] as $token) {
    if (!str_contains($dashboard, $token)) throw new RuntimeException('Missing supervisor dashboard action contract: ' . $token);
}
foreach (['FROM supervisor_actions','dynamic_values_json','json_decode('] as $forbidden) {
    if (str_contains($dashboard, $forbidden)) throw new RuntimeException('Legacy or raw JSON dashboard behavior remains: ' . $forbidden);
}

$service = (string)file_get_contents($root . '/services/ActionHubService.php');
foreach (['function teamActions','function teamStats','OrgAccess::accessibleUserIds'] as $token) {
    if (!str_contains($service, $token)) throw new RuntimeException('Missing team-scoped Action Hub service contract: ' . $token);
}

$orgAccess = (string)file_get_contents($root . '/lib/OrgAccess.php');
foreach (['supervisor_id IN','sales_team_assignments','active=1'] as $token) {
    if (!str_contains($orgAccess, $token)) throw new RuntimeException('Missing supervisor hierarchy scope contract: ' . $token);
}

$legacyList = (string)file_get_contents($root . '/admin/supervisor-actions.php');
if (!str_contains($legacyList, '/admin/supervisor-dashboard.php?action_panel=1#supervisor-actions') || !str_contains($legacyList, 'exit;')) {
    throw new RuntimeException('Legacy supervisor action list route is not preserved by redirect.');
}
foreach (['SalesOperationsService','FROM supervisor_actions','dynamic_values_json','اسکریپت فروش'] as $forbidden) {
    if (str_contains($legacyList, $forbidden)) throw new RuntimeException('Unreachable legacy supervisor UI remains: ' . $forbidden);
}
$legacyView = (string)file_get_contents($root . '/admin/supervisor-action-view.php');
foreach (["mirrorLegacyAction('supervisor_actions'", '/admin/action-view.php?id=', 'exit;'] as $token) {
    if (!str_contains($legacyView, $token)) throw new RuntimeException('Legacy supervisor action detail mapping is incomplete: ' . $token);
}
foreach (['dynamic_values_json','json_decode(','UPDATE supervisor_actions'] as $forbidden) {
    if (str_contains($legacyView, $forbidden)) throw new RuntimeException('Unreachable legacy action detail UI remains: ' . $forbidden);
}

$managerReport = (string)file_get_contents($root . '/admin/sales-manager-supervisor-reports.php');
foreach (["ActionHubService::mirrorLegacyAction('supervisor_actions'", 'گزارش‌های اجرای قالب اقدام فروش'] as $token) {
    if (!str_contains($managerReport, $token)) throw new RuntimeException('Manager report Action Hub sync contract is missing: ' . $token);
}

$menu = (string)file_get_contents($root . '/lib/admin_menu.php');
if (!str_contains($menu, "'title' => 'اقدامات تیم'")) throw new RuntimeException('Supervisor team actions menu title is missing.');

$js = (string)file_get_contents($root . '/assets/js/action-hub.js');
$css = (string)file_get_contents($root . '/assets/css/supervisor-action-hub.css');
foreach (['window.Motion','prefers-reduced-motion','dataset.actionAutoTemplate'] as $token) {
    if (!str_contains($js . $css, $token)) throw new RuntimeException('Motion/dynamic UI contract is missing: ' . $token);
}
if (!str_contains($css, '@media(max-width:720px)')) throw new RuntimeException('Supervisor Action Hub mobile contract is missing.');

foreach ([$dashboard,$service,$orgAccess] as $source) {
    if (preg_match('/\b(DROP|TRUNCATE|DELETE\s+FROM)\b/i', $source)) throw new RuntimeException('Destructive statement found in Phase 9.');
}

echo "Supervisor Action Hub contract: PASS\n";
