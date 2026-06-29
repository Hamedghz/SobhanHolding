<?php

require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/PersonalPlannerModule.php';

if (PHP_SAPI !== 'cli') {
    Auth::requireLogin();
    if (!Auth::canManageSystemTools()) {
        http_response_code(403);
        exit('برای اجرای نصب پلنر شخصی مجوز مدیریت سیستم لازم است.');
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $pageTitle = 'نصب پلنر شخصی';
        require __DIR__ . '/../views/partials/admin-header.php';
        ?>
        <section class="card admin-form">
            <h1>نصب و تعمیر پلنر شخصی</h1>
            <p class="muted">ساختار جداول بدون حذف اطلاعات موجود بررسی و تکمیل می‌شود.</p>
            <form method="post"><input type="hidden" name="csrf_token" value="<?= e(Auth::csrfToken()) ?>"><button class="btn btn-primary">اجرای نصب امن</button><a class="btn" href="/admin/install-tools.php">بازگشت</a></form>
        </section>
        <?php require __DIR__ . '/../views/partials/admin-footer.php'; exit;
    }
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
        http_response_code(419);
        exit('درخواست معتبر نیست.');
    }
}

try {
    PersonalPlannerModule::repair(Database::connection());
    $message = 'ساختار پلنر شخصی با موفقیت بررسی و بروزرسانی شد.';
    if (PHP_SAPI === 'cli') echo $message . PHP_EOL;
    else { flash($message); redirect('/admin/install-tools.php'); }
} catch (Throwable $e) {
    error_log('Personal planner seed: ' . $e->getMessage());
    $message = 'بروزرسانی پلنر شخصی انجام نشد. گزارش فنی سرور را بررسی کنید.';
    if (PHP_SAPI === 'cli') { fwrite(STDERR, $message . PHP_EOL); exit(1); }
    flash($message, 'danger');
    redirect('/admin/install-tools.php');
}
