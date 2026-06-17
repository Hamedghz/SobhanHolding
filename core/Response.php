<?php
function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function flash(?string $message = null, string $type = 'success'): ?array
{
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    if ($message !== null) {
        $_SESSION['flash'] = ['message' => $message, 'type' => $type];
        return null;
    }
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

function setting(string $key, string $default = ''): string
{
    static $settings = null;
    if ($settings === null) {
        try {
            $rows = Database::fetchAll('SELECT setting_key, setting_value FROM site_settings');
            $settings = array_column($rows, 'setting_value', 'setting_key');
        } catch (Throwable $e) {
            $settings = [];
        }
    }
    return $settings[$key] ?? $default;
}

function format_number($value): string
{
    return number_format((float)$value, 0, '.', ',');
}

function format_money($value): string
{
    return format_number($value);
}

function format_large_number($value): string
{
    $number = (float)$value;
    $abs = abs($number);
    if ($abs >= 1000000000) {
        $short = $number / 1000000000;
        return rtrim(rtrim(number_format($short, $short >= 100 ? 0 : 1, '.', ','), '0'), '.') . ' میلیارد';
    }
    if ($abs >= 1000000) {
        $short = $number / 1000000;
        return rtrim(rtrim(number_format($short, $short >= 100 ? 0 : 1, '.', ','), '0'), '.') . ' میلیون';
    }
    return format_number($number);
}

function format_percent($value, int $decimals = 0): string
{
    return number_format((float)$value, $decimals, '.', ',') . '%';
}

function format_jalali_date(?string $value): string
{
    require_once __DIR__ . '/JalaliDate.php';
    return JalaliDate::toJalali($value);
}

function format_jalali_datetime(?string $value): string
{
    require_once __DIR__ . '/JalaliDate.php';
    return JalaliDate::toJalaliDateTime($value);
}

function jalali_input_value(?string $value): string
{
    require_once __DIR__ . '/JalaliDate.php';
    return JalaliDate::inputValue($value);
}
