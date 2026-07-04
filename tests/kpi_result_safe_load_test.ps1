$ErrorActionPreference='Stop';$root=Split-Path -Parent $PSScriptRoot
$results=Get-Content(Join-Path $root 'admin/hr-kpi-results.php')-Raw;$scores=Get-Content(Join-Path $root 'admin/hr-kpi-scores.php')-Raw;$templates=Get-Content(Join-Path $root 'admin/hr-kpi-templates.php')-Raw;$module=Get-Content(Join-Path $root 'core/HrModule.php')-Raw
foreach($token in @('kpi_safe_row','average_score','OrgAccess::scopeSql','sales_line','supervisor_id','organization_manager_id','نتیجه‌ای یافت نشد')){if($results-notmatch[regex]::Escape($token)){throw "KPI safe-load/filter token missing: $token"}}
foreach($token in @('hr_kpi_template_roles','org_unit_id','sales_line','قالب KPI برای ساختار سازمانی این کاربر معتبر نیست')){if(($scores+$templates+$module)-notmatch[regex]::Escape($token)){throw "KPI role-aware token missing: $token"}}
foreach($token in @('INSERT IGNORE INTO hr_kpi_periods','INSERT IGNORE INTO hr_kpi_templates','INSERT IGNORE INTO hr_kpi_criteria','criteria_hash')){if($module-notmatch[regex]::Escape($token)){throw "KPI idempotent seed token missing: $token"}}
if(($results+$scores+$templates+$module)-match'(?i)\b(DROP|TRUNCATE|RENAME)\b'){throw 'Destructive SQL token found.'}
Write-Output 'KPI safe-load contract checks passed.'
