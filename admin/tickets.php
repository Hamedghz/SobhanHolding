<?php
require_once __DIR__.'/../core/Auth.php';
require_once __DIR__.'/../core/Database.php';
require_once __DIR__.'/../core/Response.php';
require_once __DIR__.'/../services/TicketService.php';

Auth::requireLogin();
if (!TicketService::canManage()) {
    http_response_code(403);
    $pageTitle = 'مدیریت تیکت‌ها';
    require __DIR__.'/../views/partials/admin-header.php';
    echo '<section class="card"><h1>دسترسی مدیریت تیکت‌ها برای شما فعال نیست</h1><p class="muted">برای مشاهده تیکت‌های سازمانی، مجوز ticketing.manage لازم است.</p></section>';
    require __DIR__.'/../views/partials/admin-footer.php';
    exit;
}

$filters = [
    'status' => trim((string)($_GET['status'] ?? '')),
    'priority' => trim((string)($_GET['priority'] ?? '')),
    'category_id' => (int)($_GET['category_id'] ?? 0),
    'assigned_user_id' => (int)($_GET['assigned_user_id'] ?? 0),
    'assigned_unit_id' => (int)($_GET['assigned_unit_id'] ?? 0),
    'q' => trim((string)($_GET['q'] ?? '')),
    'overdue' => !empty($_GET['overdue']),
];
$rows = TicketService::list($filters);
$stats = TicketService::dashboardStats($filters);
$categories = TicketService::categories(false);
$users = Database::fetchAll('SELECT id,name FROM users WHERE status="active" ORDER BY name');
$units = Database::fetchAll('SELECT id,title FROM org_units WHERE active=1 ORDER BY sort_order,title');
$pageTitle = 'مدیریت تیکت‌ها';
require __DIR__.'/../views/partials/admin-header.php';
?>
<div class="section-heading-row">
    <div>
        <h1>مدیریت تیکت‌ها</h1>
        <p class="muted">صف مستقل رسیدگی به درخواست‌ها؛ بدون مدیریت در Inbox پیام‌رسان</p>
    </div>
    <div>
        <a class="btn btn-primary" href="/employee/ticket-create.php">ثبت تیکت جدید</a>
        <a class="btn" href="/admin/ticket-categories.php">دسته‌بندی‌ها</a>
        <a class="btn" href="/admin/ticket-settings.php">SLA و تنظیمات</a>
    </div>
</div>
<section class="stats">
    <?php foreach(['total'=>'کل','open'=>'باز/ارجاع‌شده','in_progress'=>'در حال انجام','waiting'=>'در انتظار','overdue'=>'SLA گذشته','urgent'=>'فوری','unassigned'=>'بدون مسئول','resolved'=>'حل‌شده'] as $key=>$label): ?>
        <article class="stat-card"><span><?=e($label)?></span><strong><?=format_number($stats[$key] ?? 0)?></strong></article>
    <?php endforeach; ?>
</section>
<form class="card filter-form">
    <label>جستجو<input name="q" value="<?=e($filters['q'])?>" placeholder="شماره، عنوان یا درخواست‌کننده"></label>
    <label>وضعیت<select name="status"><option value="">همه</option><?php foreach(TicketService::STATUSES as $key=>$label):?><option value="<?=$key?>" <?=$filters['status']===$key?'selected':''?>><?=e($label)?></option><?php endforeach?></select></label>
    <label>اولویت<select name="priority"><option value="">همه</option><?php foreach(TicketService::PRIORITIES as $key=>$label):?><option value="<?=$key?>" <?=$filters['priority']===$key?'selected':''?>><?=e($label)?></option><?php endforeach?></select></label>
    <label>دسته<select name="category_id"><option value="">همه</option><?php foreach($categories as $category):?><option value="<?=$category['id']?>" <?=$filters['category_id']==$category['id']?'selected':''?>><?=e($category['title'])?></option><?php endforeach?></select></label>
    <label>مسئول<select name="assigned_user_id"><option value="">همه</option><?php foreach($users as $member):?><option value="<?=$member['id']?>" <?=$filters['assigned_user_id']==$member['id']?'selected':''?>><?=e($member['name'])?></option><?php endforeach?></select></label>
    <label>واحد<select name="assigned_unit_id"><option value="">همه</option><?php foreach($units as $unit):?><option value="<?=$unit['id']?>" <?=$filters['assigned_unit_id']==$unit['id']?'selected':''?>><?=e($unit['title'])?></option><?php endforeach?></select></label>
    <label><input type="checkbox" name="overdue" value="1" <?=$filters['overdue']?'checked':''?>> فقط SLA گذشته</label>
    <button class="btn btn-primary">اعمال</button>
    <a class="btn" href="/admin/tickets.php">پاکسازی</a>
</form>
<section class="card">
    <div class="table-wrap">
        <table>
            <thead><tr><th>شماره</th><th>عنوان / درخواست‌کننده</th><th>دسته</th><th>مسئول</th><th>اولویت</th><th>وضعیت</th><th>SLA / مهلت</th><th></th></tr></thead>
            <tbody>
            <?php foreach($rows as $ticket): ?>
                <tr class="<?=!empty($ticket['is_overdue'])?'danger-row':''?>">
                    <td><?=e($ticket['ticket_no'])?></td>
                    <td><strong><?=e($ticket['subject'])?></strong><small><?=e($ticket['requester_name'])?> · <?=format_number($ticket['message_count'] ?? 0)?> پیام</small></td>
                    <td><?=e($ticket['category_title'])?></td>
                    <td><?=e($ticket['assignee_name'] ?: $ticket['unit_title'] ?: '—')?></td>
                    <td><?=e(TicketService::PRIORITIES[$ticket['priority']] ?? $ticket['priority'])?></td>
                    <td><span class="badge"><?=e(TicketService::STATUSES[$ticket['status']] ?? $ticket['status'])?></span></td>
                    <td><?=e($ticket['due_at'] && function_exists('format_jalali_datetime') ? format_jalali_datetime($ticket['due_at']) : ($ticket['due_at'] ?: '—'))?><?php if(!empty($ticket['is_overdue'])):?><small>گذشته از SLA</small><?php endif;?></td>
                    <td><a class="btn btn-small" href="/employee/ticket-view.php?id=<?=$ticket['id']?>">رسیدگی</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if(!$rows): ?><tr><td colspan="8"><strong>هنوز تیکتی برای نمایش وجود ندارد.</strong><br><span class="muted">برای تست جریان ارسال/دریافت/ارجاع، از دکمه «ثبت تیکت جدید» یک تیکت بسازید یا فیلترها را پاک کنید.</span></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require __DIR__.'/../views/partials/admin-footer.php'; ?>
