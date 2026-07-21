<?php

require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../services/FormulaService.php';

FormulaService::boot();
FormulaService::require('view');
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            throw new DomainException('اعتبار فرم منقضی شده است.');
        }
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'save_draft') {
            FormulaService::require('manage');
            $normalized = FormulaEngine::normalizeBuilderInput($_POST);
            $versionId = FormulaRepository::saveDraft(
                $normalized,
                (int)Auth::user()['id'],
                (int)($_POST['definition_id'] ?? 0) ?: null
            );
            flash('نسخه پیش‌نویس فرمول ذخیره شد. تا زمان انتشار روی محاسبات فعال اثر ندارد.');
            redirect('/admin/formula-builder.php?version=' . $versionId);
        }
        if ($action === 'publish') {
            FormulaService::require('publish');
            FormulaRepository::publish((int)($_POST['version_id'] ?? 0), (int)Auth::user()['id']);
            flash('نسخه فرمول پس از کنترل تداخل و وابستگی منتشر شد.');
            redirect('/admin/formula-builder.php?version=' . (int)$_POST['version_id']);
        }
        if ($action === 'rollback') {
            FormulaService::require('rollback');
            $newVersionId = FormulaRepository::rollbackToVersion(
                (int)($_POST['version_id'] ?? 0),
                (int)Auth::user()['id']
            );
            flash('نسخه انتخاب‌شده به‌صورت پیش‌نویس جدید بازیابی شد؛ برای اثرگذاری آن را بررسی و منتشر کنید.');
            redirect('/admin/formula-builder.php?version=' . $newVersionId);
        }
        throw new InvalidArgumentException('عملیات فرمول معتبر نیست.');
    } catch (Throwable $e) {
        $error = FormulaService::uiError($e, 'انجام عملیات فرمول ممکن نشد.');
    }
}

$category = (string)($_GET['category'] ?? '');
if ($category !== '' && !isset(FormulaEngine::CATEGORIES[$category])) $category = '';
$definitions = FormulaRepository::listDefinitions($category ?: null);
$selectedVersionId = (int)($_GET['version'] ?? 0);
$selectedDefinitionId = (int)($_GET['definition'] ?? 0);
if (!$selectedVersionId && $selectedDefinitionId) {
    $selectedVersionId = (int)(Database::fetch(
        'SELECT id FROM formula_versions WHERE definition_id=? ORDER BY version_no DESC LIMIT 1',
        [$selectedDefinitionId]
    )['id'] ?? 0);
}
$edit = $selectedVersionId ? FormulaRepository::version($selectedVersionId) : null;
$versionRows = $edit ? FormulaRepository::versions((int)$edit['definition_id']) : [];
$auditRows = $edit ? FormulaRepository::auditLogs((int)$edit['definition_id'], 30) : [];
$allDefinitions = Database::fetchAll('SELECT id,formula_key,title FROM formula_definitions WHERE active=1 ORDER BY title,id');
$sources = FormulaSourceRegistry::sources();
$pageTitle = 'فرمول‌ساز تصویری';
$adminExtraStylesheets = ['/assets/css/formula-builder.css'];
$adminExtraScripts = ['/assets/js/formula-builder.js'];
require __DIR__ . '/../views/partials/admin-header.php';

$conditionValues = $edit['condition_values'] ?? [0];
$existingFilters = $edit['filters'] ?? [];
$selectedSource = (string)($edit['data_source_key'] ?? 'sample_input');
$sourceMeta = $sources[$selectedSource] ?? $sources['sample_input'];
?>
<div class="section-heading-row formula-heading">
    <div>
        <span class="formula-eyebrow">Formula Builder</span>
        <h1>فرمول‌ساز تصویری</h1>
        <p class="muted">قاعده را با کنترل‌های محدود بسازید؛ هیچ JSON، SQL یا عبارت اجرایی وارد نمی‌شود.</p>
    </div>
    <div class="actions">
        <?php if (FormulaService::can('test')): ?><a class="btn btn-primary" href="/admin/formula-test.php<?= $edit ? '?version=' . (int)$edit['id'] : '' ?>">پنل آزمون فرمول</a><?php endif; ?>
    </div>
</div>

<?php if ($error !== ''): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<div class="alert alert-warning">نسخه پیش‌نویس هیچ اثری روی اعداد فعال ندارد. انتشار فقط پس از کنترل تداخل، بازه اعتبار و وابستگی‌ها انجام می‌شود.</div>

