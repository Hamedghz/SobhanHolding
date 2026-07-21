<?php
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Response.php';
require_once __DIR__ . '/../../core/Pwa.php';
require_once __DIR__ . '/../../core/ThemeProfile.php';
require_once __DIR__ . '/../../lib/NotificationService.php';
require_once __DIR__ . '/../../lib/PushNotificationService.php';
require_once __DIR__ . '/../../core/SobhanAiStatus.php';
$user = Auth::user();
$pwaVersion = Pwa::version();
$pwaThemeColor = Pwa::value('pwa_theme_color');
$pwaFavicon = Pwa::asset('pwa_favicon');
$pwaAppleIcon = Pwa::asset('pwa_icon_192');
$themePreference = ThemeProfile::forUser((int)($user['id'] ?? 0));
$currentAdminPage = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
$compactUiPages = [
    'work-planner.php',
    'work-planner-templates.php',
    'sales-manager-daily-work-log.php',
    'sales-actions.php',
    'supervisor-actions.php',
    'sales-offer-formula-settings.php',
    'sales-aggregate-import.php',
    'inventory-aggregate-import.php',
    'payroll-import.php',
    'manager-dashboard-import.php',
    'hr-attendance.php',
    'my-attendance.php',
    'letter-create.php',
    'letters.php',
    'letter-templates.php',
    'ceo-dashboard-settings.php',
    'manager-dashboard-settings.php',
];
if (
    in_array($currentAdminPage, $compactUiPages, true)
    || preg_match('/(?:^|-)import(?:-|\.php)|attendance|letter|dashboard-settings/u', $currentAdminPage)
) {
    $adminBodyClasses[] = 'app-compact-ui';
}
$adminBodyClasses = array_merge(ThemeProfile::bodyClasses($themePreference), $adminBodyClasses ?? []);
$adminBodyClass = trim('admin-body ' . implode(' ', array_map(static fn($class) => preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$class), $adminBodyClasses)));
$adminExtraStylesheets = array_values(array_unique($adminExtraStylesheets ?? []));
$themeStyle = ThemeProfile::inlineStyle($themePreference);
$adminBodyStyle = $themeStyle . ($adminBodyStyle ?? '');
$notificationUnreadCount = 0;
$notificationPublicKey = '';
$sobhanAiStatus = SobhanAiStatus::cached();
try {
    if ($user) {
        $notificationUnreadCount = NotificationService::unreadCount((int)$user['id']);
        $notificationPublicKey = PushNotificationService::publicKey();
    }
} catch (Throwable $e) {
    error_log('notification header: ' . $e->getMessage());
}
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle ?? 'پنل مدیریت') ?> - <?= e(setting('company_name', 'شرکت پخش سبحان')) ?></title>
    <link rel="manifest" href="/manifest.php?v=<?= e($pwaVersion) ?>">
    <meta name="theme-color" content="<?= e($pwaThemeColor) ?>">
    <?php if ($pwaFavicon): ?><link rel="icon" href="<?= e($pwaFavicon) ?>"><?php endif; ?>
    <?php if ($pwaAppleIcon): ?><link rel="apple-touch-icon" href="<?= e($pwaAppleIcon) ?>"><?php endif; ?>
    <link rel="stylesheet" href="/assets/vendor/jalalidatepicker/jalalidatepicker-1.0.0.min.css">
    <link rel="stylesheet" href="/assets/css/app.css">
    <link rel="stylesheet" href="/assets/css/admin.css">
    <link rel="stylesheet" href="/assets/css/hr.css">
    <link rel="stylesheet" href="/assets/css/app-jalali-date.css">
    <link rel="stylesheet" href="/assets/css/app-compact-ui.css">
    <?php foreach ($adminExtraStylesheets as $stylesheet): ?><link rel="stylesheet" href="<?= e($stylesheet) ?>"><?php endforeach; ?>
    <link rel="stylesheet" href="/assets/css/admin-theme-profiles.css">
