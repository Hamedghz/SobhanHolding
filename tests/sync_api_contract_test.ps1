$ErrorActionPreference='Stop';$root=Split-Path -Parent $PSScriptRoot
function Read([string]$path){Get-Content -Raw (Join-Path $root $path)}
foreach($file in @('_bootstrap.php','health.php','pending.php','record.php','ack.php','error.php')){if(!(Test-Path (Join-Path $root "api/sync/$file"))){throw "Missing sync endpoint: $file"}}
$bootstrap=Read 'api/sync/_bootstrap.php';foreach($token in @('HTTP_X_API_KEY','hash_equals','UNAUTHORIZED','IP_NOT_ALLOWED','json_error')){if(!$bootstrap.Contains($token)){throw "Missing sync security contract: $token"}}
$service=Read 'core/SyncQueueService.php';foreach($token in @('enqueueOnce','markSynced','markError','getPending','ENTITY_MAP','password')){if(!$service.Contains($token)){throw "Missing queue contract: $token"}}
if($service -match '\$_(GET|POST).*table|SELECT \* FROM \$'){throw 'Potential arbitrary table access found.'}
foreach($file in Get-ChildItem (Join-Path $root 'api/sync') -Filter '*.php'){$source=Get-Content -Raw $file.FullName;if($source -match '\b(DROP|TRUNCATE|RENAME\s+TABLE)\b'){throw "Destructive SQL in $($file.Name)"}}
if((Read 'api/sync/record.php') -notmatch 'INVALID_ENTITY_ID|invalid_entity_id'){throw 'Invalid entity id contract missing.'}
if((Read 'docs/sync-erp-ai.md') -notmatch 'هرگز به LAN|هرگز به شبکه داخلی'){throw 'Pull-only architecture is not documented.'}
Write-Host 'Sync API contract: PASS'
