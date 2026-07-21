<?php

require_once __DIR__ . '/Database.php';

class CarouselModule
{
    public static function repair(PDO $pdo): void
    {
        if (!Database::tableExists('carousel_items')) return;

        $columns = [
            'mobile_image_path' => 'VARCHAR(255) NULL',
            'alt_text' => 'VARCHAR(255) NULL',
            'link_target' => "VARCHAR(10) NOT NULL DEFAULT '_self'",
            'placement' => "VARCHAR(50) NOT NULL DEFAULT 'homepage'",
            'item_type' => "VARCHAR(30) NOT NULL DEFAULT 'slider'",
            'starts_at' => 'DATETIME NULL',
            'ends_at' => 'DATETIME NULL',
        ];
        foreach ($columns as $column => $definition) {
            if (!Database::columnExists('carousel_items', $column)) {
                $pdo->exec("ALTER TABLE carousel_items ADD `{$column}` {$definition}");
            }
        }

        $index = $pdo->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=?');
        $index->execute(['carousel_items', 'idx_carousel_publication']);
        if ((int)$index->fetchColumn() === 0) {
            $pdo->exec('ALTER TABLE carousel_items ADD INDEX idx_carousel_publication(status,placement,item_type,starts_at,ends_at,sort_order)');
        }
    }

    public static function publicItems(): array
    {
        $rows = Database::fetchAll(
            "SELECT id,title,description,image_path,mobile_image_path,alt_text,button_text,button_link,link_target,sort_order
             FROM carousel_items
             WHERE status='active' AND placement='homepage' AND item_type IN ('slider','banner')
               AND (starts_at IS NULL OR starts_at<=NOW())
               AND (ends_at IS NULL OR ends_at>=NOW())
             ORDER BY sort_order ASC,id DESC"
        );
        $items = [];
        foreach ($rows as $row) {
            $image = self::safeImagePath((string)($row['image_path'] ?? ''));
            $mobileImage = self::safeImagePath((string)($row['mobile_image_path'] ?? ''));
            if ($image !== '' && !self::storedImageExists($image)) $image = '';
            if ($mobileImage !== '' && !self::storedImageExists($mobileImage)) $mobileImage = '';
            if ($image === '' && $mobileImage !== '') $image = $mobileImage;
            if ($image === '') continue;
            $row['image_path'] = $image;
            $row['mobile_image_path'] = $mobileImage;
            $row['button_link'] = self::safeLink((string)($row['button_link'] ?? ''));
            $row['link_target'] = ($row['link_target'] ?? '') === '_blank' ? '_blank' : '_self';
            $row['alt_text'] = trim((string)($row['alt_text'] ?? '')) ?: trim((string)($row['title'] ?? ''));
            $items[] = $row;
        }
        return $items;
    }

    public static function safeLink(string $link): string
    {
        $link = trim($link);
        if ($link === '') return '';
        if (str_starts_with($link, '/') && !str_starts_with($link, '//') && !preg_match('/[\x00-\x1F\x7F]/', $link)) return $link;
        if (!filter_var($link, FILTER_VALIDATE_URL)) return '';
        $scheme = strtolower((string)parse_url($link, PHP_URL_SCHEME));
        return in_array($scheme, ['http', 'https'], true) ? $link : '';
    }

    public static function safeImagePath(string $path): string
    {
        $path = trim(str_replace('\\', '/', $path));
        if ($path === '' || str_contains($path, '..') || preg_match('/[\x00-\x1F\x7F]/', $path)) return '';
        if (!preg_match('#^/?uploads/carousel/[a-zA-Z0-9._/-]+$#', $path)) return '';
        return '/' . ltrim($path, '/');
    }

    private static function storedImageExists(string $path): bool
    {
        $relative = str_replace('/', DIRECTORY_SEPARATOR, ltrim($path, '/'));
        return is_file(dirname(__DIR__) . DIRECTORY_SEPARATOR . $relative);
    }
}
