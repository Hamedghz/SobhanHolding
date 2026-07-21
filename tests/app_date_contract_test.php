<?php

$root = dirname(__DIR__);
require_once $root . '/lib/AppDate.php';

$fail = static function (string $message): never {
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
};
$assertSame = static function (mixed $expected, mixed $actual, string $label) use ($fail): void {
    if ($expected !== $actual) {
        $fail($label . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
};

$assertSame('1405/01/01', AppDate::toJalali('2026-03-21'), 'Gregorian to Jalali');
$assertSame('2026-03-21', AppDate::toGregorian('۱۴۰۵/۰۱/۰۱'), 'Persian digit Jalali to Gregorian');
$assertSame('2026-07-16 13:45:00', AppDate::toGregorianDateTime('۱۴۰۵-۰۴-۲۵ ۱۳:۴۵'), 'Jalali datetime');
$assertSame('1405/04/25 13:45', AppDate::toJalaliDateTime('2026-07-16 13:45:00'), 'Gregorian datetime display');
$assertSame(null, AppDate::toGregorian('1405/13/01'), 'Invalid Jalali month');
$assertSame(null, AppDate::toGregorianDateTime('1405/04/25 25:00'), 'Invalid time');

$assertSame(
    ['start_date' => '2026-07-11', 'end_date' => '2026-07-17'],
    AppDate::periodBounds('weekly', '2026-07-16'),
    'Saturday based week'
);
$assertSame(
    ['start_date' => '2026-06-22', 'end_date' => '2026-07-22'],
    AppDate::periodBounds('monthly', '2026-07-16'),
    'Jalali month'
);
$assertSame(
    ['start_date' => '2026-06-22', 'end_date' => '2026-09-22'],
    AppDate::periodBounds('quarterly', '2026-07-16'),
    'Jalali quarter'
);
$assertSame(
    ['start_date' => '2026-03-21', 'end_date' => '2026-09-22'],
    AppDate::periodBounds('half_yearly', '2026-07-16'),
    'Jalali half-year'
);
$assertSame(
    ['start_date' => '2026-03-21', 'end_date' => '2027-03-20'],
    AppDate::periodBounds('yearly', '2026-07-16'),
    'Jalali year'
);

$catalog = AppDate::defaultPeriodCatalog(new DateTimeImmutable('2026-07-16 12:00:00', new DateTimeZone('Asia/Tehran')));
$assertSame(53, count($catalog), 'Default period count');
$keys = array_column($catalog, 'period_key');
if (count($keys) !== count(array_unique($keys))) $fail('Period keys must be unique.');
foreach (['daily', 'weekly', 'monthly', 'quarterly', 'half_yearly', 'yearly', 'custom'] as $type) {
    if (!in_array($type, array_column($catalog, 'period_type'), true)) $fail('Missing period type: ' . $type);
}

$source = (string)file_get_contents($root . '/core/AppDateModule.php');
$schema = (string)file_get_contents($root . '/database/schema.sql');
$database = (string)file_get_contents($root . '/core/Database.php');
foreach ([$source, $schema] as $scope) {
    if (!str_contains($scope, 'CREATE TABLE IF NOT EXISTS system_periods')) $fail('system_periods DDL is missing.');
}
if (!str_contains($database, 'AppDateModule::repair($pdo)')) $fail('AppDate repair hook is missing.');
if (preg_match('/\b(DROP|TRUNCATE|RENAME\s+TABLE)\b/i', $source)) $fail('Destructive SQL found in AppDate module.');

echo "AppDate contract: PASS\n";
