<?php
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Response.php';
header('Content-Type: application/json; charset=utf-8');
try {
    echo json_encode(['ok'=>true,'items'=>Database::fetchAll('SELECT title,description,image_path,button_text,button_link FROM carousel_items WHERE status="active" ORDER BY sort_order ASC,id DESC')], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>'خطا در دریافت اطلاعات'], JSON_UNESCAPED_UNICODE); }
