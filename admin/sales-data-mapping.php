<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/SalesDataRepository.php';

Auth::requirePermission('sales_data_manage_mapping');
$pageTitle = 'نگاشت ستون‌های داده فروش';
$rows = SalesDataRepository::mappings();
require __DIR__ . '/../views/partials/admin-header.php';
?>
<section class="card">
    <h1><?= e($pageTitle) ?></h1>
    <p class="muted">ویرایش نگاشت‌ها در مرحله پردازش ورودی افزوده خواهد شد.</p>
    <?php if (!$rows): ?>
        <div class="alert alert-info">هنوز نگاشت ستونی ثبت نشده است.</div>
    <?php else: ?>
        <div class="table-responsive"><table><thead><tr><th>منبع</th><th>سرستون ورودی</th><th>کلید استاندارد</th><th>نوع داده</th><th>الزامی</th><th>فعال</th></tr></thead><tbody>
        <?php foreach ($rows as $row): ?><tr>
            <td><?= e($row['source_module']) ?></td><td><?= e($row['source_header']) ?></td><td><?= e($row['normalized_key']) ?></td><td><?= e($row['data_type']) ?></td>
            <td><?= (int)$row['required'] === 1 ? 'بله' : 'خیر' ?></td><td><?= (int)$row['active'] === 1 ? 'بله' : 'خیر' ?></td>
        </tr><?php endforeach; ?>
        </tbody></table></div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/../views/partials/admin-footer.php'; ?>
