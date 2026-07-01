<?php
require_once __DIR__.'/../core/Auth.php';
require_once __DIR__.'/../core/Response.php';
require_once __DIR__.'/../core/Upload.php';
require_once __DIR__.'/../services/TicketService.php';

Auth::requireLogin();
$user = Auth::user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!Auth::verifyCsrf($_POST['_csrf'] ?? null)) throw new DomainException('اعتبار فرم منقضی شده است.');
        $id = TicketService::create($_POST, (int)$user['id']);

        $assignedUserId = (int)($_POST['assigned_user_id'] ?? 0);
        $assignedUnitId = (int)($_POST['assigned_unit_id'] ?? 0);
        if ($assignedUserId || $assignedUnitId) {
            $targetUser = $assignedUserId ? Database::fetch('SELECT id,name FROM users WHERE id=? AND status="active"', [$assignedUserId]) : null;
            $targetUnit = $assignedUnitId ? Database::fetch('SELECT id,title FROM org_units WHERE id=? AND active=1', [$assignedUnitId]) : null;
            if ($assignedUserId && !$targetUser) throw new InvalidArgumentException('عضو گیرنده معتبر نیست.');
            if ($assignedUnitId && !$targetUnit) throw new InvalidArgumentException('واحد گیرنده معتبر نیست.');

            Database::execute(
                'UPDATE tickets SET assigned_user_id=?,assigned_unit_id=?,status=IF(status="open","assigned",status),updated_at=NOW() WHERE id=? AND requester_user_id=?',
                [$assignedUserId ?: null, $assignedUnitId ?: null, $id, (int)$user['id']]
            );
            Database::execute(
                'INSERT INTO ticket_assignments(ticket_id,assigned_user_id,assigned_unit_id,assigned_by,note,created_at) VALUES(?,?,?,?,?,NOW())',
                [$id, $assignedUserId ?: null, $assignedUnitId ?: null, (int)$user['id'], 'ارسال/ارجاع مستقیم هنگام ثبت تیکت']
            );
            Database::execute(
                'INSERT INTO ticket_status_logs(ticket_id,actor_user_id,old_status,new_status,note,created_at) VALUES(?,?,?,?,?,NOW())',
                [$id, (int)$user['id'], 'open', 'assigned', 'ارسال مستقیم به عضو/واحد']
            );
        }

        if (($_FILES['attachment']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $saved = Upload::save($_FILES['attachment'], 'uploads/tickets', ['pdf','doc','docx','xls','xlsx','jpg','jpeg','png','webp','txt','zip'], 10*1024*1024);
            if (!$saved['ok']) throw new InvalidArgumentException($saved['error']);
            $message = Database::fetch('SELECT id FROM ticket_messages WHERE ticket_id=? ORDER BY id LIMIT 1', [$id]);
            TicketService::addAttachment($id, (int)($message['id'] ?? 0), (int)$user['id'], $saved);
        }
        flash('تیکت با موفقیت ثبت شد.');
        redirect('/employee/ticket-view.php?id='.$id);
    } catch (InvalidArgumentException|DomainException $e) {
        flash($e->getMessage(), 'danger');
    } catch (Throwable $e) {
        error_log('Ticket create: '.$e->getMessage());
        flash('ثبت تیکت انجام نشد.', 'danger');
    }
}

$categories = TicketService::categories();
$users = Database::fetchAll('SELECT id,name,department,role_key FROM users WHERE status="active" AND id<>? ORDER BY name LIMIT 500', [(int)$user['id']]);
$units = Database::fetchAll('SELECT id,title FROM org_units WHERE active=1 ORDER BY sort_order,title');
$pageTitle = 'تیکت جدید';
require __DIR__.'/../views/partials/admin-header.php';
?>
<section class="card admin-form">
    <div class="section-heading-row">
        <div>
            <h1>ثبت تیکت جدید</h1>
            <p class="muted">تیکت برای ارسال، دریافت و ارجاع درخواست است؛ پیام‌رسان فقط برای گفتگو و اعلان استفاده می‌شود.</p>
        </div>
        <a class="btn" href="/employee/tickets.php">بازگشت</a>
    </div>
    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="_csrf" value="<?=e(Auth::csrfToken())?>">
        <label class="form-field"><span>عنوان</span><input name="subject" maxlength="255" required></label>
        <div class="grid grid-2">
            <label class="form-field"><span>دسته‌بندی</span><select name="category_id" required><?php foreach($categories as $category):?><option value="<?=$category['id']?>"><?=e($category['title'])?></option><?php endforeach?></select></label>
            <label class="form-field"><span>اولویت</span><select name="priority"><?php foreach(TicketService::PRIORITIES as $key=>$label):?><option value="<?=$key?>"><?=e($label)?></option><?php endforeach?></select></label>
        </div>
        <div class="grid grid-2">
            <label class="form-field"><span>ارسال/ارجاع به عضو</span><select name="assigned_user_id"><option value="">بر اساس دسته‌بندی یا بدون عضو مشخص</option><?php foreach($users as $member):?><option value="<?=$member['id']?>"><?=e($member['name'])?><?=!empty($member['department'])?' — '.e($member['department']):(!empty($member['role_key'])?' — '.e($member['role_key']):'')?></option><?php endforeach?></select></label>
            <label class="form-field"><span>ارسال/ارجاع به واحد</span><select name="assigned_unit_id"><option value="">بدون واحد مشخص</option><?php foreach($units as $unit):?><option value="<?=$unit['id']?>"><?=e($unit['title'])?></option><?php endforeach?></select></label>
        </div>
        <label class="form-field"><span>شرح درخواست</span><textarea name="message" rows="8" maxlength="20000" required></textarea></label>
        <label class="form-field"><span>پیوست اختیاری</span><input type="file" name="attachment" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.webp,.txt,.zip"></label>
        <button class="btn btn-primary">ثبت و ارسال تیکت</button>
    </form>
</section>
<?php require __DIR__.'/../views/partials/admin-footer.php'; ?>
