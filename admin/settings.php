<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Upload.php';

Auth::requirePermission('settings', 'view');
$pageTitle = 'تنظیمات سایت';

$siteKeys = [
    'company_name' => ['label' => 'نام شرکت', 'type' => 'text', 'default' => 'شرکت پخش سبحان'],
    'site_title' => ['label' => 'عنوان سایت', 'type' => 'text', 'default' => 'شرکت پخش سبحان'],
    'hero_subtitle' => ['label' => 'زیرعنوان هیرو', 'type' => 'textarea', 'default' => 'سامانه هلدینگ سبحان و بخش های وابسته.'],
    'meta_description' => ['label' => 'توضیحات متا', 'type' => 'textarea', 'default' => ''],
    'footer_text' => ['label' => 'متن فوتر', 'type' => 'text', 'default' => '© شرکت پخش سبحان'],
    'primary_color' => ['label' => 'رنگ اصلی', 'type' => 'color', 'default' => '#2563eb'],
    'logo_path' => ['label' => 'مسیر لوگو', 'type' => 'image', 'default' => ''],
];

$ceoKeys = [
    'page_title' => ['label' => 'عنوان اصلی صفحه', 'type' => 'text', 'default' => 'داشبورد مدیرعامل'],
    'gross_sales_title' => ['label' => 'عنوان فروش ناخالص', 'type' => 'text', 'default' => 'فروش ناخالص'],
    'discounts_title' => ['label' => 'عنوان تخفیفات', 'type' => 'text', 'default' => 'تخفیفات'],
    'discount_percent_title' => ['label' => 'عنوان درصد تخفیف', 'type' => 'text', 'default' => 'درصد'],
    'net_sales_title' => ['label' => 'عنوان فروش خالص', 'type' => 'text', 'default' => 'فروش خالص'],
    'line_sales_chart_title' => ['label' => 'عنوان نمودار فروش لاین', 'type' => 'text', 'default' => 'ریال فروش لاین'],
    'line_table_title' => ['label' => 'عنوان جدول اطلاعات لاین', 'type' => 'text', 'default' => 'اطلاعات لاین'],
    'visitor_table_title' => ['label' => 'عنوان جدول ویزیتورها', 'type' => 'text', 'default' => 'اطلاعات ویزیتورها'],
    'line_share_chart_title' => ['label' => 'عنوان نمودار سهم هر لاین', 'type' => 'text', 'default' => 'سهم فروش هر لاین'],
    'line_achievement_chart_title' => ['label' => 'عنوان نمودار درصد تحقق لاین', 'type' => 'text', 'default' => 'درصد تحقق لاین'],
    'visitor_achievement_chart_title' => ['label' => 'عنوان نمودار درصد تحقق ویزیتور', 'type' => 'text', 'default' => 'درصد تحقق ویزیتور'],
    'ceo_dashboard_discounts_amount' => ['label' => 'مبلغ تخفیفات داشبورد مدیرعامل', 'type' => 'number', 'default' => '0'],
];

$allKeys = $siteKeys + $ceoKeys;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::can('settings', 'edit')) {
        flash('برای ویرایش تنظیمات دسترسی ندارید.', 'danger');
        redirect('/admin/settings.php');
    }
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('درخواست نامعتبر است.', 'danger');
        redirect('/admin/settings.php');
    }

    foreach ($allKeys as $key => $meta) {
        $type = $meta['type'];
        $value = $_POST[$key] ?? '';
        if ($type === 'number') {
            $value = (string)max(0, (int)$value);
        }
        Database::execute(
            'INSERT INTO site_settings (setting_key,setting_value,setting_type,updated_at) VALUES (?,?,?,NOW()) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value), setting_type=VALUES(setting_type), updated_at=NOW()',
            [$key, $value, $type]
        );
    }

    if (!empty($_FILES['logo']['name'])) {
        $up = Upload::save($_FILES['logo'], 'uploads/logo', Upload::IMAGE_EXTENSIONS);
        if ($up['ok']) {
            Database::execute(
                'INSERT INTO site_settings (setting_key,setting_value,setting_type,updated_at) VALUES ("logo_path",?,"image",NOW()) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value), updated_at=NOW()',
                [$up['file_path']]
            );
        } else {
            flash($up['error'], 'danger');
            redirect('/admin/settings.php');
        }
    }

    flash('تنظیمات ذخیره شد.');
    redirect('/admin/settings.php');
}

require __DIR__ . '/../views/partials/admin-header.php';
?>
<form class="card admin-form" method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= e(Auth::csrfToken()) ?>">
    <h2>تنظیمات سایت</h2>
    <div class="grid grid-2">
        <?php foreach ($siteKeys as $key => $meta): ?>
            <label class="form-field">
                <span><?= e($meta['label']) ?></span>
                <?php if ($meta['type'] === 'textarea'): ?>
                    <textarea name="<?= e($key) ?>"><?= e(setting($key, $meta['default'])) ?></textarea>
                <?php else: ?>
                    <input <?= $meta['type'] === 'color' ? 'type="color"' : '' ?> name="<?= e($key) ?>" value="<?= e(setting($key, $meta['default'])) ?>">
                <?php endif; ?>
            </label>
        <?php endforeach; ?>
        <label class="form-field">
            <span>آپلود لوگو</span>
            <input type="file" name="logo" accept="image/png,image/jpeg">
            <small><?= e(setting('logo_path')) ?></small>
        </label>
    </div>

    <h2 class="section-title">تنظیمات داشبورد مدیرعامل</h2>
    <div class="grid grid-2">
        <?php foreach ($ceoKeys as $key => $meta): ?>
            <label class="form-field">
                <span><?= e($meta['label']) ?></span>
                <input <?= $meta['type'] === 'number' ? 'type="number" min="0" step="1"' : '' ?> name="<?= e($key) ?>" value="<?= e(setting($key, $meta['default'])) ?>">
            </label>
        <?php endforeach; ?>
    </div>
    <button class="btn btn-primary">ذخیره تنظیمات</button>
</form>
</section></main></div><script src="/assets/js/app.js"></script></body></html>
