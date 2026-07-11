<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/SalesDataSchema.php';
require_once __DIR__ . '/../core/SalesReferenceSchema.php';
require_once __DIR__ . '/../core/SalesDataNormalizer.php';
require_once __DIR__ . '/../core/InventoryImportService.php';

if (PHP_SAPI !== 'cli') {
    Auth::requireLogin();
    if (!Auth::canManageSystemTools()) {
        http_response_code(403);
        exit('مجوز مدیریت سیستم لازم است.');
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $pageTitle = 'راه‌اندازی ورود اطلاعات مرجع فروش';
        require __DIR__ . '/../views/partials/admin-header.php';
        ?><section class="card"><h1><?= e($pageTitle) ?></h1><p>جداول، مجوزها، نگاشت‌ها و Viewهای مرجع به‌صورت افزایشی بررسی می‌شوند.</p><form method="post"><input type="hidden" name="csrf_token" value="<?= e(Auth::csrfToken()) ?>"><button class="btn btn-primary">اجرای Seed امن</button></form></section><?php
        require __DIR__ . '/../views/partials/admin-footer.php';
        exit;
    }
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
        http_response_code(419);
        exit('درخواست معتبر نیست.');
    }
}

try {
    $pdo = Database::connection();
    SalesDataSchema::repair($pdo);
    SalesReferenceSchema::repair($pdo);

    $stmt = $pdo->prepare(
        'INSERT INTO sales_import_column_mappings
         (source_module,source_header,normalized_key,required,data_type,active,created_at,updated_at)
         VALUES (?,?,?,?,?,1,NOW(),NOW())
         ON DUPLICATE KEY UPDATE normalized_key=VALUES(normalized_key),required=VALUES(required),data_type=VALUES(data_type),updated_at=NOW()'
    );
    $salesMappings = 0;
    foreach (SalesDataNormalizer::mappingDefinitions() as $mapping) {
        $stmt->execute(['sales_aggregate', $mapping['source_header'], $mapping['normalized_key'], $mapping['required'], $mapping['data_type']]);
        $salesMappings++;
    }
    $inventoryMappings = 0;
    foreach (InventoryImportService::mappingDefinitions() as $mapping) {
        $stmt->execute(['inventory_aggregate', $mapping['source_header'], $mapping['normalized_key'], $mapping['required'], $mapping['data_type']]);
        $inventoryMappings++;
    }

    $missing = [];
    $check = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
    foreach (SalesReferenceSchema::tableNames() as $table) {
        $check->execute([$table]);
        if ((int)$check->fetchColumn() === 0) $missing[] = $table;
    }
    if ($missing) throw new RuntimeException('sales_reference_tables_missing');

    $viewCount = (int)$pdo->query(
        "SELECT COUNT(*) FROM information_schema.VIEWS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN (
            'vw_active_sales_aggregate_rows','vw_active_inventory_aggregate_rows','vw_sales_reference_summary','vw_inventory_reference_summary',
            'vw_sales_by_manager_reference','vw_sales_by_supervisor_reference','vw_sales_by_visitor_reference','vw_sales_by_line_reference',
            'vw_sales_by_brand_reference','vw_sales_by_customer_reference','vw_sales_by_product_reference','vw_inventory_by_brand_reference','vw_inventory_by_product_reference'
        )"
    )->fetchColumn();

    $message = "زیرساخت دیتای مرجع بررسی شد؛ {$salesMappings} نگاشت فروش، {$inventoryMappings} نگاشت موجودی و {$viewCount} View آماده است.";
    if (PHP_SAPI === 'cli') {
        echo $message . PHP_EOL;
    } else {
        flash($message);
        redirect('/admin/sales-reference-status.php');
    }
} catch (Throwable $e) {
    error_log('Sales reference import seed: ' . $e->getMessage());
    $message = 'راه‌اندازی ورود اطلاعات مرجع انجام نشد.';
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
    flash($message, 'danger');
    redirect('/admin/install-tools.php');
}
