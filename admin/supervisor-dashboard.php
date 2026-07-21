<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../services/SalesOperationsService.php';
require_once __DIR__ . '/../services/ActionHubService.php';
require_once __DIR__ . '/../lib/DashboardPreferences.php';

SalesOperationsService::boot();
ActionHubService::boot();
SalesOperationsService::requireSupervisorPermission('supervisor.panel.view');
$user = Auth::user();
$supervisorId = (int)$user['id'];
$dashboardPreferences = DashboardPreferences::forScope('supervisor', 0, $supervisorId);
$visitors = SalesOperationsService::getSupervisorVisitors($supervisorId);
$visitorIds = array_values(array_unique(array_map('intval', array_column($visitors, 'id'))));
$teamUserIds = array_values(array_unique(array_merge([$supervisorId], $visitorIds)));
$canManageTeamActions = ActionHubService::canAssign($user)
    && (SalesOperationsService::canViewAll($user) || Auth::can('supervisor.actions.manage'));
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) throw new DomainException('اعتبار فرم منقضی شده است.');
        if (($_POST['operation'] ?? '') !== 'create_team_action') throw new InvalidArgumentException('عملیات درخواست‌شده معتبر نیست.');
        if (!$canManageTeamActions) throw new DomainException('مجوز ثبت اقدام برای تیم را ندارید.');
        $assignedTo = (int)($_POST['assigned_to'] ?? 0);
        if ($assignedTo < 1 || !in_array($assignedTo, $visitorIds, true)) {
            throw new DomainException('کاربر انتخاب‌شده عضو فعال تیم فروش شما نیست.');
        }
        if (!SalesOperationsService::assertVisitorBelongsToSupervisor($assignedTo, $supervisorId)) {
            throw new DomainException('دسترسی به کاربر انتخاب‌شده تأیید نشد.');
        }
        $uploads = [];
        foreach (($_FILES['field_files']['name'] ?? []) as $key => $name) {
            $uploads[$key] = [
                'name' => $name,
                'type' => $_FILES['field_files']['type'][$key] ?? '',
                'tmp_name' => $_FILES['field_files']['tmp_name'][$key] ?? '',
                'error' => $_FILES['field_files']['error'][$key] ?? UPLOAD_ERR_NO_FILE,
                'size' => $_FILES['field_files']['size'][$key] ?? 0,
            ];
        }
        $actionInput = $_POST;
        $actionInput['source_type'] = 'manual';
        $actionInput['status'] = 'new';
        $actionId = ActionHubService::createAction($actionInput, $user, $uploads);
        Auth::log($supervisorId, 'supervisor_team_action_created', 'action_hub', $actionId);
        flash('اقدام تیمی با موفقیت ثبت شد.');
        redirect('/admin/action-view.php?id=' . $actionId);
    } catch (InvalidArgumentException|DomainException $e) {
        $errors[] = $e->getMessage();
    } catch (Throwable $e) {
        error_log('Supervisor dashboard action create: ' . $e->getMessage());
        $errors[] = 'ثبت اقدام انجام نشد. جزئیات فنی در لاگ ثبت شد.';
    }
}

$dateInput = $_GET;
if (empty($dateInput['period_key']) && empty($dateInput['from']) && empty($dateInput['to'])) {
    $dateInput['period_key'] = DashboardPreferences::defaultPeriod($dashboardPreferences);
}
[$from,$to] = SalesOperationsService::dateFilters($dateInput);
$summary = SalesOperationsService::getSupervisorSalesSummary($supervisorId, ['from'=>$from,'to'=>$to]);
$actionBucket = in_array(($_GET['action_state'] ?? ''), ['open','overdue','completed'], true)
    ? (string)$_GET['action_state']
    : 'open';