</head>
<body class="<?= e($adminBodyClass) ?>"<?= !empty($adminBodyStyle) ? ' style="' . e($adminBodyStyle) . '"' : '' ?>>
<div class="admin-shell">
<?php require __DIR__ . '/admin-sidebar.php'; ?>
<main class="admin-main">
    <header class="admin-topbar">
        <button class="menu-toggle" type="button" data-sidebar-toggle aria-controls="adminSidebar" aria-expanded="false" aria-label="باز کردن منو"><span></span><span></span><span></span></button>
        <div class="admin-topbar-title"><strong><?= e($pageTitle ?? 'پنل مدیریت') ?></strong><small><?= e(setting('company_name', 'شرکت پخش سبحان')) ?></small></div>
        <div class="admin-topbar-tools">
            <a class="sobhan-ai-indicator <?= !empty($sobhanAiStatus['healthy']) ? 'is-healthy' : 'is-unavailable' ?>" data-sobhan-ai-status href="<?= Auth::can('view_sobhan_api_settings') || Auth::can('manage_sobhan_api_settings') ? '/admin/sobhan-api-settings.php' : '#' ?>" title="<?= e(!empty($sobhanAiStatus['last_success_at']) ? 'آخرین اتصال موفق: '.format_jalali_datetime((string)$sobhanAiStatus['last_success_at']) : 'هنوز اتصال موفقی ثبت نشده است') ?>" aria-label="<?= !empty($sobhanAiStatus['healthy']) ? 'هوش مصنوعی سبحان متصل است' : 'هوش مصنوعی سبحان در دسترس نیست' ?>"><i data-ai-status-dot aria-hidden="true"></i><span data-ai-status-label><?= !empty($sobhanAiStatus['healthy']) ? 'AI متصل' : 'AI قطع' ?></span></a>
            <div class="notification-center" data-notification-center>
                <button class="notification-bell" type="button" data-notification-toggle aria-expanded="false" aria-label="اعلان‌ها">
                    <span class="notification-bell-icon" aria-hidden="true"></span>
                    <b data-notification-count <?= $notificationUnreadCount > 0 ? '' : 'hidden' ?>><?= e((string)$notificationUnreadCount) ?></b>
                </button>
                <div class="notification-dropdown" data-notification-dropdown hidden>
                    <header>
                        <strong>اعلان‌ها</strong>
                        <button type="button" data-notification-read-all>خواندن همه</button>
                    </header>
                    <div class="notification-list" data-notification-list>
                        <p class="muted">در حال دریافت اعلان‌ها...</p>
                    </div>
                    <footer>
                        <button class="btn btn-small" type="button" data-enable-push>فعال‌سازی اعلان روی این دستگاه</button>
                        <a href="/admin/notification-settings.php">تنظیمات</a>
                    </footer>
                </div>
            </div>
            <div class="admin-user"><span><?= e($user['name'] ?? '') ?></span><a href="/admin/theme-settings.php">ظاهر</a><a href="/logout.php">خروج</a></div>
        </div>
    </header>
    <script>
    window.SobhanNotifications = {
        loggedIn: true,
        csrfToken: <?= json_encode(Auth::csrfToken(), JSON_UNESCAPED_UNICODE) ?>,
        vapidPublicKey: <?= json_encode($notificationPublicKey, JSON_UNESCAPED_UNICODE) ?>,
        serviceWorker: '/service-worker.js'
    };
    </script>
    <section class="admin-content">
    <div class="app-toast-region" data-toast-region aria-live="polite" aria-atomic="true"></div>
    <?php if ($flash = flash()): ?><div class="alert alert-<?= e($flash['type']) ?>" role="<?= $flash['type'] === 'danger' ? 'alert' : 'status' ?>" data-app-notice><span><?= e($flash['message']) ?></span><button type="button" data-notice-close aria-label="بستن پیام">×</button></div><?php endif; ?>
