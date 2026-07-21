<?php

$root = dirname(__DIR__);
$failures = [];
$read = static fn(string $file): string => (string)file_get_contents($root . '/' . $file);
$check = static function (bool $condition, string $message) use (&$failures): void { if (!$condition) $failures[] = $message; };

$files = [
    'admin/ajax/hr-kpi-template.php', 'assets/js/hr-kpi-template-loader.js', 'core/CarouselModule.php',
    'services/EmailOAuthService.php', 'services/EmailSyncService.php', 'core/SmsGatewayService.php',
    'lib/admin_menu.php', 'views/partials/admin-sidebar.php', 'assets/js/app.js',
];
foreach ($files as $file) $check(is_file($root . '/' . $file), "Missing {$file}");

$kpi = $read('admin/hr-kpi-scores.php') . $read('admin/ajax/hr-kpi-template.php') . $read('assets/js/hr-kpi-template-loader.js');
$check(str_contains($kpi, 'hr_kpi_employee_assignments') && str_contains($kpi, 't.active=1'), 'KPI template scope/active guard missing');
$check(str_contains($kpi, 'X-CSRF-Token') && str_contains($kpi, 'verifyCsrf'), 'KPI AJAX CSRF contract missing');
$check(str_contains($kpi, 'TEMPLATE_OUT_OF_SCOPE') && str_contains($kpi, 'EMPLOYEE_OUT_OF_SCOPE'), 'KPI safe error codes missing');
$check(str_contains($kpi, 'hr_kpi_score_old_input'), 'KPI validation input preservation missing');

$carousel = $read('core/CarouselModule.php') . $read('admin/carousel.php') . $read('index.php') . $read('database/schema.sql');
$check(str_contains($carousel, "starts_at IS NULL OR starts_at<=NOW()") && str_contains($carousel, "ends_at IS NULL OR ends_at>=NOW()"), 'Carousel scheduling filter missing');
$check(str_contains($carousel, 'mobile_image_path') && str_contains($carousel, 'alt_text') && str_contains($carousel, 'link_target'), 'Carousel publication fields missing');
$check(str_contains($carousel, 'storedImageExists') && str_contains($carousel, 'is_file('), 'Carousel missing-file guard absent');
$check(!str_contains($read('admin/carousel.php'), 'DELETE FROM carousel_items'), 'Carousel destructive delete remains');
$check(str_contains($read('index.php'), '<picture') && str_contains($read('index.php'), 'rel="noopener noreferrer"'), 'Carousel responsive/safe link output missing');

$sms = $read('core/SmsGatewayService.php') . $read('core/SmsModule.php') . $read('admin/sms-send.php');
$check(str_contains($sms, 'GET_LOCK') && str_contains($sms, 'request_key') && str_contains($sms, 'uq_sms_request_key'), 'SMS idempotency guard missing');
$check(str_contains($sms, 'segmentCount') && str_contains($sms, 'maxlength="2000"'), 'SMS length/segment validation missing');
$check(str_contains($sms, 'sms_duplicate_prevented'), 'SMS duplicate audit action missing');

$email = $read('services/EmailOAuthService.php') . $read('services/EmailSyncService.php') . $read('core/EmailHubModule.php');
$check(str_contains($email, "grant_type' => 'refresh_token'") && str_contains($email, 'encrypted_access_token'), 'OAuth refresh/preservation missing');
$check(str_contains($email, 'sync_lock_token') && str_contains($email, 'email_sync_already_running'), 'Email sync lock missing');
$check(str_contains($email, 'sync_message_failed') && str_contains($email, "'partial'"), 'Per-message partial sync handling missing');
$check(str_contains($email, 'email_oauth_reauthorization_required') && str_contains($email, 'needs_reauth'), 'OAuth reauthorization state missing');
$check(str_contains($email, "message_id=?") && str_contains($email, 'account_id=?'), 'Cross-folder message id dedupe missing');
$check(str_contains($email, '$safeExtensions=') && str_contains($email, "in_array(\$extension,\$safeExtensions,true)?'.'.\$extension:'.bin'"), 'Executable email attachment storage guard missing');

$users = $read('admin/users.php');
$menu = $read('lib/admin_menu.php') . $read('views/partials/admin-sidebar.php') . $read('assets/js/app.js');
$check(!str_contains($users, "DELETE FROM user_permissions WHERE user_id"), 'Destructive permission rewrite remains');
$check(str_contains($users, 'ON DUPLICATE KEY UPDATE can_view') && str_contains($users, 'canonicalPermissionKey'), 'Permission upsert/canonical mapping missing');
$check(str_contains($menu, 'admin_menu_search_index') && str_contains($menu, 'admin_menu_visible_registry'), 'Shared permission-aware menu source missing');
$check(str_contains($menu, "replace(/[يى]/g, 'ی')") && str_contains($menu, "replace(/ك/g, 'ک')") && str_contains($menu, "event.key.toLowerCase() === 'k'"), 'Persian/keyboard menu search missing');

$notice = $read('core/Response.php') . $read('views/partials/admin-header.php') . $read('assets/js/app.js') . $read('assets/css/app.css');
$check(str_contains($notice, 'data-app-notice') && str_contains($notice, 'data-toast-region'), 'Shared notice component missing');
$check(str_contains($notice, 'sobhanNotify') && str_contains($notice, 'sobhanToastKeys'), 'Notification de-duplication API missing');
$check(str_contains($notice, 'alert-info') && str_contains($notice, 'aria-live="polite"'), 'Info/accessibility notice state missing');

require_once $root . '/core/CarouselModule.php';
$check(CarouselModule::safeLink('/admin/index.php?x=1') === '/admin/index.php?x=1', 'Safe internal link rejected');
$check(CarouselModule::safeLink('javascript:alert(1)') === '', 'Unsafe carousel link accepted');
$check(CarouselModule::safeImagePath('../config.php') === '', 'Carousel traversal path accepted');

require_once $root . '/core/SmsGatewayService.php';
$gateway = new SmsGatewayService(['is_active' => 1, 'default_sender' => '3000']);
$check($gateway->segmentCount(str_repeat('ش', 71)) === 2, 'Persian SMS segment calculation failed');
$check($gateway->segmentCount(str_repeat('a', 160)) === 1, 'Latin SMS segment calculation failed');

if ($failures) { fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL); exit(1); }
echo "PANEL_FINAL_INTEGRATION_CONTRACT_OK\n";
