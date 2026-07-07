<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/SalesDataRepository.php';

Auth::requirePermission('sales_data_view');
$pageTitle = 'تاریخچه ایمپورت‌های داده فروش';
$rows = SalesDataRepository::recentBatches();
require __DIR__ . '/../views/partials/admin-header.php';
?>
<section class="card">
    <h1><?= e($pageTitle) ?></h1>
    <?php if (!$rows): ?>
        <div class="alert alert-info">هنوز هیچ batch ورودی ثبت نشده است.</div>
    <?php else: ?>
        <div class="table-responsive"><table><thead><tr><th>شناسه</th><th>منبع</th><th>نوع</th><th>وضعیت</th><th>کل ردیف‌ها</th><th>معتبر</th><th>نامعتبر</th><th>تاریخ ثبت</th></tr></thead><tbody>
        <?php foreach ($rows as $row): ?><tr>
            <td><?= e((string)$row['id']) ?></td><td><?= e($row['source_module']) ?></td><td><?= e($row['source_type']) ?></td><td><?= e($row['status']) ?></td>
            <td><?= e((string)$row['total_rows']) ?></td><td><?= e((string)$row['valid_rows']) ?></td><td><?= e((string)$row['invalid_rows']) ?></td><td><?= e((string)$row['created_at']) ?></td>
        </tr><?php endforeach; ?>
        </tbody></table></div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/../views/partials/admin-footer.php'; ?>
