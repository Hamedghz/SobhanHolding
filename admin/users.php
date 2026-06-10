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
                $id,
            ];
            $sql = 'UPDATE users SET name=?, email=?, username=?, role=?, status=?, description=?, upload_quota_mb=?, updated_at=NOW() WHERE id=?';
            if (trim((string)($_POST['password'] ?? '')) !== '') {
                $sql = 'UPDATE users SET name=?, email=?, username=?, role=?, status=?, description=?, upload_quota_mb=?, password_hash=?, updated_at=NOW() WHERE id=?';
                $params = [
                    trim($_POST['name']),
                    trim($_POST['email']),
                    trim($_POST['username']),
                    $role,
                    $status,
                    trim($_POST['description'] ?? ''),
                    $quota,
                    password_hash($_POST['password'], PASSWORD_DEFAULT),
                    $id,
                ];
            }
            Database::execute($sql, $params);
        } else {
            Database::execute(
                'INSERT INTO users (name,email,username,password_hash,role,status,description,upload_quota_mb,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,NOW(),NOW())',
                [trim($_POST['name']), trim($_POST['email']), trim($_POST['username']), password_hash($_POST['password'], PASSWORD_DEFAULT), $role, $status, trim($_POST['description'] ?? ''), $quota]
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
$selectedEmployees = $edit ? array_map('intval', array_column(Database::fetchAll('SELECT employee_id FROM manager_employees WHERE manager_id = ?', [$edit['id']]), 'employee_id')) : [];
$permissionRows = $edit ? Database::fetchAll('SELECT * FROM user_permissions WHERE user_id = ?', [$edit['id']]) : [];
$selectedPermissions = [];
foreach ($permissionRows as $row) {
    $selectedPermissions[$row['module_key']] = $row;
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

    <h3>دسترسی ماژول‌ها</h3>
    <div class="table-wrap permissions-table">
        <table>
            <thead><tr><th>ماژول</th><th>مشاهده</th><th>ایجاد</th><th>ویرایش</th><th>حذف</th></tr></thead>
            <tbody>
            <?php foreach ($modules as $module): $p = $selectedPermissions[$module['module_key']] ?? []; ?>
                <tr>
                    <td><?= e($module['module_title']) ?></td>
                    <?php foreach (['view' => 'can_view', 'create' => 'can_create', 'edit' => 'can_edit', 'delete' => 'can_delete'] as $short => $column): ?>
                        <td><input type="checkbox" name="permissions[<?= e($module['module_key']) ?>][<?= e($short) ?>]" value="1" <?= !empty($p[$column]) ? 'checked' : '' ?>></td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
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
</script>
</section></main></div><script src="/assets/js/app.js"></script></body></html>
