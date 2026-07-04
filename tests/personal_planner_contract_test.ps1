$ErrorActionPreference='Stop';$root=Split-Path -Parent $PSScriptRoot
$required=@('core/PersonalPlannerModule.php','services/PersonalPlannerService.php','install/personal_planner_seed.php','admin/ajax/personal-planner.php','admin/personal-planner.php','admin/personal-planner-report.php','admin/personal-planner-settings.php','admin/includes/personal-planner-widget.php','admin/cron/personal_planner.php','assets/js/personal-planner.js','assets/css/personal-planner.css')
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
if($employeeDashboard-notmatch"redirect\('/admin/index.php'\)"){throw 'Legacy employee dashboard must redirect to the main dashboard.'}
$mainDashboard=Get-Content(Join-Path $root 'admin/index.php')-Raw
if($mainDashboard-notmatch'work-planner-widget.php'){throw 'Planner must render at the top of the main dashboard.'}
foreach($specialized in @('admin/ceo-dashboard.php','admin/manager-dashboard.php')){if(Test-Path(Join-Path $root $specialized)){if((Get-Content(Join-Path $root $specialized)-Raw)-match'personal-planner-widget.php'){throw "Planner must not render in specialized dashboard: $specialized"}}}
$workPlanner=Get-Content(Join-Path $root 'employee/work-planner-simple.php')-Raw
foreach($token in @('daily','weekly','monthly','list','moveToTomorrow','recurrence_type')){if($workPlanner-notmatch[regex]::Escape($token)){throw "Work planner behavior missing: $token"}}
if($workPlanner-match'employee_panel_enabled'){throw 'Planner must not depend on employee_panel_enabled.'}
Write-Output "Personal Planner contract checks passed ($($required.Count) files)."
