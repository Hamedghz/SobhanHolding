param(
    [string]$DocxPath = 'C:\Users\omerta\Downloads\بانک سوال آزمون سازمانی سبحان (1).docx',
    [string]$OutputPath = 'E:\Site\SobhanHolding\install\data\sobhan_assessment_20_battery.json'
)

$ErrorActionPreference = 'Stop'

Add-Type -AssemblyName System.IO.Compression.FileSystem

$seedKey = 'sobhan_20_assessment_battery'
$seedVersion = '2026-07-07-v1'
$sourceTitle = 'بانک سؤال ۲۰ آزمون سازمانی شرکت پخش سبحان'
$sourceFile = 'بانک سوال آزمون سازمانی سبحان (1).docx'
$likertOptions = [ordered]@{
    '1' = 'کاملاً مخالفم'
    '2' = 'مخالفم'
    '3' = 'متوسط / نظری ندارم'
    '4' = 'موافقم'
    '5' = 'کاملاً موافقم'
}

$testsMeta = [ordered]@{
    '1. DISC سازمانی' = @{
        code='DISC_ORG'; title='DISC سازمانی'; category='behavioral_profile'; scoring_type='likert_dimensions'; minutes=20
        dimensions=[ordered]@{
            'D'='پیش‌رانش و نتیجه‌گرایی'; 'I'='اثرگذاری و ارتباط'; 'S'='ثبات و حمایت'; 'C'='دقت و استاندارد'
        }
    }
    '2. MBTI غیررسمی سازمانی' = @{
        code='MBTI_ORG_INFORMAL'; title='MBTI غیررسمی سازمانی'; category='development_profile'; scoring_type='likert_dimensions'; minutes=20
        dimensions=[ordered]@{
            'EI'='انرژی ارتباطی'; 'SN'='واقعیت‌گرایی و الگویابی'; 'TF'='تصمیم منطقی و انسانی'; 'JP'='ساختار و انعطاف'
        }
    }
    '3. هوش هیجانی سازمانی' = @{
        code='EQ_ORG'; title='هوش هیجانی سازمانی'; category='emotional_intelligence'; scoring_type='likert_dimensions'; minutes=20
        dimensions=[ordered]@{
            'self_awareness'='خودآگاهی هیجانی'; 'others_awareness'='آگاهی از هیجان دیگران'; 'constructive_emotion_use'='استفاده سازنده از هیجان'; 'emotion_regulation'='تنظیم هیجان'
        }
    }
    '4. رضایت شغلی' = @{
        code='JOB_SATISFACTION_ORG'; title='رضایت شغلی'; category='job_satisfaction'; scoring_type='likert_dimensions'; minutes=20
        dimensions=[ordered]@{
            'compensation'='جبران خدمت'; 'supervisor_support'='سرپرستی و حمایت'; 'work_conditions'='شرایط و منابع'; 'growth_appreciation'='رشد و قدردانی'; 'schedule_pressure'='زمان‌بندی و فشار کار'
        }
    }
    '5. تعهد سازمانی' = @{
        code='ORGANIZATIONAL_COMMITMENT'; title='تعهد سازمانی'; category='organizational_commitment'; scoring_type='likert_dimensions'; minutes=20
        dimensions=[ordered]@{
            'affective_commitment'='تعهد عاطفی'; 'continuance_commitment'='تعهد تداومی'; 'normative_commitment'='تعهد هنجاری'; 'impact_growth'='اثرگذاری و رشد'
        }
    }
    '6. فرسودگی شغلی' = @{
        code='BURNOUT_ORG'; title='فرسودگی شغلی'; category='burnout_monitoring'; scoring_type='likert_dimensions'; minutes=20
        dimensions=[ordered]@{
            'exhaustion'='خستگی هیجانی و بدنی'; 'disengagement'='گسستگی و بدبینی'; 'perceived_effectiveness'='کارآمدی ادراک‌شده'; 'recovery_pressure'='فشار بازیابی'
        }
    }
    '7. تناسب نقش با شخصیت کاری' = @{
        code='ROLE_PERSON_FIT'; title='تناسب نقش با شخصیت کاری'; category='role_fit'; scoring_type='likert_dimensions'; minutes=20
        dimensions=[ordered]@{
            'ability_demand_fit'='تناسب توانایی و تقاضای شغل'; 'need_supply_fit'='تناسب نیاز و تأمین'; 'value_culture_fit'='تناسب ارزشی و فرهنگی'; 'overall_fit'='تناسب کلی'
        }
    }
    '8. آمادگی کار تیمی' = @{
        code='TEAMWORK_READINESS'; title='آمادگی کار تیمی'; category='teamwork'; scoring_type='likert_dimensions'; minutes=20
        dimensions=[ordered]@{
            'team_communication'='ارتباط تیمی'; 'conflict_resolution'='حل تعارض'; 'mutual_support'='حمایت متقابل'; 'coordination_followup'='هماهنگی و پیگیری'
        }
    }
    '9. آمادگی برخورد با مشتری' = @{
        code='CUSTOMER_INTERACTION'; title='آمادگی برخورد با مشتری'; category='customer_interaction'; scoring_type='likert_dimensions'; minutes=20
        dimensions=[ordered]@{
            'listening_need_discovery'='گوش‌دادن و کشف نیاز'; 'courtesy_clarity'='ادب و وضوح'; 'complaint_recovery'='رسیدگی به اعتراض'; 'service_initiative'='پیش‌قدمی خدماتی'
        }
    }
    '10. مدیریت استرس در محیط شلوغ' = @{
        code='STRESS_BUSY_ENV'; title='مدیریت استرس در محیط شلوغ'; category='stress_management'; scoring_type='likert_dimensions'; minutes=20
        dimensions=[ordered]@{
            'pressure_tolerance'='تحمل فشار'; 'prioritization'='اولویت‌بندی'; 'emotional_control'='کنترل هیجانی'; 'recovery_help_seeking'='بازیابی و کمک‌خواهی'
        }
    }
    '11. دانش محصول شرکت پخش' = @{
        code='PRODUCT_KNOWLEDGE_DISTRIBUTION'; title='دانش محصول شرکت پخش'; category='distribution_product_knowledge'; scoring_type='knowledge_template'; minutes=20
        dimensions=[ordered]@{
            'portfolio_sku'='پورتفوی و SKU'; 'usage_claims'='کاربرد و ادعا'; 'storage_transport'='نگهداری و حمل'; 'price_promotion'='قیمت و پروموشن'
        }
    }
    '12. دانش منوی رستوران / نسخه قابل استفاده برای سبحان: دانش کاتالوگ فروش' = @{
        code='SALES_CATALOG_KNOWLEDGE'; title='دانش کاتالوگ فروش و سبد پیشنهادی'; category='distribution_sales_knowledge'; scoring_type='knowledge_template'; minutes=20
        dimensions=[ordered]@{
            'assortment_knowledge'='شناخت سبد کالا'; 'product_introduction_order'='ترتیب معرفی محصول'; 'price_discount_offer'='قیمت، تخفیف و آفر'; 'alternative_complement'='پیشنهاد جایگزین و مکمل'
        }
    }
    '13. استانداردهای داخلی سرویس‌دهی' = @{
        code='SERVICE_STANDARDS'; title='استانداردهای داخلی سرویس‌دهی'; category='service_standards'; scoring_type='knowledge_template'; minutes=20
        dimensions=[ordered]@{
            'service_start'='شروع خدمت'; 'accuracy_speed'='دقت و سرعت'; 'followup'='پیگیری'; 'service_closure'='پایان خدمت'
        }
    }
    '14. بهداشت و ایمنی' = @{
        code='HEALTH_SAFETY'; title='بهداشت و ایمنی'; category='health_safety'; scoring_type='knowledge_template'; minutes=20
        dimensions=[ordered]@{
            'hygiene_environment'='بهداشت فردی و محیطی'; 'hazard_detection'='شناسایی خطر'; 'incident_response'='واکنش به حادثه'; 'equipment_process'='تجهیزات و فرایند'
        }
    }
    '15. فروش پیشنهادی و Upsell' = @{
        code='UPSELL_READINESS'; title='فروش پیشنهادی و Upsell'; category='upsell_readiness'; scoring_type='knowledge_template'; minutes=20
        dimensions=[ordered]@{
            'need_discovery'='کشف نیاز'; 'offer_fit'='تناسب پیشنهاد'; 'offer_timing'='زمان‌بندی پیشنهاد'; 'ethical_closing'='بستن اخلاقی فروش'
        }
    }
    '16. مسئولیت‌پذیری' = @{
        code='RESPONSIBILITY'; title='مسئولیت‌پذیری'; category='responsibility'; scoring_type='likert_dimensions'; minutes=20
        dimensions=[ordered]@{
            'completion_followup'='پیگیری تا پایان'; 'error_ownership'='مالکیت خطا'; 'escalation_reporting'='اطلاع‌رسانی و تصعید'; 'reliability'='اتکاپذیری'
        }
    }
    '17. انضباط کاری' = @{
        code='WORK_DISCIPLINE'; title='انضباط کاری'; category='work_discipline'; scoring_type='likert_dimensions'; minutes=20
        dimensions=[ordered]@{
            'punctuality'='وقت‌شناسی'; 'procedure_compliance'='رعایت رویه'; 'documentation'='ثبت و مستندسازی'; 'behavioral_consistency'='ثبات رفتاری'
        }
    }
    '18. آمادگی یادگیری' = @{
        code='LEARNING_READINESS'; title='آمادگی یادگیری'; category='learning_readiness'; scoring_type='likert_dimensions'; minutes=20
        dimensions=[ordered]@{
            'learning_orientation'='جهت‌گیری یادگیری'; 'feedback_receptivity'='بازخوردپذیری'; 'change_flexibility'='انعطاف در تغییر'; 'deliberate_practice'='تمرین هدفمند'
        }
    }
    '19. حرفه‌ای‌گری' = @{
        code='PROFESSIONALISM'; title='حرفه‌ای‌گری'; category='professionalism'; scoring_type='likert_dimensions'; minutes=20
        dimensions=[ordered]@{
            'respect_professional_etiquette'='احترام و آداب حرفه‌ای'; 'boundaries_confidentiality'='حدود و محرمانگی'; 'grooming_documentation'='آراستگی و مستندسازی'; 'error_principle_handling'='برخورد با خطا و اصول'
        }
    }
    '20. صداقت / درستی' = @{
        code='INTEGRITY_HONESTY'; title='صداقت / درستی'; category='integrity_honesty'; scoring_type='likert_dimensions'; minutes=20
        dimensions=[ordered]@{
            'truthful_expression'='صداقت در بیان'; 'fairness'='انصاف'; 'rule_compliance'='رعایت قواعد'; 'no_personal_exploitation'='عدم بهره‌برداری شخصی'
        }
    }
}

