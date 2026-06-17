<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Upload.php';
require_once __DIR__ . '/../core/JalaliDate.php';

Auth::requirePermission('files', 'view');
$pageTitle = 'فایل‌ها';
$user = Auth::user();

if (isset($_GET['delete']) && Auth::verifyCsrf($_GET['csrf_token'] ?? '') && Auth::can('files', 'delete')) {
    $file = Database::fetch('SELECT * FROM user_files WHERE id = ?', [(int)$_GET['delete']]);
    if ($file && (Auth::isAdmin() || (int)$file['user_id'] === (int)$user['id'])) {
        @unlink(__DIR__ . '/..' . $file['file_path']);
        Database::execute('DELETE FROM user_files WHERE id = ?', [$file['id']]);
        flash('فایل حذف شد.');
    }
    redirect('/admin/files.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('درخواست نامعتبر است.', 'danger');
        redirect('/admin/files.php');
    }
    if (!Auth::can('files', 'create')) {
        flash('برای آپلود فایل دسترسی ندارید.', 'danger');
        redirect('/admin/files.php');
    }

    $quotaMb = $user['upload_quota_mb'];
    $maxSize = $quotaMb === null ? null : (int)$quotaMb * 1024 * 1024;
    $upload = Upload::save($_FILES['file'] ?? [], 'uploads/files', null, $maxSize);
    if ($upload['ok']) {
        $visibility = ($_POST['visibility'] ?? 'private') === 'shared' ? 'shared' : 'private';
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            Database::execute(
                'INSERT INTO user_files (user_id,original_name,stored_name,file_path,mime_type,file_size,visibility,created_at) VALUES (?,?,?,?,?,?,?,NOW())',
                [$user['id'], $upload['original_name'], $upload['stored_name'], $upload['file_path'], $upload['mime_type'], $upload['file_size'], $visibility]
            );
            $fileId = (int)Database::lastInsertId();
            if ($visibility === 'shared') {
                foreach ($_POST['shared_users'] ?? [] as $sharedUserId) {
                    if ((int)$sharedUserId !== (int)$user['id'] && Database::fetch('SELECT id FROM users WHERE id = ? AND status = "active"', [(int)$sharedUserId])) {
                        Database::execute('INSERT IGNORE INTO file_shares (file_id,shared_with_user_id,shared_by,created_at) VALUES (?,?,?,NOW())', [$fileId, (int)$sharedUserId, $user['id']]);
                    }
                }
            }
            $pdo->commit();
            flash('فایل بارگذاری شد.');
        } catch (Throwable $e) {
            $pdo->rollBack();
            @unlink(__DIR__ . '/..' . $upload['file_path']);
            flash('خطا در ذخیره اطلاعات فایل.', 'danger');
        }
    } else {
        flash($upload['error'], 'danger');
    }
    redirect('/admin/files.php');
}

$shareUsers = Database::fetchAll('SELECT id,name,role FROM users WHERE status = "active" AND id <> ? ORDER BY name', [$user['id']]);

$params = [];
$where = [];
if (!Auth::isAdmin()) {
    $where[] = '(f.user_id = ? OR fs.shared_with_user_id = ?';
    $params[] = (int)$user['id'];
    $params[] = (int)$user['id'];
    if (Auth::isManager()) {
        $ids = Auth::assignedEmployeeIds((int)$user['id']);
        if ($ids) {
            $where[count($where) - 1] .= ' OR f.user_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
            $params = array_merge($params, $ids);
        }
    }
    $where[count($where) - 1] .= ')';
}

$sql = 'SELECT f.*,u.name user_name, GROUP_CONCAT(su.name SEPARATOR "، ") shared_names
        FROM user_files f
        JOIN users u ON u.id = f.user_id
        LEFT JOIN file_shares fs ON fs.file_id = f.id
        LEFT JOIN users su ON su.id = fs.shared_with_user_id';
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' GROUP BY f.id ORDER BY f.id DESC';
$files = Database::fetchAll($sql, $params);
$quotaLabel = $user['upload_quota_mb'] === null ? 'بدون محدودیت نرم‌افزاری' : e((string)$user['upload_quota_mb']) . ' MB';

require __DIR__ . '/../views/partials/admin-header.php';
?>
<form class="card admin-form" method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= e(Auth::csrfToken()) ?>">
    <div class="grid grid-2">
        <label class="form-field"><span>انتخاب فایل</span><input type="file" name="file" required><small>سهمیه شما: <?= $quotaLabel ?>. محدودیت واقعی سرور به تنظیمات PHP هم وابسته است.</small></label>
        <label class="form-field"><span>نمایش فایل</span><select name="visibility" id="visibilitySelect"><option value="private">خصوصی</option><option value="shared">اشتراکی</option></select></label>
    </div>
    <div id="shareUsersBox" style="display:none">
        <h3>اشتراک با کاربران</h3>
        <div class="checkbox-grid">
            <?php foreach ($shareUsers as $shareUser): ?>
                <label class="checkbox-item"><input type="checkbox" name="shared_users[]" value="<?= e($shareUser['id']) ?>"> <?= e($shareUser['name']) ?> <small><?= e($shareUser['role']) ?></small></label>
            <?php endforeach; ?>
        </div>
    </div>
    <button class="btn btn-primary">آپلود</button>
</form>

<div class="table-wrap">
    <table>
        <thead><tr><th>نام فایل</th><th>مالک</th><th>نوع</th><th>حجم</th><th>نمایش</th><th>اشتراک با</th><th>تاریخ</th><th>عملیات</th></tr></thead>
        <tbody>
        <?php foreach ($files as $f): ?>
            <tr>
                <td><a class="file-link" href="/admin/download-file.php?id=<?= e($f['id']) ?>"><?= e($f['original_name']) ?></a></td>
                <td><?= e($f['user_name']) ?></td>
                <td><?= e($f['mime_type']) ?></td>
                <td><?= e(number_format((int)$f['file_size'] / 1024, 1)) ?> KB</td>
                <td><?= $f['visibility'] === 'shared' ? 'اشتراکی' : 'خصوصی' ?></td>
                <td><?= e($f['shared_names'] ?: '-') ?></td>
                <td><?= e(format_jalali_datetime($f['created_at'])) ?></td>
                <td><?php if (Auth::isAdmin() || (int)$f['user_id'] === (int)$user['id']): ?><a class="btn btn-small btn-danger" onclick="return confirm('حذف شود؟')" href="?delete=<?= e($f['id']) ?>&csrf_token=<?= e(Auth::csrfToken()) ?>">حذف</a><?php endif; ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<script>
const visibilitySelect = document.getElementById('visibilitySelect');
const shareUsersBox = document.getElementById('shareUsersBox');
function syncShareBox(){ shareUsersBox.style.display = visibilitySelect.value === 'shared' ? 'block' : 'none'; }
visibilitySelect?.addEventListener('change', syncShareBox);
syncShareBox();
</script>
</section></main></div><script src="/assets/js/app.js"></script></body></html>
