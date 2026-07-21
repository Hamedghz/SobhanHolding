<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/JalaliDate.php';
require_once __DIR__ . '/../lib/OkrService.php';
Auth::requireLogin();
if (!OkrService::menuAllowed()) { http_response_code(403); exit('دسترسی غیرمجاز'); }
$user = Auth::user();
$userId = (int)$user['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) throw new DomainException('اعتبار فرم منقضی شده است.');
        $action = (string)($_POST['action'] ?? '');
        if ($action !== 'save_objective') throw new InvalidArgumentException('عملیات درخواست‌شده معتبر نیست.');
        $input = $_POST;
        $input['start_date'] = JalaliDate::toGregorian((string)($_POST['start_date'] ?? '')) ?: '';
        $input['due_date'] = JalaliDate::toGregorian((string)($_POST['due_date'] ?? '')) ?: '';
        $id = OkrService::saveObjective($input, $userId, (int)($_POST['objective_id'] ?? 0));
        flash('هدف OKR ذخیره شد.');
        redirect('/admin/okr-objective.php?id=' . $id);
    } catch (InvalidArgumentException|DomainException $e) {
        flash($e->getMessage(), 'danger');
    } catch (Throwable $e) {
        error_log('OKR objective save: ' . $e->getMessage());
        flash('ذخیره هدف انجام نشد. لطفاً دوباره تلاش کنید.', 'danger');
    }
    redirect('/admin/okr.php');
}

$filters = [
    'cycle_id' => (int)($_GET['cycle_id'] ?? 0),
    'status' => trim((string)($_GET['status'] ?? '')),
    'owner_user_id' => (int)($_GET['owner_user_id'] ?? 0),
];
$dashboard = OkrService::dashboard($filters);
$summary = $dashboard['summary'];
$objectives = $dashboard['objectives'];
$cycles = OkrService::cycles(true);
$openCycles = array_values(array_filter($cycles, static fn(array $cycle): bool => ($cycle['status'] ?? '') !== 'closed'));
$owners = OkrService::availableOwners($user);
$parents = OkrService::listObjectives();
$edit = null;
if ((int)($_GET['edit'] ?? 0) > 0) {
    $edit = OkrService::objective((int)$_GET['edit']);
    if ($edit && !OkrService::canManageObjective($edit, $user)) $edit = null;
}
$salesLines = Database::tableExists('sales_lines')
    ? Database::fetchAll('SELECT code,title FROM sales_lines WHERE active=1 ORDER BY sort_order,code')
    : [];
$legacySalesLine = trim((string)($edit['sales_line'] ?? ''));
$knownSalesLineCodes = array_map(static fn(array $line): string => (string)$line['code'], $salesLines);
$pageTitle = 'OKR و اهداف سازمانی';
$adminExtraStylesheets = ['/assets/css/okr.css'];
require __DIR__ . '/../views/partials/admin-header.php';
?>
<div class="section-heading-row okr-heading"><div><h1>OKR و اهداف سازمانی</h1><p class="muted">هدف‌های جهت‌دهنده، نتایج کلیدی قابل‌اندازه‌گیری و اقدام‌های متصل به پلنر</p></div><div class="form-actions"><?php if(OkrService::canManageCycles()):?><a class="btn" href="/admin/okr-cycles.php">مدیریت دوره‌ها</a><?php endif?><a class="btn" href="/employee/work-planner.php">پلنر من</a></div></div>

<section class="okr-stats" aria-label="خلاصه OKR">
  <article class="stat-card"><span>همه اهداف</span><strong><?=e((string)$summary['total'])?></strong></article>
  <article class="stat-card"><span>فعال</span><strong><?=e((string)$summary['active'])?></strong></article>
  <article class="stat-card okr-stat-warning"><span>در معرض خطر</span><strong><?=e((string)$summary['at_risk'])?></strong></article>
  <article class="stat-card okr-stat-danger"><span>عقب‌افتاده</span><strong><?=e((string)$summary['off_track'])?></strong></article>
  <article class="stat-card"><span>در انتظار تأیید</span><strong><?=e((string)$summary['pending'])?></strong></article>
  <article class="stat-card"><span>بدون Check-in</span><strong><?=e((string)$summary['without_checkin'])?></strong></article>
  <article class="stat-card"><span>میانگین تحقق</span><strong><?=e((string)$summary['average_score'])?>٪</strong></article>
</section>

