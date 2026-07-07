<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/ManagerDashboard.php';
require_once __DIR__ . '/../lib/OrgAccess.php';

Auth::requirePermission('manager_dashboard.view');
$pageTitle = ManagerDashboard::setting('dashboard_title');
$aiDashboardEnabled=setting('sobhan_api_enabled','0')==='1'&&setting('sobhan_ai_autofill_enabled','0')==='1';$dashboardCache=Database::fetch('SELECT source,updated_at FROM dashboard_data_cache WHERE dashboard_key="manager_dashboard" AND scope_key="all" LIMIT 1');$dashboardSource=$aiDashboardEnabled?($dashboardCache['source']??'Windows Server API - در انتظار بروزرسانی'):'دستی / دیتابیس';
$definitions = ManagerDashboard::definitions();
$requestedReportId = (int)($_GET['report_id'] ?? 0);
if ($requestedReportId) $report = ManagerDashboard::latestReport($requestedReportId);
elseif (ManagerDashboard::setting('show_latest_report_by_default') === '1' || ManagerDashboard::setting('default_report_mode') === 'latest_report') $report = Database::fetch("SELECT * FROM manager_dashboard_reports WHERE import_status='success' ORDER BY report_date DESC,id DESC LIMIT 1");
else $report = ManagerDashboard::latestReport((int)ManagerDashboard::setting('default_report_id') ?: null);
$reportId = (int)($report['id'] ?? 0);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) { http_response_code(419); exit('درخواست نامعتبر است.'); }
    $action = $_POST['action'] ?? '';
    $widget = $_POST['widget'] ?? '';
    $def = $definitions[$widget] ?? null;
    if (in_array($action, ['save','delete'], true)) Auth::requirePermission('manager_dashboard.edit');
    if ($action === 'delete' && $def) {
        Database::execute("DELETE FROM `{$def['table']}` WHERE id=? AND report_id=?", [(int)$_POST['id'],$reportId]);
        flash('success','ردیف حذف شد.'); redirect('/admin/manager-dashboard.php?report_id='.$reportId.'#widget-'.$widget);
    }
    if ($action === 'save' && $def) {
        $errors = ManagerDashboard::saveRow($widget, $_POST, $reportId, (int)($_POST['id'] ?? 0) ?: null);
        if (!$errors) { flash('success','اطلاعات ذخیره شد.'); redirect('/admin/manager-dashboard.php?report_id='.$reportId.'#widget-'.$widget); }
    }
    if ($action === 'ai') {
        Auth::requirePermission('manager_dashboard.ai_run');
        $ai = ManagerDashboard::aiSettings();
        Auth::start();
        if (!(int)($ai['ai_enabled'] ?? 0) || !(int)($ai['sobhan_api_enabled'] ?? 0)) $errors[] = 'ارتباط با سرویس هوش مصنوعی برقرار نشد.';
        elseif (time()-(int)($_SESSION['manager_dashboard_ai_last_run']??0)<2) $errors[] = 'لطفاً چند ثانیه صبر کنید و دوباره تلاش کنید.';
        else {
            $_SESSION['manager_dashboard_ai_last_run']=time();
            $skillKey=trim((string)($_POST['skill_key']??'basic_summary'));
            $enabledSkills=[]; foreach(ManagerDashboard::enabledSkills() as $skill)$enabledSkills[$skill['skill_key']]=$skill;
            if (!(int)($ai['ai_skills_enabled']??0)) $skillKey='basic_summary';
            $skill=$skillKey==='basic_summary'?['skill_key'=>'basic_summary','skill_title_fa'=>'تحلیل کلی گزارش','system_prompt'=>'یک خلاصه مدیریتی کوتاه و عملی به فارسی بده.']:($enabledSkills[$skillKey]??null);
            if(!$skill){$errors[]='مهارت انتخاب‌شده فعال نیست.';}
            $aiReport=((int)($ai['ai_read_selected_report']??0)&&$report)?$report:((int)($ai['ai_read_latest_report']??0)?Database::fetch("SELECT * FROM manager_dashboard_reports WHERE import_status='success' ORDER BY report_date DESC,id DESC LIMIT 1"):null);
            if(!$aiReport){$errors[]='اجازه خواندن گزارش برای هوش مصنوعی فعال نیست.';}
            $aiReportId = (int)($aiReport['id'] ?? 0);
            if($skill&&$aiReport){
                $tables=[]; foreach(['commission_summary','team_target_achievement','customer_coverage','brand_target','visitor_penalty'] as $key){$def=$definitions[$key];$name=$key==='brand_target'?'brand_target_achievement':($key==='visitor_penalty'?'penalty_coefficients':$key);$tables[$name]=Database::fetchAll('SELECT * FROM `'.$def['table'].'` WHERE report_id=? ORDER BY id LIMIT 100',[$aiReportId]);}
                $rules=ManagerDashboardCalculator::rules(); $calculated=[];
                if($skillKey==='calculate_commission')foreach($tables['commission_summary'] as $row)$calculated[]=array_merge(['visitor_name'=>$row['visitor_name']??''],ManagerDashboardCalculator::calculateCommission($row,$rules));
                elseif($skillKey==='calculate_achievement')foreach($tables['team_target_achievement'] as $row)$calculated[]=['person_name'=>$row['person_name']??'','achievement_percent'=>ManagerDashboardCalculator::calculateAchievement((float)($row['target_qty']??0),(float)($row['sold_qty']??0)),'remaining_to_target'=>ManagerDashboardCalculator::calculateRemainingToTarget((float)($row['target_qty']??0),(float)($row['sold_qty']??0))];
                elseif($skillKey==='calculate_penalty')foreach($tables['commission_summary'] as $row)$calculated[]=array_merge(['visitor_name'=>$row['visitor_name']??''],ManagerDashboardCalculator::calculatePenalty($row,$rules));
                elseif($skillKey==='calculate_customer_coverage')foreach($tables['customer_coverage'] as $row)$calculated[]=array_merge(['visitor_name'=>$row['visitor_name']??''],ManagerDashboardCalculator::calculateCustomerCoverage($row,$rules));
                elseif($skillKey==='calculate_brand_target')foreach($tables['brand_target_achievement'] as $row)$calculated[]=array_merge(['visitor_name'=>$row['visitor_name']??''],ManagerDashboardCalculator::calculateBrandAchievement($row,$rules));
                $summary=ManagerDashboardCalculator::summarizeReport($aiReportId); if($calculated)$summary['deterministic_results']=$calculated;
                if((int)($ai['ai_read_history']??0))$summary['limited_history']=Database::fetchAll("SELECT report_title,report_date,report_month,report_year FROM manager_dashboard_reports WHERE import_status='success' ORDER BY report_date DESC,id DESC LIMIT 6");
                $responseSchema=json_decode((string)($skill['output_schema_json']??''),true)?:['executive_summary'=>'','strengths'=>[],'weaknesses'=>[],'risks'=>[],'visitor_actions'=>[],'line_actions'=>[],'data_warnings'=>[],'recommended_next_steps'=>[]];
                $context=['module'=>'manager_dashboard','report_id'=>$aiReportId,'report_date'=>$aiReport['report_date']??'','skill_key'=>$skillKey,'skill_title'=>$skill['skill_title_fa'],'business_rules'=>$rules,'summary'=>$summary,'tables'=>$tables,'response_schema'=>$responseSchema,'instruction'=>trim((string)($skill['system_prompt']??'')).' داده‌های محاسبه‌شده را تغییر نده. به فارسی و فقط با JSON معتبر مطابق response_schema، بدون markdown و بدون کدبلاک پاسخ بده.'];
                $result=ManagerDashboard::callAi($context,$ai); $user=Auth::user();
                if($result['ok']){$aiAnswer=$result['content'];$aiKnowledgeSources=$result['knowledge_sources']??[];Database::execute('INSERT INTO manager_dashboard_ai_logs (report_id,user_id,skill_key,request_summary,response_text,status,created_at) VALUES (?,?,?,?,?,"success",NOW())',[$aiReportId,(int)$user['id'],$skillKey,json_encode(['report_id'=>$aiReportId,'skill_key'=>$skillKey,'table_counts'=>array_map('count',$tables)],JSON_UNESCAPED_UNICODE),$aiAnswer]);}
                else{$message=$result['message']??'ارتباط با سرویس هوش مصنوعی برقرار نشد.';$errors[]=$message;Database::execute('INSERT INTO manager_dashboard_ai_logs (report_id,user_id,skill_key,request_summary,status,error_message,created_at) VALUES (?,?,?,?,"error",?,NOW())',[$aiReportId,(int)$user['id'],$skillKey,json_encode(['report_id'=>$aiReportId,'skill_key'=>$skillKey],JSON_UNESCAPED_UNICODE),$message]);}
            }
        }
    }
}

