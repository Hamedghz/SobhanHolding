<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/SalesAggregateImportService.php';

Auth::requireLogin();
if (!Auth::can('sales_reference_upload') && !Auth::can('sales_data_import')) {
    http_response_code(403);
    exit('دسترسی غیرمجاز');
}
$user = Auth::user();
$actorId = (int)$user['id'];
$isAdmin = Auth::isAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) throw new DomainException('اعتبار فرم منقضی شده است.');
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'upload') {
            $result = SalesAggregateImportService::readUploadedFile($_FILES['sales_file'] ?? [], (string)($_POST['import_mode'] ?? ''), $actorId, (string)($_POST['period_key'] ?? ''));
            flash($result['needs_selection'] ? 'چند منبع معتبر پیدا شد؛ منبع موردنظر را انتخاب کنید.' : 'فایل بررسی شد؛ خلاصه را پیش از تایید نهایی کنترل کنید.');
            redirect('/admin/sales-aggregate-import.php?batch='.(int)$result['batch_id']);
        }
        $batchId = (int)($_POST['batch_id'] ?? 0);
        if ($action === 'select_source') {
            SalesAggregateImportService::selectCandidate($batchId,(string)($_POST['candidate_key'] ?? ''),$actorId,$isAdmin);
            flash('منبع انتخاب و ردیف‌ها در staging اعتبارسنجی شدند.');
        } elseif ($action === 'commit') {
            if (!Auth::can('sales_reference_commit') && !Auth::can('sales_data_import')) throw new DomainException('مجوز تایید اطلاعات مرجع را ندارید.');
            $result = SalesAggregateImportService::commitValidRows($batchId,$actorId,$isAdmin);
            flash('Batch تایید و به عنوان مرجع محاسبات فعال شد: '.$result['imported'].' جدید، '.$result['updated'].' بروزرسانی و '.$result['skipped'].' تکراری نادیده گرفته شد.');
        } elseif ($action === 'rollback') {
            SalesAggregateImportService::rollbackBatch($batchId,$actorId,$isAdmin);
            flash('Batch لغو شد و هیچ داده جدیدی وارد جدول نهایی نشد.');
        } else throw new InvalidArgumentException('عملیات درخواست‌شده معتبر نیست.');
        redirect('/admin/sales-aggregate-import.php?batch='.$batchId);
    } catch (InvalidArgumentException|DomainException $e) {
        flash($e->getMessage(),'danger');
    } catch (Throwable $e) {
        error_log('Sales aggregate import page: '.$e->getMessage());
        flash('پردازش فایل فروش انجام نشد. لطفاً فایل و تنظیمات را بررسی کنید.','danger');
    }
    $target = !empty($_POST['batch_id']) ? '?batch='.(int)$_POST['batch_id'] : '';
    redirect('/admin/sales-aggregate-import.php'.$target);
}

$batchId = (int)($_GET['batch'] ?? 0);
$batch = $batchId ? SalesAggregateRepository::batchForActor($batchId,$actorId,$isAdmin) : null;
$summary = $batch && in_array($batch['status'],['preview','completed','committed'],true) ? SalesAggregateImportService::generateImportSummary($batchId) : null;
$metadata = $batch ? (json_decode((string)$batch['metadata_json'],true) ?: []) : [];
$pageTitle = 'ورود اطلاعات فروش تجمیعی';
require __DIR__ . '/../views/partials/admin-header.php';
?>
<div class="section-heading-row"><div><h1><?=e($pageTitle)?></h1><p class="muted">این فایل منبع مرجع محاسبات فروش، گزارش‌ها و داشبوردهای مرحله‌های بعدی می‌شود.</p></div><a class="btn btn-light" href="/admin/sales-reference-status.php">وضعیت دیتای مرجع</a></div>

<section class="card">
    <h2>بارگذاری فایل</h2>
    <p><span class="reference-badge">فروش تجمیعی</span> فایل XLSX یا CSV با UTF-8 پذیرفته می‌شود. نام فایل در تشخیص منبع نقشی ندارد و فرمول‌های Excel اجرا نمی‌شوند.</p>
    <form method="post" enctype="multipart/form-data" class="admin-form">
        <input type="hidden" name="csrf_token" value="<?=e(Auth::csrfToken())?>"><input type="hidden" name="action" value="upload">
        <label><span>فایل فروش تجمیعی</span><input type="file" name="sales_file" accept=".xlsx,.csv" required></label>
        <label><span>دوره گزارش / ماه</span><input type="text" name="period_key" maxlength="50" placeholder="مثلاً 1405-03 یا system_current"></label>
        <label><span>Import mode</span><select name="import_mode">
            <option value="replace_reference">جایگزینی مرجع فعلی</option><option value="append">افزودن به داده‌های فعلی</option><option value="update_existing">بروزرسانی رکوردهای موجود</option><option value="skip_duplicates">رد کردن تکراری‌ها</option>
        </select></label>
        <button class="btn btn-primary" type="submit">بارگذاری و بررسی فایل</button>
    </form>