<form class="card admin-form" method="get"><h2>فیلتر داشبورد</h2><div class="grid grid-3"><label class="form-field"><span>دوره</span><select name="cycle_id"><option value="0">همه دوره‌ها</option><?php foreach($cycles as $cycle):?><option value="<?=$cycle['id']?>" <?=$filters['cycle_id']===(int)$cycle['id']?'selected':''?>><?=e($cycle['title'])?></option><?php endforeach?></select></label><label class="form-field"><span>وضعیت</span><select name="status"><option value="">همه وضعیت‌ها</option><?php foreach(OkrService::OBJECTIVE_STATUSES as $key=>$label):?><option value="<?=e($key)?>" <?=$filters['status']===$key?'selected':''?>><?=e($label)?></option><?php endforeach?></select></label><label class="form-field"><span>مالک</span><select name="owner_user_id"><option value="0">همه افراد محدوده من</option><?php foreach($owners as $owner):?><option value="<?=$owner['id']?>" <?=$filters['owner_user_id']===(int)$owner['id']?'selected':''?>><?=e($owner['name'])?></option><?php endforeach?></select></label></div><div class="form-actions"><button class="btn btn-primary">اعمال فیلتر</button><a class="btn" href="/admin/okr.php">پاک‌کردن</a></div></form>

<?php if(OkrService::canCreate($user) && $openCycles):?>
<section class="card admin-form" id="objective-form"><details <?=$edit?'open':''?>><summary><strong><?=$edit?'ویرایش هدف':'ایجاد Objective جدید'?></strong></summary>
<form method="post"><input type="hidden" name="csrf_token" value="<?=e(Auth::csrfToken())?>"><input type="hidden" name="action" value="save_objective"><input type="hidden" name="objective_id" value="<?=e((string)($edit['id']??0))?>">
<div class="grid grid-3">
  <label class="form-field"><span>دوره OKR</span><select name="cycle_id" required><option value="">انتخاب دوره</option><?php foreach($openCycles as $cycle):?><option value="<?=$cycle['id']?>" <?=(int)($edit['cycle_id']??0)===(int)$cycle['id']?'selected':''?>><?=e($cycle['title'])?></option><?php endforeach?></select></label>
  <label class="form-field"><span>مالک هدف</span><select name="owner_user_id" required><?php foreach($owners as $owner):?><option value="<?=$owner['id']?>" <?=(int)($edit['owner_user_id']??$userId)===(int)$owner['id']?'selected':''?>><?=e($owner['name'].' · '.($owner['org_role_title']?:$owner['role_key']?:'بدون نقش'))?></option><?php endforeach?></select></label>
  <label class="form-field"><span>هدف والد</span><select name="parent_objective_id"><option value="0">بدون هدف والد</option><?php foreach($parents as $parent):if((int)$parent['id']===(int)($edit['id']??0))continue;?><option value="<?=$parent['id']?>" <?=(int)($edit['parent_objective_id']??0)===(int)$parent['id']?'selected':''?>><?=e($parent['title'].' · '.$parent['owner_name'])?></option><?php endforeach?></select></label>
  <label class="form-field grid-span-2"><span>عنوان هدف</span><input name="title" required maxlength="255" value="<?=e($edit['title']??'')?>" placeholder="مثلاً افزایش کیفیت و بهره‌وری فروش لاین A"></label>
  <label class="form-field"><span>سطح هدف</span><select name="objective_level"><?php foreach(OkrService::OBJECTIVE_LEVELS as $key=>$label):?><option value="<?=$key?>" <?=($edit['objective_level']??'employee')===$key?'selected':''?>><?=e($label)?></option><?php endforeach?></select></label>
  <label class="form-field"><span>نوع OKR</span><select name="okr_type"><?php foreach(OkrService::OBJECTIVE_TYPES as $key=>$label):?><option value="<?=$key?>" <?=($edit['okr_type']??'committed')===$key?'selected':''?>><?=e($label)?></option><?php endforeach?></select></label>
  <label class="form-field"><span>اولویت</span><select name="priority"><?php foreach(OkrService::PRIORITIES as $key=>$label):?><option value="<?=$key?>" <?=($edit['priority']??'normal')===$key?'selected':''?>><?=e($label)?></option><?php endforeach?></select></label>
  <label class="form-field"><span>وزن هدف</span><input type="number" min="0" max="100" step="0.01" name="weight" value="<?=e((string)($edit['weight']??100))?>"></label>
  <label class="form-field"><span>لاین فروش</span><select name="sales_line"><option value="">بدون لاین فروش</option><?php foreach($salesLines as $line):?><option value="<?=e((string)$line['code'])?>" <?=$legacySalesLine===(string)$line['code']?'selected':''?>><?=e($line['code'].' — '.$line['title'])?></option><?php endforeach?><?php if($legacySalesLine!==''&&!in_array($legacySalesLine,$knownSalesLineCodes,true)):?><option value="<?=e($legacySalesLine)?>" selected><?=e($legacySalesLine.' — مقدار تاریخی')?></option><?php endif?></select><small>لاین از ساختار مرکزی فروش خوانده می‌شود.</small></label>
  <label class="form-field"><span>شروع</span><input class="jalali-date-input" name="start_date" required value="<?=e(isset($edit['start_date'])?JalaliDate::toJalali($edit['start_date']):'')?>"></label>
  <label class="form-field"><span>مهلت</span><input class="jalali-date-input" name="due_date" required value="<?=e(isset($edit['due_date'])?JalaliDate::toJalali($edit['due_date']):'')?>"></label>
  <label class="form-field grid-span-3"><span>شرح هدف</span><textarea name="description" rows="4" maxlength="10000"><?=e($edit['description']??'')?></textarea></label>