$reports = Database::fetchAll("SELECT * FROM manager_dashboard_reports WHERE import_status='success' ORDER BY report_date DESC,id DESC");
$widgets = Database::fetchAll('SELECT * FROM manager_dashboard_widget_settings WHERE is_enabled=1 AND show_in_dashboard=1 ORDER BY sort_order,id');
$aiSettings = ManagerDashboard::aiSettings();
$commission = $reportId ? Database::fetchAll('SELECT * FROM manager_commission_summary WHERE report_id=?',[$reportId]) : [];
$lines = $reportId ? Database::fetchAll('SELECT * FROM manager_line_performance WHERE report_id=?',[$reportId]) : [];
$coverage = $reportId ? Database::fetchAll('SELECT * FROM manager_customer_coverage WHERE report_id=?',[$reportId]) : [];
$brands = $reportId ? Database::fetchAll('SELECT * FROM manager_brand_target_achievement WHERE report_id=?',[$reportId]) : [];
if (!Auth::isAdmin()) {
    $orgIds = OrgAccess::accessibleUserIds(Auth::user());
    $orgNames = $orgIds ? array_column(Database::fetchAll('SELECT name FROM users WHERE id IN ('.implode(',',array_fill(0,count($orgIds),'?')).')', $orgIds), 'name') : [];
    $nameAllowed = static fn(array $row): bool => in_array(trim((string)($row['visitor_name'] ?? $row['person_name'] ?? '')), $orgNames, true);
    $commission = array_values(array_filter($commission, $nameAllowed));
    $coverage = array_values(array_filter($coverage, $nameAllowed));
    // Legacy line/brand rows have no employee key, so they cannot be scoped safely.
    $lines = [];
    $brands = [];
}
$sum = fn(array $rows,string $field) => array_sum(array_map(fn($r)=>(float)($r[$field]??0),$rows));
$avg = fn(array $rows,string $field) => count($rows)?$sum($rows,$field)/count($rows):0;
$best = $commission; usort($best,fn($a,$b)=>(float)$b['achievement_percent']<=>(float)$a['achievement_percent']);
$worst=$best; $bestVisitor=$best[0]['visitor_name']??'-'; $worstVisitor=$worst?end($worst)['visitor_name']:'-';
$bestLines=$lines;usort($bestLines,fn($a,$b)=>(float)$b['achievement_percent']<=>(float)$a['achievement_percent']);
$kpis=[
 'مجموع فروش'=>number_format($sum($commission,'sales_amount')),
 'مجموع پورسانت نهایی'=>number_format($sum($commission,'final_commission')),
 'میانگین تحقق'=>number_format($avg($commission,'achievement_percent'),1).'٪',
 'ویزیتورهای واجد شرایط'=>count(array_filter($commission,fn($r)=>$r['condition_status']==='ok')),
 'تعداد عدم تحقق'=>count(array_filter($commission,fn($r)=>$r['condition_status']==='عدم تحقق')),
 'مجموع پاداش‌ها'=>number_format($sum($commission,'quality_bonus')+$sum($commission,'group_achievement_bonus')+$sum($commission,'total_achievement_bonus')+$sum($commission,'coverage_bonus')),
 'مجموع جرایم'=>number_format(abs($sum($commission,'return_loss'))),
 'بهترین ویزیتور'=>$bestVisitor,'ضعیف‌ترین ویزیتور'=>$worstVisitor,'بهترین لاین'=>$bestLines[0]['line_code']??'-'];
