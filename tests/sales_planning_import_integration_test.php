<?php

$root = dirname(__DIR__);
require_once $root . '/services/UnifiedImportService.php';
require_once $root . '/tests/support/import_integration_bootstrap.php';
require_once $root . '/tests/support/xlsx_fixture.php';

$pdo = importIntegrationPdo($root);
seedImportIntegrationMappings($pdo);
$pdo->exec(
    "INSERT INTO org_roles(id,title,code,role_type,is_sales_role,hierarchy_level,active,sort_order,created_at,updated_at)
     VALUES (1,'ویزیتور','VISITOR','staff',1,3,1,1,NOW(),NOW())
     ON DUPLICATE KEY UPDATE title=VALUES(title),is_sales_role=1,active=1,updated_at=NOW()"
);
$pdo->exec(
    "INSERT INTO users(id,name,email,username,employee_no,kara_system_code,password_hash,role,role_key,status,access_scope,admin_panel_enabled,org_role_id)
     VALUES
       (1,'مدیر تست','planning-admin@example.test','planning-admin','ADMIN-1','K-ADMIN','test','super_admin','CEO','active','all',1,NULL),
       (2,'مدیر فروش','planning-manager@example.test','planning-manager','M-1','K-M-1','test','manager','SALES_MANAGER','active','team',1,NULL),
       (3,'سرپرست فروش','planning-supervisor@example.test','planning-supervisor','S-1','K-S-1','test','manager','SALES_SUPERVISOR','active','team',1,NULL),
       (4,'ویزیتور تست','planning-visitor@example.test','planning-visitor','V-1001','K-V-1001','test','employee','VISITOR','active','self',0,1)
     ON DUPLICATE KEY UPDATE name=VALUES(name),employee_no=VALUES(employee_no),kara_system_code=VALUES(kara_system_code),role_key=VALUES(role_key),status='active'"
);
$pdo->exec(
    "INSERT INTO sales_lines(id,code,title,manager_user_id,supervisor_user_id,active,sort_order,created_at,updated_at)
     VALUES (1,'A','لاین A',2,3,1,1,NOW(),NOW())
     ON DUPLICATE KEY UPDATE title=VALUES(title),manager_user_id=2,supervisor_user_id=3,active=1,updated_at=NOW()"
);
$pdo->exec(
    "UPDATE users SET sales_line='A',sales_line_id=1,supervisor_id=3,parent_user_id=3,organization_manager_id=2 WHERE id=4"
);
$pdo->exec(
    "INSERT INTO system_periods(id,period_key,title,period_type,start_date,end_date,jalali_year,jalali_month,is_current,is_system,is_active,sort_order,created_at,updated_at)
     VALUES (1,'1405-04','تیر ۱۴۰۵','monthly','2026-06-22','2026-07-22',1405,4,1,1,1,1,NOW(),NOW())
     ON DUPLICATE KEY UPDATE title=VALUES(title),start_date=VALUES(start_date),end_date=VALUES(end_date),is_active=1,updated_at=NOW()"
);

Auth::start();
$_SESSION['user_id'] = 1;

function planningWorkbook(
    string $sheet,
    string $table,
    array $headers,
    array $rows,
    string $run
): string {
    $path = tempnam(sys_get_temp_dir(), 'planning-xlsx-') . '.xlsx';
    $headers[] = 'شناسه اجرای تست';
    foreach ($rows as &$row) $row[] = $run;
    unset($row);
    return createXlsxFixture($path, $sheet, $table, $headers, $rows);
}

function planningUpload(
    string $source,
    string $sheet,
    string $table,
    array $headers,
    array $rows,
    array $context
): array {
    $path = planningWorkbook($sheet, $table, $headers, $rows, $source . '-' . bin2hex(random_bytes(4)));
    try {
        return UnifiedImportService::upload(
            ['error'=>UPLOAD_ERR_OK,'tmp_name'=>$path,'size'=>filesize($path),'name'=>'arbitrary.xlsx'],
            $source,
            'skip_duplicates',
            1,
            $context
        );
    } finally {
        @unlink($path);
    }
}

