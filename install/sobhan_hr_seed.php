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
    try{$mode=in_array($_POST['mode']??'', ['safe','repair','dry_run'],true)?$_POST['mode']:'safe';$result=SeedManager::runMany(['hr_kpi','hr_assessment','ai_sources'],$mode,(int)Auth::user()['id']);}catch(Throwable $e){error_log('Sobhan HR seed failed: '.$e->getMessage());$error='به‌روزرسانی بانک سؤال انجام نشد. لطفاً گزارش خطا را بررسی کنید.';}
}
require __DIR__.'/../views/partials/admin-header.php';
?>
<section class="card admin-form"><h1>راه‌اندازی ماژول HR</h1><p>این صفحه از Seed Manager مرکزی استفاده می‌کند و بانک سؤال ۲۰ آزمون سبحان را به‌صورت ایمن و idempotent بازسازی می‌کند.</p><?php if($error):?><div class="alert alert-danger"><?=e($error)?></div><?php endif?><?php if($result):?><div class="alert alert-success">بانک سؤال ۲۰ آزمون سازمانی سبحان با موفقیت نصب/به‌روزرسانی شد.</div><pre class="maintenance-report"><?=e(json_encode($result,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT))?></pre><?php endif?><form method="post" onsubmit="return confirm('در صورت وجود پاسخ یا نتیجه ثبت‌شده، سوالات قبلی حذف نمی‌شوند و فقط آرشیو خواهند شد.')"><input type="hidden" name="csrf_token" value="<?=e(Auth::csrfToken())?>"><select name="mode"><option value="safe">اجرای امن</option><option value="dry_run">بررسی بدون اجرا</option><option value="repair">تعمیر</option></select><button class="btn btn-primary">بازسازی امن بانک سوالات</button></form><a class="btn" href="/admin/system-maintenance.php">مدیریت همه Seedها</a></section>
</section></main></div><script src="/assets/js/app.js"></script></body></html>
