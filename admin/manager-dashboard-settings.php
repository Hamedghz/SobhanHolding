<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/ManagerDashboard.php';
require_once __DIR__ . '/../core/SobhanApiClient.php';
require_once __DIR__ . '/../core/ManagerDashboardCalculator.php';

Auth::requireLogin();
$canSettings = Auth::can('manager_dashboard.settings');
$canAiSettings = Auth::can('manager_dashboard.ai_settings');
if (!$canSettings && !$canAiSettings) { http_response_code(403); exit('شما اجازه دسترسی به تنظیمات داشبورد مدیران را ندارید.'); }
$pageTitle = 'تنظیمات داشبورد مدیران';
$permissionLabels = [
    'manager_dashboard.view'=>'مشاهده داشبورد مدیران', 'manager_dashboard.import'=>'ایمپورت اکسل',
    'manager_dashboard.export'=>'اکسپورت اکسل', 'manager_dashboard.edit'=>'ویرایش اطلاعات داشبورد',
    'manager_dashboard.image_export'=>'خروجی تصویری', 'manager_dashboard.settings'=>'تنظیمات داشبورد',
    'manager_dashboard.ai_settings'=>'تنظیمات هوش مصنوعی', 'manager_dashboard.ai_run'=>'اجرای تحلیل هوش مصنوعی',
];
$notice = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) { http_response_code(419); exit('درخواست نامعتبر است.'); }
    $action = $_POST['action'] ?? '';
    if (in_array($action, ['general','excel','image','widgets','access','default','delete'], true) && !$canSettings) { http_response_code(403); exit('دسترسی غیرمجاز'); }
    if (in_array($action, ['ai','ai_test','ai_refresh','skills','rules'], true) && !$canAiSettings) { http_response_code(403); exit('دسترسی غیرمجاز'); }
    if ($action === 'general') {
        ManagerDashboard::saveSettings([
            'dashboard_title'=>trim($_POST['dashboard_title'] ?? ''), 'default_report_id'=>(int)($_POST['default_report_id'] ?? 0),
            'default_report_mode'=>in_array($_POST['default_report_mode'] ?? '', ['latest_report','default_report'], true)?$_POST['default_report_mode']:'latest_report',
            'number_format'=>in_array($_POST['number_format'] ?? '', ['thousands','plain'], true)?$_POST['number_format']:'thousands',
            'currency_label'=>trim($_POST['currency_label'] ?? ''), 'date_format'=>($_POST['date_format'] ?? '')==='gregorian'?'gregorian':'jalali',
            'show_latest_report_by_default'=>isset($_POST['show_latest_report_by_default'])?1:0,
        ]); flash('success','تنظیمات عمومی ذخیره شد.');
    } elseif ($action === 'excel') {
        ManagerDashboard::saveSettings(['excel_strict_default'=>isset($_POST['excel_strict_default'])?1:0,'excel_max_size_mb'=>max(1,min(50,(int)($_POST['excel_max_size_mb']??10)))]);
        flash('success','تنظیمات اکسل ذخیره شد.');
    } elseif ($action === 'image') {
        ManagerDashboard::saveSettings([
            'image_export_enabled'=>isset($_POST['image_export_enabled'])?1:0,
            'image_export_format'=>($_POST['image_export_format']??'png')==='jpg'?'jpg':'png',
            'image_export_include_title'=>isset($_POST['image_export_include_title'])?1:0,
            'image_export_include_report_date'=>isset($_POST['image_export_include_report_date'])?1:0,
            'image_export_include_company_name'=>isset($_POST['image_export_include_company_name'])?1:0,
            'image_export_watermark_enabled'=>isset($_POST['image_export_watermark_enabled'])?1:0,
            'image_export_watermark_text'=>trim($_POST['image_export_watermark_text']??''),
        ]); flash('success','تنظیمات خروجی تصویری ذخیره شد.');
    } elseif ($action === 'widgets') {
        foreach (ManagerDashboard::widgets() as $key=>$meta) Database::execute('UPDATE manager_dashboard_widget_settings SET widget_title_fa=?,widget_description_fa=?,is_enabled=?,sort_order=?,show_in_dashboard=?,allow_import=?,allow_export=?,allow_image_export=?,allow_manual_edit=?,updated_at=NOW() WHERE widget_key=?',[
            trim($_POST['title'][$key]??$meta['title']),trim($_POST['description'][$key]??''),isset($_POST['enabled'][$key])?1:0,(int)($_POST['sort'][$key]??0),isset($_POST['show'][$key])?1:0,isset($_POST['import'][$key])?1:0,isset($_POST['export'][$key])?1:0,isset($_POST['image'][$key])?1:0,isset($_POST['edit'][$key])?1:0,$key]);
        flash('success','تنظیمات ویجت‌ها ذخیره شد.');
    } elseif ($action === 'access') {
        $stmt = Database::connection()->prepare('INSERT INTO user_permissions (user_id,module_key,can_view,can_create,can_edit,can_delete,created_at) VALUES (?,?,?,0,0,0,NOW()) ON DUPLICATE KEY UPDATE can_view=VALUES(can_view)');
        $userIds = array_map('intval', array_column(Database::fetchAll('SELECT id FROM users WHERE status="active"'), 'id'));
        foreach ($userIds as $userId) foreach ($permissionLabels as $key=>$label) $stmt->execute([$userId,$key,isset($_POST['permissions'][$key][$userId])?1:0]);
        flash('success','دسترسی کاربران ذخیره شد.');
    } elseif ($action === 'ai') {
        $knownSkillKeys = array_column(ManagerDashboard::defaultSkills(), 'key');
        $selected = array_values(array_intersect($knownSkillKeys, array_map('strval', $_POST['enabled_skills'] ?? [])));
        ManagerDashboard::saveSettings([
            'ai_enabled'=>isset($_POST['ai_enabled'])?1:0,
            'ai_skills_enabled'=>isset($_POST['ai_skills_enabled'])?1:0,
            'ai_show_buttons'=>isset($_POST['ai_show_buttons'])?1:0,
            'ai_read_latest_report'=>isset($_POST['ai_read_latest_report'])?1:0,
            'ai_read_selected_report'=>isset($_POST['ai_read_selected_report'])?1:0,
            'ai_read_history'=>isset($_POST['ai_read_history'])?1:0,
            'ai_enabled_skills'=>implode(',', $selected),
        ]);
        flash('تنظیمات هوش مصنوعی داشبورد ذخیره شد.');
    } elseif (in_array($action, ['ai_test','ai_refresh'], true)) {
        $test = (new SobhanApiClient())->get('/health');
        $testOk = $test['ok'] && (($test['data']['ok'] ?? true) !== false);
        if ($action === 'ai_refresh') {
            $notice = $testOk ? 'اتصال هوش مصنوعی با موفقیت بروزرسانی شد.' : 'بروزرسانی اتصال هوش مصنوعی ناموفق بود. تنظیمات Sobhan API را بررسی کنید.';
        } else {
            $notice = $testOk ? 'اتصال به سرویس هوش مصنوعی با موفقیت برقرار شد.' : 'اتصال به سرویس هوش مصنوعی ناموفق بود.';
        }
    } elseif ($action === 'skills') {
        foreach (ManagerDashboard::enabledSkills(false) as $skill) {
            $id = (int)$skill['id'];
            $input = trim((string)($_POST['input_schema_json'][$id] ?? ''));
            $output = trim((string)($_POST['output_schema_json'][$id] ?? ''));
            if (($input !== '' && json_decode($input, true) === null && json_last_error() !== JSON_ERROR_NONE) || ($output !== '' && json_decode($output, true) === null && json_last_error() !== JSON_ERROR_NONE)) {
                $notice = 'ساختار JSON مهارت‌ها معتبر نیست.';
                continue;
            }
            Database::execute('UPDATE manager_dashboard_ai_skills SET skill_title_fa=?,skill_description_fa=?,skill_type=?,is_enabled=?,sort_order=?,system_prompt=?,input_schema_json=?,output_schema_json=?,updated_at=NOW() WHERE id=?', [
                trim((string)($_POST['skill_title_fa'][$id] ?? $skill['skill_title_fa'])), trim((string)($_POST['skill_description_fa'][$id] ?? '')),
                trim((string)($_POST['skill_type'][$id] ?? 'analysis')), isset($_POST['is_enabled'][$id])?1:0, (int)($_POST['sort_order'][$id] ?? 0),
                trim((string)($_POST['system_prompt'][$id] ?? '')), $input, $output, $id,
            ]);
        }
        if ($notice === '') flash('مهارت‌های داشبورد ذخیره شد.');
    } elseif ($action === 'rules') {
        $values=[];
        foreach (ManagerDashboardCalculator::ruleDefaults() as $key=>$default) $values['rule_'.$key]=(float)($_POST[$key]??$default);
        ManagerDashboard::saveSettings($values);
        flash('قوانین محاسبات داشبورد ذخیره شد.');
    } elseif ($action === 'default') {
        Database::execute('UPDATE manager_dashboard_reports SET is_default=0'); Database::execute('UPDATE manager_dashboard_reports SET is_default=1 WHERE id=?',[(int)$_POST['id']]); flash('success','گزارش پیش‌فرض تغییر کرد.');
    } elseif ($action === 'delete') {
        Auth::requirePermission('manager_dashboard.edit'); Database::execute('DELETE FROM manager_dashboard_reports WHERE id=?',[(int)$_POST['id']]); flash('success','گزارش و داده‌های وابسته حذف شد.');
    }
    if (!in_array($action, ['ai_test','ai_refresh'], true) && $notice === '') redirect('/admin/manager-dashboard-settings.php'.($action==='skills'?'?tab=skills':'').'#'.($action==='widgets'?'widgets':($action==='access'?'access':$action)));
}

