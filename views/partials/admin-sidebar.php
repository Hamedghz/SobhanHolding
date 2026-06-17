<?php $isAdmin = Auth::isAdmin(); ?>
<div class="admin-sidebar-overlay" data-sidebar-overlay hidden></div>
<aside class="admin-sidebar" id="adminSidebar" aria-hidden="true">
    <a class="admin-logo" href="/admin/"><?= e(setting('company_name', 'شرکت پخش سبحان')) ?></a>
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
        <?php if (Auth::can('accounting')): ?><a href="/admin/accounting-collections.php">دریافت‌های حسابداری</a><?php endif; ?>
        <?php if (Auth::can('accounting', 'edit')): ?><a href="/admin/accounting-settings.php">تنظیمات حسابداری</a><?php endif; ?>
        <?php if (Auth::can('view_ceo_dashboard') || Auth::can('ceo_dashboard')): ?><a href="/admin/ceo-dashboard.php">داشبورد مدیرعامل</a><?php endif; ?>
        <?php if (Auth::can('view_ai_chat') && Auth::can('use_ai_assistant')): ?><a href="/admin/ai-chat.php">هوش مصنوعی</a><?php endif; ?>
        <?php if (Auth::can('view_sobhan_api_settings') || Auth::can('manage_sobhan_api_settings') || Auth::can('view_data_source_settings') || Auth::can('manage_data_source_settings')): ?><a href="/admin/sobhan-api-settings.php">تنظیمات API سبحان</a><?php endif; ?>
        <?php if (Auth::can('ceo_dashboard', 'edit')): ?><a href="/admin/ceo-dashboard-settings.php">تنظیمات داشبورد مدیرعامل</a><?php endif; ?>
        <?php if (Auth::can('pharmacy_settings')): ?><a href="/admin/pharmacy-settings.php">تنظیمات داروخانه</a><?php endif; ?>
        <?php if (Auth::can('carousel')): ?>
            <a href="/admin/carousel.php">اسلایدر صفحه اصلی</a>
        <?php endif; ?>
        <?php if (Auth::can('settings')): ?>
            <a href="/admin/settings.php">تنظیمات سایت</a>
        <?php endif; ?>
    </nav>
</aside>
