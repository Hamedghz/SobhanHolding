<?php

$root = dirname(__DIR__);
$read = static fn(string $path): string => (string)file_get_contents($root . '/' . $path);
$module = $read('core/LetterModule.php');
$includes = $read('admin/includes/letters.php');
$create = $read('admin/letter-create.php');
$templates = $read('admin/letter-templates.php');
$letterheads = $read('admin/letter-letterheads.php');
$settings = $read('admin/letter-settings.php');
$script = $read('assets/js/letters.js');
$schema = $read('database/schema.sql');

foreach (['background_mime','margin_top_mm','header_position_mm','footer_position_mm','is_default','body_delta_json','default_delta_json'] as $token) {
    if (!str_contains($module . $schema, $token)) throw new RuntimeException('Missing correspondence schema token: ' . $token);
}
foreach (['quill.snow.css','quill.js','sanitizeDelta','data-editor-delta'] as $token) {
    if (!str_contains($includes . $module . $create . $templates, $token)) throw new RuntimeException('Missing Quill contract token: ' . $token);
}
if (str_contains($script, 'execCommand')) throw new RuntimeException('Deprecated execCommand editor remains active.');
foreach (['ql-font','ql-size','ql-bold','ql-italic','ql-underline','ql-color','ql-background','ql-direction','ql-align','ql-list','ql-indent','ql-link','ql-clean','ql-undo','ql-redo'] as $token) {
    if (!str_contains($create . $templates, $token)) throw new RuntimeException('Missing Quill toolbar control: ' . $token);
}
foreach (['application/pdf','image/png','image/jpeg','image/webp','letterhead_upload','ImportSettings::letterheadUploadBytes'] as $token) {
    if (!str_contains($includes . $letterheads, $token)) throw new RuntimeException('Missing letterhead upload contract: ' . $token);
}
foreach (['پیش‌نمایش چاپ','is_default','margin_top_mm','header_position_mm'] as $token) {
    if (!str_contains($letterheads, $token)) throw new RuntimeException('Missing letterhead management feature: ' . $token);
}
foreach (['max_letter_attachment_mb','max_letterhead_upload_mb'] as $token) {
    if (!str_contains($settings, $token)) throw new RuntimeException('Missing configurable upload setting: ' . $token);
}
if (!str_contains($create, "app_date_input('letter_date'") || !str_contains($create, 'app_date_to_iso')) {
    throw new RuntimeException('Global Jalali input / ISO storage contract is missing.');
}
foreach ([$module,$includes,$create,$templates,$letterheads,$schema] as $scope) {
    if (preg_match('/\b(?:DROP|TRUNCATE)\b/i', $scope)) throw new RuntimeException('Destructive SQL token found.');
}

echo "Letter phase 13 contract: PASS\n";
