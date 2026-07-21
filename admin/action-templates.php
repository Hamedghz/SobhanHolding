<?php

require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../services/ActionHubService.php';

Auth::requireLogin();
ActionHubService::boot();
if (!ActionHubService::canManageTemplates()) {
    http_response_code(403);
    exit('دسترسی غیرمجاز');
}
$actor = Auth::user();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) throw new DomainException('اعتبار فرم منقضی شده است.');
        $operation = (string)($_POST['operation'] ?? '');
        if ($operation === 'template') {
            $id = ActionHubService::saveTemplate($_POST, (int)$actor['id']);
            Auth::log((int)$actor['id'], 'action_template_saved', 'action_templates', $id);
            flash('قالب اقدام ذخیره شد.');
            redirect('/admin/action-templates.php?template_id=' . $id);
        }
        if ($operation === 'field') {
            $id = ActionHubService::saveTemplateField($_POST);
            Auth::log((int)$actor['id'], 'action_template_field_saved', 'action_template_fields', $id);
            flash('فیلد قالب ذخیره شد.');
            redirect('/admin/action-templates.php?template_id=' . (int)$_POST['template_id']);
        }
        throw new InvalidArgumentException('عملیات معتبر نیست.');
    } catch (InvalidArgumentException|DomainException $e) {
        flash($e->getMessage(), 'danger');
    } catch (Throwable $e) {
        error_log('Action template: ' . $e->getMessage());
        flash('ذخیره قالب انجام نشد.', 'danger');
    }
    redirect('/admin/action-templates.php');
}

