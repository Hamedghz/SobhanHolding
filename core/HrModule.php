<?php
require_once __DIR__ . '/Database.php';

class HrModule
{
    public const DISCLAIMER = 'این آزمون صرفاً برای شناخت سازمانی، توسعه منابع انسانی و بهبود چیدمان نقش‌ها استفاده می‌شود و تشخیص پزشکی، روان‌شناختی یا بالینی محسوب نمی‌شود.';

    public static function repair(PDO $pdo): void
    {
        foreach (self::schema() as $sql) $pdo->exec($sql);
        foreach ([
            'department' => 'VARCHAR(150) NULL', 'role_key' => 'VARCHAR(100) NULL', 'sales_line' => 'VARCHAR(50) NULL',
            'supervisor_id' => 'INT UNSIGNED NULL', 'organization_manager_id' => 'INT UNSIGNED NULL',
        ] as $column => $definition) {
            if (!Database::columnExists('users', $column)) $pdo->exec("ALTER TABLE users ADD `{$column}` {$definition}");
        }
        self::repairAssessmentSchema($pdo);
    }

    public static function schema(): array
    {
        $engine=' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        return [
            "CREATE TABLE IF NOT EXISTS hr_kpi_templates (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,title VARCHAR(190) NOT NULL,code VARCHAR(100) NOT NULL UNIQUE,category VARCHAR(100) NULL,role_key VARCHAR(100) NULL,department VARCHAR(150) NULL,description TEXT NULL,active TINYINT(1) NOT NULL DEFAULT 1,sort_order INT NOT NULL DEFAULT 0,created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,INDEX idx_hr_kpi_templates_active(active)){$engine}",
            "CREATE TABLE IF NOT EXISTS hr_kpi_criteria (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,template_id INT UNSIGNED NOT NULL,criteria_text TEXT NOT NULL,criteria_hash CHAR(64) NOT NULL,category VARCHAR(100) NULL,weight DECIMAL(8,2) NOT NULL DEFAULT 1,max_score DECIMAL(8,2) NOT NULL DEFAULT 10,sort_order INT NOT NULL DEFAULT 0,active TINYINT(1) NOT NULL DEFAULT 1,created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,UNIQUE KEY uq_hr_kpi_criterion(template_id,criteria_hash),CONSTRAINT fk_hr_kpi_criteria_template FOREIGN KEY(template_id) REFERENCES hr_kpi_templates(id) ON DELETE CASCADE){$engine}",
            "CREATE TABLE IF NOT EXISTS hr_kpi_periods (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,title VARCHAR(190) NOT NULL,code VARCHAR(100) NOT NULL UNIQUE,start_date DATE NULL,end_date DATE NULL,period_type VARCHAR(50) NOT NULL DEFAULT 'custom',active TINYINT(1) NOT NULL DEFAULT 1,sort_order INT NOT NULL DEFAULT 0,created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP){$engine}",
            "CREATE TABLE IF NOT EXISTS hr_kpi_employee_assignments (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,employee_id INT UNSIGNED NOT NULL,template_id INT UNSIGNED NOT NULL,department VARCHAR(150) NULL,role_key VARCHAR(100) NULL,sales_line VARCHAR(50) NULL,supervisor_id INT UNSIGNED NULL,manager_id INT UNSIGNED NULL,assigned_by INT UNSIGNED NULL,active TINYINT(1) NOT NULL DEFAULT 1,created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,UNIQUE KEY uq_hr_kpi_assignment(employee_id,template_id),INDEX idx_hr_kpi_assignment_team(supervisor_id,manager_id),CONSTRAINT fk_hr_kpi_assignment_employee FOREIGN KEY(employee_id) REFERENCES users(id) ON DELETE CASCADE,CONSTRAINT fk_hr_kpi_assignment_template FOREIGN KEY(template_id) REFERENCES hr_kpi_templates(id) ON DELETE CASCADE){$engine}",
            "CREATE TABLE IF NOT EXISTS hr_kpi_scores (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,employee_id INT UNSIGNED NOT NULL,template_id INT UNSIGNED NOT NULL,criteria_id INT UNSIGNED NOT NULL,period_id INT UNSIGNED NOT NULL,score DECIMAL(8,2) NOT NULL DEFAULT 0,notes TEXT NULL,scored_by INT UNSIGNED NULL,scored_at DATETIME NULL,created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,UNIQUE KEY uq_hr_kpi_score(employee_id,criteria_id,period_id),INDEX idx_hr_kpi_score_period(period_id),CONSTRAINT fk_hr_kpi_score_employee FOREIGN KEY(employee_id) REFERENCES users(id) ON DELETE CASCADE,CONSTRAINT fk_hr_kpi_score_template FOREIGN KEY(template_id) REFERENCES hr_kpi_templates(id) ON DELETE CASCADE,CONSTRAINT fk_hr_kpi_score_criteria FOREIGN KEY(criteria_id) REFERENCES hr_kpi_criteria(id) ON DELETE CASCADE,CONSTRAINT fk_hr_kpi_score_period FOREIGN KEY(period_id) REFERENCES hr_kpi_periods(id) ON DELETE CASCADE){$engine}",
            "CREATE TABLE IF NOT EXISTS hr_kpi_score_logs (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,score_id INT UNSIGNED NULL,action VARCHAR(30) NOT NULL,old_value TEXT NULL,new_value TEXT NULL,performed_by INT UNSIGNED NULL,created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,INDEX idx_hr_kpi_log_score(score_id),CONSTRAINT fk_hr_kpi_log_score FOREIGN KEY(score_id) REFERENCES hr_kpi_scores(id) ON DELETE SET NULL){$engine}",
            "CREATE TABLE IF NOT EXISTS hr_kpi_forms (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,title VARCHAR(190) NOT NULL,code VARCHAR(100) NOT NULL UNIQUE,source_file VARCHAR(190) NOT NULL,content_hash CHAR(64) NOT NULL UNIQUE,description TEXT NULL,active TINYINT(1) NOT NULL DEFAULT 1,created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP){$engine}",
            "CREATE TABLE IF NOT EXISTS hr_kpi_form_templates (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,form_id INT UNSIGNED NOT NULL,template_id INT UNSIGNED NOT NULL,sort_order INT NOT NULL DEFAULT 0,UNIQUE KEY uq_hr_form_template(form_id,template_id),CONSTRAINT fk_hr_form_template_form FOREIGN KEY(form_id) REFERENCES hr_kpi_forms(id) ON DELETE CASCADE,CONSTRAINT fk_hr_form_template_template FOREIGN KEY(template_id) REFERENCES hr_kpi_templates(id) ON DELETE CASCADE){$engine}",
            "CREATE TABLE IF NOT EXISTS hr_assessment_tests (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,title VARCHAR(190) NOT NULL,code VARCHAR(100) NOT NULL UNIQUE,category VARCHAR(100) NULL,description TEXT NULL,age_range VARCHAR(80) NULL,scoring_type VARCHAR(50) NOT NULL DEFAULT 'dimensions',time_limit_minutes INT NOT NULL DEFAULT 20,active TINYINT(1) NOT NULL DEFAULT 1,sort_order INT NOT NULL DEFAULT 0,seeded TINYINT(1) NOT NULL DEFAULT 0,seed_key VARCHAR(100) NULL,seed_version VARCHAR(50) NULL,is_seeded TINYINT(1) NOT NULL DEFAULT 0,is_archived TINYINT(1) NOT NULL DEFAULT 0,created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,INDEX idx_hr_assessment_seed(seed_key,seed_version),INDEX idx_hr_assessment_active(active,is_archived)){$engine}",
            "CREATE TABLE IF NOT EXISTS hr_assessment_dimensions (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,test_id INT UNSIGNED NOT NULL,dimension_key VARCHAR(100) NOT NULL,dimension_label VARCHAR(190) NOT NULL,description TEXT NULL,sort_order INT NOT NULL DEFAULT 0,seed_key VARCHAR(100) NULL,seed_version VARCHAR(50) NULL,is_seeded TINYINT(1) NOT NULL DEFAULT 0,created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,UNIQUE KEY uq_hr_dimension(test_id,dimension_key),CONSTRAINT fk_hr_dimension_test FOREIGN KEY(test_id) REFERENCES hr_assessment_tests(id) ON DELETE CASCADE){$engine}",
            "CREATE TABLE IF NOT EXISTS hr_assessment_questions (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,test_id INT UNSIGNED NOT NULL,question_code VARCHAR(100) NULL,question_text TEXT NOT NULL,question_hash CHAR(64) NOT NULL,answer_type VARCHAR(30) NOT NULL,options_json TEXT NULL,correct_answer_json TEXT NULL,dimension_key VARCHAR(100) NULL,secondary_dimension_key VARCHAR(100) NULL,weight DECIMAL(8,2) NOT NULL DEFAULT 1,reverse_score TINYINT(1) NOT NULL DEFAULT 0,correct_answer VARCHAR(190) NULL,admin_note TEXT NULL,required TINYINT(1) NOT NULL DEFAULT 1,sort_order INT NOT NULL DEFAULT 0,active TINYINT(1) NOT NULL DEFAULT 1,seeded TINYINT(1) NOT NULL DEFAULT 0,seed_key VARCHAR(100) NULL,seed_version VARCHAR(50) NULL,is_seeded TINYINT(1) NOT NULL DEFAULT 0,created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,UNIQUE KEY uq_hr_question(test_id,question_hash),INDEX idx_hr_question_test(test_id),CONSTRAINT fk_hr_question_test FOREIGN KEY(test_id) REFERENCES hr_assessment_tests(id) ON DELETE CASCADE){$engine}",
            "CREATE TABLE IF NOT EXISTS hr_assessment_assignments (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,test_id INT UNSIGNED NOT NULL,employee_id INT UNSIGNED NOT NULL,department VARCHAR(150) NULL,role_key VARCHAR(100) NULL,sales_line VARCHAR(50) NULL,supervisor_id INT UNSIGNED NULL,manager_id INT UNSIGNED NULL,assigned_by INT UNSIGNED NULL,assignment_scope VARCHAR(40) NOT NULL DEFAULT 'employee',period_key VARCHAR(100) NULL,due_date DATE NULL,allow_retake TINYINT(1) NOT NULL DEFAULT 0,status VARCHAR(30) NOT NULL DEFAULT 'assigned',notes TEXT NULL,created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,INDEX idx_hr_assignment_employee(employee_id,status),INDEX idx_hr_assignment_team(supervisor_id,manager_id),CONSTRAINT fk_hr_assignment_test FOREIGN KEY(test_id) REFERENCES hr_assessment_tests(id) ON DELETE CASCADE,CONSTRAINT fk_hr_assignment_employee FOREIGN KEY(employee_id) REFERENCES users(id) ON DELETE CASCADE){$engine}",
            "CREATE TABLE IF NOT EXISTS hr_assessment_responses (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,assignment_id INT UNSIGNED NOT NULL,employee_id INT UNSIGNED NOT NULL,test_id INT UNSIGNED NOT NULL,answers_json LONGTEXT NULL,progress_json TEXT NULL,status VARCHAR(30) NOT NULL DEFAULT 'in_progress',started_at DATETIME NULL,submitted_at DATETIME NULL,created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,UNIQUE KEY uq_hr_response_assignment(assignment_id),CONSTRAINT fk_hr_response_assignment FOREIGN KEY(assignment_id) REFERENCES hr_assessment_assignments(id) ON DELETE CASCADE,CONSTRAINT fk_hr_response_employee FOREIGN KEY(employee_id) REFERENCES users(id) ON DELETE CASCADE,CONSTRAINT fk_hr_response_test FOREIGN KEY(test_id) REFERENCES hr_assessment_tests(id) ON DELETE CASCADE){$engine}",
            "CREATE TABLE IF NOT EXISTS hr_assessment_results (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,assignment_id INT UNSIGNED NOT NULL,employee_id INT UNSIGNED NOT NULL,test_id INT UNSIGNED NOT NULL,raw_answers_json LONGTEXT NULL,calculated_scores_json LONGTEXT NULL,normalized_scores_json LONGTEXT NULL,final_result TEXT NULL,risk_level VARCHAR(40) NULL,profile_summary TEXT NULL,recommendation_text TEXT NULL,calculated_at DATETIME NULL,created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,INDEX idx_hr_result_employee(employee_id),INDEX idx_hr_result_test(test_id),CONSTRAINT fk_hr_result_assignment FOREIGN KEY(assignment_id) REFERENCES hr_assessment_assignments(id) ON DELETE CASCADE,CONSTRAINT fk_hr_result_employee FOREIGN KEY(employee_id) REFERENCES users(id) ON DELETE CASCADE,CONSTRAINT fk_hr_result_test FOREIGN KEY(test_id) REFERENCES hr_assessment_tests(id) ON DELETE CASCADE){$engine}",
            "CREATE TABLE IF NOT EXISTS hr_assessment_result_logs (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,result_id INT UNSIGNED NULL,action VARCHAR(30) NOT NULL,performed_by INT UNSIGNED NULL,old_value_json LONGTEXT NULL,new_value_json LONGTEXT NULL,created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,CONSTRAINT fk_hr_result_log FOREIGN KEY(result_id) REFERENCES hr_assessment_results(id) ON DELETE SET NULL){$engine}",
            "CREATE TABLE IF NOT EXISTS hr_assessment_seed_versions (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,seed_key VARCHAR(100) NOT NULL,version VARCHAR(50) NOT NULL,source_title VARCHAR(255) NULL,source_file VARCHAR(255) NULL,applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,applied_by BIGINT NULL,notes TEXT NULL,UNIQUE KEY uq_seed_version(seed_key,version)){$engine}",
            "CREATE TABLE IF NOT EXISTS hr_assessment_packages (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,title VARCHAR(190) NOT NULL,code VARCHAR(100) NOT NULL UNIQUE,role_key VARCHAR(100) NULL,description TEXT NULL,active TINYINT(1) NOT NULL DEFAULT 1,seed_key VARCHAR(100) NULL,seed_version VARCHAR(50) NULL,is_seeded TINYINT(1) NOT NULL DEFAULT 0,created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP){$engine}",
            "CREATE TABLE IF NOT EXISTS hr_assessment_package_tests (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,package_id INT UNSIGNED NOT NULL,test_id INT UNSIGNED NOT NULL,sort_order INT NOT NULL DEFAULT 0,UNIQUE KEY uq_hr_package_test(package_id,test_id),CONSTRAINT fk_hr_package_test_package FOREIGN KEY(package_id) REFERENCES hr_assessment_packages(id) ON DELETE CASCADE,CONSTRAINT fk_hr_package_test_test FOREIGN KEY(test_id) REFERENCES hr_assessment_tests(id) ON DELETE CASCADE){$engine}",
            "CREATE TABLE IF NOT EXISTS ai_reporting_sources (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,source_name VARCHAR(190) NOT NULL,source_type VARCHAR(50) NOT NULL DEFAULT 'view',connection_label VARCHAR(190) NULL,view_name VARCHAR(190) NOT NULL UNIQUE,description TEXT NULL,active TINYINT(1) NOT NULL DEFAULT 1,created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP){$engine}",
            "CREATE TABLE IF NOT EXISTS ai_insight_requests (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,requested_by INT UNSIGNED NULL,prompt TEXT NULL,source_context_json LONGTEXT NULL,result_json LONGTEXT NULL,status VARCHAR(30) NOT NULL DEFAULT 'pending',created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,INDEX idx_ai_insight_requested_by(requested_by),CONSTRAINT fk_ai_insight_user FOREIGN KEY(requested_by) REFERENCES users(id) ON DELETE SET NULL){$engine}",
        ];
    }

    public static function seed(PDO $pdo, array $groups=['kpi','assessment','ai']): array
    {
        $counts=['templates'=>0,'criteria'=>0,'periods'=>0,'forms'=>0,'form_links'=>0,'tests'=>0,'questions'=>0,'sources'=>0];
        if(in_array('kpi',$groups,true)){
        $periods=['ORDIBEHESHT'=>'اردیبهشت','KHORDAD_H1'=>'نیمه اول خرداد','KHORDAD_H2'=>'نیمه دوم خرداد','TIR_H1'=>'نیمه اول تیر','TIR_H2'=>'نیمه دوم تیر','MORDAD_H1'=>'نیمه اول مرداد','MORDAD_H2'=>'نیمه دوم مرداد'];
        $stmt=$pdo->prepare('INSERT IGNORE INTO hr_kpi_periods(title,code,period_type,sort_order,active,created_at,updated_at) VALUES (?,?,"custom",?,1,NOW(),NOW())');$i=10;foreach($periods as $code=>$title){$stmt->execute([$title,$code,$i]);$counts['periods']+=$stmt->rowCount();$i+=10;}
        $templateStmt=$pdo->prepare('INSERT IGNORE INTO hr_kpi_templates(title,code,category,role_key,active,sort_order,created_at,updated_at) VALUES (?,?,"organizational",?,1,?,NOW(),NOW())');
        $criterionStmt=$pdo->prepare('INSERT IGNORE INTO hr_kpi_criteria(template_id,criteria_text,criteria_hash,weight,max_score,sort_order,active,created_at,updated_at) VALUES (?,?,?,1,10,?,1,NOW(),NOW())');
        $sort=10;foreach(self::kpiTemplates() as $code=>$data){$templateStmt->execute([$data[0],$code,$code,$sort]);$counts['templates']+=$templateStmt->rowCount();$templateId=(int)$pdo->query('SELECT id FROM hr_kpi_templates WHERE code='.$pdo->quote($code))->fetchColumn();$cSort=10;foreach($data[1] as $text){$criterionStmt->execute([$templateId,$text,hash('sha256',self::normalize($text)),$cSort]);$counts['criteria']+=$criterionStmt->rowCount();$cSort+=10;}$sort+=10;}
        self::seedKpiForms($pdo,$counts);
        }
        if(in_array('assessment',$groups,true)){
        $assessmentCounts = self::seedSobhanAssessmentBattery($pdo);
        foreach ($assessmentCounts as $key => $value) $counts[$key] = ($counts[$key] ?? 0) + (int)$value;
        }
        if(in_array('ai',$groups,true)){
        $sourceStmt=$pdo->prepare('INSERT IGNORE INTO ai_reporting_sources(source_name,source_type,connection_label,view_name,description,active,created_at,updated_at) VALUES (?,"view","SQL Server Reporting",?,?,1,NOW(),NOW())');foreach(self::reportingViews() as $view=>$description){$sourceStmt->execute([$view,$view,$description]);$counts['sources']+=$sourceStmt->rowCount();}
        }
        return $counts;
    }

    private static function seedKpiForms(PDO $pdo,array &$counts): void
    {
        $forms=[
            'KPI_MANAGERS'=>['فرم ارزیابی KPI مدیران و مسئولین','maneger.xlsx',['OFFICE_MANAGER','GENERAL_MANAGEMENT','SALES_MANAGER','FINANCE_MANAGER','WAREHOUSE_MANAGER']],
            'KPI_SALES_RIALI'=>['فرم ارزیابی KPI تیم فروش ریالی','kpi riali.xlsx',['SALES_SUPERVISOR','VISITOR']],
            'KPI_FINANCE_ADMIN'=>['فرم ارزیابی KPI مالی، اداری، بازرگانی و IT','kpi mali.xlsx',['COMMERCIAL','TAX_INSURANCE','TREASURY','SALES_ACCOUNTING','IT','COLLECTOR']],
            'KPI_SALES_DEHBASHI'=>['فرم ارزیابی KPI تیم فروش دهباشی','dhbashi kpi.xlsx',['SALES_SUPERVISOR','VISITOR']],
            'KPI_WAREHOUSE'=>['فرم ارزیابی KPI انبار، موزع و راننده','anbar kpi.xlsx',['WAREHOUSE_SUPERVISOR','WAREHOUSE_STAFF','DISTRIBUTOR','DRIVER']],
        ];
        $formStmt=$pdo->prepare('INSERT IGNORE INTO hr_kpi_forms(title,code,source_file,content_hash,description,active,created_at,updated_at) VALUES (?,?,?,?,?,1,NOW(),NOW())');
        $linkStmt=$pdo->prepare('INSERT IGNORE INTO hr_kpi_form_templates(form_id,template_id,sort_order) VALUES (?,?,?)');
        foreach($forms as $code=>[$title,$file,$templates]){$hash=hash('sha256',$code.'|'.implode('|',$templates));$formStmt->execute([$title,$code,$file,$hash,'فرم پایگاه‌داده‌ای استخراج‌شده از ساختار فایل KPI']);$counts['forms']+=$formStmt->rowCount();$formId=(int)$pdo->query('SELECT id FROM hr_kpi_forms WHERE code='.$pdo->quote($code))->fetchColumn();$sort=10;foreach($templates as $templateCode){$templateId=(int)$pdo->query('SELECT id FROM hr_kpi_templates WHERE code='.$pdo->quote($templateCode))->fetchColumn();if($templateId){$linkStmt->execute([$formId,$templateId,$sort]);$counts['form_links']+=$linkStmt->rowCount();}$sort+=10;}}
    }

    private static function normalize(string $text): string { return mb_strtolower(preg_replace('/\s+/u',' ',trim($text)),'UTF-8'); }

    private static function repairAssessmentSchema(PDO $pdo): void
    {
        foreach ([
            'hr_assessment_tests' => [
                'seed_key' => 'VARCHAR(100) NULL',
                'seed_version' => 'VARCHAR(50) NULL',
                'is_seeded' => 'TINYINT(1) NOT NULL DEFAULT 0',
                'is_archived' => 'TINYINT(1) NOT NULL DEFAULT 0',
            ],
            'hr_assessment_dimensions' => [
                'seed_key' => 'VARCHAR(100) NULL',
                'seed_version' => 'VARCHAR(50) NULL',
                'is_seeded' => 'TINYINT(1) NOT NULL DEFAULT 0',
            ],
            'hr_assessment_questions' => [
                'question_code' => 'VARCHAR(100) NULL',
                'correct_answer_json' => 'TEXT NULL',
                'admin_note' => 'TEXT NULL',
                'seed_key' => 'VARCHAR(100) NULL',
                'seed_version' => 'VARCHAR(50) NULL',
                'is_seeded' => 'TINYINT(1) NOT NULL DEFAULT 0',
            ],
            'hr_assessment_packages' => [
                'seed_key' => 'VARCHAR(100) NULL',
                'seed_version' => 'VARCHAR(50) NULL',
                'is_seeded' => 'TINYINT(1) NOT NULL DEFAULT 0',
            ],
        ] as $table => $columns) {
            if (!Database::tableExists($table)) continue;
            foreach ($columns as $column => $definition) {
                if (!Database::columnExists($table, $column)) $pdo->exec("ALTER TABLE {$table} ADD `{$column}` {$definition}");
            }
        }
        if (Database::tableExists('hr_assessment_questions') && !self::indexExists($pdo, 'hr_assessment_questions', 'uq_hr_question_code')) {
            $pdo->exec('ALTER TABLE hr_assessment_questions ADD UNIQUE KEY uq_hr_question_code(test_id,question_code)');
        }
    }

    private static function indexExists(PDO $pdo, string $table, string $indexName): bool
    {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?');
        $stmt->execute([$table, $indexName]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public static function sobhanAssessmentData(): array
    {
        $path = __DIR__ . '/../install/data/sobhan_assessment_20_battery.json';
        if (!is_file($path)) throw new RuntimeException('sobhan_assessment_seed_missing');
        $decoded = json_decode((string)file_get_contents($path), true);
        if (!is_array($decoded) || !isset($decoded['meta'], $decoded['tests'])) throw new RuntimeException('sobhan_assessment_seed_invalid');
        return $decoded;
    }

    private static function seedSobhanAssessmentBattery(PDO $pdo, ?int $userId = null): array
    {
        $data = self::sobhanAssessmentData();
        $meta = $data['meta'];
        $seedKey = (string)$meta['seed_key'];
        $seedVersion = (string)$meta['seed_version'];
        $counts = ['tests' => 0, 'questions' => 0, 'updated' => 0, 'packages' => 0, 'package_links' => 0, 'archived' => 0];
        $counts['archived'] = self::archivePreviousSeededAssessmentData($pdo, $seedKey, $seedVersion);

        $testStmt = $pdo->prepare('INSERT INTO hr_assessment_tests(title,code,category,description,scoring_type,time_limit_minutes,active,sort_order,seeded,seed_key,seed_version,is_seeded,is_archived,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,1,?,?,1,0,NOW(),NOW()) ON DUPLICATE KEY UPDATE title=VALUES(title),category=VALUES(category),description=VALUES(description),scoring_type=VALUES(scoring_type),time_limit_minutes=VALUES(time_limit_minutes),active=VALUES(active),sort_order=VALUES(sort_order),seeded=1,seed_key=VALUES(seed_key),seed_version=VALUES(seed_version),is_seeded=1,is_archived=0,updated_at=NOW()');
        $dimStmt = $pdo->prepare('INSERT INTO hr_assessment_dimensions(test_id,dimension_key,dimension_label,description,sort_order,seed_key,seed_version,is_seeded,created_at,updated_at) VALUES (?,?,?,?,?,?,?,1,NOW(),NOW()) ON DUPLICATE KEY UPDATE dimension_label=VALUES(dimension_label),description=VALUES(description),sort_order=VALUES(sort_order),seed_key=VALUES(seed_key),seed_version=VALUES(seed_version),is_seeded=1,updated_at=NOW()');
        $questionStmt = $pdo->prepare('INSERT INTO hr_assessment_questions(test_id,question_code,question_text,question_hash,answer_type,options_json,correct_answer_json,dimension_key,secondary_dimension_key,weight,reverse_score,correct_answer,admin_note,required,sort_order,active,seeded,seed_key,seed_version,is_seeded,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1,?,?,1,NOW(),NOW()) ON DUPLICATE KEY UPDATE question_text=VALUES(question_text),question_hash=VALUES(question_hash),answer_type=VALUES(answer_type),options_json=VALUES(options_json),correct_answer_json=VALUES(correct_answer_json),dimension_key=VALUES(dimension_key),secondary_dimension_key=VALUES(secondary_dimension_key),weight=VALUES(weight),reverse_score=VALUES(reverse_score),correct_answer=VALUES(correct_answer),admin_note=VALUES(admin_note),required=VALUES(required),sort_order=VALUES(sort_order),active=VALUES(active),seeded=1,seed_key=VALUES(seed_key),seed_version=VALUES(seed_version),is_seeded=1,updated_at=NOW()');
        $packageStmt = $pdo->prepare('INSERT INTO hr_assessment_packages(title,code,role_key,description,active,seed_key,seed_version,is_seeded,created_at,updated_at) VALUES (?,?,?,?,1,?,?,1,NOW(),NOW()) ON DUPLICATE KEY UPDATE title=VALUES(title),role_key=VALUES(role_key),description=VALUES(description),active=1,seed_key=VALUES(seed_key),seed_version=VALUES(seed_version),is_seeded=1,updated_at=NOW()');
        $packageTestStmt = $pdo->prepare('INSERT INTO hr_assessment_package_tests(package_id,test_id,sort_order) VALUES (?,?,?) ON DUPLICATE KEY UPDATE sort_order=VALUES(sort_order)');

        $testSort = 10;
        foreach ($data['tests'] as $test) {
            $description = self::DISCLAIMER;
            $testStmt->execute([
                $test['test_title'],
                $test['test_code'],
                $test['category'] ?? null,
                $description,
                $test['scoring_type'] ?? 'dimensions',
                (int)($test['time_limit_minutes'] ?? 20),
                1,
                $testSort,
                $seedKey,
                $seedVersion,
            ]);
            $testId = (int)$pdo->query('SELECT id FROM hr_assessment_tests WHERE code=' . $pdo->quote((string)$test['test_code']))->fetchColumn();
            $counts['tests']++;
            foreach ($test['dimensions'] as $dimension) {
                $dimStmt->execute([
                    $testId,
                    $dimension['dimension_key'],
                    $dimension['dimension_label'],
                    null,
                    (int)$dimension['sort_order'],
                    $seedKey,
                    $seedVersion,
                ]);
            }
            foreach ($test['questions'] as $question) {
                $questionStmt->execute([
                    $testId,
                    $question['question_code'],
                    $question['question_text'],
                    hash('sha256', self::normalize((string)$question['question_text'])),
                    $question['answer_type'],
                    $question['options_json'],
                    $question['correct_answer_json'],
                    $question['dimension_key'],
                    $question['secondary_dimension_key'],
                    (float)($question['weight'] ?? 1),
                    (int)($question['reverse_score'] ?? 0),
                    null,
                    $question['admin_note'] ?? null,
                    (int)($question['required'] ?? 1),
                    (int)($question['sort_order'] ?? 0),
                    (int)($question['active'] ?? 1),
                    $seedKey,
                    $seedVersion,
                ]);
                $counts['questions']++;
            }
            $testSort += 10;
        }

        foreach ($data['packages'] as $package) {
            $packageStmt->execute([
                $package['title'],
                $package['code'],
                $package['role_key'] ?? null,
                'بسته پیشنهادی نقش‌محور بانک ۲۰ آزمون سازمانی سبحان',
                $seedKey,
                $seedVersion,
            ]);
            $packageId = (int)$pdo->query('SELECT id FROM hr_assessment_packages WHERE code=' . $pdo->quote((string)$package['code']))->fetchColumn();
            $counts['packages']++;
            $sort = 10;
            foreach ($package['tests'] as $testCode) {
                $testId = (int)$pdo->query('SELECT id FROM hr_assessment_tests WHERE code=' . $pdo->quote((string)$testCode))->fetchColumn();
                if ($testId > 0) {
                    $packageTestStmt->execute([$packageId, $testId, $sort]);
                    $counts['package_links']++;
                }
                $sort += 10;
            }
        }

        $seedVersionStmt = $pdo->prepare('INSERT INTO hr_assessment_seed_versions(seed_key,version,source_title,source_file,applied_by,notes) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE source_title=VALUES(source_title),source_file=VALUES(source_file),applied_by=VALUES(applied_by),notes=VALUES(notes)');
        $seedVersionStmt->execute([$seedKey, $seedVersion, $meta['source_title'] ?? null, $meta['source_file'] ?? null, $userId, 'safe_hr_assessment_seed']);

        return $counts;
    }

    private static function archivePreviousSeededAssessmentData(PDO $pdo, string $seedKey, string $seedVersion): int
    {
        $stmt = $pdo->prepare('UPDATE hr_assessment_tests SET active=0,is_archived=1,updated_at=NOW() WHERE (COALESCE(is_seeded,0)=1 OR COALESCE(seeded,0)=1 OR seed_key IS NOT NULL) AND (seed_key <> ? OR seed_version <> ? OR seed_key IS NULL)');
        $stmt->execute([$seedKey, $seedVersion]);
        $pdo->prepare('UPDATE hr_assessment_questions q JOIN hr_assessment_tests t ON t.id=q.test_id SET q.active=0,q.updated_at=NOW() WHERE t.is_archived=1')->execute();
        $pdo->prepare('UPDATE hr_assessment_packages SET active=0,updated_at=NOW() WHERE (COALESCE(is_seeded,0)=1 OR seed_key IS NOT NULL) AND (seed_key <> ? OR seed_version <> ? OR seed_key IS NULL)')->execute([$seedKey, $seedVersion]);
        return $stmt->rowCount();
    }

    public static function kpiTemplates(): array
    {
        return [
            'OFFICE_MANAGER'=>['مسئول دفتر',['سرعت انجام کارهای روزانه','ارتباط موثر با واحدهای داخلی و مشتریان','پاسخ‌گویی مناسب به تلفن و مراجعه‌کننده','کار با آفیس و نرم‌افزارهای داخلی','تنظیم نامه‌ها و گزارش‌ها','هماهنگی جلسات و برنامه‌ها','حفظ اطلاعات محرمانه شرکت','ثبت دقیق و به‌موقع آفرها','ورود و خروج منظم']],
            'GENERAL_MANAGEMENT'=>['مدیریت',['برنامه‌ریزی و استراتژی مالی','تحلیل سودآوری','صورت سود و زیان','میزان تعامل با واحدهای دیگر','نظم در ورود و خروج']],
            'FINANCE_MANAGER'=>['مدیریت مالی',['نظارت بر ثبت صحیح اسناد حسابداری','تهیه صورت‌های مالی','نظارت بر عملکرد نیروهای واحد مالی','میزان تعامل با واحدهای دیگر','نظارت بر حساب‌های بانکی و مغایرت‌گیری روزانه حساب‌ها','برنامه‌ریزی پرداخت‌ها','مدیریت مطالبات و بدهی‌ها','جلوگیری از کمبود نقدینگی','مذاکره با بانک‌ها برای تسهیلات','نظم در ورود و خروج']],
            'SALES_MANAGER'=>['مدیر فروش',['میزان تحقق اهداف فروش کل شرکت','رشد فروش نسبت به دوره قبل','توانایی برنامه‌ریزی فروش ماهانه و اجرای آن','مدیریت تیم فروش، نظم، انگیزش و عملکرد تیم','پوشش مشتری','توانایی تحلیل بازار و پیشنهاد روش‌های افزایش فروش','نیروسازی','دقت در پیش‌بینی فروش','میزان تعامل با واحدهای دیگر','مهارت در گزارش‌دهی منظم و شفاف','نظم در ورود و خروج']],
            'WAREHOUSE_MANAGER'=>['مدیر انبار',['عدم اختلاف در میزان موجودی واقعی و ثبت‌شده در سیستم','کارایی انبار، سرعت و دقت در دریافت، جانمایی و ارسال کالا','کاهش ضایعات و خسارات','مدیریت نیروی انسانی، نظم، انگیزه و عملکرد تیم انبار','ایمنی محیط کار و رعایت استانداردهای ایمنی','بهینه‌سازی فضا و استفاده مناسب از فضای انبار','صحت بارگیری و دقت در ارسال کالای صحیح','میزان تعامل و همکاری با واحدهای دیگر','کنترل کالای نزدیک انقضا یا منقضی‌شده','نظم در ورود و خروج']],
            'SALES_SUPERVISOR'=>['سرپرست فروش',['میزان تحقق اهداف فروش منطقه یا تیم','نظارت بر عملکرد ویزیتورها، حضور، مسیر و نتیجه روزانه','توانایی آموزش و حمایت از ویزیتورها','حضور در بازار و تحلیل رقبا','افزایش پوشش مشتریان و ایجاد مشتری جدید در محدوده','نرخ مرجوعی و خطاهای سفارش‌گیری','نظم در ارسال گزارش‌های روزانه','حل مشکلات مشتریان و ویزیتورها و انتقال درست به مدیریت فروش','پیگیری وصول مطالبات','میزان تعامل با واحدهای دیگر','نظم در ورود و خروج']],
            'VISITOR'=>['ویزیتور',['میزان تحقق هدف فروش روزانه / ماهانه','تعداد ویزیت‌های موثر در روز','تعداد فاکتور ثبت‌شده','وصول مطالبات','درصد پوشش مسیر','کیفیت ارتباط با مشتری، رفتار، پیگیری و خوش‌قولی','دقت در ثبت سفارش‌ها و کاهش اشتباهات','جذب مشتری جدید','اجرای درست برنامه مسیر','نظم در ورود و خروج و ارسال لوکیشن‌ها','میزان تعامل با واحدهای دیگر','رعایت اصول ظاهری و بهداشتی شرکت']],
            'COMMERCIAL'=>['بازرگانی',['ارسال اوردر فروش و پیگیری سفارش تا رسیدن به شرکت','ثبت دقیق فاکتورهای خرید','اعلام کسری یا اضافات کالا به تامین‌کننده','برگزاری، مستندسازی و پیگیری جلسات و صورتجلسات','میزان تعامل با واحدهای دیگر','هماهنگی با مالی و فروش در تایید درخواست‌ها','بررسی موجودی انبار با انبارگردانی دوره‌ای و سیستمی','دقت در بررسی صورتحساب و مبلغ پرداختی و صدور چک به تامین‌کننده','ارسال گزارشات تامین‌کننده‌ها','نظم در ورود و خروج']],
            'TAX_INSURANCE'=>['مسئول مالیات و بیمه',['ارسال به‌موقع لیست بیمه و جلوگیری از جریمه','برنامه‌ریزی مالیاتی قانونی','نظارت و پیگیری اظهارنامه‌ها','رسیدگی به پرونده‌های مالیاتی','کاهش جرائم مالیاتی','نداشتن تاخیر در ارسال اظهارنامه‌ها','حفظ امنیت و محرمانگی اطلاعات مالی','پاسخگو و پیگیر بودن','میزان تعامل با واحدهای دیگر','نظم در ورود و خروج']],
            'TREASURY'=>['خزانه',['درصد خطای ثبت حواله و اسناد','درصد اسناد اصلاحی','میزان تعامل با بانک','پیگیری تکمیل موجودی چک‌های تامین‌کننده','حفظ امنیت و محرمانگی اطلاعات مالی','پاسخگو و پیگیر بودن','میزان تعامل با واحدهای دیگر','نظم در ورود و خروج']],
            'SALES_ACCOUNTING'=>['حسابداری فروش',['عدم مغایرت حساب‌های مشتریان','ثبت چک‌های دریافتی در سامانه','تعامل و همکاری مناسب با تیم فروش، انبار و مالی','داشتن کمترین درصد حساب باز در سیستم','درصد خطای ثبت حواله و اسناد','زمان متوسط ثبت وصولی‌ها و بستن حساب‌ها','درصد اسناد اصلاحی','پاسخگو و پیگیر بودن','نظم و بایگانی اسناد','میزان تعامل با واحدهای دیگر','درست بودن گزارشات و حساب‌های باز','نظم در ورود و خروج']],
            'IT'=>['فناوری اطلاعات',['امنیت پایه، بکاپ موفق ماهانه و بررسی روزانه دیتابیس','زمان پاسخگویی به درخواست‌ها','درصد سیستم‌های فعال بدون قطعی','در دسترس بودن نرم‌افزار حسابداری، نرم‌افزار فروش، اینترنت و سرور','کاهش خرابی‌های تکراری با حل ریشه‌ای','رضایت کاربران داخلی','آموزش پرسنل برای استفاده درست از سیستم‌ها','پیشنهاد و پیاده‌سازی تغییرات مفید در سیستم','میزان تعامل با واحدهای دیگر','نظم در ورود و خروج']],
            'COLLECTOR'=>['تحصیلدار',['دقت در وصول مطالبات و صحت مبلغ دریافتی','ثبت چک‌های دریافتی در سامانه','تعامل و همکاری مناسب با تیم فروش، انبار و مالی','داشتن کمترین درصد حساب باز در منطقه','برخورد مناسب با مشتریان','میزان تعامل با واحدهای دیگر','وصول به‌موقع در برابر مانده سررسیدشده','انضباط گزارش‌دهی و مستندسازی','نظم در ورود و خروج']],
            'WAREHOUSE_SUPERVISOR'=>['سرپرست انبار',self::warehouseCriteria()],
            'WAREHOUSE_STAFF'=>['نیروی انبار',self::warehouseCriteria()],
            'DISTRIBUTOR'=>['موزع',['زمان‌بندی تحویل کالا','رساندن کالا در زمان تعیین‌شده به مشتری','توزیع همه فاکتورهای تحویل‌شده در همان روز','رعایت ظاهر و رفتار مناسب با مشتری','برخورد حرفه‌ای و مودبانه','دقت در پر کردن خروجی‌ها','رعایت نظم در ورود و خروج','میزان تعامل و همکاری با واحدهای دیگر','جلب رضایت مشتری و دریافت بازخورد']],
            'DRIVER'=>['راننده',['رعایت اصول رانندگی ایمن و نداشتن تخلف یا تصادف','نگهداری صحیح از خودرو','گزارش به‌موقع خرابی‌ها','رعایت نظافت خودرو','صرفه‌جویی در هزینه‌های خودرو','رعایت نظم در ورود و خروج','میزان تعامل و همکاری با واحدهای دیگر','تکرار نداشتن تخلفات و تصادفات','رعایت ظاهر مناسب','برخورد مناسب با مشتری','تردد در مسیرهای تعیین‌شده']],
        ];
    }

    private static function warehouseCriteria(): array { return ['دقت در دریافت کالا و تطابق تعداد و نوع کالای دریافتی با حواله','دقت در جانمایی کالا در محل صحیح','سرعت عمل در جابه‌جایی کالا','دقت در بسته‌بندی و آماده‌سازی سفارش','کاهش ضایعات و آسیب به کالا','رعایت نظم و نظافت محیط کار','دقت در تطابق کالا با فاکتور','رعایت نکات ایمنی','همکاری با همکاران و سرپرست','میزان تعامل و همکاری با واحدهای دیگر','نظم در ورود و خروج']; }

    public static function testDefinitions(): array
    {
        return [
            'MBTI_ORG'=>['title'=>'MBTI سازمانی','category'=>'personality','minutes'=>18,'dimensions'=>['E'=>'برون‌گرایی','I'=>'درون‌گرایی','S'=>'واقع‌گرایی','N'=>'شهودی','T'=>'تحلیلی','F'=>'ارزش‌محور','J'=>'ساختارمند','P'=>'انعطاف‌پذیر'],'description'=>'شناخت ترجیحات رفتاری در محیط کار.'],
            'DISC_ORG'=>['title'=>'DISC سازمانی','category'=>'behavior','minutes'=>15,'dimensions'=>['D'=>'قاطعیت','I'=>'تأثیرگذاری','S'=>'ثبات','C'=>'وجدان‌کاری'],'description'=>'شناخت سبک رفتاری و ارتباطی.'],
            'MII_ORG'=>['title'=>'هوش‌های چندگانه سازمانی','category'=>'ability','minutes'=>20,'dimensions'=>['linguistic'=>'زبانی','logical_mathematical'=>'منطقی-ریاضی','spatial'=>'فضایی','bodily_kinesthetic'=>'بدنی-حرکتی','musical'=>'موسیقایی','interpersonal'=>'میان‌فردی','intrapersonal'=>'درون‌فردی','naturalistic'=>'طبیعت‌گرا'],'description'=>'شناسایی حوزه‌های توانمندی برجسته.'],
            'COMMITMENT_ORG'=>['title'=>'تعهد سازمانی','category'=>'engagement','minutes'=>12,'dimensions'=>['affective_commitment'=>'تعهد عاطفی','continuance_commitment'=>'تعهد استمراری','normative_commitment'=>'تعهد هنجاری'],'description'=>'بررسی الگوی پیوند و ماندگاری سازمانی.'],
            'JOB_SATISFACTION'=>['title'=>'رضایت شغلی','category'=>'wellbeing','minutes'=>15,'dimensions'=>['work_conditions'=>'شرایط کار','supervisor_relation'=>'رابطه با سرپرست','compensation_perception'=>'برداشت از جبران خدمت','growth_opportunity'=>'فرصت رشد','role_clarity'=>'شفافیت نقش','team_environment'=>'محیط تیمی'],'description'=>'سنجش تجربه کاری و رضایت سازمانی.'],
            'EQ_ORG'=>['title'=>'هوش هیجانی سازمانی','category'=>'development','minutes'=>18,'dimensions'=>['self_awareness'=>'خودآگاهی','empathy'=>'همدلی','emotional_control'=>'مدیریت هیجان','stress_handling'=>'مدیریت فشار','communication_balance'=>'تعادل ارتباطی'],'description'=>'شناسایی توانمندی‌های هیجانی در کار.'],
            'RAVEN_ABSTRACT_ORG'=>['title'=>'استدلال انتزاعی سازمانی','category'=>'reasoning','minutes'=>25,'dimensions'=>['pattern_recognition'=>'تشخیص الگو','logical_sequence'=>'توالی منطقی','abstract_reasoning'=>'استدلال انتزاعی','visual_logic'=>'منطق دیداری'],'description'=>'این نتیجه تخمین غیررسمی توانایی استدلال انتزاعی در محیط کاری است و آزمون رسمی IQ محسوب نمی‌شود.'],
            'BURNOUT_ORG'=>['title'=>'پایش فرسودگی شغلی','category'=>'wellbeing','minutes'=>12,'dimensions'=>['emotional_exhaustion'=>'خستگی هیجانی','motivation_decline'=>'کاهش انگیزه','work_fatigue'=>'خستگی کاری','reduced_effectiveness'=>'کاهش اثربخشی'],'description'=>'پایش غیربالینی فشار و خستگی کاری.'],
            'HOLLAND_ORG'=>['title'=>'علایق شغلی هالند سازمانی','category'=>'role_fit','minutes'=>18,'dimensions'=>['realistic'=>'عملگرا','investigative'=>'جست‌وجوگر','artistic'=>'هنری','social'=>'اجتماعی','enterprising'=>'متقاعدکننده','conventional'=>'قراردادی'],'description'=>'شناسایی علایق شغلی و تناسب نقش.'],
            'SPATIAL_ORG'=>['title'=>'استدلال فضایی سازمانی','category'=>'ability','minutes'=>20,'dimensions'=>['rotation_reasoning'=>'چرخش ذهنی','layout_understanding'=>'درک چیدمان','route_visualization'=>'تجسم مسیر','visual_memory'=>'حافظه دیداری'],'description'=>'سنجش غیررسمی توانمندی فضایی برای نقش‌های عملیاتی.'],
        ];
    }

    private static function questionsFor(string $code,array $test): array
    {
        $needed=['MBTI_ORG'=>3,'DISC_ORG'=>6,'MII_ORG'=>4,'COMMITMENT_ORG'=>6,'JOB_SATISFACTION'=>4,'EQ_ORG'=>5,'RAVEN_ABSTRACT_ORG'=>5,'BURNOUT_ORG'=>5,'HOLLAND_ORG'=>5,'SPATIAL_ORG'=>4][$code];
        $stems=['در کارهای روزانه از این توانمندی به‌خوبی استفاده می‌کنم','هنگام حل مسئله، این رویکرد برایم طبیعی است','در همکاری تیمی این ویژگی را نشان می‌دهم','در شرایط پرفشار می‌توانم این جنبه را حفظ کنم','برای رشد شغلی، تمرکز بر این حوزه را مفید می‌دانم','همکاران معمولاً این ویژگی را در عملکرد من می‌بینند'];
        $questions=[];$options=json_encode(['1'=>'کاملاً مخالفم','2'=>'مخالفم','3'=>'نظری ندارم','4'=>'موافقم','5'=>'کاملاً موافقم'],JSON_UNESCAPED_UNICODE);
        foreach($test['dimensions'] as $key=>$label)for($i=0;$i<$needed;$i++){
            if(in_array($code,['RAVEN_ABSTRACT_ORG','SPATIAL_ORG'],true)){$n=$i+2;$text="در الگوی کاری {$label}، توالی {$n}، ".($n*2)."، ".($n*3)."، عدد بعدی کدام است؟";$opts=[$n*4,$n*4+1,$n*5,$n*3+1];$questions[]=['text'=>$text,'type'=>'choice','options'=>json_encode(array_combine(array_map('strval',$opts),array_map('strval',$opts)),JSON_UNESCAPED_UNICODE),'dimension'=>$key,'correct'=>(string)($n*4)];}
            else{$questions[]=['text'=>$label.': '.$stems[$i],'type'=>'scale_1_5','options'=>$options,'dimension'=>$key,'reverse'=>($code==='BURNOUT_ORG'?0:0)];}
        }
        return $questions;
    }

    private static function seedPackages(PDO $pdo): void
    {
        $packages=['VISITOR_PACKAGE'=>['بسته ویزیتور','VISITOR',['DISC_ORG','EQ_ORG','HOLLAND_ORG','JOB_SATISFACTION','BURNOUT_ORG']],'SALES_SUPERVISOR_PACKAGE'=>['بسته سرپرست فروش','SALES_SUPERVISOR',['DISC_ORG','EQ_ORG','COMMITMENT_ORG','JOB_SATISFACTION','BURNOUT_ORG','MBTI_ORG']],'SALES_MANAGER_PACKAGE'=>['بسته مدیر فروش','SALES_MANAGER',['DISC_ORG','EQ_ORG','MBTI_ORG','COMMITMENT_ORG','BURNOUT_ORG','RAVEN_ABSTRACT_ORG']],'WAREHOUSE_PACKAGE'=>['بسته انبار','WAREHOUSE',['SPATIAL_ORG','HOLLAND_ORG','JOB_SATISFACTION','BURNOUT_ORG']],'FINANCE_ADMIN_PACKAGE'=>['بسته مالی و اداری','FINANCE_ADMIN',['DISC_ORG','HOLLAND_ORG','JOB_SATISFACTION','EQ_ORG','RAVEN_ABSTRACT_ORG']],'IT_PLANNING_PACKAGE'=>['بسته IT و برنامه‌ریزی','IT',['RAVEN_ABSTRACT_ORG','MII_ORG','HOLLAND_ORG','EQ_ORG','JOB_SATISFACTION']]];
        $pStmt=$pdo->prepare('INSERT IGNORE INTO hr_assessment_packages(title,code,role_key,active,created_at,updated_at) VALUES (?,?,?,1,NOW(),NOW())');$ptStmt=$pdo->prepare('INSERT IGNORE INTO hr_assessment_package_tests(package_id,test_id,sort_order) VALUES (?,?,?)');foreach($packages as $code=>[$title,$role,$tests]){$pStmt->execute([$title,$code,$role]);$pid=(int)$pdo->query('SELECT id FROM hr_assessment_packages WHERE code='.$pdo->quote($code))->fetchColumn();$sort=10;foreach($tests as $testCode){$tid=(int)$pdo->query('SELECT id FROM hr_assessment_tests WHERE code='.$pdo->quote($testCode))->fetchColumn();if($tid)$ptStmt->execute([$pid,$tid,$sort]);$sort+=10;}}
    }

    public static function reportingViews(): array
    {
        return ['vw_sales_daily'=>'فروش روزانه و ماهانه','vw_sales_by_customer'=>'فروش بر اساس مشتری','vw_sales_by_visitor'=>'فروش بر اساس ویزیتور','vw_sales_by_product'=>'فروش بر اساس کالا','vw_sales_by_brand'=>'فروش بر اساس برند','vw_targets'=>'تارگت‌ها و تحقق','vw_discounts'=>'خلاصه تخفیفات','vw_inventory_status'=>'وضعیت موجودی','vw_customer_last_purchase'=>'آخرین خرید مشتری','vw_receivables_summary'=>'خلاصه مطالبات'];
    }

    public static function accessibleEmployeeIds(array $user): array
    {
        require_once __DIR__ . '/../lib/OrgAccess.php';
        return OrgAccess::accessibleUserIds($user);
    }
}
