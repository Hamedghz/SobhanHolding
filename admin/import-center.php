<?php

require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../services/UnifiedImportService.php';
require_once __DIR__ . '/../lib/ImportSettings.php';

Auth::requireLogin();
$canView = Auth::isAdmin() || Auth::can('import_center.view') || Auth::can('sales_data_view');
$canUpload = Auth::isAdmin() || Auth::can('import_center.upload') || Auth::can('sales_reference_upload') || Auth::can('sales_data_import');
$canCommit = Auth::isAdmin() || Auth::can('import_center.commit') || Auth::can('sales_reference_commit') || Auth::can('sales_data_import');
if (!$canView && !$canUpload) {
    http_response_code(403);
    exit('دسترسی غیرمجاز');
}

$actorId = (int)Auth::user()['id'];
$isAdmin = Auth::isAdmin();
$labels = ImportSourceRegistry::labels();
$statusLabels = [
    'uploaded'=>'بارگذاری‌شده','detected'=>'منبع شناسایی‌شده','staged'=>'در staging','validation_failed'=>'نیازمند اصلاح',
    'ready_to_commit'=>'آماده ثبت','committed'=>'ثبت‌شده','activated'=>'فعال','rejected'=>'ردشده','rolled_back'=>'بازگردانی‌شده',
];
$rowStatusLabels = ['valid'=>'معتبر','invalid'=>'خطادار','duplicate'=>'تکراری','committed'=>'ثبت‌شده','skipped'=>'ردشده','rejected'=>'بازگردانی‌شده'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) throw new DomainException('اعتبار فرم منقضی شده است.');
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'upload') {
            if (!$canUpload) throw new DomainException('مجوز بارگذاری فایل را ندارید.');
            $result = UnifiedImportService::upload(
                $_FILES['import_file'] ?? [],
                (string)($_POST['source_hint'] ?? ''),
                (string)($_POST['import_mode'] ?? ''),
                $actorId,
                [
                    'period_key' => $_POST['period_key'] ?? '',
                    'snapshot_date' => $_POST['snapshot_date'] ?? '',
                    'period_id' => $_POST['period_id'] ?? 0,
                ]
            );
            flash($result['needs_selection'] ? 'چند منبع نزدیک پیدا شد؛ جدول صحیح را انتخاب کنید.' : 'فایل تشخیص داده شد و ردیف‌ها در staging اعتبارسنجی شدند.');
            redirect('/admin/import-center.php?batch='.(int)$result['batch_id']);
        }
        $batchId = max(0, (int)($_POST['batch_id'] ?? 0));
        if ($action === 'select_source') {
            if (!$canUpload) throw new DomainException('مجوز انتخاب منبع را ندارید.');
            UnifiedImportService::selectCandidate($batchId, (string)($_POST['candidate_key'] ?? ''), $actorId, $isAdmin);
            flash('جدول انتخاب شد و اعتبارسنجی ردیف‌ها انجام گرفت.');
        } elseif ($action === 'commit') {
            if (!$canCommit) throw new DomainException('مجوز تایید و فعال‌سازی Batch را ندارید.');
            $result = UnifiedImportService::commit($batchId, $actorId, $isAdmin);
            flash('Batch فعال شد: '.$result['inserted'].' جدید، '.$result['updated'].' بروزرسانی و '.$result['skipped'].' ردشده.');
        } elseif ($action === 'rollback') {
            if (!$canUpload) throw new DomainException('مجوز بازگردانی Batch را ندارید.');
            UnifiedImportService::rollback($batchId, $actorId, $isAdmin);
            flash('Batch بدون حذف داده‌های قبلی به وضعیت بازگردانی‌شده رفت.');
        } elseif ($action === 'retry') {
            if (!$canUpload) throw new DomainException('مجوز retry را ندارید.');
            $result = UnifiedImportService::retry($batchId, $actorId, $isAdmin);
            flash('تلاش مجدد در یک Batch مستقل ایجاد شد.');
            redirect('/admin/import-center.php?batch='.(int)$result['batch_id']);
        } elseif ($action === 'map_attendance_identity') {
            $result = UnifiedImportService::mapAttendanceIdentity(
                $batchId,
                max(0,(int)($_POST['staging_id']??0)),
                max(0,(int)($_POST['user_id']??0)),
                $actorId,
                $isAdmin
            );
            flash($result['status']==='valid'?'نگاشت ذخیره شد و ردیف آماده ثبت است.':'نگاشت ذخیره شد؛ خطاهای دیگر ردیف را بررسی کنید.');
        } else {
            throw new InvalidArgumentException('عملیات درخواست‌شده معتبر نیست.');
        }
        redirect('/admin/import-center.php?batch='.$batchId);
    } catch (InvalidArgumentException|DomainException $e) {
        flash($e->getMessage(), 'danger');
    } catch (Throwable $e) {
        error_log('Import center: '.$e->getMessage());
        flash('پردازش فایل انجام نشد. جزئیات فنی در لاگ ثبت شد.', 'danger');
    }
    redirect('/admin/import-center.php'.(!empty($_POST['batch_id']) ? '?batch='.(int)$_POST['batch_id'] : ''));
}

