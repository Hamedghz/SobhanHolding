$ErrorActionPreference='Stop'
$root=Split-Path -Parent $PSScriptRoot
$files=@(
  'lib/NotificationService.php','lib/PushNotificationService.php','services/NotificationHubService.php',
  'core/WindowsNotificationHubModule.php','api/notify/_bootstrap.php','api/notify/pair-device.php',
  'api/notify/register-device.php','api/notify/client-config.php','api/notify/pending.php','api/notify/ack.php',
  'api/notify/action.php','api/notify/unregister-device.php','api/notify/app-version.php','public/sw.js',
  'service-worker.js','admin/notification-test.php','admin/notification-settings.php'
)
foreach($file in $files){if(-not(Test-Path(Join-Path $root $file))){throw "Missing: $file"}}

$service=Get-Content(Join-Path $root 'lib/NotificationService.php')-Raw
$hub=Get-Content(Join-Path $root 'services/NotificationHubService.php')-Raw
$module=Get-Content(Join-Path $root 'core/WindowsNotificationHubModule.php')-Raw
$schema=Get-Content(Join-Path $root 'database/schema.sql')-Raw
$bootstrap=Get-Content(Join-Path $root 'api/notify/_bootstrap.php')-Raw

foreach($event in @('ticket_created','ticket_reply','ticket_assigned','ticket_status_changed','messenger_message','personal_planner_reminder','meeting_followup_assigned','decision_overdue','assessment_test_assigned','kpi_score_submitted','payroll_slip_published')){if($service-notmatch[regex]::Escape("'$event'")){throw "Missing event: $event"}}
foreach($helper in @('notifyTicketCreated','notifyTicketReply','notifyTicketAssigned','notifyTicketStatusChanged','notifyMessengerMessage','notifyPlannerReminder','notifyMeetingFollowupAssigned','notifyDecisionOverdue','notifyTestAssigned','notifyKpiScoreSubmitted','notifyPayrollSlipPublished')){if($service-notmatch"function $helper"){throw "Missing helper: $helper"}}

foreach($table in @('sobhan_notifications','sobhan_notification_devices','sobhan_notification_delivery_logs','sobhan_user_notification_module_settings','sobhan_notification_pairing_codes','sobhan_notification_pairing_attempts')){if($schema-notmatch"CREATE TABLE IF NOT EXISTS $table"){throw "Missing schema table: $table"}}
foreach($token in @('x-device-uid','x-device-token','x-app-version','token_hash',"hash('sha256',`$token)",'action_not_supported','action_not_allowed','sobhan_notification_delivery_logs','action_failed')){if(($hub+$module)-notmatch[regex]::Escape($token)){throw "Missing hub security contract: $token"}}
if($hub-notmatch"public const MODULES=.*'planner'" -or (Get-Content(Join-Path $root 'admin/notification-settings.php')-Raw)-notmatch"'planner'=>'برنامه‌ریز"){throw 'Planner module settings are missing.'}

$calls=@{
  'services/TicketService.php'='notifyTicketCreated';
  'workers/messenger_worker.php'='notifyMessengerMessage';
  'services/PersonalPlannerService.php'='notifyPlannerReminder';
  'lib/ManagementMeetingsRepository.php'='notifyMeetingFollowupAssigned';
  'admin/employee-assessments.php'='notifyTestAssigned';
  'admin/hr-kpi-scores.php'='notifyKpiScoreSubmitted';
  'admin/payroll-periods.php'='notifyPayrollSlipPublished'
}
foreach($entry in $calls.GetEnumerator()){if((Get-Content(Join-Path $root $entry.Key)-Raw)-notmatch[regex]::Escape($entry.Value)){throw "Missing notification call: $($entry.Key) -> $($entry.Value)"}}

$direct=Get-ChildItem $root -Recurse -Filter *.php | Where-Object{$_.FullName-notmatch'[\\/](bin|obj|vendor)[\\/]' -and $_.FullName-ne(Join-Path $root 'lib/NotificationService.php')} | Select-String -Pattern 'INSERT\s+(?:IGNORE\s+)?INTO\s+sobhan_notifications'
if($direct){throw "Direct sobhan_notifications insert outside NotificationService: $($direct.Path -join ', ')"}

foreach($code in @('action_not_allowed','action_not_supported','validation_error','request_failed')){if($bootstrap-notmatch[regex]::Escape($code)){throw "Missing controlled API error: $code"}}
$sw=Get-Content(Join-Path $root 'public/sw.js')-Raw
if($sw-notmatch'target.origin === self.location.origin' -or $sw-notmatch'clients.openWindow'){throw 'Service worker same-origin click guard is missing.'}
$testPage=Get-Content(Join-Path $root 'admin/notification-test.php')-Raw
if($testPage-notmatch'Auth::verifyCsrf' -or $testPage-notmatch'NotificationService::create'){throw 'Notification test page contract is incomplete.'}

$scope=$service+$hub+$module+$bootstrap
if($scope-match'(?i)\b(DROP|TRUNCATE|RENAME\s+TABLE)\b'){throw 'Destructive SQL token found in notification scope.'}
Write-Output "Enterprise notification flow checks passed ($($files.Count) files)."
