<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Response.php';

Auth::requirePermission('accounting', 'view');
$pageTitle = 'ارسالی‌های حسابداری';
$statusLabels = [
    'sent' => 'ارسال شده',
    'registered' => 'ثبت شده',
    'needs_followup' => 'نیاز به پیگیری',
];
$roleOptions = array_column(Database::fetchAll('SELECT title FROM accounting_roles WHERE status = "active" ORDER BY sort_order ASC, title ASC'), 'title');
$cityOptions = array_column(Database::fetchAll('SELECT title FROM accounting_cities WHERE status = "active" ORDER BY sort_order ASC, title ASC'), 'title');
if (!$roleOptions) $roleOptions = ['موزع', 'تحصیلدار', 'ویزیتور'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('درخواست نامعتبر است.', 'danger');
        redirect('/admin/accounting-collections.php');
    }
    if (!Auth::can('accounting', 'edit')) {
        flash('برای تغییر وضعیت دسترسی ندارید.', 'danger');
        redirect('/admin/accounting-collections.php');
    }
    $id = (int)($_POST['id'] ?? 0);
    $newStatus = $_POST['status'] ?? 'sent';
    if (isset($statusLabels[$newStatus])) {
        Database::execute('UPDATE accounting_collections SET status = ?, updated_at = NOW() WHERE id = ?', [$newStatus, $id]);
        flash('وضعیت بروزرسانی شد.');
    }
    redirect('/admin/accounting-collections.php?' . http_build_query($_GET));
}

$filters = [
    'collector_role' => trim($_GET['collector_role'] ?? ''),
    'full_name' => trim($_GET['full_name'] ?? ''),
    'invoice_number' => trim($_GET['invoice_number'] ?? ''),
    'description' => trim($_GET['description'] ?? ''),
    'city' => trim($_GET['city'] ?? ''),
    'status' => trim($_GET['status'] ?? ''),
    'from_date' => trim($_GET['from_date'] ?? ''),
    'to_date' => trim($_GET['to_date'] ?? ''),
];

$where = [];
$params = [];
if ($filters['collector_role'] !== '') {
    $where[] = 'collector_role = ?';
    $params[] = $filters['collector_role'];
}
foreach (['full_name', 'invoice_number', 'description', 'city'] as $field) {
    if ($filters[$field] !== '') {
        $where[] = $field . ' LIKE ?';
        $params[] = '%' . $filters[$field] . '%';
    }
}
if ($filters['status'] !== '' && isset($statusLabels[$filters['status']])) {
    $where[] = 'status = ?';
    $params[] = $filters['status'];
}
if ($filters['from_date'] !== '') {
    $where[] = 'DATE(created_at) >= ?';
    $params[] = $filters['from_date'];
}
if ($filters['to_date'] !== '') {
    $where[] = 'DATE(created_at) <= ?';
    $params[] = $filters['to_date'];
}

$sql = 'SELECT * FROM accounting_collections';
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY id DESC';
$items = Database::fetchAll($sql, $params);

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="accounting-collections.csv"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, ['شناسه', 'نقش', 'نام', 'شماره فاکتور', 'شهرستان', 'توضیحات', 'وضعیت', 'تاریخ']);
    foreach ($items as $item) {
        fputcsv($out, [$item['id'], $item['collector_role'], $item['full_name'], $item['invoice_number'], $item['city'], $item['description'], $statusLabels[$item['status']] ?? $item['status'], $item['created_at']]);
    }
    fclose($out);
    exit;
}

