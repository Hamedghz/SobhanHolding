<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/JalaliDate.php';
require_once __DIR__ . '/../lib/OkrService.php';
require_once __DIR__ . '/../lib/OkrMeetingIntegration.php';
require_once __DIR__ . '/../services/OkrAiAnalysisService.php';
Auth::requireLogin();
$objectiveId = (int)($_GET['id'] ?? $_POST['objective_id'] ?? 0);
if ($objectiveId <= 0) { http_response_code(404); exit('هدف یافت نشد.'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) throw new DomainException('اعتبار فرم منقضی شده است.');
        $action = (string)($_POST['action'] ?? '');
        $actorId = (int)Auth::user()['id'];
        if ($action === 'save_kr') {
            $input = $_POST;
            $input['due_date'] = JalaliDate::toGregorian((string)($_POST['due_date'] ?? '')) ?: '';
            OkrService::saveKeyResult($objectiveId, $input, $actorId, (int)($_POST['key_result_id'] ?? 0));
            flash('نتیجه کلیدی ذخیره شد.');
        } elseif ($action === 'submit') {
            OkrService::submitObjective($objectiveId, $actorId);
            flash('هدف برای تأیید مدیر ارسال شد.');
        } elseif ($action === 'approve' || $action === 'reject') {
            OkrService::decideObjective($objectiveId, $action === 'approve' ? 'approved' : 'rejected', (string)($_POST['approval_note'] ?? ''), $actorId);
            flash($action === 'approve' ? 'هدف تأیید و فعال شد.' : 'هدف برای اصلاح بازگردانده شد.');
        } elseif ($action === 'checkin') {
            OkrService::addCheckin($objectiveId, (int)($_POST['key_result_id'] ?? 0), $_POST, $_FILES['evidence'] ?? [], $actorId);
            flash('Check-in نتیجه کلیدی ثبت شد.');
        } elseif ($action === 'initiative') {
            $input = $_POST;
            $input['start_date'] = JalaliDate::toGregorian((string)($_POST['start_date'] ?? '')) ?: date('Y-m-d');
            $input['due_date'] = JalaliDate::toGregorian((string)($_POST['due_date'] ?? '')) ?: '';
            OkrService::addInitiative($objectiveId, $input, $actorId);
            flash('اقدام ساخته و به پلنر مسئول متصل شد.');
        } elseif ($action === 'refresh_kr') {
            $result = OkrService::refreshKeyResult($objectiveId, (int)($_POST['key_result_id'] ?? 0), $actorId);
            flash('مقدار «' . $result['label'] . '» از ' . (int)$result['row_count'] . ' ردیف داده بروزرسانی شد.');
        } elseif ($action === 'refresh_all_automatic') {
            $results = OkrService::refreshAutomaticResults($objectiveId, $actorId);
            flash(count($results) . ' نتیجه کلیدی خودکار بروزرسانی شد.');
        } elseif ($action === 'save_alignment') {
            OkrService::saveAlignment($objectiveId, $_POST, $actorId);
            flash('هم‌راستایی هدف ذخیره شد.');
        } elseif ($action === 'disable_alignment') {
            OkrService::deactivateAlignment($objectiveId, (int)($_POST['alignment_id'] ?? 0), $actorId);
            flash('هم‌راستایی بدون حذف سابقه غیرفعال شد.');
        } elseif ($action === 'run_ai_analysis') {
            $analysis = OkrAiAnalysisService::run($objectiveId, (string)($_POST['analysis_type'] ?? ''), $actorId);
            flash($analysis['source'] === 'deterministic'
                ? 'تحلیل قطعی داخلی ثبت شد؛ سرویس AI فعال نبود.'
                : 'تحلیل هوشمند OKR ثبت شد.');
        } else {
            throw new InvalidArgumentException('عملیات درخواست‌شده معتبر نیست.');
        }
    } catch (InvalidArgumentException|DomainException $e) {
        flash($e->getMessage(), 'danger');
    } catch (Throwable $e) {
        error_log('OKR objective action: ' . $e->getMessage());
        flash('عملیات OKR انجام نشد. لطفاً دوباره تلاش کنید.', 'danger');
    }
    redirect('/admin/okr-objective.php?id=' . $objectiveId);
}