$teamActionStats = ActionHubService::teamStats($user, $teamUserIds);
$teamActions = ActionHubService::teamActions($user, $teamUserIds, $actionBucket, 50);
$openActions = (int)$teamActionStats['open'];
$overdueActions = (int)$teamActionStats['overdue'];
$pendingReports = (int)(Database::fetch('SELECT COUNT(*) c FROM sales_supervisor_reports WHERE supervisor_id=? AND status IN ("submitted_by_supervisor","pending_sales_manager_review")', [$supervisorId])['c'] ?? 0);
$actionTypes = ActionHubService::types();
$generalTypeId = 0;
foreach ($actionTypes as $type) {
    if (($type['code'] ?? '') === 'general') {
        $generalTypeId = (int)$type['id'];
        break;
    }
}
$actionTemplates = ActionHubService::templates();
$templateDetails = [];
foreach ($actionTemplates as $template) {
    $templateDetails[(int)$template['id']] = ActionHubService::template((int)$template['id']);
}
$actionTabUrl = static function(string $state) use ($dateInput): string {
    $query = [
        'period_key' => $dateInput['period_key'] ?? '',
        'from' => $dateInput['from'] ?? '',
        'to' => $dateInput['to'] ?? '',
        'action_state' => $state,
    ];
    return '/admin/supervisor-dashboard.php?' . http_build_query(array_filter($query, static fn($value): bool => $value !== '')) . '#supervisor-actions';
};
$sourceLabel = match ($summary['data_source'] ?? 'none') {
    'active_sales_reference_view' => 'Batch فعال فروش / View گزارش',
    'legacy_ceo_dashboard_visitors' => 'داده قدیمی داشبورد (fallback)',
    default => 'بدون داده فعال',
};
$pageTitle = 'داشبورد سرپرست فروش';
$adminBodyClasses[] = 'app-compact-ui';
$adminExtraStylesheets = ['/assets/css/action-hub.css', '/assets/css/supervisor-action-hub.css'];
$adminExtraScripts = ['/assets/js/action-hub.js'];
require __DIR__ . '/../views/partials/admin-header.php';

