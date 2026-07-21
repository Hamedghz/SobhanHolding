<?php

$root = dirname(__DIR__);
$email = (string)file_get_contents($root . '/admin/email-providers.php');
$manager = (string)file_get_contents($root . '/admin/manager-dashboard-settings.php');

foreach (['name="oauth_config_json"', 'تنظیمات OAuth JSON'] as $forbidden) {
    if (str_contains($email, $forbidden)) throw new RuntimeException('Raw OAuth JSON UI remains: ' . $forbidden);
}
foreach (['name="oauth_client_id"', 'name="oauth_client_secret"', 'برای حفظ مقدار فعلی خالی بگذارید', 'email_oauth_config'] as $required) {
    if (!str_contains($email, $required)) throw new RuntimeException('OAuth form contract missing: ' . $required);
}
foreach (['EmailCrypto::encrypt($secret)', 'client_secret_encrypted', "unset(\$config['client_secret'])"] as $required) {
    if (!str_contains($email, $required)) throw new RuntimeException('OAuth secret encryption contract missing: ' . $required);
}
if (str_contains($email, 'value="<?=e($editOauth[\'client_secret\']')) {
    throw new RuntimeException('Saved OAuth client secret must not be redisplayed.');
}

foreach (['name="input_schema_json', 'name="output_schema_json', 'ساختار JSON مهارت‌ها معتبر نیست'] as $forbidden) {
    if (str_contains($manager, $forbidden)) throw new RuntimeException('Raw dashboard skill JSON UI remains: ' . $forbidden);
}
foreach (['manager_skill_schema_fields', 'قرارداد ورودی و خروجی داخلی است', 'داده‌های ورودی', 'بخش‌های خروجی'] as $required) {
    if (!str_contains($manager, $required)) throw new RuntimeException('Dashboard skill schema summary missing: ' . $required);
}

echo "Configuration UI contract: PASS\n";
