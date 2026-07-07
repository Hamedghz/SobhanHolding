<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/SalesDataRepository.php';

Auth::requirePermission('sales_data_view_errors');
$pageTitle = 'خطاهای ورود اطلاعات فروش';
$batchId = max(0, (int)($_GET['batch'] ?? 0));
$rows = SalesDataRepository::recentErrors(100, $batchId);
require __DIR__ . '/../views/partials/admin-header.php';
?>
<section class="card">
    <h1><?= e($pageTitle) ?></h1>
    <?php if ($batchId): ?><p class="muted">نمایش خطاهای Batch شماره <?=e((string)$batchId)?></p><?php endif; ?>
    <?php if (!$rows): ?>
        <div class="alert alert-info">خطایی برای نمایش ثبت نشده است.</div>
    <?php else: ?>
        <div class="table-responsive"><table><thead><tr><th>Batch</th><th>منبع</th><th>ردیف</th><th>کد خطا</th><th>پیام</th><th>تاریخ</th></tr></thead><tbody>
        <?php foreach ($rows as $row): ?><tr>
            <td><?= e((string)$row['import_batch_id']) ?></td><td><?= e($row['source_module']) ?></td><td><?= e((string)($row['row_number'] ?? '—')) ?></td>
            <td><?= e((string)($row['error_code'] ?? '—')) ?></td><td><?= e($row['error_message']) ?></td><td><?= e((string)$row['created_at']) ?></td>
        </tr><?php endforeach; ?>
        </tbody></table></div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/../views/partials/admin-footer.php'; ?>
