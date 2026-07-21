<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/JalaliDate.php';
require_once __DIR__ . '/../core/CeoDashboardExcel.php';

Auth::requirePermission('pharmacy_settings', 'view');
$pageTitle = 'تنظیمات داروخانه';
$canManage = Auth::can('pharmacy_settings', 'edit') || Auth::can('pharmacy_settings', 'create') || Auth::can('pharmacy_settings', 'delete');

function pharmacy_slug(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9\-_]+/', '-', $value);
    return trim($value ?: ('pharmacy-' . time()), '-');
}

function pharmacy_valid_date(?string $value): bool
{
    return JalaliDate::toGregorian($value) !== null || trim((string)$value) === '';
}

function pharmacy_money_value($value): int
{
    $value = strtr(trim((string)$value), ['،' => '', ',' => '', ' ' => '', '٬' => '']);
    $value = JalaliDate::normalize($value);
    return max(0, (int)$value);
}

function pharmacy_metrics_headers(): array
{
    return ['تاریخ گزارش', 'داروخانه', 'شناسه داروخانه', 'فروش روزانه', 'فروش ماهانه', 'مبلغ خرید', 'هزینه‌ها', 'چک‌های در جریان وصول', 'مبلغ فاکتور باز', 'ترتیب نمایش', 'فعال'];
}

function pharmacy_rows_for_export(): array
{
    $rows = [pharmacy_metrics_headers()];
    $items = Database::fetchAll('SELECT m.*, p.title pharmacy_title, p.slug pharmacy_slug FROM pharmacy_dashboard_metrics m JOIN pharmacies p ON p.id = m.pharmacy_id ORDER BY COALESCE(m.report_date, "0000-00-00") DESC, m.sort_order ASC, m.id ASC');
    $totalsByDate = [];
    foreach ($items as $item) {
        $dateKey = $item['report_date'] ?: 'بدون تاریخ';
        if (!isset($totalsByDate[$dateKey])) {
            $totalsByDate[$dateKey] = ['daily_sales' => 0, 'monthly_sales' => 0, 'supplier_purchase_amount' => 0, 'expenses_amount' => 0, 'pending_checks_amount' => 0, 'open_invoice_amount' => 0, 'sort_order' => 0];
        }
        foreach (['daily_sales', 'monthly_sales', 'supplier_purchase_amount', 'expenses_amount', 'pending_checks_amount', 'open_invoice_amount'] as $key) {
            $totalsByDate[$dateKey][$key] += (int)$item[$key];
        }
        $totalsByDate[$dateKey]['sort_order'] = max((int)$totalsByDate[$dateKey]['sort_order'], (int)$item['sort_order']);
        $rows[] = [
            format_jalali_date($item['report_date']),
            $item['pharmacy_title'],
            $item['pharmacy_slug'],
            $item['daily_sales'],
            $item['monthly_sales'],
            $item['supplier_purchase_amount'],
            $item['expenses_amount'],
            $item['pending_checks_amount'],
            $item['open_invoice_amount'],
            $item['sort_order'],
            (int)$item['active'] === 1 ? 'فعال' : 'غیرفعال',
        ];
    }
    foreach ($totalsByDate as $date => $total) {
        $rows[] = [
            $date === 'بدون تاریخ' ? '' : format_jalali_date($date),
            'Total',
            'total',
            $total['daily_sales'],
            $total['monthly_sales'],
            $total['supplier_purchase_amount'],
            $total['expenses_amount'],
            $total['pending_checks_amount'],
            $total['open_invoice_amount'],
            $total['sort_order'],
            'فعال',
        ];
    }
    return $rows;
}

function pharmacy_list_for_export(): array
{
    $rows = [['نام داروخانه', 'شناسه انگلیسی', 'ترتیب نمایش', 'وضعیت فعال']];
    foreach (Database::fetchAll('SELECT * FROM pharmacies ORDER BY sort_order ASC, id ASC') as $item) {
        $rows[] = [$item['title'], $item['slug'], $item['sort_order'], (int)$item['active'] === 1 ? 'فعال' : 'غیرفعال'];
    }
    return $rows;
}

