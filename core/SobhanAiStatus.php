<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Response.php';
require_once __DIR__ . '/SobhanApiClient.php';

final class SobhanAiStatus
{
    private const CACHE_KEY = 'sobhan_ai_header_status_cache';
    private const TTL_SECONDS = 60;

    public static function cached(): array
    {
        try {
            $row = Database::fetch('SELECT setting_value FROM site_settings WHERE setting_key=? LIMIT 1', [self::CACHE_KEY]);
            $data = json_decode((string)($row['setting_value'] ?? ''), true);
            if (!is_array($data)) return self::emptyStatus();
            return array_replace(self::emptyStatus(), array_intersect_key($data, self::emptyStatus()));
        } catch (Throwable) {
            return self::emptyStatus();
        }
    }

    public static function current(bool $refreshIfStale = true): array
    {
        $cached = self::cached();
        $checkedAt = strtotime((string)($cached['checked_at'] ?? '')) ?: 0;
        if (!$refreshIfStale || $checkedAt >= time() - self::TTL_SECONDS) return $cached;
        return self::refresh();
    }

    public static function refresh(): array
    {
        $baseUrl = rtrim(setting('sobhan_ai_model_api_url', setting('sobhan_api_base_url', '')), '/');
        $enabled = setting('sobhan_ai_model_api_enabled', setting('sobhan_api_enabled', '0')) === '1';
        $apiKey = setting('sobhan_api_key', '');
        $previous = self::cached();
        $healthy = false;
        if ($enabled && $baseUrl !== '' && filter_var($baseUrl, FILTER_VALIDATE_URL) && $apiKey !== '') {
            $result = (new SobhanApiClient($baseUrl, $apiKey, 2, true))->get('/health');
            $healthy = (bool)($result['ok'] ?? false);
        }
        $payload = [
            'healthy' => $healthy,
            'configured' => $enabled && $baseUrl !== '' && $apiKey !== '',
            'checked_at' => date('Y-m-d H:i:s'),
            'last_success_at' => $healthy ? date('Y-m-d H:i:s') : ($previous['last_success_at'] ?? null),
        ];
        try {
            Database::execute(
                'INSERT INTO site_settings(setting_key,setting_value,setting_type,updated_at) VALUES(?,?,"json",NOW()) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),setting_type="json",updated_at=NOW()',
                [self::CACHE_KEY, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]
            );
        } catch (Throwable $error) {
            error_log('Sobhan AI status cache: ' . $error->getMessage());
        }
        return $payload;
    }

    private static function emptyStatus(): array
    {
        return ['healthy' => false, 'configured' => false, 'checked_at' => null, 'last_success_at' => null];
    }
}
