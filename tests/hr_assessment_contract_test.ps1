$ErrorActionPreference='Stop';$root=Split-Path -Parent $PSScriptRoot
$required=@('core/HrModule.php','services/TestScoringService.php','admin/employee-assessments.php','admin/hr-assessment-results.php','employee/test_run.php','install/seeds/004_hr_assessment_seed.php')
foreach($file in $required){if(-not(Test-Path(Join-Path $root $file))){throw "Missing: $file"}}
$module=Get-Content(Join-Path $root 'core/HrModule.php')-Raw;$scoring=Get-Content(Join-Path $root 'services/TestScoringService.php')-Raw;$run=Get-Content(Join-Path $root 'employee/test_run.php')-Raw;$assign=Get-Content(Join-Path $root 'admin/employee-assessments.php')-Raw
foreach($code in @('MBTI_ORG','DISC_ORG','EQ_ORG','JOB_SATISFACTION','COMMITMENT_ORG','BURNOUT_ORG','HOLLAND_ORG','MII_ORG','RAVEN_ABSTRACT_ORG','SPATIAL_ORG')){if($module-notmatch[regex]::Escape($code)-or$scoring-notmatch[regex]::Escape($code)){throw "Assessment scoring missing: $code"}}
foreach($scope in @('employee','unit','role','sales_line','supervisor_team','manager_team','company')){if($assign-notmatch[regex]::Escape($scope)){throw "Assignment scope missing: $scope"}}
foreach($token in @('INSERT IGNORE INTO hr_assessment_tests','INSERT IGNORE INTO hr_assessment_dimensions','INSERT IGNORE INTO hr_assessment_questions','question_hash')){if($module-notmatch[regex]::Escape($token)){throw "Assessment idempotent seed token missing: $token"}}
foreach($token in @('answers_json','FOR UPDATE','beginTransaction','rollBack','TestScoringService','notifyAssessmentCompleted','a.employee_id=?')){if(($run+$assign)-notmatch[regex]::Escape($token)){throw "Assessment integrity token missing: $token"}}
if(($module+$scoring+$run+$assign)-match'(?i)\b(DROP|TRUNCATE|RENAME)\b'){throw 'Destructive SQL token found.'}
Write-Output "HR assessment contract checks passed ($($required.Count) files)."
