<?php

require_once __DIR__ . '/Database.php';

class ThemeProfile
{
    public const DEFAULT_PROFILE = 'white_neon';
    public const DEFAULT_ACCENT = '#00D5FF';

    public static function profiles(): array
    {
        return [
            'white_neon' => ['label' => 'سفید نئون', 'description' => 'پس‌زمینه سفید شیشه‌ای با تاکید نئون'],
            'frost' => ['label' => 'یخی آرام', 'description' => 'سطوح سرد و کم‌کنتراست برای کار طولانی'],
            'minimal' => ['label' => 'سفید مینیمال', 'description' => 'ساده‌ترین حالت با کمترین افکت بصری'],
        ];
    }

    public static function repair(PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS user_theme_preferences (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            profile_key VARCHAR(40) NOT NULL DEFAULT 'white_neon',
            accent_color VARCHAR(7) NOT NULL DEFAULT '#00D5FF',
            effects_mode ENUM('standard','reduced') NOT NULL DEFAULT 'standard',
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_user_theme_preference (user_id),
            CONSTRAINT fk_user_theme_preference_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public static function forUser(int $userId): array
    {
        if ($userId < 1) return self::defaults();
        $row = Database::fetch('SELECT profile_key,accent_color,effects_mode FROM user_theme_preferences WHERE user_id=? LIMIT 1', [$userId]) ?? [];
        $profile = array_key_exists((string)($row['profile_key'] ?? ''), self::profiles()) ? (string)$row['profile_key'] : self::DEFAULT_PROFILE;
        return [
            'profile_key' => $profile,
            'accent_color' => self::sanitizeColor($row['accent_color'] ?? null) ?? self::DEFAULT_ACCENT,
            'effects_mode' => in_array($row['effects_mode'] ?? '', ['standard', 'reduced'], true) ? $row['effects_mode'] : 'standard',
        ];
    }

    public static function defaults(): array
    {
        return ['profile_key' => self::DEFAULT_PROFILE, 'accent_color' => self::DEFAULT_ACCENT, 'effects_mode' => 'standard'];
    }

    public static function bodyClasses(array $preference): array
    {
        return ['theme-ui', 'theme-profile-' . $preference['profile_key'], 'theme-effects-' . $preference['effects_mode']];
    }

    public static function inlineStyle(array $preference): string
    {
        $accent = self::sanitizeColor($preference['accent_color'] ?? null) ?? self::DEFAULT_ACCENT;
        return '--theme-accent:' . $accent
            . ';--theme-accent-contrast:' . self::contrastColor($accent)
            . ';--theme-accent-soft:' . self::rgba($accent, .12)
            . ';--theme-accent-faint:' . self::rgba($accent, .06)
            . ';--theme-accent-border:' . self::rgba($accent, .28)
            . ';--ceo-neon:' . $accent
            . ';--ceo-neon-soft:' . self::rgba($accent, .12)
            . ';--ceo-neon-faint:' . self::rgba($accent, .06)
            . ';--ceo-border:' . self::rgba($accent, .28) . ';';
    }

    public static function save(int $userId, string $profile, string $accent, string $effects): void
    {
        if (!array_key_exists($profile, self::profiles())) throw new InvalidArgumentException('پروفایل تم معتبر نیست.');
        $accent = self::sanitizeColor($accent) ?? throw new InvalidArgumentException('رنگ تم باید یک کد HEX معتبر باشد.');
        if (!in_array($effects, ['standard', 'reduced'], true)) throw new InvalidArgumentException('حالت افکت معتبر نیست.');
        Database::execute('INSERT INTO user_theme_preferences(user_id,profile_key,accent_color,effects_mode,created_at,updated_at) VALUES (?,?,?,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE profile_key=VALUES(profile_key),accent_color=VALUES(accent_color),effects_mode=VALUES(effects_mode),updated_at=NOW()', [$userId, $profile, $accent, $effects]);
    }

    public static function sanitizeColor(?string $value): ?string
    {
        $value = strtoupper(trim((string)$value));
        return preg_match('/^#[0-9A-F]{6}$/', $value) ? $value : null;
    }

    public static function rgba(string $color, float $alpha): string
    {
        $color = self::sanitizeColor($color) ?? self::DEFAULT_ACCENT;
        return sprintf('rgba(%d,%d,%d,%.2F)', hexdec(substr($color, 1, 2)), hexdec(substr($color, 3, 2)), hexdec(substr($color, 5, 2)), max(0, min(1, $alpha)));
    }

    public static function contrastColor(string $color): string
    {
        $color = self::sanitizeColor($color) ?? self::DEFAULT_ACCENT;
        $rgb = array_map(static fn(string $part): int => hexdec($part), [substr($color, 1, 2), substr($color, 3, 2), substr($color, 5, 2)]);
        return (.2126 * $rgb[0] + .7152 * $rgb[1] + .0722 * $rgb[2]) > 145 ? '#071219' : '#FFFFFF';
    }
}
