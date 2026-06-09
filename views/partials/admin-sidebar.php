<?php $isAdmin = Auth::isAdmin(); ?>
<aside class="admin-sidebar" id="adminSidebar">
    <a class="admin-logo" href="/admin/index.php"><?= e(setting('company_name', 'شرکت پخش سبحان')) ?></a>
    <nav>
        <a href="/admin/index.php">داشبورد</a>
        <?php if ($isAdmin): ?>
            <a href="/admin/users.php">کاربران</a>
            <a href="/admin/kpis.php">شاخص‌ها</a>
            <a href="/admin/surveys.php">نظرسنجی‌ها</a>
        <?php endif; ?>
        <a href="/admin/survey-results.php">نتایج ارزیابی</a>
        <a href="/admin/files.php">فایل‌های من</a>
        <?php if ($isAdmin): ?>
            <a href="/admin/carousel.php">اسلایدر صفحه اصلی</a>
            <a href="/admin/settings.php">تنظیمات سایت</a>
        <?php endif; ?>
    </nav>
</aside>
