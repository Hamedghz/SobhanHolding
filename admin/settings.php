<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Upload.php';
require_once __DIR__ . '/../core/Pwa.php';

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
$pwaFields = Pwa::fields();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::can('settings', 'edit')) {
        flash('برای ویرایش تنظیمات دسترسی ندارید.', 'danger');
        redirect('/admin/settings.php');
    }
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('درخواست نامعتبر است.', 'danger');
        redirect('/admin/settings.php');
    }

    foreach ($siteKeys as $key => $meta) {
        $type = $meta['type'];
        $value = trim((string)($_POST[$key] ?? ''));
        if ($type === 'number') {
            $value = (string)max(0, (int)$value);
        }
        if ($type === 'color' && !preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
            flash('فرمت رنگ معتبر نیست.', 'danger');
            redirect('/admin/settings.php');
        }
        Database::execute(
            'INSERT INTO site_settings (setting_key,setting_value,setting_type,updated_at) VALUES (?,?,?,NOW()) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value), setting_type=VALUES(setting_type), updated_at=NOW()',
            [$key, $value, $type]
        );
    }

    if (!empty($_FILES['logo']['name'])) {
        $up = Upload::save($_FILES['logo'], 'uploads/logo', Upload::IMAGE_EXTENSIONS, Pwa::MAX_IMAGE_SIZE);
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

    foreach ($pwaFields as $key => $meta) {
        if ($meta['type'] === 'image') continue;
        $value = Pwa::sanitize($key, (string)($_POST[$key] ?? $meta['default']));
        if ($value === null) {
            flash('مقدار «' . $meta['label'] . '» معتبر نیست.', 'danger');
            redirect('/admin/settings.php#pwa-settings');
        }
        Database::execute(
            'INSERT INTO site_settings (setting_key,setting_value,setting_type,updated_at) VALUES (?,?,?,NOW()) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value), setting_type=VALUES(setting_type), updated_at=NOW()',
            [$key, $value, $meta['type']]
        );
    }

    foreach (['pwa_icon_192', 'pwa_icon_512', 'pwa_favicon'] as $key) {
        if (empty($_FILES[$key]['name'])) continue;
        $up = Upload::save($_FILES[$key], 'uploads/pwa', Pwa::IMAGE_EXTENSIONS, Pwa::MAX_IMAGE_SIZE);
        if (!$up['ok']) {
            flash($pwaFields[$key]['label'] . ': ' . $up['error'], 'danger');
            redirect('/admin/settings.php#pwa-settings');
        }
        Database::execute(
            'INSERT INTO site_settings (setting_key,setting_value,setting_type,updated_at) VALUES (?,?,?,NOW()) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value), setting_type=VALUES(setting_type), updated_at=NOW()',
            [$key, $up['file_path'], 'image']
        );
    }

    flash('تنظیمات ذخیره شد.');
    redirect('/admin/settings.php');
}

require __DIR__ . '/../views/partials/admin-header.php';
?>
<form class="card admin-form site-settings-form" method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= e(Auth::csrfToken()) ?>">
    <header class="settings-page-heading"><div><h1>تنظیمات سایت</h1><p class="muted">برند، محتوای عمومی، لوگو و تنظیمات اپلیکیشن نصب‌شونده</p></div></header>
    <div class="site-settings-layout"><div class="site-settings-fields">
    <section class="settings-form-section" aria-labelledby="site-general-heading">
    <div class="settings-section-heading"><div><h2 id="site-general-heading">اطلاعات عمومی سایت</h2><p class="muted">این مقادیر روی وب‌سایت عمومی نمایش داده می‌شوند و از ظاهر پنل مستقل هستند.</p></div></div>
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
    </section>
    <section class="settings-section settings-form-section" id="pwa-settings" aria-labelledby="pwa-settings-heading">
        <div class="settings-section-heading"><div><h2 id="pwa-settings-heading">تنظیمات PWA</h2><p class="muted">نام، رنگ‌ها و آیکون‌های نسخه نصب‌شونده را مستقل از رنگ سایت و پنل مدیریت کنید.</p></div></div>
        <div class="grid grid-2">
            <?php foreach ($pwaFields as $key => $meta): ?>
                <?php if ($meta['type'] === 'textarea'): ?>
                    <label class="form-field">
                        <span><?= e($meta['label']) ?></span>
                        <textarea name="<?= e($key) ?>"><?= e(Pwa::value($key)) ?></textarea>
                    </label>
                <?php elseif ($meta['type'] === 'select'): ?>
                    <label class="form-field">
                        <span><?= e($meta['label']) ?></span>
                        <select name="<?= e($key) ?>">
                            <?php foreach ($meta['options'] as $option): ?>
                                <option value="<?= e($option) ?>" <?= Pwa::value($key) === $option ? 'selected' : '' ?>><?= e($option) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                <?php elseif ($meta['type'] === 'image'): ?>
                    <label class="form-field">
                        <span><?= e($meta['label']) ?></span>
                        <input type="file" name="<?= e($key) ?>" accept="image/png,image/jpeg,image/webp">
                        <small><?= e(Pwa::value($key)) ?></small>
                    </label>
                <?php else: ?>
                    <label class="form-field">
                        <span><?= e($meta['label']) ?></span>
                        <input <?= $meta['type'] === 'color' ? 'type="color"' : '' ?> name="<?= e($key) ?>" value="<?= e(Pwa::value($key)) ?>">
                    </label>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </section>
    </div><aside class="site-settings-preview" aria-labelledby="pwa-preview-heading">
        <h2 id="pwa-preview-heading">پیش‌نمایش اپلیکیشن</h2>
        <div class="pwa-preview pwa-preview-device">
            <div class="pwa-preview-icon" style="background-color: <?= e(Pwa::value('pwa_theme_color')) ?>">
                <?php if (Pwa::value('pwa_icon_192')): ?><img src="<?= e(Pwa::asset('pwa_icon_192')) ?>" alt="PWA"><?php else: ?><span><?= e(function_exists('mb_substr') ? mb_substr(Pwa::value('pwa_short_name'), 0, 1) : substr(Pwa::value('pwa_short_name'), 0, 1)) ?></span><?php endif; ?>
            </div>
            <div>
                <strong><?= e(Pwa::value('pwa_name')) ?></strong>
                <span><?= e(Pwa::value('pwa_short_name')) ?></span>
                <small>Theme: <?= e(Pwa::value('pwa_theme_color')) ?> | Start: <?= e(Pwa::value('pwa_start_url')) ?> | Display: <?= e(Pwa::value('pwa_display')) ?></small>
            </div>
        </div>
        <p class="muted">رنگ و آیکون این پیش‌نمایش فقط از تنظیمات PWA خوانده می‌شود.</p>
    </aside></div>
    <button class="btn btn-primary">ذخیره تنظیمات</button>
</form>
</section></main></div><script src="/assets/js/app.js"></script></body></html>
