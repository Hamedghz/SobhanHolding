<?php

require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../services/DailyWorkReportService.php';

Auth::requireLogin();
DailyWorkReportService::boot();
$actor = Auth::user();
if (!DailyWorkReportService::canView($actor)) {
    http_response_code(403);
    exit('دسترسی غیرمجاز');
}

$accessibleIds = DailyWorkReportService::canViewTeam($actor) ? OrgAccess::accessibleUserIds($actor) : [(int)$actor['id']];
$accessibleIds = array_values(array_unique(array_filter(array_map('intval',$accessibleIds))));
$requestedUserId = (int)($_POST['user_id'] ?? $_GET['user_id'] ?? $actor['id']);
if (!in_array($requestedUserId,$accessibleIds,true)) $requestedUserId = (int)$actor['id'];
$targetUser = Database::fetch('SELECT * FROM users WHERE id=? AND status="active"', [$requestedUserId]) ?: $actor;
$rawDate = (string)($_POST['report_date'] ?? $_GET['date'] ?? date('Y-m-d'));
$reportDate = AppDate::toGregorian($rawDate) ?: date('Y-m-d');
$templates = DailyWorkReportService::templatesForUser($targetUser);
$templateId = (int)($_POST['template_id'] ?? $_GET['template_id'] ?? ($templates[0]['id'] ?? 0));
$templateIds = array_map('intval',array_column($templates,'id'));
if ($templateId && !in_array($templateId,$templateIds,true)) $templateId = (int)($templates[0]['id'] ?? 0);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) throw new DomainException('اعتبار فرم منقضی شده است.');
        $operation = (string)($_POST['operation'] ?? '');
        if ($operation === 'save_report') {
            $reportId = DailyWorkReportService::saveReport($_POST,$actor);
            flash(($_POST['submit_mode']??'')==='submit'?'گزارش روزانه ثبت و ارسال شد.':'پیش‌نویس گزارش ذخیره شد.');
            redirect('/admin/daily-work-report.php?'.http_build_query(['user_id'=>$requestedUserId,'date'=>$reportDate,'template_id'=>$templateId]).'#daily-report-form');
        }
        if ($operation === 'create_action') {
            $created = DailyWorkReportService::createActionFromReport($_POST,$actor);
            Auth::log((int)$actor['id'],'daily_report_action_created','daily_reports',(int)$created['report_id']);
            flash('اقدام از داخل گزارش ساخته و به گزارش پیوند شد.');
            redirect('/admin/action-view.php?id='.(int)$created['action_id']);
        }
        throw new InvalidArgumentException('عملیات درخواست‌شده معتبر نیست.');
    } catch (InvalidArgumentException|DomainException $e) {
        $errors[] = $e->getMessage();
    } catch (Throwable $e) {
        error_log('Daily work report: '.$e->getMessage());
        $errors[] = 'عملیات گزارش انجام نشد. جزئیات فنی در لاگ ثبت شد.';
    }
}

$template = $templateId ? DailyWorkReportService::template($templateId) : null;
$report = $template ? DailyWorkReportService::reportForUser($requestedUserId,$reportDate,$templateId,$actor) : null;
$presentations = $template ? DailyWorkReportService::fieldPresentations($requestedUserId,$reportDate,$template,$report,$actor) : [];
$reportLinksByField = [];
foreach (($report['links'] ?? []) as $link) $reportLinksByField[(int)($link['field_id']??0)][] = $link;
$reports = DailyWorkReportService::reports($actor,100);
$targetUsers = [];
if ($accessibleIds) {
    $targetUsers = Database::fetchAll(
        'SELECT id,name,employee_no FROM users WHERE status="active" AND id IN ('.implode(',',array_fill(0,count($accessibleIds),'?')).') ORDER BY CASE WHEN id=? THEN 0 ELSE 1 END,display_order,name',
        array_merge($accessibleIds,[(int)$actor['id']])
    );
}
$actionTypes = ActionHubService::types();
$generalTypeId = 0;
foreach ($actionTypes as $type) if (($type['code']??'')==='general') {$generalTypeId=(int)$type['id'];break;}
$actionUsers = ActionHubService::assignableUsers($actor);
$canCreateAction = $requestedUserId === (int)$actor['id']
    && DailyWorkReportService::canSubmit($actor)
    && ActionHubService::canCreateOwn($actor);
