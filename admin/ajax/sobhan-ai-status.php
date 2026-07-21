<?php
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/SobhanAiStatus.php';

header('Content-Type: application/json; charset=utf-8');
try {
    Auth::requireLogin();
    $status = SobhanAiStatus::current(true);
    echo json_encode(['success' => true, 'data' => $status], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $error) {
    error_log('Sobhan AI header status: ' . $error->getMessage());
    http_response_code(503);
    echo json_encode(['success' => false, 'data' => ['healthy' => false, 'configured' => false, 'checked_at' => null, 'last_success_at' => null]], JSON_UNESCAPED_UNICODE);
}
