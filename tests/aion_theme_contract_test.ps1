$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot

$theme = Get-Content (Join-Path $root 'core/ThemeProfile.php') -Raw
$themePage = Get-Content (Join-Path $root 'admin/theme-settings.php') -Raw
$settings = Get-Content (Join-Path $root 'admin/settings.php') -Raw
$pwa = Get-Content (Join-Path $root 'core/Pwa.php') -Raw
$menu = Get-Content (Join-Path $root 'lib/admin_menu.php') -Raw
$css = Get-Content (Join-Path $root 'assets/css/admin-theme-profiles.css') -Raw
$publicHeader = Get-Content (Join-Path $root 'views/partials/header.php') -Raw

foreach ($profile in @('aion_neon_glass','white_neon','frost','minimal')) {
    if ($theme -notmatch [regex]::Escape("'$profile'")) { throw "Theme profile missing: $profile" }
}
foreach ($token in @('--theme-bg','--theme-bg-soft','--theme-bg-deep','--theme-surface','--theme-surface-soft','--theme-surface-strong','--theme-text','--theme-text-strong','--theme-muted','--theme-border','--theme-border-strong','--theme-accent-2','--theme-accent-3','--theme-radius','--theme-radius-sm','--theme-shadow','--theme-glow')) {
    if ($css -notmatch [regex]::Escape($token)) { throw "AION token missing: $token" }
}
foreach ($selector in @('.admin-sidebar','.admin-topbar','.stat-card','.table-wrap','.site-settings-form','.theme-effects-reduced','@media (max-width:900px)')) {
    if ($css -notmatch [regex]::Escape($selector)) { throw "AION component contract missing: $selector" }
}

foreach ($guard in @("Auth::requirePermission('settings', 'view')","Auth::can('settings', 'edit')","Auth::verifyCsrf","Pwa::fields()",'INSERT INTO site_settings','ON DUPLICATE KEY UPDATE')) {
    if ($settings -notmatch [regex]::Escape($guard)) { throw "Site settings contract missing: $guard" }
}
foreach ($key in @('company_name','site_title','hero_subtitle','meta_description','footer_text','primary_color','logo_path')) {
    if ($settings -notmatch [regex]::Escape($key)) { throw "Site/PWA field missing: $key" }
}
foreach ($key in @('pwa_theme_color','pwa_background_color','pwa_icon_192','pwa_icon_512','pwa_favicon')) {
    if ($pwa -notmatch [regex]::Escape($key)) { throw "PWA field definition missing: $key" }
}
if ($themePage -notmatch 'accent_color' -or $themePage -notmatch 'effects_mode') { throw 'Panel theme controls are missing.' }
if ($settings -match 'accent_color' -or $themePage -match 'primary_color|pwa_theme_color') { throw 'Site, PWA and panel color domains were coupled.' }
if ($menu -notmatch "'/admin/settings.php'" -or $menu -notmatch "'active' => \['settings.php'\]") { throw 'Site settings menu route/active state is missing.' }
if ($menu -notmatch "'/admin/theme-settings.php'") { throw 'Panel appearance menu route is missing.' }
if ($publicHeader -notmatch "setting\('primary_color'" -or $publicHeader -notmatch "'--primary:'") { throw 'Public site primary color is not applied.' }

Write-Output 'AION theme and independent site-settings contracts passed.'
