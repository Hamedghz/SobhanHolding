<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';

Auth::requirePermission('accounting', 'view');
$id = (int)($_GET['id'] ?? 0);
$item = Database::fetch('SELECT image_path,mime_type,original_name FROM accounting_collections WHERE id = ? AND deleted_at IS NULL', [$id]);
if (!$item) {
    http_response_code(404);
    exit('تصویر پیدا نشد.');
}

$root = realpath(__DIR__ . '/..');
$uploadsRoot = realpath($root . '/uploads/accounting');
$path = realpath($root . $item['image_path']);
if (!$path || !$uploadsRoot || strncmp($path, $uploadsRoot, strlen($uploadsRoot)) !== 0 || !is_file($path)) {
    http_response_code(404);
    exit('تصویر پیدا نشد.');
}

header('Content-Type: ' . ($item['mime_type'] ?: 'image/jpeg'));
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline; filename="' . rawurlencode($item['original_name']) . '"');
header('X-Content-Type-Options: nosniff');
readfile($path);
