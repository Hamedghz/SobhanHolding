<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/ThemeProfile.php';

Auth::requireLogin();
$user = Auth::user();
$pageTitle = 'ظاهر پنل';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
        flash('درخواست نامعتبر است.', 'danger');
        redirect('/admin/theme-settings.php');
    }
    try {
        ThemeProfile::save((int)$user['id'], (string)($_POST['profile_key'] ?? ''), (string)($_POST['accent_color'] ?? ''), (string)($_POST['effects_mode'] ?? 'standard'));
        flash('ظاهر پنل برای حساب شما ذخیره شد.');
    } catch (InvalidArgumentException $e) {
        flash($e->getMessage(), 'danger');
    } catch (Throwable $e) {
        error_log('Theme preference save: ' . $e->getMessage());
        flash('ذخیره تنظیمات ظاهر انجام نشد.', 'danger');
    }
    redirect('/admin/theme-settings.php');
}

$preference = ThemeProfile::forUser((int)$user['id']);
$profiles = ThemeProfile::profiles();
require __DIR__ . '/../views/partials/admin-header.php';
?>
<section class="card theme-settings-panel">
    <div class="section-heading-row"><div><h1>ظاهر پنل</h1><p class="muted">این انتخاب فقط برای حساب کاربری شما اعمال می‌شود.</p></div></div>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= e(Auth::csrfToken()) ?>">
        <fieldset class="theme-profile-grid"><legend>پروفایل تم</legend>
            <?php foreach ($profiles as $key => $profile): ?>
                <label class="theme-profile-option">
                    <input type="radio" name="profile_key" value="<?= e($key) ?>" <?= $preference['profile_key'] === $key ? 'checked' : '' ?>>
                    <span class="theme-profile-preview theme-profile-preview-<?= e($key) ?>"><i></i><b></b><em></em></span>
                    <strong><?= e($profile['label']) ?></strong><small><?= e($profile['description']) ?></small>
                </label>
            <?php endforeach; ?>
        </fieldset>
        <div class="grid grid-2 theme-profile-controls">
            <label class="form-field"><span>رنگ تاکید</span><input type="color" name="accent_color" value="<?= e($preference['accent_color']) ?>"></label>
            <label class="form-field"><span>افکت‌های بصری</span><select name="effects_mode"><option value="standard" <?= $preference['effects_mode'] === 'standard' ? 'selected' : '' ?>>استاندارد و روان</option><option value="reduced" <?= $preference['effects_mode'] === 'reduced' ? 'selected' : '' ?>>حداقل افکت</option></select></label>
        </div>
        <div class="form-actions"><button class="btn btn-primary">ذخیره ظاهر</button><a class="btn" href="/admin/index.php">بازگشت به داشبورد</a></div>
    </form>
</section>
<?php require __DIR__ . '/../views/partials/admin-footer.php'; ?>