$sourceHint = (string)($_GET['source'] ?? '');
if (!isset($labels[$sourceHint])) $sourceHint = '';
$batchId = max(0, (int)($_GET['batch'] ?? 0));
$batch = $batchId ? UnifiedImportService::batchForActor($batchId, $actorId, $isAdmin) : null;
$summary = $batch ? UnifiedImportService::summary($batchId) : null;
$metadata = $batch ? (json_decode((string)$batch['metadata_json'], true) ?: []) : [];
$preview = $batch ? Database::fetchAll('SELECT id,`row_number`,validation_status,validation_errors_json,normalized_json FROM staging_sales_data WHERE import_batch_id=? ORDER BY `row_number`,id LIMIT 20', [$batchId]) : [];
$canMapAttendance = $batch
    && ($batch['source_module']??'')==='attendance'
    && (Auth::isAdmin()||Auth::can('hr_attendance','create')||Auth::can('hr_attendance','edit')||Auth::can('hr_attendance.settings','edit'));
$attendanceMappingUsers = $canMapAttendance ? UnifiedImportService::attendanceMappingUsers() : [];
$recent = ($isAdmin || $canView)
    ? Database::fetchAll('SELECT id,source_module,file_name,pipeline_status,total_rows,valid_rows,invalid_rows,created_at FROM sales_import_batches ORDER BY id DESC LIMIT 12')
    : Database::fetchAll('SELECT id,source_module,file_name,pipeline_status,total_rows,valid_rows,invalid_rows,created_at FROM sales_import_batches WHERE started_by=? ORDER BY id DESC LIMIT 12', [$actorId]);
$limits = ImportSettings::serverLimits();
$pageTitle = 'مرکز یکپارچه ورود اطلاعات';
$adminExtraStylesheets = ['/assets/css/import-center.css'];
$adminExtraScripts = ['/assets/js/import-center.js'];
require __DIR__ . '/../views/partials/admin-header.php';
?>

