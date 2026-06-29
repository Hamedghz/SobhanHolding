<?php

function admin_menu_registry(): array
{
    return [
        'dashboards' => ['title' => 'داشبوردها', 'items' => [
            ['title' => 'داشبورد اصلی', 'url' => '/admin/index.php', 'permission' => 'dashboard', 'active' => ['index.php']],
            ['title' => 'پنل مدیرعامل', 'url' => '/admin/ceo-dashboard.php', 'any' => ['view_ceo_dashboard', 'ceo_dashboard'], 'active' => ['ceo-dashboard.php']],
            ['title' => 'پنل مدیران فروش', 'url' => '/admin/manager-dashboard.php', 'permission' => 'manager_dashboard.view', 'active' => ['manager-dashboard.php']],
            ['title' => 'پنل کارمند', 'url' => '/admin/employee-dashboard.php', 'permission' => 'employee_portal', 'fallback_role' => 'employee', 'active' => ['employee-dashboard.php']],
        ]],
        'personal_planner' => ['title' => 'برنامه کاری من', 'items' => [
            ['title' => 'برنامه کاری', 'url' => '/admin/personal-planner.php', 'permission' => 'dashboard', 'active' => ['personal-planner.php']],
            ['title' => 'گزارش برنامه کاری', 'url' => '/admin/personal-planner-report.php', 'permission' => 'dashboard', 'active' => ['personal-planner-report.php']],
            ['title' => 'تنظیمات برنامه کاری', 'url' => '/admin/personal-planner-settings.php', 'permission' => 'dashboard', 'active' => ['personal-planner-settings.php']],
        ]],
        'sales' => ['title' => 'فروش و تحلیل عملکرد', 'items' => [
            ['title' => 'ورود گزارش فروش', 'url' => '/admin/manager-dashboard-import.php', 'permission' => 'manager_dashboard.import', 'active' => ['manager-dashboard-import.php']],
            ['title' => 'خروجی گزارش فروش', 'url' => '/admin/manager-dashboard-export.php', 'permission' => 'manager_dashboard.export', 'active' => ['manager-dashboard-export.php']],
            ['title' => 'اطلاعات لاین‌ها', 'url' => '/admin/ceo-dashboard-settings.php#lines', 'permission' => 'ceo_dashboard', 'action' => 'edit', 'active' => ['ceo-dashboard-lines.php']],
            ['title' => 'اطلاعات ویزیتورها', 'url' => '/admin/ceo-dashboard-settings.php#visitors', 'permission' => 'ceo_dashboard', 'action' => 'edit', 'active' => ['ceo-dashboard-visitors.php']],
        ]],
        'management_reports' => ['title' => 'گزارشات مدیران', 'items' => [
            ['title' => 'آماده‌سازی گزارش مدیران فروش', 'url' => '/admin/management-report-prepare.php?type=sales', 'management_report_type' => 'sales', 'active_paths' => ['/admin/management-report-prepare.php']],
            ['title' => 'آماده‌سازی گزارش مدیر مالی', 'url' => '/admin/management-report-prepare.php?type=finance', 'management_report_type' => 'finance', 'active_paths' => ['/admin/management-report-prepare.php']],
            ['title' => 'آماده‌سازی گزارش مدیر انبار', 'url' => '/admin/management-report-prepare.php?type=warehouse', 'management_report_type' => 'warehouse', 'active_paths' => ['/admin/management-report-prepare.php']],
            ['title' => 'آماده‌سازی گزارش مدیر فناوری', 'url' => '/admin/management-report-prepare.php?type=technology', 'management_report_type' => 'technology', 'active_paths' => ['/admin/management-report-prepare.php']],
            ['title' => 'گزارشات مدیران', 'url' => '/admin/management-reports.php', 'management_reports_hub' => true, 'active_paths' => ['/admin/management-reports.php','/admin/management-report-view.php','/admin/management-report-attachment.php']],
            ['title' => 'تنظیمات قالب گزارشات', 'url' => '/admin/management-report-template-settings.php', 'permission' => 'management_reports.templates', 'action' => 'edit', 'active_paths' => ['/admin/management-report-template-settings.php']],
        ]],
        'management_governance' => ['title' => 'قوانین مصوب و صورتجلسات', 'items' => [
            ['title' => 'صورتجلسات', 'url' => '/admin/management-meetings.php', 'management_governance' => true, 'active_paths' => ['/admin/management-meetings.php','/admin/management-meeting-view.php']],
            ['title' => 'مصوبات و پیگیری‌ها', 'url' => '/admin/management-decisions.php', 'management_governance' => true, 'active_paths' => ['/admin/management-decisions.php','/admin/management-decision-view.php']],
            ['title' => 'قوانین مصوب', 'url' => '/admin/management-rules.php', 'management_governance' => true, 'active_paths' => ['/admin/management-rules.php']],
        ]],
        'hr' => ['title' => 'منابع انسانی', 'items' => [
            ['title' => 'کاربران و پرسنل', 'url' => '/admin/users.php', 'permission' => 'users', 'active' => ['users.php']],
            ['title' => 'ایمپورت و اکسپورت کاربران', 'url' => '/admin/users-import-export.php', 'permission' => 'users.import_export', 'active' => ['users-import-export.php']],
            ['title' => 'واحد، نقش و ساختار سازمانی', 'url' => '/admin/hr-settings.php', 'permission' => 'hr_settings', 'active' => ['hr-settings.php']],
            ['title' => 'ثبت کارکرد روزانه', 'url' => '/admin/hr-attendance.php', 'permission' => 'hr_attendance', 'active' => ['hr-attendance.php']],
            ['title' => 'تنظیمات ساعات کاری', 'url' => '/admin/hr-attendance-settings.php', 'permission' => 'hr_attendance.settings', 'action' => 'edit', 'active' => ['hr-attendance-settings.php']],
            ['title' => 'روزهای تعطیل', 'url' => '/admin/hr-holidays.php', 'permission' => 'hr_attendance.settings', 'action' => 'edit', 'active' => ['hr-holidays.php']],
            ['title' => 'گزارش کارکرد', 'url' => '/admin/hr-attendance-reports.php', 'permission' => 'hr_attendance.reports', 'active' => ['hr-attendance-reports.php']],
            ['title' => 'پروفایل سازمانی من', 'url' => '/admin/employee-profile.php', 'permission' => 'employee_portal', 'fallback_role' => 'employee', 'active' => ['employee-profile.php']],
        ]],
        'kpi' => ['title' => 'KPI و ارزیابی عملکرد', 'items' => [
            ['title' => 'KPI منابع انسانی', 'url' => '/admin/hr-kpi.php', 'permission' => 'hr_kpi.view', 'active' => ['hr-kpi.php']],
            ['title' => 'نتایج KPI', 'url' => '/admin/hr-kpi-results.php', 'permission' => 'hr_kpi.results', 'active' => ['hr-kpi-results.php']],
            ['title' => 'قالب‌های ارزیابی', 'url' => '/admin/hr-kpi-templates.php', 'permission' => 'hr_kpi.manage', 'active' => ['hr-kpi-templates.php']],
            ['title' => 'دوره‌های ارزیابی', 'url' => '/admin/hr-kpi-periods.php', 'permission' => 'hr_kpi.manage', 'active' => ['hr-kpi-periods.php']],
            ['title' => 'امتیازدهی دوره‌ای', 'url' => '/admin/hr-kpi-scores.php', 'permission' => 'hr_kpi.score', 'active' => ['hr-kpi-scores.php']],
            ['title' => 'نتایج KPI من', 'url' => '/admin/employee-kpi.php', 'permission' => 'employee_portal', 'fallback_role' => 'employee', 'active' => ['employee-kpi.php']],
        ]],
        'assessments' => ['title' => 'آزمون‌ها و ارزیابی سازمانی', 'items' => [
            ['title' => 'آزمون‌های سازمانی', 'url' => '/admin/hr-assessment-tests.php', 'permission' => 'hr_assessments.manage', 'active' => ['hr-assessment-tests.php']],
            ['title' => 'تخصیص آزمون', 'url' => '/admin/employee-assessments.php', 'permission' => 'hr_assessments.manage', 'active' => ['employee-assessments.php']],
            ['title' => 'نتایج آزمون‌ها', 'url' => '/admin/hr-assessment-results.php', 'permission' => 'hr_assessments.results', 'active' => ['hr-assessment-results.php']],
            ['title' => 'آزمون‌های من', 'url' => '/admin/employee-tests.php', 'permission' => 'employee_portal', 'fallback_role' => 'employee', 'active' => ['employee-tests.php', 'employee-test-run.php', 'employee-assessment-results.php']],
        ]],
        'finance' => ['title' => 'مالی، انبار و وصول', 'items' => [
            ['title' => 'دوره‌های حقوق و دستمزد', 'url' => '/admin/payroll-periods.php', 'any' => ['payroll.manage','payroll.publish'], 'active' => ['payroll-periods.php', 'payroll-fields.php']],
            ['title' => 'ایمپورت حقوق', 'url' => '/admin/payroll-import.php', 'permission' => 'payroll.import', 'active' => ['payroll-import.php']],
            ['title' => 'فیش‌های حقوقی', 'url' => '/admin/payroll-slips.php', 'permission' => 'payroll.view_all', 'active_paths' => ['/admin/payroll-slips.php','/admin/payroll-slip-view.php','/admin/payroll-slip-pdf.php','/admin/payroll-print.php','/admin/payroll-export.php']],
            ['title' => 'فیش‌های حقوقی من', 'url' => '/employee/payroll.php', 'permission' => 'payroll.own', 'fallback_role' => 'employee', 'active_paths' => ['/employee/payroll.php','/employee/payroll-slip.php','/employee/payroll-slip-pdf.php']],
            ['title' => 'وصول مطالبات', 'url' => '/admin/accounting-collections.php', 'permission' => 'accounting', 'active' => ['accounting-collections.php', 'accounting-status.php', 'accounting-image.php']],
            ['title' => 'تنظیمات حسابداری', 'url' => '/admin/accounting-settings.php', 'permission' => 'accounting', 'action' => 'edit', 'active' => ['accounting-settings.php']],
            ['title' => 'تنظیمات داروخانه', 'url' => '/admin/pharmacy-settings.php', 'permission' => 'pharmacy_settings', 'active' => ['pharmacy-settings.php']],
        ]],
        'content' => ['title' => 'سایت و محتوا', 'items' => [
            ['title' => 'مدیریت مستندات و آموزش', 'url' => '/admin/docs.php', 'permission' => 'docs.manage', 'active_paths' => ['/admin/docs.php','/admin/docs-edit.php','/admin/docs-categories.php','/admin/docs-view-logs.php']],
            ['title' => 'مستندات و آموزش من', 'url' => '/employee/docs.php', 'permission' => 'docs.view', 'fallback_role' => 'employee', 'active_paths' => ['/employee/docs.php','/employee/doc-view.php','/admin/doc-download.php']],
            ['title' => 'فایل‌ها', 'url' => '/admin/files.php', 'permission' => 'files', 'active' => ['files.php', 'download-file.php']],
            ['title' => 'اسلایدر و بنرها', 'url' => '/admin/carousel.php', 'permission' => 'carousel', 'active' => ['carousel.php']],
        ]],
        'correspondence' => ['title' => 'مکاتبات اداری', 'items' => [
            ['title' => 'پیام‌رسان سازمانی', 'url' => '/employee/messenger.php', 'permission' => 'messenger.view', 'active_paths' => ['/employee/messenger.php','/employee/message-inbox.php','/messenger/','/messenger/report-view.php']],
            ['title' => 'داشبورد پیام‌رسان', 'url' => '/admin/messenger-dashboard.php', 'permission' => 'messenger.admin.dashboard', 'active' => ['messenger-dashboard.php']],
            ['title' => 'مدیریت گروه‌ها', 'url' => '/admin/messenger-groups.php', 'permission' => 'messenger.group.manage', 'active' => ['messenger-groups.php']],
            ['title' => 'مدیریت کانال‌ها', 'url' => '/admin/messenger-channels.php', 'permission' => 'messenger.channel.manage', 'active' => ['messenger-channels.php']],
            ['title' => 'ارسال سراسری', 'url' => '/admin/messenger-broadcast.php', 'permission' => 'messenger.broadcast.send', 'active' => ['messenger-broadcast.php']],
            ['title' => 'گزارش تخلف پیام‌ها', 'url' => '/admin/messenger-reports.php', 'permission' => 'messenger.admin.reports', 'active' => ['messenger-reports.php']],
            ['title' => 'ممیزی پیام‌رسان', 'url' => '/admin/messenger-audit.php', 'permission' => 'messenger.admin.audit', 'active' => ['messenger-audit.php']],
            ['title' => 'تنظیمات پیام‌رسان', 'url' => '/admin/messenger-settings.php', 'permission' => 'messenger.admin.settings', 'active' => ['messenger-settings.php']],
            ['title' => 'نامه‌های سازمانی', 'url' => '/admin/letters.php', 'letter_capability' => 'view', 'active' => ['letters.php', 'letter-create.php', 'letter-view.php', 'letter-print.php', 'letter-pdf.php']],
            ['title' => 'قالب‌های نامه', 'url' => '/admin/letter-templates.php', 'permission' => 'letters.settings', 'action' => 'edit', 'active' => ['letter-templates.php']],
            ['title' => 'سربرگ‌ها', 'url' => '/admin/letter-letterheads.php', 'permission' => 'letters.settings', 'action' => 'edit', 'active' => ['letter-letterheads.php']],
            ['title' => 'امضاکنندگان', 'url' => '/admin/letter-signatures.php', 'permission' => 'letters.settings', 'action' => 'edit', 'active' => ['letter-signatures.php']],
            ['title' => 'تنظیمات نامه', 'url' => '/admin/letter-settings.php', 'permission' => 'letters.settings', 'action' => 'edit', 'active' => ['letter-settings.php']],
        ]],
        'email_hub' => ['title' => 'ایمیل سازمانی', 'items' => [
            ['title' => 'صندوق ایمیل', 'url' => '/admin/email-inbox.php', 'email_capability' => 'read', 'active' => ['email-inbox.php','email-message.php','email-attachment.php']],
            ['title' => 'ارسال ایمیل', 'url' => '/admin/email-compose.php', 'email_capability' => 'send', 'active' => ['email-compose.php']],
            ['title' => 'حساب‌های ایمیل', 'url' => '/admin/email-accounts.php', 'permission' => 'email_hub.accounts', 'action' => 'edit', 'active' => ['email-accounts.php']],
            ['title' => 'سرویس‌دهندگان', 'url' => '/admin/email-providers.php', 'permission' => 'email_hub.providers', 'action' => 'edit', 'active' => ['email-providers.php']],
            ['title' => 'قالب‌های ایمیل', 'url' => '/admin/email-templates.php', 'permission' => 'email_hub', 'active' => ['email-templates.php']],
            ['title' => 'قوانین خودکار', 'url' => '/admin/email-rules.php', 'permission' => 'email_hub.rules', 'action' => 'edit', 'active' => ['email-rules.php']],
            ['title' => 'گزارش ایمیل', 'url' => '/admin/email-reports.php', 'permission' => 'email_hub.reports', 'active' => ['email-reports.php']],
        ]],
        'ai' => ['title' => 'هوش مصنوعی و گزارش‌گیری', 'items' => [
            ['title' => 'تنظیمات API سبحان', 'url' => '/admin/sobhan-api-settings.php', 'any' => ['view_sobhan_api_settings', 'manage_sobhan_api_settings'], 'active' => ['sobhan-api-settings.php']],
            ['title' => 'AI Insight', 'url' => '/admin/ai-insights.php', 'permission' => 'ai_insights', 'active' => ['ai-insights.php']],
            ['title' => 'گفتگوی هوش مصنوعی', 'url' => '/admin/ai-chat.php', 'all' => ['view_ai_chat', 'use_ai_assistant'], 'active' => ['ai-chat.php']],
            ['title' => 'پایگاه دانش و ایندکس', 'url' => '/admin/knowledge.php', 'permission' => 'manage_knowledge', 'active' => ['knowledge.php']],
        ]],
        'settings' => ['title' => 'تنظیمات', 'items' => [
            ['title' => 'اعلان ویندوز و دستگاه‌ها', 'url' => '/admin/notification-devices.php', 'permission' => 'dashboard', 'active' => ['notification-devices.php']],
            ['title' => 'ظاهر پنل من', 'url' => '/admin/theme-settings.php', 'permission' => 'dashboard', 'active' => ['theme-settings.php']],
            ['title' => 'تنظیمات اعلان‌ها', 'url' => '/admin/notification-settings.php', 'permission' => 'dashboard', 'active' => ['notification-settings.php']],
            ['title' => 'تنظیمات عمومی', 'url' => '/admin/settings.php', 'permission' => 'settings', 'active' => ['settings.php']],
            ['title' => 'تنظیمات داشبورد مدیرعامل', 'url' => '/admin/ceo-dashboard-settings.php', 'permission' => 'ceo_dashboard', 'action' => 'edit', 'active' => ['ceo-dashboard-settings.php']],
            ['title' => 'تنظیمات داشبورد مدیران', 'url' => '/admin/manager-dashboard-settings.php', 'any' => ['manager_dashboard.settings', 'manager_dashboard.ai_settings'], 'active' => ['manager-dashboard-settings.php']],
        ]],
        'system' => ['title' => 'ابزارهای سیستم', 'items' => [
            ['title' => 'بکاپ فایل‌های سایت', 'url' => '/admin/uploaded-files-backup.php', 'permission' => 'file_backup.manage', 'active' => ['uploaded-files-backup.php']],
            ['title' => 'نصب، Seed و Migration', 'url' => '/admin/install-tools.php', 'permission' => 'system_maintenance', 'active' => ['install-tools.php', 'system-maintenance.php']],
            ['title' => 'تست و لاگ اعلان‌ها', 'url' => '/admin/notification-test.php', 'roles' => ['admin', 'super_admin'], 'active' => ['notification-test.php']],
            ['title' => 'راه‌اندازی منابع انسانی', 'url' => '/install/sobhan_hr_seed.php', 'permission' => 'system_maintenance', 'active' => ['sobhan_hr_seed.php']],
            ['title' => 'نصب پلنر شخصی', 'url' => '/install/personal_planner_seed.php', 'permission' => 'system_maintenance', 'active' => ['personal_planner_seed.php']],
            ['title' => 'نصب قوانین و صورتجلسات', 'url' => '/install/management_meetings_seed.php', 'permission' => 'system_maintenance', 'active' => ['management_meetings_seed.php']],
            ['title' => 'نصب حضور و کارکرد', 'url' => '/install/hr_attendance_seed.php', 'permission' => 'system_maintenance', 'active' => ['hr_attendance_seed.php']],
        ]],
    ];
}