function md_value($value,string $type): string {
    if ($value === null || $value === '') return '';
    if (in_array($type,['money','signed_money'],true)) return (ManagerDashboard::setting('number_format')==='plain'?(string)(float)$value:number_format((float)$value)).' '.ManagerDashboard::setting('currency_label');
    if ($type==='percent') return number_format((float)$value,1).'٪';
    if ($type==='date') return ManagerDashboard::setting('date_format')==='gregorian'?(string)$value:format_jalali_date((string)$value);
    if ($type==='entity') return ['visitor'=>'ویزیتور','supervisor'=>'سرپرست','manager'=>'مدیر فروش'][$value]??$value;
    if ($type==='status') return (string)(['ok'=>'واجد شرایط'][$value]??$value);
    return (string)$value;
}
function md_date_label(string $value): string { return ManagerDashboard::setting('date_format')==='gregorian'?$value:format_jalali_date($value); }
function md_render_ai_answer(string $answer): string {
    $answer=trim((string)preg_replace('/^```(?:json|plaintext|text)?\s*|\s*```$/iu','',$answer));
    $decoded=json_decode($answer,true);
    if(!is_array($decoded))return nl2br(e($answer));
    if(isset($decoded['data']['answer'])&&is_string($decoded['data']['answer']))return md_render_ai_answer($decoded['data']['answer']);
    $labels=['executive_summary'=>'خلاصه مدیریتی','strengths'=>'نقاط قوت','weaknesses'=>'نقاط ضعف','risks'=>'ریسک‌ها','visitor_actions'=>'اقدامات پیشنهادی برای ویزیتورها','line_actions'=>'اقدامات پیشنهادی برای لاین‌ها','data_warnings'=>'هشدارهای داده‌ای','recommended_next_steps'=>'اقدامات بعدی پیشنهادی'];
    $html=''; foreach($labels as $key=>$label){if(!array_key_exists($key,$decoded)||$decoded[$key]===''||$decoded[$key]===[])continue;$html.='<section><h3>'.e($label).'</h3>';if(is_array($decoded[$key])){$html.='<ul>';foreach($decoded[$key] as $item)$html.='<li>'.e(is_scalar($item)?(string)$item:json_encode($item,JSON_UNESCAPED_UNICODE)).'</li>';$html.='</ul>';}else$html.='<p>'.nl2br(e((string)$decoded[$key])).'</p>';$html.='</section>';}
    return $html!==''?$html:e('پاسخ ساختاریافته سرویس قابل نمایش نیست.');
}
function md_input(array $field,$value=''): string {
    [$key,$label,$type]=$field; $name=e($key);$val=e((string)$value);
    if($type==='line'){ $html="<select name=\"{$name}\" required>";foreach(['A','B','C','D','A-B','C-D'] as $v)$html.='<option '.($value===$v?'selected':'').' value="'.e($v).'">'.e($v).'</option>';return $html.'</select>';}
    if($type==='entity'){ $html="<select name=\"{$name}\">";foreach(['visitor'=>'ویزیتور','supervisor'=>'سرپرست','manager'=>'مدیر فروش'] as $v=>$t)$html.='<option '.($value===$v?'selected':'').' value="'.$v.'">'.$t.'</option>';return $html.'</select>';}
    if($type==='status'){ $html="<select name=\"{$name}\"><option value=\"\">-</option>";foreach(['ok'=>'واجد شرایط','تحقق'=>'تحقق','عدم تحقق'=>'عدم تحقق'] as $v=>$t)$html.='<option '.($value===$v?'selected':'').' value="'.e($v).'">'.$t.'</option>';return $html.'</select>';}
    return '<input name="'.$name.'" value="'.$val.'" '.($type==='date'?'class="jalali-date-input" placeholder="1405/01/01"':(in_array($type,['money','signed_money','number','signed_number','percent'],true)?'inputmode="decimal"':'')).'>';
}
require __DIR__ . '/../views/partials/admin-header.php';
?>
<div class="dashboard-source-bar"><span>منبع بروزرسانی: <strong><?=e($dashboardSource)?></strong></span><span>آخرین بروزرسانی: <?=e($dashboardCache['updated_at']??'ثبت نشده')?></span><?php if(Auth::isAdmin()||Auth::can('ai_updates')):?><form data-dashboard-refresh><input type="hidden" name="csrf_token" value="<?=e(Auth::csrfToken())?>"><input type="hidden" name="dashboard_key" value="manager_dashboard"><button class="btn btn-small">بروزرسانی داشبورد</button></form><?php endif?></div>
<div class="manager-hero">
 <div><span>پنل مستقل مدیران فروش</span><h1><?= e($pageTitle) ?></h1><p><?= $report ? e($report['report_title']).' · '.e(md_date_label($report['report_date'])) : 'برای شروع یک فایل گزارش وارد کنید.' ?></p></div>
 <form method="get"><label>دوره گزارش<select name="report_id" onchange="this.form.submit()"><option value="">آخرین گزارش</option><?php foreach($reports as $r):?><option value="<?=$r['id']?>" <?=$reportId===$r['id']?'selected':''?>><?=e($r['report_title'].' - '.md_date_label($r['report_date']))?></option><?php endforeach?></select></label></form>