</div><div class="form-actions"><button class="btn btn-primary">ذخیره و ادامه</button><?php if($edit):?><a class="btn" href="/admin/okr.php">انصراف</a><?php endif?></div></form></details></section>
<?php elseif(OkrService::canCreate($user)):?>
<section class="card okr-empty okr-setup-required"><h2>برای ایجاد هدف، ابتدا یک دوره OKR بسازید.</h2><p class="muted">بدون دوره باز، تاریخ و چرخه Check-in هدف قابل کنترل نیست.</p><?php if(OkrService::canManageCycles()):?><a class="btn btn-primary" href="/admin/okr-cycles.php#cycle-form">ایجاد اولین دوره OKR</a><?php else:?><span class="muted">مدیر OKR باید یک دوره فعال یا پیش‌نویس ایجاد کند.</span><?php endif?></section>
<?php endif?>

<section class="okr-objective-list" aria-label="فهرست اهداف">
<?php foreach($objectives as $objective):[$bandClass,$bandLabel]=OkrService::scoreBand((float)$objective['progress_score']);?>
<article class="card okr-objective-card"><header><div><span class="okr-status status-<?=e($objective['status'])?>"><?=e(OkrService::OBJECTIVE_STATUSES[$objective['status']]??$objective['status'])?></span><span class="okr-type"><?=e(OkrService::OBJECTIVE_TYPES[$objective['okr_type']]??$objective['okr_type'])?></span></div><strong class="okr-score score-<?=e($bandClass)?>"><?=e((string)$objective['progress_score'])?>٪</strong></header><h2><a href="/admin/okr-objective.php?id=<?=$objective['id']?>"><?=e($objective['title'])?></a></h2><p class="muted"><?=e($objective['owner_name'].' · '.($objective['org_unit_title']?:'بدون واحد').' · '.$objective['cycle_title'])?></p><?php if($objective['parent_title']):?><p class="okr-parent">هم‌راستا با: <?=e($objective['parent_title'])?></p><?php endif?><div class="okr-progress"><span style="width:<?=e((string)min(100,max(0,(float)$objective['progress_score'])))?>%"></span></div><footer><small><?=e((string)$objective['kr_count'])?> نتیجه کلیدی · مهلت <?=e(JalaliDate::toJalali($objective['due_date']))?></small><span class="score-label score-<?=e($bandClass)?>"><?=e($bandLabel)?></span><div class="form-actions"><a class="btn btn-small btn-primary" href="/admin/okr-objective.php?id=<?=$objective['id']?>">مشاهده و Check-in</a><?php if(OkrService::canManageObjective($objective,$user)):?><a class="btn btn-small" href="/admin/okr.php?edit=<?=$objective['id']?>#objective-form">ویرایش</a><?php endif?></div></footer></article>
<?php endforeach?>
<?php if(!$objectives):?><section class="card okr-empty"><h2>هنوز هدفی در این محدوده ثبت نشده است.</h2><p class="muted"><?=$openCycles?'Objective نخست را با مالک، نتیجه قابل‌اندازه‌گیری و موعد مشخص ایجاد کنید.':'ابتدا یک دوره OKR بسازید و سپس Objective و نتایج کلیدی آن را تعریف کنید.'?></p><?php if(!$openCycles&&OkrService::canManageCycles()):?><a class="btn" href="/admin/okr-cycles.php#cycle-form">مدیریت دوره‌ها</a><?php endif?></section><?php endif?>
</section>
<?php require __DIR__ . '/../views/partials/admin-footer.php';
