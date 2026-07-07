<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/SalesDataNormalizer.php';

if (PHP_SAPI !== 'cli') {
    Auth::requireLogin();
    if (!Auth::canManageSystemTools()) { http_response_code(403); exit('مجوز مدیریت سیستم لازم است.'); }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $pageTitle = 'Seed نگاشت فروش تجمیعی';
        require __DIR__ . '/../views/partials/admin-header.php';
        ?><section class="card"><h1><?=e($pageTitle)?></h1><p>سرستون‌های شناخته‌شده tbltajmi بدون حذف نگاشت‌های موجود ثبت یا بروزرسانی می‌شوند.</p><form method="post"><input type="hidden" name="csrf_token" value="<?=e(Auth::csrfToken())?>"><button class="btn btn-primary">اجرای Seed نگاشت</button></form></section><?php
        require __DIR__ . '/../views/partials/admin-footer.php'; exit;
    }
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) { http_response_code(419); exit('درخواست معتبر نیست.'); }
}

try {
    $pdo = Database::connection();
    $stmt = $pdo->prepare(
        'INSERT INTO sales_import_column_mappings
         (source_module,source_header,normalized_key,required,data_type,active,created_at,updated_at)
         VALUES ("sales_aggregate",?,?,?,?,1,NOW(),NOW())
         ON DUPLICATE KEY UPDATE normalized_key=VALUES(normalized_key),required=VALUES(required),data_type=VALUES(data_type),updated_at=NOW()'
    );
    $count = 0;
    foreach (SalesDataNormalizer::mappingDefinitions() as $mapping) {
        $stmt->execute([$mapping['source_header'],$mapping['normalized_key'],$mapping['required'],$mapping['data_type']]);
        $count++;
    }
    $message = "{$count} نگاشت فروش تجمیعی با موفقیت بررسی شد.";
    if (PHP_SAPI === 'cli') echo $message.PHP_EOL;
    else { flash($message); redirect('/admin/sales-data-mapping.php'); }
} catch (Throwable $e) {
    error_log('Sales aggregate mapping seed: '.$e->getMessage());
    $message = 'ثبت نگاشت‌های فروش تجمیعی انجام نشد.';
    if (PHP_SAPI === 'cli') { fwrite(STDERR,$message.PHP_EOL); exit(1); }
    flash($message,'danger'); redirect('/admin/install-tools.php');
}