</div>
<?php foreach($errors as $error):?><div class="alert alert-error"><?=e(is_array($error)?implode(' ',$error):$error)?></div><?php endforeach?>
<?php if(!$report):?><div class="card manager-empty"><h2>هنوز گزارشی وجود ندارد</h2><p>قالب اکسل را دریافت کنید، اعداد را وارد کنید و فایل را بارگذاری کنید.</p><?php if(Auth::can('manager_dashboard.import')):?><a class="btn" href="/admin/manager-dashboard-import.php">ورودی اکسل</a><?php endif?></div><?php else:?>
<div class="manager-kpis"><?php foreach($kpis as $label=>$value):?><article><span><?=e($label)?></span><strong><?=e((string)$value)?></strong></article><?php endforeach?></div>
<div class="manager-chart-grid">
 <?php foreach([['تحقق ویزیتورها','visitorAchievementChart'],['پورسانت نهایی ویزیتورها','visitorCommissionChart'],['تحقق لاین‌ها','lineAchievementChart'],['پوشش مشتری','coverageChart'],['تحقق برندها','brandChart'],['ضرایب کاهنده','penaltyChart']] as [$title,$id]):?><article class="card"><h2><?=e($title)?></h2><canvas id="<?=$id?>"></canvas></article><?php endforeach?>
