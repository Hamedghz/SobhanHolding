<?php

require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../services/ActionHubService.php';

Auth::requireLogin();
ActionHubService::boot();

$scriptId = max(0, (int)($_GET['edit'] ?? 0));
$templateId = 0;
if ($scriptId > 0) {
    $template = Database::fetch(
        'SELECT id FROM action_templates
         WHERE legacy_source_type="sales_script" AND legacy_source_id=? LIMIT 1',
        [$scriptId]
    );
    $templateId = (int)($template['id'] ?? 0);
}

$destination = '/admin/action-templates.php?legacy=1';
if ($templateId > 0) $destination .= '&template_id=' . $templateId;
header('Location: ' . $destination, true, 302);
exit;