try { $data = OkrService::detail($objectiveId); }
catch (DomainException $e) { http_response_code(403); exit('دسترسی غیرمجاز'); }
$objective = $data['objective'];
$krs = $data['krs'];
$canManage = OkrService::canManageObjective($objective);
$canApprove = OkrService::canApproveObjective($objective);
$owners = OkrService::availableOwners();
$editKr = null;
if ((int)($_GET['edit_kr'] ?? 0) > 0) {
    foreach ($krs as $candidate) if ((int)$candidate['id'] === (int)$_GET['edit_kr']) $editKr = $candidate;
}
$sourceDefinitions = OkrService::dataSourceDefinitions();
$sourceConfig = $editKr ? OkrService::keyResultSourceConfig($editKr) : [];
$alignmentCandidates = OkrService::alignmentCandidates($objective);
$automaticCount = count(array_filter($krs, static fn(array $kr): bool => ($kr['data_source_type'] ?? 'manual') === 'automatic'));
$decisionLinks = OkrMeetingIntegration::objectiveLinks($objectiveId);
$trend = array_reverse($data['scores']);
$trendMax = max(100.0, ...array_map(static fn(array $row): float => (float)$row['score_percent'], $trend ?: [['score_percent'=>100]]));
$trendPoints = [];
foreach ($trend as $index => $point) {
    $x = count($trend) > 1 ? 4 + ($index * 92 / (count($trend) - 1)) : 50;
    $y = 36 - min(36, max(0, ((float)$point['score_percent'] / $trendMax) * 32));
    $trendPoints[] = round($x, 2) . ',' . round($y, 2);
}
$canRunAi = OkrAiAnalysisService::canRun($objective);
$aiAnalyses = OkrAiAnalysisService::history($objectiveId, 10);
[$scoreClass,$scoreLabel] = OkrService::scoreBand((float)$objective['progress_score']);
$pageTitle = 'جزئیات OKR';
$adminExtraStylesheets = ['/assets/css/okr.css'];
$adminExtraScripts = ['/assets/js/okr.js'];
require __DIR__ . '/../views/partials/admin-header.php';
?>
<div class="section-heading-row okr-heading"><div><a class="muted" href="/admin/okr.php">OKR ← داشبورد</a><h1><?=e($objective['title'])?></h1><p class="muted"><?=e($objective['owner_name'].' · '.($objective['org_unit_title']?:'بدون واحد').' · '.$objective['cycle_title'])?></p></div><div class="form-actions"><?php if($canManage):?><a class="btn" href="/admin/okr.php?edit=<?=$objectiveId?>#objective-form">ویرایش هدف</a><?php endif?><a class="btn" href="/employee/work-planner.php">مشاهده پلنر</a></div></div>

<section class="card okr-objective-hero"><div><span class="okr-status status-<?=e($objective['status'])?>"><?=e(OkrService::OBJECTIVE_STATUSES[$objective['status']]??$objective['status'])?></span><span class="okr-type"><?=e(OkrService::OBJECTIVE_TYPES[$objective['okr_type']]??$objective['okr_type'])?></span><span class="badge"><?=e(OkrService::OBJECTIVE_LEVELS[$objective['objective_level']]??$objective['objective_level'])?></span></div><div class="okr-score-ring score-<?=e($scoreClass)?>"><strong><?=e((string)$objective['progress_score'])?>٪</strong><small><?=e($scoreLabel)?></small></div><div class="okr-objective-meta"><span>شروع: <?=e(JalaliDate::toJalali($objective['start_date']))?></span><span>مهلت: <?=e(JalaliDate::toJalali($objective['due_date']))?></span><span>سلامت: <?=e(OkrService::HEALTH_STATUSES[$objective['health_status']]??$objective['health_status'])?></span><span>وزن: <?=e((string)$objective['weight'])?>٪</span></div><?php if($objective['parent_title']):?><p class="okr-parent">هدف والد: <?=e($objective['parent_title'])?></p><?php endif?><?php if($objective['description']):?><p><?=nl2br(e($objective['description']))?></p><?php endif?></section>

<?php if($objective['status']==='draft'&&$canManage):?><form class="card okr-approval-box" method="post"><input type="hidden" name="csrf_token" value="<?=e(Auth::csrfToken())?>"><input type="hidden" name="objective_id" value="<?=$objectiveId?>"><input type="hidden" name="action" value="submit"><div><h2>آماده ارسال برای تأیید؟</h2><p class="muted">حداقل یک KR و مجموع وزن دقیقاً ۱۰۰٪ لازم است.</p></div><button class="btn btn-primary">ارسال برای تأیید</button></form><?php endif?>
<?php if($objective['status']==='pending_approval'&&$canApprove):?><section class="card okr-approval-box"><div><h2>تصمیم مدیر</h2><p class="muted">پس از تأیید، هدف فعال و آماده Check-in می‌شود.</p></div><form method="post" class="okr-approval-form"><input type="hidden" name="csrf_token" value="<?=e(Auth::csrfToken())?>"><input type="hidden" name="objective_id" value="<?=$objectiveId?>"><textarea name="approval_note" rows="2" placeholder="نظر تأیید یا دلیل بازگشت"></textarea><div class="form-actions"><button class="btn btn-primary" name="action" value="approve">تأیید و فعال‌سازی</button><button class="btn btn-danger" name="action" value="reject">بازگشت برای اصلاح</button></div></form></section><?php endif?>