$widgets=Database::fetchAll('SELECT * FROM manager_dashboard_widget_settings ORDER BY sort_order,id');
$ai=ManagerDashboard::aiSettings();
$skills=ManagerDashboard::enabledSkills(false);
$rules=ManagerDashboardCalculator::rules();
$ruleLabels=[
    'minimum_commission_achievement_percent'=>'حداقل درصد تحقق برای دریافت پورسانت','minimum_commission_sales_amount'=>'حداقل مبلغ فروش برای دریافت پورسانت',
    'max_achievement_factor_percent'=>'حداکثر درصد اثرگذاری تحقق','high_achievement_threshold_percent'=>'حد تحقق بالا','line_underperformance_threshold_percent'=>'حد افت عملکرد لاین',
    'brand_target_min_percent'=>'حداقل درصد تحقق برند','customer_coverage_floor'=>'کف پوشش مشتری','customer_target_floor'=>'کف هدف مشتری',
    'default_return_loss_rate'=>'نرخ پیش‌فرض خسارت مرجوعی','shared_visitor_deduction_percent_1'=>'درصد کسر ویزیتور مشترک نوع اول','shared_visitor_deduction_percent_2'=>'درصد کسر ویزیتور مشترک نوع دوم',
    'over_100_bonus_amount'=>'پاداش تحقق بالای ۱۰۰٪','over_110_total_bonus_amount'=>'پاداش تحقق توتال بالای ۱۱۰٪','brand_customer_bonus_amount'=>'پاداش برند / مشتری',
    'brand_target_bonus_amount'=>'پاداش برند تارگت‌دار','customer_coverage_penalty_amount'=>'جریمه پوشش مشتری',
];
$reports=Database::fetchAll('SELECT r.*,u.name imported_by_name FROM manager_dashboard_reports r LEFT JOIN users u ON u.id=r.imported_by ORDER BY r.report_date DESC,r.id DESC');
$users=Database::fetchAll('SELECT id,name,username,role FROM users WHERE status="active" ORDER BY name,username');
$permissionRows=Database::fetchAll('SELECT user_id,module_key,can_view FROM user_permissions WHERE module_key LIKE "manager_dashboard.%"');
$grants=[]; foreach($permissionRows as $row)$grants[$row['module_key']][(int)$row['user_id']]=(int)$row['can_view'];
require __DIR__.'/../views/partials/admin-header.php';
?>
<nav class="manager-tabs"><a href="#general">عمومی</a><a href="#widgets">ویجت‌ها</a><a href="#excel">اکسل</a><a href="#image">خروجی تصویری</a><a href="#access">کاربران و دسترسی‌ها</a><a href="#ai">هوش مصنوعی</a><a href="?tab=skills#skills">مهارت‌ها</a><a href="#rules">قوانین محاسبات</a></nav>
<?php if($notice):?><div class="alert <?=str_contains($notice,'موفقیت')?'alert-success':'alert-error'?>"><?=e($notice)?></div><?php endif?>

