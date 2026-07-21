$ErrorActionPreference = 'Stop'

$root = Split-Path -Parent $PSScriptRoot
$read = {
    param([string]$Path)
    Get-Content -Raw (Join-Path $root $Path)
}

$header = & $read 'views/partials/admin-header.php'
$footer = & $read 'views/partials/admin-footer.php'
$css = & $read 'assets/css/app-compact-ui.css'
$js = & $read 'assets/js/app-compact-ui.js'

$checks = [ordered]@{
    'scoped compact body class' = $header.Contains("app-compact-ui") -and $css.Contains('.app-compact-ui')
    'target module coverage' = $header.Contains('work-planner.php') -and $header.Contains('sales-manager-daily-work-log.php') -and $header.Contains('sales-actions.php') -and $header.Contains('sales-offer-formula-settings.php') -and $header.Contains('sales-aggregate-import.php') -and $header.Contains('hr-attendance.php') -and $header.Contains('letter-create.php') -and $header.Contains('ceo-dashboard-settings.php')
    'local assets loaded' = $header.Contains('/assets/css/app-compact-ui.css') -and $footer.Contains('/assets/js/app-compact-ui.js')
    'compact form tokens' = $css.Contains('--compact-control-height: 42px') -and $css.Contains('.app-form-grid')
    'sticky actions' = $css.Contains('.app-sticky-actions') -and $js.Contains('decorateActionBars')
    'mobile table cards' = $css.Contains('table.app-mobile-cards') -and $js.Contains('data-label')
    'advanced sections' = $css.Contains('details.app-advanced-section') -and $js.Contains('decorateAdvancedSections')
    'empty and loading states' = $css.Contains('.app-empty-state') -and $css.Contains('.app-loading')
    'dynamic content support' = $js.Contains('MutationObserver')
    'motion and reduced-motion support' = $js.Contains('window.Motion.animate') -and $css.Contains('@media (prefers-reduced-motion: reduce)')
}

$failed = @($checks.GetEnumerator() | Where-Object { -not $_.Value })
if ($failed.Count -gt 0) {
    $failed | ForEach-Object { Write-Error "Compact UI contract failed: $($_.Key)" }
    exit 1
}

Write-Output 'COMPACT_UI_CONTRACT_OK'
