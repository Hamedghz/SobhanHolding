$ErrorActionPreference = 'Stop'

$root = Split-Path -Parent $PSScriptRoot
$read = {
    param([string]$Path)
    Get-Content -Raw (Join-Path $root $Path)
}

$header = & $read 'views/partials/admin-header.php'
$footer = & $read 'views/partials/admin-footer.php'
$response = & $read 'core/Response.php'
$dateJs = & $read 'assets/js/app-jalali-date.js'
$motionJs = & $read 'assets/js/app-motion.js'
$dateCss = & $read 'assets/css/app-jalali-date.css'

$checks = [ordered]@{
    'local Jalali CSS' = $header.Contains('/assets/vendor/jalalidatepicker/jalalidatepicker-1.0.0.min.css')
    'local Jalali JS' = $footer.Contains('/assets/vendor/jalalidatepicker/jalalidatepicker-1.0.0.min.js')
    'local Motion JS' = $footer.Contains('/assets/vendor/motion/motion-12.42.2.min.js')
    'shared date input helper' = $response.Contains('function app_date_input') -and $response.Contains('data-jalali-date')
    'shared period selector' = $response.Contains('function app_period_select') -and $response.Contains('data-period-selector')
    'dynamic date support' = $dateJs.Contains('MutationObserver') -and $dateJs.Contains('dataset.appDateReady')
    'reduced motion' = $motionJs.Contains('prefers-reduced-motion') -and $dateCss.Contains('@media (prefers-reduced-motion: reduce)')
    'mobile picker sheet' = $dateCss.Contains('position: fixed !important') -and $dateCss.Contains('max-height: min(82vh, 680px)')
}

$nativeInputs = Get-ChildItem (Join-Path $root 'admin'),(Join-Path $root 'employee'),(Join-Path $root 'views') -Recurse -Filter '*.php' |
    Select-String -Pattern 'type="date"|type="datetime-local"'
if ($nativeInputs) {
    $nativeInputs | ForEach-Object { Write-Error "Native date input remains: $($_.Path):$($_.LineNumber)" }
    exit 1
}

$dateNamePattern = 'name="(?:date|date_from|date_to|from_date|to_date|start_date|end_date|due_date|deadline|[a-z0-9_]+_date|[a-z0-9_]+_deadline)"'
$dateNamedInputs = Get-ChildItem (Join-Path $root 'admin'),(Join-Path $root 'employee'),(Join-Path $root 'views') -Recurse -Filter '*.php' |
    Select-String -Pattern '<input\b[^>]*>' -AllMatches |
    ForEach-Object {
        $matchInfo = $_
        foreach ($match in $_.Matches) {
            $tag = $match.Value
            if ($tag -match $dateNamePattern -and
                $tag -notmatch 'type="hidden"' -and
                $tag -notmatch 'jalali-date-input|data-jalali-date') {
                [pscustomobject]@{ Path = $matchInfo.Path; LineNumber = $matchInfo.LineNumber; Tag = $tag }
            }
        }
    }
if ($dateNamedInputs) {
    $dateNamedInputs | ForEach-Object { Write-Error "Unbound Jalali date input remains: $($_.Path):$($_.LineNumber) $($_.Tag)" }
    exit 1
}

$failed = @($checks.GetEnumerator() | Where-Object { -not $_.Value })
if ($failed.Count -gt 0) {
    $failed | ForEach-Object { Write-Error "AppDate UI contract failed: $($_.Key)" }
    exit 1
}

Write-Output 'APP_DATE_UI_CONTRACT_OK'
