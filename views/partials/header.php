<?php require_once __DIR__ . '/../../core/Response.php'; ?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= e(setting('meta_description', 'سامانه داخلی شرکت پخش سبحان')) ?>">
    <title><?= e(setting('site_title', 'شرکت پخش سبحان')) ?></title>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
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
