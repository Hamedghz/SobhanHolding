$ErrorActionPreference='Stop';$root=Split-Path -Parent $PSScriptRoot
$files=@('core/ManagementMeetingsModule.php','lib/ManagementMeetingsRepository.php','install/management_meetings_seed.php','admin/management-meetings.php','admin/management-meeting-view.php','admin/management-decisions.php','admin/management-decision-view.php','admin/management-decision-edit.php','admin/management-rules.php','assets/css/management-governance.css')
foreach($file in $files){if(-not(Test-Path(Join-Path $root $file))){throw "Missing: $file"}}
$module=Get-Content(Join-Path $root 'core/ManagementMeetingsModule.php')-Raw
foreach($table in @('management_meetings','management_decisions','management_decision_followups','management_rule_versions')){if($module-notmatch"CREATE TABLE IF NOT EXISTS $table"){throw "Missing table: $table"}}
$repo=Get-Content(Join-Path $root 'lib/ManagementMeetingsRepository.php')-Raw
foreach($method in @('getMeetings','createMeeting','finalizeMeeting','getDecisions','updateDecisionStatus','verifyDecision','convertDecisionToRule','getActiveRules','archiveRule')){if($repo-notmatch"function $method"){throw "Missing method: $method"}}
foreach($guard in @('OrgAccess::accessibleUserIds','responsible_user_id','supervisor_user_id','canAssignScope','assertFollowup','canVerify')){if($repo-notmatch[regex]::Escape($guard)){throw "Missing access guard: $guard"}}
foreach($guard in @('canFollowupDecision','canVerifyDecision',"required(`$r['rule_code']")){if($repo-notmatch[regex]::Escape($guard)){throw "Missing hardened guard: $guard"}}
$scope=$module+$repo+(Get-Content(Join-Path $root 'admin/management-meetings.php')-Raw)+(Get-Content(Join-Path $root 'admin/management-decision-view.php')-Raw)
if($scope-match'(?i)\b(DROP|TRUNCATE|RENAME)\b'){throw 'Destructive SQL token found.'}
foreach($page in @('admin/management-meetings.php','admin/management-meeting-view.php','admin/management-decision-view.php','admin/management-rules.php')){if((Get-Content(Join-Path $root $page)-Raw)-notmatch'Auth::verifyCsrf'){throw "Missing CSRF: $page"}}
$decisions=Get-Content(Join-Path $root 'admin/management-decisions.php')-Raw
if($decisions-notmatch'\\xEF\\xBB\\xBF' -or $decisions-notmatch'managementAnalytics'){throw 'CSV BOM or analytics is missing.'}
$edit=Get-Content(Join-Path $root 'admin/management-decision-edit.php')-Raw
if($edit-notmatch'canEditDecision'){throw 'Decision edit GET guard is missing.'}
Write-Output "Management governance contract checks passed ($($files.Count) files)."
