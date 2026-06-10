<?php $isAdmin = Auth::isAdmin(); ?>
<aside class="admin-sidebar" id="adminSidebar">
    <a class="admin-logo" href="/admin/index.php"><?= e(setting('company_name', 'شرکت پخش سبحان')) ?></a>
    <nav>
        <?php if (Auth::can('dashboard')): ?><a href="/admin/index.php">داشبورد</a><?php endif; ?>
        <?php if (Auth::can('users')): ?>
            <a href="/admin/users.php">کاربران</a>
        <?php endif; ?>
        <?php if (Auth::can('kpis')): ?>
            <a href="/admin/kpis.php">شاخص‌ها</a>
        <?php endif; ?>
        <?php if (Auth::can('surveys')): ?>
            <a href="/admin/surveys.php">نظرسنجی‌ها</a>
        <?php endif; ?>
        <?php if (Auth::can('survey_results')): ?><a href="/admin/survey-results.php">نتایج ارزیابی</a><?php endif; ?>
        <?php if (Auth::can('files')): ?><a href="/admin/files.php">فایل‌ها</a><?php endif; ?>
        <?php if (Auth::can('carousel')): ?>
            <a href="/admin/carousel.php">اسلایدر صفحه اصلی</a>
        <?php endif; ?>
        <?php if (Auth::can('settings')): ?>
            <a href="/admin/settings.php">تنظیمات سایت</a>
        <?php endif; ?>
    </nav>
</aside>