$pageTitle = 'گزارش کار روزانه';
$adminBodyClasses[] = 'app-compact-ui';
$adminExtraStylesheets = ['/assets/css/daily-work-report.css'];
$adminExtraScripts = ['/assets/js/daily-work-report.js'];
require __DIR__ . '/../views/partials/admin-header.php';
?>
<div class="daily-report-page">
    <section class="daily-report-hero" data-daily-reveal>
        <div><span class="daily-report-kicker">DAILY WORK REPORT</span><h1>گزارش کار روزانه</h1><p>فعالیت‌های دستی و داده‌های واقعی Action Hub، پلنر، KPI، حضور و ورود اطلاعات در یک گزارش قابل پیگیری جمع می‌شوند.</p></div>
        <div class="daily-report-hero-actions"><?php if(DailyWorkReportService::canManageTemplates($actor)):?><a class="btn" href="/admin/daily-report-templates.php">مدیریت قالب‌ها</a><?php endif;?><a class="btn" href="/admin/action-hub.php?mine=assigned">اقدامات من</a></div>
    </section>

    <?php foreach($errors as $error):?><div class="alert alert-danger"><?=e($error)?></div><?php endforeach;?>

    <form class="card daily-report-switcher" method="get" data-daily-reveal>
        <?php if(DailyWorkReportService::canViewTeam($actor)):?>
        <label class="form-field"><span>کاربر گزارش</span><select name="user_id"><?php foreach($targetUsers as $user):?><option value="<?=(int)$user['id']?>" <?=((int)$user['id']===$requestedUserId)?'selected':''?>><?=e($user['name'])?><?=!empty($user['employee_no'])?' — '.e($user['employee_no']):''?></option><?php endforeach;?></select></label>
        <?php else:?><input type="hidden" name="user_id" value="<?=$requestedUserId?>"><?php endif;?>
        <label class="form-field"><span>تاریخ گزارش</span><?=app_date_input('date',$reportDate)?></label>
        <label class="form-field"><span>قالب گزارش</span><select name="template_id" <?=!$templates?'disabled':''?>><?php if(!$templates):?><option>قالب فعالی تخصیص ندارد</option><?php endif;?><?php foreach($templates as $item):?><option value="<?=(int)$item['id']?>" <?=((int)$item['id']===$templateId)?'selected':''?>><?=e($item['title'])?> — نسخه <?=e((string)$item['version_no'])?></option><?php endforeach;?></select></label>
        <button class="btn" <?=!$templates?'disabled':''?>>نمایش گزارش</button>
    </form>

    <?php if(!$template):?>
        <section class="card action-empty">
            <b>قالب فعالی برای این کاربر تخصیص داده نشده است.</b>
            <span>مدیر سیستم یا مدیر واحد باید یک قالب را به کاربر، نقش، واحد، لاین یا تیم تخصیص دهد.</span>
            <?php if(DailyWorkReportService::canManageTemplates($actor)):?><a class="btn btn-primary" href="/admin/daily-report-templates.php?new=1">ایجاد یا تخصیص قالب گزارش</a><?php endif;?>
        </section>
    <?php else:?>
    <section class="daily-report-meta" data-daily-reveal>
        <div><span>کاربر</span><strong><?=e($targetUser['name']??'-')?></strong></div>
        <div><span>تاریخ</span><strong><?=e(format_jalali_date($reportDate))?></strong></div>
        <div><span>قالب</span><strong><?=e($template['title'])?></strong></div>
        <div><span>وضعیت</span><strong class="daily-report-status is-<?=e($report['status']??'new')?>"><?=e(['draft'=>'پیش‌نویس','submitted'=>'ارسال‌شده','new'=>'ثبت‌نشده'][$report['status']??'new']??'ثبت‌نشده')?></strong></div>
    </section>

    <form class="daily-report-form" id="daily-report-form" method="post" data-daily-reveal>
        <input type="hidden" name="csrf_token" value="<?=e(Auth::csrfToken())?>">
        <input type="hidden" name="operation" value="save_report">
        <input type="hidden" name="user_id" value="<?=$requestedUserId?>">
        <input type="hidden" name="report_date" value="<?=e($reportDate)?>">
        <input type="hidden" name="template_id" value="<?=$templateId?>">
        <section class="daily-report-fields">
        <?php foreach($template['fields'] as $field):?>
            <?php $presentation=$presentations[$field['field_key']]??['value'=>'','display'=>'','links'=>[]];?>
            <?php if($field['source_type']==='manual'):?>
                <label class="card daily-manual-field">
                    <span class="daily-field-label"><?=e($field['field_label'])?><?=(int)$field['required']?' *':''?></span>
                    <?php if($field['input_type']==='short_text'):?>
                        <input name="fields[<?=e($field['field_key'])?>]" value="<?=e($_POST['fields'][$field['field_key']]??$presentation['value']??'')?>" placeholder="<?=e($field['placeholder']??'')?>" <?=(int)$field['required']?'required':''?>>
                    <?php elseif($field['input_type']==='number'):?>
                        <input type="number" step="any" name="fields[<?=e($field['field_key'])?>]" value="<?=e((string)($_POST['fields'][$field['field_key']]??$presentation['value']??''))?>" <?=(int)$field['required']?'required':''?>>
                    <?php elseif($field['input_type']==='yes_no'):?>
                        <select name="fields[<?=e($field['field_key'])?>]" <?=(int)$field['required']?'required':''?>><option value="">انتخاب کنید</option><option value="1" <?=((string)($presentation['value']??'')==='1')?'selected':''?>>بله</option><option value="0" <?=((string)($presentation['value']??'')==='0')?'selected':''?>>خیر</option></select>
                    <?php else:?>
                        <textarea name="fields[<?=e($field['field_key'])?>]" rows="4" placeholder="<?=e($field['placeholder']??'')?>" <?=(int)$field['required']?'required':''?>><?=e($_POST['fields'][$field['field_key']]??$presentation['value']??'')?></textarea>
                    <?php endif;?>
                    <?php if($field['help_text']):?><small><?=e($field['help_text'])?></small><?php endif;?>
                </label>
            <?php else:?>
                <article class="card daily-readonly-field" style="--daily-source:<?=e($field['source_type'])?>">
                    <header><span class="daily-source-dot"></span><div><h2><?=e($field['field_label'])?></h2><small><?=e(DailyWorkReportService::SOURCE_TYPES[$field['source_type']]??$field['source_type'])?></small></div><b><?=e(number_format((float)($presentation['number']??0),2))?></b></header>
                    <p><?=e($presentation['display']??'داده‌ای موجود نیست.')?></p>
                    <?php $fieldLinks=array_merge($presentation['links']??[],$reportLinksByField[(int)$field['id']]??[]);?>
                    <?php if($fieldLinks):?><div class="daily-link-row"><?php foreach(array_slice($fieldLinks,0,12) as $link):?><a href="<?=e($link['url']??$link['link_url']??'#')?>"><?=e($link['label']??'مشاهده')?></a><?php endforeach;?></div><?php endif;?>
                </article>
            <?php endif;?>
        <?php endforeach;?>
        </section>
        <?php if(DailyWorkReportService::canSubmit($actor) && $requestedUserId===(int)$actor['id']):?>
        <div class="daily-report-submit"><button class="btn" name="submit_mode" value="draft">ذخیره پیش‌نویس</button><button class="btn btn-primary" name="submit_mode" value="submit">ثبت و ارسال گزارش</button></div>
        <?php endif;?>
    </form>

    <?php if($canCreateAction):?>
    <details class="card daily-action-composer" data-daily-reveal>
        <summary><span><b>ساخت اقدام از «اقدامات و پیشنهادات»</b><small>گزارش فقط لینک اقدام را نگه می‌دارد؛ مالکیت و وضعیت در مرکز اقدامات مدیریت می‌شود.</small></span><span>＋</span></summary>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?=e(Auth::csrfToken())?>">
            <input type="hidden" name="operation" value="create_action">
            <input type="hidden" name="user_id" value="<?=$requestedUserId?>">
            <input type="hidden" name="report_date" value="<?=e($reportDate)?>">
            <input type="hidden" name="template_id" value="<?=$templateId?>">
            <div class="daily-action-grid">
                <label class="form-field daily-span-2"><span>عنوان اقدام *</span><input name="action_title" required maxlength="190"></label>
                <label class="form-field"><span>مسئول *</span><select name="assigned_to" required><?php foreach($actionUsers as $user):?><option value="<?=(int)$user['id']?>" <?=((int)$user['id']===(int)$actor['id'])?'selected':''?>><?=e($user['name'])?></option><?php endforeach;?></select></label>
                <label class="form-field"><span>نوع اقدام *</span><select name="action_type_id" required><?php foreach($actionTypes as $type):?><option value="<?=(int)$type['id']?>" <?=((int)$type['id']===$generalTypeId)?'selected':''?>><?=e($type['title'])?></option><?php endforeach;?></select></label>
                <label class="form-field"><span>موعد جلالی</span><?=app_date_input('due_date')?></label>
                <label class="form-field"><span>اولویت</span><select name="priority"><?php foreach(ActionHubService::PRIORITIES as $key=>$label):?><option value="<?=e($key)?>" <?=$key==='normal'?'selected':''?>><?=e($label)?></option><?php endforeach;?></select></label>
                <label class="form-field daily-span-2"><span>شرح اقدام</span><textarea name="action_description" rows="3"></textarea></label>
            </div>
            <label class="daily-planner-check"><input type="checkbox" name="add_to_planner" value="1" checked> هم‌زمان به پلنر مسئول اضافه شود</label>
            <div class="form-actions"><button class="btn btn-primary">ساخت و پیوند اقدام</button></div>
        </form>
    </details>
    <?php endif;?>
    <?php endif;?>

    <section class="card daily-report-history" data-daily-reveal>
        <header><h2>گزارش‌های اخیر قابل دسترس</h2><span><?=e((string)count($reports))?> گزارش</span></header>
        <div class="table-wrap"><table><thead><tr><th>تاریخ</th><th>کاربر</th><th>قالب</th><th>وضعیت</th><th>اقدام پیوندی</th><th>نمایش</th></tr></thead><tbody>
        <?php foreach($reports as $item):?><tr><td><?=e(format_jalali_date($item['report_date']))?></td><td><?=e($item['user_name'])?></td><td><?=e($item['template_title'])?></td><td><span class="daily-report-status is-<?=e($item['status'])?>"><?=e($item['status']==='submitted'?'ارسال‌شده':'پیش‌نویس')?></span></td><td><?=e((string)$item['action_count'])?></td><td><a class="btn btn-sm" href="/admin/daily-work-report.php?<?=e(http_build_query(['user_id'=>$item['user_id'],'date'=>$item['report_date'],'template_id'=>$item['template_id']]))?>">مشاهده</a></td></tr><?php endforeach;?>
        <?php if(!$reports):?><tr><td colspan="6">هنوز گزارشی ثبت نشده است.</td></tr><?php endif;?>
        </tbody></table></div>
    </section>
</div>
<?php require __DIR__ . '/../views/partials/admin-footer.php'; ?>
