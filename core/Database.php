<?php
require_once __DIR__ . '/Config.php';

class Database
{
    private static ?PDO $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo === null) {
            Config::ensureInstalled();
            $config = Config::db();
            foreach (['host', 'name', 'user'] as $key) {
                if (trim((string)($config[$key] ?? '')) === '') {
                    throw new RuntimeException('Database configuration is incomplete. Run install.php again.');
                }
            }
            $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $config['host'], $config['name'], $config['charset'] ?? 'utf8mb4');
            self::$pdo = new PDO($dsn, $config['user'], $config['pass'] ?? '', [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        }
        return self::$pdo;
    }

    public static function fetch(string $sql, array $params = []): ?array
    {
        $stmt = self::connection()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function fetchAll(string $sql, array $params = []): array
    {
        $stmt = self::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function execute(string $sql, array $params = []): bool
    {
        $stmt = self::connection()->prepare($sql);
        return $stmt->execute($params);
    }

    public static function lastInsertId(): string
    {
        return self::connection()->lastInsertId();
    }
}
