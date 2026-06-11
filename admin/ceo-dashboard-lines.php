<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Response.php';

Auth::requirePermission('ceo_dashboard', 'view');
$pageTitle = 'اطلاعات لاین‌های داشبورد مدیرعامل';
$edit = null;

if (isset($_GET['delete']) && Auth::verifyCsrf($_GET['csrf_token'] ?? '') && Auth::can('ceo_dashboard', 'delete')) {
    Database::execute('DELETE FROM ceo_dashboard_lines WHERE id = ?', [(int)$_GET['delete']]);
    flash('اطلاعات لاین حذف شد.');
    redirect('/admin/ceo-dashboard-lines.php');
}

if (isset($_GET['edit'])) {
    $edit = Database::fetch('SELECT * FROM ceo_dashboard_lines WHERE id = ?', [(int)$_GET['edit']]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('درخواست نامعتبر است.', 'danger');
        redirect('/admin/ceo-dashboard-lines.php');
    }

    $id = (int)($_POST['id'] ?? 0);
    if (!Auth::can('ceo_dashboard', $id ? 'edit' : 'create')) {
        flash('برای این عملیات دسترسی ندارید.', 'danger');
        redirect('/admin/ceo-dashboard-lines.php');
    }

    $lineCode = trim($_POST['line_code'] ?? '');
    $salesAmount = max(0, (int)($_POST['sales_amount'] ?? 0));
    $qty = max(0, (int)($_POST['qty'] ?? 0));
    $targetQty = max(0, (int)($_POST['target_qty'] ?? 0));
    if ($lineCode === '') {
        flash('کد لاین الزامی است.', 'danger');
        redirect('/admin/ceo-dashboard-lines.php' . ($id ? '?edit=' . $id : ''));
    }

    $data = [
        $_POST['report_date'] !== '' ? $_POST['report_date'] : null,
        $lineCode,
        trim($_POST['line_title'] ?? ''),
        $salesAmount,
        $qty,
        $targetQty,
        max(0, (int)($_POST['sort_order'] ?? 0)),
        !empty($_POST['active']) ? 1 : 0,
    ];

    if ($id) {
        Database::execute(
            'UPDATE ceo_dashboard_lines SET report_date=?, line_code=?, line_title=?, sales_amount=?, qty=?, target_qty=?, sort_order=?, active=?, updated_at=NOW() WHERE id=?',
            [...$data, $id]
        );
        flash('اطلاعات لاین بروزرسانی شد.');
    } else {
        Database::execute(
            'INSERT INTO ceo_dashboard_lines (report_date,line_code,line_title,sales_amount,qty,target_qty,sort_order,active,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,NOW(),NOW())',
            $data
        );
        flash('اطلاعات لاین ثبت شد.');
    }
    redirect('/admin/ceo-dashboard-lines.php');
}

$items = Database::fetchAll('SELECT * FROM ceo_dashboard_lines ORDER BY COALESCE(report_date, "0000-00-00") DESC, sort_order ASC, id DESC');

require __DIR__ . '/../views/partials/admin-header.php';
?>
<?php if (Auth::can('ceo_dashboard', $edit ? 'edit' : 'create')): ?>
<form class="card admin-form" method="post">
    <input type="hidden" name="csrf_token" value="<?= e(Auth::csrfToken()) ?>">
    <input type="hidden" name="id" value="<?= e($edit['id'] ?? '') ?>">
    <div class="grid grid-3">
        <label class="form-field"><span>تاریخ گزارش</span><input type="date" name="report_date" value="<?= e($edit['report_date'] ?? '') ?>"></label>
        <label class="form-field"><span>کد لاین</span><input name="line_code" maxlength="10" value="<?= e($edit['line_code'] ?? '') ?>" required></label>
        <label class="form-field"><span>عنوان لاین</span><input name="line_title" maxlength="100" value="<?= e($edit['line_title'] ?? '') ?>"></label>
        <label class="form-field"><span>فروش لاین</span><input type="number" min="0" step="1" name="sales_amount" value="<?= e($edit['sales_amount'] ?? '0') ?>" required></label>
        <label class="form-field"><span>قطعه</span><input type="number" min="0" step="1" name="qty" value="<?= e($edit['qty'] ?? '0') ?>" required></label>
        <label class="form-field"><span>تارگت</span><input type="number" min="0" step="1" name="target_qty" value="<?= e($edit['target_qty'] ?? '0') ?>" required></label>
        <label class="form-field"><span>ترتیب</span><input type="number" min="0" step="1" name="sort_order" value="<?= e($edit['sort_order'] ?? '0') ?>"></label>
        <label class="checkbox-item"><input type="checkbox" name="active" value="1" <?= (int)($edit['active'] ?? 1) === 1 ? 'checked' : '' ?>> فعال</label>
    </div>
    <div class="form-actions"><button class="btn btn-primary">ذخیره</button><a class="btn" href="/admin/ceo-dashboard-lines.php">جدید</a><a class="btn" href="/admin/ceo-dashboard.php">بازگشت به داشبورد</a></div>
</form>
<?php endif; ?>

<div class="table-wrap">
    <table>
        <thead><tr><th>تاریخ</th><th>لاین</th><th>عنوان</th><th>فروش</th><th>قطعه</th><th>تارگت</th><th>درصد تحقق</th><th>ترتیب</th><th>وضعیت</th><th>عملیات</th></tr></thead>
        <tbody>
        <?php foreach ($items as $item): $percent = (int)$item['target_qty'] > 0 ? ((int)$item['qty'] / (int)$item['target_qty']) * 100 : 0; ?>
            <tr>
                <td><?= e($item['report_date']) ?></td>
                <td><?= e($item['line_code']) ?></td>
                <td><?= e($item['line_title']) ?></td>
                <td><?= e(format_money($item['sales_amount'])) ?></td>
                <td><?= e(format_number($item['qty'])) ?></td>
                <td><?= e(format_number($item['target_qty'])) ?></td>
                <td><?= e(format_percent(round($percent))) ?></td>
                <td><?= e($item['sort_order']) ?></td>
                <td><?= (int)$item['active'] === 1 ? 'فعال' : 'غیرفعال' ?></td>
                <td class="actions">
                    <?php if (Auth::can('ceo_dashboard', 'edit')): ?><a class="btn btn-small" href="?edit=<?= e($item['id']) ?>">ویرایش</a><?php endif; ?>
                    <?php if (Auth::can('ceo_dashboard', 'delete')): ?><a class="btn btn-small btn-danger" onclick="return confirm('حذف شود؟')" href="?delete=<?= e($item['id']) ?>&csrf_token=<?= e(Auth::csrfToken()) ?>">حذف</a><?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
</section></main></div><script src="/assets/js/app.js"></script></body></html>
