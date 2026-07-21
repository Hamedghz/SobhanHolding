<?php

require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../services/ActionHubService.php';

Auth::requireLogin();
ActionHubService::boot();
$actor = Auth::user();
if (!ActionHubService::canView($actor)) {
    http_response_code(403);
    exit('دسترسی غیرمجاز');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) throw new DomainException('اعتبار فرم منقضی شده است.');
        $operation = (string)($_POST['operation'] ?? 'create');
        if ($operation === 'create') {
            $uploads = [];
            foreach (($_FILES['field_files']['name'] ?? []) as $key => $name) {
                $uploads[$key] = [
                    'name'=>$name,
                    'type'=>$_FILES['field_files']['type'][$key] ?? '',
                    'tmp_name'=>$_FILES['field_files']['tmp_name'][$key] ?? '',
                    'error'=>$_FILES['field_files']['error'][$key] ?? UPLOAD_ERR_NO_FILE,
                    'size'=>$_FILES['field_files']['size'][$key] ?? 0,
                ];
            }
            $id = ActionHubService::createAction($_POST, $actor, $uploads);
            Auth::log((int)$actor['id'], 'action_created', 'action_hub', $id);
            flash('اقدام با موفقیت ثبت شد.');
            redirect('/admin/action-view.php?id=' . $id);
        }
        throw new InvalidArgumentException('عملیات درخواست‌شده معتبر نیست.');
    } catch (InvalidArgumentException|DomainException $e) {
        flash($e->getMessage(), 'danger');
    } catch (Throwable $e) {
        error_log('Action hub create: ' . $e->getMessage());
        flash('ثبت اقدام انجام نشد. جزئیات فنی در لاگ ثبت شد.', 'danger');
    }
    redirect('/admin/action-hub.php');
}

$types = ActionHubService::types();
$templates = ActionHubService::templates();
$templateDetails = [];
foreach ($templates as $template) $templateDetails[(int)$template['id']] = ActionHubService::template((int)$template['id']);
$users = ActionHubService::assignableUsers($actor);
$actions = ActionHubService::actions($actor, $_GET);
$canCreate = ActionHubService::canCreateOwn($actor);
$canTemplates = ActionHubService::canManageTemplates();
$canTypes = ActionHubService::canManageTypes();
$hasActionFilters = trim((string)($_GET['q'] ?? '')) !== ''
    || trim((string)($_GET['status'] ?? '')) !== ''
    || trim((string)($_GET['priority'] ?? '')) !== ''
    || (int)($_GET['action_type_id'] ?? 0) > 0
    || in_array((string)($_GET['mine'] ?? ''), ['assigned','created'], true);
$isFirstUseEmpty = !$actions && !$hasActionFilters;
$pageTitle = 'مرکز اقدامات';
$adminBodyClasses[] = 'app-compact-ui';
$adminExtraStylesheets = ['/assets/css/action-hub.css'];
$adminExtraScripts = ['/assets/js/action-hub.js'];
require __DIR__ . '/../views/partials/admin-header.php';

