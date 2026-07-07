$ErrorActionPreference = 'Stop'

$path = 'E:\Site\SobhanHolding\install\data\sobhan_assessment_20_battery.json'
$json = Get-Content -Raw -LiteralPath $path | ConvertFrom-Json

function Assert-True {
    param([bool]$Condition, [string]$Message)
    if (-not $Condition) { throw $Message }
}

Assert-True ($json.meta.seed_key -eq 'sobhan_20_assessment_battery') 'seed_key mismatch'
Assert-True ($json.meta.seed_version -eq '2026-07-07-v1') 'seed_version mismatch'
Assert-True ($json.tests.Count -eq 20) 'expected 20 tests'

$questionTotal = 0
foreach ($test in $json.tests) {
    Assert-True ($test.questions.Count -ge 20) "test $($test.test_code) has fewer than 20 questions"
    foreach ($question in $test.questions) {
        $questionTotal++
        Assert-True (-not ($question.question_text -match '^\d+\.\s')) "numbering artifact stored in $($question.question_code)"
    }
}

Assert-True ($questionTotal -eq 400) 'expected 400 questions'

$reverseCodes = @('DISC-S4','MBTI-EI2','MBTI-EI4','JS3','JS7','JS11','JS15','JS19','BO12','BO13','BO14','BO15','BO16','IN8','IN12','IN17')
$allQuestions = foreach ($test in $json.tests) { $test.questions }
foreach ($code in $reverseCodes) {
    $match = $allQuestions | Where-Object { $_.question_code -eq $code }
    Assert-True ($null -ne $match) "missing reverse question $code"
    Assert-True ([int]$match.reverse_score -eq 1) "reverse_score not set for $code"
}

$knowledgeTests = @('PRODUCT_KNOWLEDGE_DISTRIBUTION','SALES_CATALOG_KNOWLEDGE','SERVICE_STANDARDS','HEALTH_SAFETY','UPSELL_READINESS')
foreach ($testCode in $knowledgeTests) {
    $test = $json.tests | Where-Object { $_.test_code -eq $testCode }
    Assert-True ($null -ne $test) "missing knowledge test $testCode"
    foreach ($question in $test.questions) {
        Assert-True ($question.answer_type -eq 'choice') "$($question.question_code) should be choice"
        Assert-True ([string]::IsNullOrWhiteSpace([string]$question.correct_answer_json)) "$($question.question_code) should not have fake answer key"
    }
}

Write-Output 'hr_assessment_20_battery_contract_test: PASS'
