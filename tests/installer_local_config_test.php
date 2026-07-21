<?php

$root = dirname(__DIR__);
$localPath = $root . '/config/local.php';
if (is_file($localPath)) {
    fwrite(STDERR, "Refusing to replace an existing local config.\n");
    exit(2);
}

require_once $root . '/core/Installer.php';
try {
    Installer::writeConfig([
        'installed'=>true,
        'db'=>['host'=>'test-host','port'=>3307,'name'=>'test-db','user'=>'test-user','pass'=>'test-pass','charset'=>'utf8mb4'],
        'app'=>['url'=>'https://example.test','name'=>'Test','debug'=>false],
    ]);
    require_once $root . '/core/Config.php';
    $config = Config::all();
    if (($config['db']['host'] ?? '') !== 'test-host' || (int)($config['db']['port'] ?? 0) !== 3307 || ($config['app']['name'] ?? '') !== 'Test') {
        throw new RuntimeException('Local config does not override the tracked template.');
    }
    $ignored = trim((string)shell_exec('git -C ' . escapeshellarg($root) . ' check-ignore ' . escapeshellarg('config/local.php')));
    if ($ignored !== 'config/local.php') throw new RuntimeException('Local config is not gitignored.');
    echo "Installer local config: PASS\n";
} finally {
    if (is_file($localPath)) @unlink($localPath);
    if (is_file($localPath . '.tmp')) @unlink($localPath . '.tmp');
}
