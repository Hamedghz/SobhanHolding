<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Validator.php';

Auth::requirePermission('users', 'view');
$pageTitle = 'مدیریت کاربران';
$currentUser = Auth::user();
$roleLabels = ['admin' => 'ادمین', 'manager' => 'مدیر', 'employee' => 'کارمند'];
$edit = null;

if (isset($_GET['delete']) && Auth::verifyCsrf($_GET['csrf_token'] ?? '') && Auth::can('users', 'delete')) {
    $deleteId = (int)$_GET['delete'];
    if ($deleteId !== (int)$currentUser['id']) {
        Database::execute('DELETE FROM users WHERE id = ?', [$deleteId]);
        flash('کاربر حذف شد.');
    }
    redirect('/admin/users.php');
}

if (isset($_GET['edit'])) {
    $edit = Database::fetch('SELECT * FROM users WHERE id = ?', [(int)$_GET['edit']]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('درخواست نامعتبر است.', 'danger');
        redirect('/admin/users.php');
    }

    $id = (int)($_POST['id'] ?? 0);
    $action = $id ? 'edit' : 'create';
    if (!Auth::can('users', $action)) {
        flash('برای این عملیات دسترسی ندارید.', 'danger');
        redirect('/admin/users.php');
    }

    $errors = Validator::required($_POST, ['name' => 'نام', 'email' => 'ایمیل', 'username' => 'نام کاربری']);
    if (!Validator::email($_POST['email'] ?? '')) $errors['email'] = 'ایمیل معتبر نیست.';
    $role = in_array($_POST['role'] ?? '', ['admin', 'manager', 'employee'], true) ? $_POST['role'] : 'employee';
    $status = in_array($_POST['status'] ?? '', ['active', 'disabled'], true) ? $_POST['status'] : 'active';
    $quota = trim((string)($_POST['upload_quota_mb'] ?? '')) === '' ? null : max(0, (int)$_POST['upload_quota_mb']);
    $department = trim((string)($_POST['department'] ?? ''));
    $roleKey = trim((string)($_POST['role_key'] ?? ''));
    $salesLine = trim((string)($_POST['sales_line'] ?? ''));
    $supervisorId = (int)($_POST['supervisor_id'] ?? 0) ?: null;
    $organizationManagerId = (int)($_POST['organization_manager_id'] ?? 0) ?: null;

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
                $role,
                $status,
                trim($_POST['description'] ?? ''),
                $quota,
                $department, $roleKey, $salesLine, $supervisorId, $organizationManagerId,
                $id,
            ];
            $sql = 'UPDATE users SET name=?, email=?, username=?, role=?, status=?, description=?, upload_quota_mb=?, department=?, role_key=?, sales_line=?, supervisor_id=?, organization_manager_id=?, updated_at=NOW() WHERE id=?';
            if (trim((string)($_POST['password'] ?? '')) !== '') {
                $sql = 'UPDATE users SET name=?, email=?, username=?, role=?, status=?, description=?, upload_quota_mb=?, department=?, role_key=?, sales_line=?, supervisor_id=?, organization_manager_id=?, password_hash=?, updated_at=NOW() WHERE id=?';
                $params = [
                    trim($_POST['name']),
                    trim($_POST['email']),
                    trim($_POST['username']),
                    $role,
                    $status,
                    trim($_POST['description'] ?? ''),
                    $quota,
                    $department, $roleKey, $salesLine, $supervisorId, $organizationManagerId,
                    password_hash($_POST['password'], PASSWORD_DEFAULT),
                    $id,
                ];
            }
            Database::execute($sql, $params);
        } else {
            Database::execute(
                'INSERT INTO users (name,email,username,password_hash,role,status,description,upload_quota_mb,department,role_key,sales_line,supervisor_id,organization_manager_id,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())',
                [trim($_POST['name']), trim($_POST['email']), trim($_POST['username']), password_hash($_POST['password'], PASSWORD_DEFAULT), $role, $status, trim($_POST['description'] ?? ''), $quota, $department, $roleKey, $salesLine, $supervisorId, $organizationManagerId]
            );
            $id = (int)Database::lastInsertId();
        }

        Database::execute('DELETE FROM manager_employees WHERE manager_id = ? OR employee_id = ?', [$id, $id]);
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
        flash('کاربر ذخیره شد.');
    } catch (Throwable $e) {
        $pdo->rollBack();
        flash('خطا در ذخیره کاربر. ایمیل یا نام کاربری ممکن است تکراری باشد.', 'danger');
    }
    redirect('/admin/users.php');
}