$renderField = static function(array $field): void {
    $key = (string)$field['field_key'];
    $name = 'fields[' . $key . ']';
    $type = (string)$field['field_type'];
    $required = (int)$field['required'] ? ' required' : '';
    $readonly = (int)$field['readonly'] ? ' readonly' : '';
    $placeholder = e((string)($field['placeholder'] ?? ''));
    $default = e((string)($field['default_value'] ?? ''));
    $options = $field['options'] ?? [];
    ?>
    <label class="form-field action-field" data-action-field>
        <span><?=e($field['field_label'])?><?=(int)$field['required']?' *':''?></span>
        <?php if ($type === 'long_text'): ?>
            <textarea name="<?=$name?>" rows="3" placeholder="<?=$placeholder?>"<?=$required.$readonly?>><?=$default?></textarea>
        <?php elseif (in_array($type, ['number','money','percentage'], true)): ?>
            <input type="number" step="any" name="<?=$name?>" value="<?=$default?>" placeholder="<?=$placeholder?>"<?=$required.$readonly?>>
        <?php elseif ($type === 'jalali_date'): ?>
            <?=app_date_input($name, (string)($field['default_value'] ?? ''), ['required'=>(bool)$field['required']])?>
        <?php elseif ($type === 'datetime'): ?>
            <?=app_date_input($name, (string)($field['default_value'] ?? ''), ['datetime'=>true,'required'=>(bool)$field['required'],'readonly'=>(bool)$field['readonly']])?>
        <?php elseif ($type === 'time'): ?>
            <input type="time" name="<?=$name?>" value="<?=$default?>"<?=$required.$readonly?>>
        <?php elseif ($type === 'single_select'): ?>
            <select name="<?=$name?>"<?=$required?>><option value="">انتخاب کنید</option><?php foreach($options as $value=>$label):?><option value="<?=e($value)?>"><?=e($label)?></option><?php endforeach;?></select>
        <?php elseif ($type === 'multi_select'): ?>
            <select name="fields[<?=e($key)?>][]" multiple<?=$required?>><?php foreach($options as $value=>$label):?><option value="<?=e($value)?>"><?=e($label)?></option><?php endforeach;?></select>
        <?php elseif ($type === 'yes_no'): ?>
            <select name="<?=$name?>"<?=$required?>><option value="">انتخاب کنید</option><option value="1">بله</option><option value="0">خیر</option></select>
        <?php elseif ($type === 'checkbox'): ?>
            <span class="action-check"><input type="checkbox" name="<?=$name?>" value="1"> تأیید می‌کنم</span>
        <?php elseif ($type === 'user'): ?>
            <select name="<?=$name?>" data-action-users<?=$required?>></select>
        <?php elseif ($type === 'org_unit'): ?>
            <select name="<?=$name?>" data-action-org-units<?=$required?>><option value="">انتخاب واحد</option></select>
        <?php elseif ($type === 'sales_line'): ?>
            <select name="<?=$name?>" data-action-sales-lines<?=$required?>><option value="">انتخاب لاین</option></select>
        <?php elseif ($type === 'file'): ?>
            <input type="file" name="field_files[<?=e($key)?>]" accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.jpg,.jpeg,.png,.webp,.zip"<?=$required?>>
        <?php elseif ($type === 'url'): ?>
            <input type="url" name="<?=$name?>" value="<?=$default?>" placeholder="https://"<?=$required.$readonly?>>
        <?php else: ?>
            <input name="<?=$name?>" value="<?=$default?>" placeholder="<?=$placeholder?>"<?=$required.$readonly?>>
        <?php endif; ?>
        <?php if (!empty($field['help_text'])): ?><small><?=e($field['help_text'])?></small><?php endif; ?>
    </label>
    <?php
};
?>
<div class="action-hub-page">
    <section class="action-hero" data-action-reveal>
        <div>
            <span class="action-kicker">UNIVERSAL ACTION HUB</span>
            <h1>مرکز اقدامات سازمانی</h1>
            <p>تمام اقدام‌های دستی، گزارش‌ها، مصوبات، OKR، KPI، برنامه کاری و پیشنهادهای هوشمند در یک مسیر قابل پیگیری جمع می‌شوند.</p>
        </div>
        <div class="action-hero-tools">
            <?php if ($canTemplates): ?><a class="btn" href="/admin/action-templates.php">قالب‌های اقدام</a><?php endif; ?>
            <?php if ($canTypes): ?><a class="btn" href="/admin/action-types.php">انواع اقدام</a><?php endif; ?>
        </div>
    </section>

    <nav class="action-tabs" aria-label="فیلتر سریع" data-action-reveal>
        <a class="<?=($_GET['mine']??'')===''?'is-active':''?>" href="/admin/action-hub.php">همه قابل دسترس</a>
        <a class="<?=($_GET['mine']??'')==='assigned'?'is-active':''?>" href="/admin/action-hub.php?mine=assigned">محول‌شده به من</a>
        <a class="<?=($_GET['mine']??'')==='created'?'is-active':''?>" href="/admin/action-hub.php?mine=created">ایجادشده توسط من</a>
    </nav>

    <?php if ($canCreate): ?>
    <details class="card action-composer" id="action-composer" data-action-reveal <?=(isset($_GET['create']) || $isFirstUseEmpty)?'open':''?>>
        <summary><span><b>ایجاد اقدام جدید</b><small>با قالب یا بدون قالب، همراه اتصال اختیاری به برنامه کاری</small></span><span class="action-summary-icon">＋</span></summary>
        <form method="post" enctype="multipart/form-data" data-action-form>
            <input type="hidden" name="csrf_token" value="<?=e(Auth::csrfToken())?>">
            <input type="hidden" name="operation" value="create">
            <div class="action-form-grid">
                <label class="form-field action-span-2"><span>عنوان *</span><input name="title" required maxlength="190"></label>
                <label class="form-field"><span>نوع اقدام *</span><select name="action_type_id" required data-action-type><option value="">انتخاب کنید</option><?php foreach($types as $type):?><option value="<?=(int)$type['id']?>"><?=e($type['title'])?></option><?php endforeach;?></select></label>
                <label class="form-field"><span>قالب اقدام</span><select name="template_id" data-action-template><option value="">بدون قالب</option><?php foreach($templates as $template):?><option value="<?=(int)$template['id']?>" data-type="<?=(int)$template['action_type_id']?>"><?=e($template['title'])?></option><?php endforeach;?></select></label>
                <label class="form-field"><span>مسئول اقدام *</span><select name="assigned_to" required data-action-assignee><?php foreach($users as $user):?><option value="<?=(int)$user['id']?>" <?=((int)$user['id']===(int)$actor['id'])?'selected':''?>><?=e($user['name'])?><?=!empty($user['org_unit_title'])?' — '.e($user['org_unit_title']):''?></option><?php endforeach;?></select></label>
                <label class="form-field"><span>اولویت</span><select name="priority"><?php foreach(ActionHubService::PRIORITIES as $key=>$label):?><option value="<?=e($key)?>" <?=$key==='normal'?'selected':''?>><?=e($label)?></option><?php endforeach;?></select></label>
                <label class="form-field"><span>وضعیت اولیه</span><select name="status"><?php foreach(ActionHubService::STATUSES as $key=>$label):?><option value="<?=e($key)?>" <?=$key==='new'?'selected':''?>><?=e($label)?></option><?php endforeach;?></select></label>
                <label class="form-field"><span>منبع اقدام</span><select name="source_type"><?php foreach(ActionHubService::SOURCES as $key=>$label):?><option value="<?=e($key)?>" <?=$key==='manual'?'selected':''?>><?=e($label)?></option><?php endforeach;?></select></label>
                <label class="form-field"><span>شناسه مرجع</span><input type="number" min="1" name="source_id"></label>
                <label class="form-field"><span>تاریخ شروع</span><?=app_date_input('start_date')?></label>
                <label class="form-field"><span>سررسید</span><?=app_date_input('due_date')?></label>
                <label class="form-field action-span-2"><span>شرح اقدام</span><textarea name="description" rows="4"></textarea></label>
            </div>
            <div class="action-dynamic-fields" data-action-dynamic>
                <?php foreach($templateDetails as $templateId=>$template): ?>
                    <section data-template-fields="<?=$templateId?>" hidden>
                        <div class="action-subheading"><b>اطلاعات قالب «<?=e($template['title'])?>»</b><small><?=e($template['instructions']??'فیلدهای لازم را تکمیل کنید.')?></small></div>
                        <div class="action-form-grid"><?php foreach($template['fields'] as $field) $renderField($field); ?></div>
                    </section>
                <?php endforeach; ?>
            </div>
            <label class="action-check action-planner-check"><input type="checkbox" name="add_to_planner" value="1"> هم‌زمان در برنامه کاری مسئول نیز ثبت شود</label>
            <div class="action-check-row action-submit-row"><button class="btn btn-primary">ثبت اقدام</button></div>
        </form>
    </details>
    <?php endif; ?>

    <form class="card action-filter" method="get" data-action-reveal>
        <label class="form-field"><span>جستجو</span><input name="q" value="<?=e($_GET['q']??'')?>" placeholder="عنوان یا شرح"></label>
        <label class="form-field"><span>وضعیت</span><select name="status"><option value="">همه</option><?php foreach(ActionHubService::STATUSES as $key=>$label):?><option value="<?=e($key)?>" <?=($_GET['status']??'')===$key?'selected':''?>><?=e($label)?></option><?php endforeach;?></select></label>
        <label class="form-field"><span>اولویت</span><select name="priority"><option value="">همه</option><?php foreach(ActionHubService::PRIORITIES as $key=>$label):?><option value="<?=e($key)?>" <?=($_GET['priority']??'')===$key?'selected':''?>><?=e($label)?></option><?php endforeach;?></select></label>
        <label class="form-field"><span>نوع</span><select name="action_type_id"><option value="">همه</option><?php foreach($types as $type):?><option value="<?=(int)$type['id']?>" <?=((int)($_GET['action_type_id']??0)===(int)$type['id'])?'selected':''?>><?=e($type['title'])?></option><?php endforeach;?></select></label>
        <button class="btn">اعمال فیلتر</button>
    </form>

    <section class="action-list" data-action-reveal>
        <?php foreach($actions as $action): ?>
            <?php
            $effectiveStatus = $action['status'];
            if (!in_array($effectiveStatus, ['done','cancelled'], true) && $action['due_date'] && $action['due_date'] < date('Y-m-d')) $effectiveStatus = 'overdue';
            ?>
            <a class="action-card" href="/admin/action-view.php?id=<?=(int)$action['id']?>" style="--action-color:<?=e($action['action_type_color'])?>">
                <span class="action-card-rail"></span>
                <div class="action-card-main">
                    <div class="action-card-title"><span class="action-type-dot"></span><h2><?=e($action['title'])?></h2></div>
                    <p><?=e($action['description']?:'بدون توضیح تکمیلی')?></p>
                    <div class="action-card-meta">
                        <span><?=e($action['action_type_title'])?></span>
                        <span>مسئول: <?=e($action['assigned_to_name'])?></span>
                        <span>ایجادکننده: <?=e($action['assigned_by_name'])?></span>
                        <?php if($action['due_date']):?><span>سررسید: <?=e(format_jalali_date($action['due_date']))?></span><?php endif;?>
                    </div>
                </div>
                <div class="action-card-state">
                    <span class="action-priority is-<?=e($action['priority'])?>"><?=e(ActionHubService::PRIORITIES[$action['priority']]??'عادی')?></span>
                    <span class="action-status is-<?=e($effectiveStatus)?>"><?=e(ActionHubService::STATUSES[$effectiveStatus]??$effectiveStatus)?></span>
                </div>
            </a>
        <?php endforeach; ?>
        <?php if(!$actions):?>
            <div class="card action-empty">
                <?php if($canCreate && $isFirstUseEmpty):?>
                    <b>هنوز اقدامی در این محدوده ثبت نشده است.</b>
                    <span>فرم ایجاد اقدام آماده است؛ اولین اقدام واقعی را با مسئول و سررسید مشخص ثبت کنید.</span>
                    <a class="btn btn-primary" href="#action-composer">ایجاد اولین اقدام</a>
                <?php elseif($canCreate):?>
                    <b>اقدامی مطابق فیلترها پیدا نشد.</b>
                    <span>فیلترها را پاک کنید یا یک اقدام جدید بسازید.</span>
                    <a class="btn" href="/admin/action-hub.php?create=1#action-composer">پاک‌کردن فیلتر و ایجاد اقدام</a>
                <?php elseif($hasActionFilters):?>
                    <b>اقدامی مطابق فیلترها پیدا نشد.</b>
                    <span>فیلترها را تغییر دهید یا برای مشاهده همه اقدام‌های قابل دسترس بازگردید.</span>
                    <a class="btn" href="/admin/action-hub.php">نمایش همه اقدام‌ها</a>
                <?php else:?>
                    <b>هنوز اقدامی به شما محول نشده است.</b>
                    <span>پس از تخصیص اقدام توسط مدیر مجاز، جزئیات و وضعیت آن در این بخش نمایش داده می‌شود.</span>
                <?php endif;?>
            </div>
        <?php endif;?>
    </section>
</div>
<script>
window.SobhanActionHub = <?=json_encode([
    'users'=>array_map(static fn($u)=>['id'=>(int)$u['id'],'title'=>(string)$u['name']],$users),
    'orgUnits'=>Database::fetchAll('SELECT id,title FROM org_units WHERE active=1 ORDER BY sort_order,title'),
    'salesLines'=>Database::tableExists('sales_lines')?Database::fetchAll('SELECT id,CONCAT(code," — ",title) title FROM sales_lines WHERE active=1 ORDER BY sort_order,code'):[],
], JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP)?>;
</script>
<?php require __DIR__ . '/../views/partials/admin-footer.php'; ?>
