<?php
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Response.php';
$user = Auth::user();
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle ?? 'پنل مدیریت') ?> - <?= e(setting('company_name', 'شرکت پخش سبحان')) ?></title>
    <link rel="stylesheet" href="/assets/css/app.css">
    <link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body class="admin-body">
<div class="admin-shell">
<?php require __DIR__ . '/admin-sidebar.php'; ?>
<main class="admin-main">
    <header class="admin-topbar">
        <button class="menu-toggle" type="button" data-sidebar-toggle>☰</button>
        <div><strong><?= e($pageTitle ?? 'پنل مدیریت') ?></strong></div>
        <div class="admin-user"><?= e($user['name'] ?? '') ?> <a href="/logout.php">خروج</a></div>
    </header>
    <section class="admin-content">
    <?php if ($flash = flash()): ?><div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div><?php endif; ?>