function pharmacy_template_rows(): array
{
    $rows = [pharmacy_metrics_headers()];
    $pharmacies = Database::fetchAll('SELECT title,slug FROM pharmacies WHERE active=1 ORDER BY sort_order,id');
    foreach ($pharmacies as $pharmacy) {
        $rows[] = ['1405/04/25', $pharmacy['title'], $pharmacy['slug'], 0, 0, 0, 0, 0, 0, 0, 'فعال'];
    }
    if (!$pharmacies) $rows[] = ['1405/04/25', 'ابتدا داروخانه را در همین صفحه تعریف کنید', 'pharmacy-slug', 0, 0, 0, 0, 0, 0, 0, 'فعال'];
    return $rows;
}

function pharmacy_export_file(bool $template = false): void
{
    CeoDashboardExcel::output([
        'Pharmacies' => pharmacy_list_for_export(),
        'Metrics' => $template ? pharmacy_template_rows() : pharmacy_rows_for_export(),
    ], $template ? 'pharmacy-import-template.xlsx' : 'pharmacy-settings-export-' . date('Y-m-d-H-i') . '.xlsx');
}

function pharmacy_validate_import(array $workbook): array
{
    $errors = [];
    $success = [];
    $rows = $workbook['Metrics'] ?? [];
    $required = ['تاریخ گزارش', 'داروخانه', 'شناسه داروخانه', 'فروش روزانه', 'فروش ماهانه', 'مبلغ خرید', 'هزینه‌ها', 'چک‌های در جریان وصول', 'مبلغ فاکتور باز', 'ترتیب نمایش', 'فعال'];
    if (!$rows) {
        return ['rows' => [], 'errors' => [['row' => 1, 'error' => 'شیت Metrics پیدا نشد یا خالی است.']]];
    }
    $headers = array_map('trim', $rows[0]);
    foreach ($required as $index => $title) {
        if (($headers[$index] ?? '') !== $title) {
            $errors[] = ['row' => 1, 'error' => 'ستون «' . $title . '» در جایگاه درست وجود ندارد.'];
        }
    }
    $pharmacies = Database::fetchAll('SELECT id,title,slug FROM pharmacies');
    $pharmacyTitleMap = [];
    $pharmacySlugMap = [];
    foreach ($pharmacies as $pharmacy) {
        $pharmacyTitleMap[trim($pharmacy['title'])] = (int)$pharmacy['id'];
        $pharmacySlugMap[trim($pharmacy['slug'])] = (int)$pharmacy['id'];
    }
    foreach (array_slice($rows, 1) as $offset => $row) {
        $rowNumber = $offset + 2;
        if (!array_filter($row, static fn($value) => trim((string)$value) !== '')) continue;
        $pharmacyTitle = trim((string)($row[1] ?? ''));
        $pharmacySlug = trim((string)($row[2] ?? ''));
        if ($pharmacyTitle === 'Total' || $pharmacySlug === 'total') continue;
        $date = JalaliDate::toGregorian((string)($row[0] ?? ''));
        if ($date === null) $errors[] = ['row' => $rowNumber, 'error' => 'تاریخ گزارش باید شمسی مثل 1404/09/15 باشد.'];
        $pharmacyId = $pharmacySlugMap[$pharmacySlug] ?? $pharmacyTitleMap[$pharmacyTitle] ?? 0;
        if ($pharmacyId === 0) $errors[] = ['row' => $rowNumber, 'error' => 'داروخانه «' . ($pharmacyTitle ?: $pharmacySlug) . '» در تنظیمات وجود ندارد.'];
        $activeText = trim((string)($row[10] ?? 'فعال'));
        $success[] = [
            'row' => $rowNumber,
            'pharmacy_id' => $pharmacyId,
            'report_date' => $date,
            'daily_sales' => pharmacy_money_value($row[3] ?? 0),
            'monthly_sales' => pharmacy_money_value($row[4] ?? 0),
            'supplier_purchase_amount' => pharmacy_money_value($row[5] ?? 0),
            'expenses_amount' => pharmacy_money_value($row[6] ?? 0),
            'pending_checks_amount' => pharmacy_money_value($row[7] ?? 0),
            'open_invoice_amount' => pharmacy_money_value($row[8] ?? 0),
            'sort_order' => max(0, (int)JalaliDate::normalize((string)($row[9] ?? 0))),
            'active' => in_array($activeText, ['1', 'فعال', 'active'], true) ? 1 : 0,
        ];
    }
    return ['rows' => $success, 'errors' => $errors];
}