<section class="card okr-alignment-section" data-okr-reveal>
<div class="section-heading-row"><div><h2>هم‌راستایی و Cascading</h2><p class="muted">سهم این هدف در اهداف بالادستی و اهداف زیرمجموعه آن.</p></div><span class="badge"><?=count($data['alignments'])?> بالادستی · <?=count($data['alignedChildren'])?> زیرمجموعه</span></div>
<div class="okr-alignment-grid">
<div><h3>اهداف بالادستی</h3><div class="okr-alignment-list">
<?php foreach($data['alignments'] as $alignment):?><article><div class="okr-alignment-marker"></div><div><strong><?=e($alignment['parent_title'])?></strong><small><?=e($alignment['parent_owner_name'].' · '.(OkrService::ALIGNMENT_TYPES[$alignment['alignment_type']]??$alignment['alignment_type']))?></small><div class="okr-progress"><span style="width:<?=e((string)min(100,max(0,(float)$alignment['parent_progress'])))?>%"></span></div></div><span><?=e((string)$alignment['contribution_weight'])?>٪</span><?php if($canManage):?><form method="post"><input type="hidden" name="csrf_token" value="<?=e(Auth::csrfToken())?>"><input type="hidden" name="objective_id" value="<?=$objectiveId?>"><input type="hidden" name="action" value="disable_alignment"><input type="hidden" name="alignment_id" value="<?=$alignment['id']?>"><button class="btn btn-small" type="submit">غیرفعال‌سازی</button></form><?php endif?></article><?php endforeach?>
<?php if(!$data['alignments']):?><p class="muted">هنوز هدف بالادستی ثبت نشده است.</p><?php endif?></div></div>
<div><h3>اهداف هم‌راستای زیرمجموعه</h3><div class="okr-alignment-list"><?php foreach($data['alignedChildren'] as $alignment):?><a class="okr-alignment-child" href="/admin/okr-objective.php?id=<?=$alignment['child_objective_id']?>"><strong><?=e($alignment['child_title'])?></strong><small><?=e($alignment['child_owner_name'].' · '.(string)$alignment['child_progress'].'٪')?></small></a><?php endforeach?><?php if(!$data['alignedChildren']):?><p class="muted">هدف زیرمجموعه‌ای ثبت نشده است.</p><?php endif?></div></div>
</div>
<?php if($canManage&&$alignmentCandidates):?><details class="okr-alignment-form"><summary>افزودن هم‌راستایی</summary><form method="post" class="admin-form"><input type="hidden" name="csrf_token" value="<?=e(Auth::csrfToken())?>"><input type="hidden" name="objective_id" value="<?=$objectiveId?>"><input type="hidden" name="action" value="save_alignment"><div class="grid grid-3"><label class="form-field grid-span-2"><span>هدف بالادستی</span><select name="parent_objective_id" required><option value="">انتخاب کنید</option><?php foreach($alignmentCandidates as $candidate):?><option value="<?=$candidate['id']?>"><?=e($candidate['title'].' · '.$candidate['owner_name'])?></option><?php endforeach?></select></label><label class="form-field"><span>نوع ارتباط</span><select name="alignment_type"><?php foreach(OkrService::ALIGNMENT_TYPES as $key=>$label):?><option value="<?=$key?>"><?=e($label)?></option><?php endforeach?></select></label><label class="form-field"><span>سهم مشارکت</span><input type="number" min="0" max="100" step="0.01" name="contribution_weight" value="100"></label><label class="form-field grid-span-2"><span>یادداشت</span><input name="alignment_note" maxlength="500" placeholder="این هدف چگونه به هدف بالادستی کمک می‌کند؟"></label></div><button class="btn btn-primary">ذخیره هم‌راستایی</button></form></details><?php endif?>
</section>

