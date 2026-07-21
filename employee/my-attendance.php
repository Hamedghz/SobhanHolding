<?php
require_once __DIR__.'/../core/Auth.php';
require_once __DIR__.'/../core/Response.php';
require_once __DIR__.'/../core/JalaliDate.php';
require_once __DIR__.'/../lib/AppDate.php';
require_once __DIR__.'/../lib/HrAttendanceRepository.php';

Auth::requireLogin();
$user=Auth::user();
$userId=(int)$user['id'];
function my_attendance_minutes(int $minutes):string{return format_number(intdiv($minutes,60)).' ساعت و '.format_number($minutes%60).' دقیقه';}

$periodKey=trim((string)($_GET['period_key']??''));
$customFrom=trim((string)($_GET['date_from']??''));
$customTo=trim((string)($_GET['date_to']??''));
try{
    $period=AppDate::resolvePeriod($periodKey,$customFrom,$customTo,'monthly');
}catch(InvalidArgumentException $e){
    flash($e->getMessage(),'danger');
    $period=AppDate::resolvePeriod('',null,null,'monthly');
    $periodKey='';
    $customFrom=$customTo='';
}
$fromGregorian=(string)$period['start_date'];
$toGregorian=(string)$period['end_date'];
$filters=['date_from'=>$fromGregorian,'date_to'=>$toGregorian];
try{
    $rows=HrAttendanceRepository::myReport($userId,$filters);
    $stats=HrAttendanceRepository::myReportStats($rows,['date_from'=>$fromGregorian,'date_to'=>$toGregorian,'group_code'=>HrAttendanceRepository::groupCodeForUser($userId)]);
}catch(InvalidArgumentException $e){
    flash($e->getMessage(),'danger');$rows=[];$stats=HrAttendanceRepository::myReportStats([],[]);
}catch(Throwable $e){
    error_log('My attendance: '.$e->getMessage());flash('دریافت گزارش کارکرد انجام نشد.','danger');$rows=[];$stats=HrAttendanceRepository::myReportStats([],[]);
}
if(($_GET['export']??'')==='csv'){
    header('Content-Type: text/csv; charset=UTF-8');header('Content-Disposition: attachment; filename="my-attendance-'.date('Ymd-His').'.csv"');echo "\xEF\xBB\xBF";
    $out=fopen('php://output','wb');fputcsv($out,['تاریخ','وضعیت روز','ورود','خروج','کار مصوب دقیقه','کارکرد دقیقه','تأخیر','تعجیل','اضافه‌کاری عادی','اضافه‌کاری تعطیل','وضعیت تأیید اضافه‌کاری','جزئیات','توضیحات ایمپورت']);
    $safe=static fn($value)=>(is_string($value)&&preg_match('/^[=+\-@]/u',$value)?"'":'').$value;
    foreach($rows as $row){
        $details=$row['day_status']==='leave'?($row['leave_type']??''):($row['day_status']==='mission'?($row['mission_details']??''):($row['notes']??''));
        fputcsv($out,[
            JalaliDate::toJalali($row['attendance_date']),
            HrAttendanceRepository::DAY_STATUSES[$row['display_day_status']]??$row['display_day_status'],
            substr((string)$row['actual_in_time'],0,5),
            substr((string)$row['actual_out_time'],0,5),
            (int)$row['scheduled_work_minutes'],
            (int)$row['work_minutes'],
            (int)$row['late_minutes'],
            (int)$row['early_leave_minutes'],
            (int)$row['normal_overtime_minutes'],
            (int)$row['holiday_overtime_minutes'],
            HrAttendanceRepository::OVERTIME_STATUSES[$row['overtime_status']]??$row['overtime_status'],
            $safe($details),
            $safe($row['import_time_notes']??''),
        ]);
    }
    fclose($out);exit;
}
$query=$_GET;unset($query['employee_id'],$query['export']);
$pageTitle='کارکرد من';
$adminExtraStylesheets=['/assets/css/hr-attendance.css'];
$adminExtraScripts=['/assets/js/hr-attendance.js'];
require __DIR__.'/../views/partials/admin-header.php';
?>
<div class="attendance-page attendance-own-page">
    <header class="attendance-hero">
        <div><small>پنل شخصی منابع انسانی</small><h1>کارکرد من</h1><p><?=e((string)$period['title'])?> · <?=e(JalaliDate::toJalali($fromGregorian))?> تا <?=e(JalaliDate::toJalali($toGregorian))?></p></div>
        <a class="btn attendance-export" href="?<?=e(http_build_query([...$query,'export'=>'csv']))?>">خروجی CSV</a>
    </header>
    <section class="card attendance-filter-card">
        <form class="attendance-filter attendance-period-filter" method="get">
            <label><span>دوره کارکرد</span><?=app_period_select('period_key',$periodKey,['weekly','monthly','quarterly','half_yearly','yearly','custom'],['placeholder'=>'ماه جاری','custom_target'=>'#attendance-custom-period'])?></label>
            <div id="attendance-custom-period" class="attendance-custom-period" hidden>
                <label><span>از تاریخ</span><?=app_date_input('date_from',$customFrom,['required'=>true])?></label>
                <label><span>تا تاریخ</span><?=app_date_input('date_to',$customTo,['required'=>true])?></label>
            </div>
            <div class="attendance-actions"><button class="btn btn-primary">نمایش دوره</button><a class="btn" href="/employee/my-attendance.php">ماه جاری</a></div>
        </form>
    </section>
    <section class="attendance-kpis" data-attendance-reveal>
        <?php foreach(['work_minutes'=>'مجموع کارکرد','late_minutes'=>'مجموع تأخیر','early_leave_minutes'=>'مجموع تعجیل','approved_overtime_minutes'=>'اضافه‌کاری تأییدشده'] as $key=>$label):?>
            <article class="attendance-kpi"><span><?=e($label)?></span><strong><?=e(my_attendance_minutes((int)$stats[$key]))?></strong></article>
        <?php endforeach?>
    </section>
    <section class="attendance-status-strip" aria-label="خلاصه وضعیت روزها" data-attendance-reveal>
        <?php foreach(['absent'=>'غیبت','leave'=>'مرخصی','mission'=>'مأموریت','holiday_count'=>'تعطیل'] as $key=>$label):?>
            <article><span><?=e($label)?></span><strong><?=format_number($stats[$key])?></strong></article>
        <?php endforeach?>
        <p>روز کاری مورد انتظار: <strong><?=format_number($stats['expected_work_days'])?></strong> از <?=format_number($stats['calendar_days'])?> روز تقویمی</p>
    </section>
    <section class="card attendance-record-card" data-attendance-reveal>
        <div class="attendance-section-heading"><div><span>ریز کارکرد</span><h2><?=e((string)$period['title'])?></h2></div><small>تمام تاریخ‌ها شمسی نمایش داده می‌شوند.</small></div>
        <div class="table-wrap"><table class="attendance-table"><thead><tr><th>تاریخ</th><th>وضعیت</th><th>ورود / خروج</th><th>کارکرد</th><th>تأخیر / تعجیل</th><th>اضافه‌کاری</th><th>جزئیات</th></tr></thead><tbody>
        <?php foreach($rows as $row):$details=$row['day_status']==='leave'?($row['leave_type']??''):($row['day_status']==='mission'?($row['mission_details']??''):($row['notes']??''));?>
            <tr><td><strong><?=e(JalaliDate::toJalali($row['attendance_date']))?></strong></td><td><span class="attendance-badge attendance-<?=e($row['display_day_status'])?>"><?=e(HrAttendanceRepository::DAY_STATUSES[$row['display_day_status']]??$row['display_day_status'])?></span><?php if(!empty($row['holiday_title'])):?><small><?=e($row['holiday_title'])?></small><?php endif?></td><td><span class="attendance-time-pair"><?=e(substr((string)$row['actual_in_time'],0,5)?:'—')?> <b>تا</b> <?=e(substr((string)$row['actual_out_time'],0,5)?:'—')?></span></td><td><?=e(my_attendance_minutes((int)$row['work_minutes']))?><small>مصوب: <?=format_number($row['scheduled_work_minutes'])?> دقیقه</small></td><td><?=format_number($row['late_minutes'])?> / <?=format_number($row['early_leave_minutes'])?> دقیقه</td><td><?=format_number((int)$row['normal_overtime_minutes']+(int)$row['holiday_overtime_minutes'])?> دقیقه<small><?=e(HrAttendanceRepository::OVERTIME_STATUSES[$row['overtime_status']]??$row['overtime_status'])?></small></td><td><?=e($details?:'—')?><?php if(!empty($row['import_time_notes'])):?><small><?=e($row['import_time_notes'])?></small><?php endif?></td></tr>
        <?php endforeach?><?php if(!$rows):?><tr><td colspan="7"><div class="attendance-empty"><strong>رکوردی برای این دوره ثبت نشده است.</strong><span>با تغییر دوره، بازه دیگری را بررسی کنید.</span></div></td></tr><?php endif?>
        </tbody></table></div>
    </section>
</div>
<?php require __DIR__.'/../views/partials/admin-footer.php';?>
