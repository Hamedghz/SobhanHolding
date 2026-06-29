<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../lib/NotificationService.php';

header('Content-Type: application/json; charset=utf-8');

function sobhan_notifications_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    Auth::requireLogin();
    $user = Auth::user();
    $limit = max(1, min(50, (int)($_GET['limit'] ?? 10)));
    $unreadOnly = (int)($_GET['unread'] ?? 0) === 1;
    $items = NotificationService::listForUser((int)$user['id'], $limit, $unreadOnly);

    sobhan_notifications_json([
        'ok' => true,
        'unread_count' => NotificationService::unreadCount((int)$user['id']),
        'items' => array_map(static function (array $item): array {
            return [
                'id' => (int)$item['id'],
                'event_type' => $item['event_type'],
                'title' => $item['title'],
                'body' => $item['body'],
                'action_url' => $item['action_url'] ?: '/admin/notification-settings.php',
                'priority' => $item['priority'],
                'status' => $item['status'],
                'created_at' => $item['created_at'],
                'created_at_fa' => format_jalali_datetime($item['created_at']),
            ];
        }, $items),
    ]);
} catch (Throwable $e) {
    error_log('notifications-api: ' . $e->getMessage());
    sobhan_notifications_json(['ok' => false, 'message' => 'دریافت اعلان‌ها انجام نشد.', 'unread_count' => 0, 'items' => []], 500);
}
