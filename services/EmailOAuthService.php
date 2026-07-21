<?php

require_once __DIR__ . '/../core/EmailHubModule.php';

class EmailOAuthService
{
    public static function ensureAccessToken(array $account, bool $force = false): array
    {
        if (($account['auth_type'] ?? '') !== 'oauth2') return $account;
        $credentials = EmailHubModule::credentials($account);
        $expiresAt = trim((string)($account['access_token_expires_at'] ?? ''));
        $needsRefresh = $force || $expiresAt === '' || strtotime($expiresAt) <= time() + 120;
        if (!$needsRefresh && trim((string)$credentials['access_token']) !== '') return $account;
        if (trim((string)$credentials['refresh_token']) === '') throw new RuntimeException('email_oauth_reauthorization_required');

        $config = json_decode((string)($account['oauth_config_json'] ?? ''), true);
        if (!is_array($config)) $config = [];
        $tokenUrl = trim((string)($config['token_url'] ?? ''));
        $clientId = trim((string)($config['client_id'] ?? ''));
        $secret = EmailCrypto::decrypt($config['client_secret_encrypted'] ?? null);
        if (!self::safeTokenUrl($tokenUrl) || $clientId === '' || $secret === '') throw new RuntimeException('email_oauth_configuration_incomplete');

        $payload = http_build_query([
            'client_id' => $clientId,
            'client_secret' => $secret,
            'refresh_token' => $credentials['refresh_token'],
            'grant_type' => 'refresh_token',
        ], '', '&', PHP_QUERY_RFC3986);
        $body = self::post($tokenUrl, $payload);
        $response = json_decode($body, true);
        if (!is_array($response) || trim((string)($response['access_token'] ?? '')) === '') throw new RuntimeException('email_oauth_reauthorization_required');

        $accessToken = (string)$response['access_token'];
        $expiresIn = max(60, min(86400, (int)($response['expires_in'] ?? 3600)));
        $encryptedRefresh = !empty($response['refresh_token']) ? EmailCrypto::encrypt((string)$response['refresh_token']) : $account['encrypted_refresh_token'];
        Database::execute('UPDATE email_accounts SET encrypted_access_token=?,encrypted_refresh_token=?,access_token_expires_at=DATE_ADD(NOW(),INTERVAL ? SECOND),last_error=NULL,updated_at=NOW() WHERE id=?', [EmailCrypto::encrypt($accessToken), $encryptedRefresh, $expiresIn, (int)$account['id']]);
        EmailHubModule::log((int)$account['id'], null, 'oauth_token_refreshed', 'توکن اتصال ایمیل با موفقیت تمدید شد.');
        return EmailHubModule::account((int)$account['id']) ?? $account;
    }

    private static function safeTokenUrl(string $url): bool
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) return false;
        $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
        $host = strtolower((string)parse_url($url, PHP_URL_HOST));
        return $scheme === 'https' || ($scheme === 'http' && in_array($host, ['localhost', '127.0.0.1'], true));
    }

    private static function post(string $url, string $payload): string
    {
        if (function_exists('curl_init')) {
            $handle = curl_init($url);
            curl_setopt_array($handle, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload, CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_TIMEOUT => 20, CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded', 'Accept: application/json'], CURLOPT_PROTOCOLS => CURLPROTO_HTTPS | CURLPROTO_HTTP]);
            $body = curl_exec($handle);$status = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);$failed = $body === false;curl_close($handle);
            if ($failed || $status < 200 || $status >= 300) throw new RuntimeException($status === 400 || $status === 401 ? 'email_oauth_reauthorization_required' : 'email_oauth_refresh_failed');
            return (string)$body;
        }
        $context = stream_context_create(['http' => ['method' => 'POST', 'header' => "Content-Type: application/x-www-form-urlencoded\r\nAccept: application/json\r\n", 'content' => $payload, 'timeout' => 20, 'ignore_errors' => true]]);
        $body = file_get_contents($url, false, $context);
        if ($body === false) throw new RuntimeException('email_oauth_refresh_failed');
        return $body;
    }
}
