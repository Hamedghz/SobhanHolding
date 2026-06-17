<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/SobhanApiClient.php';

Auth::requireLogin();
if (!Auth::can('view_sobhan_api_settings') && !Auth::can('manage_sobhan_api_settings') && !Auth::can('view_data_source_settings') && !Auth::can('manage_data_source_settings')) {
    http_response_code(403);
    echo 'دسترسی غیرمجاز';
    exit;
}
$pageTitle = 'تنظیمات API سبحان';

function sobhan_save_setting(string $key, string $value, string $type): void
{
    Database::execute(
        'INSERT INTO site_settings (setting_key,setting_value,setting_type,updated_at) VALUES (?,?,?,NOW()) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value), setting_type=VALUES(setting_type), updated_at=NOW()',
        [$key, $value, $type]
    );
}

$testResult = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? 'save');
    if (in_array($action, ['save', 'test'], true) && !Auth::can('manage_sobhan_api_settings', 'edit')) {
        flash('برای ویرایش تنظیمات API دسترسی ندارید.', 'danger');
        redirect('/admin/sobhan-api-settings.php');
    }
    if ($action === 'save_data_source' && !Auth::can('manage_data_source_settings', 'edit')) {
        flash('برای تغییر منبع داده دسترسی ندارید.', 'danger');
        redirect('/admin/sobhan-api-settings.php#data-source-settings');
    }
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('درخواست نامعتبر است.', 'danger');
        redirect('/admin/sobhan-api-settings.php');
    }

    if ($action === 'save_data_source') {
        $mode = in_array($_POST['sobhan_distribution_data_mode'] ?? '', ['import_file', 'ai_api'], true) ? $_POST['sobhan_distribution_data_mode'] : 'import_file';
        $autofill = !empty($_POST['sobhan_ai_autofill_enabled']) ? '1' : '0';
        $overwrite = !empty($_POST['sobhan_ai_overwrite_manual_data']) ? '1' : '0';

        if ($autofill === '1' && !Auth::can('toggle_ai_autofill', 'edit')) {
            flash('برای فعال‌سازی تکمیل خودکار با هوش مصنوعی دسترسی ندارید.', 'danger');
            redirect('/admin/sobhan-api-settings.php#data-source-settings');
        }
        if ($overwrite === '1' && !Auth::can('allow_ai_overwrite_manual_data', 'edit')) {
            flash('برای اجازه بازنویسی داده‌های دستی دسترسی جداگانه لازم است.', 'danger');
            redirect('/admin/sobhan-api-settings.php#data-source-settings');
        }

        sobhan_save_setting('sobhan_distribution_data_mode', $mode, 'select');
        sobhan_save_setting('sobhan_ai_autofill_enabled', $autofill, 'boolean');
        sobhan_save_setting('sobhan_ai_overwrite_manual_data', $overwrite, 'boolean');
        sobhan_save_setting('sobhan_static_pharmacy_mode', '1', 'boolean');
        flash('تنظیمات منبع داده ذخیره شد.');
        redirect('/admin/sobhan-api-settings.php#data-source-settings');
    }

    $baseUrl = rtrim(trim((string)($_POST['sobhan_api_base_url'] ?? '')), '/');
    $timeout = (string)max(1, min(60, (int)($_POST['sobhan_api_timeout'] ?? 10)));
    $enabled = !empty($_POST['sobhan_api_enabled']) ? '1' : '0';
    $newKey = trim((string)($_POST['sobhan_api_key'] ?? ''));

    if ($baseUrl !== '' && !filter_var($baseUrl, FILTER_VALIDATE_URL)) {
        flash('آدرس API معتبر نیست.', 'danger');
        redirect('/admin/sobhan-api-settings.php');
    }

    sobhan_save_setting('sobhan_api_base_url', $baseUrl, 'text');
    sobhan_save_setting('sobhan_api_timeout', $timeout, 'number');
    sobhan_save_setting('sobhan_api_enabled', $enabled, 'boolean');
    if ($newKey !== '') {
        sobhan_save_setting('sobhan_api_key', $newKey, 'password');
    }

    if ($action === 'test') {
        $client = new SobhanApiClient($baseUrl, $newKey !== '' ? $newKey : setting('sobhan_api_key', ''), (int)$timeout, $enabled === '1');
        $testResult = $client->get('/health');
        if ($testResult['ok']) {
            flash('اتصال به سرویس گزارش‌گیری سبحان با موفقیت برقرار شد.');
        } else {
            flash($testResult['error']['message_fa'] ?? 'اتصال به سرویس گزارش‌گیری سبحان برقرار نشد.', 'danger');
        }
    } else {
        flash('تنظیمات API سبحان ذخیره شد.');
    }
    redirect('/admin/sobhan-api-settings.php');
}