$knowledgeTests = @('PRODUCT_KNOWLEDGE_DISTRIBUTION','SALES_CATALOG_KNOWLEDGE','SERVICE_STANDARDS','HEALTH_SAFETY','UPSELL_READINESS')
$pendingNote = 'این سؤال نیازمند تکمیل گزینه‌ها و کلید صحیح بر اساس SOP، برندها، SKUها، آفرها و فرایندهای واقعی سبحان است.'

function Get-ParagraphRows {
    param([string]$Path)
    $zip = [IO.Compression.ZipFile]::OpenRead($Path)
    try {
        $entry = $zip.GetEntry('word/document.xml')
        $reader = New-Object IO.StreamReader($entry.Open())
        $xmlText = $reader.ReadToEnd()
        $reader.Dispose()
        [xml]$xml = $xmlText
        $ns = New-Object System.Xml.XmlNamespaceManager($xml.NameTable)
        $ns.AddNamespace('w','http://schemas.openxmlformats.org/wordprocessingml/2006/main')
        $rows = @()
        foreach ($p in $xml.SelectNodes('//w:body/w:p', $ns)) {
            $texts = @()
            foreach ($t in $p.SelectNodes('.//w:t', $ns)) {
                $texts += $t.InnerText
            }
            $line = ($texts -join '').Trim()
            if ($line.Length -eq 0) { continue }
            $styleNode = $p.SelectSingleNode('./w:pPr/w:pStyle', $ns)
            $style = ''
            if ($styleNode) {
                $style = $styleNode.GetAttribute('w:val')
                if (-not $style) { $style = $styleNode.GetAttribute('val') }
            }
            $rows += [pscustomobject]@{ Style = $style; Text = $line }
        }
        return $rows
    } finally {
        $zip.Dispose()
    }
}