$users = Database::fetchAll('SELECT * FROM users ORDER BY id DESC');
$employees = Database::fetchAll('SELECT id,name,email FROM users WHERE role = "employee" AND status = "active" ORDER BY name');
$modules = Database::fetchAll('SELECT * FROM modules WHERE status = "active" ORDER BY sort_order ASC, id ASC');
$moduleMeta = [
    'dashboard' => ['group' => 'داشبوردها', 'route' => '/admin/index.php', 'description' => 'داشبورد عمومی پنل مدیریت'],
    'ceo_dashboard' => ['group' => 'داشبوردها', 'route' => '/admin/ceo-dashboard.php', 'description' => 'نسخه قدیمی دسترسی داشبورد مدیرعامل'],
    'view_ceo_dashboard' => ['group' => 'داشبوردها', 'route' => '/admin/ceo-dashboard.php', 'description' => 'مشاهده داشبورد مدیرعامل و گزارش‌های API سبحان'],
    'kpis' => ['group' => 'گزارش‌ها', 'route' => '/admin/kpis.php', 'description' => 'مدیریت شاخص‌های ارزیابی'],
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
    'files' => ['group' => 'فایل‌ها', 'route' => '/admin/files.php', 'description' => 'مدیریت فایل‌ها و اشتراک‌گذاری'],
    'surveys' => ['group' => 'نظرسنجی', 'route' => '/admin/surveys.php', 'description' => 'تعریف و مدیریت نظرسنجی‌ها'],
    'survey_results' => ['group' => 'نظرسنجی', 'route' => '/admin/survey-results.php', 'description' => 'مشاهده نتایج ارزیابی'],
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
        <label class="form-field"><span>رمز عبور <?= $edit ? '(در صورت تغییر)' : '' ?></span><input type="password" name="password" <?= $edit ? '' : 'required' ?>></label>
        <label class="form-field"><span>نقش</span><select name="role" id="roleSelect"><?php foreach ($roleLabels as $value => $label): ?><option value="<?= e($value) ?>" <?= ($edit['role'] ?? 'employee') === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
        <label class="form-field"><span>وضعیت</span><select name="status"><option value="active" <?= ($edit['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>فعال</option><option value="disabled" <?= ($edit['status'] ?? '') === 'disabled' ? 'selected' : '' ?>>غیرفعال</option></select></label>
        <label class="form-field"><span>سهمیه آپلود (MB)</span><input type="number" min="0" name="upload_quota_mb" value="<?= e($edit['upload_quota_mb'] ?? '') ?>" placeholder="خالی یعنی بدون محدودیت نرم‌افزاری"></label>
        <label class="form-field"><span>واحد سازمانی</span><input name="department" value="<?= e($edit['department'] ?? '') ?>"></label>
        <label class="form-field"><span>کلید نقش سازمانی</span><input dir="ltr" name="role_key" value="<?= e($edit['role_key'] ?? '') ?>" placeholder="VISITOR"></label>
        <label class="form-field"><span>لاین فروش</span><input name="sales_line" value="<?= e($edit['sales_line'] ?? '') ?>"></label>
        <label class="form-field"><span>شناسه سرپرست</span><input type="number" min="1" name="supervisor_id" value="<?= e($edit['supervisor_id'] ?? '') ?>"></label>
        <label class="form-field"><span>شناسه مدیر سازمانی</span><input type="number" min="1" name="organization_manager_id" value="<?= e($edit['organization_manager_id'] ?? '') ?>"></label>
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
    <div class="form-actions"><button class="btn btn-primary">ذخیره</button><a class="btn" href="/admin/users.php">کاربر جدید</a></div>
</form>

<div class="table-wrap">
    <table>
        <thead><tr><th>نام</th><th>ایمیل</th><th>نام کاربری</th><th>نقش</th><th>سهمیه</th><th>وضعیت</th><th>عملیات</th></tr></thead>
        <tbody>
        <?php foreach ($users as $u): ?>
            <tr>
                <td><?= e($u['name']) ?></td>
                <td><?= e($u['email']) ?></td>
                <td><?= e($u['username']) ?></td>
                <td><?= e($roleLabels[$u['role']] ?? $u['role']) ?></td>
                <td><?= $u['upload_quota_mb'] === null ? 'نامحدود' : e($u['upload_quota_mb']) . ' MB' ?></td>
                <td><?= $u['status'] === 'active' ? 'فعال' : 'غیرفعال' ?></td>
                <td class="actions"><a class="btn btn-small" href="?edit=<?= e($u['id']) ?>">ویرایش</a><?php if ((int)$u['id'] !== (int)$currentUser['id']): ?><a class="btn btn-small btn-danger" onclick="return confirm('حذف شود؟')" href="?delete=<?= e($u['id']) ?>&csrf_token=<?= e(Auth::csrfToken()) ?>">حذف</a><?php endif; ?></td>
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
