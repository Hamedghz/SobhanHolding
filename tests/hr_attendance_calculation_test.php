<?php
require_once __DIR__.'/../lib/HrAttendanceRepository.php';
$method=(new ReflectionClass(HrAttendanceRepository::class))->getMethod('calculate');
$method->setAccessible(true);
$settings=['default_start_time'=>'08:00:00','default_end_time'=>'16:00:00','late_tolerance_minutes'=>15,'early_leave_tolerance_minutes'=>10,'allowed_checkin_from'=>null,'allowed_checkin_to'=>'08:30:00','allowed_checkout_from'=>'15:30:00','allowed_checkout_to'=>'17:00:00','allow_before_shift_overtime'=>1,'allow_after_shift_overtime'=>1];
$normal=$method->invoke(null,$settings,null,'present','08:40:00','15:20:00');
if($normal['late_minutes']!==10||$normal['early_leave_minutes']!==10||$normal['work_minutes']!==400)throw new RuntimeException('Allowed-range calculation failed.');
$holiday=$method->invoke(null,$settings,['id'=>1,'is_half_day'=>0],'present','09:00:00','13:00:00');
if($holiday['late_minutes']!==0||$holiday['early_leave_minutes']!==0||$holiday['normal_overtime_minutes']!==0||$holiday['holiday_overtime_minutes']!==240)throw new RuntimeException('Holiday calculation failed.');
echo "HR attendance calculation checks passed.\n";
