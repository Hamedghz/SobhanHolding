<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Validator.php';
require_once __DIR__ . '/../core/OrgModule.php';
require_once __DIR__ . '/../core/SyncQueueService.php';
require_once __DIR__ . '/../lib/OrgAccess.php';

Auth::requirePermission('users', 'view');
$pageTitle = 'مدیریت کاربران';
$currentUser = Auth::user();
$roleLabels = ['super_admin' => 'سوپرادمین', 'admin' => 'ادمین', 'manager' => 'مدیر', 'employee' => 'کارمند'];
$edit = null;

if (isset($_GET['delete']) && Auth::verifyCsrf($_GET['csrf_token'] ?? '') && Auth::can('users', 'delete')) {
    $deleteId = (int)$_GET['delete'];
    if ($deleteId !== (int)$currentUser['id'] && OrgAccess::canAccessUser($currentUser,$deleteId)) {
        Database::execute('UPDATE users SET status="disabled",updated_at=NOW() WHERE id = ?', [$deleteId]);
        flash('کاربر به‌صورت امن غیرفعال شد.');
    }
    redirect('/admin/users.php');
}

if (isset($_GET['edit'])) {
    $editId=(int)$_GET['edit'];
    if(!OrgAccess::canAccessUser($currentUser,$editId)){http_response_code(403);exit('دسترسی غیرمجاز است.');}
    $edit = Database::fetch('SELECT * FROM users WHERE id = ?', [$editId]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('درخواست نامعتبر است.', 'danger');
        redirect('/admin/users.php');
    }

    $id = (int)($_POST['id'] ?? 0);
    $originalUser = $id ? Database::fetch('SELECT id,role FROM users WHERE id=?', [$id]) : null;
    if ($id && !OrgAccess::canAccessUser($currentUser,$id)) {
        flash('برای ویرایش این کاربر دسترسی ندارید.', 'danger');
        redirect('/admin/users.php');
    }
    $action = $id ? 'edit' : 'create';
    if (!Auth::can('users', $action)) {
        flash('برای این عملیات دسترسی ندارید.', 'danger');
        redirect('/admin/users.php');
    }

    $errors = Validator::required($_POST, ['name' => 'نام', 'email' => 'ایمیل', 'username' => 'نام کاربری']);
    if (!Validator::email($_POST['email'] ?? '')) $errors['email'] = 'ایمیل معتبر نیست.';
    $role = in_array($_POST['role'] ?? '', ['super_admin', 'admin', 'manager', 'employee'], true) ? $_POST['role'] : 'employee';
    if ($role === 'super_admin' && !Auth::isSuperAdmin()) $role = $originalUser['role'] ?? 'employee';
    $status = in_array($_POST['status'] ?? '', ['active', 'disabled'], true) ? $_POST['status'] : 'active';
    if (($originalUser['role'] ?? '') === 'super_admin' && !Auth::isSuperAdmin()) {
        $role = 'super_admin';
        $status = 'active';
    }
    $quota = trim((string)($_POST['upload_quota_mb'] ?? '')) === '' ? null : max(0, (int)$_POST['upload_quota_mb']);
    $employeeNo = trim((string)($_POST['employee_no'] ?? '')) ?: null;
    $mobile = trim((string)($_POST['mobile'] ?? '')) ?: null;
    if ($employeeNo && Database::fetch('SELECT id FROM users WHERE employee_no=? AND id<>? LIMIT 1', [$employeeNo,$id])) $errors['employee_no'] = 'شماره پرسنلی قبلاً ثبت شده است.';
    $department = trim((string)($_POST['department'] ?? ''));
    $roleKey = trim((string)($_POST['role_key'] ?? ''));
    $orgUnitId = (int)($_POST['org_unit_id'] ?? 0) ?: null;
    $orgRoleId = (int)($_POST['org_role_id'] ?? 0) ?: null;
    $accessScope = in_array($_POST['access_scope'] ?? '', ['self','team','unit','all'], true) ? $_POST['access_scope'] : 'self';
    if ($accessScope === 'all' && !Auth::isAdmin()) $accessScope = 'self';
    $organization = OrgModule::normalizeUserOrganization($_POST, $id);
    $orgUnit = $organization['org_unit'];
    $orgRole = $organization['org_role'];
    $salesLine = $organization['sales_line'];
    $supervisorId = $organization['supervisor_id'];
    $organizationManagerId = $organization['organization_manager_id'];
    $parentUserId = $organization['parent_user_id'];
    $errors = array_merge($errors, $organization['errors']);
    $parentContext = $parentUserId ? OrgAccess::userContext($parentUserId) : null;
    if ($parentUserId && (!$parentContext || $parentUserId === $id || !OrgAccess::canAccessUser($currentUser, $parentUserId))) {
        $errors['parent_user_id'] = 'مدیر مستقیم معتبر نیست.';
    }
    if ($orgUnit) $department = $orgUnit['title'];
    if ($orgRole) $roleKey = $orgRole['code'];

    if (!$id && trim((string)($_POST['password'] ?? '')) === '') {
        $errors['password'] = 'رمز عبور برای کاربر جدید ضروری است.';
    }

    if ($errors) {
        flash(implode(' ', $errors), 'danger');
        redirect('/admin/users.php' . ($id ? '?edit=' . $id : ''));
    }

    $pdo = Database::connection();
    $pdo->beginTransaction();
    try {
        if ($id) {
            $params = [
                trim($_POST['name']),
                trim($_POST['email']),
                trim($_POST['username']),
                $employeeNo,$mobile,$role,
                $status,
                trim($_POST['description'] ?? ''),
                $quota,
                $department, $roleKey, $salesLine, $supervisorId, $organizationManagerId,$orgUnitId,$orgRoleId,$parentUserId,$accessScope,isset($_POST['employee_panel_enabled'])?1:0,isset($_POST['admin_panel_enabled'])?1:0,max(0,(int)($_POST['display_order']??0)),
                $id,
            ];
            $sql = 'UPDATE users SET name=?,email=?,username=?,employee_no=?,mobile=?,role=?,status=?,description=?,upload_quota_mb=?,department=?,role_key=?,sales_line=?,supervisor_id=?,organization_manager_id=?,org_unit_id=?,org_role_id=?,parent_user_id=?,access_scope=?,employee_panel_enabled=?,admin_panel_enabled=?,display_order=?,updated_at=NOW() WHERE id=?';
            if (trim((string)($_POST['password'] ?? '')) !== '') {
                $sql = 'UPDATE users SET name=?,email=?,username=?,employee_no=?,mobile=?,role=?,status=?,description=?,upload_quota_mb=?,department=?,role_key=?,sales_line=?,supervisor_id=?,organization_manager_id=?,org_unit_id=?,org_role_id=?,parent_user_id=?,access_scope=?,employee_panel_enabled=?,admin_panel_enabled=?,display_order=?,password_hash=?,updated_at=NOW() WHERE id=?';
                $params = [
                    trim($_POST['name']),
                    trim($_POST['email']),
                    trim($_POST['username']),
                    $employeeNo,$mobile,$role,
                    $status,
                    trim($_POST['description'] ?? ''),
                    $quota,
                    $department,$roleKey,$salesLine,$supervisorId,$organizationManagerId,$orgUnitId,$orgRoleId,$parentUserId,$accessScope,isset($_POST['employee_panel_enabled'])?1:0,isset($_POST['admin_panel_enabled'])?1:0,max(0,(int)($_POST['display_order']??0)),
                    password_hash($_POST['password'], PASSWORD_DEFAULT),
                    $id,
                ];
            }
            Database::execute($sql, $params);
        } else {
            Database::execute(
                'INSERT INTO users(name,email,username,password_hash,employee_no,mobile,role,status,description,upload_quota_mb,department,role_key,sales_line,supervisor_id,organization_manager_id,org_unit_id,org_role_id,parent_user_id,access_scope,employee_panel_enabled,admin_panel_enabled,display_order,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())',
                [trim($_POST['name']),trim($_POST['email']),trim($_POST['username']),password_hash($_POST['password'],PASSWORD_DEFAULT),$employeeNo,$mobile,$role,$status,trim($_POST['description']??''),$quota,$department,$roleKey,$salesLine,$supervisorId,$organizationManagerId,$orgUnitId,$orgRoleId,$parentUserId,$accessScope,isset($_POST['employee_panel_enabled'])?1:0,isset($_POST['admin_panel_enabled'])?1:0,max(0,(int)($_POST['display_order']??0))]
            );
            $id = (int)Database::lastInsertId();
        }

        Database::execute('DELETE FROM manager_employees WHERE manager_id = ? OR employee_id = ?', [$id, $id]);
        if ($parentUserId) Database::execute('INSERT IGNORE INTO manager_employees(manager_id,employee_id,assigned_by,created_at) VALUES (?,?,?,NOW())', [$parentUserId,$id,(int)$currentUser['id']]);
        if ($role === 'manager') {
            foreach ($_POST['employees'] ?? [] as $employeeId) {
                $employee = Database::fetch('SELECT id FROM users WHERE id = ? AND role = "employee"', [(int)$employeeId]);
                if ($employee) {
                    Database::execute('INSERT IGNORE INTO manager_employees (manager_id, employee_id, assigned_by, created_at) VALUES (?,?,?,NOW())', [$id, (int)$employeeId, $currentUser['id']]);
                }
            }
        }

        Database::execute('DELETE FROM user_permissions WHERE user_id = ?', [$id]);
        foreach ($_POST['permissions'] ?? [] as $moduleKey => $perms) {
            $module = Database::fetch('SELECT module_key FROM modules WHERE module_key = ? AND status = "active"', [$moduleKey]);
            if (!$module) continue;
            Database::execute(
                'INSERT INTO user_permissions (user_id,module_key,can_view,can_create,can_edit,can_delete,created_at) VALUES (?,?,?,?,?,?,NOW())',
                [$id, $moduleKey, !empty($perms['view']) ? 1 : 0, !empty($perms['create']) ? 1 : 0, !empty($perms['edit']) ? 1 : 0, !empty($perms['delete']) ? 1 : 0]
            );
        }

        $pdo->commit();
        try { SyncQueueService::enqueueOnce('users', $id ?: (int)Database::lastInsertId()); } catch (Throwable $syncError) { error_log('User sync queue: '.$syncError->getMessage()); }
        flash('کاربر ذخیره شد.');
    } catch (Throwable $e) {
        $pdo->rollBack();
        error_log('User save failed: ' . $e->getMessage());
        flash('خطا در ذخیره کاربر. ایمیل یا نام کاربری ممکن است تکراری باشد.', 'danger');
    }
    redirect('/admin/users.php');
}

