<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Response.php';

Auth::requirePermission('ceo_dashboard', 'view');
$pageTitle = 'اطلاعات ویزیتورهای داشبورد مدیرعامل';
$edit = null;

if (isset($_GET['delete']) && Auth::verifyCsrf($_GET['csrf_token'] ?? '') && Auth::can('ceo_dashboard', 'delete')) {
    Database::execute('DELETE FROM ceo_dashboard_visitors WHERE id = ?', [(int)$_GET['delete']]);
    flash('اطلاعات ویزیتور حذف شد.');
    redirect('/admin/ceo-dashboard-visitors.php');
}

if (isset($_GET['edit'])) {
    $edit = Database::fetch('SELECT * FROM ceo_dashboard_visitors WHERE id = ?', [(int)$_GET['edit']]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('درخواست نامعتبر است.', 'danger');
        redirect('/admin/ceo-dashboard-visitors.php');
    }

    $id = (int)($_POST['id'] ?? 0);
    if (!Auth::can('ceo_dashboard', $id ? 'edit' : 'create')) {
        flash('برای این عملیات دسترسی ندارید.', 'danger');
        redirect('/admin/ceo-dashboard-visitors.php');
    }

    $lineCode = trim($_POST['line_code'] ?? '');
    $visitorName = trim($_POST['visitor_name'] ?? '');
    if ($lineCode === '' || $visitorName === '') {
        flash('کد لاین و نام ویزیتور الزامی است.', 'danger');
        redirect('/admin/ceo-dashboard-visitors.php' . ($id ? '?edit=' . $id : ''));
    }

    $data = [
        $_POST['report_date'] !== '' ? $_POST['report_date'] : null,
        $lineCode,
        $visitorName,
        max(0, (int)($_POST['target_qty'] ?? 0)),
        max(0, (int)($_POST['qty'] ?? 0)),
        max(0, (int)($_POST['sort_order'] ?? 0)),
        !empty($_POST['active']) ? 1 : 0,
    ];

    if ($id) {
        Database::execute(
            'UPDATE ceo_dashboard_visitors SET report_date=?, line_code=?, visitor_name=?, target_qty=?, qty=?, sort_order=?, active=?, updated_at=NOW() WHERE id=?',
            [...$data, $id]
        );
        flash('اطلاعات ویزیتور بروزرسانی شد.');
    } else {
        Database::execute(
            'INSERT INTO ceo_dashboard_visitors (report_date,line_code,visitor_name,target_qty,qty,sort_order,active,created_at,updated_at) VALUES (?,?,?,?,?,?,?,NOW(),NOW())',
            $data
        );
        flash('اطلاعات ویزیتور ثبت شد.');
    }
    redirect('/admin/ceo-dashboard-visitors.php');
}

$items = Database::fetchAll('SELECT * FROM ceo_dashboard_visitors ORDER BY COALESCE(report_date, "0000-00-00") DESC, sort_order ASC, id DESC');
$lineOptions = array_column(Database::fetchAll('SELECT DISTINCT line_code FROM ceo_dashboard_lines WHERE line_code <> "" ORDER BY line_code ASC'), 'line_code');

require __DIR__ . '/../views/partials/admin-header.php';
?>
<?php if (Auth::can('ceo_dashboard', $edit ? 'edit' : 'create')): ?>
<form class="card admin-form" method="post">
    <input type="hidden" name="csrf_token" value="<?= e(Auth::csrfToken()) ?>">
    <input type="hidden" name="id" value="<?= e($edit['id'] ?? '') ?>">
    <div class="grid grid-3">
        <label class="form-field"><span>تاریخ گزارش</span><input type="date" name="report_date" value="<?= e($edit['report_date'] ?? '') ?>"></label>
        <label class="form-field"><span>کد لاین</span><input name="line_code" list="ceoLineCodes" maxlength="10" value="<?= e($edit['line_code'] ?? '') ?>" required></label>
        <datalist id="ceoLineCodes"><?php foreach ($lineOptions as $lineCode): ?><option value="<?= e($lineCode) ?>"></option><?php endforeach; ?></datalist>
        <label class="form-field"><span>ویزیتور</span><input name="visitor_name" maxlength="150" value="<?= e($edit['visitor_name'] ?? '') ?>" required></label>
        <label class="form-field"><span>تارگت</span><input type="number" min="0" step="1" name="target_qty" value="<?= e($edit['target_qty'] ?? '0') ?>" required></label>
        <label class="form-field"><span>فروش / قطعه</span><input type="number" min="0" step="1" name="qty" value="<?= e($edit['qty'] ?? '0') ?>" required></label>
        <label class="form-field"><span>ترتیب</span><input type="number" min="0" step="1" name="sort_order" value="<?= e($edit['sort_order'] ?? '0') ?>"></label>
        <label class="checkbox-item"><input type="checkbox" name="active" value="1" <?= (int)($edit['active'] ?? 1) === 1 ? 'checked' : '' ?>> فعال</label>
    </div>
    <div class="form-actions"><button class="btn btn-primary">ذخیره</button><a class="btn" href="/admin/ceo-dashboard-visitors.php">جدید</a><a class="btn" href="/admin/ceo-dashboard.php">بازگشت به داشبورد</a></div>
</form>
<?php endif; ?>

<div class="table-wrap">
    <table>
        <thead><tr><th>تاریخ</th><th>لاین</th><th>ویزیتور</th><th>تارگت</th><th>فروش / قطعه</th><th>درصد تحقق</th><th>ترتیب</th><th>وضعیت</th><th>عملیات</th></tr></thead>
        <tbody>
        <?php foreach ($items as $item): $percent = (int)$item['target_qty'] > 0 ? ((int)$item['qty'] / (int)$item['target_qty']) * 100 : 0; ?>
            <tr>
                <td><?= e($item['report_date']) ?></td>
                <td><?= e($item['line_code']) ?></td>
                <td><?= e($item['visitor_name']) ?></td>
                <td><?= e(format_number($item['target_qty'])) ?></td>
                <td><?= e(format_number($item['qty'])) ?></td>
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
