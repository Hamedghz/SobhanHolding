. "$PSScriptRoot\common.ps1"

$root = Get-SobhanRepoRoot
Set-Location $root

Write-Host "Sobhan project setup check" -ForegroundColor Cyan
Write-Host "Repository root: $root"

$runtimeDirectories = @(
    ".codex-runtime",
    ".codex-runtime\tmp",
    ".codex-runtime\logs",
    ".codex-runtime\backups"
)

foreach ($dir in $runtimeDirectories) {
    if (-not (Test-Path $dir)) {
        New-Item -ItemType Directory -Path $dir -Force | Out-Null
        Write-Host "Created: $dir"
    }
}

$git = Find-Executable -Name "git"
Write-Check "Git" ([bool]$git) $(if ($git) { & $git --version } else { "Not found" })

$php = Get-PhpExecutable
Write-Check "PHP" ([bool]$php) $(if ($php) { (& $php -v | Select-Object -First 1) } else { "PHP 8.1+ is required for local linting" })

$mysql = Get-MySqlExecutable
Write-Check "MySQL client" ([bool]$mysql) $(if ($mysql) { (& $mysql --version) } else { "Optional for local DB diagnostics" })

$importantPaths = @(
    "admin",
    "AGENTS.md",
    ".codex\CONTEXT.md",
    ".agents\skills"
)

foreach ($path in $importantPaths) {
    Write-Check "Path $path" (Test-Path $path) $(if (Test-Path $path) { "Found" } else { "Not found; verify repository structure" })
}

Write-Host ""
Write-Host "Setup check completed. No dependency was installed and no application data was changed." -ForegroundColor Green
