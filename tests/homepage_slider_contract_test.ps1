$ErrorActionPreference = 'Stop'

$root = Split-Path -Parent $PSScriptRoot
$index = Get-Content -Raw (Join-Path $root 'index.php')
$script = Get-Content -Raw (Join-Path $root 'assets/js/carousel.js')
$style = Get-Content -Raw (Join-Path $root 'assets/css/app.css')

$checks = @{
    'carousel semantics' = $index.Contains('aria-roledescription="carousel"')
    'navigation controls' = $index.Contains('data-hero-prev') -and $index.Contains('data-hero-next')
    'external carousel script' = $index.Contains('/assets/js/carousel.js')
    'accessible hidden state' = $script.Contains("setAttribute('aria-hidden'")
    'reduced motion support' = $script.Contains('prefers-reduced-motion') -and $style.Contains('@media (prefers-reduced-motion: reduce)')
    'interaction pause' = $script.Contains("addEventListener('mouseenter', stop)") -and $script.Contains("addEventListener('focusin', stop)")
    'panel source and schedule' = $index.Contains('CarouselModule::publicItems()')
    'responsive image' = $index.Contains('<picture') -and $index.Contains('mobile_image_path')
    'safe external target' = $index.Contains('noopener noreferrer')
    'no legacy cloned track' = -not $script.Contains('track.innerHTML += track.innerHTML')
}

$failed = @($checks.GetEnumerator() | Where-Object { -not $_.Value })
if ($failed.Count -gt 0) {
    $failed | ForEach-Object { Write-Error "Homepage slider contract failed: $($_.Key)" }
    exit 1
}

Write-Output 'HOMEPAGE_SLIDER_CONTRACT_OK'
