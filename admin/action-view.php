<?php

require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../services/ActionHubService.php';

Auth::requireLogin();
ActionHubService::boot();
$actor = Auth::user();
$id = max(0, (int)($_GET['id'] ?? $_POST['id'] ?? 0));
$action = ActionHubService::action($id, $actor);
if (!$action) {
    http_response_code(404);
    exit('اقدام پیدا نشد یا در محدوده دسترسی شما نیست.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) throw new DomainException('اعتبار فرم منقضی شده است.');
        $operation = (string)($_POST['operation'] ?? '');
        if ($operation === 'status') {
            ActionHubService::updateStatus($id, (string)($_POST['status'] ?? ''), $actor, (string)($_POST['note'] ?? ''));
            Auth::log((int)$actor['id'], 'action_status_changed', 'action_hub', $id);
            flash('وضعیت اقدام بروزرسانی شد.');
        } elseif ($operation === 'planner') {
            ActionHubService::syncToPlanner($id, (int)$actor['id']);
            flash('اقدام به برنامه کاری متصل شد.');
        } else {
            throw new InvalidArgumentException('عملیات معتبر نیست.');
        }
    } catch (InvalidArgumentException|DomainException $e) {
        flash($e->getMessage(), 'danger');
    } catch (Throwable $e) {
        error_log('Action hub view: ' . $e->getMessage());
        flash('عملیات انجام نشد. جزئیات فنی در لاگ ثبت شد.', 'danger');
    }
    redirect('/admin/action-view.php?id=' . $id);
}

$pageTitle = 'جزئیات اقدام';
$adminBodyClasses[] = 'app-compact-ui';
$adminExtraStylesheets = ['/assets/css/action-hub.css'];
$adminExtraScripts = ['/assets/js/action-hub.js'];
require __DIR__ . '/../views/partials/admin-header.php';

