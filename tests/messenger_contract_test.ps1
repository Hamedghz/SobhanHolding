$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
$required = @(
  'core/MessengerModule.php','install/messenger_seed.php','employee/messenger.php','employee/message-inbox.php',
  'api/messenger/health.php','api/messenger/conversations.php','api/messenger/message-send.php',
  'api/messenger/file-upload.php','api/messenger/file-download.php','api/messenger/live-location-start.php',
  'api/messenger/windows/register-device.php','api/messenger/admin/broadcast-send.php',
  'realtime-server/server.js','workers/messenger_worker.php','desktop-agent/windows-agent-api.md'
)
foreach ($file in $required) { if (-not (Test-Path (Join-Path $root $file))) { throw "Missing: $file" } }
$module = Get-Content (Join-Path $root 'core/MessengerModule.php') -Raw
foreach ($table in @('chat_settings','chat_conversations','chat_participants','chat_messages','chat_message_status','chat_files','chat_reactions','chat_mentions','chat_notifications','chat_push_subscriptions','chat_windows_devices','chat_live_locations','chat_audit_logs','chat_reports')) { if ($module -notmatch "CREATE TABLE IF NOT EXISTS $table") { throw "Missing table: $table" } }
$scope = Get-Content (Join-Path $root 'lib/messenger/MessengerService.php') -Raw
if ($scope -match '(?i)\bDROP\b|\bTRUNCATE\b') { throw 'Destructive SQL found.' }
$fileService = Get-Content (Join-Path $root 'lib/messenger/MessengerFileService.php') -Raw
foreach ($token in @('is_uploaded_file','finfo','realpath','hash_equals','MessengerSecurity::participant')) { if ($fileService -notmatch [regex]::Escape($token)) { throw "File security contract missing: $token" } }
$api = Get-Content (Join-Path $root 'api/messenger/bootstrap.php') -Raw
foreach ($token in @('Auth::user','MessengerSecurity::csrf','JSON_UNESCAPED_UNICODE','error')) { if ($api -notmatch [regex]::Escape($token)) { throw "API contract missing: $token" } }
Write-Output "Sobhan Messenger contract checks passed ($($required.Count) required files)."
