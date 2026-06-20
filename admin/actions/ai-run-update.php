<?php
require_once __DIR__.'/../../core/Auth.php';require_once __DIR__.'/../../core/Response.php';require_once __DIR__.'/../../core/AiUpdateService.php';
header('Content-Type: application/json; charset=utf-8');Auth::requireLogin();
if(!Auth::isAdmin()&&!Auth::can('ai_updates')){http_response_code(403);echo json_encode(['ok'=>false,'message'=>'دسترسی غیرمجاز است.'],JSON_UNESCAPED_UNICODE);exit;}
if($_SERVER['REQUEST_METHOD']!=='POST'||!Auth::verifyCsrf($_POST['csrf_token']??'')){http_response_code(419);echo json_encode(['ok'=>false,'message'=>'درخواست نامعتبر است.'],JSON_UNESCAPED_UNICODE);exit;}
try{$job=AiUpdateService::createAndRun((string)($_POST['job_type']??'full_update'),(int)Auth::user()['id']);echo json_encode(['ok'=>$job['status']!=='failed','job'=>$job],JSON_UNESCAPED_UNICODE);}catch(Throwable $e){error_log('AI update action: '.$e->getMessage());echo json_encode(['ok'=>false,'message'=>'اجرای بروزرسانی ناموفق بود.'],JSON_UNESCAPED_UNICODE);}
