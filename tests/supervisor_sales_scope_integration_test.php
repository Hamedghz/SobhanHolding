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
]);
$engine = ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
$pdo->exec(
    "CREATE TABLE IF NOT EXISTS users (
        id INT UNSIGNED PRIMARY KEY,
        name VARCHAR(150) NOT NULL,
        email VARCHAR(190) NOT NULL UNIQUE,
        username VARCHAR(100) NOT NULL UNIQUE,
        employee_no VARCHAR(50) NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        role VARCHAR(30) NOT NULL DEFAULT 'employee',
        role_key VARCHAR(100) NULL,
        status VARCHAR(30) NOT NULL DEFAULT 'active',
        sales_line VARCHAR(50) NULL,
        kara_system_code VARCHAR(100) NULL,
        sales_line_id INT UNSIGNED NULL,
        org_role_id INT UNSIGNED NULL,
        organization_manager_id INT UNSIGNED NULL,
        supervisor_id INT UNSIGNED NULL,
        parent_user_id INT UNSIGNED NULL,
        display_order INT NOT NULL DEFAULT 0
    ){$engine}"
);
$pdo->exec(
    "CREATE TABLE IF NOT EXISTS org_roles (
        id INT UNSIGNED PRIMARY KEY,
        code VARCHAR(100) NOT NULL UNIQUE,
        title VARCHAR(190) NOT NULL
    ){$engine}"
);
$pdo->exec(
    "CREATE TABLE IF NOT EXISTS sales_team_assignments (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        supervisor_id INT UNSIGNED NOT NULL,
        visitor_id INT UNSIGNED NOT NULL,
        sales_manager_id INT UNSIGNED NULL,
        sales_line VARCHAR(50) NULL,
        active TINYINT(1) NOT NULL DEFAULT 1,
        UNIQUE KEY uq_sales_team_active(supervisor_id,visitor_id)
    ){$engine}"
);
$pdo->exec(
    "CREATE TABLE IF NOT EXISTS vw_active_sales_aggregate_rows (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        invoice_date DATE NOT NULL,
        invoice_number VARCHAR(100) NULL,
        customer_code VARCHAR(100) NULL,
        visitor_code VARCHAR(100) NULL,
        visitor_name VARCHAR(255) NULL,
        line_code VARCHAR(100) NULL,
        net_amount DECIMAL(20,2) NULL,
        total_qty DECIMAL(18,4) NULL
    ){$engine}"
);

$userStatement = $pdo->prepare(
    'INSERT INTO users
     (id,name,email,username,employee_no,password_hash,role,role_key,status,sales_line,kara_system_code,sales_line_id,org_role_id,organization_manager_id,supervisor_id,parent_user_id,display_order)
     VALUES (?,?,?,?,?,"test","employee",?,"active","A",NULL,NULL,NULL,NULL,?,?,?)
     ON DUPLICATE KEY UPDATE
       name=VALUES(name),
       employee_no=VALUES(employee_no),
       role_key=VALUES(role_key),
       supervisor_id=VALUES(supervisor_id),
       parent_user_id=VALUES(parent_user_id)'
);
$userStatement->execute([1, 'سرپرست آزمون', 'scope-supervisor@example.test', 'scope-supervisor', 'SUP-1', 'SALES_SUPERVISOR', null, null, 1]);
$userStatement->execute([2, 'نام مشترک', 'scope-visitor-code@example.test', 'scope-visitor-code', 'CODE-2', 'VISITOR', 1, 1, 2]);
$userStatement->execute([3, 'ویزیتور بدون کد', 'scope-visitor-name@example.test', 'scope-visitor-name', null, 'VISITOR', 1, 1, 3]);
$userStatement->execute([4, 'نام مشترک', 'scope-outsider@example.test', 'scope-outsider', 'OUT-4', 'VISITOR', 99, 99, 4]);

$rowStatement = $pdo->prepare(
    'INSERT INTO vw_active_sales_aggregate_rows
     (invoice_date,invoice_number,customer_code,visitor_code,visitor_name,line_code,net_amount,total_qty)
     VALUES (?,?,?,?,?,?,?,?)'
);
$rowStatement->execute(['2026-07-01', 'INV-1', 'C-1', 'CODE-2', 'نام مشترک', 'A', 100, 1]);
$rowStatement->execute(['2026-07-02', 'INV-2', 'C-2', null, 'ویزیتور بدون کد', 'A', 200, 2]);
$rowStatement->execute(['2026-07-03', 'INV-OUT', 'C-OUT', 'OUT-4', 'نام مشترک', 'B', 900, 9]);

require_once $root . '/core/Database.php';
$reflection = new ReflectionClass(Database::class);
$pdoProperty = $reflection->getProperty('pdo');
$pdoProperty->setValue(null, $pdo);
$migratedProperty = $reflection->getProperty('migrated');
$migratedProperty->setValue(null, true);

require_once $root . '/services/SalesOperationsService.php';
$summary = SalesOperationsService::getSupervisorSalesSummary(1, [
    'from' => '2026-07-01',
    'to' => '2026-07-31',
]);

if (($summary['data_source'] ?? '') !== 'active_sales_reference_view') {
    throw new RuntimeException('Active sales view was not selected.');
}
if ((float)($summary['net_sales'] ?? 0) !== 300.0) {
    throw new RuntimeException('Supervisor scope leaked or omitted sales rows.');
}
if ((int)($summary['invoice_count'] ?? 0) !== 2 || (int)($summary['customer_count'] ?? 0) !== 2) {
    throw new RuntimeException('Supervisor distinct totals are incorrect.');
}
if (count($summary['rows'] ?? []) !== 2) {
    throw new RuntimeException('Supervisor row count is incorrect.');
}
foreach ($summary['rows'] as $row) {
    if (($row['visitor_code'] ?? '') === 'OUT-4') {
        throw new RuntimeException('Out-of-scope visitor was included by a duplicate name.');
    }
}

echo "Supervisor sales scope integration: PASS\n";