</section>

<?php if ($batch): ?>
<section class="card">
    <h2>وضعیت Batch شماره <?=e((string)$batch['id'])?></h2>
    <div class="sales-detection"><span>وضعیت: <strong><?=e($batch['status'])?></strong></span><span>دوره: <strong><?=e((string)($batch['period_key'] ?: '—'))?></strong></span><span>فایل: <strong><?=e((string)$batch['file_name'])?></strong></span><span>شیت: <strong><?=e((string)($batch['detected_sheet'] ?: '—'))?></strong></span><span>Table: <strong><?=e((string)($batch['detected_table'] ?: '—'))?></strong></span></div>

    <?php if ($batch['status'] === 'awaiting_source_selection'): ?>
        <div class="alert alert-info">چند منبع معتبر پیدا شد. یک مورد را برای staging انتخاب کنید.</div>
        <form method="post" class="admin-form"><input type="hidden" name="csrf_token" value="<?=e(Auth::csrfToken())?>"><input type="hidden" name="action" value="select_source"><input type="hidden" name="batch_id" value="<?=(int)$batch['id']?>">
        <?php foreach (($metadata['candidates'] ?? []) as $index=>$candidate): ?>
            <label class="sales-source-choice"><input type="radio" name="candidate_key" value="<?=e($candidate['key'])?>" <?=$index===0?'checked':''?>>
                <span>شیت <?=e($candidate['sheet_name'])?><?=!empty($candidate['table_name'])?' — Table '.e($candidate['table_name']):''?> (<?=e($candidate['detection'])?>)</span></label>
        <?php endforeach; ?><button class="btn btn-primary" type="submit">انتخاب منبع و بررسی</button></form>
    <?php endif; ?>

    <?php if ($summary): ?>
        <div class="sales-summary-grid">
            <?php foreach (['total_rows'=>'کل ردیف‌ها','valid_rows'=>'معتبر','invalid_rows'=>'نامعتبر','duplicate_rows'=>'تکراری','ready_rows'=>'آماده ورود'] as $key=>$label): ?>
                <article><span><?=e($label)?></span><strong><?=e((string)$summary[$key])?></strong></article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="form-actions">
        <?php if ($batch['status'] === 'preview'): ?><form method="post"><input type="hidden" name="csrf_token" value="<?=e(Auth::csrfToken())?>"><input type="hidden" name="action" value="commit"><input type="hidden" name="batch_id" value="<?=(int)$batch['id']?>"><button class="btn btn-primary">تایید و فعال‌سازی به عنوان مرجع محاسبات</button></form><?php endif; ?>
        <?php if (!in_array($batch['status'],['completed','committed','cancelled'],true)): ?><form method="post"><input type="hidden" name="csrf_token" value="<?=e(Auth::csrfToken())?>"><input type="hidden" name="action" value="rollback"><input type="hidden" name="batch_id" value="<?=(int)$batch['id']?>"><button class="btn btn-light">لغو Batch</button></form><?php endif; ?>
        <?php if (Auth::can('sales_reference_view_errors') || Auth::can('sales_data_view_errors')): ?><a class="btn btn-light" href="/admin/sales-reference-errors.php?batch=<?=(int)$batch['id']?>">مشاهده خطاها</a><?php endif; ?>
        <a class="btn btn-light" href="/admin/sales-reference-batches.php">تاریخچه ورود اطلاعات مرجع</a>
    </div>
</section>
<?php endif; ?>
<style>.reference-badge{display:inline-block;padding:4px 10px;border-radius:8px;background:#e8f5f1;color:#0f6b57;margin-left:8px}.sales-summary-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:12px;margin:18px 0}.sales-summary-grid article{border:1px solid var(--border,#dfe5e7);border-radius:8px;padding:14px;background:var(--surface,#fff)}.sales-summary-grid span{display:block;color:var(--muted,#64748b);margin-bottom:6px}.sales-summary-grid strong{font-size:1.45rem}.sales-detection{display:flex;gap:18px;flex-wrap:wrap;margin:12px 0}.sales-source-choice{display:flex!important;gap:8px;align-items:center;padding:10px;border:1px solid var(--border,#ddd);border-radius:8px}.form-actions form{display:inline-block}</style>
<?php require __DIR__ . '/../views/partials/admin-footer.php'; ?>
