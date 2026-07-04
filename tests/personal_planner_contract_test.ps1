$ErrorActionPreference='Stop';$root=Split-Path -Parent $PSScriptRoot
$required=@('core/PersonalPlannerModule.php','core/WorkPlannerModule.php','services/PersonalPlannerService.php','services/WorkPlannerService.php','install/personal_planner_seed.php','admin/ajax/personal-planner.php','admin/personal-planner.php','admin/personal-planner-report.php','admin/personal-planner-settings.php','admin/includes/personal-planner-widget.php','admin/cron/personal_planner.php','workers/planner_reminder_worker.php','assets/js/personal-planner.js','assets/css/personal-planner.css')
foreach($file in $required){if(-not(Test-Path(Join-Path $root $file))){throw "Missing: $file"}}
$module=Get-Content(Join-Path $root 'core/PersonalPlannerModule.php')-Raw
foreach($table in @('personal_planner_tasks','personal_planner_notes','personal_planner_checks','personal_planner_notifications','personal_planner_logs','personal_planner_settings')){if($module-notmatch"CREATE TABLE IF NOT EXISTS $table"){throw "Missing table: $table"}}
$ajax=Get-Content(Join-Path $root 'admin/ajax/personal-planner.php')-Raw
foreach($action in @('load_day','load_week','load_month','add_task','toggle_task','move_task_to_tomorrow','move_unfinished_to_tomorrow','create_recurring_next','load_reminders','mark_notification_sent','get_report_summary')){if($ajax-notmatch"'$action'"){throw "Missing action: $action"}}
$service=Get-Content(Join-Path $root 'services/PersonalPlannerService.php')-Raw
foreach($token in @('WHERE id=? AND user_id=?','deleted_at=NOW()','createNextRecurringTask','logAction','generateDueNotifications')){if($service-notmatch[regex]::Escape($token)){throw "Security/service contract missing: $token"}}
$scope=($module+$service+$ajax)
if($scope-match'(?i)\b(DROP|TRUNCATE|RENAME)\b'){throw 'Destructive SQL token found.'}
$employeeDashboard=Get-Content(Join-Path $root 'admin/employee-dashboard.php')-Raw
if($employeeDashboard-notmatch'work-planner-widget.php'){throw 'Work planner widget is missing from the personal dashboard.'}
$mainDashboard=Get-Content(Join-Path $root 'admin/index.php')-Raw
if($mainDashboard-match'(personal|work)-planner-widget.php'){throw 'Planner must not render in the shared management dashboard.'}
foreach($specialized in @('admin/ceo-dashboard.php','admin/manager-dashboard.php')){if(Test-Path(Join-Path $root $specialized)){if((Get-Content(Join-Path $root $specialized)-Raw)-match'(personal|work)-planner-widget.php'){throw "Planner must not render in specialized dashboard: $specialized"}}}
$workPlanner=Get-Content(Join-Path $root 'employee/work-planner-simple.php')-Raw
foreach($token in @('daily','weekly','monthly','list','moveToTomorrow','moveUnfinishedToTomorrow','toggleImportant','reportSummary','planner-quick-add','recurrence_type')){if($workPlanner-notmatch[regex]::Escape($token)){throw "Work planner behavior missing: $token"}}
if($workPlanner-notmatch[regex]::Escape('WHERE id=? AND user_id=?')){throw 'Personal planner actions must enforce session ownership.'}
if($workPlanner-match'employee_panel_enabled'){throw 'Planner must not depend on employee_panel_enabled.'}
$workService=Get-Content(Join-Path $root 'services/WorkPlannerService.php')-Raw
$workModule=Get-Content(Join-Path $root 'core/WorkPlannerModule.php')-Raw
foreach($token in @('notifyWorkPlannerReminder','reminder_sent_at','GET_LOCK','RELEASE_LOCK','parent_task_id','completion_percent')){if(($workService+$workModule)-notmatch[regex]::Escape($token)){throw "Planner reminder/recurrence contract missing: $token"}}
Write-Output "Personal Planner contract checks passed ($($required.Count) files)."
