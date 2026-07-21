<?php

require_once __DIR__ . '/../core/Database.php';

final class ImportSettings
{
    public const DEFAULT_EXCEL_MB = 50;
    public const DEFAULT_LETTER_MB = 50;
    public const DEFAULT_LETTERHEAD_MB = 50;
    public const DEFAULT_EXTENSIONS = ['xlsx', 'csv'];

    public static function excelUploadMb(): int
    {
        return self::boundedInt(self::value('max_excel_upload_mb', (string)self::DEFAULT_EXCEL_MB), 1, 200, self::DEFAULT_EXCEL_MB);
    }

    public static function letterAttachmentMb(): int
    {
        return self::boundedInt(self::value('max_letter_attachment_mb', (string)self::DEFAULT_LETTER_MB), 1, 100, self::DEFAULT_LETTER_MB);
    }

    public static function letterheadUploadMb(): int
    {
        return self::boundedInt(self::value('max_letterhead_upload_mb', (string)self::DEFAULT_LETTERHEAD_MB), 1, 100, self::DEFAULT_LETTERHEAD_MB);
    }

    public static function letterAttachmentBytes(): int
    {
        return self::letterAttachmentMb() * 1024 * 1024;
    }

    public static function letterheadUploadBytes(): int
    {
        return self::letterheadUploadMb() * 1024 * 1024;
    }

    public static function allowedExtensions(): array
    {
        $raw = strtolower(self::value('allowed_import_extensions', implode(',', self::DEFAULT_EXTENSIONS)));
        $items = preg_split('/[\s,،;]+/u', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $allowed = array_values(array_unique(array_intersect(array_map(static fn(string $v): string => ltrim(trim($v), '.'), $items), ['xlsx', 'csv'])));
        return $allowed ?: self::DEFAULT_EXTENSIONS;
    }

    public static function maxFileBytes(): int
    {
        return self::excelUploadMb() * 1024 * 1024;
    }

    public static function serverLimits(): array
    {
        return [
            'upload_max_filesize' => (string)ini_get('upload_max_filesize'),
            'post_max_size' => (string)ini_get('post_max_size'),
            'memory_limit' => (string)ini_get('memory_limit'),
            'max_execution_time' => (string)ini_get('max_execution_time'),
        ];
    }

    public static function effectiveUploadBytes(): int
    {
        $limits = [self::maxFileBytes()];
        foreach (['upload_max_filesize', 'post_max_size'] as $key) {
            $bytes = self::iniBytes((string)ini_get($key));
            if ($bytes > 0) $limits[] = $bytes;
        }
        return min($limits);
    }

    public static function applicationExceedsServer(): bool
    {
        return self::effectiveUploadBytes() < self::maxFileBytes();
    }

    private static function value(string $key, string $default): string
    {
        try {
            $row = Database::fetch('SELECT setting_value FROM site_settings WHERE setting_key=? LIMIT 1', [$key]);
            return trim((string)($row['setting_value'] ?? $default));
        } catch (Throwable) {
            return $default;
        }
    }

    private static function boundedInt(string $value, int $min, int $max, int $default): int
    {
        $number = filter_var($value, FILTER_VALIDATE_INT);
        return $number === false ? $default : max($min, min($max, (int)$number));
    }

    private static function iniBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '' || $value === '-1') return 0;
        $unit = strtolower(substr($value, -1));
        $number = (float)$value;
        return match ($unit) {
            'g' => (int)round($number * 1024 * 1024 * 1024),
            'm' => (int)round($number * 1024 * 1024),
            'k' => (int)round($number * 1024),
            default => (int)$number,
        };
    }
}
