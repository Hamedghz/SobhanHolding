<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Response.php';

class Pwa
{
    public const DISPLAY_OPTIONS = ['standalone', 'fullscreen', 'minimal-ui', 'browser'];
    public const ORIENTATION_OPTIONS = ['portrait', 'landscape', 'any'];
    public const IMAGE_EXTENSIONS = ['png', 'jpg', 'jpeg', 'webp'];
    public const MAX_IMAGE_SIZE = 2097152;

    public static function fields(): array
    {
        return [
            'pwa_name' => ['label' => 'نام برنامه', 'type' => 'text', 'default' => setting('site_title', 'شرکت پخش سبحان')],
            'pwa_short_name' => ['label' => 'نام کوتاه برنامه', 'type' => 'text', 'default' => setting('company_name', 'سبحان')],
            'pwa_description' => ['label' => 'توضیح برنامه', 'type' => 'textarea', 'default' => setting('meta_description', 'سامانه هلدینگ سبحان')],
            'pwa_theme_color' => ['label' => 'رنگ اصلی / Theme Color', 'type' => 'color', 'default' => '#004647'],
            'pwa_background_color' => ['label' => 'رنگ پس‌زمینه / Background Color', 'type' => 'color', 'default' => '#ffffff'],
            'pwa_start_url' => ['label' => 'آدرس شروع برنامه', 'type' => 'text', 'default' => '/'],
            'pwa_display' => ['label' => 'حالت نمایش', 'type' => 'select', 'default' => 'standalone', 'options' => self::DISPLAY_OPTIONS],
            'pwa_orientation' => ['label' => 'جهت نمایش', 'type' => 'select', 'default' => 'portrait', 'options' => self::ORIENTATION_OPTIONS],
            'pwa_icon_192' => ['label' => 'آیکون 192x192', 'type' => 'image', 'default' => ''],
            'pwa_icon_512' => ['label' => 'آیکون 512x512', 'type' => 'image', 'default' => ''],
            'pwa_favicon' => ['label' => 'favicon', 'type' => 'image', 'default' => ''],
        ];
    }

    public static function value(string $key): string
    {
        $field = self::fields()[$key] ?? ['default' => ''];
        return setting($key, (string)$field['default']);
    }

    public static function sanitize(string $key, string $value): ?string
    {
        $value = trim($value);
        if (in_array($key, ['pwa_theme_color', 'pwa_background_color'], true)) {
            return preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? strtolower($value) : null;
        }
        if ($key === 'pwa_start_url') {
            if ($value === '') return '/';
            $parts = parse_url($value);
            if ($parts === false || isset($parts['scheme']) || isset($parts['host']) || self::startsWith($value, '//')) {
                return null;
            }
            return self::startsWith($value, '/') ? $value : '/' . ltrim($value, '/');
        }
        if ($key === 'pwa_display') {
            return in_array($value, self::DISPLAY_OPTIONS, true) ? $value : null;
        }
        if ($key === 'pwa_orientation') {
            return in_array($value, self::ORIENTATION_OPTIONS, true) ? $value : null;
        }
        $clean = strip_tags($value);
        return function_exists('mb_substr') ? mb_substr($clean, 0, 255) : substr($clean, 0, 255);
    }

    private static function startsWith(string $value, string $prefix): bool
    {
        return substr($value, 0, strlen($prefix)) === $prefix;
    }

    public static function version(): string
    {
        try {
            $row = Database::fetch('SELECT UNIX_TIMESTAMP(MAX(updated_at)) version FROM site_settings WHERE setting_key LIKE "pwa_%"');
            return (string)($row['version'] ?: time());
        } catch (Throwable $e) {
            return (string)time();
        }
    }

    public static function asset(string $key): string
    {
        $path = self::value($key);
        if ($path === '') return '';
        return $path . (str_contains($path, '?') ? '&' : '?') . 'v=' . rawurlencode(self::version());
    }

    public static function iconType(string $path): string
    {
        return match (strtolower(pathinfo(parse_url($path, PHP_URL_PATH) ?: $path, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            default => 'image/png',
        };
    }

    public static function manifest(): array
    {
        $icons = [];
        foreach (['pwa_icon_192' => '192x192', 'pwa_icon_512' => '512x512'] as $key => $size) {
            $src = self::value($key);
            if ($src !== '') {
                $icons[] = ['src' => self::asset($key), 'sizes' => $size, 'type' => self::iconType($src)];
            }
        }
        return [
            'name' => self::value('pwa_name'),
            'short_name' => self::value('pwa_short_name'),
            'description' => self::value('pwa_description'),
            'start_url' => self::value('pwa_start_url'),
            'display' => self::value('pwa_display'),
            'orientation' => self::value('pwa_orientation'),
            'theme_color' => self::value('pwa_theme_color'),
            'background_color' => self::value('pwa_background_color'),
            'icons' => $icons,
        ];
    }
}