<?php if($canSettings):?>
<section class="card manager-settings" id="general"><header><div><h2>تنظیمات عمومی</h2><p>نمای پیش‌فرض و شیوه نمایش اعداد و تاریخ را تعیین کنید.</p></div></header><form method="post" class="manager-form-grid"><input type="hidden" name="csrf_token" value="<?=e(Auth::csrfToken())?>"><input type="hidden" name="action" value="general"><label><span>عنوان داشبورد</span><input name="dashboard_title" value="<?=e(ManagerDashboard::setting('dashboard_title'))?>"></label><label><span>گزارش پیش‌فرض</span><select name="default_report_id"><option value="0">خودکار</option><?php foreach($reports as $r):?><option value="<?=$r['id']?>" <?=ManagerDashboard::setting('default_report_id')==$r['id']?'selected':''?>><?=e($r['report_title'].' - '.format_jalali_date($r['report_date']))?></option><?php endforeach?></select></label><label><span>حالت نمایش پیش‌فرض</span><select name="default_report_mode"><option value="latest_report" <?=ManagerDashboard::setting('default_report_mode')==='latest_report'?'selected':''?>>آخرین گزارش</option><option value="default_report" <?=ManagerDashboard::setting('default_report_mode')==='default_report'?'selected':''?>>گزارش پیش‌فرض</option></select></label><label><span>فرمت اعداد</span><select name="number_format"><option value="thousands" <?=ManagerDashboard::setting('number_format')==='thousands'?'selected':''?>>جداکننده هزارگان</option><option value="plain" <?=ManagerDashboard::setting('number_format')==='plain'?'selected':''?>>بدون جداکننده</option></select></label><label><span>واحد پول</span><input name="currency_label" value="<?=e(ManagerDashboard::setting('currency_label'))?>"></label><label><span>فرمت تاریخ</span><select name="date_format"><option value="jalali" <?=ManagerDashboard::setting('date_format')==='jalali'?'selected':''?>>شمسی</option><option value="gregorian" <?=ManagerDashboard::setting('date_format')==='gregorian'?'selected':''?>>میلادی</option></select></label><label class="toggle-row"><input type="checkbox" name="show_latest_report_by_default" <?=ManagerDashboard::setting('show_latest_report_by_default')==='1'?'checked':''?>><span>نمایش آخرین گزارش به صورت پیش‌فرض</span></label><div class="full"><button class="btn">ذخیره تنظیمات عمومی</button></div></form>
<details class="manager-history"><summary>تاریخچه گزارش‌ها</summary><div class="table-wrap"><table><thead><tr><th>عنوان</th><th>تاریخ</th><th>فایل</th><th>واردکننده</th><th>وضعیت</th><th>عملیات</th></tr></thead><tbody><?php foreach($reports as $r):?><tr><td><?=e($r['report_title'])?> <?=$r['is_default']?'<span class="badge">پیش‌فرض</span>':''?></td><td><?=e(format_jalali_date($r['report_date']))?></td><td><?=e($r['source_file_name']??'-')?></td><td><?=e($r['imported_by_name']??'-')?></td><td><?=e($r['import_status'])?></td><td><div class="actions"><a class="link-btn" href="/admin/manager-dashboard.php?report_id=<?=$r['id']?>">مشاهده</a><a class="link-btn" href="/admin/manager-dashboard-export.php?report_id=<?=$r['id']?>">خروجی</a><form method="post"><input type="hidden" name="csrf_token" value="<?=e(Auth::csrfToken())?>"><input type="hidden" name="action" value="default"><input type="hidden" name="id" value="<?=$r['id']?>"><button class="link-btn">پیش‌فرض</button></form><?php if(Auth::can('manager_dashboard.edit')):?><form method="post" onsubmit="return confirm('گزارش حذف شود؟')"><input type="hidden" name="csrf_token" value="<?=e(Auth::csrfToken())?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=$r['id']?>"><button class="link-btn danger">حذف</button></form><?php endif?></div></td></tr><?php endforeach?></tbody></table></div></details></section>

