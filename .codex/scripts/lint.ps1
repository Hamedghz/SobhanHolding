param(
    [switch]$All
)

. "$PSScriptRoot\common.ps1"

$root = Get-SobhanRepoRoot
Set-Location $root

$php = Get-PhpExecutable
if (-not $php) {
    throw @"
PHP was not found.

Set it for the current PowerShell session:
`$env:SOBHAN_PHP_PATH = "C:\php\php.exe"

Or save it permanently:
[Environment]::SetEnvironmentVariable("SOBHAN_PHP_PATH", "C:\php\php.exe", "User")
"@
}

function Invoke-NativeCapture {
    param(
        [Parameter(Mandatory = $true)]
        [string]$FilePath,

        [Parameter(Mandatory = $true)]
        [string[]]$ArgumentList
    )

    $startInfo = New-Object System.Diagnostics.ProcessStartInfo
    $startInfo.FileName = $FilePath
    $startInfo.UseShellExecute = $false
    $startInfo.RedirectStandardOutput = $true
    $startInfo.RedirectStandardError = $true
    $startInfo.CreateNoWindow = $true

    foreach ($argument in $ArgumentList) {
        [void] $startInfo.ArgumentList.Add($argument)
    }

    $process = New-Object System.Diagnostics.Process
    $process.StartInfo = $startInfo

    [void] $process.Start()

    $stdout = $process.StandardOutput.ReadToEnd()
    $stderr = $process.StandardError.ReadToEnd()

    $process.WaitForExit()

    return [PSCustomObject]@{
        ExitCode = $process.ExitCode
        StdOut = $stdout.Trim()
        StdErr = $stderr.Trim()
    }
}

function Invoke-GitLines {
    param(
        [Parameter(Mandatory = $true)]
        [string[]]$Arguments
    )

    # Windows PowerShell 5.1 does not provide ProcessStartInfo.ArgumentList.
    # Build a safely quoted command line for git arguments used by this script.
    $quoted = @(
        $Arguments | ForEach-Object {
            if ($_ -match '[\s"]') {
                '"' + ($_ -replace '"', '\"') + '"'
            }
            else {
                $_
            }
        }
    )

    $startInfo = New-Object System.Diagnostics.ProcessStartInfo
    $startInfo.FileName = "git.exe"
    $startInfo.Arguments = ($quoted -join " ")
    $startInfo.UseShellExecute = $false
    $startInfo.RedirectStandardOutput = $true
    $startInfo.RedirectStandardError = $true
    $startInfo.CreateNoWindow = $true
    $startInfo.WorkingDirectory = $root

    $process = New-Object System.Diagnostics.Process
    $process.StartInfo = $startInfo

    [void] $process.Start()

    $stdout = $process.StandardOutput.ReadToEnd()
    $stderr = $process.StandardError.ReadToEnd()

    $process.WaitForExit()

    if ($process.ExitCode -ne 0) {
        throw "Git command failed: git $($Arguments -join ' ')`n$stderr"
    }

    if ([string]::IsNullOrWhiteSpace($stdout)) {
        return @()
    }

    return @(
        $stdout -split "\r?\n" |
        Where-Object { -not [string]::IsNullOrWhiteSpace($_) }
    )
}

$files = @()

if ($All) {
    $files = @(
        Get-ChildItem -Path $root -Recurse -File -Filter "*.php" |
        Where-Object {
            $_.FullName -notmatch "\\vendor\\" -and
            $_.FullName -notmatch "\\node_modules\\" -and
            $_.FullName -notmatch "\\uploads\\" -and
            $_.FullName -notmatch "\\backups\\" -and
            $_.FullName -notmatch "\\storage\\" -and
            $_.FullName -notmatch "\\.git\\" -and
            $_.FullName -notmatch "\\.codex-runtime\\"
        } |
        Select-Object -ExpandProperty FullName
    )
}
else {
    $git = Find-Executable -Name "git"

    if (-not $git) {
        throw "Git is required to detect changed PHP files. Use -All for the complete project."
    }

    $changed = @()
    $changed += @(Invoke-GitLines -Arguments @("diff", "--name-only", "--diff-filter=ACMR"))
    $changed += @(Invoke-GitLines -Arguments @("diff", "--cached", "--name-only", "--diff-filter=ACMR"))
    $changed += @(Invoke-GitLines -Arguments @("ls-files", "--others", "--exclude-standard"))

    $files = @(
        $changed |
        Where-Object {
            $_ -match "\.php$" -and
            (Test-Path $_ -PathType Leaf)
        } |
        Sort-Object -Unique
    )
}

if ($files.Count -eq 0) {
    $scope = if ($All) { "the repository" } else { "the changed files" }
    Write-Host "No PHP files were found in $scope." -ForegroundColor Yellow
    exit 0
}

$failed = @()

foreach ($file in $files) {
    $startInfo = New-Object System.Diagnostics.ProcessStartInfo
    $startInfo.FileName = $php
    $startInfo.Arguments = '-l "' + ($file -replace '"', '\"') + '"'
    $startInfo.UseShellExecute = $false
    $startInfo.RedirectStandardOutput = $true
    $startInfo.RedirectStandardError = $true
    $startInfo.CreateNoWindow = $true
    $startInfo.WorkingDirectory = $root

    $process = New-Object System.Diagnostics.Process
    $process.StartInfo = $startInfo

    [void] $process.Start()

    $stdout = $process.StandardOutput.ReadToEnd()
    $stderr = $process.StandardError.ReadToEnd()

    $process.WaitForExit()

    $result = (($stdout + [Environment]::NewLine + $stderr).Trim())

    if ($process.ExitCode -ne 0) {
        $failed += $file
        Write-Host "[FAIL] $file" -ForegroundColor Red
        Write-Host $result
    }
    else {
        Write-Host "[OK] $file" -ForegroundColor Green
    }
}

Write-Host ""
Write-Host "Checked: $($files.Count) | Passed: $($files.Count - $failed.Count) | Failed: $($failed.Count)" -ForegroundColor Cyan

if ($failed.Count -gt 0) {
    Write-Host ""
    Write-Host "Failed files:" -ForegroundColor Red
    foreach ($file in $failed) {
        Write-Host " - $file" -ForegroundColor Red
    }

    exit 1
}

Write-Host "PHP lint passed." -ForegroundColor Green
exit 0
