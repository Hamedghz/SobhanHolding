<?php

require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../services/DailyWorkReportService.php';

Auth::requireLogin();
DailyWorkReportService::boot();
$actor = Auth::user();
if (!DailyWorkReportService::canManageTemplates($actor)) {
    http_response_code(403);
    exit('دسترسی غیرمجاز');
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) throw new DomainException('اعتبار فرم منقضی شده است.');
        $operation = (string)($_POST['operation'] ?? '');
        if ($operation === 'save_template') {
            $id = DailyWorkReportService::saveTemplate($_POST,$actor);
            flash('قالب گزارش ذخیره شد.');
            redirect('/admin/daily-report-templates.php?template_id='.$id);
        }
        if ($operation === 'save_field') {
            DailyWorkReportService::saveField($_POST,$actor);
            flash('فیلد قالب ذخیره شد.');
            redirect('/admin/daily-report-templates.php?template_id='.(int)$_POST['template_id'].'#template-fields');
        }
        if ($operation === 'save_assignment') {
            DailyWorkReportService::saveAssignment($_POST,$actor);
            flash('تخصیص قالب ذخیره شد.');
            redirect('/admin/daily-report-templates.php?template_id='.(int)$_POST['template_id'].'#template-assignments');
        }
        throw new InvalidArgumentException('عملیات درخواست‌شده معتبر نیست.');
    } catch (InvalidArgumentException|DomainException $e) {
        $errors[] = $e->getMessage();
    } catch (Throwable $e) {
        error_log('Daily report templates: '.$e->getMessage());
        $errors[] = 'ذخیره قالب انجام نشد. جزئیات فنی در لاگ ثبت شد.';
    }
}

