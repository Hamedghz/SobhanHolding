<?php

$root = dirname(__DIR__);
$dsn = getenv('SOBHAN_TEST_DSN') ?: '';
$user = getenv('SOBHAN_TEST_DB_USER') ?: 'root';
$password = getenv('SOBHAN_TEST_DB_PASSWORD') ?: '';
if ($dsn === '') {
    fwrite(STDERR, "SOBHAN_TEST_DSN is required.\n");
    exit(2);
}

$pdo = new PDO($dsn, $user, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
$sql = (string)file_get_contents($root . '/database/schema.sql');
$statements = preg_split('/;\s*(?:\r?\n|$)/', $sql, -1, PREG_SPLIT_NO_EMPTY) ?: [];
foreach ($statements as $statement) {
    $statement = preg_replace('/^(?:\s*--[^\r\n]*(?:\r?\n|$))+/', '', trim($statement)) ?? $statement;
    if (trim($statement) !== '') $pdo->exec(trim($statement));
}

require_once $root . '/core/Database.php';
$reflection = new ReflectionClass(Database::class);
$pdoProperty = $reflection->getProperty('pdo');
$pdoProperty->setValue(null, $pdo);
$migratedProperty = $reflection->getProperty('migrated');
$migratedProperty->setValue(null, true);
if (!function_exists('setting')) {
    function setting(string $key, string $default = ''): string
    {
        $row = Database::fetch('SELECT setting_value FROM site_settings WHERE setting_key=? LIMIT 1', [$key]);
        return $row ? (string)$row['setting_value'] : $default;
    }
}

require_once $root . '/core/ReportingViewsModule.php';
require_once $root . '/core/FormulaModule.php';
require_once $root . '/lib/FormulaRepository.php';
require_once $root . '/services/CanonicalSalesDashboardService.php';
ReportingViewsModule::repair($pdo);
FormulaModule::repair($pdo);

$pdo->exec(
    "INSERT INTO users(id,name,email,username,employee_no,password_hash,role,status,role_key,access_scope)
     VALUES
       (8101,'مدیر فروش تست','canonical-manager@example.test','canonical-manager','M-8101','test','manager','active','SALES_MANAGER','self'),
       (8102,'ویزیتور مجاز','canonical-visitor@example.test','canonical-visitor','V-8102','test','employee','active','VISITOR','self'),
       (8103,'ویزیتور خارج از دامنه','canonical-outsider@example.test','canonical-outsider','V-8103','test','employee','active','VISITOR','self')
     ON DUPLICATE KEY UPDATE name=VALUES(name),employee_no=VALUES(employee_no),status='active'"
);
$pdo->exec('UPDATE users SET parent_user_id=8101,organization_manager_id=8101,sales_line="A" WHERE id=8102');
$pdo->exec('UPDATE users SET parent_user_id=NULL,organization_manager_id=NULL,sales_line="B" WHERE id=8103');
$pdo->exec(
    "INSERT INTO sales_lines(id,code,title,manager_user_id,active)
     VALUES (8201,'A','لاین A',8101,1),(8202,'B','لاین B',NULL,1)
     ON DUPLICATE KEY UPDATE title=VALUES(title),manager_user_id=VALUES(manager_user_id),active=1"
);
$pdo->exec(
    "INSERT INTO system_periods(id,period_key,title,period_type,start_date,end_date,is_current,is_active)
     VALUES (8301,'1405-05','مرداد ۱۴۰۵','monthly','2026-07-23','2026-08-22',1,1)
     ON DUPLICATE KEY UPDATE title=VALUES(title),start_date=VALUES(start_date),end_date=VALUES(end_date),is_active=1"
);
$pdo->exec(
    "INSERT INTO sales_import_batches(id,source_type,source_module,file_name,file_hash,period_key,status,pipeline_status,is_active_reference)
     VALUES
       (8401,'excel','sales_aggregate','active.xlsx','canonical-active','1405-05','committed','committed',1),
       (8402,'excel','sales_aggregate','inactive.xlsx','canonical-inactive','1405-05','committed','committed',0),
       (8403,'excel','sales_targets','targets.xlsx','canonical-targets','1405-05','committed','committed',1)
     ON DUPLICATE KEY UPDATE status=VALUES(status),pipeline_status=VALUES(pipeline_status),is_active_reference=VALUES(is_active_reference)"
);
$sales = $pdo->prepare(
    'INSERT INTO sales_aggregate_rows
       (import_batch_id,source_unique_key,invoice_number,invoice_date,period_key,customer_code,customer_name,
        product_code,product_name,brand_code,brand_name,visitor_code,visitor_name,sales_manager_code,sales_manager_name,
        line_code,line_name,total_qty,gross_amount,discount_total,net_amount,return_quantity,return_amount)
     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
     ON DUPLICATE KEY UPDATE import_batch_id=VALUES(import_batch_id),net_amount=VALUES(net_amount),total_qty=VALUES(total_qty)'
);
$sales->execute([8401,'canonical-active-row','INV-A','2026-08-01','1405-05','C-1','مشتری یک','P-1','کالای یک','BR-1','برند یک','V-8102','ویزیتور مجاز','M-8101','مدیر فروش تست','A','لاین A',50,550,50,500,0,0]);
$sales->execute([8402,'canonical-inactive-row','INV-I','2026-08-01','1405-05','C-2','مشتری دو','P-1','کالای یک','BR-1','برند یک','V-8102','ویزیتور مجاز','M-8101','مدیر فروش تست','A','لاین A',999,9999,0,9999,0,0]);
$sales->execute([8401,'canonical-outsider-row','INV-O','2026-08-01','1405-05','C-3','مشتری سه','P-2','کالای دو','BR-2','برند دو','V-8103','ویزیتور خارج از دامنه','','','B','لاین B',800,8000,0,8000,0,0]);
$target = $pdo->prepare(
    'INSERT INTO sales_targets
       (id,import_batch_id,source_unique_key,period_id,visitor_user_id,line_id,line_code,product_code,product_name,
        brand_code,brand_name,visitor_code,target_quantity,target_amount,allocation_percent,active,source_type)
     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1,"import")
     ON DUPLICATE KEY UPDATE import_batch_id=VALUES(import_batch_id),target_quantity=VALUES(target_quantity),target_amount=VALUES(target_amount),active=1'
);
$target->execute([8501,8403,'canonical-target-row',8301,8102,8201,'A','P-1','کالای یک','BR-1','برند یک','V-8102',100,1000,100]);
$target->execute([8502,8403,'canonical-outsider-target',8301,8103,8202,'B','P-2','کالای دو','BR-2','برند دو','V-8103',100,1000,100]);

