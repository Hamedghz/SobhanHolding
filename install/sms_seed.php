<?php
require_once __DIR__.'/../core/Auth.php';require_once __DIR__.'/../core/Response.php';require_once __DIR__.'/../core/SmsModule.php';
Auth::requirePermission('system_maintenance');$pageTitle='راه‌اندازی ماژول پیامکی';$result=null;
if($_SERVER['REQUEST_METHOD']==='POST'){if(!Auth::verifyCsrf($_POST['csrf_token']??'')){flash('درخواست نامعتبر است.','danger');redirect('/install/sms_seed.php');}try{SmsModule::repair(Database::connection());$count=SmsModule::seedTemplates(Database::connection(),(int)Auth::user()['id']);Auth::log((int)Auth::user()['id'],'sms_seed_run','sms_templates');$result="ساختار ماژول بررسی و {$count} قالب پیش‌فرض به‌صورت idempotent پردازش شد.";}catch(Throwable $e){error_log('SMS seed: '.get_class($e));$result='راه‌اندازی ماژول پیامکی انجام نشد. لاگ سرور را بررسی کنید.';}}
require __DIR__.'/../views/partials/admin-header.php';?>
<section class="card"><h2>راه‌اندازی امن پیامک</h2><p>این عملیات فقط ساختارهای مفقود را ایجاد و metadata قالب‌های پیش‌فرض را بروزرسانی می‌کند؛ داده‌ای حذف نمی‌شود.</p><?php if($result):?><div class="alert"><?=e($result)?></div><?php endif?><form method="post"><input type="hidden" name="csrf_token" value="<?=e(Auth::csrfToken())?>"><button class="btn btn-primary">اجرای بررسی و Seed</button></form></section>
<?php require __DIR__.'/../views/partials/admin-footer.php';
