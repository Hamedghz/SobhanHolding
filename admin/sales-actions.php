<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../services/SalesOperationsService.php';
SalesOperationsService::boot(); SalesOperationsService::requireSalesManagerPermission('sales_manager.actions.manage');
$user=Auth::user();$errors=[];
if($_SERVER['REQUEST_METHOD']==='POST'){
 try{
  if(!Auth::verifyCsrf($_POST['csrf_token']??null)) throw new RuntimeException('درخواست نامعتبر است.');
  $title=trim((string)($_POST['title']??'')); if($title==='') throw new RuntimeException('عنوان اقدام اجباری است.');
  $id=SalesOperationsService::createSalesAction(['sales_manager_id'=>(int)$user['id'],'supervisor_id'=>(int)($_POST['supervisor_id']??0),'visitor_id'=>(int)($_POST['visitor_id']??0),'sales_line'=>$_POST['sales_line']??null,'assigned_to'=>(int)($_POST['assigned_to']??0),'title'=>$title,'description'=>$_POST['description']??'','priority'=>$_POST['priority']??'normal','status'=>$_POST['status']??'open','due_date'=>$_POST['due_date']??null,'add_to_planner'=>!empty($_POST['add_to_planner']),'dynamic_values'=>$_POST['dynamic']??[]]);
  flash('اقدام فروش ثبت شد.'); redirect('/admin/sales-actions.php');
 }catch(Throwable $e){$errors[]=SalesOperationsService::uiError($e,'ثبت اقدام فروش انجام نشد.');}
}
$where=[];$params=[];
if(!SalesOperationsService::canViewAll($user)){$where[]='(a.sales_manager_id=? OR a.assigned_to=?)';$params[]=(int)$user['id'];$params[]=(int)$user['id'];}
$status=trim((string)($_GET['status']??'')); if($status!==''){$where[]='a.status=?';$params[]=$status;}
$whereSql=$where?'WHERE '.implode(' AND ',$where):'';
$actions=Database::fetchAll("SELECT a.*,sup.name supervisor_name,v.name visitor_name,ass.name assigned_name FROM sales_actions a LEFT JOIN users sup ON sup.id=a.supervisor_id LEFT JOIN users v ON v.id=a.visitor_id LEFT JOIN users ass ON ass.id=a.assigned_to {$whereSql} ORDER BY a.created_at DESC LIMIT 200",$params);
$supervisors=SalesOperationsService::canViewAll($user)?Database::fetchAll('SELECT id,name,sales_line FROM users WHERE status="active" ORDER BY name LIMIT 500'):Database::fetchAll('SELECT id,name,sales_line FROM users WHERE id IN (SELECT supervisor_id FROM sales_team_assignments WHERE sales_manager_id=? AND active=1) OR organization_manager_id=? OR parent_user_id=? ORDER BY name',[(int)$user['id'],(int)$user['id'],(int)$user['id']]);
$teamIds=SalesOperationsService::getSalesManagerTeamUserIds((int)$user['id'],$user);$teamPh=implode(',',array_fill(0,count($teamIds),'?'));$users=Database::fetchAll("SELECT id,name FROM users WHERE status=\"active\" AND id IN ({$teamPh}) ORDER BY name LIMIT 500",$teamIds);
$pageTitle='اقدامات فروش'; require __DIR__ . '/../views/partials/admin-header.php';
?>
<div class="section-heading-row"><div><h1>اقدامات فروش</h1><p class="muted">ایجاد اقدام دستی یا پیگیری اقدامات تبدیل‌شده از گزارش سرپرستان و اتصال به پلنر.</p></div><div class="actions"><a class="btn" href="/admin/sales-manager-supervisor-reports.php">گزارش سرپرستان</a></div></div>
<?php if(!SalesOperationsService::plannerAvailable()):?><div class="alert alert-warning">ماژول پلنر در حال حاضر در دسترس نیست؛ اقدام بدون وظیفه پلنر ذخیره می‌شود.</div><?php endif?>
<?php foreach($errors as $error):?><div class="alert alert-danger"><?=e($error)?></div><?php endforeach;?>
<form class="card admin-form" method="post"><input type="hidden" name="csrf_token" value="<?=e(Auth::csrfToken())?>"><h2>اقدام جدید</h2><div class="grid grid-3"><label class="form-field"><span>عنوان</span><input name="title" required></label><label class="form-field"><span>مسئول اقدام</span><select name="assigned_to"><option value="0">خود مدیر فروش</option><?php foreach($users as $u):?><option value="<?=(int)$u['id']?>"><?=e($u['name'])?></option><?php endforeach;?></select></label><label class="form-field"><span>سرپرست مرتبط</span><select name="supervisor_id"><option value="0">بدون انتخاب</option><?php foreach($supervisors as $s):?><option value="<?=(int)$s['id']?>"><?=e($s['name'])?></option><?php endforeach;?></select></label><label class="form-field"><span>لاین فروش</span><input name="sales_line"></label><label class="form-field"><span>مهلت</span><input type="date" name="due_date"></label><label class="form-field"><span>اولویت</span><select name="priority"><option value="normal">متوسط</option><option value="high">بالا</option><option value="urgent">فوری</option><option value="low">پایین</option></select></label><label class="form-field"><span>وضعیت</span><select name="status"><option value="open">باز</option><option value="in_progress">در حال انجام</option><option value="done">انجام‌شده</option><option value="cancelled">لغوشده</option></select></label></div><label class="form-field"><span>توضیحات</span><textarea name="description" rows="4"></textarea></label><label><input type="checkbox" name="add_to_planner" value="1"> ارسال به پلنر</label><div class="form-actions"><button class="btn btn-primary">ثبت اقدام</button></div></form>
<section class="card"><h2>لیست اقدامات</h2><div class="table-wrap"><table><thead><tr><th>عنوان</th><th>مسئول</th><th>سرپرست</th><th>اولویت</th><th>مهلت</th><th>وضعیت</th><th>پلنر</th></tr></thead><tbody><?php foreach($actions as $a):?><tr><td><?=e($a['title'])?></td><td><?=e($a['assigned_name']??'-')?></td><td><?=e($a['supervisor_name']??'-')?></td><td><?=e(SalesOperationsService::priorityLabel($a['priority']))?></td><td><?=e($a['due_date']?:'-')?></td><td><span class="badge"><?=e(SalesOperationsService::statusLabel($a['status']))?></span></td><td><?=!empty($a['planner_task_id'])?'ثبت شده':'-'?></td></tr><?php endforeach;?><?php if(!$actions):?><tr><td colspan="7">اقدامی ثبت نشده است.</td></tr><?php endif;?></tbody></table></div></section>
<?php require __DIR__ . '/../views/partials/admin-footer.php'; ?>
