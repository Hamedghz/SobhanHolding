param(
    [string]$OutputDirectory = ".codex-runtime\backups"
)

. "$PSScriptRoot\common.ps1"

$root = Get-SobhanRepoRoot
Set-Location $root

if (-not (Test-Path $OutputDirectory)) {
    New-Item -ItemType Directory -Path $OutputDirectory -Force | Out-Null
}

$timestamp = Get-Date -Format "yyyyMMdd-HHmmss"
$repoName = Split-Path $root -Leaf
$destination = Join-Path $OutputDirectory "$repoName-source-$timestamp.zip"

$exclude = @(
    "\.git\",
    "\node_modules\",
    "\vendor\",
    "\uploads\",
    "\storage\backups\",
    "\.codex-runtime\",
    "\tmp\",
    "\logs\"
)

$filesToArchive = Get-ChildItem -Path $root -Recurse -File |
    Where-Object {
        $full = $_.FullName
        -not ($exclude | Where-Object { $full -like "*$_*" }) -and
        $_.Name -ne ".env" -and
        $_.Extension -notin @(".bak", ".dump")
    }

if ($filesToArchive.Count -eq 0) {
    throw "No files found for backup."
}

$staging = Join-Path $env:TEMP "sobhan-backup-$timestamp"
New-Item -ItemType Directory -Path $staging -Force | Out-Null

try {
    foreach ($file in $filesToArchive) {
        $relative = $file.FullName.Substring($root.Length).TrimStart("\")
        $target = Join-Path $staging $relative
        $targetDir = Split-Path $target -Parent
        if (-not (Test-Path $targetDir)) {
            New-Item -ItemType Directory -Path $targetDir -Force | Out-Null
        }
        Copy-Item $file.FullName $target -Force
    }

    Compress-Archive -Path (Join-Path $staging "*") -DestinationPath $destination -Force
}
finally {
    Remove-Item $staging -Recurse -Force -ErrorAction SilentlyContinue
}

Write-Host "Source backup created: $destination" -ForegroundColor Green
Write-Host "Database and user uploads are intentionally not included." -ForegroundColor Yellow