<section class="card manager-settings" id="widgets"><header><div><h2>تنظیمات ویجت‌ها</h2><p>نمایش، ترتیب و خروجی هر بخش را مستقل کنترل کنید.</p></div></header><form method="post"><input type="hidden" name="csrf_token" value="<?=e(Auth::csrfToken())?>"><input type="hidden" name="action" value="widgets"><div class="table-wrap"><table><thead><tr><th>عنوان نمایشی</th><th>توضیحات</th><th>ترتیب</th><th>فعال</th><th>داشبورد</th><th>ایمپورت</th><th>اکسل</th><th>تصویر</th><th>ویرایش</th></tr></thead><tbody><?php foreach($widgets as $w):$k=$w['widget_key'];?><tr><td><input name="title[<?=e($k)?>]" value="<?=e($w['widget_title_fa'])?>"><small><?=e($k)?></small></td><td><input name="description[<?=e($k)?>]" value="<?=e($w['widget_description_fa']??'')?>"></td><td><input type="number" name="sort[<?=e($k)?>]" value="<?=$w['sort_order']?>"></td><?php foreach(['enabled'=>'is_enabled','show'=>'show_in_dashboard','import'=>'allow_import','export'=>'allow_export','image'=>'allow_image_export','edit'=>'allow_manual_edit'] as $name=>$col):?><td><input type="checkbox" name="<?=$name?>[<?=e($k)?>]" <?=(int)($w[$col]??0)?'checked':''?>></td><?php endforeach?></tr><?php endforeach?></tbody></table></div><button class="btn">ذخیره تنظیمات ویجت‌ها</button></form></section>

