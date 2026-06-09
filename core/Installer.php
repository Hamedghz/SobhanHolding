<?php
class Installer
{
    public static function requirements(): array
    {
        $uploadDirs = ['uploads/carousel', 'uploads/files', 'uploads/logo'];
        foreach ($uploadDirs as $dir) {
            if (!is_dir(__DIR__ . '/../' . $dir)) @mkdir(__DIR__ . '/../' . $dir, 0755, true);
        }
        return [
            'PHP 8.1+' => version_compare(PHP_VERSION, '8.1.0', '>='),
            'PDO' => extension_loaded('pdo'),
            'PDO MySQL' => extension_loaded('pdo_mysql'),
            'پوشه config قابل نوشتن' => is_writable(__DIR__ . '/../config'),
            'پوشه uploads قابل نوشتن' => is_writable(__DIR__ . '/../uploads'),
        ];
    }
}