function pharmacy_apply_import(array $preview, string $mode): array
{
    $result = ['success' => 0, 'errors' => []];
    $pdo = Database::connection();
    $pdo->beginTransaction();
    try {
        if ($mode === 'replace_same_period') {
            $dates = [];
            foreach ($preview['rows'] as $row) $dates[$row['report_date'] ?? 'NULL'] = $row['report_date'] ?? null;
            foreach ($dates as $date) {
                Database::execute('DELETE FROM pharmacy_dashboard_metrics WHERE report_date <=> ?', [$date]);
            }
        }
        foreach ($preview['rows'] as $row) {
            $existing = Database::fetch('SELECT id FROM pharmacy_dashboard_metrics WHERE pharmacy_id = ? AND report_date <=> ? LIMIT 1', [$row['pharmacy_id'], $row['report_date']]);
            $data = [$row['pharmacy_id'], $row['report_date'], $row['daily_sales'], $row['monthly_sales'], $row['supplier_purchase_amount'], $row['open_invoice_amount'], $row['expenses_amount'], $row['pending_checks_amount'], $row['sort_order'], $row['active']];
            if ($mode === 'update_existing' && $existing) {
                Database::execute('UPDATE pharmacy_dashboard_metrics SET pharmacy_id=?, report_date=?, daily_sales=?, monthly_sales=?, supplier_purchase_amount=?, open_invoice_amount=?, expenses_amount=?, pending_checks_amount=?, sort_order=?, active=?, updated_at=NOW() WHERE id=?', [...$data, (int)$existing['id']]);
            } elseif ($mode === 'append' || $mode === 'replace_same_period' || !$existing) {
                Database::execute('INSERT INTO pharmacy_dashboard_metrics (pharmacy_id,report_date,daily_sales,monthly_sales,supplier_purchase_amount,open_invoice_amount,expenses_amount,pending_checks_amount,sort_order,active,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,NOW(),NOW())', $data);
            }
            $result['success']++;
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('Pharmacy import apply: ' . $e->getMessage());
        $result['errors'][] = 'ثبت اطلاعات داروخانه انجام نشد. جزئیات فنی در لاگ ثبت شد.';
    }
    return $result;
}

try {
    if (isset($_GET['export'])) {
        if (!$canManage) throw new RuntimeException('برای خروجی اکسل دسترسی ندارید.');
        pharmacy_export_file(($_GET['export'] ?? '') === 'template');
    }
} catch (Throwable $e) {
    error_log('Pharmacy export: ' . $e->getMessage());
    flash('ساخت خروجی اکسل انجام نشد. لطفاً تنظیمات فایل و دسترسی سرور را بررسی کنید.', 'danger');
    redirect('/admin/pharmacy-settings.php#import-export');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$canManage) {
        flash('برای این عملیات دسترسی ندارید.', 'danger');
        redirect('/admin/pharmacy-settings.php');
    }
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('درخواست نامعتبر است.', 'danger');
        redirect('/admin/pharmacy-settings.php');
    }

    $action = $_POST['action'] ?? '';
    if (str_starts_with((string)$action, 'ai_') || $action === 'autofill') {
        flash('اطلاعات داروخانه‌ها فقط از فایل استاتیک خوانده می‌شود و امکان تکمیل یا بازنویسی با هوش مصنوعی ندارد.', 'danger');
        redirect('/admin/pharmacy-settings.php');
    }
    if ($action === 'save_pharmacy') {
        $id = (int)($_POST['id'] ?? 0);
        $title = trim((string)($_POST['title'] ?? ''));
        $slug = pharmacy_slug((string)($_POST['slug'] ?? ''));
        if ($title === '') {
            flash('نام داروخانه الزامی است.', 'danger');
            redirect('/admin/pharmacy-settings.php#pharmacies');
        }
        $data = [$title, $slug, max(0, (int)($_POST['sort_order'] ?? 0)), !empty($_POST['active']) ? 1 : 0];
        try {
            if ($id) {
                Database::execute('UPDATE pharmacies SET title=?, slug=?, sort_order=?, active=?, updated_at=NOW() WHERE id=?', [...$data, $id]);
            } else {
                Database::execute('INSERT INTO pharmacies (title,slug,sort_order,active,created_at,updated_at) VALUES (?,?,?,?,NOW(),NOW())', $data);
            }
            flash('داروخانه ذخیره شد.');
        } catch (Throwable $e) {
            flash('شناسه داروخانه تکراری است یا ذخیره انجام نشد.', 'danger');
        }
        redirect('/admin/pharmacy-settings.php#pharmacies');
    }

    if ($action === 'delete_pharmacy') {
        if (!Auth::can('pharmacy_settings', 'delete')) {
            flash('برای حذف دسترسی ندارید.', 'danger');
            redirect('/admin/pharmacy-settings.php#pharmacies');
        }
        Database::execute('DELETE FROM pharmacies WHERE id = ?', [(int)($_POST['id'] ?? 0)]);
        flash('داروخانه حذف شد.');
        redirect('/admin/pharmacy-settings.php#pharmacies');
    }

    if ($action === 'save_metric') {
        $id = (int)($_POST['id'] ?? 0);
        $pharmacyId = (int)($_POST['pharmacy_id'] ?? 0);
        $pharmacy = Database::fetch('SELECT id FROM pharmacies WHERE id = ?', [$pharmacyId]);
        $reportDate = JalaliDate::toGregorian($_POST['report_date'] ?? '');
        if (!$pharmacy || $reportDate === null) {
            flash('داروخانه یا تاریخ گزارش معتبر نیست.', 'danger');
            redirect('/admin/pharmacy-settings.php#metrics');
        }
        $data = [
            $pharmacyId,
            $reportDate,
            pharmacy_money_value($_POST['daily_sales'] ?? 0),
            pharmacy_money_value($_POST['monthly_sales'] ?? 0),
            pharmacy_money_value($_POST['supplier_purchase_amount'] ?? 0),
            pharmacy_money_value($_POST['open_invoice_amount'] ?? 0),
            pharmacy_money_value($_POST['expenses_amount'] ?? 0),
            pharmacy_money_value($_POST['pending_checks_amount'] ?? 0),
            max(0, (int)($_POST['sort_order'] ?? 0)),
            !empty($_POST['active']) ? 1 : 0,
        ];
        if ($id) {
            Database::execute('UPDATE pharmacy_dashboard_metrics SET pharmacy_id=?, report_date=?, daily_sales=?, monthly_sales=?, supplier_purchase_amount=?, open_invoice_amount=?, expenses_amount=?, pending_checks_amount=?, sort_order=?, active=?, updated_at=NOW() WHERE id=?', [...$data, $id]);
        } else {
            Database::execute('INSERT INTO pharmacy_dashboard_metrics (pharmacy_id,report_date,daily_sales,monthly_sales,supplier_purchase_amount,open_invoice_amount,expenses_amount,pending_checks_amount,sort_order,active,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,NOW(),NOW())', $data);
        }
        flash('اطلاعات داشبورد داروخانه ذخیره شد.');
        redirect('/admin/pharmacy-settings.php#metrics');
    }

    if ($action === 'delete_metric') {
        Database::execute('DELETE FROM pharmacy_dashboard_metrics WHERE id = ?', [(int)($_POST['id'] ?? 0)]);
        flash('اطلاعات داشبورد حذف شد.');
        redirect('/admin/pharmacy-settings.php#metrics');
    }

    if ($action === 'preview_import') {
        if (empty($_FILES['excel_file']['tmp_name']) || (int)($_FILES['excel_file']['size'] ?? 0) > 5 * 1024 * 1024) {
            flash('فایل اکسل معتبر نیست یا حجم آن بیش از حد مجاز است.', 'danger');
            redirect('/admin/pharmacy-settings.php#import-export');
        }
        $ext = strtolower(pathinfo($_FILES['excel_file']['name'] ?? '', PATHINFO_EXTENSION));
        if (!in_array($ext, ['xlsx', 'xls'], true)) {
            flash('فقط فایل xlsx یا xls پذیرفته می‌شود.', 'danger');
            redirect('/admin/pharmacy-settings.php#import-export');
        }
        try {
            $preview = pharmacy_validate_import(CeoDashboardExcel::read($_FILES['excel_file']['tmp_name']));
            $preview['mode'] = in_array($_POST['import_mode'] ?? '', ['append', 'update_existing', 'replace_same_period'], true) ? $_POST['import_mode'] : 'append';
            Auth::start();
            $_SESSION['pharmacy_import_preview'] = $preview;
            flash('پیش‌نمایش ورود اکسل آماده شد.', $preview['errors'] ? 'danger' : 'success');
        } catch (Throwable $e) {
            error_log('Pharmacy import preview: ' . $e->getMessage());
            flash('خواندن فایل اکسل انجام نشد. قالب فایل و مقادیر ستون‌ها را بررسی کنید.', 'danger');
        }
        redirect('/admin/pharmacy-settings.php#import-export');
    }

    if ($action === 'confirm_import') {
        Auth::start();
        $preview = $_SESSION['pharmacy_import_preview'] ?? null;
        if (!$preview || !empty($preview['errors'])) {
            flash('پیش‌نمایش معتبر برای ثبت وجود ندارد.', 'danger');
            redirect('/admin/pharmacy-settings.php#import-export');
        }
        $result = pharmacy_apply_import($preview, $preview['mode'] ?? 'append');
        $_SESSION['pharmacy_import_result'] = $result;
        unset($_SESSION['pharmacy_import_preview']);
        flash('ورود اکسل انجام شد. رکورد موفق: ' . $result['success']);
        redirect('/admin/pharmacy-settings.php#import-export');
    }
}

