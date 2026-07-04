<?php

$root = dirname(__DIR__);
require_once $root . '/core/Response.php';

$success = json_envelope(true, ['item' => 1]);
if ($success['success'] !== true || $success['ok'] !== true || $success['error'] !== null) throw new RuntimeException('Success envelope is invalid.');
if (json_encode($success['meta']) !== '{}') throw new RuntimeException('Empty success meta must be a JSON object.');

$error = json_envelope(false, ['must_not_leak' => true], 'درخواست معتبر نیست.', 'VALIDATION_ERROR');
if ($error['success'] !== false || $error['ok'] !== false || $error['data'] !== null || $error['error'] !== 'VALIDATION_ERROR') throw new RuntimeException('Error envelope is invalid.');
if (json_encode($error['meta']) !== '{}') throw new RuntimeException('Empty error meta must be a JSON object.');

$response = file_get_contents($root . '/core/Response.php');
foreach (['json_response', 'json_success', 'json_error', 'json_require_method', 'json_require_csrf', 'CSRF_EXPIRED', '419', 'error_log'] as $token) {
    if (!str_contains($response, $token)) throw new RuntimeException("Missing response helper contract: {$token}");
}

$endpoints = [
    'dashboard-ceo.php',
    'users-table.php',
    'tickets-table.php',
    'kpi-results-table.php',
    'assessment-results-table.php',
    'planner-events.php',
];
foreach ($endpoints as $endpoint) {
    $path = $root . '/api/ui/' . $endpoint;
    if (!is_file($path)) throw new RuntimeException("Missing UI endpoint: {$endpoint}");
    $source = file_get_contents($path);
    foreach (['/_bootstrap.php', 'ui_run('] as $token) {
        if (!str_contains($source, $token)) throw new RuntimeException("{$endpoint} is missing {$token}");
    }
    if (preg_match('/\b(DROP|TRUNCATE|RENAME\s+TABLE)\b/i', $source)) throw new RuntimeException("Destructive SQL found in {$endpoint}");
}

$bootstrap = file_get_contents($root . '/api/ui/_bootstrap.php');
foreach (['UNAUTHENTICATED', '401', 'FORBIDDEN', '403', 'ui_pagination', 'INTERNAL_ERROR'] as $token) {
    if (!str_contains($bootstrap, $token)) throw new RuntimeException("Missing UI bootstrap contract: {$token}");
}

$users = file_get_contents($root . '/api/ui/users-table.php');
$kpi = file_get_contents($root . '/api/ui/kpi-results-table.php');
$assessments = file_get_contents($root . '/api/ui/assessment-results-table.php');
$planner = file_get_contents($root . '/api/ui/planner-events.php');
if (!str_contains($users, 'OrgAccess::scopeSql') || !str_contains($kpi, 'OrgAccess::scopeSql') || !str_contains($assessments, 'OrgAccess::scopeSql')) throw new RuntimeException('Scoped table endpoints must use OrgAccess.');
if (!str_contains($planner, "(int)\$user['id']") || str_contains($planner, "\$_GET['user_id']") || str_contains($planner, "\$_POST['user_id']")) throw new RuntimeException('Planner endpoint must use only the session user.');

$legacyPlanner = file_get_contents($root . '/admin/ajax/personal-planner.php');
$legacyMessenger = file_get_contents($root . '/api/messenger/bootstrap.php');
$aiPolling = file_get_contents($root . '/admin/actions/ai-update-status.php');
if (!str_contains($legacyPlanner, 'planner_json') || !str_contains($legacyMessenger, 'messenger_run') || !str_contains($aiPolling, "'job'=>\$job")) throw new RuntimeException('A legacy compatibility contract changed unexpectedly.');

echo "Unified API JSON contract checks passed.\n";
