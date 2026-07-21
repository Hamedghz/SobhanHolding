<?php

require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../services/FormulaService.php';

FormulaService::boot();
FormulaService::require('test');
$selectedVersionId = (int)($_POST['version_id'] ?? $_GET['version'] ?? 0);
$versions = Database::fetchAll(
    'SELECT v.id,v.version_no,v.status,d.title,d.formula_key,d.category_key
     FROM formula_versions v JOIN formula_definitions d ON d.id=v.definition_id
     WHERE d.active=1 ORDER BY d.title,v.version_no DESC'
);
if (!$selectedVersionId && $versions) $selectedVersionId = (int)$versions[0]['id'];
$selectedVersion = $selectedVersionId ? FormulaRepository::version($selectedVersionId) : null;
$result = null;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) throw new DomainException('اعتبار فرم منقضی شده است.');
        if (!$selectedVersion) throw new InvalidArgumentException('نسخه فرمول انتخاب نشده است.');
        $periodKey = trim((string)($_POST['period_key'] ?? ''));
        $dateFrom = AppDate::toGregorian((string)($_POST['date_from'] ?? ''));
        $dateTo = AppDate::toGregorian((string)($_POST['date_to'] ?? ''));
        if ($periodKey !== '') {
            $period = AppDate::resolvePeriod($periodKey, (string)($_POST['date_from'] ?? ''), (string)($_POST['date_to'] ?? ''));
            $dateFrom = $period['start_date'];
            $dateTo = $period['end_date'];
        }
        $sampleValues = [];
        foreach ((array)($_POST['sample_metric'] ?? []) as $key => $value) {
            $normalized = trim(AppDate::normalizeDigits((string)$value));
            if ($normalized !== '') $sampleValues[(string)$key] = is_numeric($normalized) ? (float)$normalized : $normalized;
        }
        $context = [
            'period_key' => $periodKey,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'user_id' => (int)($_POST['user_id'] ?? 0),
            'line_code' => trim((string)($_POST['line_code'] ?? '')),
            'product_code' => trim((string)($_POST['product_code'] ?? '')),
            'customer_code' => trim((string)($_POST['customer_code'] ?? '')),
            'sample_values' => $sampleValues,
        ];
        $result = FormulaRepository::runTest($selectedVersionId, $context, (int)Auth::user()['id']);
    } catch (Throwable $e) {
        $error = FormulaService::uiError($e, 'آزمون فرمول انجام نشد.');
    }
}

$users = Database::fetchAll('SELECT id,name,employee_no FROM users WHERE status="active" ORDER BY name,id LIMIT 500');
$source = $selectedVersion ? FormulaSourceRegistry::source((string)$selectedVersion['data_source_key']) : null;
$pageTitle = 'پنل آزمون فرمول';
$adminExtraStylesheets = ['/assets/css/formula-builder.css'];
require __DIR__ . '/../views/partials/admin-header.php';
?>
<div class="section-heading-row">
    <div><h1>پنل آزمون فرمول</h1><p class="muted">پیش از انتشار، ورودی، شرط‌های برقرار، مقادیر میانی و نتیجه نهایی را ببینید.</p></div>
    <a class="btn" href="/admin/formula-builder.php<?= $selectedVersion ? '?version=' . (int)$selectedVersion['id'] : '' ?>">بازگشت به فرمول‌ساز</a>
</div>
<?php if ($error !== ''): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

