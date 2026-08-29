<?php

require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/CeoDashboardExcel.php';
require_once __DIR__ . '/../services/UnifiedImportService.php';
require_once __DIR__ . '/../lib/ImportTemplateService.php';

Auth::requireLogin();
$canDownload = Auth::isAdmin()
    || Auth::can('import_center.view')
    || Auth::can('import_center.upload')
    || Auth::can('sales_reference_upload')
    || Auth::can('sales_data_import')
    || Auth::can('sales_data_view');
if (!$canDownload) {
    http_response_code(403);
    exit('دسترسی غیرمجاز');
}

$requested = trim((string)($_GET['source'] ?? 'all'));
$available = array_keys(ImportSourceRegistry::all());
$candidates = $requested === 'all'
    ? $available
    : ($requested === 'budget_inputs' ? UnifiedImportService::BUDGET_INPUT_SOURCES : [$requested]);
$allowed = [];
foreach ($candidates as $source) {
    if (!in_array($source, $available, true)) continue;
    try {
        UnifiedImportService::assertTemplatePermission($source);
        $allowed[] = $source;
    } catch (DomainException $e) {
        if ($requested !== 'all') {
            http_response_code(403);
            exit('برای دریافت قالب این بخش دسترسی ندارید.');
        }
    }
}
if (!$allowed) {
    http_response_code($requested === 'all' ? 403 : 404);
    exit('قالب قابل دریافت نیست.');
}

Auth::log((int)(Auth::user()['id'] ?? 0), 'download_import_template', 'import_center', null);
try {
    CeoDashboardExcel::output(
        ImportTemplateService::workbook($allowed),
        ImportTemplateService::fileName($allowed)
    );
} catch (Throwable $e) {
    error_log('Import template download: '.$e->getMessage());
    http_response_code(503);
    exit('ساخت فایل قالب در حال حاضر امکان‌پذیر نیست. لطفاً تنظیمات Excel سرور بررسی شود.');
}