$pharmacyEdit = isset($_GET['edit']) ? Database::fetch('SELECT * FROM pharmacies WHERE id = ?', [(int)$_GET['edit']]) : null;
$metricEdit = isset($_GET['metric_edit']) ? Database::fetch('SELECT * FROM pharmacy_dashboard_metrics WHERE id = ?', [(int)$_GET['metric_edit']]) : null;
$pharmacies = Database::fetchAll('SELECT * FROM pharmacies ORDER BY sort_order ASC, id ASC');
$metrics = Database::fetchAll('SELECT m.*, p.title pharmacy_title FROM pharmacy_dashboard_metrics m JOIN pharmacies p ON p.id = m.pharmacy_id ORDER BY COALESCE(m.report_date, "0000-00-00") DESC, m.sort_order ASC, m.id DESC');
Auth::start();
$importPreview = $_SESSION['pharmacy_import_preview'] ?? null;
$importResult = $_SESSION['pharmacy_import_result'] ?? null;
unset($_SESSION['pharmacy_import_result']);

require __DIR__ . '/../views/partials/admin-header.php';
?>
<section class="card">
    <div class="section-heading-row">
        <div>
            <h2>منبع داده داروخانه‌ها</h2>
            <p class="muted">اطلاعات داروخانه‌ها همیشه از فایل استاتیک/ایمپورت‌شده خوانده می‌شود و توسط هوش مصنوعی یا API بازنویسی نمی‌شود.</p>
        </div>
        <span class="badge">منبع داروخانه‌ها: فایل استاتیک</span>
    </div>
