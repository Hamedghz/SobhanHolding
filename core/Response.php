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

function format_percent($value, int $decimals = 0): string
{
    return number_format((float)$value, $decimals, '.', ',') . '%';
}
