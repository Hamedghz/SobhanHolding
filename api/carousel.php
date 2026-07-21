<?php
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/CarouselModule.php';
header('Content-Type: application/json; charset=utf-8');
try {
    echo json_encode(['success'=>true,'message'=>'اسلایدها دریافت شدند.','data'=>['items'=>CarouselModule::publicItems()],'meta'=>[],'error'=>null], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) { error_log('Carousel API ['.get_class($e).']');http_response_code(500);echo json_encode(['success'=>false,'message'=>'دریافت اسلایدها امکان‌پذیر نبود.','data'=>null,'meta'=>[],'error'=>['code'=>'CAROUSEL_LOAD_FAILED']],JSON_UNESCAPED_UNICODE); }