</section>

<div class="ceo-settings-tabs">
    <a href="#pharmacies">داروخانه‌ها</a>
    <a href="#metrics">داده‌های داشبورد</a>
    <a href="#import-export">ورودی / خروجی اکسل</a>
    <a href="/admin/ceo-dashboard.php">مشاهده داشبورد</a>
</div>

<section class="card ceo-settings-card" id="pharmacies">
    <h2>داروخانه‌ها <span class="badge">منبع داروخانه‌ها: فایل استاتیک</span></h2>
    <form method="post" class="admin-form">
        <input type="hidden" name="csrf_token" value="<?= e(Auth::csrfToken()) ?>">
        <input type="hidden" name="action" value="save_pharmacy">
        <input type="hidden" name="id" value="<?= e($pharmacyEdit['id'] ?? '') ?>">
        <div class="grid grid-3">
            <label class="form-field"><span>نام داروخانه</span><input name="title" maxlength="150" value="<?= e($pharmacyEdit['title'] ?? '') ?>" required></label>
            <label class="form-field"><span>شناسه انگلیسی</span><input name="slug" maxlength="100" dir="ltr" value="<?= e($pharmacyEdit['slug'] ?? '') ?>" placeholder="sobhan"></label>
            <label class="form-field"><span>ترتیب نمایش</span><input type="number" min="0" step="1" name="sort_order" value="<?= e($pharmacyEdit['sort_order'] ?? '0') ?>"></label>
            <label class="checkbox-item"><input type="checkbox" name="active" value="1" <?= (int)($pharmacyEdit['active'] ?? 1) === 1 ? 'checked' : '' ?>> فعال</label>
        </div>
        <div class="form-actions"><button class="btn btn-primary">ذخیره داروخانه</button><a class="btn" href="/admin/pharmacy-settings.php#pharmacies">جدید</a></div>
    </form>
    <div class="table-wrap">
        <table>
            <thead><tr><th>نام</th><th>شناسه</th><th>ترتیب</th><th>وضعیت</th><th>عملیات</th></tr></thead>
            <tbody>
            <?php foreach ($pharmacies as $item): ?>
                <tr>
                    <td><?= e($item['title']) ?></td>
                    <td dir="ltr"><?= e($item['slug']) ?></td>
                    <td><?= e($item['sort_order']) ?></td>
                    <td><?= (int)$item['active'] === 1 ? 'فعال' : 'غیرفعال' ?></td>
                    <td class="actions"><a class="btn btn-small" href="?edit=<?= e($item['id']) ?>#pharmacies">ویرایش</a><form class="inline-form" method="post" onsubmit="return confirm('حذف شود؟')"><input type="hidden" name="csrf_token" value="<?= e(Auth::csrfToken()) ?>"><input type="hidden" name="action" value="delete_pharmacy"><input type="hidden" name="id" value="<?= e($item['id']) ?>"><button class="btn btn-small btn-danger">حذف</button></form></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="card ceo-settings-card" id="metrics">
    <h2>داده‌های داشبورد داروخانه <span class="badge">منبع داروخانه‌ها: فایل استاتیک</span></h2>
    <form method="post" class="admin-form">
        <input type="hidden" name="csrf_token" value="<?= e(Auth::csrfToken()) ?>">
        <input type="hidden" name="action" value="save_metric">
        <input type="hidden" name="id" value="<?= e($metricEdit['id'] ?? '') ?>">
        <div class="grid grid-3">
            <label class="form-field"><span>داروخانه</span><select name="pharmacy_id" required><?php foreach ($pharmacies as $pharmacy): ?><option value="<?= e($pharmacy['id']) ?>" <?= (int)($metricEdit['pharmacy_id'] ?? 0) === (int)$pharmacy['id'] ? 'selected' : '' ?>><?= e($pharmacy['title']) ?></option><?php endforeach; ?></select></label>
            <label class="form-field"><span>تاریخ گزارش</span><input class="jalali-date-input" name="report_date" inputmode="numeric" placeholder="1404/09/15" value="<?= e(jalali_input_value($metricEdit['report_date'] ?? date('Y-m-d'))) ?>" required></label>
            <label class="form-field"><span>فروش روزانه</span><input type="number" min="0" step="1" name="daily_sales" value="<?= e($metricEdit['daily_sales'] ?? '0') ?>"></label>
            <label class="form-field"><span>فروش ماهانه</span><input type="number" min="0" step="1" name="monthly_sales" value="<?= e($metricEdit['monthly_sales'] ?? '0') ?>"></label>
            <label class="form-field"><span>مبلغ خرید</span><input type="number" min="0" step="1" name="supplier_purchase_amount" value="<?= e($metricEdit['supplier_purchase_amount'] ?? '0') ?>"></label>
            <label class="form-field"><span>هزینه‌ها</span><input type="number" min="0" step="1" name="expenses_amount" value="<?= e($metricEdit['expenses_amount'] ?? '0') ?>"></label>
            <label class="form-field"><span>چک‌های در جریان وصول</span><input type="number" min="0" step="1" name="pending_checks_amount" value="<?= e($metricEdit['pending_checks_amount'] ?? '0') ?>"></label>
            <label class="form-field"><span>مبلغ فاکتور باز</span><input type="number" min="0" step="1" name="open_invoice_amount" value="<?= e($metricEdit['open_invoice_amount'] ?? '0') ?>"></label>
            <label class="form-field"><span>ترتیب نمایش</span><input type="number" min="0" step="1" name="sort_order" value="<?= e($metricEdit['sort_order'] ?? '0') ?>"></label>
            <label class="checkbox-item"><input type="checkbox" name="active" value="1" <?= (int)($metricEdit['active'] ?? 1) === 1 ? 'checked' : '' ?>> فعال</label>
        </div>
        <div class="form-actions"><button class="btn btn-primary">ذخیره داده</button><a class="btn" href="/admin/pharmacy-settings.php#metrics">جدید</a></div>
    </form>
    <div class="table-wrap">
        <table>
            <thead><tr><th>تاریخ</th><th>داروخانه</th><th>روزانه</th><th>ماهانه</th><th>مبلغ خرید</th><th>هزینه</th><th>چک در وصول</th><th>مبلغ فاکتور باز</th><th>وضعیت</th><th>عملیات</th></tr></thead>
            <tbody>
            <?php foreach ($metrics as $item): ?>
                <tr>
                    <td><?= e(format_jalali_date($item['report_date'])) ?></td><td><?= e($item['pharmacy_title']) ?></td><td><?= e(format_money($item['daily_sales'])) ?></td><td><?= e(format_money($item['monthly_sales'])) ?></td><td><?= e(format_money($item['supplier_purchase_amount'])) ?></td><td><?= e(format_money($item['expenses_amount'])) ?></td><td><?= e(format_money($item['pending_checks_amount'])) ?></td><td><?= e(format_money($item['open_invoice_amount'])) ?></td><td><?= (int)$item['active'] === 1 ? 'فعال' : 'غیرفعال' ?></td>
                    <td class="actions"><a class="btn btn-small" href="?metric_edit=<?= e($item['id']) ?>#metrics">ویرایش</a><form class="inline-form" method="post" onsubmit="return confirm('حذف شود؟')"><input type="hidden" name="csrf_token" value="<?= e(Auth::csrfToken()) ?>"><input type="hidden" name="action" value="delete_metric"><input type="hidden" name="id" value="<?= e($item['id']) ?>"><button class="btn btn-small btn-danger">حذف</button></form></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="card ceo-settings-card" id="import-export">
    <h2>ورودی / خروجی اکسل <span class="badge">منبع داروخانه‌ها: فایل استاتیک</span></h2>
    <div class="form-actions">
        <a class="btn btn-primary" href="?export=1">خروجی اکسل تنظیمات داروخانه</a>
        <a class="btn" href="?export=template">دانلود قالب فایل ورودی</a>
    </div>
    <form method="post" enctype="multipart/form-data" class="admin-form">
        <input type="hidden" name="csrf_token" value="<?= e(Auth::csrfToken()) ?>">
        <input type="hidden" name="action" value="preview_import">
        <div class="grid grid-2">
            <label class="form-field"><span>حالت ورود اطلاعات</span><select name="import_mode"><option value="append">افزودن رکوردهای جدید</option><option value="update_existing">بروزرسانی رکوردهای موجود</option><option value="replace_same_period">جایگزینی اطلاعات همان بازه/تاریخ</option></select></label>
            <label class="form-field"><span>فایل اکسل</span><input type="file" name="excel_file" accept=".xlsx,.xls" required></label>
        </div>
        <div class="form-actions actions"><a class="btn" href="?export=template">دانلود قالب فایل ورودی</a><button class="btn btn-primary">بررسی و پیش‌نمایش</button></div>
    </form>
    <?php if ($importPreview): ?>
        <div class="stats">
            <div class="stat-card"><span>رکورد قابل ثبت</span><strong><?= e((string)count($importPreview['rows'])) ?></strong></div>
            <div class="stat-card"><span>خطا</span><strong><?= e((string)count($importPreview['errors'])) ?></strong></div>
        </div>
        <?php if (!empty($importPreview['errors'])): ?>
            <div class="table-wrap"><table><thead><tr><th>ردیف</th><th>خطا</th></tr></thead><tbody><?php foreach ($importPreview['errors'] as $error): ?><tr><td><?= e((string)$error['row']) ?></td><td><?= e($error['error']) ?></td></tr><?php endforeach; ?></tbody></table></div>
        <?php else: ?>
            <form method="post"><input type="hidden" name="csrf_token" value="<?= e(Auth::csrfToken()) ?>"><input type="hidden" name="action" value="confirm_import"><button class="btn btn-primary">تایید و ثبت اطلاعات</button></form>
        <?php endif; ?>
    <?php endif; ?>
    <?php if ($importResult): ?>
        <div class="alert <?= empty($importResult['errors']) ? 'alert-success' : 'alert-danger' ?>">رکورد موفق: <?= e((string)$importResult['success']) ?><?= $importResult['errors'] ? ' | خطا: ' . e(implode('، ', $importResult['errors'])) : '' ?></div>
    <?php endif; ?>
</section>
</section></main></div><script src="/assets/js/app.js"></script></body></html>
