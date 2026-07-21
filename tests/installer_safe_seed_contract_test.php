<?php

$root = dirname(__DIR__);
$installer = (string)file_get_contents($root . '/core/Installer.php');
$config = (string)file_get_contents($root . '/core/Config.php');
$gitignore = (string)file_get_contents($root . '/.gitignore');
$database = (string)file_get_contents($root . '/core/Database.php');
$installPage = (string)file_get_contents($root . '/install.php');
$dashboardSeed = (string)file_get_contents($root . '/install/seeds/006_dashboard_seed.php');
$importMappingsSeed = (string)file_get_contents($root . '/install/seeds/010_sales_import_mappings_seed.php');

foreach ([
    'Database::withConnection',
    'SeedManager::runMany',
    "array_keys(SeedManager::registry())",
] as $token) {
    if (!str_contains($installer, $token)) throw new RuntimeException('Installer safe seed contract missing: ' . $token);
}
if (!str_contains($database, 'finally') || !str_contains($database, 'self::$pdo = $previousPdo')) {
    throw new RuntimeException('Temporary installer connection is not restored safely.');
}
if (!str_contains($installPage, 'Installer::seedFreshDatabase($pdo, $adminUserId)')) {
    throw new RuntimeException('Fresh installer does not execute safe seeds.');
}
if (!str_contains($installer, "config/local.php") || !str_contains($config, "config/local.php") || !str_contains($gitignore, 'config/local.php')) {
    throw new RuntimeException('Installer credentials are not isolated in a gitignored local config.');
}
if (str_contains($installer, "../config/config.php';")) {
    throw new RuntimeException('Installer still writes secrets to the tracked config template.');
}
foreach (['installer_csrf', 'hash_equals', 'name="csrf_token"'] as $token) {
    if (!str_contains($installPage, $token)) throw new RuntimeException('Installer CSRF contract missing: ' . $token);
}
foreach (['name="db_port"', "';port=' . \$dbPort", "'port' => \$dbPort"] as $token) {
    if (!str_contains($installPage, $token)) throw new RuntimeException('Installer database port contract missing: ' . $token);
}
if (str_contains($installPage, '$exception->getMessage()') || !str_contains($installPage, 'Installer failure: type=')) {
    throw new RuntimeException('Installer may expose a raw database error.');
}
if (!str_contains($installer, "'Mbstring' => extension_loaded('mbstring')")) {
    throw new RuntimeException('Installer does not block unsupported PHP runtimes without mbstring.');
}
if (!str_contains($installer, "'JSON' => extension_loaded('json')")) {
    throw new RuntimeException('Installer does not block unsupported PHP runtimes without JSON.');
}
if (!str_contains($installer, "'ZipArchive برای Excel' => class_exists('ZipArchive')")) {
    throw new RuntimeException('Installer does not block environments that cannot generate Excel import templates.');
}
if (!str_contains($installer, "'XMLReader برای Excel بزرگ' => class_exists('XMLReader')")) {
    throw new RuntimeException('Installer does not block environments that cannot stream large Excel worksheets.');
}
if (!str_contains($installer, "'Fileinfo برای آپلود امن' => class_exists('finfo')")) {
    throw new RuntimeException('Installer does not block environments that cannot verify uploaded file MIME types.');
}
if (!str_contains($dashboardSeed, 'DashboardModule::repair($pdo)')) {
    throw new RuntimeException('Dashboard preference seed is still a no-op.');
}
$combined = $installer . $dashboardSeed . $importMappingsSeed;
if (preg_match('/\b(DROP|TRUNCATE|DELETE\s+FROM)\b/i', $combined)) {
    throw new RuntimeException('Destructive operation found in fresh-install seed path.');
}
foreach (['sales_aggregate_rows','purchase_aggregate_rows','inventory_aggregate_rows','hr_attendance_entries'] as $operationalTable) {
    if (preg_match('/INSERT\s+INTO\s+`?' . preg_quote($operationalTable, '/') . '`?/i', $combined)) {
        throw new RuntimeException('Installer fabricates operational data: ' . $operationalTable);
    }
}
if (!str_contains($importMappingsSeed, 'ImportSourceRegistry::all()') || !str_contains($importMappingsSeed, 'INSERT IGNORE INTO sales_import_column_mappings')) {
    throw new RuntimeException('Fresh installer does not seed non-destructive mappings for generated import templates.');
}
foreach (glob($root . '/install/seeds/*.php') ?: [] as $seedFile) {
    $prefix = (string)file_get_contents($seedFile, false, null, 0, 3);
    if ($prefix === "\xEF\xBB\xBF") {
        throw new RuntimeException('Seed file emits an unsafe UTF-8 BOM: ' . basename($seedFile));
    }
}

echo "Installer safe seed contract: PASS\n";
