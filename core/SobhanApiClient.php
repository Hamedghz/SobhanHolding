<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Response.php';

class SobhanApiClient
{
    private string $baseUrl;
    private string $apiKey;
    private int $timeout;
    private bool $enabled;

    public function __construct(?string $baseUrl = null, ?string $apiKey = null, ?int $timeout = null, ?bool $enabled = null)
    {
        $this->baseUrl = rtrim(trim((string)($baseUrl ?? setting('sobhan_api_base_url', ''))), '/');
        $this->apiKey = trim((string)($apiKey ?? setting('sobhan_api_key', '')));
        $this->timeout = max(1, min(60, (int)($timeout ?? setting('sobhan_api_timeout', '10'))));
        $this->enabled = $enabled ?? setting('sobhan_api_enabled', '0') === '1';
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function get(string $path, array $query = []): array
    {
        return $this->request('GET', $path, $query);
    }

    public function post(string $path, array $payload = []): array
    {
        return $this->request('POST', $path, [], $payload);
    }

    public static function maskKey(string $value): string
    {
        $value = trim($value);
        if ($value === '') return '';
        $suffix = function_exists('mb_substr') ? mb_substr($value, -3, null, 'UTF-8') : substr($value, -3);
        return '********' . $suffix;
    }

    private function request(string $method, string $path, array $query = [], array $payload = []): array
    {
        if (!$this->enabled) {
            return $this->error('سرویس گزارش‌گیری سبحان غیرفعال است.', 'API disabled', 0);
        }
        if ($this->baseUrl === '' || $this->apiKey === '') {
            return $this->error('تنظیمات اتصال به API کامل نیست.', 'Missing base URL or API key', 0);
        }
        if (!function_exists('curl_init')) {
            return $this->error('امکان اتصال cURL روی سرور فعال نیست.', 'cURL extension is not available', 0);
        }

        $url = $this->baseUrl . '/' . ltrim($path, '/');
        if ($query) {
            $url .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }

        $headers = [
            'Accept: application/json',
            'X-API-Key: ' . $this->apiKey,
        ];
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => $this->timeout,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CUSTOMREQUEST => $method,
        ]);

        if ($method === 'POST') {
            $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body === false ? '{}' : $body);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge($headers, ['Content-Type: application/json']));
        }

        $raw = curl_exec($ch);
        $curlError = curl_error($ch);
        $curlNo = curl_errno($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($raw === false) {
            if (in_array($curlNo, [CURLE_OPERATION_TIMEDOUT, CURLE_COULDNT_CONNECT, CURLE_COULDNT_RESOLVE_HOST], true)) {
                $message = $curlNo === CURLE_OPERATION_TIMEDOUT ? 'پاسخ‌گویی سرویس بیش از حد طول کشید.' : 'اتصال به سرویس گزارش‌گیری سبحان برقرار نشد.';
                return $this->error($message, $curlError, $status);
            }
            return $this->error('اتصال به سرویس گزارش‌گیری سبحان برقرار نشد.', $curlError, $status);
        }

        $decoded = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->error('پاسخ دریافتی از سرویس معتبر نیست.', json_last_error_msg(), $status);
        }

        if ($status >= 400) {
            $message = in_array($status, [401, 403], true)
                ? 'کلید اتصال به API نامعتبر است.'
                : 'اتصال به سرویس گزارش‌گیری سبحان برقرار نشد.';
            return $this->error($message, 'HTTP ' . $status, $status, $decoded);
        }

        return [
            'ok' => true,
            'data' => $decoded,
            'error' => null,
            'status' => $status,
        ];
    }

    private function error(string $messageFa, string $technical, int $status = 0, mixed $data = null): array
    {
        return [
            'ok' => false,
            'data' => $data,
            'error' => [
                'message_fa' => $messageFa,
                'technical' => $technical,
            ],
            'status' => $status,
        ];
    }
}
