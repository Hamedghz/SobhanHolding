<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';

Auth::requirePermission('files', 'view');
$user = Auth::user();
$fileId = (int)($_GET['id'] ?? 0);

$file = Database::fetch('SELECT * FROM user_files WHERE id = ?', [$fileId]);
if (!$file) {
    http_response_code(404);
    echo 'فایل پیدا نشد.';
    exit;
}

$allowed = Auth::isAdmin() || (int)$file['user_id'] === (int)$user['id'];
if (!$allowed) {
    $allowed = (bool)Database::fetch('SELECT id FROM file_shares WHERE file_id = ? AND shared_with_user_id = ? LIMIT 1', [$fileId, $user['id']]);
}
if (!$allowed && Auth::isManager()) {
    $allowed = (bool)Database::fetch('SELECT id FROM manager_employees WHERE manager_id = ? AND employee_id = ? LIMIT 1', [$user['id'], $file['user_id']]);
}
if (!$allowed) {
    http_response_code(403);
    echo 'دسترسی غیرمجاز';
    exit;
}

$root = realpath(__DIR__ . '/..');
$path = realpath($root . $file['file_path']);
$uploadsRoot = realpath($root . '/uploads/files');
if (!$path || !$uploadsRoot || strncmp($path, $uploadsRoot, strlen($uploadsRoot)) !== 0 || !is_file($path)) {
    http_response_code(404);
    echo 'فایل پیدا نشد.';
    exit;
}

header('Content-Type: ' . ($file['mime_type'] ?: 'application/octet-stream'));
header('Content-Length: ' . filesize($path));
header('Content-Disposition: attachment; filename="' . rawurlencode($file['original_name']) . '"');
header('X-Content-Type-Options: nosniff');
readfile($path);
