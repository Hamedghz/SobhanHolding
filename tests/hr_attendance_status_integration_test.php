<?php

$root = dirname(__DIR__);
require_once $root . '/lib/HrAttendanceRepository.php';
require_once $root . '/core/HrAttendanceModule.php';
require_once $root . '/tests/support/import_integration_bootstrap.php';

$pdo = importIntegrationPdo($root);
HrAttendanceModule::repair($pdo);

$adminId = 1900001;
$employeeId = 1900002;
$pdo->exec(
    "INSERT INTO users
        (id,name,email,username,employee_no,kara_system_code,password_hash,role,status,department,access_scope,admin_panel_enabled)
     VALUES
        ({$adminId},'مدیر تست حضور','attendance-status-admin@example.test','attendance-status-admin','ATT-ADMIN','ATT-KARA-ADMIN','test','super_admin','active','مدیریت','all',1),
        ({$employeeId},'کارمند تست حضور','attendance-status-user@example.test','attendance-status-user','ATT-USER','ATT-KARA-USER','test','employee','active','اداری','self',0)
     ON DUPLICATE KEY UPDATE
        name=VALUES(name),employee_no=VALUES(employee_no),kara_system_code=VALUES(kara_system_code),role=VALUES(role),status='active'"
);
$groupId = (int)$pdo->query("SELECT id FROM hr_work_groups WHERE code='ADMIN_WAREHOUSE'")->fetchColumn();
$setting = $pdo->prepare(
    'INSERT INTO hr_attendance_settings
        (work_group_id,effective_from,effective_to,default_start_time,default_end_time,
         late_tolerance_minutes,early_leave_tolerance_minutes,allow_before_shift_overtime,
         allow_after_shift_overtime,require_overtime_approval,active,created_by,created_at,updated_at)
     VALUES (?,?,?,?,?,0,0,0,1,1,1,?,NOW(),NOW())
     ON DUPLICATE KEY UPDATE
        effective_to=VALUES(effective_to),default_start_time=VALUES(default_start_time),
        default_end_time=VALUES(default_end_time),active=1,updated_at=NOW()'
);
$setting->execute([$groupId, '2099-01-01', '2099-12-31', '08:00:00', '16:00:00', $adminId]);

Auth::start();
$_SESSION['user_id'] = $adminId;

$row = static function (string $status, array $extra = []) use ($employeeId): array {
    return [
        $employeeId => array_merge([
            'selected' => '1',
            'group_code' => 'ADMIN_WAREHOUSE',
            'day_status' => $status,
            'actual_in_time' => '',
            'actual_out_time' => '',
            'leave_type' => '',
            'mission_details' => '',
            'notes' => '',
        ], $extra),
    ];
};
$expectInvalid = static function (callable $action, string $message): void {
    try {
        $action();
    } catch (InvalidArgumentException $e) {
        if (!str_contains($e->getMessage(), $message)) {
            throw new RuntimeException('Unexpected validation message: ' . $e->getMessage());
        }
        return;
    }
    throw new RuntimeException('Expected validation was not raised: ' . $message);
};

$expectInvalid(
    fn() => HrAttendanceRepository::saveBatch('2099-01-10', $row('present'), $adminId),
    'ساعت ورود و خروج'
);
$expectInvalid(
    fn() => HrAttendanceRepository::saveBatch('2099-01-11', $row('leave'), $adminId),
    'نوع مرخصی'
);
$expectInvalid(
    fn() => HrAttendanceRepository::saveBatch('2099-01-12', $row('mission'), $adminId),
    'شرح مأموریت'
);
$expectInvalid(
    fn() => HrAttendanceRepository::saveBatch('2099-01-16', $row('holiday_work', [
        'actual_in_time' => '09:00',
        'actual_out_time' => '13:00',
    ]), $adminId),
    'فقط روی تاریخ تعطیل'
);

HrAttendanceRepository::saveBatch('2099-01-13', $row('absent', [
    'actual_in_time' => '09:00',
    'actual_out_time' => '13:00',
]), $adminId);
$absent = Database::fetch(
    'SELECT actual_in_time,actual_out_time,work_minutes FROM hr_attendance_entries
     WHERE employee_id=? AND attendance_date=?',
    [$employeeId, '2099-01-13']
);
if (!$absent || $absent['actual_in_time'] !== null || $absent['actual_out_time'] !== null || (int)$absent['work_minutes'] !== 0) {
    throw new RuntimeException('Absent attendance retained time values.');
}

HrAttendanceRepository::saveBatch('2099-01-14', $row('leave', ['leave_type' => 'استحقاقی']), $adminId);
HrAttendanceRepository::saveBatch('2099-01-15', $row('mission', ['mission_details' => 'مراجعه به شعبه']), $adminId);
$details = Database::fetchAll(
    'SELECT attendance_date,day_status,leave_type,mission_details FROM hr_attendance_entries
     WHERE employee_id=? AND attendance_date IN (?,?) ORDER BY attendance_date',
    [$employeeId, '2099-01-14', '2099-01-15']
);
if (
    count($details) !== 2
    || ($details[0]['leave_type'] ?? '') !== 'استحقاقی'
    || ($details[1]['mission_details'] ?? '') !== 'مراجعه به شعبه'
) {
    throw new RuntimeException('Leave or mission details were not persisted.');
}

$holiday = $pdo->prepare(
    'INSERT INTO hr_month_holidays
        (holiday_date,jalali_year,jalali_month,title,holiday_type,applies_to_group,is_half_day,active,created_by,created_at,updated_at)
     VALUES (?,?,?,?,?,"admin_warehouse",0,1,?,NOW(),NOW())
     ON DUPLICATE KEY UPDATE title=VALUES(title),active=1,updated_at=NOW()'
);
$holiday->execute(['2099-01-17', 1477, 10, 'تعطیلی تست', 'internal', $adminId]);
HrAttendanceRepository::saveBatch('2099-01-17', $row('holiday_work', [
    'actual_in_time' => '09:00',
    'actual_out_time' => '13:00',
]), $adminId);
$holidayWork = Database::fetch(
    'SELECT day_status,is_holiday,work_minutes,holiday_overtime_minutes FROM hr_attendance_entries
     WHERE employee_id=? AND attendance_date=?',
    [$employeeId, '2099-01-17']
);
if (
    ($holidayWork['day_status'] ?? '') !== 'holiday_work'
    || (int)($holidayWork['is_holiday'] ?? 0) !== 1
    || (int)($holidayWork['work_minutes'] ?? 0) !== 240
    || (int)($holidayWork['holiday_overtime_minutes'] ?? 0) !== 240
) {
    throw new RuntimeException('Holiday-work calculation or persistence failed.');
}

echo "HR attendance status integration: PASS\n";