require __DIR__ . '/../views/partials/admin-header.php';
?>
<form class="card admin-form" method="get">
    <h2>فیلتر ارسال‌ها</h2>
    <div class="grid grid-3">
        <label class="form-field"><span>نقش</span><select name="collector_role"><option value="">همه</option><?php foreach ($roleOptions as $role): ?><option value="<?= e($role) ?>" <?= $filters['collector_role'] === $role ? 'selected' : '' ?>><?= e($role) ?></option><?php endforeach; ?></select></label>
        <label class="form-field"><span>نام</span><input name="full_name" value="<?= e($filters['full_name']) ?>"></label>
        <label class="form-field"><span>شماره فاکتور</span><input name="invoice_number" value="<?= e($filters['invoice_number']) ?>"></label>
        <label class="form-field"><span>توضیحات</span><input name="description" value="<?= e($filters['description']) ?>"></label>
        <label class="form-field"><span>شهرستان</span><select name="city"><option value="">همه</option><?php foreach ($cityOptions as $city): ?><option value="<?= e($city) ?>" <?= $filters['city'] === $city ? 'selected' : '' ?>><?= e($city) ?></option><?php endforeach; ?></select></label>
        <label class="form-field"><span>وضعیت</span><select name="status"><option value="">همه</option><?php foreach ($statusLabels as $key => $label): ?><option value="<?= e($key) ?>" <?= $filters['status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
        <label class="form-field"><span>از تاریخ</span><input type="date" name="from_date" value="<?= e($filters['from_date']) ?>"></label>
        <label class="form-field"><span>تا تاریخ</span><input type="date" name="to_date" value="<?= e($filters['to_date']) ?>"></label>
    </div>
    <div class="form-actions"><button class="btn btn-primary">اعمال فیلتر</button><a class="btn" href="/admin/accounting-collections.php">پاکسازی</a><a class="btn" href="?<?= e(http_build_query(array_merge($_GET, ['export' => 'csv']))) ?>">خروجی CSV</a><a class="btn" href="/admin/accounting-settings.php">تنظیمات حسابداری</a></div>
</form>

<div class="table-wrap">
    <table>
        <thead>
        <tr>
            <th>تصویر</th>
            <th>نقش</th>
            <th>نام</th>
            <th>شماره فاکتور</th>
            <th>شهرستان</th>
            <th>توضیحات</th>
            <th>وضعیت</th>
            <th>تاریخ</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($items as $index => $item): ?>
            <tr>
                <td><button class="thumb-button" type="button" data-lightbox-index="<?= e((string)$index) ?>"><img src="/admin/accounting-image.php?id=<?= e($item['id']) ?>" alt="<?= e($item['invoice_number']) ?>"></button></td>
                <td><?= e($item['collector_role']) ?></td>
                <td><?= e($item['full_name']) ?></td>
                <td><?= e($item['invoice_number']) ?></td>
                <td><?= e($item['city']) ?></td>
                <td><?= e($item['description']) ?></td>
                <td>
                    <form class="inline-status-form quick-status" method="post" data-status-form>
                        <input type="hidden" name="csrf_token" value="<?= e(Auth::csrfToken()) ?>">
                        <input type="hidden" name="id" value="<?= e($item['id']) ?>">
                        <?php foreach ($statusLabels as $key => $label): ?>
                            <button class="status-chip <?= $item['status'] === $key ? 'active' : '' ?>" type="button" data-status="<?= e($key) ?>"><?= e($label) ?></button>
                        <?php endforeach; ?>
                    </form>
                </td>
                <td><?= e($item['created_at']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="image-lightbox" id="imageLightbox" aria-hidden="true">
    <button type="button" class="lightbox-close" data-lightbox-close>×</button>
    <button type="button" class="lightbox-nav lightbox-prev" data-lightbox-prev>‹</button>
    <figure>
        <img id="lightboxImage" src="" alt="">
        <figcaption id="lightboxCaption"></figcaption>
    </figure>
    <button type="button" class="lightbox-nav lightbox-next" data-lightbox-next>›</button>
</div>

<script>
const accountingImages = <?= json_encode(array_map(static fn($item) => [
    'src' => '/admin/accounting-image.php?id=' . (int)$item['id'],
    'caption' => $item['full_name'] . ' | فاکتور ' . $item['invoice_number'] . ' | ' . $item['city'],
], $items), JSON_UNESCAPED_UNICODE) ?>;
let currentImage = 0;
const lightbox = document.getElementById('imageLightbox');
const lightboxImage = document.getElementById('lightboxImage');
const lightboxCaption = document.getElementById('lightboxCaption');
function showAccountingImage(index) {
    if (!accountingImages.length) return;
    currentImage = ((index % accountingImages.length) + accountingImages.length) % accountingImages.length;
    lightboxImage.src = accountingImages[currentImage].src;
    lightboxCaption.textContent = accountingImages[currentImage].caption;
    lightbox.classList.add('open');
    lightbox.setAttribute('aria-hidden', 'false');
}
document.querySelectorAll('[data-lightbox-index]').forEach(button => button.addEventListener('click', () => showAccountingImage(Number(button.dataset.lightboxIndex))));
document.querySelector('[data-lightbox-close]')?.addEventListener('click', () => { lightbox.classList.remove('open'); lightbox.setAttribute('aria-hidden', 'true'); });
document.querySelector('[data-lightbox-prev]')?.addEventListener('click', () => showAccountingImage(currentImage - 1));
document.querySelector('[data-lightbox-next]')?.addEventListener('click', () => showAccountingImage(currentImage + 1));
document.addEventListener('keydown', event => {
    if (!lightbox.classList.contains('open')) return;
    if (event.key === 'Escape') lightbox.classList.remove('open');
    if (event.key === 'ArrowRight') showAccountingImage(currentImage - 1);
    if (event.key === 'ArrowLeft') showAccountingImage(currentImage + 1);
});
let touchStartX = null;
lightbox?.addEventListener('touchstart', event => { touchStartX = event.changedTouches[0]?.clientX ?? null; }, {passive:true});
lightbox?.addEventListener('touchend', event => {
    if (touchStartX === null) return;
    const diff = (event.changedTouches[0]?.clientX ?? touchStartX) - touchStartX;
    if (Math.abs(diff) > 45) showAccountingImage(currentImage + (diff > 0 ? -1 : 1));
    touchStartX = null;
}, {passive:true});
document.querySelectorAll('[data-status-form]').forEach(form => {
    form.querySelectorAll('[data-status]').forEach(button => {
        button.addEventListener('click', async () => {
            const body = new FormData(form);
            body.append('status', button.dataset.status);
            button.disabled = true;
            try {
                const response = await fetch('/admin/accounting-status.php', {method: 'POST', body});
                const data = await response.json();
                if (!data.ok) throw new Error(data.error || 'خطا');
                form.querySelectorAll('.status-chip').forEach(item => item.classList.remove('active'));
                button.classList.add('active');
            } catch (error) {
                alert(error.message || 'خطا در تغییر وضعیت');
            } finally {
                button.disabled = false;
            }
        });
    });
});
</script>
</section></main></div><script src="/assets/js/app.js"></script></body></html>
