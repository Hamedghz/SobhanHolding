<?php

$root=dirname(__DIR__);
$read=static fn(string $path):string=>(string)file_get_contents($root.'/'.$path);
$service=$read('services/WorkPlannerService.php');
$module=$read('core/WorkPlannerModule.php');
$dashboard=$read('admin/index.php');
$widget=$read('views/partials/work-planner-widget.php');
$endpoint=$read('admin/ajax/work-planner.php');
$script=$read('assets/js/dashboard-planner.js');
$page=$read('employee/work-planner-simple.php');
$schema=$read('database/schema.sql');

foreach(['started_at','paused_at','client_request_key','uq_work_planner_client_request'] as $token){
    if(!str_contains($service.$module.$schema,$token))throw new RuntimeException('Missing planner persistence token: '.$token);
}
foreach(['beginTransaction','commit()','rollBack()','createPersonalTask'] as $token){
    if(!str_contains($service,$token))throw new RuntimeException('Transaction-safe insertion contract is missing: '.$token);
}
foreach(['matchingTemplatesForUser','تولید خودکار پس از تنظیم نقش یا واحد سازمانی فعال می‌شود.','قالب نقش‌محور متناسب با حساب شما یافت نشد'] as $token){
    if(!str_contains($service.$page,$token))throw new RuntimeException('Planner first-use contract is missing: '.$token);
}
if(!str_contains($page,'if($hasMatchingTemplates)'))throw new RuntimeException('Role task generation must stay unavailable without a matching template.');
if(!str_contains($service,'started_at=IF(?="in_progress",COALESCE(started_at,NOW()),started_at)'))throw new RuntimeException('Start timestamp behavior is missing.');
if(!str_contains($service,'paused_at=IF(?="todo" AND status="in_progress",NOW(),paused_at)'))throw new RuntimeException('Pause timestamp behavior is missing.');
if(!str_contains($service,'t.due_date<=CURDATE() OR t.status="in_progress"'))throw new RuntimeException('Started tasks may disappear from the current daily widget.');
foreach(['Auth::verifyCsrf','application/json','JSON_UNESCAPED_UNICODE','createPersonalTask'] as $token){
    if(!str_contains($endpoint,$token))throw new RuntimeException('AJAX endpoint contract is missing: '.$token);
}
foreach(['data-dashboard-planner','data-planner-ajax','data-planner-inline-error','planner_quick_add','مکث','همه وظایف','مشاهده اقدام مرتبط','مرتبط با OKR'] as $token){
    if(!str_contains($widget,$token))throw new RuntimeException('Dashboard daily workflow token is missing: '.$token);
}
foreach(['event.preventDefault','dataset.submitting','client_request_key','response.json','location.reload'] as $token){
    if(!str_contains($script,$token))throw new RuntimeException('Dashboard submission guard is missing: '.$token);
}
if(!str_contains($dashboard,'dashboard-planner.js'))throw new RuntimeException('Dashboard planner script is not loaded.');
foreach(['دیروز','امروز','فردا','jalali-date-input','شروع','مکث','انجام شد','انتقال به فردا','planner-daily-quick-add'] as $token){
    if(!str_contains($page,$token))throw new RuntimeException('Daily planner UI token is missing: '.$token);
}
foreach([$service,$module,$endpoint,$schema] as $scope){
    if(preg_match('/\b(?:DROP|TRUNCATE)\b/i',$scope))throw new RuntimeException('Destructive SQL token found.');
}

echo "Work planner phase 14 contract: PASS\n";