</div>
<nav class="manager-tabs"><?php foreach($widgets as $w):?><a href="#widget-<?=e($w['widget_key'])?>"><?=e($w['widget_title_fa'])?></a><?php endforeach?></nav>
<?php foreach($widgets as $setting): $key=$setting['widget_key']; if($key==='ai_insights'||!isset($definitions[$key]))continue;$def=$definitions[$key];
 $search=trim($_GET['search']??'');$line=trim($_GET['line_code']??'');$visitor=trim($_GET['visitor']??'');$supervisor=trim($_GET['supervisor']??'');
 $where=['report_id=?'];$params=[$reportId];
 $cols=array_column($def['fields'],0);if($search){$like=[];foreach($cols as $c)$like[]="CAST(`$c` AS CHAR) LIKE ?"; $where[]='('.implode(' OR ',$like).')';$params=array_merge($params,array_fill(0,count($cols),'%'.$search.'%'));}
 foreach(['line_code'=>$line,'line_group'=>$line,'visitor_name'=>$visitor,'supervisor_name'=>$supervisor] as $c=>$v)if($v&&in_array($c,$cols,true)){$where[]="`$c`=?";$params[]=$v;}
 $page=max(1,(int)(($_GET['widget']??'')===$key?($_GET['page']??1):1));$per=25;$total=(int)(Database::fetch("SELECT COUNT(*) c FROM `{$def['table']}` WHERE ".implode(' AND ',$where),$params)['c']??0);$offset=($page-1)*$per;
 $order=in_array($key,['team_target_achievement','over_achievement_bonus'],true)?"FIELD(entity_type,'visitor','supervisor','manager'),id DESC":'id DESC';
 $rows=Database::fetchAll("SELECT * FROM `{$def['table']}` WHERE ".implode(' AND ',$where)." ORDER BY {$order} LIMIT {$per} OFFSET {$offset}",$params);
 $imageUrl='/admin/manager-dashboard-image-export.php?'.http_build_query(['widget_key'=>$key,'report_id'=>$reportId,'format'=>ManagerDashboard::setting('image_export_format'),'search'=>$search,'line_code'=>$line,'visitor'=>$visitor,'supervisor'=>$supervisor,'page'=>$page]);?>