<div class="grid grid-2 okr-insight-grid">
<section class="card okr-trend-card" data-okr-reveal><div class="section-heading-row"><div><h2>نمودار روند</h2><p class="muted">تغییر امتیاز Objective در آخرین محاسبات و Check-inها.</p></div><span class="badge"><?=count($trend)?> نقطه</span></div>
<?php if($trend):?><svg class="okr-trend-chart" viewBox="0 0 100 40" role="img" aria-label="روند امتیاز هدف"><line x1="4" y1="36" x2="96" y2="36"></line><line x1="4" y1="4" x2="4" y2="36"></line><polyline points="<?=e(implode(' ', $trendPoints))?>"></polyline><?php foreach($trendPoints as $index=>$point):$coordinates=explode(',',$point);?><circle cx="<?=e($coordinates[0])?>" cy="<?=e($coordinates[1])?>" r="1.4"><title><?=e((string)$trend[$index]['score_percent'].'٪ · '.JalaliDate::toJalaliDateTime($trend[$index]['recorded_at']))?></title></circle><?php endforeach?></svg><div class="okr-trend-legend"><span>ابتدا: <?=e((string)$trend[0]['score_percent'])?>٪</span><span>آخرین: <?=e((string)$trend[count($trend)-1]['score_percent'])?>٪</span><span>سقف نمودار: <?=e((string)$trendMax)?>٪</span></div><?php else:?><p class="muted">پس از اولین محاسبه یا Check-in، روند نمایش داده می‌شود.</p><?php endif?>
</section>
<section class="card" data-okr-reveal><div class="section-heading-row"><div><h2>مصوبات مرتبط</h2><p class="muted">مصوبات متصل‌شده از صفحه صورتجلسه.</p></div><span class="badge"><?=count($decisionLinks)?> مصوبه</span></div><div class="okr-decision-links"><?php foreach($decisionLinks as $link):?><article><div><strong><?=e($link['decision_title'])?></strong><small><?=e($link['meeting_title'].' · '.($link['key_result_title']?:'کل Objective'))?></small></div><div><span><?=e((string)$link['decision_progress'])?>٪</span><a class="btn btn-small" href="/admin/management-decision-view.php?id=<?=$link['decision_id']?>">مشاهده مصوبه</a></div></article><?php endforeach?><?php if(!$decisionLinks):?><p class="muted">مصوبه مرتبطی ثبت نشده است.</p><?php endif?></div></section>
</div>

<section class="card okr-ai-section" data-okr-reveal>
<div class="section-heading-row"><div><h2>تحلیل هوشمند OKR</h2><p class="muted">تحلیل فقط خواندنی است؛ هیچ Objective، KR، مقدار یا اقدامی بدون تأیید شما تغییر نمی‌کند.</p></div><span class="okr-source-badge"><?=setting('sobhan_api_enabled','0')==='1'?'SobhanAI + تحلیل قطعی':'تحلیل قطعی داخلی'?></span></div>
<?php if($canRunAi):?><form method="post" class="okr-ai-actions"><input type="hidden" name="csrf_token" value="<?=e(Auth::csrfToken())?>"><input type="hidden" name="objective_id" value="<?=$objectiveId?>"><input type="hidden" name="action" value="run_ai_analysis"><?php foreach(OkrAiAnalysisService::TYPES as $key=>$label):?><button class="btn btn-light" name="analysis_type" value="<?=e($key)?>"><?=e($label)?></button><?php endforeach?></form><?php else:?><p class="muted">برای اجرا، مجوز تحلیل OKR یا دستیار هوشمند همراه با مدیریت این هدف لازم است.</p><?php endif?>
<div class="okr-ai-history"><?php foreach($aiAnalyses as $analysis):$aiResult=OkrAiAnalysisService::decodeResult($analysis);?><details <?=$analysis===$aiAnalyses[0]?'open':''?>><summary><span><strong><?=e(OkrAiAnalysisService::TYPES[$analysis['analysis_type']]??$analysis['analysis_type'])?></strong><small><?=e($analysis['requester_name'].' · '.JalaliDate::toJalaliDateTime($analysis['created_at']))?></small></span><span class="badge"><?=e(['sobhan_ai'=>'SobhanAI','sobhan_ai_text'=>'SobhanAI متنی','deterministic'=>'تحلیل قطعی'][$analysis['source']]??$analysis['source'])?></span></summary><article><p><?=nl2br(e($aiResult['executive_summary']))?></p><?php foreach(['strengths'=>'نقاط قوت','risks'=>'ریسک‌ها','recommended_actions'=>'اقدامات پیشنهادی','suggested_objectives'=>'Objectiveهای پیشنهادی','suggested_key_results'=>'KRهای پیشنهادی','data_warnings'=>'هشدار کیفیت داده'] as $resultKey=>$resultLabel):if(empty($aiResult[$resultKey]))continue;?><div><h4><?=e($resultLabel)?></h4><ul><?php foreach($aiResult[$resultKey] as $item):?><li><?=e($item)?></li><?php endforeach?></ul></div><?php endforeach?><?php if($analysis['status']==='fallback'):?><small class="muted">سرویس بیرونی در دسترس نبود؛ نتیجه قطعی داخلی نمایش داده شده است.</small><?php endif?></article></details><?php endforeach?><?php if(!$aiAnalyses):?><p class="muted">هنوز تحلیلی ثبت نشده است. یکی از تحلیل‌های بالا را اجرا کنید.</p><?php endif?></div>
</section>

