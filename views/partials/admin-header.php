<?php
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Response.php';
require_once __DIR__ . '/../../core/Pwa.php';
$user = Auth::user();
$pwaVersion = Pwa::version();
$pwaThemeColor = Pwa::value('pwa_theme_color');
$pwaFavicon = Pwa::asset('pwa_favicon');
$pwaAppleIcon = Pwa::asset('pwa_icon_192');
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
</head>
<body class="admin-body">
<div class="admin-shell">
<?php require __DIR__ . '/admin-sidebar.php'; ?>
<main class="admin-main">
    <header class="admin-topbar">
        <button class="menu-toggle" type="button" data-sidebar-toggle aria-controls="adminSidebar" aria-expanded="false" aria-label="باز کردن منو"><span></span><span></span><span></span></button>
        <div class="admin-topbar-title"><strong><?= e($pageTitle ?? 'پنل مدیریت') ?></strong><small><?= e(setting('company_name', 'شرکت پخش سبحان')) ?></small></div>
        <div class="admin-user"><span><?= e($user['name'] ?? '') ?></span> <a href="/logout.php">خروج</a></div>
    </header>
    <section class="admin-content">
    <?php if ($flash = flash()): ?><div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div><?php endif; ?>
