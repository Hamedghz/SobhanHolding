<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/SeedManager.php';

Auth::requireLogin();
if (!Auth::canManageSystemTools()) { http_response_code(403); exit('برای این بخش مجوز مدیریت سیستم لازم است.'); }
$pageTitle='راه‌اندازی ماژول منابع انسانی';$result=null;$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!Auth::verifyCsrf($_POST['csrf_token']??'')){http_response_code(419);exit('درخواست نامعتبر است.');}
    try{$mode=in_array($_POST['mode']??'', ['safe','repair','dry_run'],true)?$_POST['mode']:'safe';$result=SeedManager::runMany(['hr_kpi','hr_assessment','ai_sources'],$mode,(int)Auth::user()['id']);}catch(Throwable $e){error_log('Sobhan HR seed failed: '.$e->getMessage());$error='خطا در ذخیره‌سازی اطلاعات. لطفاً مجدد تلاش کنید.';}
}
require __DIR__.'/../views/partials/admin-header.php';
?>
<section class="card admin-form"><h1>راه‌اندازی ماژول HR</h1><p>این صفحه از Seed Manager مرکزی استفاده می‌کند و اطلاعات سفارشی را بازنویسی نمی‌کند.</p><?php if($error):?><div class="alert alert-danger"><?=e($error)?></div><?php endif?><?php if($result):?><div class="alert alert-success">راه‌اندازی ماژول HR با موفقیت انجام شد.</div><pre class="maintenance-report"><?=e(json_encode($result,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT))?></pre><?php endif?><form method="post"><input type="hidden" name="csrf_token" value="<?=e(Auth::csrfToken())?>"><select name="mode"><option value="safe">اجرای امن</option><option value="dry_run">بررسی بدون اجرا</option><option value="repair">تعمیر</option></select><button class="btn btn-primary">اجرای Seed منابع انسانی</button></form><a class="btn" href="/admin/system-maintenance.php">مدیریت همه Seedها</a></section>
</section></main></div><script src="/assets/js/app.js"></script></body></html>
