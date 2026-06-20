<?php
require_once __DIR__.'/../../core/Auth.php';require_once __DIR__.'/../../core/Response.php';require_once __DIR__.'/../../core/AiUpdateService.php';
header('Content-Type: application/json; charset=utf-8');Auth::requireLogin();if(!Auth::isAdmin()&&!Auth::can('ai_updates')){http_response_code(403);echo json_encode(['ok'=>false,'message'=>'دسترسی غیرمجاز است.'],JSON_UNESCAPED_UNICODE);exit;}
try{$job=AiUpdateService::refreshStatus((int)($_GET['id']??0));echo json_encode(['ok'=>(bool)$job,'job'=>$job],JSON_UNESCAPED_UNICODE);}catch(Throwable $e){error_log('AI status action: '.$e->getMessage());echo json_encode(['ok'=>false,'message'=>'دریافت وضعیت ناموفق بود.'],JSON_UNESCAPED_UNICODE);}