$userWhere = ['1=1'];$userParams=[];
if(!Auth::isAdmin()){$allowedUserIds=OrgAccess::accessibleUserIds($currentUser);if(!$allowedUserIds)$allowedUserIds=[-1];$userWhere[]='u.id IN ('.implode(',',array_fill(0,count($allowedUserIds),'?')).')';$userParams=array_merge($userParams,$allowedUserIds);}
foreach(['org_unit_id','org_role_id','parent_user_id'] as $filter){$value=(int)($_GET[$filter]??0);if($value){$userWhere[]="u.{$filter}=?";$userParams[]=$value;}}
foreach(['role','sales_line','status'] as $filter){$value=trim((string)($_GET[$filter]??''));if($value!==''){$userWhere[]="u.{$filter}=?";$userParams[]=$value;}}
$users = Database::fetchAll('SELECT u.*,ou.title org_unit_title,orr.title org_role_title,p.name parent_name,(SELECT COUNT(*) FROM users c WHERE c.parent_user_id=u.id) child_count FROM users u LEFT JOIN org_units ou ON ou.id=u.org_unit_id LEFT JOIN org_roles orr ON orr.id=u.org_role_id LEFT JOIN users p ON p.id=u.parent_user_id WHERE '.implode(' AND ',$userWhere).' ORDER BY u.display_order,u.id DESC',$userParams);
$orgUnits = Database::fetchAll('SELECT id,title,unit_type FROM org_units WHERE active=1 ORDER BY sort_order,title');
foreach ($orgUnits as &$orgUnitOption) $orgUnitOption['is_sales_branch'] = OrgModule::salesBranch((int)$orgUnitOption['id']) ? 1 : 0;
unset($orgUnitOption);
$orgRoles = Database::fetchAll('SELECT id,title,code,is_sales_role FROM org_roles WHERE active=1 ORDER BY sort_order,title');
$selectableIds = Auth::isAdmin() ? [] : OrgAccess::accessibleUserIds($currentUser);
$selectableSql = Auth::isAdmin() ? '' : ' AND u.id IN ('.implode(',',array_fill(0,max(1,count($selectableIds)),'?')).')';
$selectableParams = Auth::isAdmin() ? [] : ($selectableIds ?: [-1]);
$parentUsers = Database::fetchAll('SELECT u.id,u.name,r.title org_role_title,r.code org_role_code FROM users u LEFT JOIN org_roles r ON r.id=u.org_role_id WHERE u.status="active"'.$selectableSql.' ORDER BY u.name',$selectableParams);
$salesSupervisors = array_values(array_filter($parentUsers, static fn($user) => ($user['org_role_code'] ?? '') === 'SALES_SUPERVISOR'));
$salesManagers = array_values(array_filter($parentUsers, static fn($user) => ($user['org_role_code'] ?? '') === 'SALES_MANAGER'));
$salesLines = array_column(Database::fetchAll('SELECT DISTINCT sales_line FROM users WHERE status="active" AND sales_line IS NOT NULL AND sales_line<>"" ORDER BY sales_line'), 'sales_line');
$employees = Database::fetchAll('SELECT u.id,u.name,u.email FROM users u WHERE u.role = "employee" AND u.status = "active"'.$selectableSql.' ORDER BY u.name',$selectableParams);
$modules = Database::fetchAll('SELECT * FROM modules WHERE status = "active" ORDER BY sort_order ASC, id ASC');
$moduleMeta = [
    'messenger.view' => ['group' => 'پیام‌رسان', 'route' => '/messenger/', 'description' => 'مشاهده پیام‌ها و Snapshotهای ارسال‌شده برای کاربر'],
    'notification_hub.devices' => ['group' => 'اعلان‌ها', 'route' => '/admin/notification-devices.php', 'description' => 'مدیریت اتصال برنامه اعلان ویندوز'],
    'manager_dashboard.forward' => ['group' => 'فروش و گزارش‌ها', 'route' => '/admin/manager-dashboard.php', 'description' => 'ارسال خروجی‌های مجاز پنل مدیران فروش به پیام‌رسان'],
    'dashboard' => ['group' => 'داشبوردها', 'route' => '/admin/index.php', 'description' => 'داشبورد عمومی پنل مدیریت'],
    'ceo_dashboard' => ['group' => 'داشبوردها', 'route' => '/admin/ceo-dashboard.php', 'description' => 'نسخه قدیمی دسترسی داشبورد مدیرعامل'],
    'view_ceo_dashboard' => ['group' => 'داشبوردها', 'route' => '/admin/ceo-dashboard.php', 'description' => 'مشاهده داشبورد مدیرعامل و گزارش‌های API سبحان'],
    'kpis' => ['group' => 'گزارش‌ها', 'route' => '/admin/hr-kpi.php', 'description' => 'مدیریت شاخص‌های ارزیابی'],
    'accounting' => ['group' => 'گزارش‌ها', 'route' => '/admin/accounting-collections.php', 'description' => 'دریافت‌ها و گزارش‌های حسابداری'],
    'use_ai_assistant' => ['group' => 'هوش مصنوعی', 'route' => '/admin/ceo-dashboard.php', 'description' => 'استفاده از کادر تحلیل هوش مصنوعی در داشبورد'],
    'view_ai_chat' => ['group' => 'هوش مصنوعی', 'route' => '/admin/ai-chat.php', 'description' => 'مشاهده صفحه گفتگوی هوش مصنوعی'],
    'manage_ai_chat_settings' => ['group' => 'هوش مصنوعی', 'route' => '/admin/ai-chat.php', 'description' => 'مدیریت تنظیمات مربوط به گفتگوی هوش مصنوعی'],
    'manage_knowledge' => ['group' => 'هوش مصنوعی', 'route' => '/admin/knowledge.php', 'description' => 'آپلود منابع دانش و بازسازی ایندکس جستجوی هوش مصنوعی'],
    'settings' => ['group' => 'تنظیمات', 'route' => '/admin/settings.php', 'description' => 'تنظیمات عمومی سایت و PWA'],
    'view_sobhan_api_settings' => ['group' => 'تنظیمات', 'route' => '/admin/sobhan-api-settings.php', 'description' => 'مشاهده تنظیمات اتصال API سبحان'],
    'manage_sobhan_api_settings' => ['group' => 'تنظیمات', 'route' => '/admin/sobhan-api-settings.php', 'description' => 'ذخیره و تست اتصال API سبحان'],
    'view_data_source_settings' => ['group' => 'تنظیمات', 'route' => '/admin/sobhan-api-settings.php#data-source-settings', 'description' => 'مشاهده وضعیت منبع داده داشبورد'],
    'manage_data_source_settings' => ['group' => 'تنظیمات', 'route' => '/admin/sobhan-api-settings.php#data-source-settings', 'description' => 'تغییر منبع داده شرکت پخش'],
    'toggle_ai_autofill' => ['group' => 'تنظیمات', 'route' => '/admin/sobhan-api-settings.php#data-source-settings', 'description' => 'فعال یا غیرفعال کردن تکمیل خودکار هوش مصنوعی'],
    'allow_ai_overwrite_manual_data' => ['group' => 'تنظیمات', 'route' => '/admin/sobhan-api-settings.php#data-source-settings', 'description' => 'اجازه جداگانه برای بازنویسی داده دستی یا ایمپورت‌شده'],
    'pharmacy_settings' => ['group' => 'تنظیمات', 'route' => '/admin/pharmacy-settings.php', 'description' => 'تنظیمات داشبورد داروخانه‌ها'],
    'users' => ['group' => 'کاربران', 'route' => '/admin/users.php', 'description' => 'مدیریت کاربران، نقش‌ها و دسترسی‌ها'],
    'users.import_export' => ['group' => 'کاربران', 'route' => '/admin/users-import-export.php', 'description' => 'ایمپورت و اکسپورت کاربران با Excel و CSV'],
    'docs.manage' => ['group' => 'مستندات', 'route' => '/admin/docs.php', 'description' => 'مدیریت مستندات، دسته‌بندی و گزارش مطالعه'],
    'docs.view' => ['group' => 'مستندات', 'route' => '/employee/docs.php', 'description' => 'مشاهده مستندات مجاز در پنل کاربر'],
    'payroll.manage' => ['group' => 'حقوق و دستمزد', 'route' => '/admin/payroll-periods.php', 'description' => 'مدیریت دوره‌ها و فیلدهای داینامیک'],
    'payroll.import' => ['group' => 'حقوق و دستمزد', 'route' => '/admin/payroll-import.php', 'description' => 'پیش‌نمایش و ثبت ایمپورت حقوق'],
    'payroll.publish' => ['group' => 'حقوق و دستمزد', 'route' => '/admin/payroll-periods.php', 'description' => 'انتشار، لغو و قفل دوره حقوقی'],
    'payroll.view_all' => ['group' => 'حقوق و دستمزد', 'route' => '/admin/payroll-slips.php', 'description' => 'مشاهده جزئیات همه فیش‌های حقوقی'],
    'payroll.own' => ['group' => 'حقوق و دستمزد', 'route' => '/employee/payroll.php', 'description' => 'مشاهده فیش‌های منتشرشده شخصی'],
    'management_reports.sales' => ['group' => 'گزارشات مدیران', 'route' => '/admin/management-report-prepare.php?type=sales', 'description' => 'ثبت و مشاهده گزارش فروش شخصی'],
    'management_reports.finance' => ['group' => 'گزارشات مدیران', 'route' => '/admin/management-report-prepare.php?type=finance', 'description' => 'ثبت و مشاهده گزارش مالی شخصی'],
    'management_reports.warehouse' => ['group' => 'گزارشات مدیران', 'route' => '/admin/management-report-prepare.php?type=warehouse', 'description' => 'ثبت و مشاهده گزارش انبار شخصی'],
    'management_reports.technology' => ['group' => 'گزارشات مدیران', 'route' => '/admin/management-report-prepare.php?type=technology', 'description' => 'ثبت و مشاهده گزارش فناوری شخصی'],
    'management_reports.review' => ['group' => 'گزارشات مدیران', 'route' => '/admin/management-reports.php', 'description' => 'بررسی، تأیید و برگشت گزارش‌ها'],
    'management_reports.aggregate' => ['group' => 'گزارشات مدیران', 'route' => '/admin/management-reports.php', 'description' => 'مشاهده گزارش‌های تأییدشده و تجمیعی'],
    'management_reports.templates' => ['group' => 'گزارشات مدیران', 'route' => '/admin/management-report-template-settings.php', 'description' => 'مدیریت قالب، بخش‌ها و فیلدهای گزارش'],
    'file_backup.manage' => ['group' => 'ابزارهای سیستم', 'route' => '/admin/uploaded-files-backup.php', 'description' => 'مشاهده وضعیت، اسکن و مدیریت بکاپ فایل‌های آپلودشده'],
    'files' => ['group' => 'فایل‌ها', 'route' => '/admin/files.php', 'description' => 'مدیریت فایل‌ها و اشتراک‌گذاری'],
    'surveys' => ['group' => 'ارزیابی‌ها', 'route' => '/admin/employee-assessments.php', 'description' => 'تعریف و تخصیص ارزیابی‌ها'],
    'survey_results' => ['group' => 'ارزیابی‌ها', 'route' => '/admin/hr-assessment-results.php', 'description' => 'مشاهده نتایج ارزیابی'],
    'hr_kpi.view' => ['group' => 'منابع انسانی', 'route' => '/admin/hr-kpi.php', 'description' => 'مشاهده داشبورد KPI در دامنه مجاز'],
    'hr_kpi.manage' => ['group' => 'منابع انسانی', 'route' => '/admin/hr-kpi-templates.php', 'description' => 'مدیریت قالب‌ها و دوره‌های KPI'],
    'hr_kpi.score' => ['group' => 'منابع انسانی', 'route' => '/admin/hr-kpi-scores.php', 'description' => 'ثبت و ویرایش امتیاز KPI'],
    'hr_kpi.results' => ['group' => 'منابع انسانی', 'route' => '/admin/hr-kpi-results.php', 'description' => 'مشاهده و خروجی نتایج KPI'],
    'hr_assessments.manage' => ['group' => 'منابع انسانی', 'route' => '/admin/employee-assessments.php', 'description' => 'مدیریت و تخصیص آزمون سازمانی'],
    'hr_assessments.results' => ['group' => 'منابع انسانی', 'route' => '/admin/hr-assessment-results.php', 'description' => 'مشاهده نتایج آزمون در دامنه مجاز'],
    'hr_assessments.recalculate' => ['group' => 'منابع انسانی', 'route' => '/admin/hr-assessment-results.php', 'description' => 'محاسبه مجدد و ثبت نسخه تاریخی نتیجه'],
    'hr_tests.own' => ['group' => 'منابع انسانی', 'route' => '/employee/tests.php', 'description' => 'مشاهده و انجام آزمون‌های تخصیص‌یافته خود'],
    'ai_insights' => ['group' => 'هوش مصنوعی', 'route' => '/admin/ai-insights.php', 'description' => 'مدیریت منابع گزارشی خواندنی AI'],
    'ai_updates' => ['group' => 'هوش مصنوعی', 'route' => '/admin/sobhan-api-settings.php#ai-update-runner', 'description' => 'اجرای jobهای کنترل‌شده بروزرسانی AI و داشبورد'],
    'system_maintenance' => ['group' => 'تنظیمات', 'route' => '/admin/system-maintenance.php', 'description' => 'اجرای امن migration و Seed بدون phpMyAdmin'],
    'carousel' => ['group' => 'محتوای سایت', 'route' => '/admin/carousel.php', 'description' => 'مدیریت اسلایدر صفحه اصلی'],
];
$groupOrder = ['داشبوردها', 'گزارش‌ها', 'منابع انسانی', 'هوش مصنوعی', 'تنظیمات', 'کاربران', 'فایل‌ها', 'CRM', 'نظرسنجی', 'محتوای سایت'];
foreach ($modules as &$module) {
    $meta = $moduleMeta[$module['module_key']] ?? ['group' => 'سایر', 'route' => '-', 'description' => 'دسترسی ماژول'];
    $module['group_title'] = $meta['group'];
    $module['route'] = $meta['route'];
    $module['description'] = $meta['description'];
}
unset($module);
$modulesByGroup = [];
foreach ($groupOrder as $groupTitle) $modulesByGroup[$groupTitle] = [];
foreach ($modules as $module) {
    $modulesByGroup[$module['group_title']][] = $module;
}
$modulesByGroup = array_filter($modulesByGroup);
$selectedEmployees = $edit ? array_map('intval', array_column(Database::fetchAll('SELECT employee_id FROM manager_employees WHERE manager_id = ?', [$edit['id']]), 'employee_id')) : [];
$permissionRows = $edit ? Database::fetchAll('SELECT * FROM user_permissions WHERE user_id = ?', [$edit['id']]) : [];
$selectedPermissions = [];
foreach ($permissionRows as $row) {
    $selectedPermissions[$row['module_key']] = $row;
}
$allPermissionRows = Database::fetchAll('SELECT user_id,module_key,can_view,can_create,can_edit,can_delete FROM user_permissions');
$permissionCopyMap = [];
foreach ($allPermissionRows as $row) {
    $permissionCopyMap[(int)$row['user_id']][$row['module_key']] = [
        'view' => (int)$row['can_view'],
        'create' => (int)$row['can_create'],
        'edit' => (int)$row['can_edit'],
        'delete' => (int)$row['can_delete'],
    ];
}

