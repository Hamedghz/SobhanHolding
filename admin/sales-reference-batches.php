<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/SalesReferenceRepository.php';

Auth::requireLogin();
if (!Auth::can('sales_reference_view_batches') && !Auth::can('sales_data_view')) {
    http_response_code(403);
    exit('دسترسی غیرمجاز');
}

$pageTitle = 'تاریخچه ورود اطلاعات مرجع';
$rows = SalesReferenceRepository::recentBatches();
require __DIR__ . '/../views/partials/admin-header.php';
?>
<section class="card">
    <div class="section-heading-row">
        <div><h1><?= e($pageTitle) ?></h1><p class="muted">تاریخچه Batchهای مرجع فروش تجمیعی و موجودی انبار.</p></div>
        <a class="btn btn-light" href="/admin/sales-reference-status.php">وضعیت دیتای مرجع سایت</a>
    </div>
    <?php if (!$rows): ?>
        <div class="alert alert-info">هنوز هیچ Batch مرجع ثبت نشده است.</div>
    <?php else: ?>
        <div class="table-responsive"><table><thead><tr><th>شناسه</th><th>منبع</th><th>دوره</th><th>وضعیت</th><th>فعال</th><th>کل</th><th>معتبر</th><th>نامعتبر</th><th>تکراری</th><th>تاریخ</th></tr></thead><tbody>
        <?php foreach ($rows as $row): ?><tr>
            <td><?= e((string)$row['id']) ?></td>
            <td><?= e($row['source_module'] === 'inventory_aggregate' ? 'موجودی انبار' : 'فروش تجمیعی') ?></td>
            <td><?= e((string)($row['period_key'] ?: '—')) ?></td>
            <td><?= e((string)$row['status']) ?></td>
            <td><?= (int)$row['is_active_reference'] === 1 ? 'بله' : '—' ?></td>
            <td><?= e((string)$row['total_rows']) ?></td>
            <td><?= e((string)$row['valid_rows']) ?></td>
            <td><?= e((string)$row['invalid_rows']) ?></td>
            <td><?= e((string)$row['duplicate_rows']) ?></td>
            <td><?= e((string)$row['created_at']) ?></td>
        </tr><?php endforeach; ?>
        </tbody></table></div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/../views/partials/admin-footer.php'; ?>
