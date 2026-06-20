<?php
function admin_menu_registry(): array
{
    return [
        'dashboards'=>['title'=>'داشبوردها','items'=>[
            ['title'=>'داشبورد اصلی','url'=>'/admin/index.php','permission'=>'dashboard','active'=>['/admin/index.php']],
            ['title'=>'داشبورد مدیرعامل','url'=>'/admin/ceo-dashboard.php','any'=>['view_ceo_dashboard','ceo_dashboard'],'active'=>['ceo-dashboard']],
            ['title'=>'داشبورد مدیران','url'=>'/admin/manager-dashboard.php','permission'=>'manager_dashboard.view','active'=>['manager-dashboard.php']],
        ]],
        'performance'=>['title'=>'فروش و عملکرد','items'=>[
            ['title'=>'شاخص‌ها','url'=>'/admin/kpis.php','permission'=>'kpis','active'=>['/admin/kpis.php']],
            ['title'=>'گزارش‌های مدیران فروش','url'=>'/admin/manager-dashboard.php','permission'=>'manager_dashboard.view','active'=>['manager-dashboard']],
            ['title'=>'نتایج ارزیابی','url'=>'/admin/survey-results.php','permission'=>'survey_results','active'=>['survey-results']],
        ]],
        'hr'=>['title'=>'منابع انسانی','items'=>[
            ['title'=>'پرسنل و دسترسی‌ها','url'=>'/admin/users.php','permission'=>'users','active'=>['users.php']],
            ['title'=>'داشبورد KPI','url'=>'/admin/hr-kpi.php','permission'=>'hr_kpi.view','active'=>['hr-kpi.php']],
            ['title'=>'قالب‌های ارزیابی','url'=>'/admin/hr-kpi-templates.php','permission'=>'hr_kpi.manage','active'=>['hr-kpi-templates']],
            ['title'=>'دوره‌های ارزیابی','url'=>'/admin/hr-kpi-periods.php','permission'=>'hr_kpi.manage','active'=>['hr-kpi-periods']],
            ['title'=>'ثبت امتیاز KPI','url'=>'/admin/hr-kpi-scores.php','permission'=>'hr_kpi.score','active'=>['hr-kpi-scores']],
            ['title'=>'نتایج KPI','url'=>'/admin/hr-kpi-results.php','permission'=>'hr_kpi.results','active'=>['hr-kpi-results']],
            ['title'=>'آزمون‌های سازمانی','url'=>'/admin/hr-assessment-tests.php','permission'=>'hr_assessments.manage','active'=>['hr-assessment-tests']],
            ['title'=>'تخصیص آزمون','url'=>'/admin/employee-assessments.php','permission'=>'hr_assessments.manage','active'=>['employee-assessments']],
            ['title'=>'نتایج آزمون‌ها','url'=>'/admin/hr-assessment-results.php','permission'=>'hr_assessments.results','active'=>['hr-assessment-results']],
            ['title'=>'آزمون‌های من','url'=>'/employee/tests.php','role'=>'employee','active'=>['/employee/tests.php']],
            ['title'=>'نتایج KPI من','url'=>'/employee/kpi-results.php','role'=>'employee','active'=>['/employee/kpi-results.php']],
        ]],
        'finance'=>['title'=>'مالی و وصول','items'=>[
            ['title'=>'وصول مطالبات','url'=>'/admin/accounting-collections.php','permission'=>'accounting','active'=>['accounting-collections']],
            ['title'=>'تنظیمات حسابداری','url'=>'/admin/accounting-settings.php','permission'=>'accounting','action'=>'edit','active'=>['accounting-settings']],
        ]],
        'ai'=>['title'=>'هوش مصنوعی و دانش','items'=>[
            ['title'=>'گفتگوی هوش مصنوعی','url'=>'/admin/ai-chat.php','all'=>['view_ai_chat','use_ai_assistant'],'active'=>['ai-chat']],
            ['title'=>'تنظیمات هوش مصنوعی','url'=>'/admin/sobhan-api-settings.php','any'=>['view_sobhan_api_settings','manage_sobhan_api_settings'],'active'=>['sobhan-api-settings']],
            ['title'=>'AI Insight','url'=>'/admin/ai-insights.php','permission'=>'ai_insights','active'=>['ai-insights']],
            ['title'=>'پایگاه دانش و ایندکس','url'=>'/admin/knowledge.php','permission'=>'manage_knowledge','active'=>['knowledge.php']],
        ]],
        'content'=>['title'=>'سایت و محتوا','items'=>[
            ['title'=>'فایل‌ها','url'=>'/admin/files.php','permission'=>'files','active'=>['files.php']],
            ['title'=>'نظرسنجی‌ها','url'=>'/admin/surveys.php','permission'=>'surveys','active'=>['surveys.php']],
            ['title'=>'اسلایدر و بنرها','url'=>'/admin/carousel.php','permission'=>'carousel','active'=>['carousel']],
        ]],
        'system'=>['title'=>'تنظیمات سیستم','items'=>[
            ['title'=>'تنظیمات عمومی','url'=>'/admin/settings.php','permission'=>'settings','active'=>['settings.php']],
            ['title'=>'بروزرسانی SQL و Seed','url'=>'/admin/system-maintenance.php','permission'=>'system_maintenance','active'=>['system-maintenance']],
            ['title'=>'تنظیمات داشبورد مدیران','url'=>'/admin/manager-dashboard-settings.php','any'=>['manager_dashboard.settings','manager_dashboard.ai_settings'],'active'=>['manager-dashboard-settings']],
            ['title'=>'تنظیمات داروخانه','url'=>'/admin/pharmacy-settings.php','permission'=>'pharmacy_settings','active'=>['pharmacy-settings']],
        ]],
    ];
}
function admin_menu_allowed(array $item): bool
{
    if(isset($item['role']))return (Auth::user()['role']??'')===$item['role'];
    if(isset($item['all'])){foreach($item['all'] as $permission)if(!Auth::can($permission))return false;return true;}
    if(isset($item['any'])){foreach($item['any'] as $permission)if(Auth::can($permission))return true;return false;}
    return Auth::can($item['permission']??'dashboard',$item['action']??'view');
}
function admin_menu_is_active(array $item,string $path): bool
{
    foreach($item['active']??[$item['url']] as $pattern)if(str_contains($path,$pattern))return true;return false;
}
