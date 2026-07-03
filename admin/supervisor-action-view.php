<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../services/SalesOperationsService.php';
SalesOperationsService::boot(); Auth::requireLogin();
$user=Auth::user(); $id=(int)($_GET['id']??0);
$action=Database::fetch('SELECT a.*,s.title section_title,s.description section_description,sup.name supervisor_name,v.name visitor_name,m.name manager_name FROM supervisor_actions a LEFT JOIN supervisor_script_sections s ON s.id=a.section_id LEFT JOIN users sup ON sup.id=a.supervisor_id LEFT JOIN users v ON v.id=a.visitor_id LEFT JOIN users m ON m.id=a.sales_manager_id WHERE a.id=?',[$id]);
if(!$action){http_response_code(404);exit('اقدام پیدا نشد.');}
$canAccess=SalesOperationsService::canAccessSupervisor((int)$action['supervisor_id'],$user);
if(!$canAccess){http_response_code(403);exit('دسترسی غیرمجاز');}
$errors=[];
if($_SERVER['REQUEST_METHOD']==='POST'){
 try{
  if(!Auth::verifyCsrf($_POST['csrf_token']??null)) throw new RuntimeException('درخواست نامعتبر است.');
  $postAction=$_POST['action']??'';
  if($postAction==='update_status'){
   $new=SalesOperationsService::validSupervisorStatus((string)($_POST['status']??'open'));
   Database::execute('UPDATE supervisor_actions SET status=?, manager_note=COALESCE(NULLIF(?,""),manager_note), updated_by=?, completed_at=IF(?="done",NOW(),completed_at), updated_at=NOW() WHERE id=?',[$new,trim((string)($_POST['manager_note']??'')),(int)$user['id'],$new,$id]);
   SalesOperationsService::logSupervisorAction($id,'status_review',['status'=>$action['status']],['status'=>$new,'manager_note'=>$_POST['manager_note']??'']);
   flash('وضعیت اقدام بروزرسانی شد.'); redirect('/admin/supervisor-action-view.php?id='.$id);
  }
  if($postAction==='send_planner'){
   $taskId=SalesOperationsService::syncSupervisorActionToPlanner($id);
   flash($taskId?'اقدام به پلنر ارسال شد.':'ماژول پلنر هنوز فعال نیست.'); redirect('/admin/supervisor-action-view.php?id='.$id);
  }
 }catch(Throwable $e){$errors[]=$e->getMessage();}
}
$logs=Database::fetchAll('SELECT l.*,u.name user_name FROM supervisor_action_logs l LEFT JOIN users u ON u.id=l.performed_by WHERE l.action_id=? ORDER BY l.created_at DESC',[$id]);
$dynamic=json_decode((string)($action['dynamic_values_json']??'[]'),true)?:[];
$pageTitle='جزئیات اقدام سرپرست'; require __DIR__ . '/../views/partials/admin-header.php';
?>
<div class="section-heading-row"><div><h1><?=e($action['title'])?></h1><p class="muted">سرپرست: <?=e($action['supervisor_name']??'-')?> | ویزیتور: <?=e($action['visitor_name']??'-')?></p></div><div class="actions"><a class="btn" href="/admin/supervisor-actions.php">بازگشت</a></div></div>
<?php foreach($errors as $error):?><div class="alert alert-danger"><?=e($error)?></div><?php endforeach;?>
<div class="stats"><div class="stat-card"><span>بخش</span><strong><?=e($action['section_title']??'-')?></strong></div><div class="stat-card"><span>اولویت</span><strong><?=e(SalesOperationsService::priorityLabel($action['priority']))?></strong></div><div class="stat-card"><span>مهلت</span><strong><?=e($action['due_date']?:'-')?></strong></div><div class="stat-card"><span>وضعیت</span><strong><?=e(SalesOperationsService::statusLabel($action['status']))?></strong></div><div class="stat-card"><span>پلنر</span><strong><?=!empty($action['planner_task_id'])?'ثبت شده':'ثبت نشده'?></strong></div></div>
<section class="card"><h2>شرح اقدام</h2><p><?=nl2br(e($action['description']??''))?></p><?php if($dynamic):?><h3>فیلدهای داینامیک</h3><div class="table-wrap"><table><tbody><?php foreach($dynamic as $k=>$v):?><tr><th><?=e((string)$k)?></th><td><?=e(is_scalar($v)?(string)$v:json_encode($v,JSON_UNESCAPED_UNICODE))?></td></tr><?php endforeach;?></tbody></table></div><?php endif;?></section>
<form class="card admin-form" method="post"><input type="hidden" name="csrf_token" value="<?=e(Auth::csrfToken())?>"><h2>بررسی / بروزرسانی وضعیت</h2><div class="grid grid-2"><label class="form-field"><span>وضعیت</span><select name="status"><option value="open">باز</option><option value="in_progress">در حال پیگیری</option><option value="needs_manager_review">نیازمند بررسی مدیر فروش</option><option value="done">انجام‌شده</option><option value="cancelled">لغوشده</option></select></label><label class="form-field"><span>نظر مدیر فروش</span><textarea name="manager_note" rows="3"><?=e($action['manager_note']??'')?></textarea></label></div><div class="form-actions"><button class="btn btn-primary" name="action" value="update_status">ثبت بررسی</button><button class="btn" name="action" value="send_planner">افزودن به پلنر</button></div></form>
<section class="card"><h2>تاریخچه</h2><div class="table-wrap"><table><thead><tr><th>عملیات</th><th>کاربر</th><th>زمان</th></tr></thead><tbody><?php foreach($logs as $log):?><tr><td><?=e($log['action'])?></td><td><?=e($log['user_name']??'-')?></td><td><?=e($log['created_at'])?></td></tr><?php endforeach;?><?php if(!$logs):?><tr><td colspan="3">تاریخچه‌ای ثبت نشده است.</td></tr><?php endif;?></tbody></table></div></section>
<?php require __DIR__ . '/../views/partials/admin-footer.php'; ?>
