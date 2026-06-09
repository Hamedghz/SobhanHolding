<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Upload.php';
header('Content-Type: application/json; charset=utf-8');
Auth::requireLogin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Auth::verifyCsrf($_POST['csrf_token'] ?? '')) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'درخواست نامعتبر است'], JSON_UNESCAPED_UNICODE); exit; }
$up = Upload::save($_FILES['file'] ?? [], 'uploads/files');
if (!$up['ok']) { http_response_code(422); echo json_encode($up, JSON_UNESCAPED_UNICODE); exit; }
$user = Auth::user();
Database::execute('INSERT INTO user_files (user_id,original_name,stored_name,file_path,mime_type,file_size,created_at) VALUES (?,?,?,?,?,?,NOW())',[$user['id'],$up['original_name'],$up['stored_name'],$up['file_path'],$up['mime_type'],$up['file_size']]);
echo json_encode(['ok'=>true,'file'=>$up], JSON_UNESCAPED_UNICODE);
