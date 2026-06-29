<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../lib/NotificationService.php';

header('Content-Type: application/json; charset=utf-8');

function sobhan_notifications_read_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    Auth::requireLogin();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') sobhan_notifications_read_json(['ok' => false, 'message' => 'روش درخواست نامعتبر است.'], 405);
    if (!Auth::verifyCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? ''))) {
        sobhan_notifications_read_json(['ok' => false, 'message' => 'درخواست نامعتبر است.'], 403);
    }

    $user = Auth::user();
    $input = json_decode(file_get_contents('php://input') ?: '{}', true);
    if (!is_array($input)) $input = $_POST;
    $all = !empty($input['all']);
    $id = (int)($input['id'] ?? 0);

    if ($all) {
        NotificationService::markAllAsRead((int)$user['id']);
    } elseif ($id > 0) {
        NotificationService::markAsRead((int)$user['id'], $id);
    } else {
        sobhan_notifications_read_json(['ok' => false, 'message' => 'شناسه اعلان معتبر نیست.'], 422);
    }

    sobhan_notifications_read_json(['ok' => true, 'unread_count' => NotificationService::unreadCount((int)$user['id'])]);
} catch (Throwable $e) {
    error_log('notifications-read-api: ' . $e->getMessage());
    sobhan_notifications_read_json(['ok' => false, 'message' => 'ثبت وضعیت خوانده‌شده انجام نشد.'], 500);
}