<section class="card manager-widget" id="widget-<?=e($key)?>">
 <header><div><h2><?=e($setting['widget_title_fa'])?></h2><?php if($setting['widget_description_fa']):?><p><?=e($setting['widget_description_fa'])?></p><?php endif?></div><div class="actions"><?php if(Auth::can('manager_dashboard.export')&&(int)$setting['allow_export']):?><a class="btn btn-light" href="/admin/manager-dashboard-export.php?report_id=<?=$reportId?>&widget=<?=e($key)?>">خروجی اکسل</a><?php endif?><?php if(Auth::can('manager_dashboard.image_export')&&ManagerDashboard::setting('image_export_enabled')==='1'&&(int)($setting['allow_image_export']??0)):?><a class="btn btn-light" target="_blank" href="<?=e($imageUrl)?>">خروجی تصویری</a><a class="btn btn-light" target="_blank" href="<?=e($imageUrl.'&print=1')?>">چاپ</a><?php endif?><a class="btn btn-light" href="<?=e($_SERVER['REQUEST_URI'])?>#widget-<?=e($key)?>">بروزرسانی</a><?php if(Auth::can('manager_dashboard.import')&&(int)$setting['allow_import']):?><a class="btn btn-light" href="/admin/manager-dashboard-import.php?widget=<?=e($key)?>">ورودی جدول</a><?php endif?><?php if(Auth::can('manager_dashboard.edit')&&(int)$setting['allow_manual_edit']):?><button class="btn" type="button" onclick="document.getElementById('add-<?=e($key)?>').showModal()">افزودن ردیف</button><?php endif?></div></header>
 <form class="manager-filter" method="get"><input type="hidden" name="report_id" value="<?=$reportId?>"><input type="hidden" name="widget" value="<?=e($key)?>"><input name="search" placeholder="جستجو..." value="<?=e($search)?>"><select name="line_code"><option value="">همه لاین‌ها</option><?php foreach(['A','B','C','D','A-B','C-D'] as $v):?><option <?=$line===$v?'selected':''?>><?=$v?></option><?php endforeach?></select><input name="visitor" placeholder="ویزیتور" value="<?=e($visitor)?>"><input name="supervisor" placeholder="سرپرست" value="<?=e($supervisor)?>"><button class="btn btn-light">اعمال فیلتر</button></form>
 <div class="table-wrap"><table><thead><tr><?php foreach($def['fields'] as $f):?><th><?=e($f[1])?></th><?php endforeach?><th>عملیات</th></tr></thead><tbody><?php foreach($rows as $row):?><tr><?php foreach($def['fields'] as [$field,,$type]): $class=$type==='percent'?' achievement-'.((float)$row[$field]>=100?'good':((float)$row[$field]>=75?'warn':'bad')):'';?><td><span class="<?=$class?>"><?=e(md_value($row[$field],$type))?></span></td><?php endforeach?><td class="row-actions"><?php if(Auth::can('manager_dashboard.edit')&&(int)$setting['allow_manual_edit']):?><button class="link-btn" type="button" onclick="document.getElementById('edit-<?=$key?>-<?=$row['id']?>').showModal()">ویرایش</button><form method="post" onsubmit="return confirm('ردیف حذف شود؟')"><input type="hidden" name="csrf_token" value="<?=e(Auth::csrfToken())?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="widget" value="<?=e($key)?>"><input type="hidden" name="id" value="<?=$row['id']?>"><button class="link-btn danger">حذف</button></form><?php endif?></td></tr>
 <dialog class="manager-dialog" id="edit-<?=$key?>-<?=$row['id']?>"><form method="post"><header><h3>ویرایش ردیف</h3><button type="button" onclick="this.closest('dialog').close()">×</button></header><input type="hidden" name="csrf_token" value="<?=e(Auth::csrfToken())?>"><input type="hidden" name="action" value="save"><input type="hidden" name="widget" value="<?=e($key)?>"><input type="hidden" name="id" value="<?=$row['id']?>"><div class="manager-form-grid"><?php foreach($def['fields'] as $f):?><label><span><?=e($f[1])?></span><?=md_input($f,$row[$f[0]])?></label><?php endforeach?></div><footer><button class="btn">ذخیره</button><button class="btn btn-light" type="button" onclick="this.closest('dialog').close()">انصراف</button></footer></form></dialog>
 <?php endforeach?><?php if(!$rows):?><tr><td colspan="<?=count($def['fields'])+1?>">داده‌ای برای این گزارش ثبت نشده است.</td></tr><?php endif?></tbody></table></div>
 <?php if($total>$per):?><div class="pagination"><?php for($p=1;$p<=ceil($total/$per);$p++):?><a class="<?=$p===$page?'active':''?>" href="?report_id=<?=$reportId?>&widget=<?=$key?>&page=<?=$p?>#widget-<?=$key?>"><?=$p?></a><?php endfor?></div><?php endif?>
