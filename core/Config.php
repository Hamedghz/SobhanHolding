<?php
class Config
{
    public static function all(): array
    {
        $path = __DIR__ . '/../config/config.php';
        if (!file_exists($path)) {
            return self::defaults();
        }

        $config = require $path;
        if (!is_array($config)) {
            return self::defaults();
        }

        return array_replace_recursive(self::defaults(), $config);
    }

    public static function db(): array
    {
        return self::all()['db'];
    }

    public static function app(): array
    {
        return self::all()['app'];
    }

    public static function isInstalled(): bool
    {
        $config = self::all();
        return !empty($config['installed']) && file_exists(__DIR__ . '/../install.lock');
    }

    public static function ensureInstalled(): void
    {
        if (self::isInstalled()) {
            return;
        }

        if (PHP_SAPI === 'cli') {
            throw new RuntimeException('Application is not installed. Run install.php first.');
        }

        $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
        if ($script !== 'install.php') {
            header('Location: /install.php');
            exit;
        }
    }

    private static function defaults(): array
    {
        return [
            'installed' => false,
            'db' => [
                'host' => '',
                'name' => '',
                'user' => '',
                'pass' => '',
                'charset' => 'utf8mb4',
            ],
            'app' => [
                'url' => '',
                'name' => 'شرکت پخش سبحان',
                'debug' => false,
            ],
        ];
    }
}

// Production must never expose PHP warnings or stack traces to end users.
// Keep full diagnostics in the configured PHP error log; debug mode remains opt-in.
$appConfig = Config::app();
$debugMode = (bool)($appConfig['debug'] ?? false);
error_reporting(E_ALL);
ini_set('log_errors', '1');
ini_set('display_errors', $debugMode ? '1' : '0');
ini_set('display_startup_errors', $debugMode ? '1' : '0');
