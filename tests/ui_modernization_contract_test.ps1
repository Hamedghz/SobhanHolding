$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot

function Assert-Contains([string]$Path, [string]$Pattern, [string]$Message) {
    $content = Get-Content -Raw (Join-Path $root $Path)
    if ($content -notmatch $Pattern) { throw $Message }
}

Assert-Contains 'views/partials/admin-header.php' 'ui-modernization\.css' 'Shared modernization stylesheet is not loaded.'
Assert-Contains 'views/partials/admin-footer.php' 'ui-modernization\.js' 'Shared modernization script is not loaded.'
foreach ($route in @('users','tickets','hr-kpi-results','hr-assessment-results','employee-assessments','management-reports','payroll-slips')) {
    Assert-Contains 'assets/js/ui-modernization.js' ("/admin/" + [regex]::Escape($route) + "\.php") ("Table route is missing: " + $route)
}
Assert-Contains 'api/ui/dashboard-ceo.php' "'charts'\s*=>\s*\`$charts" 'Dashboard chart contract is missing.'
Assert-Contains 'assets/css/ui-modernization.css' '--theme-accent-contrast' 'Theme contrast token is not used.'
Assert-Contains 'assets/js/ui-modernization.js' 'jalali-date-input' 'Jalali date enhancement is missing.'

Write-Host 'UI modernization contract: PASS'
