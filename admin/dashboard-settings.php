<?php

require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../lib/DashboardPreferences.php';

Auth::requireLogin();
$allowedScopes = DashboardPreferences::allowedManageScopes(Auth::user());
$scope = (string)($_POST['scope'] ?? $_GET['scope'] ?? ($allowedScopes[0] ?? ''));
if (!in_array($scope, $allowedScopes, true)) {
    http_response_code(403);
    exit('دسترسی تنظیمات این داشبورد را ندارید.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            throw new DomainException('اعتبار فرم منقضی شده است.');
        }
        DashboardPreferences::save($scope, $_POST, (int)Auth::user()['id']);
        flash('تنظیمات نمایش داشبورد ذخیره شد.');
    } catch (InvalidArgumentException|DomainException $e) {
        flash($e->getMessage(), 'danger');
    } catch (Throwable $e) {
        error_log('Dashboard preferences: ' . $e->getMessage());
        flash('ذخیره تنظیمات داشبورد انجام نشد.', 'danger');
    }
    redirect('/admin/dashboard-settings.php?scope=' . urlencode($scope));
}

$preferences = DashboardPreferences::forScope($scope);
$scopeLabels = DashboardPreferences::scopeLabels();
$periodLabels = [
    'daily' => 'روزانه',
    'weekly' => 'هفتگی',
    'monthly' => 'ماهانه',
    'quarterly' => 'فصلی',
    'half_yearly' => 'شش‌ماهه',
    'yearly' => 'سالانه',
];
$sizeLabels = ['third' => 'یک‌سوم', 'half' => 'نیم‌عرض', 'wide' => 'تمام‌عرض'];
$filterLabels = [
    'authorized_scope' => 'دامنه مجاز نقش',
    'own_scope' => 'فقط دامنه مستقیم کاربر',
    'all_authorized' => 'تمام داده مجاز',
];
$pageTitle = 'تنظیمات مشترک داشبورد';
require __DIR__ . '/../views/partials/admin-header.php';
?>
<div class="section-heading-row">
    <div>
        <h1>تنظیمات داشبورد <?= e($scopeLabels[$scope] ?? $scope) ?></h1>
        <p class="muted">نمایش، ترتیب، اندازه، دوره و فیلتر پیش‌فرض را بدون ورود JSON یا عدد کسب‌وکار تنظیم کنید.</p>
    </div>
    <div class="actions">
        <a class="btn" href="/admin/sales-aggregate-import.php">مرکز ورود داده فروش</a>
        <?php if ($scope === 'sales_manager'): ?><a class="btn" href="/admin/manager-dashboard-settings.php">تنظیمات تخصصی مدیر فروش</a><?php endif; ?>
        <?php if ($scope === 'supervisor'): ?><a class="btn" href="/admin/supervisor-settings.php">ساختار تیم سرپرست</a><?php endif; ?>
    </div>
</div>

<?php if (count($allowedScopes) > 1): ?>
<nav class="manager-tabs" aria-label="دامنه داشبورد">
    <?php foreach ($allowedScopes as $scopeKey): ?>
        <a class="<?= $scopeKey === $scope ? 'active' : '' ?>" href="?scope=<?= e($scopeKey) ?>"><?= e($scopeLabels[$scopeKey] ?? $scopeKey) ?></a>
    <?php endforeach; ?>
</nav>
<?php endif; ?>

<div class="alert alert-warning">
    این صفحه فقط تنظیمات نمایش را ذخیره می‌کند. اعداد داشبورد باید از Batch فعال، Viewهای گزارش و سرویس محاسبات خوانده شوند.
</div>

<form method="post" class="card admin-form">
    <input type="hidden" name="csrf_token" value="<?= e(Auth::csrfToken()) ?>">
    <input type="hidden" name="scope" value="<?= e($scope) ?>">
    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>ویجت</th>
                <th>منبع داده</th>
                <th>نمایش</th>
                <th>ترتیب</th>
                <th>اندازه</th>
                <th>دوره پیش‌فرض</th>
                <th>فیلتر پیش‌فرض</th>
                <th>بروزرسانی</th>
                <th>جزئیات</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($preferences as $key => $preference): ?>
                <tr>
                    <td>
                        <input name="title[<?= e($key) ?>]" value="<?= e($preference['title_override'] ?? $preference['title'] ?? $key) ?>" maxlength="190">
                        <small dir="ltr"><?= e($key) ?></small>
                    </td>
                    <td><code><?= e($preference['data_source_key']) ?></code></td>
                    <td><input type="checkbox" name="visible[<?= e($key) ?>]" value="1" <?= (int)$preference['visible'] === 1 ? 'checked' : '' ?> aria-label="نمایش <?= e($preference['title_override']) ?>"></td>
                    <td><input type="number" min="0" name="sort[<?= e($key) ?>]" value="<?= e((string)$preference['sort_order']) ?>"></td>
                    <td><select name="size[<?= e($key) ?>]"><?php foreach ($sizeLabels as $value => $label): ?><option value="<?= e($value) ?>" <?= $preference['size_key'] === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></td>
                    <td><select name="period[<?= e($key) ?>]"><?php foreach ($periodLabels as $value => $label): ?><option value="<?= e($value) ?>" <?= $preference['default_period_key'] === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></td>
                    <td><select name="filter_mode[<?= e($key) ?>]"><?php $filterMode = DashboardPreferences::filterMode($preference); foreach ($filterLabels as $value => $label): ?><option value="<?= e($value) ?>" <?= $filterMode === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></td>
                    <td><select name="refresh[<?= e($key) ?>]"><?php foreach ([0 => 'دستی', 60 => '۱ دقیقه', 300 => '۵ دقیقه', 900 => '۱۵ دقیقه', 3600 => '۱ ساعت'] as $value => $label): ?><option value="<?= e((string)$value) ?>" <?= (int)$preference['refresh_seconds'] === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></td>
                    <td><input type="checkbox" name="drilldown[<?= e($key) ?>]" value="1" <?= (int)$preference['drilldown_enabled'] === 1 ? 'checked' : '' ?> aria-label="اجازه مشاهده جزئیات <?= e($preference['title_override']) ?>"></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="form-actions"><button class="btn btn-primary">ذخیره تنظیمات نمایش</button></div>
</form>
<?php require __DIR__ . '/../views/partials/admin-footer.php'; ?>
