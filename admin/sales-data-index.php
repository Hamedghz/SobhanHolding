<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Response.php';

$sources = [
    'sales_aggregate' => ['ورود اطلاعات فروش تجمیعی', 'منبع تجمیعی / tbltajmi'],
    'inventory' => ['آپدیت موجودی انبار', 'منبع tblanbar'],
    'sales_team' => ['ورود اطلاعات ویزیتورها', 'منبع ویزیتور / tblvizit'],
    'customer_coefficients' => ['ورود ضرایب صنف', 'منبع zarib / tblzarib'],
    'product_priorities' => ['ورود اولویت کالا', 'منبع olaviyat / tblolaviyat'],
    'sales_targets' => ['ورود تارگت فروش', 'منبع target / tbltargrt'],
];
$source = (string)($_GET['source'] ?? '');
$section = (string)($_GET['section'] ?? '');

if (isset($sources[$source])) {
    Auth::requirePermission('sales_data_import');
    [$pageTitle, $sourceHint] = $sources[$source];
    $description = 'زیرساخت امن این منبع آماده است. خواندن و اعتبارسنجی فایل در مرحله بعد پیاده‌سازی می‌شود.';
} elseif ($section === 'ai') {
    Auth::requirePermission('sales_data_sync_ai');
    $pageTitle = 'وضعیت اتصال SobhanAI';
    $sourceHint = 'همگام‌سازی در این مرحله فعال نشده است.';
    $description = 'این صفحه فقط جایگاه امن اتصال آینده است و هیچ داده‌ای ارسال یا دریافت نمی‌کند.';
} elseif ($section === 'views') {
    Auth::requirePermission('sales_data_view_reports');
    $pageTitle = 'Viewهای گزارش‌گیری';
    $sourceHint = 'View گزارش‌گیری در این مرحله ساخته نشده است.';
    $description = 'Viewها پس از تثبیت قرارداد داده و قواعد گزارش‌گیری افزوده خواهند شد.';
} else {
    Auth::requirePermission('sales_data_view');
    $pageTitle = 'مدیریت داده فروش';
    $sourceHint = 'زیرساخت پایه آماده است.';
    $description = 'برای مشاهده تاریخچه، خطاها یا نگاشت ستون‌ها از منوی مدیریت داده فروش استفاده کنید.';
}

require __DIR__ . '/../views/partials/admin-header.php';
?>
<section class="card">
    <h1><?= e($pageTitle) ?></h1>
    <p><?= e($description) ?></p>
    <div class="alert alert-info"><?= e($sourceHint) ?></div>
    <p class="muted">در این مرحله هیچ فایل Excel/CSV پردازش نمی‌شود و اتصال SobhanAI نیز غیرفعال است.</p>
</section>
<?php require __DIR__ . '/../views/partials/admin-footer.php'; ?>
