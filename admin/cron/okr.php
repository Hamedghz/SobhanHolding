<?php
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Response.php';
require_once __DIR__ . '/../../services/OkrReminderService.php';

$cli = PHP_SAPI === 'cli';
$expected = (string)(getenv('SOBHAN_OKR_CRON_TOKEN') ?: setting('okr_cron_token', ''));
$provided = (string)($_SERVER['HTTP_X_OKR_CRON_TOKEN'] ?? $_GET['token'] ?? '');
if (!$cli && ($expected === '' || !hash_equals($expected, $provided))) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success'=>false,'message'=>'دسترسی غیرمجاز است.'], JSON_UNESCAPED_UNICODE);
    exit;
}
header('Content-Type: application/json; charset=utf-8');
try {
    $result = OkrReminderService::runMaintenance(1000);
    echo json_encode(['success'=>true,'data'=>$result], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    error_log('OKR cron: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'اجرای یادآوری‌های OKR ناموفق بود.'], JSON_UNESCAPED_UNICODE);
}