$templates = DailyWorkReportService::templates();
$templateId = (int)($_GET['template_id'] ?? $_POST['template_id'] ?? ($templates[0]['id'] ?? 0));
$template = $templateId ? DailyWorkReportService::template($templateId) : null;
$editFieldId = (int)($_GET['field_id'] ?? 0);
$editField = null;
foreach (($template['fields'] ?? []) as $field) if ((int)$field['id']===$editFieldId) {$editField=$field;break;}
$scopeOptions = DailyWorkReportService::scopeOptions($actor);
$assignmentScopes = DailyWorkReportService::assignmentScopes($actor);
$pageTitle = 'قالب‌های گزارش کار روزانه';
$adminBodyClasses[] = 'app-compact-ui';
$adminExtraStylesheets = ['/assets/css/daily-work-report.css'];
$adminExtraScripts = ['/assets/js/daily-work-report.js'];
require __DIR__ . '/../views/partials/admin-header.php';
?>
<div class="daily-template-page">
    <section class="daily-report-hero" data-daily-reveal>
        <div><span class="daily-report-kicker">REPORT TEMPLATE STUDIO</span><h1>قالب‌های گزارش کار روزانه</h1><p>قالب را بسازید، فیلدهای دستی یا خواندنی را انتخاب کنید و بدون ورود JSON به کاربر، نقش، واحد، لاین یا تیم تخصیص دهید.</p></div>
        <a class="btn" href="/admin/daily-work-report.php">بازگشت به گزارش روزانه</a>
    </section>
    <?php foreach($errors as $error):?><div class="alert alert-danger"><?=e($error)?></div><?php endforeach;?>

    <div class="daily-template-layout">
        <aside class="card daily-template-nav" data-daily-reveal>
            <header><b>قالب‌ها</b><a href="/admin/daily-report-templates.php?new=1">＋ قالب جدید</a></header>
            <?php foreach($templates as $item):?><a class="<?=((int)$item['id']===$templateId)?'is-active':''?>" href="/admin/daily-report-templates.php?template_id=<?=(int)$item['id']?>"><span><b><?=e($item['title'])?></b><small><?=e($item['template_code'])?> — نسخه <?=e((string)$item['version_no'])?></small></span><em><?=e((string)$item['field_count'])?> فیلد</em></a><?php endforeach;?>
        </aside>

        <main>
            <form class="card daily-template-editor" method="post" data-daily-reveal>
                <input type="hidden" name="csrf_token" value="<?=e(Auth::csrfToken())?>">
                <input type="hidden" name="operation" value="save_template">
                <input type="hidden" name="id" value="<?=isset($_GET['new'])?0:(int)($template['id']??0)?>">
                <header><h2><?=isset($_GET['new'])?'ایجاد قالب جدید':'مشخصات قالب'?></h2><span>تغییرات نسخه‌ای و غیردست‌خرب</span></header>
                <div class="grid grid-3">
                    <label class="form-field"><span>عنوان *</span><input name="title" required value="<?=e(isset($_GET['new'])?'':($template['title']??''))?>"></label>
                    <label class="form-field"><span>کد انگلیسی *</span><input name="template_code" required pattern="[a-zA-Z][a-zA-Z0-9_-]{2,99}" value="<?=e(isset($_GET['new'])?'':($template['template_code']??''))?>"></label>
                    <label class="form-field"><span>نسخه</span><input type="number" min="1" name="version_no" value="<?=e((string)(isset($_GET['new'])?1:($template['version_no']??1)))?>"></label>
                </div>
                <label class="form-field"><span>توضیحات</span><textarea name="description" rows="3"><?=e(isset($_GET['new'])?'':($template['description']??''))?></textarea></label>
                <label class="daily-planner-check"><input type="checkbox" name="active" value="1" <?=isset($_GET['new'])||($template['active']??1)?'checked':''?>> قالب فعال باشد</label>
                <div class="form-actions"><button class="btn btn-primary">ذخیره قالب</button></div>
            </form>

            <?php if($template && !isset($_GET['new'])):?>
            <section class="card daily-template-fields" id="template-fields" data-daily-reveal>
                <header><div><h2>فیلدهای پویا</h2><p>منبع و نوع نمایش را از گزینه‌های کنترل‌شده انتخاب کنید؛ JSON در رابط وجود ندارد.</p></div><span><?=e((string)count($template['fields']))?> فیلد</span></header>
                <div class="daily-field-list">
                    <?php foreach($template['fields'] as $field):?><a href="/admin/daily-report-templates.php?template_id=<?=$templateId?>&field_id=<?=(int)$field['id']?>#field-editor"><i class="is-<?=e($field['source_type'])?>"></i><span><b><?=e($field['field_label'])?></b><small><?=e(DailyWorkReportService::SOURCE_TYPES[$field['source_type']]??$field['source_type'])?> — <?=e(DailyWorkReportService::INPUT_TYPES[$field['input_type']]??$field['input_type'])?></small></span><em><?=e($field['field_key'])?></em></a><?php endforeach;?>
                </div>
            </section>

            <form class="card daily-field-editor" id="field-editor" method="post" data-daily-reveal>
                <input type="hidden" name="csrf_token" value="<?=e(Auth::csrfToken())?>">
                <input type="hidden" name="operation" value="save_field">
                <input type="hidden" name="template_id" value="<?=$templateId?>">
                <input type="hidden" name="id" value="<?=(int)($editField['id']??0)?>">
                <header><h2><?=$editField?'ویرایش فیلد':'افزودن فیلد'?></h2><span>منبع‌های غیر دستی به‌صورت اجباری فقط‌خواندنی هستند</span></header>
                <div class="grid grid-3">
                    <label class="form-field"><span>عنوان فارسی *</span><input name="field_label" required value="<?=e($editField['field_label']??'')?>"></label>
                    <label class="form-field"><span>کلید انگلیسی *</span><input name="field_key" required value="<?=e($editField['field_key']??'')?>"></label>
                    <label class="form-field"><span>نوع ورودی</span><select name="input_type"><?php foreach(DailyWorkReportService::INPUT_TYPES as $key=>$label):?><option value="<?=e($key)?>" <?=(($editField['input_type']??'long_text')===$key)?'selected':''?>><?=e($label)?></option><?php endforeach;?></select></label>
                    <label class="form-field"><span>منبع داده</span><select name="source_type" data-daily-source><?php foreach(DailyWorkReportService::SOURCE_TYPES as $key=>$label):?><option value="<?=e($key)?>" <?=(($editField['source_type']??'manual')===$key)?'selected':''?>><?=e($label)?></option><?php endforeach;?></select></label>
                    <label class="form-field"><span>گزینه منبع</span><select name="source_key" data-daily-source-key data-current="<?=e($editField['source_key']??'')?>"><option value="">برای ورودی دستی لازم نیست</option></select></label>
                    <label class="form-field"><span>ترتیب</span><input type="number" name="sort_order" value="<?=e((string)($editField['sort_order']??0))?>"></label>
                </div>
                <label class="form-field" data-daily-formula><span>فرمول کنترل‌شده</span><input name="formula_expression" value="<?=e($editField['formula_expression']??'')?>" placeholder="{completed_actions}+{completed_tasks}"><small>فقط کلید فیلدها داخل { }، عدد، پرانتز و چهار عمل اصلی مجاز است.</small></label>
                <label class="form-field"><span>راهنمای کاربر</span><textarea name="help_text" rows="2"><?=e($editField['help_text']??'')?></textarea></label>
                <label class="form-field"><span>Placeholder</span><input name="placeholder" value="<?=e($editField['placeholder']??'')?>"></label>
                <label class="form-field"><span>گزینه‌ها</span><textarea name="options_text" rows="3" placeholder="yes|بله&#10;no|خیر"><?=e(DailyWorkReportService::optionsText($editField['options_json']??null))?></textarea><small>هر خط: مقدار|عنوان فارسی. کاربر JSON نمی‌بیند.</small></label>
                <div class="daily-check-row"><label><input type="checkbox" name="required" value="1" <?=($editField['required']??0)?'checked':''?>> اجباری</label><label><input type="checkbox" name="readonly" value="1" <?=($editField['readonly']??0)?'checked':''?>> فقط‌خواندنی</label><label><input type="checkbox" name="active" value="1" <?=!$editField||($editField['active']??1)?'checked':''?>> فعال</label></div>
                <div class="form-actions"><button class="btn btn-primary">ذخیره فیلد</button><?php if($editField):?><a class="btn" href="/admin/daily-report-templates.php?template_id=<?=$templateId?>#field-editor">فیلد جدید</a><?php endif;?></div>
            </form>

            <section class="card daily-assignment-editor" id="template-assignments" data-daily-reveal>
                <header><div><h2>تخصیص قالب</h2><p>یک قالب می‌تواند هم‌زمان به چند محدوده تخصیص داده شود.</p></div><span><?=e((string)count($template['assignments']))?> تخصیص</span></header>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?=e(Auth::csrfToken())?>">
                    <input type="hidden" name="operation" value="save_assignment">
                    <input type="hidden" name="template_id" value="<?=$templateId?>">
                    <div class="grid grid-3">
                        <label class="form-field"><span>نوع محدوده</span><select name="scope_type" data-daily-scope><?php foreach($assignmentScopes as $key=>$label):?><option value="<?=e($key)?>"><?=e($label)?></option><?php endforeach;?></select></label>
                        <label class="form-field"><span>مقدار محدوده</span><select name="scope_id" data-daily-scope-value></select></label>
                        <label class="form-field"><span>کلید جایگزین</span><input name="scope_key" placeholder="فقط در صورت نیاز"></label>
                    </div>
                    <div class="form-actions"><button class="btn btn-primary">افزودن تخصیص</button></div>
                </form>
                <div class="daily-assignment-list">
                    <?php foreach($template['assignments'] as $assignment):?>
                    <?php $assignmentLabel=$assignment['user_name']??$assignment['role_title']??$assignment['unit_title']??$assignment['sales_line_title']??$assignment['team_user_name']??($assignment['scope_type']==='company'?'کل شرکت':($assignment['scope_key']?:$assignment['scope_id']));?>
                    <span><b><?=e(DailyWorkReportService::SCOPES[$assignment['scope_type']]??$assignment['scope_type'])?></b><em><?=e((string)$assignmentLabel)?></em></span>
                    <?php endforeach;?>
                </div>
            </section>
            <?php endif;?>
        </main>
    </div>