$rows = Get-ParagraphRows -Path $DocxPath
$tests = @()
$currentTest = $null
$currentDimensions = $null
$currentDimensionKey = $null
$currentDimensionLabel = $null
$dimensionSort = 10
$questionSort = 10

foreach ($row in $rows) {
    if ($row.Style -eq 'Heading2') {
        if ($currentTest) { $tests += $currentTest }
        if (-not $testsMeta.Contains($row.Text)) { throw "Unknown test heading: $($row.Text)" }
        $meta = $testsMeta[$row.Text]
        $currentTest = [ordered]@{
            test_code = $meta.code
            test_title = $meta.title
            category = $meta.category
            scoring_type = $meta.scoring_type
            time_limit_minutes = $meta.minutes
            seed_key = $seedKey
            seed_version = $seedVersion
            dimensions = @()
            questions = @()
        }
        $currentDimensions = $meta.dimensions
        $currentDimensionKey = $null
        $currentDimensionLabel = $null
        $dimensionSort = 10
        $questionSort = 10
        continue
    }
    if (-not $currentTest) { continue }
    if ($row.Style -eq 'Heading3') {
        $dimensionKey = $null
        $headingLabel = ($row.Text -replace '^[A-Za-z\/]+\s+—\s+', '').Trim()
        foreach ($item in $currentDimensions.GetEnumerator()) {
            if ($item.Value -eq $row.Text -or $item.Value -eq $headingLabel) { $dimensionKey = $item.Key; break }
        }
        if (-not $dimensionKey) { throw "Unknown dimension heading '$($row.Text)' for $($currentTest.test_code)" }
        $currentDimensionKey = $dimensionKey
        $currentDimensionLabel = if ($headingLabel) { $headingLabel } else { $row.Text }
        $currentTest.dimensions += [ordered]@{
            dimension_key = $dimensionKey
            dimension_label = $currentDimensionLabel
            sort_order = $dimensionSort
            seed_key = $seedKey
            seed_version = $seedVersion
        }
        $dimensionSort += 10
        continue
    }
    if ($row.Style -ne 'Compact') { continue }
    if (-not $currentDimensionKey) { throw "Question found before dimension in $($currentTest.test_code)" }
    if ($row.Text -notmatch '^(?:\d+\.\s*)?(?<code>[A-Z0-9\-]+)\s+—\s+(?<text>.+)$') { throw "Unparseable question row: $($row.Text)" }
    $questionCode = $matches.code.Trim()
    $questionText = $matches.text.Trim()
    $reverse = $false
    if ($questionText -match '\[R\]\s*$') {
        $reverse = $true
        $questionText = ($questionText -replace '\s*\[R\]\s*$', '').Trim()
    }
    $isKnowledge = $knowledgeTests -contains $currentTest.test_code
    $answerType = if ($isKnowledge) { 'choice' } else { 'scale_1_5' }
    $optionsJson = if ($isKnowledge) { $null } else { ($likertOptions | ConvertTo-Json -Compress) }
    $correctAnswerJson = $null
    $adminNote = if ($isKnowledge) { $pendingNote } else { $null }
    $currentTest.questions += [ordered]@{
        question_code = $questionCode
        question_text = $questionText
        answer_type = $answerType
        options_json = $optionsJson
        correct_answer_json = $correctAnswerJson
        dimension_key = $currentDimensionKey
        secondary_dimension_key = $null
        weight = 1
        reverse_score = [int]$reverse
        required = 1
        sort_order = $questionSort
        active = 1
        admin_note = $adminNote
        seed_key = $seedKey
        seed_version = $seedVersion
    }
    $questionSort += 10
}

