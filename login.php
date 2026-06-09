<?php
require_once __DIR__ . '/core/Auth.php';
require_once __DIR__ . '/core/Response.php';
$error = '';
if (Auth::user()) redirect('/admin/index.php');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'درخواست نامعتبر است.';
    } elseif (trim($_POST['login'] ?? '') === '' || trim($_POST['password'] ?? '') === '') {
        $error = 'نام کاربری/ایمیل و رمز عبور الزامی است.';
    } elseif (Auth::attempt(trim($_POST['login']), $_POST['password'])) {
        redirect('/admin/index.php');
    } else {
        $error = 'اطلاعات ورود صحیح نیست یا حساب غیرفعال است.';
    }
}
?>
<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>ورود</title><link rel="stylesheet" href="/assets/css/app.css"></head><body class="login-page"><main class="card login-card"><h1>ورود به سامانه</h1><p class="muted">شرکت پخش سبحان</p><?php if($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?><form method="post"><input type="hidden" name="csrf_token" value="<?= e(Auth::csrfToken()) ?>"><label class="form-field"><span>نام کاربری یا ایمیل</span><input name="login" value="<?= e($_POST['login'] ?? '') ?>" required autofocus></label><label class="form-field"><span>رمز عبور</span><input type="password" name="password" required></label><button class="btn btn-primary" type="submit">ورود</button></form></main></body></html>