require __DIR__ . '/../views/partials/admin-header.php';
?>
<form class="card admin-form" method="post">
    <input type="hidden" name="csrf_token" value="<?= e(Auth::csrfToken()) ?>">
    <input type="hidden" name="id" value="<?= e($edit['id'] ?? '') ?>">
    <div class="grid grid-3">
        <label class="form-field"><span>نام</span><input name="name" value="<?= e($edit['name'] ?? '') ?>" required></label>
        <label class="form-field"><span>ایمیل</span><input type="email" name="email" value="<?= e($edit['email'] ?? '') ?>" required></label>
        <label class="form-field"><span>نام کاربری</span><input name="username" value="<?= e($edit['username'] ?? '') ?>" required></label>
        <label class="form-field"><span>شماره پرسنلی</span><input name="employee_no" value="<?= e($edit['employee_no'] ?? '') ?>" maxlength="50"></label>
        <label class="form-field"><span>شماره موبایل</span><input name="mobile" value="<?= e($edit['mobile'] ?? '') ?>" maxlength="30" dir="ltr"></label>
        <label class="form-field"><span>رمز عبور <?= $edit ? '(در صورت تغییر)' : '' ?></span><input type="password" name="password" <?= $edit ? '' : 'required' ?>></label>
        <label class="form-field"><span>نقش سیستمی</span><select name="role" id="roleSelect"><?php foreach ($roleLabels as $value => $label): if($value==='super_admin'&&!Auth::isSuperAdmin()&&($edit['role']??'')!=='super_admin')continue;?><option value="<?= e($value) ?>" <?= ($edit['role'] ?? 'employee') === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
        <label class="form-field"><span>وضعیت</span><select name="status"><option value="active" <?= ($edit['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>فعال</option><option value="disabled" <?= ($edit['status'] ?? '') === 'disabled' ? 'selected' : '' ?>>غیرفعال</option></select></label>
        <label class="form-field"><span>سهمیه آپلود (MB)</span><input type="number" min="0" name="upload_quota_mb" value="<?= e($edit['upload_quota_mb'] ?? '') ?>" placeholder="خالی یعنی بدون محدودیت نرم‌افزاری"></label>
        <label class="form-field"><span>واحد سازمانی</span><select name="org_unit_id" id="orgUnitSelect"><option value="">بدون واحد</option><?php foreach($orgUnits as $unit):?><option value="<?=$unit['id']?>" data-sales-branch="<?=$unit['is_sales_branch']?>" <?=(int)($edit['org_unit_id']??0)===(int)$unit['id']?'selected':''?>><?=e($unit['title'])?></option><?php endforeach?></select></label>
        <label class="form-field"><span>نقش سازمانی</span><select name="org_role_id" id="orgRoleSelect"><option value="">بدون نقش</option><?php foreach($orgRoles as $orgRoleOption):?><option value="<?=$orgRoleOption['id']?>" data-code="<?=e($orgRoleOption['code'])?>" data-sales="<?=$orgRoleOption['is_sales_role']?>" <?=(int)($edit['org_role_id']??0)===(int)$orgRoleOption['id']?'selected':''?>><?=e($orgRoleOption['title'])?></option><?php endforeach?></select></label>
        <label class="form-field sales-org-field" id="salesLineField"><span>لاین فروش</span><input name="sales_line" list="salesLines" value="<?= e($edit['sales_line'] ?? '') ?>"><datalist id="salesLines"><?php foreach($salesLines as $line):?><option value="<?=e($line)?>"><?php endforeach?></datalist></label>
        <label class="form-field sales-org-field" id="salesSupervisorField"><span>سرپرست فروش</span><select name="supervisor_id" id="salesSupervisorSelect"><option value="">انتخاب سرپرست</option><?php foreach($salesSupervisors as $parent):if((int)$parent['id']===(int)($edit['id']??0))continue?><option value="<?=$parent['id']?>" <?=(int)($edit['supervisor_id']??0)===(int)$parent['id']?'selected':''?>><?=e($parent['name'])?></option><?php endforeach?></select></label>
        <label class="form-field sales-org-field" id="salesManagerField"><span>مدیر فروش</span><select name="organization_manager_id" id="salesManagerSelect"><option value="">انتخاب مدیر فروش</option><?php foreach($salesManagers as $parent):if((int)$parent['id']===(int)($edit['id']??0))continue?><option value="<?=$parent['id']?>" <?=(int)($edit['organization_manager_id']??0)===(int)$parent['id']?'selected':''?>><?=e($parent['name'])?></option><?php endforeach?></select></label>
        <label class="form-field" id="parentUserField"><span>مدیر مستقیم عمومی</span><select name="parent_user_id" id="parentUserSelect"><option value="">بدون مدیر مستقیم</option><?php foreach($parentUsers as $parent):if((int)$parent['id']===(int)($edit['id']??0))continue?><option value="<?=$parent['id']?>" data-role="<?=e($parent['org_role_code']??'')?>" <?=(int)($edit['parent_user_id']??0)===(int)$parent['id']?'selected':''?>><?=e($parent['name'].' - '.($parent['org_role_title']?:'بدون نقش'))?></option><?php endforeach?></select></label>
        <label class="form-field"><span>سطح مشاهده اطلاعات</span><select name="access_scope"><option value="self">فقط خود</option><option value="team" <?=($edit['access_scope']??'')==='team'?'selected':''?>>تیم مستقیم</option><option value="unit" <?=($edit['access_scope']??'')==='unit'?'selected':''?>>واحد سازمانی</option><?php if(Auth::isAdmin()):?><option value="all" <?=($edit['access_scope']??'')==='all'?'selected':''?>>همه اطلاعات</option><?php endif?></select></label>
        <label class="form-field"><span>ترتیب نمایش</span><input type="number" min="0" name="display_order" value="<?=e($edit['display_order']??0)?>"></label>
        <label class="checkbox-item"><input type="checkbox" name="employee_panel_enabled" <?=!isset($edit['employee_panel_enabled'])||(int)$edit['employee_panel_enabled']?'checked':''?>> دسترسی پنل کارمند</label>
        <label class="checkbox-item"><input type="checkbox" name="admin_panel_enabled" <?=(int)($edit['admin_panel_enabled']??0)?'checked':''?>> دسترسی پنل ادمین</label>
        <label class="form-field grid-span-2"><span>توضیحات</span><textarea name="description"><?= e($edit['description'] ?? '') ?></textarea></label>
    </div>

    <div class="manager-employees-box" id="managerEmployeesBox">
        <h3>کارمندان زیرمجموعه مدیر</h3>
        <div class="checkbox-grid">
            <?php foreach ($employees as $employee): ?>
                <label class="checkbox-item"><input type="checkbox" name="employees[]" value="<?= e($employee['id']) ?>" <?= in_array((int)$employee['id'], $selectedEmployees, true) ? 'checked' : '' ?>> <?= e($employee['name']) ?> <small><?= e($employee['email']) ?></small></label>
            <?php endforeach; ?>
        </div>
    </div>

    <section class="permission-manager">
        <div class="section-heading-row">
            <div>
                <h3>مدیریت دسترسی‌ها</h3>
                <p class="muted">دسترسی‌ها بر اساس ماژول گروه‌بندی شده‌اند و کلیدهای فنی قبلی همچنان پشتیبانی می‌شوند.</p>
            </div>
            <div class="permission-tools">
                <input type="search" id="permissionSearch" placeholder="جستجو در عنوان، مسیر یا کلید">
                <button class="btn btn-small" type="button" data-permission-action="all">انتخاب همه</button>
                <button class="btn btn-small" type="button" data-permission-action="none">حذف همه</button>
                <button class="btn btn-small" type="button" data-permission-action="view">فقط مشاهده</button>
                <button class="btn btn-small" type="button" data-permission-action="admin">دسترسی کامل مدیر</button>
                <select id="copyPermissionUser">
                    <option value="">کپی دسترسی از نقش دیگر</option>
                    <?php foreach ($users as $copyUser): ?>
                        <?php if (!$edit || (int)$copyUser['id'] !== (int)$edit['id']): ?>
                            <option value="<?= e($copyUser['id']) ?>"><?= e(($roleLabels[$copyUser['role']] ?? $copyUser['role']) . ' - ' . $copyUser['name']) ?></option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <?php foreach ($modulesByGroup as $groupTitle => $groupModules): ?>
            <details class="permission-group" open>
                <summary><?= e($groupTitle) ?></summary>
                <div class="table-wrap permissions-table">
                    <table>
                        <thead><tr><th>مجوز</th><th>مشاهده</th><th>ایجاد</th><th>ویرایش</th><th>حذف</th></tr></thead>
                        <tbody>
                        <?php foreach ($groupModules as $module): $p = $selectedPermissions[$module['module_key']] ?? []; ?>
                            <tr data-permission-row data-search="<?= e($module['module_title'] . ' ' . $module['module_key'] . ' ' . $module['route'] . ' ' . $module['description']) ?>">
                                <td>
                                    <strong><?= e($module['module_title']) ?></strong>
                                    <small><code><?= e($module['module_key']) ?></code> | <?= e($module['route']) ?></small>
                                    <em><?= e($module['description']) ?></em>
                                </td>
                                <?php foreach (['view' => 'can_view', 'create' => 'can_create', 'edit' => 'can_edit', 'delete' => 'can_delete'] as $short => $column): ?>
                                    <td><input type="checkbox" data-module="<?= e($module['module_key']) ?>" data-action="<?= e($short) ?>" name="permissions[<?= e($module['module_key']) ?>][<?= e($short) ?>]" value="1" <?= !empty($p[$column]) ? 'checked' : '' ?>></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </details>
        <?php endforeach; ?>
    </section>

    <section class="card page-access-matrix">
        <h3>ماتریس دسترسی صفحات</h3>
        <div class="table-wrap">
            <table>
                <thead><tr><th>صفحه</th><th>مدیر</th><th>مدیر میانی</th><th>کارمند</th></tr></thead>
                <tbody>
                <?php foreach ($modules as $module): ?>
                    <tr>
                        <td><strong><?= e($module['route']) ?></strong><small><?= e($module['module_title']) ?></small></td>
                        <td><input type="checkbox" checked disabled></td>
                        <td><input type="checkbox" disabled <?= in_array($module['module_key'], ['dashboard', 'files', 'survey_results'], true) ? 'checked' : '' ?>></td>
                        <td><input type="checkbox" disabled <?= in_array($module['module_key'], ['dashboard', 'files'], true) ? 'checked' : '' ?>></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="muted">این ماتریس نمای سریع رفتار پیش‌فرض نقش‌هاست؛ دسترسی دقیق هر کاربر از چک‌باکس‌های بالا ذخیره می‌شود.</p>
    </section>
    <div class="form-actions"><button class="btn btn-primary">ذخیره</button><a class="btn" href="/admin/users.php">کاربر جدید</a><a class="btn" href="/admin/users-import-export.php">ایمپورت / اکسپورت Excel</a></div>
</form>

<form class="card admin-form" method="get"><h2>فیلتر کاربران</h2><div class="grid grid-3"><label class="form-field"><span>واحد</span><select name="org_unit_id"><option value="">همه</option><?php foreach($orgUnits as $unit):?><option value="<?=$unit['id']?>" <?=(int)($_GET['org_unit_id']??0)===(int)$unit['id']?'selected':''?>><?=e($unit['title'])?></option><?php endforeach?></select></label><label class="form-field"><span>نقش سازمانی</span><select name="org_role_id"><option value="">همه</option><?php foreach($orgRoles as $item):?><option value="<?=$item['id']?>" <?=(int)($_GET['org_role_id']??0)===(int)$item['id']?'selected':''?>><?=e($item['title'])?></option><?php endforeach?></select></label><label class="form-field"><span>نقش سیستمی</span><select name="role"><option value="">همه</option><?php foreach($roleLabels as $value=>$label):?><option value="<?=$value?>" <?=($_GET['role']??'')===$value?'selected':''?>><?=e($label)?></option><?php endforeach?></select></label><label class="form-field"><span>مدیر مستقیم</span><select name="parent_user_id"><option value="">همه</option><?php foreach($parentUsers as $parent):?><option value="<?=$parent['id']?>" <?=(int)($_GET['parent_user_id']??0)===(int)$parent['id']?'selected':''?>><?=e($parent['name'])?></option><?php endforeach?></select></label><label class="form-field"><span>لاین فروش</span><input name="sales_line" value="<?=e($_GET['sales_line']??'')?>"></label><label class="form-field"><span>وضعیت</span><select name="status"><option value="">همه</option><option value="active" <?=($_GET['status']??'')==='active'?'selected':''?>>فعال</option><option value="disabled" <?=($_GET['status']??'')==='disabled'?'selected':''?>>غیرفعال</option></select></label></div><div class="form-actions"><button class="btn btn-primary">اعمال فیلتر</button><a class="btn" href="/admin/users.php">پاکسازی</a><a class="btn" href="/admin/hr-settings.php">ساختار سازمانی</a></div></form>
<div class="table-wrap">
    <table data-progressive-table data-export-name="users">
        <thead><tr><th>نام / شماره پرسنلی</th><th>واحد</th><th>نقش سازمانی</th><th>نقش سیستمی</th><th>مدیر مستقیم</th><th>لاین</th><th>زیرمجموعه</th><th>آخرین ورود</th><th>وضعیت</th><th>عملیات</th></tr></thead>
        <tbody>
        <?php foreach ($users as $u): ?>
            <tr>
                <td><?= e($u['name']) ?><small><?=e($u['employee_no']?:'بدون شماره پرسنلی')?></small></td>
                <td><?=e($u['org_unit_title']?:($u['department']?:'-'))?></td>
                <td><?=e($u['org_role_title']?:($u['role_key']?:'-'))?></td>
                <td><?= e($roleLabels[$u['role']] ?? $u['role']) ?></td>
                <td><?=e($u['parent_name']?:'-')?></td><td><?=e($u['sales_line']?:'-')?></td><td><?=e((string)$u['child_count'])?></td><td><?=e($u['last_login_at']?format_jalali_datetime($u['last_login_at']):'-')?></td>
                <td><?= $u['status'] === 'active' ? 'فعال' : 'غیرفعال' ?></td>
                <td class="actions"><a class="btn btn-small" href="?edit=<?= e($u['id']) ?>">ویرایش</a><?php if ((int)$u['id'] !== (int)$currentUser['id']): ?><a class="btn btn-small btn-danger" onclick="return confirm('کاربر غیرفعال شود؟')" href="?delete=<?= e($u['id']) ?>&csrf_token=<?= e(Auth::csrfToken()) ?>">غیرفعال‌سازی</a><?php endif; ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<script>
const roleSelect = document.getElementById('roleSelect');
const managerBox = document.getElementById('managerEmployeesBox');
function syncManagerBox(){ managerBox.style.display = roleSelect.value === 'manager' ? 'block' : 'none'; }
roleSelect?.addEventListener('change', syncManagerBox);
syncManagerBox();
const orgRoleSelect=document.getElementById('orgRoleSelect'),orgUnitSelect=document.getElementById('orgUnitSelect'),salesLineField=document.getElementById('salesLineField'),salesSupervisorField=document.getElementById('salesSupervisorField'),salesManagerField=document.getElementById('salesManagerField'),parentUserField=document.getElementById('parentUserField'),salesSupervisorSelect=document.getElementById('salesSupervisorSelect'),salesManagerSelect=document.getElementById('salesManagerSelect');
function syncOrgFields(){const role=orgRoleSelect?.selectedOptions[0],unit=orgUnitSelect?.selectedOptions[0],code=role?.dataset.code||'',salesUnit=unit?.dataset.salesBranch==='1',visitor=salesUnit&&code==='VISITOR',supervisor=salesUnit&&code==='SALES_SUPERVISOR',manager=salesUnit&&code==='SALES_MANAGER';if(salesLineField)salesLineField.hidden=!(visitor||supervisor);if(salesSupervisorField)salesSupervisorField.hidden=!visitor;if(salesManagerField)salesManagerField.hidden=!supervisor;if(parentUserField)parentUserField.hidden=visitor||supervisor||manager;if(salesSupervisorSelect)salesSupervisorSelect.required=visitor;if(salesManagerSelect)salesManagerSelect.required=supervisor;const line=salesLineField?.querySelector('input');if(line)line.required=visitor||supervisor;}
orgRoleSelect?.addEventListener('change',syncOrgFields);orgUnitSelect?.addEventListener('change',syncOrgFields);syncOrgFields();

const permissionCopyMap = <?= json_encode($permissionCopyMap, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK) ?>;
const currentUserId = <?= (int)$currentUser['id'] ?>;
const editingUserId = <?= (int)($edit['id'] ?? 0) ?>;
const permissionRows = [...document.querySelectorAll('[data-permission-row]')];
const permissionInputs = [...document.querySelectorAll('.permission-manager input[type="checkbox"][data-module]')];
document.getElementById('permissionSearch')?.addEventListener('input', event => {
    const term = event.target.value.trim().toLowerCase();
    permissionRows.forEach(row => row.hidden = term !== '' && !row.dataset.search.toLowerCase().includes(term));
});
document.querySelectorAll('[data-permission-action]').forEach(button => {
    button.addEventListener('click', () => {
        const action = button.dataset.permissionAction;
        permissionInputs.forEach(input => {
            input.checked = action === 'all' || action === 'admin' || (action === 'view' && input.dataset.action === 'view');
            if (action === 'none') input.checked = false;
        });
    });
});
document.getElementById('copyPermissionUser')?.addEventListener('change', event => {
    const map = permissionCopyMap[event.target.value] || {};
    permissionInputs.forEach(input => {
        input.checked = !!(map[input.dataset.module] && map[input.dataset.module][input.dataset.action]);
    });
});
document.querySelector('form.admin-form')?.addEventListener('submit', event => {
    if (editingUserId && editingUserId === currentUserId) {
        const usersView = document.querySelector('input[data-module="users"][data-action="view"]');
        if (usersView && !usersView.checked && !confirm('با حذف دسترسی کاربران ممکن است دسترسی خودتان محدود شود. ادامه می‌دهید؟')) {
            event.preventDefault();
        }
    }
});
</script>
</section></main></div><script src="/assets/js/app.js"></script></body></html>
