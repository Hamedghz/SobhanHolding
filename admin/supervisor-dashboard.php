<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../services/SalesOperationsService.php';

SalesOperationsService::boot();
SalesOperationsService::requireSupervisorPermission('supervisor.panel.view');
$user = Auth::user();
$supervisorId = (int)$user['id'];
[$from,$to] = SalesOperationsService::dateFilters($_GET);
$summary = SalesOperationsService::getSupervisorSalesSummary($supervisorId, ['from'=>$from,'to'=>$to]);
$actionsToday = Database::fetchAll('SELECT * FROM supervisor_actions WHERE supervisor_id=? AND (due_date IS NULL OR due_date<=CURDATE()) AND status NOT IN ("done","cancelled") ORDER BY FIELD(priority,"urgent","high","normal","low"), due_date IS NULL, due_date LIMIT 20', [$supervisorId]);
$openActions = (int)(Database::fetch('SELECT COUNT(*) c FROM supervisor_actions WHERE supervisor_id=? AND status NOT IN ("done","cancelled")', [$supervisorId])['c'] ?? 0);
$overdueActions = (int)(Database::fetch('SELECT COUNT(*) c FROM supervisor_actions WHERE supervisor_id=? AND due_date<CURDATE() AND status NOT IN ("done","cancelled")', [$supervisorId])['c'] ?? 0);
$pendingReports = (int)(Database::fetch('SELECT COUNT(*) c FROM sales_supervisor_reports WHERE supervisor_id=? AND status IN ("submitted_by_supervisor","pending_sales_manager_review")', [$supervisorId])['c'] ?? 0);
$visitors = SalesOperationsService::getSupervisorVisitors($supervisorId);
$pageTitle = 'داشبورد سرپرست فروش';
require __DIR__ . '/../views/partials/admin-header.php';
?>
<div class="section-heading-row"><div><h1>داشبورد سرپرست فروش</h1><p class="muted">خلاصه عملکرد تیم فروش زیرمجموعه شما از <?=e($from)?> تا <?=e($to)?></p></div><div class="actions"><a class="btn btn-primary" href="/admin/supervisor-actions.php">ثبت اقدام</a><a class="btn" href="/admin/supervisor-sales-report.php">گزارش فروش</a></div></div>
<form class="card admin-form" method="get"><div class="grid grid-3"><label class="form-field"><span>از تاریخ</span><input type="date" name="from" value="<?=e($from)?>"></label><label class="form-field"><span>تا تاریخ</span><input type="date" name="to" value="<?=e($to)?>"></label></div><div class="form-actions"><button class="btn btn-primary">اعمال فیلتر</button></div></form>
<div class="stats"><div class="stat-card"><span>فروش خالص تیم</span><strong><?=e(number_format((float)$summary['net_sales']))?></strong></div><div class="stat-card"><span>تعداد ویزیتورهای تیم</span><strong><?=e((string)$summary['visitors'])?></strong></div><div class="stat-card"><span>اقدامات باز</span><strong><?=e((string)$openActions)?></strong></div><div class="stat-card"><span>اقدامات سررسید گذشته</span><strong><?=e((string)$overdueActions)?></strong></div><div class="stat-card"><span>گزارش‌های در انتظار بررسی</span><strong><?=e((string)$pendingReports)?></strong></div></div>
<section class="card"><h2>عملکرد ویزیتورها</h2><div class="table-wrap"><table><thead><tr><th>ویزیتور</th><th>لاین</th><th>فروش بازه</th><th>تعداد کالا/مقدار</th><th>تحقق تقریبی</th></tr></thead><tbody><?php foreach($summary['rows'] as $row): ?><tr><td><?=e($row['visitor_name'] ?? '-')?></td><td><?=e($row['line_code'] ?? '-')?></td><td><?=e(number_format((float)($row['net_sales'] ?? 0)))?></td><td><?=e(number_format((float)($row['qty'] ?? 0)))?></td><td><span class="achievement-pill <?=((float)($row['achievement_percent'] ?? 0)>=80?'achievement-good':'achievement-warn')?>"><?=e(number_format((float)($row['achievement_percent'] ?? 0),1))?>٪</span></td></tr><?php endforeach; ?><?php if(!$summary['rows']): ?><tr><td colspan="5">برای این بازه داده فروش ثبت نشده است.</td></tr><?php endif; ?></tbody></table></div></section>
<section class="card"><h2>اقدامات امروز و موارد فوری</h2><div class="table-wrap"><table><thead><tr><th>عنوان</th><th>اولویت</th><th>مهلت</th><th>وضعیت</th><th>عملیات</th></tr></thead><tbody><?php foreach($actionsToday as $action): ?><tr><td><?=e($action['title'])?></td><td><?=e(SalesOperationsService::priorityLabel($action['priority']))?></td><td><?=e($action['due_date'] ?: '-')?></td><td><span class="badge"><?=e(SalesOperationsService::statusLabel($action['status']))?></span></td><td><a class="btn btn-sm" href="/admin/supervisor-action-view.php?id=<?=(int)$action['id']?>">مشاهده</a></td></tr><?php endforeach; ?><?php if(!$actionsToday): ?><tr><td colspan="5">اقدام باز یا فوری برای امروز وجود ندارد.</td></tr><?php endif; ?></tbody></table></div></section>
<section class="card"><h2>خلاصه تیم</h2><p class="muted">ویزیتورهای فعال زیرمجموعه: <?=e((string)count($visitors))?></p><div class="table-wrap"><table><thead><tr><th>نام</th><th>لاین</th><th>نقش</th></tr></thead><tbody><?php foreach($visitors as $visitor): ?><tr><td><?=e($visitor['name'])?></td><td><?=e($visitor['sales_line'] ?? '-')?></td><td><?=e($visitor['role_key'] ?? '-')?></td></tr><?php endforeach; ?><?php if(!$visitors): ?><tr><td colspan="3">هنوز ویزیتوری به شما تخصیص داده نشده است.</td></tr><?php endif; ?></tbody></table></div></section>
<?php require __DIR__ . '/../views/partials/admin-footer.php'; ?>
