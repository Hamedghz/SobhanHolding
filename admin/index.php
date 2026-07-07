<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../lib/admin_menu.php';
require_once __DIR__ . '/../lib/OrgAccess.php';
require_once __DIR__ . '/../core/JalaliDate.php';
require_once __DIR__ . '/../services/WorkPlannerService.php';
Auth::requirePermission('dashboard','view');
$dashboardUser=Auth::user();$dashboardUserId=(int)$dashboardUser['id'];
if($_SERVER['REQUEST_METHOD']==='POST'&&str_starts_with((string)($_POST['action']??''),'planner_')){try{if(!Auth::verifyCsrf($_POST['csrf_token']??''))throw new InvalidArgumentException('اعتبار فرم منقضی شده است.');$action=(string)$_POST['action'];if($action==='planner_quick_add'){$today=date('Y-m-d');$input=$_POST;$input['due_date']=$today;WorkPlannerService::createPersonalTask($dashboardUserId,$input,$today);flash('وظیفه امروز افزوده شد.');}elseif($action==='planner_tomorrow'){if(!WorkPlannerService::moveToTomorrow((int)($_POST['task_id']??0),$dashboardUserId))throw new InvalidArgumentException('انتقال این وظیفه ممکن نیست.');flash('وظیفه به فردا منتقل شد.');}elseif($action==='planner_urgent'){if(!WorkPlannerService::markTaskUrgent((int)($_POST['task_id']??0),$dashboardUserId))throw new InvalidArgumentException('تغییر اولویت این وظیفه ممکن نیست.');flash('وظیفه فوری شد.');}}catch(InvalidArgumentException $e){flash($e->getMessage(),'danger');}catch(Throwable $e){error_log('Dashboard planner action: '.$e->getMessage());flash('عملیات وظیفه انجام نشد.','danger');}redirect('/admin/index.php');}
$pageTitle='داشبورد';
$icons=['dashboards'=>'▦','performance'=>'↗','hr'=>'◎','finance'=>'﷼','ai'=>'AI','content'=>'□','system'=>'⚙'];
$groups=[];foreach(admin_menu_registry() as $key=>$group){$items=array_values(array_filter($group['items'],'admin_menu_allowed'));if($items)$groups[$key]=['title'=>$group['title'],'items'=>$items];}
$cache=Database::fetchAll('SELECT dashboard_key,source,updated_at FROM dashboard_data_cache ORDER BY updated_at DESC');$cacheByKey=array_column($cache,null,'dashboard_key');
$profile=OrgAccess::userContext($dashboardUserId);
$adminExtraStylesheets=['/assets/css/personal-planner.css'];
require __DIR__ . '/../views/partials/admin-header.php';
?>
<?php require __DIR__ . '/../views/partials/work-planner-widget.php';?>
<section class="card" id="profile"><div class="section-heading-row"><div><h2>پروفایل سازمانی من</h2><p class="muted"><?=e($profile['org_unit_title']?:($profile['department']?:'بدون واحد'))?> · <?=e($profile['org_role_title']?:($profile['role_key']?:'بدون نقش'))?></p></div><strong><?=e($profile['name']??$dashboardUser['name']??'')?></strong></div><div class="dashboard-access-grid"><a class="dashboard-access-card" href="/admin/employee-tests.php"><strong>آزمون‌های من</strong></a><a class="dashboard-access-card" href="/admin/employee-kpi.php"><strong>نتایج KPI من</strong></a><a class="dashboard-access-card" href="/employee/tickets.php"><strong>تیکت‌های من</strong></a><a class="dashboard-access-card" href="/employee/work-planner.php"><strong>برنامه کاری من</strong></a></div></section>
<div class="section-heading-row"><div><h1>داشبورد دسترسی‌ها</h1><p class="muted">مسیرهای قابل استفاده بر اساس نقش و مجوزهای حساب شما</p></div></div>
<?php if(!$groups):?><section class="card"><p>برای حساب کاربری شما هنوز دسترسی فعالی تعریف نشده است.</p></section><?php endif?>
<?php foreach($groups as $key=>$group):?><section class="card"><h2><?=e($group['title'])?></h2><div class="dashboard-access-grid"><?php foreach($group['items'] as $item):?><a class="dashboard-access-card" href="<?=e($item['url'])?>"><span class="dashboard-access-icon"><?=e($icons[$key]??'□')?></span><strong><?=e($item['title'])?></strong><small>ورود به بخش</small></a><?php endforeach?></div></section><?php endforeach?>
<?php if($cache):?><section class="card"><h2>آخرین بروزرسانی داشبوردها</h2><div class="table-wrap"><table><thead><tr><th>داشبورد</th><th>منبع</th><th>زمان</th></tr></thead><tbody><?php foreach($cacheByKey as $row):?><tr><td><?=e($row['dashboard_key'])?></td><td><?=e($row['source'])?></td><td><?=e($row['updated_at'])?></td></tr><?php endforeach?></tbody></table></div></section><?php endif?>
<?php require __DIR__ . '/../views/partials/admin-footer.php';?>
