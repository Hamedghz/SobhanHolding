$ErrorActionPreference = 'Stop'

$root = Split-Path -Parent $PSScriptRoot
$removedPaths = @(
  'employee/messenger.php',
  'employee/message-inbox.php',
  'admin/messenger-dashboard.php',
  'admin/messenger-groups.php',
  'admin/messenger-channels.php',
  'admin/messenger-broadcast.php',
  'admin/messenger-reports.php',
  'admin/messenger-audit.php',
  'admin/messenger-settings.php',
  'admin/messenger.php',
  'admin/includes/messenger-admin-page.php',
  'core/MessengerModule.php',
  'services/MessengerForwardService.php',
  'services/SalesReportShareService.php',
  'admin/sales-manager-forward.php',
  'assets/js/sales-report-forward.js',
  'assets/css/sales-report-forward.css',
  'install/messenger_seed.php',
  'workers/messenger_worker.php',
  'docs/sobhan-messenger.md'
)

foreach ($path in $removedPaths) {
  if (Test-Path (Join-Path $root $path)) {
    throw "Path should be removed: $path"
  }
}

$database = Get-Content (Join-Path $root 'core/Database.php') -Raw
if ($database -match 'MessengerModule') { throw 'Database::repair still references MessengerModule.' }

$menu = Get-Content (Join-Path $root 'lib/admin_menu.php') -Raw
if ($menu -match 'messenger') { throw 'Admin menu still references messenger routes.' }

$notification = Get-Content (Join-Path $root 'lib/NotificationService.php') -Raw
if ($notification -match 'messenger_message|messenger_official_notice|notifyForwardedReport|/messenger/') { throw 'NotificationService still contains messenger-specific hooks.' }

$hub = Get-Content (Join-Path $root 'services/NotificationHubService.php') -Raw
if ($hub -match 'messenger_group|messenger_channel|sobhan_messenger_quick_reply') { throw 'Notification hub still exposes messenger-only features.' }

$dashboard = Get-Content (Join-Path $root 'admin/manager-dashboard.php') -Raw
if ($dashboard -match 'sales-report-forward|data-forward-report|MessengerForwardService|ارسال به پیام‌رسان') { throw 'Manager dashboard still exposes messenger forwarding UI.' }

$users = Get-Content (Join-Path $root 'admin/users.php') -Raw
if ($users -match 'messenger\.view|manager_dashboard\.forward') { throw 'User permission catalog still lists messenger modules.' }

$schema = Get-Content (Join-Path $root 'database/schema.sql') -Raw
if ($schema -match 'CREATE TABLE IF NOT EXISTS messenger_|CREATE TABLE IF NOT EXISTS sales_report_shares') { throw 'Fresh schema still creates messenger tables.' }

foreach ($path in @('core/TicketingModule.php','core/LetterModule.php','core/EmailHubModule.php','admin/tickets.php','admin/letters.php','admin/email-accounts.php')) {
  if (-not (Test-Path (Join-Path $root $path))) {
    throw "Required module missing: $path"
  }
}

Write-Output 'Messenger removal contract checks passed.'