function admin_menu_allowed(array $item): bool
{
    if (!empty($item['management_governance'])) {
        if (!class_exists('ManagementMeetingsRepository')) require_once __DIR__ . '/ManagementMeetingsRepository.php';
        return ManagementMeetingsRepository::menuAllowed();
    }
    if (isset($item['management_report_type']) || !empty($item['management_reports_hub'])) {
        if (!class_exists('ManagementReportsRepository')) require_once __DIR__ . '/ManagementReportsRepository.php';
        if (isset($item['management_report_type'])) return ManagementReportsRepository::canPrepare((string)$item['management_report_type']);
        if (ManagementReportsRepository::canAggregate() || ManagementReportsRepository::canReview()) return true;
        foreach (array_keys(ManagementReportsRepository::TYPES) as $type) if (ManagementReportsRepository::canPrepare($type)) return true;
        return false;
    }
    if (isset($item['email_capability'])) {
        if (!class_exists('EmailHubModule')) require_once __DIR__ . '/../core/EmailHubModule.php';
        return count(EmailHubModule::accessibleAccounts((string)$item['email_capability'])) > 0;
    }
    if (isset($item['letter_capability'])) {
        if (!class_exists('LetterModule')) require_once __DIR__ . '/../core/LetterModule.php';
        return LetterModule::can((string)$item['letter_capability']);
    }
    if (!empty($item['super_admin'])) return Auth::isSuperAdmin();
    if (isset($item['roles']) && in_array(Auth::user()['role'] ?? '', $item['roles'], true)) return true;
    if (isset($item['role'])) return (Auth::user()['role'] ?? '') === $item['role'];
    if (isset($item['fallback_role']) && (Auth::user()['role'] ?? '') === $item['fallback_role']) return true;
    if (isset($item['all'])) {
        foreach ($item['all'] as $permission) if (!Auth::can($permission)) return false;
        return true;
    }
    if (isset($item['any'])) {
        foreach ($item['any'] as $permission) if (Auth::can($permission)) return true;
        return false;
    }
    return Auth::can($item['permission'] ?? 'dashboard', $item['action'] ?? 'view');
}

function admin_menu_is_active(array $item, string $path): bool
{
    $pathOnly = parse_url($path, PHP_URL_PATH) ?: $path;
    if (isset($item['management_report_type'])) {
        parse_str((string)(parse_url($path, PHP_URL_QUERY) ?? ''), $query);
        return $pathOnly === '/admin/management-report-prepare.php' && ($query['type'] ?? '') === $item['management_report_type'];
    }
    if (isset($item['active_paths'])) return in_array($pathOnly, $item['active_paths'], true);
    $currentFile = basename($pathOnly);
    return in_array($currentFile, $item['active'] ?? [basename(parse_url($item['url'], PHP_URL_PATH) ?: $item['url'])], true);
}