<nav class="formula-category-tabs" aria-label="دسته فرمول">
    <a class="<?= $category === '' ? 'is-active' : '' ?>" href="/admin/formula-builder.php">همه</a>
    <?php foreach (FormulaEngine::CATEGORIES as $key => $label): ?>
        <a class="<?= $category === $key ? 'is-active' : '' ?>" href="?category=<?= e($key) ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
</nav>

<div class="formula-workspace">
    <aside class="card formula-library">
        <div class="formula-library-head">
            <div><h2>کتابخانه فرمول‌ها</h2><small><?= e((string)count($definitions)) ?> فرمول</small></div>
            <?php if (FormulaService::can('manage')): ?><a class="btn btn-small" href="/admin/formula-builder.php">فرمول جدید</a><?php endif; ?>
        </div>
        <div class="formula-library-list">
            <?php foreach ($definitions as $definition): ?>
                <?php $versionId = (int)($definition['draft_version_id'] ?: $definition['active_version_id']); ?>
                <a class="formula-library-item <?= $edit && (int)$edit['definition_id'] === (int)$definition['id'] ? 'is-active' : '' ?>" href="?version=<?= $versionId ?>">
                    <span><?= e($definition['title']) ?></span>
                    <small><?= e(FormulaEngine::CATEGORIES[$definition['category_key']] ?? $definition['category_key']) ?> · <?= $definition['draft_version_id'] ? 'پیش‌نویس ' . e((string)$definition['draft_version_no']) : 'فعال ' . e((string)$definition['active_version_no']) ?></small>
                </a>
            <?php endforeach; ?>
            <?php if (!$definitions): ?><div class="app-empty-state">هنوز فرمولی در این دسته ساخته نشده است.</div><?php endif; ?>
        </div>
    </aside>

    <section class="formula-main">
        <form method="post" class="card admin-form formula-builder-form" data-formula-builder data-prevent-dashboard-refresh="1">
            <input type="hidden" name="csrf_token" value="<?= e(Auth::csrfToken()) ?>">
            <input type="hidden" name="action" value="save_draft">
            <input type="hidden" name="definition_id" value="<?= e((string)($edit['definition_id'] ?? 0)) ?>">

            <ol class="formula-steps" aria-label="مراحل ساخت فرمول">
                <li class="is-active"><span>۱</span>هویت</li>
                <li><span>۲</span>منبع و شاخص</li>
                <li><span>۳</span>شرط و نتیجه</li>
                <li><span>۴</span>اعتبار و انتشار</li>
            </ol>

            <section class="formula-section">
                <header><span>۱</span><div><h2>هویت فرمول</h2><p>عنوان، دسته و کلید پایدار فرمول را مشخص کنید.</p></div></header>
                <div class="grid grid-3">
                    <label class="form-field"><span>عنوان فرمول</span><input name="title" value="<?= e($edit['title'] ?? '') ?>" maxlength="190" required></label>
                    <label class="form-field"><span>کلید انگلیسی</span><input name="formula_key" dir="ltr" value="<?= e($edit['formula_key'] ?? '') ?>" pattern="[a-z][a-z0-9_]{2,99}" <?= $edit ? 'readonly' : '' ?> required></label>
                    <label class="form-field"><span>دسته فرمول</span><select name="category_key" required><?php foreach (FormulaEngine::CATEGORIES as $key => $label): ?><option value="<?= e($key) ?>" <?= ($edit['category_key'] ?? $category) === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
                    <label class="form-field"><span>دامنه مالک</span><input name="owner_scope" value="<?= e($edit['owner_scope'] ?? '') ?>" placeholder="مثلاً sales یا hr"></label>
                    <label class="form-field formula-span-2"><span>توضیح کاربر</span><textarea name="description" rows="2"><?= e($edit['description'] ?? '') ?></textarea></label>
                </div>
            </section>

            <section class="formula-section">
                <header><span>۲</span><div><h2>منبع و شاخص</h2><p>منبع و ستون‌ها فقط از فهرست امن انتخاب می‌شوند.</p></div></header>
                <div class="grid grid-3">
                    <label class="form-field"><span>منبع داده</span><select name="data_source_key" data-formula-source required><?php foreach ($sources as $key => $source): ?><option value="<?= e($key) ?>" <?= $selectedSource === $key ? 'selected' : '' ?>><?= e($source['title']) ?></option><?php endforeach; ?></select></label>
                    <label class="form-field"><span>شاخص</span><select name="metric_key" data-formula-metric data-selected="<?= e($edit['metric_key'] ?? '') ?>" required><?php foreach ($sourceMeta['metrics'] as $key => $label): ?><option value="<?= e($key) ?>" <?= ($edit['metric_key'] ?? '') === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
                    <label class="form-field"><span>نوع تجمیع</span><select name="aggregation_key" data-formula-aggregation required><?php foreach (FormulaEngine::AGGREGATIONS as $key => $label): ?><option value="<?= e($key) ?>" <?= ($edit['aggregation_key'] ?? 'SUM') === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
                    <label class="form-field" data-comparison-field><span>شاخص مقایسه برای درصد/نسبت</span><select name="comparison_metric_key" data-formula-comparison data-selected="<?= e($edit['comparison_metric_key'] ?? '') ?>"><option value="">انتخاب کنید</option><?php foreach ($sourceMeta['metrics'] as $key => $label): ?><option value="<?= e($key) ?>" <?= ($edit['comparison_metric_key'] ?? '') === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
                </div>

                <div class="formula-filter-head"><div><h3>فیلترهای منبع</h3><p class="muted">هر فیلتر فقط روی فیلدهای مجاز همان منبع اعمال می‌شود.</p></div><button class="btn btn-small" type="button" data-add-formula-filter>افزودن فیلتر</button></div>
                <div class="formula-filter-list" data-formula-filters>
                    <?php foreach ($existingFilters as $filter): ?>
                        <div class="formula-filter-row">
                            <label><span>فیلد</span><select name="filter_field[]" data-filter-field data-selected="<?= e($filter['field_key']) ?>"><?php foreach ($sourceMeta['filters'] as $key => $label): ?><option value="<?= e($key) ?>" <?= $filter['field_key'] === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
                            <label><span>عملگر</span><select name="filter_operator[]"><?php foreach (FormulaEngine::OPERATORS as $key => $label): ?><option value="<?= e($key) ?>" <?= $filter['operator_key'] === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
                            <label><span>مقدار</span><input name="filter_value[]" value="<?= e(implode('، ', $filter['values'])) ?>"></label>
                            <button class="formula-remove" type="button" data-remove-formula-filter aria-label="حذف فیلتر">×</button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="formula-section">
                <header><span>۳</span><div><h2>شرط و نتیجه</h2><p>نتیجه فقط از الگوهای عددی کنترل‌شده ساخته می‌شود.</p></div></header>
                <div class="formula-rule-sentence">
                    <span>اگر</span>
                    <select name="operator_key"><?php foreach (FormulaEngine::OPERATORS as $key => $label): ?><option value="<?= e($key) ?>" <?= ($edit['operator_key'] ?? '>=') === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select>
                    <input name="condition_value" value="<?= e(implode('، ', $conditionValues)) ?>" placeholder="مثلاً ۷۵ یا ۷۵، ۱۰۰" required>
                    <span>آنگاه</span>
                    <select name="result_type"><?php foreach (FormulaEngine::RESULT_TYPES as $key => $label): ?><option value="<?= e($key) ?>" <?= ($edit['result_type'] ?? 'fixed') === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select>
                    <input name="result_value" inputmode="decimal" value="<?= e((string)($edit['result_value'] ?? 0)) ?>" required>
                </div>
                <div class="grid grid-2">
                    <label class="form-field"><span>اولویت اجرا</span><input type="number" min="0" max="10000" name="priority" value="<?= e((string)($edit['priority'] ?? 100)) ?>"></label>
                    <label class="form-field"><span>یادداشت نسخه</span><input name="user_note" value="<?= e($edit['user_note'] ?? '') ?>" maxlength="500"></label>
                </div>
            </section>

            <section class="formula-section">
                <header><span>۴</span><div><h2>اعتبار و وابستگی</h2><p>بازه زمانی، نسخه و وابستگی‌ها پیش از انتشار کنترل می‌شوند.</p></div></header>
                <div class="grid grid-3">
                    <label class="form-field"><span>شروع اعتبار</span><?= app_date_input('effective_from', $edit['effective_from'] ?? '', ['placeholder' => '۱۴۰۵/۰۱/۰۱']) ?></label>
                    <label class="form-field"><span>پایان اعتبار</span><?= app_date_input('effective_to', $edit['effective_to'] ?? '', ['placeholder' => 'اختیاری']) ?></label>
                    <label class="form-field"><span>فرمول‌های وابسته</span><select name="dependency_ids[]" multiple size="4"><?php foreach ($allDefinitions as $dependency): if ((int)($edit['definition_id'] ?? 0) === (int)$dependency['id']) continue; ?><option value="<?= (int)$dependency['id'] ?>" <?= in_array((int)$dependency['id'], $edit['dependency_ids'] ?? [], true) ? 'selected' : '' ?>><?= e($dependency['title']) ?></option><?php endforeach; ?></select></label>
                </div>
                <label class="checkbox-item"><input type="checkbox" name="active" value="1" <?= !isset($edit['definition_active']) || (int)$edit['definition_active'] === 1 ? 'checked' : '' ?>> فرمول در کتابخانه فعال باشد</label>
            </section>

            <?php if (FormulaService::can('manage')): ?><div class="form-actions"><button class="btn btn-primary">ذخیره به‌عنوان نسخه پیش‌نویس جدید</button></div><?php endif; ?>
        </form>

        <?php if ($edit): ?>
        <section class="card formula-version-card">
            <div class="section-heading-row">
                <div><h2>نسخه‌ها و کنترل انتشار</h2><p class="muted">نسخه فعال immutable است؛ هر تغییر یک نسخه جدید می‌سازد.</p></div>
                <span class="badge">نسخه انتخابی: <?= e((string)$edit['version_no']) ?> / <?= e($edit['status']) ?></span>
            </div>
            <div class="table-wrap"><table><thead><tr><th>نسخه</th><th>وضعیت</th><th>اعتبار</th><th>سازنده</th><th>انتشار</th><th>عملیات</th></tr></thead><tbody>
            <?php foreach ($versionRows as $version): ?>
                <tr>
                    <td><a href="?version=<?= (int)$version['id'] ?>">v<?= e((string)$version['version_no']) ?></a></td>
                    <td><span class="formula-status formula-status-<?= e($version['status']) ?>"><?= e($version['status']) ?></span></td>
                    <td><?= e($version['effective_from'] ? format_jalali_date($version['effective_from']) : 'بدون شروع') ?> تا <?= e($version['effective_to'] ? format_jalali_date($version['effective_to']) : 'بدون پایان') ?></td>
                    <td><?= e($version['created_by_name'] ?? '-') ?></td>
                    <td><?= e($version['published_at'] ? format_jalali_datetime($version['published_at']) : '-') ?></td>
                    <td class="row-actions">
                        <?php if ($version['status'] === 'draft' && FormulaService::can('publish')): ?><form method="post"><input type="hidden" name="csrf_token" value="<?= e(Auth::csrfToken()) ?>"><input type="hidden" name="action" value="publish"><input type="hidden" name="version_id" value="<?= (int)$version['id'] ?>"><button class="btn btn-small btn-primary">انتشار</button></form><?php endif; ?>
                        <?php if (in_array($version['status'], ['active','retired'], true) && FormulaService::can('rollback')): ?><form method="post"><input type="hidden" name="csrf_token" value="<?= e(Auth::csrfToken()) ?>"><input type="hidden" name="action" value="rollback"><input type="hidden" name="version_id" value="<?= (int)$version['id'] ?>"><button class="btn btn-small">بازیابی به پیش‌نویس</button></form><?php endif; ?>
                        <?php if (FormulaService::can('test')): ?><a class="btn btn-small" href="/admin/formula-test.php?version=<?= (int)$version['id'] ?>">آزمون</a><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody></table></div>
        </section>

        <section class="card">
            <h2>ردپای تغییرات</h2>
            <div class="formula-audit-timeline">
                <?php foreach ($auditRows as $audit): ?><article><span></span><div><strong><?= e($audit['action']) ?></strong><small><?= e($audit['actor_name'] ?? 'سیستم') ?> · <?= e(format_jalali_datetime($audit['created_at'])) ?></small></div></article><?php endforeach; ?>
                <?php if (!$auditRows): ?><div class="app-empty-state">هنوز رخداد ممیزی ثبت نشده است.</div><?php endif; ?>
            </div>
        </section>
        <?php endif; ?>
    </section>
</div>
<script>window.SOBHAN_FORMULA_SOURCES = <?= json_encode($sources, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;</script>
<?php require __DIR__ . '/../views/partials/admin-footer.php'; ?>