$maskedKey = SobhanApiClient::maskKey(setting('sobhan_api_key', ''));
$distributionMode = setting('sobhan_distribution_data_mode', 'import_file');
$aiAutofillEnabled = setting('sobhan_ai_autofill_enabled', '0') === '1';
$aiOverwriteManual = setting('sobhan_ai_overwrite_manual_data', '0') === '1';
$canManageApi = Auth::can('manage_sobhan_api_settings', 'edit');
require __DIR__ . '/../views/partials/admin-header.php';
?>
<form class="card admin-form" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= e(Auth::csrfToken()) ?>">
    <h2>تنظیمات اتصال به API گزارش‌گیری سبحان</h2>
    <div class="grid grid-2">
        <label class="form-field">
            <span>SOBHAN_API_BASE_URL</span>
            <input dir="ltr" name="sobhan_api_base_url" value="<?= e(setting('sobhan_api_base_url', 'http://178.131.83.26:18000')) ?>" placeholder="http://178.131.83.26:18000" <?= $canManageApi ? '' : 'disabled' ?>>
        </label>
        <label class="form-field">
            <span>SOBHAN_API_TIMEOUT</span>
            <input dir="ltr" type="number" min="1" max="60" name="sobhan_api_timeout" value="<?= e(setting('sobhan_api_timeout', '10')) ?>" <?= $canManageApi ? '' : 'disabled' ?>>
        </label>
        <label class="form-field">
            <span>SOBHAN_API_KEY</span>
            <input dir="ltr" type="password" name="sobhan_api_key" value="" placeholder="<?= e($maskedKey ?: 'برای تغییر، کلید جدید را وارد کنید') ?>" autocomplete="new-password" <?= $canManageApi ? '' : 'disabled' ?>>
            <?php if ($maskedKey): ?><small>کلید ذخیره‌شده: <?= e($maskedKey) ?></small><?php endif; ?>
        </label>
        <label class="checkbox-item sobhan-toggle">
            <input type="checkbox" name="sobhan_api_enabled" value="1" <?= setting('sobhan_api_enabled', '0') === '1' ? 'checked' : '' ?> <?= $canManageApi ? '' : 'disabled' ?>>
            <span>SOBHAN_API_ENABLED</span>
        </label>
    </div>
    <div class="form-actions">
        <?php if ($canManageApi): ?>
            <button class="btn btn-primary" name="action" value="save">ذخیره تنظیمات</button>
            <button class="btn" name="action" value="test">Test API Connection</button>
        <?php else: ?>
            <p class="muted">شما فقط می‌توانید تنظیمات را مشاهده کنید.</p>
        <?php endif; ?>
    </div>
</form>
<form class="card admin-form" method="post" id="data-source-settings">
    <input type="hidden" name="csrf_token" value="<?= e(Auth::csrfToken()) ?>">
    <h2>منبع داده داشبورد و اطلاعات شرکت پخش</h2>
    <section class="settings-section">
        <h3>تنظیمات منبع داده</h3>
        <p class="muted">برای اطلاعات شرکت پخش می‌توانید مشخص کنید داده‌ها از فایل ایمپورت‌شده خوانده شوند یا از API/هوش مصنوعی سبحان تکمیل گردند. اطلاعات داروخانه‌ها همیشه از فایل استاتیک خوانده می‌شود و توسط هوش مصنوعی بازنویسی نمی‌شود.</p>
        <div class="data-source-options">
            <label class="checkbox-item">
                <input type="radio" name="sobhan_distribution_data_mode" value="import_file" <?= $distributionMode === 'import_file' ? 'checked' : '' ?> <?= Auth::can('manage_data_source_settings', 'edit') ? '' : 'disabled' ?>>
                <strong>فایل ایمپورت‌شده</strong>
                <small>خواندن از فایل ایمپورت‌شده</small>
            </label>
            <label class="checkbox-item">
                <input type="radio" name="sobhan_distribution_data_mode" value="ai_api" <?= $distributionMode === 'ai_api' ? 'checked' : '' ?> <?= Auth::can('manage_data_source_settings', 'edit') ? '' : 'disabled' ?>>
                <strong>API/هوش مصنوعی سبحان</strong>
                <small>تکمیل و تحلیل با هوش مصنوعی/API سبحان</small>
            </label>
        </div>
        <div class="grid grid-2">
            <label class="checkbox-item sobhan-toggle">
                <input type="checkbox" name="sobhan_ai_autofill_enabled" value="1" <?= $aiAutofillEnabled ? 'checked' : '' ?> <?= Auth::can('toggle_ai_autofill', 'edit') ? '' : 'disabled' ?>>
                <span>تکمیل خودکار با هوش مصنوعی</span>
            </label>
            <label class="checkbox-item sobhan-toggle">
                <input type="checkbox" name="sobhan_ai_overwrite_manual_data" value="1" <?= $aiOverwriteManual ? 'checked' : '' ?> <?= Auth::can('allow_ai_overwrite_manual_data', 'edit') ? '' : 'disabled' ?> data-overwrite-toggle>
                <span>اجازه بازنویسی داده‌های دستی/ایمپورت‌شده</span>
            </label>
        </div>
        <div class="alert alert-error" data-overwrite-warning <?= $aiOverwriteManual ? '' : 'hidden' ?>>با فعال‌سازی این گزینه، داده‌های دستی یا ایمپورت‌شده ممکن است با داده‌های API/هوش مصنوعی جایگزین شوند.</div>
        <p><span class="badge">داروخانه‌ها: همیشه از فایل استاتیک خوانده می‌شود</span></p>
    </section>
    <?php if (Auth::can('manage_data_source_settings', 'edit')): ?>
        <button class="btn btn-primary" name="action" value="save_data_source">ذخیره تنظیمات منبع داده</button>
    <?php else: ?>
        <p class="muted">شما فقط می‌توانید وضعیت منبع داده را مشاهده کنید.</p>
    <?php endif; ?>
</form>
<section class="card">
    <h2>نکات امنیتی</h2>
    <p class="muted">کلید API پس از ذخیره نمایش داده نمی‌شود و فقط درخواست‌های PHP سرور از آن استفاده می‌کنند.</p>
</section>
<script>
const overwriteToggle = document.querySelector('[data-overwrite-toggle]');
const overwriteWarning = document.querySelector('[data-overwrite-warning]');
overwriteToggle?.addEventListener('change', () => {
    if (overwriteWarning) overwriteWarning.hidden = !overwriteToggle.checked;
});
</script>
</section></main></div><script src="/assets/js/app.js"></script></body></html>
