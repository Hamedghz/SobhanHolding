<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/JalaliDate.php';
require_once __DIR__ . '/../lib/OkrService.php';
Auth::requireLogin();
if (!OkrService::canManageCycles()) { http_response_code(403); exit('دسترسی غیرمجاز'); }
$userId = (int)Auth::user()['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) throw new DomainException('اعتبار فرم منقضی شده است.');
        $input = $_POST;
        foreach (['start_date','end_date','registration_deadline','approval_deadline'] as $field) {
            $raw = trim((string)($_POST[$field] ?? ''));
            $input[$field] = $raw === '' ? '' : (JalaliDate::toGregorian($raw) ?: '');
        }
        OkrService::saveCycle($input, $userId, (int)($_POST['cycle_id'] ?? 0));
        flash('دوره OKR ذخیره شد.');
    } catch (InvalidArgumentException|DomainException $e) {
        flash($e->getMessage(), 'danger');
    } catch (Throwable $e) {
        error_log('OKR cycle save: ' . $e->getMessage());
        flash('ذخیره دوره انجام نشد. لطفاً دوباره تلاش کنید.', 'danger');
    }
    redirect('/admin/okr-cycles.php');
}

$cycles = OkrService::cycles(true);
$edit = null;
if ((int)($_GET['edit'] ?? 0) > 0) $edit = Database::fetch('SELECT * FROM okr_cycles WHERE id=?', [(int)$_GET['edit']]);
$pageTitle = 'دوره‌های OKR';
$adminExtraStylesheets = ['/assets/css/okr.css'];
require __DIR__ . '/../views/partials/admin-header.php';
?>
<div class="section-heading-row"><div><h1>دوره‌های OKR</h1><p class="muted">تعریف بازه ثبت، تأیید و Check-in اهداف سازمانی</p></div><a class="btn" href="/admin/okr.php">بازگشت به داشبورد OKR</a></div>
<section class="card admin-form" id="cycle-form"><h2><?=$edit?'ویرایش دوره':'دوره جدید'?></h2><form method="post"><input type="hidden" name="csrf_token" value="<?=e(Auth::csrfToken())?>"><input type="hidden" name="cycle_id" value="<?=e((string)($edit['id']??0))?>"><div class="grid grid-3">
<label class="form-field"><span>عنوان دوره</span><input name="title" required maxlength="190" value="<?=e($edit['title']??'')?>" placeholder="فصل دوم ۱۴۰۵"></label>
<label class="form-field"><span>نوع دوره</span><select name="cycle_type"><?php foreach(OkrService::CYCLE_TYPES as $key=>$label):?><option value="<?=$key?>" <?=($edit['cycle_type']??'quarterly')===$key?'selected':''?>><?=e($label)?></option><?php endforeach?></select></label>
<label class="form-field"><span>وضعیت</span><select name="status"><?php foreach(OkrService::CYCLE_STATUSES as $key=>$label):?><option value="<?=$key?>" <?=($edit['status']??'draft')===$key?'selected':''?>><?=e($label)?></option><?php endforeach?></select></label>
<label class="form-field"><span>شروع دوره</span><input class="jalali-date-input" name="start_date" required value="<?=e(isset($edit['start_date'])?JalaliDate::toJalali($edit['start_date']):'')?>"></label>
<label class="form-field"><span>پایان دوره</span><input class="jalali-date-input" name="end_date" required value="<?=e(isset($edit['end_date'])?JalaliDate::toJalali($edit['end_date']):'')?>"></label>
<label class="form-field"><span>تعداد Check-in برنامه‌ریزی‌شده</span><input type="number" min="0" max="366" name="checkin_count" value="<?=e((string)($edit['checkin_count']??0))?>"></label>
<label class="form-field"><span>مهلت ثبت هدف</span><input class="jalali-date-input" name="registration_deadline" value="<?=e(!empty($edit['registration_deadline'])?JalaliDate::toJalali($edit['registration_deadline']):'')?>"></label>
<label class="form-field"><span>مهلت تأیید</span><input class="jalali-date-input" name="approval_deadline" value="<?=e(!empty($edit['approval_deadline'])?JalaliDate::toJalali($edit['approval_deadline']):'')?>"></label>
<label class="form-field"><span>تناوب Check-in</span><select name="checkin_frequency"><?php foreach(['weekly'=>'هفتگی','monthly'=>'ماهانه','none'=>'بدون برنامه'] as $key=>$label):?><option value="<?=$key?>" <?=($edit['checkin_frequency']??'weekly')===$key?'selected':''?>><?=e($label)?></option><?php endforeach?></select></label>
</div><div class="form-actions"><button class="btn btn-primary">ذخیره دوره</button><?php if($edit):?><a class="btn" href="/admin/okr-cycles.php">انصراف</a><?php endif?></div></form></section>
<section class="card"><h2>دوره‌های ثبت‌شده</h2><div class="table-wrap"><table><thead><tr><th>دوره</th><th>بازه</th><th>ثبت/تأیید</th><th>Check-in</th><th>اهداف</th><th>وضعیت</th><th></th></tr></thead><tbody><?php foreach($cycles as $cycle):?><tr><td><strong><?=e($cycle['title'])?></strong><small><?=e(OkrService::CYCLE_TYPES[$cycle['cycle_type']]??$cycle['cycle_type'])?></small></td><td><?=e(JalaliDate::toJalali($cycle['start_date']).' تا '.JalaliDate::toJalali($cycle['end_date']))?></td><td><small>ثبت: <?=e($cycle['registration_deadline']?JalaliDate::toJalali($cycle['registration_deadline']):'—')?></small><small>تأیید: <?=e($cycle['approval_deadline']?JalaliDate::toJalali($cycle['approval_deadline']):'—')?></small></td><td><?=e(['weekly'=>'هفتگی','monthly'=>'ماهانه','none'=>'بدون برنامه'][$cycle['checkin_frequency']]??$cycle['checkin_frequency'])?> · <?=e((string)$cycle['checkin_count'])?></td><td><?=e((string)$cycle['objective_count'])?></td><td><span class="okr-status status-<?=e($cycle['status'])?>"><?=e(OkrService::CYCLE_STATUSES[$cycle['status']]??$cycle['status'])?></span></td><td><a class="btn btn-small" href="?edit=<?=$cycle['id']?>">ویرایش</a></td></tr><?php endforeach?><?php if(!$cycles):?><tr><td colspan="7">دوره‌ای ثبت نشده است.</td></tr><?php endif?></tbody></table></div></section>
<?php require __DIR__ . '/../views/partials/admin-footer.php';
