<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/HrModule.php';

Auth::requireLogin();
if (!Auth::isAdmin()) { http_response_code(403); exit('دسترسی غیرمجاز است.'); }
$pageTitle='راه‌اندازی ماژول منابع انسانی';$result=null;$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!Auth::verifyCsrf($_POST['csrf_token']??'')){http_response_code(419);exit('درخواست نامعتبر است.');}
    try{$pdo=Database::connection();HrModule::repair($pdo);$result=HrModule::seed($pdo);}catch(Throwable $e){error_log('Sobhan HR seed failed: '.$e->getMessage());$error='خطا در ذخیره‌سازی اطلاعات. لطفاً مجدد تلاش کنید.';}
}
require __DIR__.'/../views/partials/admin-header.php';
?>
<section class="card admin-form"><h1>راه‌اندازی ماژول HR</h1><p>این عملیات idempotent است و رکورد تکراری نمی‌سازد.</p><?php if($error):?><div class="alert alert-danger"><?=e($error)?></div><?php endif?><?php if($result):?><div class="alert alert-success">عملیات با موفقیت انجام شد.</div><div class="stats"><?php foreach($result as $key=>$count):?><div class="stat-card"><span><?=e($key)?></span><strong><?=e((string)$count)?></strong></div><?php endforeach?></div><?php endif?><form method="post"><input type="hidden" name="csrf_token" value="<?=e(Auth::csrfToken())?>"><button class="btn btn-primary">بررسی و اجرای seed</button></form></section>
</section></main></div><script src="/assets/js/app.js"></script></body></html>
