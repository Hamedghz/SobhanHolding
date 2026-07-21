<?php

require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../services/ActionHubService.php';

Auth::requireLogin();
ActionHubService::boot();
if (!ActionHubService::canManageTypes()) {
    http_response_code(403);
    exit('دسترسی غیرمجاز');
}
$actor = Auth::user();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) throw new DomainException('اعتبار فرم منقضی شده است.');
        $id = ActionHubService::saveType($_POST, (int)$actor['id']);
        Auth::log((int)$actor['id'], 'action_type_saved', 'action_types', $id);
        flash('نوع اقدام ذخیره شد.');
    } catch (InvalidArgumentException|DomainException $e) {
        flash($e->getMessage(), 'danger');
    } catch (Throwable $e) {
        error_log('Action type: ' . $e->getMessage());
        flash('ذخیره نوع اقدام انجام نشد.', 'danger');
    }
    redirect('/admin/action-types.php');
}
$types = ActionHubService::types(false);
$editId = max(0, (int)($_GET['edit'] ?? 0));
$edit = $editId ? Database::fetch('SELECT * FROM action_types WHERE id=?', [$editId]) : null;
$pageTitle = 'انواع اقدام';
$adminBodyClasses[] = 'app-compact-ui';
$adminExtraStylesheets = ['/assets/css/action-hub.css'];
$adminExtraScripts = ['/assets/js/action-hub.js'];
require __DIR__ . '/../views/partials/admin-header.php';
?>
<div class="action-hub-page">
    <section class="action-hero" data-action-reveal><div><span class="action-kicker">ACTION TAXONOMY</span><h1>انواع اقدام</h1><p>نوع اقدام قواعد مشترک، رنگ، آیکن و الزام تأیید را برای تمام قالب‌ها و اقدام‌ها مشخص می‌کند.</p></div><div class="action-hero-tools"><a class="btn" href="/admin/action-hub.php">مرکز اقدامات</a><a class="btn" href="/admin/action-templates.php">قالب‌ها</a></div></section>
    <section class="action-admin-grid">
        <form class="card action-editor" method="post" data-action-reveal>
            <input type="hidden" name="csrf_token" value="<?=e(Auth::csrfToken())?>">
            <input type="hidden" name="id" value="<?=(int)($edit['id']??0)?>">
            <div class="action-subheading"><b><?=$edit?'ویرایش نوع اقدام':'نوع اقدام جدید'?></b><small>کد پس از استفاده بهتر است ثابت بماند.</small></div>
            <div class="action-form-grid">
                <label class="form-field"><span>عنوان فارسی *</span><input name="title" required maxlength="190" value="<?=e($edit['title']??'')?>"></label>
                <label class="form-field"><span>کد *</span><input name="code" dir="ltr" required pattern="[A-Za-z0-9_-]+" value="<?=e($edit['code']??'')?>"></label>
                <label class="form-field"><span>رنگ</span><input type="color" name="color" value="<?=e($edit['color']??'#2563eb')?>"></label>
                <label class="form-field"><span>آیکن</span><input name="icon" dir="ltr" maxlength="80" value="<?=e($edit['icon']??'')?>" placeholder="check-circle"></label>
                <label class="form-field"><span>ترتیب</span><input type="number" name="sort_order" value="<?=e((string)($edit['sort_order']??10))?>"></label>
                <label class="form-field"><span>فیلدهای پایه الزامی</span><input name="required_fields_csv" dir="ltr" value="<?=e($edit['required_fields_csv']??'title,assigned_to')?>"></label>
                <label class="form-field action-span-2"><span>توضیحات</span><textarea name="description" rows="3"><?=e($edit['description']??'')?></textarea></label>
            </div>
            <div class="action-check-row"><label class="action-check"><input type="checkbox" name="active" value="1" <?=($edit['active']??1)?'checked':''?>> فعال</label><label class="action-check"><input type="checkbox" name="requires_approval" value="1" <?=($edit['requires_approval']??0)?'checked':''?>> نیازمند تأیید نهایی</label></div>
            <div class="form-actions"><button class="btn btn-primary">ذخیره</button><?php if($edit):?><a class="btn" href="/admin/action-types.php">انصراف</a><?php endif;?></div>
        </form>
        <section class="card action-type-list" data-action-reveal>
            <div class="action-subheading"><b>انواع ثبت‌شده</b><small><?=count($types)?> نوع</small></div>
            <?php foreach($types as $type):?><a href="?edit=<?=(int)$type['id']?>" class="action-type-row"><i style="--type-color:<?=e($type['color'])?>"></i><span><b><?=e($type['title'])?></b><small><?=e($type['code'])?><?=((int)$type['requires_approval'])?' — تأیید الزامی':''?></small></span><em><?=((int)$type['active'])?'فعال':'غیرفعال'?></em></a><?php endforeach;?>
        </section>
    </section>
</div>
<?php require __DIR__ . '/../views/partials/admin-footer.php'; ?>