<div class="import-page">
    <header class="import-hero">
        <div>
            <span class="import-eyebrow">Data Import Center</span>
            <h1><?= e($pageTitle) ?></h1>
            <p>تشخیص خودکار Table و Sheet، پیش‌نمایش، staging، خطاهای ردیفی و فعال‌سازی امن Batch در یک مسیر.</p>
        </div>
        <div class="import-limit">
            <span>سقف برنامه</span>
            <strong><?= e((string)ImportSettings::excelUploadMb()) ?> MB</strong>
            <small><?= ImportSettings::applicationExceedsServer() ? 'محدودیت PHP کمتر از تنظیم برنامه است' : 'با محدودیت سرور سازگار است' ?></small>
        </div>
    </header>

    <nav class="import-source-tabs" aria-label="نوع ورود اطلاعات">
        <a class="<?= $sourceHint===''?'is-active':'' ?>" href="/admin/import-center.php">تشخیص خودکار</a>
        <?php foreach ($labels as $key => $title): ?>
            <a class="<?= $sourceHint===$key?'is-active':'' ?>" href="/admin/import-center.php?source=<?= e($key) ?>"><?= e($title) ?></a>
        <?php endforeach; ?>
    </nav>

    <section class="import-workspace">
        <article class="card import-upload-card">
            <div class="import-section-title">
                <div><span>مرحله ۱</span><h2>بارگذاری و تشخیص</h2></div>
                <div class="actions">
                    <a class="btn btn-light" data-import-template-link href="/admin/import-template.php?source=<?=e($sourceHint ?: 'all')?>">دانلود قالب <?= $sourceHint !== '' ? e($labels[$sourceHint]) : 'همه منابع مجاز' ?></a>
                    <span class="import-auto-badge">Auto mapping</span>
                </div>
            </div>
            <form method="post" enctype="multipart/form-data" class="admin-form import-upload-form" data-import-form>
                <input type="hidden" name="csrf_token" value="<?= e(Auth::csrfToken()) ?>">
                <input type="hidden" name="action" value="upload">
                <label class="import-dropzone">
                    <input type="file" name="import_file" accept=".xlsx,.csv" required>
                    <strong>فایل Excel یا CSV را انتخاب کنید</strong>
                    <span>نام فایل برای تشخیص استفاده نمی‌شود؛ Table، Sheet و امضای سرستون ملاک است.</span>
                </label>
                <div class="grid grid-2">
                    <label><span>نوع منبع</span><select name="source_hint" data-source-select><option value="">تشخیص خودکار</option><?php foreach ($labels as $key=>$title): ?><option value="<?=e($key)?>" <?=$sourceHint===$key?'selected':''?>><?=e($title)?></option><?php endforeach; ?></select></label>
                    <label><span>روش ورود</span><select name="import_mode"><option value="replace_reference">مرجع جدید و فعال‌سازی پس از تایید</option><option value="update_existing">بروزرسانی کلیدهای موجود</option><option value="skip_duplicates">نادیده‌گرفتن تکراری‌ها</option><option value="fail_on_duplicate">توقف در صورت تکراری</option></select></label>
                    <label><span>کلید دوره</span><input name="period_key" maxlength="50" placeholder="مثلاً 1405-04"></label>
                    <label data-snapshot-field><span>تاریخ snapshot / کارکرد</span><input name="snapshot_date" class="jalali-date-input" placeholder="1405/04/25"></label>
                    <label data-period-field><span>شناسه دوره</span><input type="number" min="1" name="period_id"></label>
                </div>
                <button class="btn btn-primary" <?= !$canUpload?'disabled':'' ?>>بررسی فایل و ساخت پیش‌نمایش</button>
            </form>
            <?php if (ImportSettings::applicationExceedsServer()): ?><div class="alert alert-warning">تنظیم <?=e((string)ImportSettings::excelUploadMb())?> مگابایت از سقف مؤثر PHP بیشتر است؛ ابتدا تنظیم سرور اصلاح شود.</div><?php endif; ?>
            <details class="import-server-limits"><summary>مقادیر مؤثر PHP / Server</summary><dl><?php foreach ($limits as $key=>$value): ?><div><dt><?=e($key)?></dt><dd><?=e($value)?></dd></div><?php endforeach; ?></dl></details>
        </article>

        <aside class="card import-flow-card">
            <div class="import-section-title"><div><span>مسیر کنترل‌شده</span><h2>وضعیت Pipeline</h2></div></div>
            <?php foreach (['بارگذاری','بررسی فایل','تشخیص منبع','نرمال‌سازی','Staging','اعتبارسنجی','ثبت نهایی','فعال‌سازی'] as $index=>$step): ?>
                <div class="import-flow-step"><b><?= e((string)($index+1)) ?></b><span><?= e($step) ?></span></div>
            <?php endforeach; ?>
        </aside>
    </section>

    <?php if ($batch): ?>
        <section class="card import-batch-card">
            <div class="import-section-title">
                <div><span>Batch #<?=e((string)$batch['id'])?></span><h2><?=e($labels[$batch['source_module']] ?? $batch['source_module'])?></h2></div>
                <span class="import-status status-<?=e((string)$batch['pipeline_status'])?>"><?=e($statusLabels[$batch['pipeline_status']] ?? (string)$batch['pipeline_status'])?></span>
            </div>
            <div class="import-meta-grid">
                <span>فایل <strong><?=e((string)$batch['file_name'])?></strong></span>
                <span>Sheet <strong><?=e((string)($batch['detected_sheet'] ?: '—'))?></strong></span>
                <span>Table <strong><?=e((string)($batch['detected_table'] ?: '—'))?></strong></span>
                <span>Confidence <strong><?=e((string)($batch['source_confidence'] ?: '—'))?>%</strong></span>
                <span>Snapshot <strong><?=e((string)($batch['snapshot_date'] ?: '—'))?></strong></span>
            </div>

            <?php if (($batch['pipeline_status'] ?? '') === 'detected' && !empty($metadata['candidates'])): ?>
                <div class="alert alert-info">تشخیص قطعی نیست. فقط Table/محدوده صحیح را انتخاب کنید؛ جداول تحلیلی نامرتبط وارد نمی‌شوند.</div>
                <form method="post" class="import-candidate-list">
                    <input type="hidden" name="csrf_token" value="<?=e(Auth::csrfToken())?>"><input type="hidden" name="action" value="select_source"><input type="hidden" name="batch_id" value="<?=(int)$batch['id']?>">
                    <?php foreach ($metadata['candidates'] as $index=>$candidate): ?><label><input type="radio" name="candidate_key" value="<?=e($candidate['key'])?>" <?=$index===0?'checked':''?>><span><strong><?=e($candidate['source_title']??$candidate['source_module'])?></strong><small><?=e($candidate['sheet_name'])?><?=!empty($candidate['table_name'])?' / '.e($candidate['table_name']):''?> — اطمینان <?=e((string)$candidate['confidence'])?>%</small></span></label><?php endforeach; ?>
                    <button class="btn btn-primary">انتخاب و staging</button>
                </form>
            <?php endif; ?>

            <?php if ($summary): ?><div class="import-summary-grid"><?php foreach (['total_rows'=>'کل ردیف','valid_rows'=>'معتبر','invalid_rows'=>'خطادار','duplicate_rows'=>'تکراری','ready_rows'=>'آماده ثبت'] as $key=>$label): ?><article><span><?=e($label)?></span><strong><?=e((string)$summary[$key])?></strong></article><?php endforeach; ?></div><?php endif; ?>

            <?php if (!empty($metadata['mapping_required'])): ?>
                <div class="alert alert-warning">سرستون‌های الزامی تطبیق داده نشد: <?=e(implode('، ', $metadata['missing_required'] ?? []))?>.</div>
                <?php if (Auth::isSuperAdmin()): ?><a class="btn btn-light" href="/admin/sales-data-mapping.php?source=<?=e((string)$batch['source_module'])?>&batch=<?=(int)$batch['id']?>">ابزار پیشرفته نگاشت ستون‌ها</a><?php endif; ?>
            <?php endif; ?>
            <?php if (!empty($metadata['context_required'])): ?><div class="alert alert-warning">اطلاعات زمینه‌ای الزامی ثبت نشده است: <?=e(implode('، ', $metadata['missing_context'] ?? []))?>. فایل را با این مقادیر دوباره بارگذاری کنید.</div><?php endif; ?>

            <?php if ($preview): ?>
                <div class="import-preview">
                    <h3>پیش‌نمایش ۲۰ ردیف نخست</h3>
                    <div class="table-responsive"><table><thead><tr><th>ردیف</th><th>وضعیت</th><th>خلاصه داده نرمال‌شده</th><th>خطا</th><?php if($canMapAttendance):?><th>نگاشت هویت</th><?php endif?></tr></thead><tbody>
                    <?php foreach ($preview as $row): $data=json_decode((string)$row['normalized_json'],true)?:[];$errors=json_decode((string)$row['validation_errors_json'],true)?:[]; ?>
                        <?php $errorCodes=array_column($errors,'code');$identityUnresolved=(bool)array_intersect(['employee_not_found','attendance_identity_required'],$errorCodes); ?>
                        <tr><td><?=e((string)$row['row_number'])?></td><td><span class="import-row-status"><?=e($rowStatusLabels[$row['validation_status']] ?? (string)$row['validation_status'])?></span></td><td><?=e(implode(' | ', array_slice(array_map(static fn($k,$v)=>$k.': '.$v,array_keys($data),array_values($data)),0,5)))?></td><td><?=e(implode('؛ ',array_column($errors,'message'))?:'—')?></td><?php if($canMapAttendance):?><td><?php $externalIdentity=(string)(($data['kara_system_code']??'')?:($data['employee_no']??''));if($identityUnresolved&&$externalIdentity!==''):?><form method="post" class="import-identity-map"><input type="hidden" name="csrf_token" value="<?=e(Auth::csrfToken())?>"><input type="hidden" name="action" value="map_attendance_identity"><input type="hidden" name="batch_id" value="<?=(int)$batch['id']?>"><input type="hidden" name="staging_id" value="<?=(int)$row['id']?>"><small><?=e($externalIdentity)?></small><select name="user_id" required><option value="">انتخاب کاربر موجود</option><?php foreach($attendanceMappingUsers as $mapUser):?><option value="<?=(int)$mapUser['id']?>"><?=e($mapUser['name'].' · '.(($mapUser['kara_system_code']??'')?:($mapUser['employee_no']??'')?:'بدون کد'))?></option><?php endforeach?></select><button class="btn btn-light btn-sm">ثبت نگاشت</button></form><?php else:?>—<?php endif?></td><?php endif?></tr>
                    <?php endforeach; ?></tbody></table></div>
                </div>
            <?php endif; ?>

            <div class="form-actions">
                <?php if (($batch['pipeline_status']??'')==='ready_to_commit' && $canCommit): ?><form method="post"><input type="hidden" name="csrf_token" value="<?=e(Auth::csrfToken())?>"><input type="hidden" name="action" value="commit"><input type="hidden" name="batch_id" value="<?=(int)$batch['id']?>"><button class="btn btn-primary">Commit و فعال‌سازی Batch</button></form><?php endif; ?>
                <?php if (!in_array($batch['pipeline_status']??'', ['activated','committed','rolled_back'], true)): ?><form method="post"><input type="hidden" name="csrf_token" value="<?=e(Auth::csrfToken())?>"><input type="hidden" name="action" value="rollback"><input type="hidden" name="batch_id" value="<?=(int)$batch['id']?>"><button class="btn btn-light">بازگردانی Batch</button></form><?php endif; ?>
                <?php if (in_array($batch['pipeline_status']??'', ['validation_failed','rejected','rolled_back'], true)): ?><form method="post"><input type="hidden" name="csrf_token" value="<?=e(Auth::csrfToken())?>"><input type="hidden" name="action" value="retry"><input type="hidden" name="batch_id" value="<?=(int)$batch['id']?>"><button class="btn btn-light">Retry در Batch جدید</button></form><?php endif; ?>
                <a class="btn btn-light" href="/admin/import-errors.php?batch=<?=(int)$batch['id']?>">خطاهای این Batch</a>
            </div>
        </section>
    <?php endif; ?>

    <section class="card">
        <div class="import-section-title"><div><span>آخرین فعالیت‌ها</span><h2>تاریخچه ورود اطلاعات</h2></div><a class="btn btn-light" href="/admin/import-history.php">مشاهده همه</a></div>
        <div class="table-responsive"><table><thead><tr><th>Batch</th><th>منبع</th><th>فایل</th><th>وضعیت</th><th>معتبر / خطادار</th><th>تاریخ</th></tr></thead><tbody>
        <?php foreach ($recent as $row): ?><tr><td><a href="/admin/import-center.php?batch=<?=(int)$row['id']?>">#<?=e((string)$row['id'])?></a></td><td><?=e($labels[$row['source_module']]??$row['source_module'])?></td><td><?=e((string)$row['file_name'])?></td><td><?=e($statusLabels[$row['pipeline_status']]??(string)$row['pipeline_status'])?></td><td><?=e((string)$row['valid_rows'])?> / <?=e((string)$row['invalid_rows'])?></td><td><?=e((string)$row['created_at'])?></td></tr><?php endforeach; ?>
        </tbody></table></div>
    </section>
</div>

<?php require __DIR__ . '/../views/partials/admin-footer.php'; ?>