<form method="post" class="card admin-form formula-test-form" data-prevent-dashboard-refresh="1">
    <input type="hidden" name="csrf_token" value="<?= e(Auth::csrfToken()) ?>">
    <div class="grid grid-3">
        <label class="form-field formula-span-2"><span>فرمول و نسخه</span><select name="version_id" onchange="location.href='?version='+this.value"><?php foreach ($versions as $version): ?><option value="<?= (int)$version['id'] ?>" <?= $selectedVersionId === (int)$version['id'] ? 'selected' : '' ?>><?= e($version['title']) ?> · v<?= e((string)$version['version_no']) ?> · <?= e($version['status']) ?></option><?php endforeach; ?></select></label>
        <label class="form-field"><span>دوره</span><?= app_period_select('period_key', $_POST['period_key'] ?? 'monthly', ['daily','weekly','monthly','quarterly','half_yearly','yearly','custom']) ?></label>
        <label class="form-field"><span>از تاریخ</span><?= app_date_input('date_from', $_POST['date_from'] ?? '') ?></label>
        <label class="form-field"><span>تا تاریخ</span><?= app_date_input('date_to', $_POST['date_to'] ?? '') ?></label>
        <label class="form-field"><span>کاربر</span><select name="user_id"><option value="0">همه کاربران مجاز</option><?php foreach ($users as $user): ?><option value="<?= (int)$user['id'] ?>" <?= (int)($_POST['user_id'] ?? 0) === (int)$user['id'] ? 'selected' : '' ?>><?= e($user['name'] . ($user['employee_no'] ? ' · ' . $user['employee_no'] : '')) ?></option><?php endforeach; ?></select></label>
        <label class="form-field"><span>لاین</span><input name="line_code" value="<?= e($_POST['line_code'] ?? '') ?>"></label>
        <label class="form-field"><span>کالا</span><input name="product_code" value="<?= e($_POST['product_code'] ?? '') ?>"></label>
        <label class="form-field"><span>مشتری</span><input name="customer_code" value="<?= e($_POST['customer_code'] ?? '') ?>"></label>
    </div>
    <?php if ($source && ($source['table'] ?? null) === null): ?>
        <details open class="formula-sample-inputs"><summary>مقادیر نمونه کنترل‌شده</summary><div class="grid grid-4"><?php foreach ($source['metrics'] as $key => $label): ?><label class="form-field"><span><?= e($label) ?></span><input inputmode="decimal" name="sample_metric[<?= e($key) ?>]" value="<?= e((string)($_POST['sample_metric'][$key] ?? 0)) ?>"></label><?php endforeach; ?></div></details>
    <?php else: ?>
        <div class="alert alert-warning">آزمون از منبع «<?= e($source['title'] ?? 'نامشخص') ?>» و فقط با فیلترهای کنترل‌شده بالا خوانده می‌شود؛ حداکثر ۱۰۰۰ ردیف وارد تست می‌شود.</div>
    <?php endif; ?>
    <div class="form-actions"><button class="btn btn-primary">اجرای آزمون بدون اثر روی داده</button></div>
</form>

<?php if ($result): ?>
<section class="formula-test-result">
    <div class="formula-result-hero <?= $result['matched'] ? 'is-matched' : 'is-unmatched' ?>">
        <span><?= $result['matched'] ? 'شرط برقرار شد' : 'شرط برقرار نشد' ?></span>
        <strong><?= e(number_format((float)$result['final_result'], 4)) ?></strong>
        <small>نتیجه نهایی نسخه v<?= e((string)$result['version']['version_no']) ?></small>
    </div>
    <div class="card">
        <h2>ردیابی محاسبه</h2>
        <ol class="formula-trace"><?php foreach ($result['trace'] as $step): ?><li><span><?= e($step['label']) ?></span><strong><?= e((string)$step['value']) ?></strong></li><?php endforeach; ?></ol>
    </div>
    <div class="card">
        <h2>ورودی‌های استفاده‌شده</h2>
        <div class="table-wrap"><table><thead><tr><th>ردیف</th><?php foreach (array_keys($result['rows'][0] ?? []) as $key): ?><th><?= e($key) ?></th><?php endforeach; ?></tr></thead><tbody><?php foreach (array_slice($result['rows'], 0, 20) as $index => $row): ?><tr><td><?= $index + 1 ?></td><?php foreach ($row as $value): ?><td><?= e(is_scalar($value) ? (string)$value : '') ?></td><?php endforeach; ?></tr><?php endforeach; ?><?php if (!$result['rows']): ?><tr><td>داده‌ای با فیلترهای انتخاب‌شده پیدا نشد.</td></tr><?php endif; ?></tbody></table></div>
    </div>
</section>
<?php endif; ?>
<?php require __DIR__ . '/../views/partials/admin-footer.php'; ?>