</div>
<script>
window.SobhanDailyReport = <?=json_encode([
    'sourceKeys'=>DailyWorkReportService::SOURCE_KEYS,
    'scopeOptions'=>[
        'user'=>array_map(static fn($row)=>['id'=>(int)$row['id'],'title'=>(string)$row['name']],$scopeOptions['users']),
        'role'=>array_map(static fn($row)=>['id'=>(int)$row['id'],'title'=>(string)$row['title']],$scopeOptions['roles']),
        'department'=>array_map(static fn($row)=>['id'=>(int)$row['id'],'title'=>(string)$row['title']],$scopeOptions['units']),
        'sales_line'=>array_map(static fn($row)=>['id'=>(int)$row['id'],'title'=>(string)$row['title']],$scopeOptions['sales_lines']),
        'supervisor_team'=>array_map(static fn($row)=>['id'=>(int)$row['id'],'title'=>(string)$row['name']],$scopeOptions['supervisors']),
        'manager_team'=>array_map(static fn($row)=>['id'=>(int)$row['id'],'title'=>(string)$row['name']],$scopeOptions['managers']),
        'company'=>[['id'=>0,'title'=>'کل شرکت']],
    ],
],JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP)?>;
</script>
<?php require __DIR__ . '/../views/partials/admin-footer.php'; ?>