if ($currentTest) { $tests += $currentTest }

$packages = @(
    [ordered]@{ code='visitor_sales_rep'; title='بسته ویزیتور فروش'; role_key='visitor_sales_rep'; tests=@('DISC_ORG','EQ_ORG','CUSTOMER_INTERACTION','PRODUCT_KNOWLEDGE_DISTRIBUTION','UPSELL_READINESS','STRESS_BUSY_ENV','RESPONSIBILITY','WORK_DISCIPLINE') },
    [ordered]@{ code='sales_supervisor'; title='بسته سرپرست فروش'; role_key='sales_supervisor'; tests=@('DISC_ORG','EQ_ORG','ORGANIZATIONAL_COMMITMENT','CUSTOMER_INTERACTION','TEAMWORK_READINESS','STRESS_BUSY_ENV','BURNOUT_ORG','PROFESSIONALISM') },
    [ordered]@{ code='sales_manager'; title='بسته مدیر فروش'; role_key='sales_manager'; tests=@('DISC_ORG','MBTI_ORG_INFORMAL','EQ_ORG','ORGANIZATIONAL_COMMITMENT','ROLE_PERSON_FIT','TEAMWORK_READINESS','PROFESSIONALISM','INTEGRITY_HONESTY') },
    [ordered]@{ code='warehouse'; title='بسته انبار'; role_key='warehouse'; tests=@('WORK_DISCIPLINE','RESPONSIBILITY','HEALTH_SAFETY','TEAMWORK_READINESS','STRESS_BUSY_ENV','PROFESSIONALISM') },
    [ordered]@{ code='finance_admin'; title='بسته مالی و اداری'; role_key='finance_admin'; tests=@('WORK_DISCIPLINE','RESPONSIBILITY','INTEGRITY_HONESTY','PROFESSIONALISM','EQ_ORG','JOB_SATISFACTION_ORG') },
    [ordered]@{ code='it_planning'; title='بسته IT و برنامه‌ریزی'; role_key='it_planning'; tests=@('LEARNING_READINESS','ROLE_PERSON_FIT','RESPONSIBILITY','TEAMWORK_READINESS','EQ_ORG','PROFESSIONALISM') },
    [ordered]@{ code='driver_delivery'; title='بسته راننده و توزیع'; role_key='driver_delivery'; tests=@('WORK_DISCIPLINE','RESPONSIBILITY','HEALTH_SAFETY','CUSTOMER_INTERACTION','STRESS_BUSY_ENV','PROFESSIONALISM') }
)

$payload = [ordered]@{
    meta = [ordered]@{
        seed_key = $seedKey
        seed_version = $seedVersion
        source_title = $sourceTitle
        source_file = $sourceFile
        generated_at = (Get-Date).ToString('s')
        test_count = $tests.Count
        question_count = (@($tests | ForEach-Object { $_.questions.Count } | Measure-Object -Sum).Sum)
    }
    tests = $tests
    packages = $packages
}

$dir = Split-Path -Parent $OutputPath
if (-not (Test-Path -LiteralPath $dir)) {
    New-Item -ItemType Directory -Path $dir | Out-Null
}
$json = $payload | ConvertTo-Json -Depth 12
[IO.File]::WriteAllText($OutputPath, $json, [Text.UTF8Encoding]::new($false))
Write-Output "Generated $OutputPath"
