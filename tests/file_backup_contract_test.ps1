$ErrorActionPreference='Stop';$root=Split-Path -Parent $PSScriptRoot
function Read([string]$path){Get-Content -Raw (Join-Path $root $path)}
foreach($file in @('_bootstrap.php','health.php','pending.php','metadata.php','download.php','ack.php','error.php')){if(!(Test-Path (Join-Path $root "api/file-backup/$file"))){throw "Missing backup endpoint: $file"}}
$bootstrap=Read 'api/file-backup/_bootstrap.php';foreach($token in @('HTTP_X_API_KEY','HTTP_X_BACKUP_API_KEY','hash_equals','UNAUTHORIZED','backup_disabled')){if(!$bootstrap.Contains($token)){throw "Missing backup security contract: $token"}}
$service=Read 'lib/FileBackupService.php';foreach($token in @('normalizeRelativePath','resolveExistingFile','is_link','metadata','file_backup_max_attempts')){if(!$service.Contains($token)){throw "Missing backup safety contract: $token"}}
$download=Read 'api/file-backup/download.php';if($download -match '\$_GET\[["'']path'){throw 'Download accepts an arbitrary path.'};if(!$download.Contains('resolveExistingFile')){throw 'Download does not resolve the registered file server-side.'}
$pending=Read 'api/file-backup/pending.php';if($pending.Contains('relative_path')){throw 'Pending endpoint exposes relative path directly.'}
if((Read 'docs/file-backup.md') -notmatch 'حذف فایل از سایت باعث حذف نسخه پشتیبان داخلی نمی‌شود'){throw 'Retention policy is not documented.'}
Write-Host 'File backup contract: PASS'
