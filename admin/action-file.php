<?php

require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../services/ActionHubService.php';

Auth::requireLogin();
ActionHubService::boot();
$fieldId = max(0, (int)($_GET['id'] ?? 0));
$field = Database::fetch('SELECT action_id,file_path,file_name FROM action_field_values WHERE id=? AND file_path IS NOT NULL', [$fieldId]);
if (!$field || !ActionHubService::action((int)$field['action_id'], Auth::user())) {
    http_response_code(404);
    exit('فایل پیدا نشد.');
}
$root = realpath(dirname(__DIR__) . '/storage/action-files');
$path = realpath(dirname(__DIR__) . '/' . ltrim((string)$field['file_path'], '/\\'));
if (!$root || !$path || !str_starts_with(strtolower($path), strtolower($root . DIRECTORY_SEPARATOR)) || !is_file($path)) {
    http_response_code(404);
    exit('فایل پیدا نشد.');
}
header('Content-Type: application/octet-stream');
header('Content-Length: ' . filesize($path));
header("Content-Disposition: attachment; filename*=UTF-8''" . rawurlencode((string)($field['file_name'] ?: basename($path))));
header('X-Content-Type-Options: nosniff');
readfile($path);
exit;
