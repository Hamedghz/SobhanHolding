<?php
require_once __DIR__ . '/../../core/Response.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Pwa.php';
require_once __DIR__ . '/../../core/ThemeProfile.php';
$pwaVersion = Pwa::version();
$pwaThemeColor = Pwa::value('pwa_theme_color');
$pwaFavicon = Pwa::asset('pwa_favicon');
$pwaAppleIcon = Pwa::asset('pwa_icon_192');
$publicUser = Auth::user();
$publicTheme = $publicUser ? ThemeProfile::forUser((int)$publicUser['id']) : ThemeProfile::defaults();
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= e(setting('meta_description', 'سامانه داخلی شرکت پخش سبحان')) ?>">
    <title><?= e(setting('site_title', 'شرکت پخش سبحان')) ?></title>
    <link rel="manifest" href="/manifest.php?v=<?= e($pwaVersion) ?>">
    <meta name="theme-color" content="<?= e($pwaThemeColor) ?>">
    <?php if ($pwaFavicon): ?><link rel="icon" href="<?= e($pwaFavicon) ?>"><?php endif; ?>
    <?php if ($pwaAppleIcon): ?><link rel="apple-touch-icon" href="<?= e($pwaAppleIcon) ?>"><?php endif; ?>
    <link rel="stylesheet" href="/assets/css/app.css">
    <link rel="stylesheet" href="/assets/css/admin-theme-profiles.css">
</head>
<body class="<?= e(implode(' ', ThemeProfile::bodyClasses($publicTheme))) ?>" style="<?= e(ThemeProfile::inlineStyle($publicTheme)) ?>">
<header class="site-header">
    <a class="brand" href="/">
        <?php if (setting('logo_path')): ?><img src="<?= e(setting('logo_path')) ?>" alt="لوگو"><?php endif; ?>
        <span><?= e(setting('company_name', 'شرکت پخش سبحان')) ?></span>
    </a>
    <nav class="site-nav">
        <a href="/">خانه</a>
        <a href="#contact">تماس</a>
    </nav>
</header>
<?php if ($publicUser): ?>
<script>
window.SobhanNotifications = {
    loggedIn: true,
    csrfToken: <?= json_encode(Auth::csrfToken(), JSON_UNESCAPED_UNICODE) ?>,
    vapidPublicKey: '',
    serviceWorker: '/service-worker.js'
};
if ('serviceWorker' in navigator && (location.protocol === 'https:' || ['localhost', '127.0.0.1'].includes(location.hostname))) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/service-worker.js').catch(error => console.warn('Service worker registration failed:', error));
    });
}
</script>
<?php endif; ?>
