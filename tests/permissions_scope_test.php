<?php

$root = dirname(__DIR__);
$files = [
    'core/OrgModule.php',
    'lib/OrgAccess.php',
    'admin/users.php',
    'admin/hr-settings.php',
    'database/schema.sql',
];

foreach ($files as $file) {
    if (!is_file($root . '/' . $file)) throw new RuntimeException("Missing required file: {$file}");
}

$org = file_get_contents($root . '/core/OrgModule.php');
$access = file_get_contents($root . '/lib/OrgAccess.php');
$users = file_get_contents($root . '/admin/users.php');
$hrSettings = file_get_contents($root . '/admin/hr-settings.php');
$schema = file_get_contents($root . '/database/schema.sql');

foreach (['normalizeUserOrganization', 'activeSalesUser', 'SALES_MANAGER', 'SALES_SUPERVISOR', 'VISITOR', 'salesBranch'] as $token) {
    if (!str_contains($org, $token)) throw new RuntimeException("Missing organization rule: {$token}");
}
foreach (['accessibleUserIds', 'canAccessUser', 'directReports', 'teamUserIds', 'unitUserIds', 'lineUserIds', "if (!\$isManager) return [\$userId]"] as $token) {
    if (!str_contains($access, $token)) throw new RuntimeException("Missing scope rule: {$token}");
}
if (!str_contains($access, "Database::fetchAll('SELECT id FROM users')")) throw new RuntimeException('Admin scope must retain access to all users.');
if (substr_count($users . $hrSettings, 'OrgModule::normalizeUserOrganization') < 2) throw new RuntimeException('User editing paths do not share organization validation.');
if (!str_contains($users, 'OrgAccess::canAccessUser($currentUser,$editId)')) throw new RuntimeException('Direct edit URL is not scope protected.');

foreach (['idx_users_org_unit', 'idx_users_org_role', 'idx_users_parent', 'idx_users_supervisor', 'idx_users_organization_manager', 'idx_users_sales_line', 'uq_users_employee_no'] as $index) {
    if (!str_contains($schema, $index)) throw new RuntimeException("Missing users index: {$index}");
}

$changedScope = $org . $access . $users . $hrSettings;
if (preg_match('/\b(DROP|TRUNCATE|RENAME\s+TABLE)\b/i', $changedScope)) throw new RuntimeException('Destructive SQL found in Phase 1 scope.');

echo "Organization and permission scope contract checks passed.\n";
