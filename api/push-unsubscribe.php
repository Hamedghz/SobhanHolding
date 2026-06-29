<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';

header('Content-Type: application/json; charset=utf-8');

function sobhan_push_unsub_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    Auth::requireLogin();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') sobhan_push_unsub_json(['ok' => false, 'message' => 'روش درخواست نامعتبر است.'], 405);
    if (!Auth::verifyCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? ''))) {
        sobhan_push_unsub_json(['ok' => false, 'message' => 'درخواست نامعتبر است.'], 403);
    }

    $user = Auth::user();
    $input = json_decode(file_get_contents('php://input') ?: '{}', true);
    $endpoint = is_array($input) ? trim((string)($input['endpoint'] ?? '')) : '';

    if ($endpoint !== '') {
        Database::execute('UPDATE sobhan_push_subscriptions SET active = 0, updated_at = NOW() WHERE user_id = ? AND endpoint_hash = ?', [(int)$user['id'], hash('sha256', $endpoint)]);
    } else {
        Database::execute('UPDATE sobhan_push_subscriptions SET active = 0, updated_at = NOW() WHERE user_id = ?', [(int)$user['id']]);
    }

    sobhan_push_unsub_json(['ok' => true, 'message' => 'اعلان این دستگاه غیرفعال شد.']);
} catch (Throwable $e) {
    error_log('push-unsubscribe: ' . $e->getMessage());
    sobhan_push_unsub_json(['ok' => false, 'message' => 'غیرفعال‌سازی اعلان انجام نشد.'], 500);
}