$types = ActionHubService::types(false);
$templates = ActionHubService::templates(false);
$templateId = max(0, (int)($_GET['template_id'] ?? 0));
$template = $templateId ? ActionHubService::template($templateId) : null;
$editFieldId = max(0, (int)($_GET['field'] ?? 0));
$editField = $editFieldId ? Database::fetch('SELECT * FROM action_template_fields WHERE id=? AND template_id=?', [$editFieldId,$templateId]) : null;
$pageTitle = 'قالب‌های اقدام';
$adminBodyClasses[] = 'app-compact-ui';
$adminExtraStylesheets = ['/assets/css/action-hub.css'];
$adminExtraScripts = ['/assets/js/action-hub.js'];
require __DIR__ . '/../views/partials/admin-header.php';
?>
<div class="action-hub-page">
    <?php if(($_GET['legacy']??'')==='1'):?>
    <div class="alert alert-info" data-action-reveal>
        مسیر قدیمی «اسکریپت فروش» به قالب‌های اقدام منتقل شده است. رکوردهای قبلی بدون حذف داده، با نشان «منبع قدیمی فروش» در همین بخش نگهداری می‌شوند.
    </div>
    <?php endif;?>
    <section class="action-hero" data-action-reveal><div><span class="action-kicker">ACTION WORKFLOWS</span><h1>قالب‌ها و فرآیندهای اقدام</h1><p>ساختار اقدام را با فیلدهای فارسی تعریف کنید؛ کاربر هیچ JSON یا تنظیم فنی خامی نمی‌بیند.</p></div><div class="action-hero-tools"><a class="btn" href="/admin/action-hub.php">مرکز اقدامات</a><?php if(ActionHubService::canManageTypes()):?><a class="btn" href="/admin/action-types.php">انواع اقدام</a><?php endif;?></div></section>

    <div class="action-template-layout">
        <aside class="card action-template-nav" data-action-reveal>
            <div class="action-subheading"><b>قالب‌ها</b><a href="/admin/action-templates.php">＋ جدید</a></div>
            <?php foreach($templates as $item):?><a class="<?=$templateId===(int)$item['id']?'is-active':''?>" href="?template_id=<?=(int)$item['id']?>"><span><b><?=e($item['title'])?></b><small><?=e($item['action_type_title'])?> — <?=(int)$item['field_count']?> فیلد</small></span><em><?=((int)$item['active'])?'فعال':'خاموش'?></em></a><?php endforeach;?>
            <?php if(!$templates):?><p class="muted">هنوز قالبی ثبت نشده است.</p><?php endif;?>
        </aside>

        <main>
            <form class="card action-editor" method="post" data-action-reveal>
                <input type="hidden" name="csrf_token" value="<?=e(Auth::csrfToken())?>">
                <input type="hidden" name="operation" value="template">
                <input type="hidden" name="id" value="<?=(int)($template['id']??0)?>">
                <div class="action-subheading"><b><?=$template?'ویرایش قالب':'قالب جدید'?></b><small>قالب، مسیر و اطلاعات مورد نیاز اقدام را یکسان می‌کند.</small></div>
                <div class="action-form-grid">
                    <label class="form-field"><span>عنوان فارسی *</span><input name="title" required maxlength="190" value="<?=e($template['title']??'')?>"></label>
                    <label class="form-field"><span>کد قالب *</span><input name="template_code" dir="ltr" required pattern="[A-Za-z0-9_-]+" value="<?=e($template['template_code']??'')?>"></label>
                    <label class="form-field"><span>نوع اقدام *</span><select name="action_type_id" required><option value="">انتخاب کنید</option><?php foreach($types as $type):?><option value="<?=(int)$type['id']?>" <?=((int)($template['action_type_id']??0)===(int)$type['id'])?'selected':''?>><?=e($type['title'])?></option><?php endforeach;?></select></label>
                    <label class="action-check"><input type="checkbox" name="active" value="1" <?=($template['active']??1)?'checked':''?>> قالب فعال است</label>
                    <label class="form-field action-span-2"><span>توضیحات</span><textarea name="description" rows="3"><?=e($template['description']??'')?></textarea></label>
                    <label class="form-field action-span-2"><span>راهنمای تکمیل</span><textarea name="instructions" rows="3"><?=e($template['instructions']??'')?></textarea></label>
                </div>
                <div class="form-actions"><button class="btn btn-primary">ذخیره قالب</button></div>
            </form>

            <?php if($template):?>
            <form class="card action-editor" method="post" data-action-reveal>
                <input type="hidden" name="csrf_token" value="<?=e(Auth::csrfToken())?>">
                <input type="hidden" name="operation" value="field">
                <input type="hidden" name="template_id" value="<?=$templateId?>">
                <input type="hidden" name="id" value="<?=(int)($editField['id']??0)?>">
                <div class="action-subheading"><b><?=$editField?'ویرایش فیلد':'فیلد جدید'?></b><small>همه تنظیمات با ورودی فارسی و کنترل‌شده ثبت می‌شوند.</small></div>
                <div class="action-form-grid">
                    <label class="form-field"><span>عنوان فیلد *</span><input name="field_label" required maxlength="190" value="<?=e($editField['field_label']??'')?>"></label>
                    <label class="form-field"><span>کلید فیلد *</span><input name="field_key" dir="ltr" required pattern="[A-Za-z0-9_-]+" value="<?=e($editField['field_key']??'')?>"></label>
                    <label class="form-field"><span>نوع فیلد *</span><select name="field_type" required><?php foreach(ActionHubService::FIELD_TYPES as $key=>$label):?><option value="<?=e($key)?>" <?=($editField['field_type']??'short_text')===$key?'selected':''?>><?=e($label)?></option><?php endforeach;?></select></label>
                    <label class="form-field"><span>ترتیب نمایش</span><input type="number" name="sort_order" value="<?=e((string)($editField['sort_order']??10))?>"></label>
                    <label class="form-field"><span>متن راهنما داخل فیلد</span><input name="placeholder" value="<?=e($editField['placeholder']??'')?>"></label>
                    <label class="form-field"><span>مقدار پیش‌فرض</span><input name="default_value" value="<?=e($editField['default_value']??'')?>"></label>
                    <label class="form-field action-span-2"><span>راهنمای زیر فیلد</span><input name="help_text" value="<?=e($editField['help_text']??'')?>"></label>
                    <label class="form-field action-span-2"><span>گزینه‌ها</span><textarea name="options_text" rows="4" placeholder="open|باز&#10;done|انجام‌شده"><?=e(ActionHubService::optionsText($editField['options_json']??null))?></textarea><small>هر گزینه در یک خط و به شکل «مقدار|عنوان فارسی»؛ فقط برای انتخاب تکی و چندتایی.</small></label>
                    <label class="form-field action-span-2"><span>فرمول یا منبع محاسبه</span><input name="formula_expression" dir="ltr" value="<?=e($editField['formula_expression']??'')?>" placeholder="برای فیلد محاسباتی"></label>
                </div>
                <div class="action-check-row"><label class="action-check"><input type="checkbox" name="required" value="1" <?=($editField['required']??0)?'checked':''?>> اجباری</label><label class="action-check"><input type="checkbox" name="readonly" value="1" <?=($editField['readonly']??0)?'checked':''?>> فقط خواندنی</label><label class="action-check"><input type="checkbox" name="active" value="1" <?=($editField['active']??1)?'checked':''?>> فعال</label></div>
                <div class="form-actions"><button class="btn btn-primary">ذخیره فیلد</button><?php if($editField):?><a class="btn" href="?template_id=<?=$templateId?>">انصراف</a><?php endif;?></div>
            </form>

            <section class="card action-fields-list" data-action-reveal>
                <div class="action-subheading"><b>فیلدهای «<?=e($template['title'])?>»</b><small><?=count($template['fields'])?> فیلد</small></div>
                <?php foreach($template['fields'] as $field):?><a href="?template_id=<?=$templateId?>&field=<?=(int)$field['id']?>"><span><b><?=e($field['field_label'])?></b><small><?=e(ActionHubService::FIELD_TYPES[$field['field_type']]??$field['field_type'])?> — <?=e($field['field_key'])?></small></span><em><?=(int)$field['required']?'اجباری':'اختیاری'?></em></a><?php endforeach;?>
                <?php if(!$template['fields']):?><p class="muted">برای این قالب هنوز فیلدی تعریف نشده است.</p><?php endif;?>
            </section>
            <?php endif;?>
        </main>
    </div>
</div>
<?php require __DIR__ . '/../views/partials/admin-footer.php'; ?>
