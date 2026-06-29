<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../lib/NotificationService.php';

header('Content-Type: application/json; charset=utf-8');

function sobhan_push_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    Auth::requireLogin();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') sobhan_push_json(['ok' => false, 'message' => 'روش درخواست نامعتبر است.'], 405);
    if (!Auth::verifyCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? ''))) {
        sobhan_push_json(['ok' => false, 'message' => 'درخواست نامعتبر است.'], 403);
    }

    $user = Auth::user();
    $input = json_decode(file_get_contents('php://input') ?: '{}', true);
    if (!is_array($input)) $input = $_POST;

    $endpoint = trim((string)($input['endpoint'] ?? ''));
    $keys = is_array($input['keys'] ?? null) ? $input['keys'] : [];
    $p256dh = trim((string)($keys['p256dh'] ?? ''));
    $authKey = trim((string)($keys['auth'] ?? ''));
    $encoding = trim((string)($input['contentEncoding'] ?? $input['content_encoding'] ?? 'aes128gcm'));

    if ($endpoint === '' || !filter_var($endpoint, FILTER_VALIDATE_URL) || !str_starts_with($endpoint, 'https://')) {
        sobhan_push_json(['ok' => false, 'message' => 'اشتراک اعلان معتبر نیست.'], 422);
    }

    $hash = hash('sha256', $endpoint);
    Database::execute(
        'INSERT INTO sobhan_push_subscriptions
         (user_id,endpoint,endpoint_hash,p256dh,auth_key,content_encoding,user_agent,active,created_at,updated_at)
         VALUES (?,?,?,?,?,?,?,1,NOW(),NOW())
         ON DUPLICATE KEY UPDATE user_id=VALUES(user_id),p256dh=VALUES(p256dh),auth_key=VALUES(auth_key),content_encoding=VALUES(content_encoding),user_agent=VALUES(user_agent),active=1,last_error=NULL,updated_at=NOW()',
        [(int)$user['id'], $endpoint, $hash, $p256dh ?: null, $authKey ?: null, $encoding ?: 'aes128gcm', substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)]
    );

    $settings = NotificationService::settings((int)$user['id']);
    if (!$settings['push_enabled']) {
        $settings['push_enabled'] = 1;
        NotificationService::saveSettings((int)$user['id'], $settings);
    }

    sobhan_push_json(['ok' => true, 'message' => 'اعلان این دستگاه فعال شد.']);
} catch (Throwable $e) {
    error_log('push-subscribe: ' . $e->getMessage());
    sobhan_push_json(['ok' => false, 'message' => 'فعال‌سازی اعلان انجام نشد.'], 500);
}