$formulaInput = [
    'formula_key' => 'manager_commission',
    'title' => 'پورسانت داشبورد canonical',
    'category_key' => 'commission',
    'data_source_key' => 'sample_input',
    'metric_key' => 'net_amount',
    'aggregation_key' => 'SUM',
    'operator_key' => '>=',
    'condition_value' => '0',
    'result_type' => 'percent_of_metric',
    'result_value' => '10',
    'priority' => 1,
    'effective_from' => '1405/01/01',
    'effective_to' => '1405/12/29',
    'active' => 1,
];
$existingFormula = Database::fetch('SELECT id FROM formula_definitions WHERE formula_key=?', ['manager_commission']);
$versionId = FormulaRepository::saveDraft(
    FormulaEngine::normalizeBuilderInput($formulaInput),
    8101,
    (int)($existingFormula['id'] ?? 0) ?: null
);
FormulaRepository::publish($versionId, 8101);

$actor = Database::fetch('SELECT * FROM users WHERE id=8101');
$snapshot = CanonicalSalesDashboardService::managerSnapshot($actor ?: []);
if (!$snapshot['has_data'] || $snapshot['source'] !== 'active_import_views_formulas') {
    throw new RuntimeException('Canonical dashboard source was not selected.');
}
if ($snapshot['period_key'] !== '1405-05' || count($snapshot['commission']) !== 1 || count($snapshot['lines']) !== 1) {
    throw new RuntimeException('Period or organization scope is incorrect.');
}
$visitor = $snapshot['commission'][0];
if ($visitor['visitor_name'] !== 'ویزیتور مجاز'
    || (float)$visitor['sales_amount'] !== 500.0
    || (float)$visitor['target_amount'] !== 1000.0
    || (float)$visitor['achievement_percent'] !== 50.0) {
    throw new RuntimeException('Active import/View calculation is incorrect: ' . json_encode($visitor, JSON_UNESCAPED_UNICODE));
}
if ((float)$visitor['final_commission'] !== 50.0) {
    throw new RuntimeException('Published FormulaRuntime result was not applied to the dashboard.');
}
if ((float)$snapshot['lines'][0]['line_sales_amount'] !== 500.0) {
    throw new RuntimeException('Inactive batch or unauthorized line leaked into manager totals.');
}
if (count($snapshot['brands']) !== 1 || (int)$snapshot['brands'][0]['achieved_brand_count'] !== 1) {
    throw new RuntimeException('Canonical brand target calculation is incorrect.');
}

echo "Canonical manager dashboard integration: PASS\n";
