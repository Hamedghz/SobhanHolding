<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../services/SalesOperationsService.php';

SalesOperationsService::boot();
SalesOperationsService::ensureSupervisorAccess();
$user = Auth::user();
$supervisorId = (int)$user['id'];
[$from,$to] = SalesOperationsService::dateFilters($_GET);
$visitorId = (int)($_GET['visitor_id'] ?? 0);
if ($visitorId && !SalesOperationsService::assertVisitorBelongsToSupervisor($visitorId, $supervisorId)) { http_response_code(403); exit('دسترسی غیرمجاز'); }
$visitors = SalesOperationsService::getSupervisorVisitors($supervisorId);
$summary = SalesOperationsService::getSupervisorSalesSummary($supervisorId, ['from'=>$from,'to'=>$to]);
$rows = $summary['rows'];
if ($visitorId) $rows = array_values(array_filter($rows, fn($r)=>(int)($r['user_id'] ?? 0)===$visitorId));
$pageTitle = 'گزارش فروش ویزیتورها';
require __DIR__ . '/../views/partials/admin-header.php';
?>
<div class="section-heading-row"><div><h1>گزارش فروش ویزیتورهای زیرمجموعه</h1><p class="muted">این گزارش فقط داده‌های ویزیتورهای زیرمجموعه سرپرست جاری را نمایش می‌دهد.</p></div><div class="actions"><a class="btn" href="/admin/supervisor-dashboard.php">داشبورد سرپرست</a><button class="btn" onclick="window.print()">چاپ گزارش</button></div></div>
<form class="card admin-form" method="get"><h2>فیلتر گزارش</h2><div class="grid grid-4"><label class="form-field"><span>از تاریخ</span><input type="date" name="from" value="<?=e($from)?>"></label><label class="form-field"><span>تا تاریخ</span><input type="date" name="to" value="<?=e($to)?>"></label><label class="form-field"><span>ویزیتور</span><select name="visitor_id"><option value="0">همه</option><?php foreach($visitors as $visitor): ?><option value="<?=(int)$visitor['id']?>" <?=$visitorId===(int)$visitor['id']?'selected':''?>><?=e($visitor['name'])?></option><?php endforeach; ?></select></label></div><div class="form-actions"><button class="btn btn-primary">اعمال فیلتر</button><a class="btn" href="/admin/supervisor-sales-report.php">پاکسازی</a></div></form>
<div class="stats"><div class="stat-card"><span>فروش بازه</span><strong><?=e(number_format(array_sum(array_map(fn($r)=>(float)($r['net_sales'] ?? 0), $rows))))?></strong></div><div class="stat-card"><span>تعداد ویزیتورها</span><strong><?=e((string)count($rows))?></strong></div><div class="stat-card"><span>میانگین تحقق</span><strong><?=count($rows)?e(number_format(array_sum(array_map(fn($r)=>(float)($r['achievement_percent'] ?? 0), $rows))/count($rows),1)).'٪':'۰٪'?></strong></div></div>
<section class="card"><h2>گزارش تفکیکی ویزیتورها</h2><div class="table-wrap"><table><thead><tr><th>ویزیتور</th><th>لاین</th><th>فروش خالص</th><th>تعداد/مقدار</th><th>تحقق</th></tr></thead><tbody><?php foreach($rows as $row): ?><tr><td><?=e($row['visitor_name'] ?? '-')?></td><td><?=e($row['line_code'] ?? '-')?></td><td><?=e(number_format((float)($row['net_sales'] ?? 0)))?></td><td><?=e(number_format((float)($row['qty'] ?? 0)))?></td><td><span class="achievement-pill <?=((float)($row['achievement_percent'] ?? 0)>=80?'achievement-good':'achievement-warn')?>"><?=e(number_format((float)($row['achievement_percent'] ?? 0),1))?>٪</span></td></tr><?php endforeach; ?><?php if(!$rows): ?><tr><td colspan="5">گزارشی برای فیلتر انتخاب‌شده وجود ندارد.</td></tr><?php endif; ?></tbody></table></div></section>
<?php require __DIR__ . '/../views/partials/admin-footer.php'; ?>
