<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/SalesReferenceRepository.php';

Auth::requireLogin();
if (!Auth::can('sales_reference_view_status') && !Auth::can('sales_data_view')) {
    http_response_code(403);
    exit('دسترسی غیرمجاز');
}

$pageTitle = 'وضعیت دیتای مرجع سایت';
$status = SalesReferenceRepository::statusSummary();
require __DIR__ . '/../views/partials/admin-header.php';

function reference_card(string $title, ?array $batch, string $missing): void
{
    ?>
    <article class="reference-status-card">
        <h2><?= e($title) ?></h2>
        <?php if (!$batch): ?>
            <div class="alert alert-warning"><?= e($missing) ?></div>
        <?php else: ?>
            <dl>
                <div><dt>Batch فعال</dt><dd><?= e((string)$batch['id']) ?></dd></div>
                <div><dt>آخرین آپلود</dt><dd><?= e((string)($batch['created_at'] ?? '—')) ?></dd></div>
                <div><dt>آپلودکننده</dt><dd><?= e((string)($batch['started_by'] ?? '—')) ?></dd></div>
                <div><dt>دوره</dt><dd><?= e((string)($batch['period_key'] ?: '—')) ?></dd></div>
                <div><dt>تعداد ردیف</dt><dd><?= e((string)($batch['valid_rows'] ?? 0)) ?></dd></div>
                <div><dt>وضعیت سلامت</dt><dd><?= ((int)($batch['invalid_rows'] ?? 0) > 0) ? 'دارای هشدار' : 'آماده' ?></dd></div>
            </dl>
        <?php endif; ?>
    </article>
    <?php
}
?>
<section class="card">
    <div class="section-heading-row">
        <div><h1><?= e($pageTitle) ?></h1><p class="muted">Batch فعال، تنها منبع رسمی محاسبات مرجع برای گزارش‌های بعدی است.</p></div>
        <a class="btn btn-light" href="/admin/sales-reference-batches.php">تاریخچه ورود اطلاعات مرجع</a>
    </div>
    <div class="reference-status-grid">
        <?php reference_card('داده فروش تجمیعی', $status['sales_aggregate'], 'داده فروش تجمیعی هنوز فعال نشده است.'); ?>
        <?php reference_card('داده موجودی انبار', $status['inventory_aggregate'], 'داده موجودی انبار هنوز فعال نشده است.'); ?>
    </div>
</section>
<style>.reference-status-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px}.reference-status-card{border:1px solid var(--border,#dfe5e7);border-radius:8px;padding:16px}.reference-status-card dl{display:grid;gap:10px}.reference-status-card dl div{display:flex;justify-content:space-between;gap:14px;border-bottom:1px solid var(--border,#edf2f4);padding-bottom:8px}.reference-status-card dt{color:var(--muted,#64748b)}.reference-status-card dd{margin:0;font-weight:700}</style>
<?php require __DIR__ . '/../views/partials/admin-footer.php'; ?>
