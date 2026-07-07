<?php
require_once __DIR__.'/../core/Auth.php';require_once __DIR__.'/../core/Response.php';require_once __DIR__.'/../core/SmsGatewayService.php';
Auth::requirePermission('sms.manage');$pageTitle='تنظیمات سامانه پیامکی';$canEdit=Auth::can('sms.manage','edit');
$setting=Database::fetch('SELECT id,provider_name,wsdl_url,url_api_base,username,default_sender,is_active,updated_at FROM sms_settings ORDER BY id DESC LIMIT 1');
if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!$canEdit){flash('برای تغییر تنظیمات پیامکی دسترسی ندارید.','danger');redirect('/admin/sms-settings.php');}
    if(!Auth::verifyCsrf($_POST['csrf_token']??'')){flash('درخواست نامعتبر است.','danger');redirect('/admin/sms-settings.php');}
    $action=(string)($_POST['action']??'save');$wsdl=trim((string)($_POST['wsdl_url']??''));$username=trim((string)($_POST['username']??''));$sender=trim((string)($_POST['default_sender']??''));$password=(string)($_POST['password']??'');
    if(!filter_var($wsdl,FILTER_VALIDATE_URL)||$username===''||$sender===''||(!$setting&&$password==='')){flash('آدرس WSDL، نام کاربری، رمز و خط ارسال را کامل و معتبر وارد کنید.','danger');redirect('/admin/sms-settings.php');}
    try{
        if($setting){$params=['bazyabpayam',$wsdl,trim((string)($_POST['url_api_base']??''))?:null,$username,$sender,!empty($_POST['is_active'])?1:0,(int)$setting['id']];$sql='UPDATE sms_settings SET provider_name=?,wsdl_url=?,url_api_base=?,username=?,default_sender=?,is_active=?,updated_at=NOW()';if($password!==''){$sql.=',password_encrypted=?';array_splice($params,6,0,[SmsGatewayService::encrypt($password)]);}$sql.=' WHERE id=?';Database::execute($sql,$params);}
        else Database::execute('INSERT INTO sms_settings(provider_name,wsdl_url,url_api_base,username,password_encrypted,default_sender,is_active,created_by,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,NOW(),NOW())',['bazyabpayam',$wsdl,trim((string)($_POST['url_api_base']??''))?:null,$username,SmsGatewayService::encrypt($password),$sender,!empty($_POST['is_active'])?1:0,(int)Auth::user()['id']]);
        Auth::log((int)Auth::user()['id'],'sms_settings_updated','sms_settings',(int)($setting['id']??Database::lastInsertId()));
        if($action==='test_credit'){$result=SmsGatewayService::active()->getCredit();flash($result['success']?'اعتبار پنل: '.(string)$result['credit']:$result['error_message'],$result['success']?'success':'danger');}else flash('تنظیمات پیامکی ذخیره شد.');
    }catch(Throwable $e){error_log('SMS settings: '.$e->getMessage());flash('ذخیره یا تست تنظیمات پیامکی انجام نشد. تنظیمات و افزونه‌های سرور را بررسی کنید.','danger');}
    redirect('/admin/sms-settings.php');
}
$setting=Database::fetch('SELECT id,provider_name,wsdl_url,url_api_base,username,default_sender,is_active,updated_at FROM sms_settings ORDER BY id DESC LIMIT 1');require __DIR__.'/../views/partials/admin-header.php';
?>
<form class="card admin-form" method="post" autocomplete="off"><input type="hidden" name="csrf_token" value="<?=e(Auth::csrfToken())?>"><h2>اتصال SOAP پیامک</h2><p class="muted">رمز پس از ذخیره نمایش داده نمی‌شود. API مبتنی بر URL در این فاز استفاده نمی‌شود.</p><div class="grid grid-2">
<label class="form-field"><span>سرویس‌دهنده</span><input value="BazyabPayam" disabled></label>
<label class="form-field"><span>WSDL</span><input dir="ltr" name="wsdl_url" required value="<?=e($setting['wsdl_url']??'http://185.94.99.84/webservice/wsdl/server.php?wsdl')?>" <?=$canEdit?'':'disabled'?>></label>
<label class="form-field"><span>نام کاربری</span><input dir="ltr" name="username" required value="<?=e($setting['username']??'')?>" <?=$canEdit?'':'disabled'?>></label>
<label class="form-field"><span>رمز عبور</span><input dir="ltr" type="password" name="password" autocomplete="new-password" placeholder="<?=$setting?'برای حفظ رمز فعلی خالی بگذارید':'رمز عبور'?>" <?=$canEdit?'':'disabled'?>></label>
<label class="form-field"><span>خط پیش‌فرض ارسال</span><input dir="ltr" name="default_sender" required value="<?=e($setting['default_sender']??'')?>" <?=$canEdit?'':'disabled'?>></label>
<label class="form-field"><span>URL API اختیاری (غیرفعال)</span><input dir="ltr" name="url_api_base" value="<?=e($setting['url_api_base']??'')?>" <?=$canEdit?'':'disabled'?>></label>
<label class="checkbox-item"><input type="checkbox" name="is_active" value="1" <?=!$setting||!empty($setting['is_active'])?'checked':''?> <?=$canEdit?'':'disabled'?>> سرویس فعال باشد</label></div>
<?php if($canEdit):?><div class="form-actions"><button class="btn btn-primary" name="action" value="save">ذخیره</button><button class="btn" name="action" value="test_credit">ذخیره و تست اعتبار</button></div><?php endif?></form>
<section class="card"><h2>کنترل‌های امنیتی</h2><p class="muted">ارتباط فقط در سمت سرور انجام می‌شود؛ خطاهای خام و رمز در رابط کاربری یا گزارش ارسال ذخیره نمی‌شوند.</p><a class="btn" href="/admin/sms-send.php">ارسال دستی و گزارش‌ها</a></section>
<?php require __DIR__.'/../views/partials/admin-footer.php';