$renderActionField = static function(array $field): void {
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
<div class="section-heading-row"><div><h1>داشبورد سرپرست فروش</h1><p class="muted">خلاصه عملکرد تیم فروش زیرمجموعه شما از <?=e(format_jalali_date($from))?> تا <?=e(format_jalali_date($to))?></p></div><div class="actions"><?php if($canManageTeamActions):?><a class="btn btn-primary" href="#supervisor-action-composer">ثبت اقدام تیمی</a><?php endif;?><a class="btn" href="/admin/supervisor-sales-report.php">گزارش فروش</a></div></div>
<div class="dashboard-source-bar"><span>منبع داده: <strong><?=e($sourceLabel)?></strong></span><?php if(DashboardPreferences::canManage('supervisor',$user)):?><a class="btn btn-small" href="/admin/dashboard-settings.php?scope=supervisor">تنظیم نمایش</a><?php endif?></div>
<form class="card admin-form" method="get"><div class="grid grid-3"><label class="form-field"><span>دوره گزارش</span><?=app_period_select('period_key',$dateInput['period_key']??null,['daily','weekly','monthly','quarterly','half_yearly','yearly'],['placeholder'=>'ماه جاری'])?></label><label class="form-field"><span>از تاریخ (پیشرفته)</span><?=app_date_input('from',$from)?></label><label class="form-field"><span>تا تاریخ (پیشرفته)</span><?=app_date_input('to',$to)?></label></div><div class="form-actions"><button class="btn btn-primary">اعمال فیلتر</button></div></form>
<?php foreach($errors as $error):?><div class="alert alert-danger"><?=e($error)?></div><?php endforeach;?>
<?php
$widgetContent=[];
ob_start();?>
<div class="stats"><div class="stat-card"><span>فروش خالص تیم</span><strong><?=e(number_format((float)$summary['net_sales']))?></strong></div><div class="stat-card"><span>فاکتور</span><strong><?=e(number_format((int)$summary['invoice_count']))?></strong></div><div class="stat-card"><span>مشتری</span><strong><?=e(number_format((int)$summary['customer_count']))?></strong></div><div class="stat-card"><span>ویزیتورهای تیم</span><strong><?=e((string)$summary['visitors'])?></strong></div><div class="stat-card"><span>اقدامات باز</span><strong><?=e((string)$openActions)?></strong></div><div class="stat-card"><span>سررسید گذشته</span><strong><?=e((string)$overdueActions)?></strong></div><div class="stat-card"><span>گزارش در انتظار</span><strong><?=e((string)$pendingReports)?></strong></div></div>
<?php $widgetContent['summary_kpis']=ob_get_clean();ob_start();?>
<section class="card"><h2><?=e(DashboardPreferences::title($dashboardPreferences,'visitor_performance','عملکرد ویزیتورها'))?></h2><div class="table-wrap"><table><thead><tr><th>ویزیتور</th><th>لاین</th><th>فروش بازه</th><th>تعداد کالا/مقدار</th><th>تحقق</th></tr></thead><tbody><?php foreach($summary['rows'] as $row): ?><tr><td><?=e($row['visitor_name'] ?? '-')?></td><td><?=e($row['line_code'] ?? '-')?></td><td><?=e(number_format((float)($row['net_sales'] ?? 0)))?></td><td><?=e(number_format((float)($row['qty'] ?? 0)))?></td><td><?php if($row['achievement_percent']===null):?><span class="muted">پس از اتصال تارگت</span><?php else:?><span class="achievement-pill <?=((float)$row['achievement_percent']>=80?'achievement-good':'achievement-warn')?>"><?=e(number_format((float)$row['achievement_percent'],1))?>٪</span><?php endif?></td></tr><?php endforeach; ?><?php if(!$summary['rows']): ?><tr><td colspan="5">برای این بازه در Batch فعال فروش داده‌ای وجود ندارد.</td></tr><?php endif; ?></tbody></table></div></section>
<?php $widgetContent['visitor_performance']=ob_get_clean();ob_start();?>
<section class="supervisor-action-panel" id="supervisor-actions">
    <header class="supervisor-action-heading" data-action-reveal>
        <div><span class="action-kicker">TEAM ACTIONS</span><h2><?=e(DashboardPreferences::title($dashboardPreferences,'actions','اقدامات تیم فروش'))?></h2><p>ثبت و پیگیری اقدام فقط برای اعضای فعال تیم شما؛ تمام داده‌ها در مرکز اقدامات سازمانی نگهداری می‌شود.</p></div>
        <a class="btn" href="/admin/action-hub.php?mine=created">نمایش در مرکز اقدامات</a>
    </header>
    <nav class="action-tabs supervisor-action-tabs" aria-label="وضعیت اقدام‌های تیم" data-action-reveal>
        <a class="<?=$actionBucket==='open'?'is-active':''?>" href="<?=e($actionTabUrl('open'))?>">باز <b><?=e((string)$teamActionStats['open'])?></b></a>
        <a class="<?=$actionBucket==='overdue'?'is-active':''?>" href="<?=e($actionTabUrl('overdue'))?>">سررسید گذشته <b><?=e((string)$teamActionStats['overdue'])?></b></a>
        <a class="<?=$actionBucket==='completed'?'is-active':''?>" href="<?=e($actionTabUrl('completed'))?>">تکمیل‌شده <b><?=e((string)$teamActionStats['completed'])?></b></a>
    </nav>

    <?php if($canManageTeamActions):?>
    <details class="card action-composer supervisor-action-composer" id="supervisor-action-composer" data-action-reveal <?=($errors || isset($_GET['action_panel']))?'open':''?>>
        <summary><span><b>ثبت اقدام برای عضو تیم</b><small>نوع پیش‌فرض عمومی است؛ قالب انتخابی فیلدهای تکمیلی را نمایش می‌دهد.</small></span><span class="action-summary-icon">＋</span></summary>
        <?php if(!$visitors):?>
            <div class="supervisor-action-empty-note">برای ثبت اقدام، ابتدا یک ویزیتور فعال به این سرپرست تخصیص دهید.</div>
        <?php else:?>
        <form method="post" enctype="multipart/form-data" data-action-form>
            <input type="hidden" name="csrf_token" value="<?=e(Auth::csrfToken())?>">
            <input type="hidden" name="operation" value="create_team_action">
            <div class="action-form-grid">
                <label class="form-field action-span-2"><span>عنوان اقدام *</span><input name="title" required maxlength="190" value="<?=e($_POST['title']??'')?>"></label>
                <label class="form-field"><span>عضو مسئول تیم *</span><select name="assigned_to" required><?php foreach($visitors as $visitor):?><option value="<?=(int)$visitor['id']?>" <?=((int)($_POST['assigned_to']??0)===(int)$visitor['id'])?'selected':''?>><?=e($visitor['name'])?><?=!empty($visitor['sales_line'])?' — لاین '.e($visitor['sales_line']):''?></option><?php endforeach;?></select></label>
                <label class="form-field"><span>نوع اقدام *</span><select name="action_type_id" required data-action-type><option value="">انتخاب کنید</option><?php foreach($actionTypes as $type):?><?php $selectedType=(int)($_POST['action_type_id']??$generalTypeId)===(int)$type['id'];?><option value="<?=(int)$type['id']?>" <?=$selectedType?'selected':''?>><?=e($type['title'])?></option><?php endforeach;?></select></label>
                <label class="form-field"><span>قالب نوع اقدام</span><select name="template_id" data-action-template data-action-auto-template="1"><option value="">بدون قالب</option><?php foreach($actionTemplates as $template):?><option value="<?=(int)$template['id']?>" data-type="<?=(int)$template['action_type_id']?>" <?=((int)($_POST['template_id']??0)===(int)$template['id'])?'selected':''?>><?=e($template['title'])?></option><?php endforeach;?></select></label>
                <label class="form-field"><span>موعد انجام</span><?=app_date_input('due_date', (string)($_POST['due_date']??''))?></label>
                <label class="form-field"><span>اولویت</span><select name="priority"><?php foreach(ActionHubService::PRIORITIES as $key=>$label):?><option value="<?=e($key)?>" <?=(($_POST['priority']??'normal')===$key)?'selected':''?>><?=e($label)?></option><?php endforeach;?></select></label>
                <label class="form-field action-span-2"><span>شرح اقدام</span><textarea name="description" rows="3"><?=e($_POST['description']??'')?></textarea></label>
            </div>
            <div class="action-dynamic-fields" data-action-dynamic>
                <?php foreach($templateDetails as $templateId=>$template):?>
                    <section data-template-fields="<?=$templateId?>" hidden>
                        <div class="action-subheading"><b>اطلاعات قالب «<?=e($template['title'])?>»</b><small><?=e($template['instructions']??'فیلدهای لازم را تکمیل کنید.')?></small></div>
                        <div class="action-form-grid"><?php foreach($template['fields'] as $field) $renderActionField($field);?></div>
                    </section>
                <?php endforeach;?>
            </div>
            <label class="action-check action-planner-check"><input type="checkbox" name="add_to_planner" value="1" <?=!isset($_POST['operation'])||!empty($_POST['add_to_planner'])?'checked':''?>> هم‌زمان به برنامه کاری عضو مسئول افزوده شود</label>
            <div class="form-actions"><button class="btn btn-primary">ثبت اقدام تیمی</button></div>
        </form>
        <?php endif;?>
    </details>
    <?php endif;?>

    <div class="action-list supervisor-action-list" data-action-reveal>
        <?php foreach($teamActions as $action):?>
            <?php $effectiveStatus=!in_array($action['status'],['done','cancelled'],true)&&$action['due_date']&&$action['due_date']<date('Y-m-d')?'overdue':$action['status'];?>
            <a class="action-card" href="/admin/action-view.php?id=<?=(int)$action['id']?>" style="--action-color:<?=e($action['action_type_color'])?>">
                <span class="action-card-rail"></span>
                <div class="action-card-main">
                    <div class="action-card-title"><span class="action-type-dot"></span><h3><?=e($action['title'])?></h3></div>
                    <p><?=e($action['description']?:'بدون توضیح تکمیلی')?></p>
                    <div class="action-card-meta"><span><?=e($action['action_type_title'])?></span><span>مسئول: <?=e($action['assigned_to_name'])?></span><?php if($action['due_date']):?><span>موعد: <?=e(format_jalali_date($action['due_date']))?></span><?php endif;?><?php if($action['planner_task_id']):?><span>متصل به پلنر</span><?php endif;?></div>
                </div>
                <div class="action-card-state"><span class="action-priority is-<?=e($action['priority'])?>"><?=e(ActionHubService::PRIORITIES[$action['priority']]??'عادی')?></span><span class="action-status is-<?=e($effectiveStatus)?>"><?=e(ActionHubService::STATUSES[$effectiveStatus]??$effectiveStatus)?></span></div>
            </a>
        <?php endforeach;?>
        <?php if(!$teamActions):?><div class="card action-empty"><b>در این وضعیت اقدامی برای تیم شما وجود ندارد.</b><span>از فرم بالا یک اقدام جدید برای عضو تیم ثبت کنید.</span></div><?php endif;?>
    </div>
</section>
<?php $widgetContent['actions']=ob_get_clean();ob_start();?>
<section class="card"><h2><?=e(DashboardPreferences::title($dashboardPreferences,'team_structure','ساختار تیم'))?></h2><p class="muted">ویزیتورهای فعال زیرمجموعه: <?=e((string)count($visitors))?></p><div class="table-wrap"><table><thead><tr><th>نام</th><th>کد پرسنلی</th><th>لاین</th><th>نقش</th></tr></thead><tbody><?php foreach($visitors as $visitor): ?><tr><td><?=e($visitor['name'])?></td><td><?=e($visitor['employee_no'] ?? '-')?></td><td><?=e($visitor['sales_line'] ?? '-')?></td><td><?=e($visitor['role_key'] ?? '-')?></td></tr><?php endforeach; ?><?php if(!$visitors): ?><tr><td colspan="4">هنوز ویزیتوری به شما تخصیص داده نشده است.</td></tr><?php endif; ?></tbody></table></div></section>
<?php $widgetContent['team_structure']=ob_get_clean();echo DashboardPreferences::render($dashboardPreferences,$widgetContent);?>
<script>
window.SobhanActionHub = <?=json_encode([
    'users'=>array_map(static fn($visitor)=>['id'=>(int)$visitor['id'],'title'=>(string)$visitor['name']],$visitors),
    'orgUnits'=>Database::fetchAll('SELECT id,title FROM org_units WHERE active=1 ORDER BY sort_order,title'),
    'salesLines'=>Database::tableExists('sales_lines')?Database::fetchAll('SELECT id,CONCAT(code," — ",title) title FROM sales_lines WHERE active=1 ORDER BY sort_order,code'):[],
], JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP)?>;
</script>
<?php require __DIR__ . '/../views/partials/admin-footer.php'; ?>
