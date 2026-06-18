<?php
class Installer
{
    public static function requirements(): array
    {
        $uploadDirs = ['uploads/carousel', 'uploads/files', 'uploads/logo', 'uploads/accounting', 'uploads/knowledge'];
        foreach ($uploadDirs as $dir) {
            if (!is_dir(__DIR__ . '/../' . $dir)) {
                @mkdir(__DIR__ . '/../' . $dir, 0755, true);
            }
        }

        $configFile = __DIR__ . '/../config/config.php';

        return [
            'PHP 8.1+' => version_compare(PHP_VERSION, '8.1.0', '>='),
            'PDO' => extension_loaded('pdo'),
            'PDO MySQL' => extension_loaded('pdo_mysql'),
            'پوشه config قابل نوشتن' => is_writable(__DIR__ . '/../config') && (!file_exists($configFile) || is_writable($configFile)),
            'پوشه uploads قابل نوشتن' => is_writable(__DIR__ . '/../uploads'),
        ];
    }

    public static function cleanDatabaseName(string $name): string
    {
        return preg_replace('/[^a-zA-Z0-9_]/', '', $name);
    }

    public static function writeConfig(array $data): void
    {
        $config = "<?php\nreturn " . var_export($data, true) . ";\n";
        $target = __DIR__ . '/../config/config.php';
        $tmp = $target . '.tmp';

        if (file_put_contents($tmp, $config, LOCK_EX) === false) {
            throw new RuntimeException('امکان نوشتن فایل تنظیمات وجود ندارد.');
        }

        if (!rename($tmp, $target)) {
            @unlink($tmp);
            throw new RuntimeException('امکان ذخیره نهایی فایل تنظیمات وجود ندارد.');
        }
    }
}
