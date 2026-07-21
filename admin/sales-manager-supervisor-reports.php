<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../services/SalesOperationsService.php';
require_once __DIR__ . '/../services/ActionHubService.php';
SalesOperationsService::boot();
ActionHubService::boot();
SalesOperationsService::requireSalesManagerPermission('sales_manager.supervisors.view');
$user=Auth::user(); [$from,$to]=SalesOperationsService::dateFilters($_GET);
$summaryRows=SalesOperationsService::getSalesManagerSupervisorSummary((int)$user['id'],['from'=>$from,'to'=>$to]);
$supervisorId=(int)($_GET['supervisor_id']??0);
if($supervisorId){$summaryRows=array_values(array_filter($summaryRows,fn($r)=>(int)$r['supervisor']['id']===$supervisorId));}
if($_SERVER['REQUEST_METHOD']==='POST'){
 if(!Auth::verifyCsrf($_POST['csrf_token']??null)){http_response_code(419);exit('درخواست نامعتبر است.');}
 try{
  if(!SalesOperationsService::canViewAll($user)&&!Auth::can('sales_manager.supervisor_actions.review'))throw new InvalidArgumentException('مجوز بررسی اقدامات سرپرستان را ندارید.');
  $actionId=(int)($_POST['action_id']??0);$note=trim((string)($_POST['manager_note']??''));$status=(string)($_POST['review_status']??'needs_manager_review');
  $row=Database::fetch('SELECT * FROM supervisor_actions WHERE id=?',[$actionId]);
  if(!$row || !SalesOperationsService::canAccessSupervisor((int)$row['supervisor_id'],$user)) throw new RuntimeException('دسترسی بررسی این اقدام وجود ندارد.');
  $normalizedStatus=SalesOperationsService::validSupervisorStatus($status);
  Database::execute(
      'UPDATE supervisor_actions SET manager_note=?, status=?, updated_by=?,
       completed_at=IF(?="done",COALESCE(completed_at,NOW()),NULL), updated_at=NOW() WHERE id=?',
      [$note,$normalizedStatus,(int)$user['id'],$normalizedStatus,$actionId]
  );
  SalesOperationsService::logSupervisorAction($actionId,'manager_review',null,['note'=>$note,'status'=>$normalizedStatus]);
  if (!ActionHubService::mirrorLegacyAction('supervisor_actions', $actionId)) {
      error_log('Action Hub legacy sync failed for supervisor action id=' . $actionId);
  }
  flash('نظر مدیر فروش ثبت شد.'); redirect('/admin/sales-manager-supervisor-reports.php');
 }catch(Throwable $e){flash(SalesOperationsService::uiError($e,'ثبت بررسی مدیر فروش انجام نشد.'),'danger');redirect('/admin/sales-manager-supervisor-reports.php');}
}
$allSupervisorIds=array_map(fn($r)=>(int)$r['supervisor']['id'],$summaryRows);
$actions=[];$reports=[];
if($allSupervisorIds){$ph=implode(',',array_fill(0,count($allSupervisorIds),'?'));$actions=Database::fetchAll("SELECT a.*,sup.name supervisor_name,v.name visitor_name FROM supervisor_actions a LEFT JOIN users sup ON sup.id=a.supervisor_id LEFT JOIN users v ON v.id=a.visitor_id WHERE a.supervisor_id IN ($ph) ORDER BY a.created_at DESC LIMIT 100",$allSupervisorIds);$reports=Database::fetchAll("SELECT r.*,sup.name supervisor_name,v.name visitor_name FROM sales_supervisor_reports r LEFT JOIN users sup ON sup.id=r.supervisor_id LEFT JOIN users v ON v.id=r.visitor_id WHERE r.supervisor_id IN ($ph) ORDER BY r.report_date DESC,r.id DESC LIMIT 100",$allSupervisorIds);} 
$pageTitle='گزارش عملکرد سرپرستان فروش'; require __DIR__ . '/../views/partials/admin-header.php';
?>
<div class="section-heading-row"><div><h1>گزارش عملکرد سرپرستان</h1><p class="muted">گزارش تجمیعی سرپرستان زیرمجموعه مدیر فروش برای جلسه تحلیل فروش ماهانه.</p></div><div class="actions"><a class="btn" href="/admin/sales-actions.php">اقدامات فروش</a><a class="btn" href="/admin/sales-purchase-suggestions.php">پیشنهاد اردر خرید</a></div></div>
<form class="card admin-form" method="get"><div class="grid grid-3"><label class="form-field"><span>دوره گزارش</span><?=app_period_select('period_key',$_GET['period_key']??null,['daily','weekly','monthly','quarterly','half_yearly','yearly'],['placeholder'=>'ماه جاری'])?></label><label class="form-field"><span>از تاریخ (پیشرفته)</span><?=app_date_input('from',$from)?></label><label class="form-field"><span>تا تاریخ (پیشرفته)</span><?=app_date_input('to',$to)?></label><label class="form-field"><span>سرپرست</span><select name="supervisor_id"><option value="0">همه</option><?php foreach($summaryRows as $row):?><option value="<?=(int)$row['supervisor']['id']?>" <?=$supervisorId===(int)$row['supervisor']['id']?'selected':''?>><?=e($row['supervisor']['name'])?></option><?php endforeach;?></select></label></div><div class="form-actions"><button class="btn btn-primary">اعمال فیلتر</button><a class="btn" href="/admin/sales-manager-supervisor-reports.php">پاکسازی</a></div></form>
<div class="stats"><div class="stat-card"><span>سرپرستان فعال</span><strong><?=e((string)count($summaryRows))?></strong></div><div class="stat-card"><span>فروش کل تیم‌ها</span><strong><?=e(number_format(array_sum(array_map(fn($r)=>(float)$r['summary']['net_sales'],$summaryRows))))?></strong></div><div class="stat-card"><span>اقدامات باز</span><strong><?=e((string)array_sum(array_map(fn($r)=>(int)($r['actions']['open_count']??0),$summaryRows)))?></strong></div><div class="stat-card"><span>اقدامات فوری</span><strong><?=e((string)array_sum(array_map(fn($r)=>(int)($r['actions']['urgent_count']??0),$summaryRows)))?></strong></div><div class="stat-card"><span>اقدامات سررسید گذشته</span><strong><?=e((string)array_sum(array_map(fn($r)=>(int)($r['actions']['overdue_count']??0),$summaryRows)))?></strong></div></div>
<section class="card"><h2>خلاصه مدیریتی سرپرستان</h2><div class="table-wrap"><table><thead><tr><th>سرپرست</th><th>لاین</th><th>فروش تیم</th><th>اقدام باز</th><th>فوری</th><th>سررسید گذشته</th><th>گزارش در انتظار</th><th>اقدام مدیریتی پیشنهادی</th></tr></thead><tbody><?php foreach($summaryRows as $row):?><tr><td><?=e($row['supervisor']['name'])?></td><td><?=e($row['supervisor']['sales_line']??'-')?></td><td><?=e(number_format((float)$row['summary']['net_sales']))?></td><td><?=e((string)($row['actions']['open_count']??0))?></td><td><?=e((string)($row['actions']['urgent_count']??0))?></td><td><?=e((string)($row['actions']['overdue_count']??0))?></td><td><?=e((string)($row['reports']['pending_count']??0))?></td><td><?=e($row['manager_suggestion'])?></td></tr><?php endforeach;?><?php if(!$summaryRows):?><tr><td colspan="8">سرپرستی در محدوده دسترسی شما ثبت نشده است.</td></tr><?php endif;?></tbody></table></div></section>
<section class="card"><h2>اقدامات ثبت‌شده سرپرستان</h2><div class="table-wrap"><table><thead><tr><th>سرپرست</th><th>ویزیتور</th><th>عنوان</th><th>اولویت</th><th>مهلت</th><th>وضعیت</th><th>نظر</th></tr></thead><tbody><?php foreach($actions as $a):?><tr><td><?=e($a['supervisor_name']??'-')?></td><td><?=e($a['visitor_name']??'-')?></td><td><a href="/admin/supervisor-action-view.php?id=<?=(int)$a['id']?>"><?=e($a['title'])?></a></td><td><?=e(SalesOperationsService::priorityLabel($a['priority']))?></td><td><?=e($a['due_date']?format_jalali_date($a['due_date']):'-')?></td><td><?=e(SalesOperationsService::statusLabel($a['status']))?></td><td><form method="post" class="inline-form"><input type="hidden" name="csrf_token" value="<?=e(Auth::csrfToken())?>"><input type="hidden" name="action_id" value="<?=(int)$a['id']?>"><input name="manager_note" placeholder="نظر مدیر فروش" value="<?=e($a['manager_note']??'')?>"><select name="review_status"><option value="needs_manager_review">نیازمند بررسی</option><option value="in_progress">در حال پیگیری</option><option value="done">انجام‌شده</option></select><button class="btn btn-sm">ثبت</button></form></td></tr><?php endforeach;?><?php if(!$actions):?><tr><td colspan="7">اقدامی ثبت نشده است.</td></tr><?php endif;?></tbody></table></div></section>
<section class="card"><h2>گزارش‌های اجرای قالب اقدام فروش</h2><div class="table-wrap"><table><thead><tr><th>تاریخ</th><th>سرپرست</th><th>ویزیتور</th><th>عنوان</th><th>وضعیت</th><th>خلاصه</th></tr></thead><tbody><?php foreach($reports as $r):?><tr><td><?=e(format_jalali_date($r['report_date']))?></td><td><?=e($r['supervisor_name']??'-')?></td><td><?=e($r['visitor_name']??'-')?></td><td><?=e($r['title'])?></td><td><?=e(SalesOperationsService::statusLabel($r['status']))?></td><td><?=e(mb_substr((string)($r['summary']??''),0,120))?></td></tr><?php endforeach;?><?php if(!$reports):?><tr><td colspan="6">گزارشی ثبت نشده است.</td></tr><?php endif;?></tbody></table></div></section>
<?php require __DIR__ . '/../views/partials/admin-footer.php'; ?>
