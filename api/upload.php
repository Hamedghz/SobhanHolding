<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Upload.php';
header('Content-Type: application/json; charset=utf-8');
Auth::requireLogin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Auth::verifyCsrf($_POST['csrf_token'] ?? '')) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'درخواست نامعتبر است'], JSON_UNESCAPED_UNICODE); exit; }
$user = Auth::user();
if (!Auth::can('files', 'create')) { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'دسترسی غیرمجاز'], JSON_UNESCAPED_UNICODE); exit; }
$maxSize = $user['upload_quota_mb'] === null ? null : (int)$user['upload_quota_mb'] * 1024 * 1024;
$up = Upload::save($_FILES['file'] ?? [], 'uploads/files', null, $maxSize);
if (!$up['ok']) { http_response_code(422); echo json_encode($up, JSON_UNESCAPED_UNICODE); exit; }
$visibility = ($_POST['visibility'] ?? 'private') === 'shared' ? 'shared' : 'private';
Database::execute('INSERT INTO user_files (user_id,original_name,stored_name,file_path,mime_type,file_size,visibility,created_at) VALUES (?,?,?,?,?,?,?,NOW())',[$user['id'],$up['original_name'],$up['stored_name'],$up['file_path'],$up['mime_type'],$up['file_size'],$visibility]);
$fileId = (int)Database::lastInsertId();
if ($visibility === 'shared') {
    foreach ($_POST['shared_users'] ?? [] as $sharedUserId) {
        if ((int)$sharedUserId !== (int)$user['id']) {
            Database::execute('INSERT IGNORE INTO file_shares (file_id,shared_with_user_id,shared_by,created_at) VALUES (?,?,?,NOW())',[$fileId,(int)$sharedUserId,$user['id']]);
        }
    }
}
echo json_encode(['ok'=>true,'file'=>$up], JSON_UNESCAPED_UNICODE);