<?php if($canManage&&$objective['status']!=='pending_approval'):?><section class="card admin-form" id="kr-form"><details <?=$editKr?'open':''?>><summary><strong><?=$editKr?'ویرایش Key Result':'افزودن Key Result'?></strong></summary><form method="post"><input type="hidden" name="csrf_token" value="<?=e(Auth::csrfToken())?>"><input type="hidden" name="objective_id" value="<?=$objectiveId?>"><input type="hidden" name="action" value="save_kr"><input type="hidden" name="key_result_id" value="<?=e((string)($editKr['id']??0))?>"><div class="grid grid-3">
<label class="form-field grid-span-2"><span>عنوان نتیجه کلیدی</span><input name="title" required maxlength="255" value="<?=e($editKr['title']??'')?>" placeholder="مثلاً افزایش فروش خالص از ۲۰ به ۲۵ میلیارد ریال"></label>
<label class="form-field"><span>مسئول KR</span><select name="owner_user_id"><?php foreach($owners as $owner):?><option value="<?=$owner['id']?>" <?=(int)($editKr['owner_user_id']??$objective['owner_user_id'])===(int)$owner['id']?'selected':''?>><?=e($owner['name'])?></option><?php endforeach?></select></label>
<label class="form-field"><span>نوع شاخص</span><select name="metric_type"><?php foreach(OkrService::METRIC_TYPES as $key=>$label):?><option value="<?=$key?>" <?=($editKr['metric_type']??'number')===$key?'selected':''?>><?=e($label)?></option><?php endforeach?></select></label>
<label class="form-field"><span>واحد</span><select name="unit"><?php foreach(OkrService::UNITS as $key=>$label):?><option value="<?=$key?>" <?=($editKr['unit']??'count')===$key?'selected':''?>><?=e($label)?></option><?php endforeach?></select></label>
<label class="form-field"><span>جهت</span><select name="direction"><?php foreach(OkrService::DIRECTIONS as $key=>$label):?><option value="<?=$key?>" <?=($editKr['direction']??'increase')===$key?'selected':''?>><?=e($label)?></option><?php endforeach?></select></label>
<label class="form-field"><span>مقدار مبنا</span><input inputmode="decimal" name="baseline_value" required value="<?=e((string)($editKr['baseline_value']??0))?>"></label>
<label class="form-field"><span>مقدار هدف</span><input inputmode="decimal" name="target_value" required value="<?=e((string)($editKr['target_value']??0))?>"></label>
<label class="form-field"><span>وزن KR</span><input type="number" min="0" max="100" step="0.01" name="weight" value="<?=e((string)($editKr['weight']??0))?>"></label>
<label class="form-field"><span>مهلت</span><input class="jalali-date-input" name="due_date" required value="<?=e(isset($editKr['due_date'])?JalaliDate::toJalali($editKr['due_date']):JalaliDate::toJalali($objective['due_date']))?>"></label>
<label class="form-field"><span>روش بروزرسانی</span><select name="data_source_type" data-okr-source-type><option value="manual" <?=($editKr['data_source_type']??'manual')==='manual'?'selected':''?>>ثبت دستی</option><option value="automatic" <?=($editKr['data_source_type']??'manual')==='automatic'?'selected':''?>>خودکار از داده واقعی</option></select></label>
</div>
<div class="okr-source-panel" data-okr-source-panel><div><strong>منبع داده امن</strong><p class="muted">فقط منابع تأییدشده و محدود به Scope سازمانی قابل انتخاب‌اند؛ SQL یا View دلخواه پذیرفته نمی‌شود.</p></div><div class="grid grid-3">
<label class="form-field grid-span-2"><span>منبع</span><select name="data_source_key" data-okr-source-key><option value="">انتخاب کنید</option><?php foreach($sourceDefinitions as $key=>$definition):?><option value="<?=e($key)?>" <?=($sourceConfig['source_key']??'')===$key?'selected':''?> data-filters="<?=e(implode(',', $definition['filters']))?>"><?=e($definition['label'].' — '.$definition['description'])?></option><?php endforeach?></select></label>
<label class="form-field" data-source-filter="period_key"><span>دوره فروش میلادی</span><input name="source_period_key" value="<?=e((string)($sourceConfig['period_key']??''))?>" placeholder="2026-07"></label>
<label class="form-field" data-source-filter="line_code"><span>کد لاین فروش</span><input name="source_line_code" value="<?=e((string)($sourceConfig['line_code']??''))?>" placeholder="A"></label>
<label class="form-field" data-source-filter="year"><span>سال میلادی</span><input type="number" min="2000" max="2100" name="source_year" value="<?=e((string)($sourceConfig['year']??''))?>"></label>
<label class="form-field" data-source-filter="month"><span>ماه</span><input type="number" min="1" max="12" name="source_month" value="<?=e((string)($sourceConfig['month']??''))?>"></label>
<label class="form-field" data-source-filter="status"><span>وضعیت اقدام</span><input name="source_status" value="<?=e((string)($sourceConfig['status']??''))?>" placeholder="open"></label>
<label class="form-field" data-source-filter="priority"><span>اولویت اقدام</span><select name="source_priority"><option value="">همه</option><?php foreach(OkrService::PRIORITIES as $key=>$label):?><option value="<?=$key?>" <?=($sourceConfig['priority']??'')===$key?'selected':''?>><?=e($label)?></option><?php endforeach?></select></label>
<label class="form-field" data-source-filter="kpi_period_id"><span>شناسه دوره KPI</span><input type="number" min="1" name="source_kpi_period_id" value="<?=e((string)($sourceConfig['kpi_period_id']??''))?>"></label>
<label class="form-field" data-source-filter="kpi_template_id"><span>شناسه قالب KPI</span><input type="number" min="1" name="source_kpi_template_id" value="<?=e((string)($sourceConfig['kpi_template_id']??''))?>"></label>
<label class="form-field" data-source-filter="kpi_criteria_id"><span>شناسه معیار KPI</span><input type="number" min="1" name="source_kpi_criteria_id" value="<?=e((string)($sourceConfig['kpi_criteria_id']??''))?>"></label>
</div></div>
<div class="form-actions"><button class="btn btn-primary">ذخیره KR</button><?php if($editKr):?><a class="btn" href="/admin/okr-objective.php?id=<?=$objectiveId?>#kr-form">انصراف</a><?php endif?></div></form></details></section><?php endif?>

