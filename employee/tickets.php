<?php
require_once __DIR__.'/../core/Auth.php';
require_once __DIR__.'/../core/Response.php';
require_once __DIR__.'/../services/TicketService.php';

Auth::requireLogin();
$filters = [
    'status' => trim((string)($_GET['status'] ?? '')),
    'priority' => trim((string)($_GET['priority'] ?? '')),
    'q' => trim((string)($_GET['q'] ?? '')),
    'overdue' => !empty($_GET['overdue']),
];
$rows = TicketService::list($filters);
$stats = TicketService::dashboardStats($filters);
$pageTitle = 'تیکت‌های من';
require __DIR__.'/../views/partials/admin-header.php';
?>
<div class="section-heading-row">
    <div>
        <h1>تیکت‌های من</h1>
        <p class="muted">درخواست‌های پشتیبانی مستقل از گفتگوهای پیام‌رسان</p>
    </div>
    <?php if(TicketService::canCreate()): ?><a class="btn btn-primary" href="/employee/ticket-create.php">تیکت جدید</a><?php endif; ?>
</div>
<section class="stats">
    <?php foreach(['total'=>'کل','open'=>'باز/ارجاع‌شده','waiting'=>'در انتظار','overdue'=>'SLA گذشته','resolved'=>'حل‌شده'] as $key=>$label): ?>
        <article class="stat-card"><span><?=e($label)?></span><strong><?=format_number($stats[$key] ?? 0)?></strong></article>
    <?php endforeach; ?>
</section>
<form class="card filter-form">
    <label>جستجو<input name="q" value="<?=e($filters['q'])?>" placeholder="شماره یا عنوان"></label>
    <label>وضعیت<select name="status"><option value="">همه</option><?php foreach(TicketService::STATUSES as $key=>$label):?><option value="<?=$key?>" <?=$filters['status']===$key?'selected':''?>><?=e($label)?></option><?php endforeach?></select></label>
    <label>اولویت<select name="priority"><option value="">همه</option><?php foreach(TicketService::PRIORITIES as $key=>$label):?><option value="<?=$key?>" <?=$filters['priority']===$key?'selected':''?>><?=e($label)?></option><?php endforeach?></select></label>
    <label><input type="checkbox" name="overdue" value="1" <?=$filters['overdue']?'checked':''?>> فقط SLA گذشته</label>
    <button class="btn">اعمال</button>
    <a class="btn" href="/employee/tickets.php">پاکسازی</a>
</form>
<section class="card">
    <div class="table-wrap">
        <table>
            <thead><tr><th>شماره</th><th>عنوان</th><th>دسته</th><th>مسئول</th><th>اولویت</th><th>وضعیت</th><th>آخرین بروزرسانی</th><th></th></tr></thead>
            <tbody>
            <?php foreach($rows as $ticket): ?>
                <tr class="<?=!empty($ticket['is_overdue'])?'danger-row':''?>">
                    <td><?=e($ticket['ticket_no'])?></td>
                    <td><strong><?=e($ticket['subject'])?></strong><small><?=format_number($ticket['message_count'] ?? 0)?> پیام</small></td>
                    <td><?=e($ticket['category_title'])?></td>
                    <td><?=e($ticket['assignee_name'] ?: $ticket['unit_title'] ?: '—')?></td>
                    <td><?=e(TicketService::PRIORITIES[$ticket['priority']] ?? $ticket['priority'])?></td>
                    <td><span class="badge"><?=e(TicketService::STATUSES[$ticket['status']] ?? $ticket['status'])?></span></td>
                    <td><?=e(format_jalali_datetime($ticket['updated_at']))?></td>
                    <td><a class="btn btn-small" href="/employee/ticket-view.php?id=<?=$ticket['id']?>">مشاهده</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if(!$rows): ?><tr><td colspan="8">هنوز تیکتی ثبت نشده یا مطابق فیلتر وجود ندارد.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require __DIR__.'/../views/partials/admin-footer.php'; ?>
