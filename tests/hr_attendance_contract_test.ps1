$ErrorActionPreference='Stop'
$root=Split-Path -Parent $PSScriptRoot
$files=@(
  'core/HrAttendanceModule.php','lib/HrAttendanceRepository.php','install/hr_attendance_seed.php',
  'admin/hr-attendance.php','admin/hr-attendance-settings.php','admin/hr-holidays.php',
  'admin/hr-attendance-reports.php','admin/includes/hr-attendance-nav.php','assets/css/hr-attendance.css'
)
foreach($file in $files){if(-not(Test-Path(Join-Path $root $file))){throw "Missing: $file"}}
$module=Get-Content(Join-Path $root 'core/HrAttendanceModule.php')-Raw
foreach($table in @('hr_work_groups','hr_attendance_settings','hr_month_holidays','hr_attendance_entries','hr_attendance_logs')){if($module-notmatch"CREATE TABLE IF NOT EXISTS $table"){throw "Missing table: $table"}}
foreach($token in @('uq_hr_attendance_employee_date','uq_hr_holiday_date_group','vw_hr_attendance_monthly_summary','attendance_score_suggestion','INSERT IGNORE INTO hr_work_groups')){if($module-notmatch[regex]::Escape($token)){throw "Missing schema contract: $token"}}
$repo=Get-Content(Join-Path $root 'lib/HrAttendanceRepository.php')-Raw
foreach($method in @('saveSettings','saveHoliday','saveBatch','approveOvertime','report','reportStats','calculate','holidayForDate','settingForDate')){if($repo-notmatch"function $method"){throw "Missing repository method: $method"}}
foreach($formula in @('late_tolerance_minutes','early_leave_tolerance_minutes','normal_overtime_minutes','holiday_overtime_minutes','require_overtime_approval','OrgAccess::accessibleUserIds')){if($repo-notmatch[regex]::Escape($formula)){throw "Missing calculation/access token: $formula"}}
foreach($page in @('admin/hr-attendance.php','admin/hr-attendance-settings.php','admin/hr-holidays.php')){if((Get-Content(Join-Path $root $page)-Raw)-notmatch'Auth::verifyCsrf'){throw "Missing CSRF: $page"}}
$report=Get-Content(Join-Path $root 'admin/hr-attendance-reports.php')-Raw
if($report-notmatch'\\xEF\\xBB\\xBF' -or $report-notmatch'window.print'){throw 'CSV BOM or print-friendly output is missing.'}
$scope=$module+$repo
if($scope-match'(?i)\b(DROP|TRUNCATE)\b'){throw 'Destructive SQL token found.'}
$menu=Get-Content(Join-Path $root 'lib/admin_menu.php')-Raw
foreach($route in @('/admin/hr-attendance.php','/admin/hr-attendance-settings.php','/admin/hr-holidays.php','/admin/hr-attendance-reports.php')){if($menu-notmatch[regex]::Escape($route)){throw "Missing menu route: $route"}}
Write-Output "HR attendance contract checks passed ($($files.Count) files)."