</section>
<dialog class="manager-dialog" id="add-<?=e($key)?>"><form method="post"><header><h3>افزودن ردیف به <?=e($setting['widget_title_fa'])?></h3><button type="button" onclick="this.closest('dialog').close()">×</button></header><input type="hidden" name="csrf_token" value="<?=e(Auth::csrfToken())?>"><input type="hidden" name="action" value="save"><input type="hidden" name="widget" value="<?=e($key)?>"><div class="manager-form-grid"><?php foreach($def['fields'] as $f):?><label><span><?=e($f[1])?></span><?=md_input($f,$f[0]==='report_date'?format_jalali_date($report['report_date']):'')?></label><?php endforeach?></div><footer><button class="btn">ذخیره</button><button class="btn btn-light" type="button" onclick="this.closest('dialog').close()">انصراف</button></footer></form></dialog>
<?php endforeach?>
<?php if((int)($aiSettings['ai_enabled']??0)&&Auth::can('manager_dashboard.ai_run')):$buttonLabels=['calculate_achievement'=>'محاسبه و کنترل تحقق','calculate_commission'=>'بررسی پورسانت‌ها','calculate_penalty'=>'بررسی ضرایب کاهنده','calculate_customer_coverage'=>'تحلیل پوشش مشتری','calculate_brand_target'=>'تحلیل تحقق برند','detect_anomalies'=>'شناسایی مغایرت‌ها','generate_management_recommendations'=>'پیشنهاد اقدام مدیریتی'];?><section class="card manager-ai" id="widget-ai_insights"><h2>بینش هوشمند فروش</h2><p>تحلیل فقط خواندنی است و هیچ داده‌ای را تغییر نمی‌دهد.</p><?php if((int)($aiSettings['ai_show_buttons']??1)):?><form method="post"><input type="hidden" name="csrf_token" value="<?=e(Auth::csrfToken())?>"><input type="hidden" name="action" value="ai"><div class="actions"><button class="btn btn-light" name="skill_key" value="basic_summary">تحلیل کلی گزارش</button><?php if((int)($aiSettings['ai_skills_enabled']??0)): foreach(ManagerDashboard::enabledSkills() as $skill):?><button class="btn btn-light" name="skill_key" value="<?=e($skill['skill_key'])?>"><?=e($buttonLabels[$skill['skill_key']]??$skill['skill_title_fa'])?></button><?php endforeach; endif?></div></form><?php endif?><?php if(!empty($aiAnswer)):?><div class="ai-answer"><?=md_render_ai_answer($aiAnswer)?><?=render_ai_knowledge_sources($aiKnowledgeSources??[])?></div><?php endif?></section><?php endif?>
<script src="/assets/js/chart.umd.min.js"></script><script>
const chart=(id,labels,data,color)=>new Chart(document.getElementById(id),{type:'bar',data:{labels,datasets:[{data,backgroundColor:color,borderRadius:7}]},options:{responsive:true,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true}}}});
const visitors=<?=json_encode(array_column($commission,'visitor_name'),JSON_UNESCAPED_UNICODE)?>;
chart('visitorAchievementChart',visitors,<?=json_encode(array_map('floatval',array_column($commission,'achievement_percent')))?>,'#22c55e');chart('visitorCommissionChart',visitors,<?=json_encode(array_map('floatval',array_column($commission,'final_commission')))?>,'#3b82f6');
chart('lineAchievementChart',<?=json_encode(array_column($lines,'line_code'),JSON_UNESCAPED_UNICODE)?>,<?=json_encode(array_map('floatval',array_column($lines,'achievement_percent')))?>,'#14b8a6');chart('coverageChart',<?=json_encode(array_column($coverage,'visitor_name'),JSON_UNESCAPED_UNICODE)?>,<?=json_encode(array_map('floatval',array_column($coverage,'coverage_count')))?>,'#6366f1');chart('brandChart',<?=json_encode(array_column($brands,'visitor_name'),JSON_UNESCAPED_UNICODE)?>,<?=json_encode(array_map('floatval',array_column($brands,'achievement_percent')))?>,'#f59e0b');chart('penaltyChart',visitors,<?=json_encode(array_map('floatval',array_column($commission,'penalty_percent')))?>,'#ef4444');
</script>
<?php endif?>
</section></main></div><script src="/assets/js/app.js"></script></body></html>
