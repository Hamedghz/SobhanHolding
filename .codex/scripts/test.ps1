. "$PSScriptRoot\common.ps1"

$root = Get-SobhanRepoRoot
Set-Location $root

Write-Host "Running Sobhan validation" -ForegroundColor Cyan

& "$PSScriptRoot\policy-contract-test.ps1"

& "$PSScriptRoot\lint.ps1"

$php = Get-PhpExecutable
$ranAdditionalTests = $false

if ($php -and (Test-Path "vendor\bin\phpunit")) {
    Write-Host "Running PHPUnit..."
    & "vendor\bin\phpunit"
    if ($LASTEXITCODE -ne 0) { throw "PHPUnit failed." }
    $ranAdditionalTests = $true
}

if ($php -and (Test-Path "tests\run.php")) {
    Write-Host "Running tests\run.php..."
    & $php "tests\run.php"
    if ($LASTEXITCODE -ne 0) { throw "tests\run.php failed." }
    $ranAdditionalTests = $true
}

if (-not $ranAdditionalTests) {
    Write-Host "No recognized automated test runner found. PHP lint was completed." -ForegroundColor Yellow
}

Write-Host "Validation completed." -ForegroundColor Green
