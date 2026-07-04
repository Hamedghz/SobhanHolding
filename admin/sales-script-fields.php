<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../services/SalesOperationsService.php';
SalesOperationsService::boot(); SalesOperationsService::requireSalesManagerPermission('sales_manager.scripts.manage');
$scriptId=(int)($_GET['script_id']??($_POST['script_id']??0));$errors=[];
$script=$scriptId?(SalesOperationsService::canViewAll(Auth::user())?Database::fetch('SELECT * FROM sales_scripts WHERE id=?',[$scriptId]):Database::fetch('SELECT * FROM sales_scripts WHERE id=? AND created_by=?',[$scriptId,(int)Auth::user()['id']])):null;
if($scriptId&&!$script){http_response_code(403);exit('دسترسی غیرمجاز');}
if($_SERVER['REQUEST_METHOD']==='POST'){
 try{
  if(!Auth::verifyCsrf($_POST['csrf_token']??null)) throw new RuntimeException('درخواست نامعتبر است.');
  if($scriptId<=0) throw new RuntimeException('ابتدا اسکریپت را انتخاب کنید.');
  $key=trim((string)($_POST['field_key']??''));$label=trim((string)($_POST['field_label']??''));
  if($key===''||$label==='') throw new RuntimeException('کلید و عنوان فیلد اجباری هستند.');
  $type=(string)($_POST['field_type']??'text');
  $allowed=['text','textarea','number','date','select','multi_select','status','customer','product','brand','user','file']; if(!in_array($type,$allowed,true))$type='text';
  Database::execute('INSERT INTO sales_script_fields(script_id,field_key,field_label,field_type,options_json,default_value,required,visible_to_supervisor,visible_to_sales_manager,sort_order,active,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,1,NOW(),NOW()) ON DUPLICATE KEY UPDATE field_label=VALUES(field_label),field_type=VALUES(field_type),options_json=VALUES(options_json),default_value=VALUES(default_value),required=VALUES(required),visible_to_supervisor=VALUES(visible_to_supervisor),visible_to_sales_manager=VALUES(visible_to_sales_manager),sort_order=VALUES(sort_order),active=1,updated_at=NOW()',[$scriptId,$key,$label,$type,trim((string)($_POST['options_json']??''))?:null,trim((string)($_POST['default_value']??''))?:null,!empty($_POST['required'])?1:0,!empty($_POST['visible_to_supervisor'])?1:0,!empty($_POST['visible_to_sales_manager'])?1:0,(int)($_POST['sort_order']??0)]);
  flash('فیلد داینامیک ذخیره شد.');redirect('/admin/sales-script-fields.php?script_id='.$scriptId);
 }catch(Throwable $e){$errors[]=SalesOperationsService::uiError($e,'ذخیره فیلد اسکریپت انجام نشد.');}
}
$scripts=SalesOperationsService::canViewAll(Auth::user())?Database::fetchAll('SELECT id,title,script_code FROM sales_scripts ORDER BY title'):Database::fetchAll('SELECT id,title,script_code FROM sales_scripts WHERE created_by=? ORDER BY title',[(int)Auth::user()['id']]);
$fields=$scriptId?Database::fetchAll('SELECT * FROM sales_script_fields WHERE script_id=? ORDER BY sort_order,id',[$scriptId]):[];
$pageTitle='فیلدهای داینامیک اسکریپت';require __DIR__ . '/../views/partials/admin-header.php';
?>
<div class="section-heading-row"><div><h1>فیلدهای داینامیک اسکریپت و اقدامات</h1><p class="muted">تعریف فیلدهایی که سرپرست یا مدیر فروش هنگام تکمیل اسکریپت مشاهده و ثبت می‌کند.</p></div><div class="actions"><a class="btn" href="/admin/sales-scripts.php">اسکریپت‌ها</a></div></div>
<?php foreach($errors as $error):?><div class="alert alert-danger"><?=e($error)?></div><?php endforeach;?>
<form class="card admin-form" method="get"><label class="form-field"><span>انتخاب اسکریپت</span><select name="script_id" onchange="this.form.submit()"><option value="0">انتخاب کنید</option><?php foreach($scripts as $s):?><option value="<?=(int)$s['id']?>" <?=$scriptId===(int)$s['id']?'selected':''?>><?=e($s['title'].' - '.$s['script_code'])?></option><?php endforeach;?></select></label></form>
<?php if($script):?><form class="card admin-form" method="post"><input type="hidden" name="csrf_token" value="<?=e(Auth::csrfToken())?>"><input type="hidden" name="script_id" value="<?=$scriptId?>"><h2>فیلد جدید برای <?=e($script['title'])?></h2><div class="grid grid-3"><label class="form-field"><span>کلید فیلد</span><input name="field_key" required placeholder="followup_note"></label><label class="form-field"><span>عنوان فیلد</span><input name="field_label" required></label><label class="form-field"><span>نوع فیلد</span><select name="field_type"><option>text</option><option>textarea</option><option>number</option><option>date</option><option>select</option><option>multi_select</option><option>status</option><option>customer</option><option>product</option><option>brand</option><option>user</option><option>file</option></select></label><label class="form-field"><span>ترتیب</span><input type="number" name="sort_order" value="10"></label><label class="form-field"><span>مقدار پیش‌فرض</span><input name="default_value"></label></div><label class="form-field"><span>گزینه‌ها JSON</span><textarea name="options_json" rows="3" placeholder='{"open":"باز","done":"انجام‌شده"}'></textarea></label><label><input type="checkbox" name="required" value="1"> اجباری</label> <label><input type="checkbox" name="visible_to_supervisor" value="1" checked> نمایش به سرپرست</label> <label><input type="checkbox" name="visible_to_sales_manager" value="1" checked> نمایش به مدیر فروش</label><div class="form-actions"><button class="btn btn-primary">ذخیره فیلد</button></div></form><?php endif;?>
<section class="card"><h2>فیلدهای ثبت‌شده</h2><div class="table-wrap"><table><thead><tr><th>کلید</th><th>عنوان</th><th>نوع</th><th>اجباری</th><th>سرپرست</th><th>مدیر فروش</th><th>ترتیب</th></tr></thead><tbody><?php foreach($fields as $f):?><tr><td><?=e($f['field_key'])?></td><td><?=e($f['field_label'])?></td><td><?=e($f['field_type'])?></td><td><?=((int)$f['required']?'بله':'خیر')?></td><td><?=((int)$f['visible_to_supervisor']?'بله':'خیر')?></td><td><?=((int)$f['visible_to_sales_manager']?'بله':'خیر')?></td><td><?=e((string)$f['sort_order'])?></td></tr><?php endforeach;?><?php if(!$fields):?><tr><td colspan="7">فیلدی ثبت نشده است.</td></tr><?php endif;?></tbody></table></div></section>
<?php require __DIR__ . '/../views/partials/admin-footer.php'; ?>
