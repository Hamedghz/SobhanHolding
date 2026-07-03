<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/SalesOperationsModule.php';

if (PHP_SAPI !== 'cli') {
    Auth::requireLogin();
    if (!Auth::canManageSystemTools()) {
        http_response_code(403);
        die('مجوز مدیریت سیستم لازم است.');
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $pageTitle = 'تعمیر پنل سرپرستان فروش';
        require __DIR__ . '/../views/partials/admin-header.php';
        ?>
        <section class="card admin-form">
            <h1>تعمیر پنل سرپرستان فروش</h1>
            <p class="muted">ساختار جداول و دسترسی‌ها بدون حذف اطلاعات بررسی می‌شود.</p>
            <form method="post"><input type="hidden" name="csrf_token" value="<?= e(Auth::csrfToken()) ?>"><button class="btn btn-primary">اجرا</button></form>
        </section>
        <?php
        require __DIR__ . '/../views/partials/admin-footer.php';
        die;
    }
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
        http_response_code(419);
        die('درخواست معتبر نیست.');
    }
}

try {
    SalesOperationsModule::repair(Database::connection());
    $message = 'پنل سرپرستان فروش با موفقیت بررسی شد.';
    if (PHP_SAPI === 'cli') echo $message . PHP_EOL;
    else { flash($message); redirect('/admin/install-tools.php'); }
} catch (Throwable $e) {
    error_log('Sales supervisor panel repair: ' . $e->getMessage());
    $message = 'بررسی پنل سرپرستان فروش انجام نشد.';
    if (PHP_SAPI === 'cli') { fwrite(STDERR, $message . PHP_EOL); die(1); }
    flash($message, 'danger');
    redirect('/admin/install-tools.php');
}