<section class="okr-kr-list"><div class="section-heading-row"><div><h2>نتایج کلیدی</h2><p class="muted">پیشرفت Objective از میانگین وزنی این نتایج محاسبه می‌شود.</p></div><div class="form-actions"><strong>مجموع وزن: <?=e((string)array_sum(array_map(static fn($kr)=>(float)$kr['weight'],$krs)))?>٪</strong><?php if($canManage&&$automaticCount>0):?><form method="post"><input type="hidden" name="csrf_token" value="<?=e(Auth::csrfToken())?>"><input type="hidden" name="objective_id" value="<?=$objectiveId?>"><input type="hidden" name="action" value="refresh_all_automatic"><button class="btn btn-small" type="submit">بروزرسانی <?=e((string)$automaticCount)?> KR خودکار</button></form><?php endif?></div></div>
<?php foreach($krs as $kr):[$krClass,$krLabel]=OkrService::scoreBand((float)$kr['progress_percent']);$canCheckin=OkrService::canCheckin($objective,$kr);$isAutomatic=($kr['data_source_type']??'manual')==='automatic';?><article class="card okr-kr-card" data-okr-reveal><header><div><span class="okr-health health-<?=e($kr['health_status'])?>"><?=e(OkrService::HEALTH_STATUSES[$kr['health_status']]??$kr['health_status'])?></span><span class="badge">وزن <?=e((string)$kr['weight'])?>٪</span><?php if($isAutomatic):?><span class="okr-source-badge">خودکار · <?=e(OkrService::keyResultSourceLabel($kr))?></span><?php endif?></div><strong class="score-<?=e($krClass)?>"><?=e((string)$kr['progress_percent'])?>٪</strong></header><h3><?=e($kr['title'])?></h3><p class="muted"><?=e($kr['owner_name'])?> · <?=e(OkrService::DIRECTIONS[$kr['direction']]??$kr['direction'])?> · <?=e(OkrService::UNITS[$kr['unit']]??$kr['unit'])?></p><div class="okr-value-grid"><span>مبنا <strong><?=e((string)$kr['baseline_value'])?></strong></span><span>فعلی <strong><?=e((string)$kr['current_value'])?></strong></span><span>هدف <strong><?=e((string)$kr['target_value'])?></strong></span><span>مهلت <strong><?=e(JalaliDate::toJalali($kr['due_date']))?></strong></span></div><div class="okr-progress"><span style="width:<?=e((string)min(100,max(0,(float)$kr['progress_percent'])))?>%"></span></div><footer><small><?php if($isAutomatic):?>آخرین محاسبه: <?=e($kr['last_calculated_at']?JalaliDate::toJalaliDateTime($kr['last_calculated_at']):'هنوز محاسبه نشده')?><?php else:?><?=e((string)$kr['checkin_count'])?> Check-in · <?=e($kr['last_checkin_at']?JalaliDate::toJalaliDateTime($kr['last_checkin_at']):'هنوز ثبت نشده')?><?php endif?></small><div class="form-actions"><?php if($canManage&&$isAutomatic):?><form method="post"><input type="hidden" name="csrf_token" value="<?=e(Auth::csrfToken())?>"><input type="hidden" name="objective_id" value="<?=$objectiveId?>"><input type="hidden" name="action" value="refresh_kr"><input type="hidden" name="key_result_id" value="<?=$kr['id']?>"><button class="btn btn-small" type="submit">بروزرسانی از داده واقعی</button></form><?php endif?><?php if($canManage&&$objective['status']!=='pending_approval'):?><a class="btn btn-small" href="?id=<?=$objectiveId?>&edit_kr=<?=$kr['id']?>#kr-form">ویرایش</a><?php endif?></div></footer>
<?php if($canCheckin&&in_array($objective['status'],['active','approved','at_risk','off_track'],true)):?><details class="okr-checkin"><summary>ثبت Check-in جدید</summary><form method="post" enctype="multipart/form-data" class="admin-form"><input type="hidden" name="csrf_token" value="<?=e(Auth::csrfToken())?>"><input type="hidden" name="objective_id" value="<?=$objectiveId?>"><input type="hidden" name="action" value="checkin"><input type="hidden" name="key_result_id" value="<?=$kr['id']?>"><div class="grid grid-3"><label class="form-field"><span>مقدار فعلی</span><input inputmode="decimal" name="current_value" required value="<?=e((string)$kr['current_value'])?>"></label><label class="form-field"><span>سطح اطمینان</span><select name="confidence_level"><?php foreach(OkrService::CONFIDENCE_LEVELS as $key=>$label):?><option value="<?=$key?>"><?=e($label)?></option><?php endforeach?></select></label><label class="form-field"><span>وضعیت سلامت</span><select name="health_status"><?php foreach(OkrService::HEALTH_STATUSES as $key=>$label):?><option value="<?=$key?>" <?=$kr['health_status']===$key?'selected':''?>><?=e($label)?></option><?php endforeach?></select></label><label class="form-field"><span>مانع یا مشکل</span><textarea name="blocker_text" rows="3"></textarea></label><label class="form-field"><span>اقدام بعدی</span><textarea name="next_action" rows="3"></textarea></label><label class="form-field"><span>توضیحات</span><textarea name="note" rows="3"></textarea></label><label class="form-field grid-span-2"><span>مدرک اختیاری (حداکثر ۵MB)</span><input type="file" name="evidence" accept=".pdf,.png,.jpg,.jpeg,.webp,.txt,.csv,.xlsx,.docx"></label></div><button class="btn btn-primary">ثبت Check-in</button></form></details><?php endif?></article><?php endforeach?>
<?php if(!$krs):?><section class="card okr-empty"><p>هنوز نتیجه کلیدی ثبت نشده است.</p></section><?php endif?></section>