$priority = planningUpload(
    'product_priorities',
    'اولویت کالا',
    'tblolaviyat',
    ['کد کالا','نام کالا','کد برند','نام برند','اولویت','موجودی کالا','ارزش موجودی','وضعیت'],
    [['P-1001','کالای برنامه فروش','BR-1','برند یک','خیلی بالا','120','24000000','فعال']],
    ['period_id'=>1]
);
if (($priority['summary']['ready_rows'] ?? 0) !== 1) {
    throw new RuntimeException('Product priority staging failed.');
}
if (UnifiedImportService::commit((int)$priority['batch_id'], 1, true) !== ['inserted'=>1,'updated'=>0,'skipped'=>0]) {
    throw new RuntimeException('Product priority commit failed.');
}
$priorityRow = Database::fetch('SELECT product_code,priority_code,priority_rank FROM vw_active_product_priorities WHERE period_id=1');
if (($priorityRow['product_code'] ?? '') !== 'P-1001' || ($priorityRow['priority_code'] ?? '') !== 'P1' || (int)($priorityRow['priority_rank'] ?? 0) !== 1) {
    throw new RuntimeException('Product priority normalization/view activation failed.');
}

$coefficient = planningUpload(
    'customer_coefficients',
    'ضرایب صنف',
    'tblzarib',
    ['کد صنف','نام صنف','ضریب'],
    [['G-10','سوپرمارکت','1.15']],
    ['period_id'=>1]
);
if (($coefficient['summary']['ready_rows'] ?? 0) !== 1) {
    throw new RuntimeException('Customer coefficient staging failed.');
}
if (UnifiedImportService::commit((int)$coefficient['batch_id'], 1, true) !== ['inserted'=>1,'updated'=>0,'skipped'=>0]) {
    throw new RuntimeException('Customer coefficient commit failed.');
}
$coefficientRow = Database::fetch(
    'SELECT customer_class_code,coefficient,version_no FROM vw_active_customer_class_coefficients WHERE period_id=1'
);
if (($coefficientRow['customer_class_code'] ?? '') !== 'G-10' || (float)($coefficientRow['coefficient'] ?? 0) !== 1.15 || (int)($coefficientRow['version_no'] ?? 0) !== 1) {
    throw new RuntimeException('Customer coefficient version/view activation failed.');
}

$target = planningUpload(
    'sales_targets',
    'تارگت',
    'tbltarget',
    ['کد لاین','کد کالا','کد فروشنده','تارگت تعداد','تارگت مبلغ','درصد تخصیص'],
    [['A','P-1001','V-1001','100','50000000','100']],
    ['period_id'=>1]
);
if (($target['summary']['ready_rows'] ?? 0) !== 1) {
    $errors = Database::fetchAll(
        'SELECT error_code,error_message FROM sales_import_errors WHERE import_batch_id=?',
        [(int)$target['batch_id']]
    );
    throw new RuntimeException('Sales target staging failed: '.json_encode($errors,JSON_UNESCAPED_UNICODE));
}
if (UnifiedImportService::commit((int)$target['batch_id'], 1, true) !== ['inserted'=>1,'updated'=>0,'skipped'=>0]) {
    throw new RuntimeException('Sales target commit failed.');
}
$targetRow = Database::fetch(
    'SELECT period_id,visitor_user_id,line_id,product_code,product_name,brand_code,target_quantity,target_amount
     FROM vw_active_sales_targets WHERE period_id=1'
);
if (
    (int)($targetRow['visitor_user_id'] ?? 0) !== 4
    || (int)($targetRow['line_id'] ?? 0) !== 1
    || ($targetRow['product_name'] ?? '') !== 'کالای برنامه فروش'
    || ($targetRow['brand_code'] ?? '') !== 'BR-1'
    || (float)($targetRow['target_quantity'] ?? 0) !== 100.0
    || (float)($targetRow['target_amount'] ?? 0) !== 50000000.0
) {
    throw new RuntimeException('Sales target canonical identity/reference resolution failed.');
}
$lineTotal = Database::fetch(
    'SELECT target_quantity,target_amount FROM vw_sales_target_line_totals WHERE period_id=1 AND line_id=1'
);
if ((float)($lineTotal['target_quantity'] ?? 0) !== 100.0 || (float)($lineTotal['target_amount'] ?? 0) !== 50000000.0) {
    throw new RuntimeException('Line target total was not derived from visitor product target.');
}

echo "Sales planning import integration: PASS\n";
