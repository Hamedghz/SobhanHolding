. "$PSScriptRoot\common.ps1"

$root = Get-SobhanRepoRoot
Set-Location $root

Write-Host "Sobhan project doctor" -ForegroundColor Cyan
Write-Host "Repository root: $root"

$git = Find-Executable -Name "git"
$php = Get-PhpExecutable
$mysql = Get-MySqlExecutable

if ($git) {
    $gitVersion = ((& $git --version 2>&1) | Out-String).Trim()
}
else {
    $gitVersion = "Not found"
}
Write-Check "Git" ([bool]$git) $gitVersion

if ($php) {
    $phpVersion = ((& $php -v 2>&1 | Select-Object -First 1) | Out-String).Trim()
}
else {
    $phpVersion = "Not found. Set SOBHAN_PHP_PATH or install PHP 8.1+."
}
Write-Check "PHP" ([bool]$php) $phpVersion

if ($mysql) {
    $mysqlVersion = ((& $mysql --version 2>&1) | Out-String).Trim()
}
else {
    $mysqlVersion = "Not found; optional for local DB diagnostics."
}
Write-Check "MySQL client" ([bool]$mysql) $mysqlVersion

if ($git) {
    $branch = ((& $git branch --show-current 2>$null) | Out-String).Trim()

    if ([string]::IsNullOrWhiteSpace($branch)) {
        $branchOk = $false
        $branchDetail = "Detached or unknown"
    }
    else {
        $branchOk = $true
        $branchDetail = $branch
    }

    Write-Check "Current branch" $branchOk $branchDetail

    $status = @(& $git status --short 2>$null)
    $clean = ($status.Count -eq 0)

    if ($clean) {
        $workingTreeDetail = "Clean"
    }
    else {
        $workingTreeDetail = "$($status.Count) changed/untracked item(s)"
    }

    Write-Check "Working tree" $clean $workingTreeDetail
}

$requiredPaths = @(
    "admin",
    ".codex",
    ".agents\skills",
    "AGENTS.md"
)

foreach ($path in $requiredPaths) {
    $exists = Test-Path $path
    Write-Check "Required path" $exists $path
}

if ($git) {
    $sensitivePatterns = @(
        ".env",
        ".env.*",
        "*.bak",
        "*.dump",
        "*.sql.gz",
        "*.pem",
        "*.key",
        "config.production.php"
    )

    foreach ($pattern in $sensitivePatterns) {
        $tracked = @(& $git ls-files -- $pattern 2>$null)

        if ($tracked.Count -gt 0) {
            $preview = (@($tracked | Select-Object -First 5) -join ", ")
            Write-Check "Sensitive tracked files [$pattern]" $false $preview
        }
    }
}

if ($php) {
    $entryFiles = @(
        "admin\index.php",
        "install.php",
        "index.php"
    ) | Where-Object { Test-Path $_ }

    foreach ($file in @($entryFiles)) {
        $result = ((& $php -l $file 2>&1) | Out-String).Trim()
        $lintOk = ($LASTEXITCODE -eq 0)
        Write-Check "PHP lint" $lintOk "$file - $result"
    }
}
else {
    Write-Host ""
    Write-Host "PHP lint was skipped because php.exe is unavailable." -ForegroundColor Yellow
    Write-Host 'For this PowerShell session:' -ForegroundColor Cyan
    Write-Host '$env:SOBHAN_PHP_PATH = "C:\php\php.exe"'
    Write-Host 'Permanent user variable:' -ForegroundColor Cyan
    Write-Host '[Environment]::SetEnvironmentVariable("SOBHAN_PHP_PATH", "C:\php\php.exe", "User")'
}

Write-Host ""
Write-Host "Doctor completed. No application or database change was made." -ForegroundColor Green
