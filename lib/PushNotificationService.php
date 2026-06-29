<?php
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Response.php';

class PushNotificationService
{
    public static function publicKey(): string
    {
        $keys = self::vapidKeys();
        return (string)($keys['public'] ?? '');
    }

    public static function hasSupport(): bool
    {
        return function_exists('curl_init') && function_exists('openssl_pkey_new') && self::publicKey() !== '';
    }

    public static function sendToUser(int $userId, int $notificationId): array
    {
        if (!self::hasSupport()) {
            return ['attempted' => 0, 'sent' => 0, 'disabled' => true];
        }

        $subscriptions = Database::fetchAll(
            'SELECT * FROM sobhan_push_subscriptions WHERE user_id = ? AND active = 1 ORDER BY updated_at DESC',
            [$userId]
        );

        $sent = 0;
        foreach ($subscriptions as $subscription) {
            $ok = self::send((int)$subscription['id'], (string)$subscription['endpoint']);
            if ($ok) $sent++;
        }

        return ['attempted' => count($subscriptions), 'sent' => $sent, 'disabled' => false];
    }

    private static function send(int $subscriptionId, string $endpoint): bool
    {
        $keys = self::vapidKeys();
        $jwt = self::vapidJwt($endpoint, (string)$keys['private']);
        if ($jwt === '') return false;

        $ch = curl_init($endpoint);
        if (!$ch) return false;

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_POSTFIELDS => '',
            CURLOPT_HTTPHEADER => [
                'TTL: 180',
                'Content-Length: 0',
                'Authorization: vapid t=' . $jwt . ', k=' . $keys['public'],
            ],
        ]);

        curl_exec($ch);
        $error = curl_error($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if (in_array($status, [200, 201, 202, 204], true)) {
            Database::execute(
                'UPDATE sobhan_push_subscriptions SET last_success_at = NOW(), last_error = NULL, updated_at = NOW() WHERE id = ?',
                [$subscriptionId]
            );
            return true;
        }

        if (in_array($status, [404, 410], true)) {
            Database::execute(
                'UPDATE sobhan_push_subscriptions SET active = 0, last_error = ?, updated_at = NOW() WHERE id = ?',
                ['اشتراک push منقضی شده است.', $subscriptionId]
            );
            return false;
        }

        Database::execute(
            'UPDATE sobhan_push_subscriptions SET last_error = ?, updated_at = NOW() WHERE id = ?',
            [substr($error !== '' ? $error : ('HTTP ' . $status), 0, 250), $subscriptionId]
        );
        return false;
    }

    private static function vapidKeys(): array
    {
        static $keys = null;
        if ($keys !== null) return $keys;

        $public = setting('notification_vapid_public_key', '');
        $private = setting('notification_vapid_private_key', '');
        if ($public !== '' && $private !== '') {
            $keys = ['public' => $public, 'private' => $private];
            return $keys;
        }

        $generated = self::generateVapidKeys();
        if (!$generated) {
            $keys = ['public' => '', 'private' => ''];
            return $keys;
        }

        $stmt = Database::connection()->prepare(
            'INSERT INTO site_settings (setting_key, setting_value, setting_type, updated_at) VALUES (?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), setting_type = VALUES(setting_type), updated_at = NOW()'
        );
        $stmt->execute(['notification_vapid_public_key', $generated['public'], 'textarea']);
        $stmt->execute(['notification_vapid_private_key', $generated['private'], 'textarea']);

        $keys = $generated;
        return $keys;
    }

    private static function generateVapidKeys(): ?array
    {
        if (!function_exists('openssl_pkey_new')) return null;

        $resource = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name' => 'prime256v1',
        ]);
        if (!$resource) return null;

        $privatePem = '';
        if (!openssl_pkey_export($resource, $privatePem)) return null;
        $details = openssl_pkey_get_details($resource);
        $ec = $details['ec'] ?? [];
        $x = $ec['x'] ?? '';
        $y = $ec['y'] ?? '';
        if ($x === '' || $y === '') return null;

        $rawPublic = "\x04" . str_pad($x, 32, "\0", STR_PAD_LEFT) . str_pad($y, 32, "\0", STR_PAD_LEFT);
        return ['public' => self::base64Url($rawPublic), 'private' => $privatePem];
    }

    private static function vapidJwt(string $endpoint, string $privatePem): string
    {
        $origin = parse_url($endpoint, PHP_URL_SCHEME) . '://' . parse_url($endpoint, PHP_URL_HOST);
        $port = parse_url($endpoint, PHP_URL_PORT);
        if ($port) $origin .= ':' . $port;

        $subject = setting('notification_vapid_subject', '');
        if ($subject === '') {
            $email = setting('admin_email', 'admin@sobhan.local');
            $subject = str_starts_with($email, 'mailto:') ? $email : 'mailto:' . $email;
        }

        $header = self::base64Url(json_encode(['typ' => 'JWT', 'alg' => 'ES256'], JSON_UNESCAPED_SLASHES));
        $payload = self::base64Url(json_encode([
            'aud' => $origin,
            'exp' => time() + 3600,
            'sub' => $subject,
        ], JSON_UNESCAPED_SLASHES));
        $data = $header . '.' . $payload;

        $signature = '';
        if (!openssl_sign($data, $signature, $privatePem, OPENSSL_ALGO_SHA256)) return '';
        $rawSignature = self::derToJose($signature, 64);
        if ($rawSignature === '') return '';

        return $data . '.' . self::base64Url($rawSignature);
    }

    private static function derToJose(string $der, int $partLength): string
    {
        $offset = 0;
        if (ord($der[$offset++]) !== 0x30) return '';
        self::readAsnLength($der, $offset);
        if (ord($der[$offset++]) !== 0x02) return '';
        $rLength = self::readAsnLength($der, $offset);
        $r = substr($der, $offset, $rLength);
        $offset += $rLength;
        if (ord($der[$offset++]) !== 0x02) return '';
        $sLength = self::readAsnLength($der, $offset);
        $s = substr($der, $offset, $sLength);

        $r = str_pad(ltrim($r, "\0"), $partLength / 2, "\0", STR_PAD_LEFT);
        $s = str_pad(ltrim($s, "\0"), $partLength / 2, "\0", STR_PAD_LEFT);
        return $r . $s;
    }

    private static function readAsnLength(string $data, int &$offset): int
    {
        $length = ord($data[$offset++]);
        if ($length < 0x80) return $length;

        $bytes = $length & 0x7f;
        $length = 0;
        for ($i = 0; $i < $bytes; $i++) {
            $length = ($length << 8) | ord($data[$offset++]);
        }
        return $length;
    }

    public static function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