$fieldValue = static function(array $field): string {
    if (!empty($field['file_path'])) return 'file';
    if ($field['value_date']) return format_jalali_date($field['value_date']);
    if ($field['value_datetime']) return format_jalali_datetime($field['value_datetime']);
    if ($field['value_number'] !== null) return number_format((float)$field['value_number'], 2);
    if ($field['value_json']) {
        $values = json_decode($field['value_json'], true);
        return is_array($values) ? implode('، ', array_map('strval', $values)) : (string)$field['value_json'];
    }
    if (in_array($field['field_type'], ['yes_no','checkbox'], true)) return (string)$field['value_text'] === '1' ? 'بله' : 'خیر';
    return (string)($field['value_text'] ?? '—');
};
?>
<div class="action-hub-page">
    <section class="action-hero action-view-hero" data-action-reveal style="--action-color:<?=e($action['action_type_color'])?>">
        <div>
            <span class="action-kicker"><?=e($action['action_type_title'])?></span>
            <h1><?=e($action['title'])?></h1>
            <p><?=nl2br(e($action['description']?:'برای این اقدام توضیح تکمیلی ثبت نشده است.'))?></p>
        </div>
        <div class="action-hero-tools">
            <span class="action-priority is-<?=e($action['priority'])?>"><?=e(ActionHubService::PRIORITIES[$action['priority']]??'عادی')?></span>
            <span class="action-status is-<?=e($action['status'])?>"><?=e(ActionHubService::STATUSES[$action['status']]??$action['status'])?></span>
            <a class="btn" href="/admin/action-hub.php">بازگشت</a>
        </div>
    </section>

    <section class="action-view-grid">
        <main>
            <section class="card action-detail-card" data-action-reveal>
                <div class="action-subheading"><b>مشخصات اقدام</b><small>مالکیت، زمان‌بندی و منبع ثبت</small></div>
                <dl class="action-facts">
                    <div><dt>مسئول</dt><dd><?=e($action['assigned_to_name'])?></dd></div>
                    <div><dt>تخصیص‌دهنده</dt><dd><?=e($action['assigned_by_name'])?></dd></div>
                    <div><dt>قالب</dt><dd><?=e($action['template_title']?:'بدون قالب')?></dd></div>
                    <div><dt>منبع</dt><dd><?=e(ActionHubService::SOURCES[$action['source_type']]??$action['source_type'])?></dd></div>
                    <div><dt>شروع</dt><dd><?=e($action['start_date']?format_jalali_date($action['start_date']):'تعیین نشده')?></dd></div>
                    <div><dt>سررسید</dt><dd><?=e($action['due_date']?format_jalali_date($action['due_date']):'تعیین نشده')?></dd></div>
                    <div><dt>ایجاد</dt><dd><?=e(format_jalali_datetime($action['created_at']))?></dd></div>
                    <div><dt>تأیید نهایی</dt><dd><?=((int)$action['approval_required'])?'الزامی':'لازم نیست'?></dd></div>
                </dl>
            </section>

            <?php if($action['fields']):?>
            <section class="card action-detail-card" data-action-reveal>
                <div class="action-subheading"><b>اطلاعات قالب</b><small>مقادیر ثبت‌شده برای این اقدام</small></div>
                <dl class="action-facts action-field-facts">
                    <?php foreach($action['fields'] as $field):?><div><dt><?=e($field['field_label']?:$field['field_key'])?></dt><dd>
                        <?php if(!empty($field['file_path'])):?><a href="/admin/action-file.php?id=<?=(int)$field['id']?>"><?=e($field['file_name']?:'دریافت فایل')?></a>
                        <?php else:?><?=e($fieldValue($field))?><?php endif;?>
                    </dd></div><?php endforeach;?>
                </dl>
            </section>
            <?php endif;?>

            <?php if($action['links']):?>
            <section class="card action-detail-card" data-action-reveal>
                <div class="action-subheading"><b>پیوندهای مرتبط</b><small>ارتباط اقدام با سایر ماژول‌ها</small></div>
                <div class="action-links"><?php foreach($action['links'] as $link):?>
                    <?php if(!empty($link['link_url'])):?><a class="btn" href="<?=e($link['link_url'])?>"><?=e($link['label']?:'مشاهده پیوند')?></a>
                    <?php else:?><span class="action-link-chip"><?=e($link['label']?:$link['linked_type'])?> #<?=e((string)$link['linked_id'])?></span><?php endif;?>
                <?php endforeach;?></div>
            </section>
            <?php endif;?>
        </main>

        <aside>
            <form class="card action-status-form" method="post" data-action-reveal>
                <input type="hidden" name="csrf_token" value="<?=e(Auth::csrfToken())?>">
                <input type="hidden" name="id" value="<?=$id?>">
                <input type="hidden" name="operation" value="status">
                <div class="action-subheading"><b>بروزرسانی وضعیت</b><small>تغییرات در تاریخچه ثبت می‌شوند.</small></div>
                <label class="form-field"><span>وضعیت جدید</span><select name="status"><?php foreach(ActionHubService::STATUSES as $key=>$label):?><option value="<?=e($key)?>" <?=$action['status']===$key?'selected':''?>><?=e($label)?></option><?php endforeach;?></select></label>
                <label class="form-field"><span>یادداشت</span><textarea name="note" rows="3" maxlength="5000"></textarea></label>
                <button class="btn btn-primary">ثبت تغییر</button>
            </form>

            <?php if(empty($action['planner_task_id'])):?>
            <form class="card action-planner-card" method="post" data-action-reveal>
                <input type="hidden" name="csrf_token" value="<?=e(Auth::csrfToken())?>">
                <input type="hidden" name="id" value="<?=$id?>">
                <input type="hidden" name="operation" value="planner">
                <b>اتصال به برنامه کاری</b>
                <p>یک وظیفه قابل پیگیری برای مسئول اقدام ایجاد می‌شود.</p>
                <button class="btn">افزودن به برنامه کاری</button>
            </form>
            <?php endif;?>

            <section class="card action-timeline" data-action-reveal>
                <div class="action-subheading"><b>تاریخچه</b><small><?=count($action['logs'])?> رویداد</small></div>
                <?php foreach($action['logs'] as $log):?><article>
                    <span></span><div><b><?=e([
                        'created'=>'ایجاد اقدام','status_changed'=>'تغییر وضعیت','updated'=>'ویرایش','assigned'=>'تخصیص',
                    ][$log['action_key']]??$log['action_key'])?></b>
                    <small><?=e($log['performed_by_name']?:'سیستم')?> — <?=e(format_jalali_datetime($log['created_at']))?></small>
                    <?php if($log['note']):?><p><?=e($log['note'])?></p><?php endif;?></div>
                </article><?php endforeach;?>
            </section>
        </aside>
    </section>
</div>
<?php require __DIR__ . '/../views/partials/admin-footer.php'; ?>
