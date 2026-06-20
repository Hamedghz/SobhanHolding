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
        <?php if (Auth::can('hr_kpi.view') || Auth::can('hr_kpi.results') || Auth::can('hr_assessments.manage') || Auth::can('hr_assessments.results')): ?>
            <details class="sidebar-group"<?= str_contains($_SERVER['PHP_SELF'] ?? '', 'hr-') || str_contains($_SERVER['PHP_SELF'] ?? '', 'employee-assessments') ? ' open' : '' ?>><summary>منابع انسانی</summary>
                <?php if (Auth::can('hr_kpi.view')): ?><a href="/admin/hr-kpi.php">شاخص‌های KPI</a><?php endif; ?>
                <?php if (Auth::can('hr_kpi.manage')): ?><a href="/admin/hr-kpi-templates.php">قالب‌های ارزیابی</a><a href="/admin/hr-kpi-periods.php">دوره‌های ارزیابی</a><?php endif; ?>
                <?php if (Auth::can('hr_kpi.score')): ?><a href="/admin/hr-kpi-scores.php">ثبت امتیاز</a><?php endif; ?>
                <?php if (Auth::can('hr_kpi.results')): ?><a href="/admin/hr-kpi-results.php">نتایج KPI</a><?php endif; ?>
                <?php if (Auth::can('hr_assessments.manage')): ?><a href="/admin/employee-assessments.php">تخصیص آزمون</a><a href="/admin/hr-assessment-tests.php">آزمون‌های سازمانی</a><?php endif; ?>
                <?php if (Auth::can('hr_assessments.results')): ?><a href="/admin/hr-assessment-results.php">نتایج آزمون</a><?php endif; ?>
            </details>
        <?php endif; ?>
        <?php if (Auth::isEmployee()): ?><a href="/employee/tests.php">آزمون‌های من</a><a href="/employee/kpi-results.php">نتایج KPI من</a><?php endif; ?>
        <?php if (Auth::can('files')): ?><a href="/admin/files.php">فایل‌ها</a><?php endif; ?>
        <?php if (Auth::can('accounting')): ?><a href="/admin/accounting-collections.php">دریافت‌های حسابداری</a><?php endif; ?>
        <?php if (Auth::can('accounting', 'edit')): ?><a href="/admin/accounting-settings.php">تنظیمات حسابداری</a><?php endif; ?>
        <?php if (Auth::can('view_ceo_dashboard') || Auth::can('ceo_dashboard')): ?><a href="/admin/ceo-dashboard.php">داشبورد مدیرعامل</a><?php endif; ?>
        <?php if (Auth::can('manager_dashboard.view')): ?>
            <details class="sidebar-group"<?= str_contains($_SERVER['PHP_SELF'] ?? '', 'manager-dashboard') ? ' open' : '' ?>>
                <summary>پنل مدیران فروش</summary>
                <a href="/admin/manager-dashboard.php">داشبورد مدیران</a>
                <?php if (Auth::can('manager_dashboard.settings') || Auth::can('manager_dashboard.ai_settings')): ?><a href="/admin/manager-dashboard-settings.php">تنظیمات داشبورد مدیران</a><?php endif; ?>
                <?php if (Auth::can('manager_dashboard.import')): ?><a href="/admin/manager-dashboard-import.php">ورودی / خروجی اکسل</a><?php endif; ?>
                <?php if (Auth::can('manager_dashboard.settings')): ?><a href="/admin/manager-dashboard-settings.php#general">تاریخچه گزارش‌ها</a><?php endif; ?>
                <?php if (Auth::can('manager_dashboard.ai_settings')): ?><a href="/admin/manager-dashboard-settings.php#ai">تنظیمات هوش مصنوعی</a><?php endif; ?>
                <?php if (Auth::can('manager_dashboard.ai_settings')): ?><a href="/admin/manager-dashboard-settings.php?tab=skills#skills">مهارت‌های داشبورد</a><?php endif; ?>
            </details>
        <?php endif; ?>
        <?php if (Auth::can('view_ai_chat') && Auth::can('use_ai_assistant')): ?><a href="/admin/ai-chat.php">هوش مصنوعی</a><?php endif; ?>
        <?php if (Auth::can('ai_insights')): ?><a href="/admin/ai-insights.php">تحلیل هوشمند</a><?php endif; ?>
        <?php if (Auth::can('manage_knowledge')): ?><a href="/admin/knowledge.php">منابع دانش</a><?php endif; ?>
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
