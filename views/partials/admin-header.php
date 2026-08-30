<?php
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Response.php';
require_once __DIR__ . '/../../core/Pwa.php';
require_once __DIR__ . '/../../core/ThemeProfile.php';
require_once __DIR__ . '/../../lib/NotificationService.php';
require_once __DIR__ . '/../../lib/PushNotificationService.php';
$user = Auth::user();
$pwaVersion = Pwa::version();
$pwaThemeColor = Pwa::value('pwa_theme_color');
$pwaFavicon = Pwa::asset('pwa_favicon');
$pwaAppleIcon = Pwa::asset('pwa_icon_192');
$themePreference = ThemeProfile::forUser((int)($user['id'] ?? 0));
$adminBodyClasses = array_merge(ThemeProfile::bodyClasses($themePreference), $adminBodyClasses ?? []);
$adminBodyClass = trim('admin-body ' . implode(' ', array_map(static fn($class) => preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$class), $adminBodyClasses)));
$adminExtraStylesheets = array_values(array_unique($adminExtraStylesheets ?? []));
$themeStyle = ThemeProfile::inlineStyle($themePreference);
$adminBodyStyle = $themeStyle . ($adminBodyStyle ?? '');
$notificationUnreadCount = 0;
$notificationPublicKey = '';
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
    <link rel="stylesheet" href="/assets/css/app.css">
    <link rel="stylesheet" href="/assets/css/admin.css">
    <link rel="stylesheet" href="/assets/css/hr.css">
    <link rel="stylesheet" href="/assets/css/ui-modernization.css">
    <?php foreach ($adminExtraStylesheets as $stylesheet): ?><link rel="stylesheet" href="<?= e($stylesheet) ?>"><?php endforeach; ?>
    <link rel="stylesheet" href="/assets/css/admin-theme-profiles.css">
    <link rel="stylesheet" href="/assets/css/admin-redesign-2026.css">
</head>
<body class="<?= e($adminBodyClass) ?>"<?= !empty($adminBodyStyle) ? ' style="' . e($adminBodyStyle) . '"' : '' ?>>
<div class="admin-shell">
<?php require __DIR__ . '/admin-sidebar.php'; ?>
<main class="admin-main">
    <header class="admin-topbar">
        <button class="menu-toggle" type="button" data-sidebar-toggle aria-controls="adminSidebar" aria-expanded="false" aria-label="باز کردن منو"><span></span><span></span><span></span></button>
        <div class="admin-topbar-title"><strong><?= e($pageTitle ?? 'پنل مدیریت') ?></strong><small><?= e(setting('company_name', 'شرکت پخش سبحان')) ?></small></div>
        <div class="admin-topbar-tools">
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
    <?php if ($flash = flash()): ?><div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div><?php endif; ?>
