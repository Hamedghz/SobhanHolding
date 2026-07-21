<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../services/ActionHubService.php';

Auth::requireLogin();
ActionHubService::boot();

$legacyId = (int)($_GET['id'] ?? 0);
$actionId = $legacyId > 0
    ? ActionHubService::mirrorLegacyAction('supervisor_actions', $legacyId)
    : null;

if (!$actionId) {
    http_response_code(404);
    exit('اقدام پیدا نشد.');
}

header('Location: /admin/action-view.php?id=' . $actionId, true, 302);
exit;
