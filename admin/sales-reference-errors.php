<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/SalesReferenceRepository.php';
require_once __DIR__ . '/../lib/ImportSourceRegistry.php';

Auth::requireLogin();
if (!Auth::can('sales_reference_view_errors') && !Auth::can('sales_data_view_errors')) {
    http_response_code(403);
    exit('دسترسی غیرمجاز');
}

$pageTitle = 'خطاهای ورود اطلاعات';
$batchId = max(0, (int)($_GET['batch'] ?? 0));
$rows = SalesReferenceRepository::recentErrors(150, $batchId);
require __DIR__ . '/../views/partials/admin-header.php';
?>
<section class="card">
    <div class="section-heading-row">
        <div><h1><?= e($pageTitle) ?></h1><?php if ($batchId): ?><p class="muted">خطاهای Batch شماره <?= e((string)$batchId) ?></p><?php endif; ?></div>
        <a class="btn btn-light" href="/admin/import-history.php">تاریخچه ورود اطلاعات</a>
    </div>
    <?php if (!$rows): ?>
        <div class="alert alert-info">خطایی برای نمایش ثبت نشده است.</div>
    <?php else: ?>
        <div class="table-responsive"><table><thead><tr><th>Batch</th><th>منبع</th><th>ردیف</th><th>کد خطا</th><th>پیام</th><th>تاریخ</th></tr></thead><tbody>
        <?php foreach ($rows as $row): ?><tr>
            <td><?= e((string)$row['import_batch_id']) ?></td>
            <td><?= e(ImportSourceRegistry::labels()[$row['source_module']] ?? $row['source_module']) ?></td>
            <td><?= e((string)($row['row_number'] ?? '—')) ?></td>
            <td><?= e((string)($row['error_code'] ?? '—')) ?></td>
            <td><?= e((string)$row['error_message']) ?></td>
            <td><?= e((string)$row['created_at']) ?></td>
        </tr><?php endforeach; ?>
        </tbody></table></div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/../views/partials/admin-footer.php'; ?>