<section class="card manager-settings" id="excel"><header><div><h2>تنظیمات ورودی و خروجی اکسل</h2><p>فرمت مجاز فقط XLSX است و فایل‌ها پیش از ورود اعتبارسنجی می‌شوند.</p></div></header><form method="post" class="manager-form-grid"><input type="hidden" name="csrf_token" value="<?=e(Auth::csrfToken())?>"><input type="hidden" name="action" value="excel"><label><span>حداکثر حجم فایل (مگابایت)</span><input type="number" min="1" max="50" name="excel_max_size_mb" value="<?=e(ManagerDashboard::setting('excel_max_size_mb'))?>"></label><label class="toggle-row"><input type="checkbox" name="excel_strict_default" <?=ManagerDashboard::setting('excel_strict_default')==='1'?'checked':''?>><span>حالت سخت‌گیرانه به صورت پیش‌فرض</span></label><div class="full actions"><button class="btn">ذخیره تنظیمات اکسل</button><a class="btn btn-light" href="/admin/manager-dashboard-export.php?template=1">دانلود قالب</a></div></form></section>

<section class="card manager-settings" id="image"><header><div><h2>تنظیمات خروجی تصویری</h2><p>تصویر در مرورگر و با فایل محلی تولید می‌شود؛ هیچ داده‌ای به سرویس بیرونی ارسال نمی‌شود.</p></div></header><form method="post" class="manager-form-grid"><input type="hidden" name="csrf_token" value="<?=e(Auth::csrfToken())?>"><input type="hidden" name="action" value="image"><label class="toggle-row"><input type="checkbox" name="image_export_enabled" <?=ManagerDashboard::setting('image_export_enabled')==='1'?'checked':''?>><span>فعال‌سازی خروجی تصویری</span></label><label><span>فرمت خروجی تصویر</span><select name="image_export_format"><option value="png" <?=ManagerDashboard::setting('image_export_format')==='png'?'selected':''?>>PNG</option><option value="jpg" <?=ManagerDashboard::setting('image_export_format')==='jpg'?'selected':''?>>JPG</option></select></label><?php foreach(['image_export_include_title'=>'نمایش عنوان جدول در تصویر','image_export_include_report_date'=>'نمایش تاریخ گزارش در تصویر','image_export_include_company_name'=>'نمایش نام شرکت در تصویر','image_export_watermark_enabled'=>'فعال‌سازی واترمارک'] as $key=>$label):?><label class="toggle-row"><input type="checkbox" name="<?=e($key)?>" <?=ManagerDashboard::setting($key)==='1'?'checked':''?>><span><?=e($label)?></span></label><?php endforeach?><label class="full"><span>متن واترمارک</span><input name="image_export_watermark_text" value="<?=e(ManagerDashboard::setting('image_export_watermark_text'))?>"></label><div class="full"><button class="btn">ذخیره تنظیمات تصویر</button></div></form></section>

