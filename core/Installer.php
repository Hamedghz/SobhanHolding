<?php
class Installer
{
    public static function seedFreshDatabase(PDO $pdo, int $adminUserId): array
    {
        require_once __DIR__ . '/Database.php';
        require_once __DIR__ . '/SeedManager.php';

        return Database::withConnection($pdo, static function () use ($adminUserId): array {
            $results = SeedManager::runMany(array_keys(SeedManager::registry()), 'safe', $adminUserId);
            foreach ($results as $key => $result) {
                if (($result['status'] ?? '') !== 'completed' || (int)($result['errors'] ?? 0) > 0) {
                    error_log('Fresh install seed failed: ' . $key);
                    throw new RuntimeException('تکمیل داده‌های پایه نصب انجام نشد. نصب را دوباره بررسی کنید.');
                }
            }
            return $results;
        });
    }

    public static function requirements(): array
    {
        $uploadDirs = ['uploads/carousel', 'uploads/files', 'uploads/logo', 'uploads/accounting', 'uploads/knowledge'];
        foreach ($uploadDirs as $dir) {
            if (!is_dir(__DIR__ . '/../' . $dir)) {
                @mkdir(__DIR__ . '/../' . $dir, 0755, true);
            }
        }

        $configFile = __DIR__ . '/../config/local.php';

        return [
            'PHP 8.1+' => version_compare(PHP_VERSION, '8.1.0', '>='),
            'PDO' => extension_loaded('pdo'),
            'PDO MySQL' => extension_loaded('pdo_mysql'),
            'JSON' => extension_loaded('json'),
            'Mbstring' => extension_loaded('mbstring'),
            'Fileinfo برای آپلود امن' => class_exists('finfo'),
            'ZipArchive برای Excel' => class_exists('ZipArchive'),
            'XMLReader برای Excel بزرگ' => class_exists('XMLReader'),
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
        $target = __DIR__ . '/../config/local.php';
        $tmp = $target . '.tmp';

        if (file_put_contents($tmp, $config, LOCK_EX) === false) {
            throw new RuntimeException('امکان نوشتن فایل تنظیمات وجود ندارد.');
        }

        if (!rename($tmp, $target)) {
            @unlink($tmp);
            throw new RuntimeException('امکان ذخیره نهایی فایل تنظیمات وجود ندارد.');
        }
        @chmod($target, 0600);
    }
}