<?php if($canManage):?><section class="card admin-form"><details><summary><strong>ایجاد Initiative و افزودن به پلنر</strong></summary><form method="post"><input type="hidden" name="csrf_token" value="<?=e(Auth::csrfToken())?>"><input type="hidden" name="objective_id" value="<?=$objectiveId?>"><input type="hidden" name="action" value="initiative"><div class="grid grid-3"><label class="form-field grid-span-2"><span>عنوان اقدام</span><input name="title" maxlength="255" required></label><label class="form-field"><span>مسئول</span><select name="owner_user_id"><?php foreach($owners as $owner):?><option value="<?=$owner['id']?>" <?=(int)$objective['owner_user_id']===(int)$owner['id']?'selected':''?>><?=e($owner['name'])?></option><?php endforeach?></select></label><label class="form-field"><span>KR مرتبط</span><select name="key_result_id"><option value="0">کل Objective</option><?php foreach($krs as $kr):?><option value="<?=$kr['id']?>"><?=e($kr['title'])?></option><?php endforeach?></select></label><label class="form-field"><span>اولویت</span><select name="priority"><?php foreach(OkrService::PRIORITIES as $key=>$label):?><option value="<?=$key?>"><?=e($label)?></option><?php endforeach?></select></label><label class="form-field"><span>شروع</span><input class="jalali-date-input" name="start_date" value="<?=e(JalaliDate::toJalali(date('Y-m-d')))?>"></label><label class="form-field"><span>مهلت</span><input class="jalali-date-input" name="due_date" required value="<?=e(JalaliDate::toJalali($objective['due_date']))?>"></label><label class="form-field grid-span-2"><span>شرح اقدام</span><textarea name="description" rows="3"></textarea></label></div><button class="btn btn-primary">ساخت اقدام و تسک پلنر</button></form></details></section><?php endif?>