<section class="card manager-settings" id="access"><header><div><h2>کنترل دسترسی داشبورد مدیران</h2><p>مجوز هر کاربر مستقل است؛ دسترسی ایمپورت و اکسپورت به یکدیگر وابسته نیستند.</p></div></header><form method="post"><input type="hidden" name="csrf_token" value="<?=e(Auth::csrfToken())?>"><input type="hidden" name="action" value="access"><div class="table-wrap"><table class="permissions-table"><thead><tr><th>کاربر</th><?php foreach($permissionLabels as $label):?><th><?=e($label)?></th><?php endforeach?></tr></thead><tbody><?php foreach($users as $u):?><tr><td><strong><?=e($u['name']?:$u['username'])?></strong><small><?=e($u['role'])?></small></td><?php foreach($permissionLabels as $key=>$label):?><td><input type="checkbox" name="permissions[<?=e($key)?>][<?=$u['id']?>]" <?=($u['role']==='admin'||($grants[$key][$u['id']]??0))?'checked':''?> <?=$u['role']==='admin'?'disabled':''?>></td><?php endforeach?></tr><?php endforeach?></tbody></table></div><button class="btn">ذخیره دسترسی کاربران</button></form></section>
<?php endif?>

<?php if($canAiSettings):?>
<section class="card manager-settings" id="ai"><header><div><h2>هوش مصنوعی داشبورد مدیران</h2><p>اتصال فقط از <a href="/admin/sobhan-api-settings.php">تنظیمات Sobhan API</a> خوانده می‌شود.</p></div><span class="badge"><?=(int)($ai['sobhan_api_enabled']??0)?'سرویس فعال است':'سرویس غیرفعال است'?></span></header>
<form method="post" class="manager-form-grid"><input type="hidden" name="csrf_token" value="<?=e(Auth::csrfToken())?>"><input type="hidden" name="action" value="ai">
<?php foreach(['ai_enabled'=>'فعال‌سازی هوش مصنوعی در داشبورد مدیران','ai_skills_enabled'=>'فعال‌سازی مهارت‌های هوشمند','ai_show_buttons'=>'نمایش دکمه‌های تحلیل هوشمند','ai_read_latest_report'=>'اجازه تحلیل آخرین گزارش','ai_read_selected_report'=>'اجازه تحلیل گزارش انتخاب‌شده','ai_read_history'=>'اجازه استفاده از تاریخچه محدود'] as $key=>$label):?><label class="toggle-row"><input type="checkbox" name="<?=e($key)?>" <?=(int)($ai[$key]??0)?'checked':''?>><span><?=e($label)?></span></label><?php endforeach?>
<div class="full"><strong>مهارت‌های فعال داشبورد</strong><div class="actions"><?php $selectedSkills=array_filter(explode(',',ManagerDashboard::setting('ai_enabled_skills'))); foreach($skills as $skill):?><label class="toggle-row"><input type="checkbox" name="enabled_skills[]" value="<?=e($skill['skill_key'])?>" <?=!$selectedSkills||in_array($skill['skill_key'],$selectedSkills,true)?'checked':''?>><span><?=e($skill['skill_title_fa'])?></span></label><?php endforeach?></div></div>
<div class="full actions"><button class="btn">ذخیره تنظیمات داشبورد</button></div></form>
<form method="post" class="actions"><input type="hidden" name="csrf_token" value="<?=e(Auth::csrfToken())?>"><button class="btn btn-light" name="action" value="ai_refresh">بروزرسانی اتصال هوش مصنوعی</button><button class="btn btn-light" name="action" value="ai_test">تست اتصال هوش مصنوعی</button></form></section>

