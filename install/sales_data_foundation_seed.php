<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/SalesDataSchema.php';

if (PHP_SAPI !== 'cli') {
    Auth::requireLogin();
    if (!Auth::canManageSystemTools()) {
        http_response_code(403);
        exit('مجوز مدیریت سیستم لازم است.');
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $pageTitle = 'ایجاد زیرساخت مدیریت داده فروش';
        require __DIR__ . '/../views/partials/admin-header.php';
        ?>
        <section class="card">
            <h1><?= e($pageTitle) ?></h1>
            <p>جداول و مجوزهای مفقود به‌صورت افزایشی ایجاد می‌شوند و هیچ داده‌ای حذف یا بازنویسی نمی‌شود.</p>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= e(Auth::csrfToken()) ?>">
                <button class="btn btn-primary" type="submit">اجرای Seed امن</button>
            </form>
        </section>
        <?php
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
    $missing = [];
    $check = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?'
    );
    foreach (SalesDataSchema::tableNames() as $table) {
        $check->execute([$table]);
        if ((int)$check->fetchColumn() === 0) $missing[] = $table;
    }
    if ($missing) throw new RuntimeException('sales_data_tables_missing');

    $message = 'زیرساخت مدیریت داده فروش با موفقیت بررسی شد؛ ۱۳ جدول و ۸ مجوز آماده هستند.';
    if (PHP_SAPI === 'cli') {
        echo $message . PHP_EOL;
    } else {
        flash($message);
        redirect('/admin/sales-data-index.php');
    }
} catch (Throwable $e) {
    error_log('Sales data foundation seed: ' . $e->getMessage());
    $message = 'ایجاد زیرساخت مدیریت داده فروش انجام نشد.';
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
    flash($message, 'danger');
    redirect('/admin/install-tools.php');
}