<div class="grid grid-2"><section class="card"><h2>اقدامات و پلنر</h2><div class="table-wrap"><table><thead><tr><th>اقدام</th><th>مسئول</th><th>مهلت</th><th>پلنر</th></tr></thead><tbody><?php foreach($data['initiatives'] as $item):?><tr><td><strong><?=e($item['title'])?></strong><small><?=e($item['key_result_title']?:'کل Objective')?></small></td><td><?=e($item['owner_name'])?></td><td><?=e(JalaliDate::toJalali($item['due_date']))?></td><td><a href="/employee/work-planner.php?view=list">#<?=e((string)$item['planner_task_id'])?></a><small><?=e(($item['planner_status']?:'—').' · '.(string)($item['planner_progress']??0).'٪')?></small></td></tr><?php endforeach?><?php if(!$data['initiatives']):?><tr><td colspan="4">اقدامی ثبت نشده است.</td></tr><?php endif?></tbody></table></div></section>
<section class="card"><h2>تاریخچه تأیید</h2><div class="okr-timeline"><?php foreach($data['approvals'] as $approval):?><article><strong><?=e(['pending'=>'در انتظار','approved'=>'تأیید','rejected'=>'بازگشت'][$approval['decision']]??$approval['decision'])?></strong><span><?=e($approval['approver_name']?:$approval['requester_name'])?></span><small><?=e(JalaliDate::toJalaliDateTime($approval['decided_at']?:$approval['created_at']))?></small><?php if($approval['note']):?><p><?=e($approval['note'])?></p><?php endif?></article><?php endforeach?><?php if(!$data['approvals']):?><p class="muted">هنوز گردش تأییدی ثبت نشده است.</p><?php endif?></div></section></div>

<section class="card"><h2>آخرین Check-inها</h2><div class="table-wrap"><table><thead><tr><th>KR</th><th>مقدار / پیشرفت</th><th>سلامت / اطمینان</th><th>مانع و اقدام بعدی</th><th>ثبت‌کننده</th></tr></thead><tbody><?php foreach($data['checkins'] as $checkin):?><tr><td><?=e($checkin['key_result_title'])?><small><?=e(JalaliDate::toJalaliDateTime($checkin['created_at']))?></small></td><td><?=e((string)$checkin['current_value'])?> · <?=e((string)$checkin['progress_percent'])?>٪</td><td><?=e(OkrService::HEALTH_STATUSES[$checkin['health_status']]??$checkin['health_status'])?><small>اطمینان: <?=e(OkrService::CONFIDENCE_LEVELS[$checkin['confidence_level']]??$checkin['confidence_level'])?></small></td><td><small>مانع: <?=e($checkin['blocker_text']?:'—')?></small><small>بعدی: <?=e($checkin['next_action']?:'—')?></small></td><td><?=e($checkin['creator_name'])?></td></tr><?php endforeach?><?php if(!$data['checkins']):?><tr><td colspan="5">Check-in ثبت نشده است.</td></tr><?php endif?></tbody></table></div></section>

<div class="grid grid-2"><section class="card"><h2>شواهد</h2><div class="okr-evidence-list"><?php foreach($data['evidence'] as $evidence):?><a href="/admin/okr-evidence.php?id=<?=$evidence['id']?>"><strong><?=e($evidence['original_name'])?></strong><small><?=e($evidence['uploader_name'].' · '.number_format((int)$evidence['file_size']/1024,1).' KB')?></small></a><?php endforeach?><?php if(!$data['evidence']):?><p class="muted">مدرکی بارگذاری نشده است.</p><?php endif?></div></section><section class="card"><h2>Audit Log</h2><div class="okr-timeline"><?php foreach($data['audit'] as $log):?><article><strong><?=e($log['action'])?></strong><span><?=e($log['actor_name']?:'سیستم')?></span><small><?=e(JalaliDate::toJalaliDateTime($log['created_at']))?></small><?php if($log['note']):?><p><?=e($log['note'])?></p><?php endif?></article><?php endforeach?></div></section></div>
<?php require __DIR__ . '/../views/partials/admin-footer.php';