<section class="card manager-settings" id="skills"><header><div><h2>مهارت‌های داشبورد مدیران</h2><p>مهارت‌ها تحلیل AI را هدفمند می‌کنند؛ محاسبات قطعی در بک‌اند انجام می‌شود.</p></div></header><form method="post"><input type="hidden" name="csrf_token" value="<?=e(Auth::csrfToken())?>"><input type="hidden" name="action" value="skills"><div class="table-wrap"><table><thead><tr><th>کلید مهارت</th><th>عنوان مهارت</th><th>توضیح مهارت</th><th>نوع</th><th>فعال</th><th>ترتیب</th><th>پرامپت سیستمی مهارت</th><th>ساختار ورودی</th><th>ساختار خروجی</th></tr></thead><tbody><?php foreach($skills as $skill):$id=(int)$skill['id'];?><tr><td><code><?=e($skill['skill_key'])?></code></td><td><input name="skill_title_fa[<?=$id?>]" value="<?=e($skill['skill_title_fa'])?>"></td><td><textarea name="skill_description_fa[<?=$id?>]" rows="3"><?=e($skill['skill_description_fa'])?></textarea></td><td><input name="skill_type[<?=$id?>]" value="<?=e($skill['skill_type'])?>"></td><td><input type="checkbox" name="is_enabled[<?=$id?>]" <?=(int)$skill['is_enabled']?'checked':''?>></td><td><input type="number" name="sort_order[<?=$id?>]" value="<?=(int)$skill['sort_order']?>"></td><td><textarea name="system_prompt[<?=$id?>]" rows="4"><?=e($skill['system_prompt'])?></textarea></td><td><textarea dir="ltr" name="input_schema_json[<?=$id?>]" rows="4"><?=e($skill['input_schema_json'])?></textarea></td><td><textarea dir="ltr" name="output_schema_json[<?=$id?>]" rows="4"><?=e($skill['output_schema_json'])?></textarea></td></tr><?php endforeach?></tbody></table></div><button class="btn">ذخیره مهارت‌ها</button></form></section>

<section class="card manager-settings" id="rules"><header><div><h2>قوانین محاسبات داشبورد مدیران</h2><p>این مقادیر در محاسبات محلی و قابل تکرار استفاده می‌شوند.</p></div></header><form method="post" class="manager-form-grid"><input type="hidden" name="csrf_token" value="<?=e(Auth::csrfToken())?>"><input type="hidden" name="action" value="rules"><?php foreach($ruleLabels as $key=>$label):?><label><span><?=e($label)?></span><input type="number" step="0.01" name="<?=e($key)?>" value="<?=e((string)($rules[$key]??0))?>"></label><?php endforeach?><div class="full"><button class="btn">ذخیره قوانین محاسبات</button></div></form></section>
<?php endif?>
</section></main></div><script src="/assets/js/app.js"></script></body></html>
