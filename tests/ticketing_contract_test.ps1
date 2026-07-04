$ErrorActionPreference='Stop';$root=Split-Path -Parent $PSScriptRoot
$required=@('core/TicketingModule.php','services/TicketService.php','install/ticketing_seed.php','employee/tickets.php','employee/ticket-create.php','employee/ticket-view.php','employee/ticket-attachment.php','admin/tickets.php','admin/ticket-categories.php','admin/ticket-settings.php')
foreach($file in $required){if(-not(Test-Path(Join-Path $root $file))){throw "Missing: $file"}}
$module=Get-Content(Join-Path $root 'core/TicketingModule.php')-Raw
foreach($table in @('tickets','ticket_categories','ticket_messages','ticket_attachments','ticket_status_logs','ticket_assignments','ticket_sla_rules')){if($module-notmatch"CREATE TABLE IF NOT EXISTS $table"){throw "Missing table: $table"}}
$scope=$module+(Get-Content(Join-Path $root 'services/TicketService.php')-Raw)
if($scope-match'(?i)\b(DROP|TRUNCATE|RENAME)\b'){throw 'Destructive SQL token found.'}
foreach($status in @('open','assigned','in_progress','waiting_user','waiting_admin','resolved','closed','cancelled')){if($scope-notmatch[regex]::Escape($status)){throw "Missing status: $status"}}
if($scope-match'messenger_messages|chat_messages'){throw 'Ticket storage must not use messenger message tables.'}
if($scope-notmatch'NotificationService'){throw 'Ticket notifications are missing.'}
$attachmentScope=$scope+(Get-Content(Join-Path $root 'employee/ticket-attachment.php')-Raw)
foreach($token in @('notifyTicketCreated','notifyTicketReply','self::find((int)$row[''ticket_id''])','realpath','X-Content-Type-Options')){if($attachmentScope-notmatch[regex]::Escape($token)){throw "Ticket security contract missing: $token"}}
$view=Get-Content(Join-Path $root 'employee/ticket-view.php')-Raw
if($view-notmatch'/employee/ticket-attachment.php'){throw 'Ticket attachments bypass the scoped download endpoint.'}
$inbox=Get-Content(Join-Path $root 'employee/message-inbox.php')-Raw
if($inbox-match'TicketService|ticket_messages'){throw 'Ticketing leaked into the messenger inbox.'}
Write-Output "Ticketing contract checks passed ($($required.Count) files)."
