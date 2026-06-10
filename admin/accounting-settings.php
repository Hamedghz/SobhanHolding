<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Response.php';

Auth::requirePermission('accounting', 'edit');
$pageTitle = 'تنظیمات حسابداری';
$tables = [
    'role' => ['table' => 'accounting_roles', 'title' => 'نقش‌ها', 'label' => 'نقش'],
    'city' => ['table' => 'accounting_cities', 'title' => 'شهرستان‌ها', 'label' => 'شهرستان'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('درخواست نامعتبر است.', 'danger');
        redirect('/admin/accounting-settings.php');
    }

    $type = $_POST['type'] ?? '';
    if (!isset($tables[$type])) {
        flash('نوع تنظیمات معتبر نیست.', 'danger');
        redirect('/admin/accounting-settings.php');
    }

    $table = $tables[$type]['table'];
    $id = (int)($_POST['id'] ?? 0);
    $title = trim((string)($_POST['title'] ?? ''));
    $sortOrder = (int)($_POST['sort_order'] ?? 0);
    $status = ($_POST['status'] ?? 'active') === 'disabled' ? 'disabled' : 'active';

    if ($title === '') {
        flash('عنوان را وارد کنید.', 'danger');
        redirect('/admin/accounting-settings.php');
    }

    try {
        if ($id > 0) {
            Database::execute("UPDATE {$table} SET title = ?, sort_order = ?, status = ?, updated_at = NOW() WHERE id = ?", [$title, $sortOrder, $status, $id]);
        } else {
            Database::execute("INSERT INTO {$table} (title,sort_order,status,created_at,updated_at) VALUES (?,?,?,NOW(),NOW())", [$title, $sortOrder, $status]);
        }
        flash('تنظیمات ذخیره شد.');
    } catch (Throwable $e) {
        flash('این عنوان قبلاً ثبت شده یا ذخیره‌سازی ناموفق بود.', 'danger');
    }
    redirect('/admin/accounting-settings.php');
}

$roles = Database::fetchAll('SELECT * FROM accounting_roles ORDER BY sort_order ASC, title ASC');
$cities = Database::fetchAll('SELECT * FROM accounting_cities ORDER BY sort_order ASC, title ASC');

require __DIR__ . '/../views/partials/admin-header.php';
?>
<div class="grid grid-2">
    <?php foreach (['role' => $roles, 'city' => $cities] as $type => $items): $meta = $tables[$type]; ?>
        <section class="card admin-form">
            <h2><?= e($meta['title']) ?></h2>
            <form method="post" class="accounting-option-form">
                <input type="hidden" name="csrf_token" value="<?= e(Auth::csrfToken()) ?>">
                <input type="hidden" name="type" value="<?= e($type) ?>">
                <input type="hidden" name="id" value="">
                <label class="form-field"><span><?= e($meta['label']) ?></span><input name="title" required></label>
                <div class="grid grid-2">
                    <label class="form-field"><span>ترتیب</span><input type="number" name="sort_order" value="0"></label>
                    <label class="form-field"><span>وضعیت</span><select name="status"><option value="active">فعال</option><option value="disabled">غیرفعال</option></select></label>
                </div>
                <div class="form-actions"><button class="btn btn-primary">ذخیره</button><button class="btn" type="reset">جدید</button></div>
            </form>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>عنوان</th><th>ترتیب</th><th>وضعیت</th><th>عملیات</th></tr></thead>
                    <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td><?= e($item['title']) ?></td>
                            <td><?= e($item['sort_order']) ?></td>
                            <td><?= $item['status'] === 'active' ? 'فعال' : 'غیرفعال' ?></td>
                            <td><button class="btn btn-small" type="button" data-option-edit data-type="<?= e($type) ?>" data-id="<?= e($item['id']) ?>" data-title="<?= e($item['title']) ?>" data-sort="<?= e($item['sort_order']) ?>" data-status="<?= e($item['status']) ?>">ویرایش</button></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endforeach; ?>
</div>
<script>
document.querySelectorAll('[data-option-edit]').forEach(button => {
    button.addEventListener('click', () => {
        const form = document.querySelector(`form input[name="type"][value="${button.dataset.type}"]`)?.closest('form');
        if (!form) return;
        form.querySelector('[name="id"]').value = button.dataset.id;
        form.querySelector('[name="title"]').value = button.dataset.title;
        form.querySelector('[name="sort_order"]').value = button.dataset.sort;
        form.querySelector('[name="status"]').value = button.dataset.status;
        form.scrollIntoView({behavior: 'smooth', block: 'center'});
    });
});
document.querySelectorAll('.accounting-option-form').forEach(form => {
    form.addEventListener('reset', () => setTimeout(() => {
        form.querySelector('[name="id"]').value = '';
        form.querySelector('[name="sort_order"]').value = 0;
        form.querySelector('[name="status"]').value = 'active';
    }, 0));
});
</script>
</section></main></div><script src="/assets/js/app.js"></script></body></html>
