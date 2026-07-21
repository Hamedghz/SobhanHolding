<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/JalaliDate.php';
require_once __DIR__ . '/OrgAccess.php';

final class HrAttendanceRepository
{
    public const DAY_STATUSES = [
        'present'=>'حاضر',
        'absent'=>'غیبت',
        'leave'=>'مرخصی',
        'mission'=>'مأموریت',
        'holiday_work'=>'حضور در روز تعطیل',
        'holiday'=>'تعطیل',
        'half_day'=>'نیمه‌وقت',
    ];
    public const ENTRY_STATUSES = [
        'present'=>'حاضر',
        'absent'=>'غیبت',
        'leave'=>'مرخصی',
        'mission'=>'مأموریت',
        'holiday_work'=>'حضور در روز تعطیل',
    ];
    public const OVERTIME_STATUSES = ['none'=>'بدون اضافه‌کاری','pending'=>'در انتظار تأیید','approved'=>'تأییدشده','rejected'=>'ردشده'];
    public const HOLIDAY_TYPES = ['official'=>'رسمی','company'=>'شرکتی','internal'=>'داخلی','half_day'=>'نیمه‌روز'];
    public const HOLIDAY_SCOPES = ['all'=>'همه','sales'=>'فروش','admin_warehouse'=>'اداری و انبار'];

    public static function canView(): bool { return Auth::isAdmin() || Auth::can('hr_attendance'); }
    public static function canWrite(): bool { return Auth::isAdmin() || Auth::can('hr_attendance', 'edit') || Auth::can('hr_attendance', 'create'); }
    public static function canSettings(): bool { return Auth::isAdmin() || Auth::can('hr_attendance.settings', 'edit'); }
    public static function canReports(): bool { return Auth::isAdmin() || Auth::can('hr_attendance.reports'); }

    public static function groups(bool $activeOnly=true): array
    {
        return Database::fetchAll('SELECT * FROM hr_work_groups'.($activeOnly?' WHERE active=1':'').' ORDER BY FIELD(code,"SALES","ADMIN_WAREHOUSE"),title');
    }

    public static function employees(array $filters=[]): array
    {
        $u=Auth::user(); if(!$u)return [];
        [$scope,$params]=self::scopeSql($u,'u.id'); $where=[$scope,'u.status="active"'];
        foreach(['org_unit_id','org_role_id','supervisor_id','organization_manager_id'] as $key) if((int)($filters[$key]??0)>0){$where[]="u.$key=?";$params[]=(int)$filters[$key];}
        if(trim((string)($filters['sales_line']??''))!==''){$where[]='u.sales_line=?';$params[]=self::text($filters['sales_line'],50);}
        if(($filters['group_code']??'')==='SALES')$where[]='(u.sales_line IS NOT NULL AND u.sales_line<>"" OR COALESCE(r.is_sales_role,0)=1)';
        if(($filters['group_code']??'')==='ADMIN_WAREHOUSE')$where[]='NOT (u.sales_line IS NOT NULL AND u.sales_line<>"" OR COALESCE(r.is_sales_role,0)=1)';
        return Database::fetchAll('SELECT u.id,u.name,u.employee_no,u.kara_system_code,u.department,u.role_key,u.sales_line,u.org_unit_id,u.org_role_id,u.supervisor_id,u.organization_manager_id,ou.title unit_title,r.title role_title,COALESCE(r.is_sales_role,0) is_sales_role FROM users u LEFT JOIN org_units ou ON ou.id=u.org_unit_id LEFT JOIN org_roles r ON r.id=u.org_role_id WHERE '.implode(' AND ',$where).' ORDER BY u.display_order,u.name',$params);
    }

    public static function settingsHistory(): array
    {
        return Database::fetchAll('SELECT s.*,g.title group_title,g.code group_code,u.name creator_name FROM hr_attendance_settings s JOIN hr_work_groups g ON g.id=s.work_group_id LEFT JOIN users u ON u.id=s.created_by ORDER BY g.id,s.effective_from DESC,s.id DESC');
    }

    public static function latestSettings(): array
    {
        $rows=Database::fetchAll('SELECT s.*,g.title group_title,g.code group_code FROM hr_attendance_settings s JOIN hr_work_groups g ON g.id=s.work_group_id WHERE s.active=1 AND s.effective_from<=CURDATE() AND (s.effective_to IS NULL OR s.effective_to>=CURDATE()) ORDER BY s.effective_from DESC,s.id DESC');$out=[];foreach($rows as $row)if(!isset($out[$row['group_code']]))$out[$row['group_code']]=$row;return $out;
    }

    public static function saveSettings(array $data,int $by): void
    {
        if(!self::canSettings())throw new DomainException('دسترسی تنظیمات ساعات کاری را ندارید.');
        $groupId=(int)($data['work_group_id']??0); $group=Database::fetch('SELECT * FROM hr_work_groups WHERE id=? AND active=1',[$groupId]);
        if(!$group)throw new InvalidArgumentException('گروه کاری معتبر نیست.');
        $effective=self::date($data['effective_from']??'');$effectiveTo=trim((string)($data['effective_to']??''))!==''?self::date($data['effective_to']):null;if($effectiveTo!==null&&$effectiveTo<$effective)throw new InvalidArgumentException('پایان اجرا نمی‌تواند قبل از شروع اجرا باشد.');
        if(Database::fetch('SELECT id FROM hr_attendance_settings WHERE work_group_id=? AND active=1 AND effective_from<>? AND effective_from<=COALESCE(?,"9999-12-31") AND COALESCE(effective_to,"9999-12-31")>=? LIMIT 1',[$groupId,$effective,$effectiveTo,$effective]))throw new InvalidArgumentException('این بازه با یک نسخه فعال دیگر هم‌پوشانی دارد.');$start=self::time($data['default_start_time']??''); $end=self::time($data['default_end_time']??'');
        if(self::minutes($end)<=self::minutes($start))throw new InvalidArgumentException('ساعت خروج باید بعد از ساعت ورود باشد.');
        $late=self::boundedInt($data['late_tolerance_minutes']??0,0,180,'تلورانس تأخیر');
        $early=self::boundedInt($data['early_leave_tolerance_minutes']??0,0,180,'تلورانس تعجیل');
        $checkinFrom=self::optionalTime($data['allowed_checkin_from']??'');$checkinTo=self::optionalTime($data['allowed_checkin_to']??'');$checkoutFrom=self::optionalTime($data['allowed_checkout_from']??'');$checkoutTo=self::optionalTime($data['allowed_checkout_to']??'');
        if($checkinFrom&&$checkinTo&&self::minutes($checkinTo)<self::minutes($checkinFrom))throw new InvalidArgumentException('پایان بازه ورود باید بعد از شروع آن باشد.');
        if($checkoutFrom&&$checkoutTo&&self::minutes($checkoutTo)<self::minutes($checkoutFrom))throw new InvalidArgumentException('پایان بازه خروج باید بعد از شروع آن باشد.');
        $pdo=Database::connection();$pdo->beginTransaction();
        try{
            Database::execute('INSERT INTO hr_attendance_settings(work_group_id,effective_from,effective_to,default_start_time,default_end_time,late_tolerance_minutes,early_leave_tolerance_minutes,allowed_checkin_from,allowed_checkin_to,allowed_checkout_from,allowed_checkout_to,allow_before_shift_overtime,allow_after_shift_overtime,require_overtime_approval,active,created_by,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,1,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE effective_to=VALUES(effective_to),default_start_time=VALUES(default_start_time),default_end_time=VALUES(default_end_time),late_tolerance_minutes=VALUES(late_tolerance_minutes),early_leave_tolerance_minutes=VALUES(early_leave_tolerance_minutes),allowed_checkin_from=VALUES(allowed_checkin_from),allowed_checkin_to=VALUES(allowed_checkin_to),allowed_checkout_from=VALUES(allowed_checkout_from),allowed_checkout_to=VALUES(allowed_checkout_to),allow_before_shift_overtime=VALUES(allow_before_shift_overtime),allow_after_shift_overtime=VALUES(allow_after_shift_overtime),require_overtime_approval=VALUES(require_overtime_approval),active=1,updated_at=NOW()',[$groupId,$effective,$effectiveTo,$start,$end,$late,$early,$checkinFrom,$checkinTo,$checkoutFrom,$checkoutTo,!empty($data['allow_before_shift_overtime'])?1:0,!empty($data['allow_after_shift_overtime'])?1:0,!empty($data['require_overtime_approval'])?1:0,$by]);
            $pdo->commit();Auth::log($by,'hr_attendance_settings_saved','hr_attendance_settings');
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }

    public static function holidays(array $filters=[]): array
    {
        $where=['1=1'];$params=[];
        if((int)($filters['year']??0)>0){$where[]='h.jalali_year=?';$params[]=(int)$filters['year'];}
        if((int)($filters['month']??0)>0){$where[]='h.jalali_month=?';$params[]=(int)$filters['month'];}
        if(trim((string)($filters['group']??''))!==''){$where[]='h.applies_to_group=?';$params[]=self::text($filters['group'],30);}
        if(isset($filters['active'])){$where[]='h.active=?';$params[]=(int)$filters['active'];}
        return Database::fetchAll('SELECT h.*,u.name creator_name FROM hr_month_holidays h LEFT JOIN users u ON u.id=h.created_by WHERE '.implode(' AND ',$where).' ORDER BY h.holiday_date DESC,h.id DESC',$params);
    }

    public static function holidayById(int $id): ?array { return Database::fetch('SELECT * FROM hr_month_holidays WHERE id=?',[$id])?:null; }

    public static function saveHoliday(array $data,int $by): int
    {
        if(!self::canSettings())throw new DomainException('دسترسی مدیریت تعطیلات را ندارید.');
        $id=(int)($data['id']??0);$date=self::date($data['holiday_date']??'');$jalali=explode('/',JalaliDate::toJalali($date));
        $title=self::required($data['title']??'','عنوان تعطیلی');$type=array_key_exists($data['holiday_type']??'',self::HOLIDAY_TYPES)?$data['holiday_type']:'official';
        $scope=array_key_exists($data['applies_to_group']??'',self::HOLIDAY_SCOPES)?$data['applies_to_group']:'all';
        $duplicate=Database::fetch('SELECT id FROM hr_month_holidays WHERE holiday_date=? AND applies_to_group=? AND id<>?',[$date,$scope,$id]);
        if($duplicate)throw new InvalidArgumentException('برای این تاریخ و گروه قبلاً تعطیلی ثبت شده است.');
        $values=[$date,(int)($jalali[0]??0),(int)($jalali[1]??0),$title,$type,$scope,($type==='half_day'||!empty($data['is_half_day']))?1:0,self::text($data['description']??'',5000),!empty($data['active'])?1:0];
        if($id){Database::execute('UPDATE hr_month_holidays SET holiday_date=?,jalali_year=?,jalali_month=?,title=?,holiday_type=?,applies_to_group=?,is_half_day=?,description=?,active=?,updated_at=NOW() WHERE id=?',[...$values,$id]);}
        else{Database::execute('INSERT INTO hr_month_holidays(holiday_date,jalali_year,jalali_month,title,holiday_type,applies_to_group,is_half_day,description,active,created_by,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,NOW(),NOW())',[...$values,$by]);$id=(int)Database::lastInsertId();}
        Auth::log($by,'hr_holiday_saved','hr_month_holidays',$id);return $id;
    }

    public static function toggleHoliday(int $id,int $by): void
    {
        if(!self::canSettings())throw new DomainException('دسترسی مدیریت تعطیلات را ندارید.');
        Database::execute('UPDATE hr_month_holidays SET active=IF(active=1,0,1),updated_at=NOW() WHERE id=?',[$id]);Auth::log($by,'hr_holiday_toggled','hr_month_holidays',$id);
    }

    public static function entriesForDate(string $date,array $filters=[]): array
    {
        $date=self::date($date);$employees=self::employees($filters);if(!$employees)return [];
        $ids=array_column($employees,'id');$ph=implode(',',array_fill(0,count($ids),'?'));
        $entries=Database::fetchAll("SELECT e.*,g.code group_code FROM hr_attendance_entries e JOIN hr_work_groups g ON g.id=e.work_group_id WHERE e.attendance_date=? AND e.employee_id IN ($ph)",array_merge([$date],$ids));
        $map=[];foreach($entries as $entry)$map[(int)$entry['employee_id']]=$entry;
        foreach($employees as &$employee){$employee['default_group_code']=self::employeeGroupCode($employee);$employee['entry']=$map[(int)$employee['id']]??null;}unset($employee);
        return $employees;
    }

    public static function saveBatch(string $date,array $rows,int $by): int
    {
        if(!self::canWrite())throw new DomainException('دسترسی ثبت کارکرد را ندارید.');$date=self::date($date);$u=Auth::user();$allowed=array_flip(self::accessibleEmployeeIds($u));$count=0;
        $pdo=Database::connection();$pdo->beginTransaction();
        try{foreach($rows as $employeeId=>$row){$employeeId=(int)$employeeId;if(!$employeeId||!isset($allowed[$employeeId])||empty($row['selected']))continue;$employee=Database::fetch('SELECT u.*,COALESCE(r.is_sales_role,0) is_sales_role FROM users u LEFT JOIN org_roles r ON r.id=u.org_role_id WHERE u.id=? AND u.status="active"',[$employeeId]);if(!$employee)continue;self::saveEntry($employee,$date,$row,$by);$count++;}$pdo->commit();return $count;}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }

    public static function approveOvertime(int $id,string $status,int $by): void
    {
        if(!self::canWrite())throw new DomainException('دسترسی تأیید اضافه‌کاری را ندارید.');if(!in_array($status,['approved','rejected'],true))throw new InvalidArgumentException('وضعیت تأیید معتبر نیست.');
        $entry=self::entryById($id);$old=$entry;Database::execute('UPDATE hr_attendance_entries SET overtime_status=?,approved_by=?,approved_at=NOW(),updated_at=NOW() WHERE id=?',[$status,$by,$id]);$entry['overtime_status']=$status;$entry['approved_by']=$by;
        self::log($id,$status==='approved'?'approve_overtime':'reject_overtime',$old,$entry,$by);Auth::log($by,'hr_overtime_'.$status,'hr_attendance_entries',$id);
    }

    public static function report(array $filters=[]): array
    {
        $u=Auth::user();if(!$u||!self::canReports())return [];[$scope,$params]=self::scopeSql($u,'e.employee_id');$where=[$scope];
        foreach(['date_from'=>'e.attendance_date>=?','date_to'=>'e.attendance_date<=?'] as $key=>$sql)if(trim((string)($filters[$key]??''))!==''){$where[]=$sql;$params[]=self::date($filters[$key]);}
        foreach(['employee_id'=>'e.employee_id','work_group_id'=>'e.work_group_id','org_unit_id'=>'u.org_unit_id','org_role_id'=>'u.org_role_id'] as $key=>$column)if((int)($filters[$key]??0)>0){$where[]="$column=?";$params[]=(int)$filters[$key];}
        foreach(['day_status'=>'e.day_status','overtime_status'=>'e.overtime_status'] as $key=>$column)if(trim((string)($filters[$key]??''))!==''){$where[]="$column=?";$params[]=self::text($filters[$key],30);}
        return Database::fetchAll('SELECT e.*,u.name employee_name,u.employee_no,u.department,u.role_key,u.sales_line,ou.title unit_title,r.title role_title,g.title group_title,g.code group_code,a.name approver_name FROM hr_attendance_entries e JOIN users u ON u.id=e.employee_id JOIN hr_work_groups g ON g.id=e.work_group_id LEFT JOIN org_units ou ON ou.id=u.org_unit_id LEFT JOIN org_roles r ON r.id=u.org_role_id LEFT JOIN users a ON a.id=e.approved_by WHERE '.implode(' AND ',$where).' ORDER BY e.attendance_date DESC,u.name',$params);
    }

    public static function myReport(int $userId,array $filters=[]): array
    {
        $actor=Auth::user();if($userId<1||!$actor||(int)$actor['id']!==$userId)return [];$where=['e.employee_id=?'];$params=[$userId];
        $year=(int)($filters['year']??0);$month=(int)($filters['month']??0);$from=trim((string)($filters['date_from']??''));$to=trim((string)($filters['date_to']??''));
        if($from===''&&$year&&$month)$from=JalaliDate::toGregorian(sprintf('%04d/%02d/01',$year,$month))??'';
        if($to===''&&$year&&$month)$to=JalaliDate::toGregorian(sprintf('%04d/%02d/%02d',$year,$month,JalaliDate::monthLength($year,$month)))??'';
        if($from!==''){$from=self::date($from);$where[]='e.attendance_date>=?';$params[]=$from;}
        if($to!==''){$to=self::date($to);$where[]='e.attendance_date<=?';$params[]=$to;}
        $rows=Database::fetchAll('SELECT e.*,g.title group_title,g.code group_code,a.name approver_name FROM hr_attendance_entries e JOIN hr_work_groups g ON g.id=e.work_group_id LEFT JOIN users a ON a.id=e.approved_by WHERE '.implode(' AND ',$where).' ORDER BY e.attendance_date DESC,e.id DESC',$params);
        $groupCode=self::groupCodeForUser($userId);$holidays=($from!==''&&$to!=='')?self::holidaysBetween($from,$to,$groupCode):[];$holidayMap=[];foreach($holidays as $holiday)$holidayMap[$holiday['holiday_date']]=$holiday;
        foreach($rows as &$row){$hasTimes=!empty($row['actual_in_time'])&&!empty($row['actual_out_time']);$work=$hasTimes?max(0,self::minutes($row['actual_out_time'])-self::minutes($row['actual_in_time'])):0;if(!in_array($row['day_status'],['present','half_day','holiday','holiday_work'],true))$work=0;$row['work_minutes']=$work;$row['scheduled_work_minutes']=(!empty($row['approved_start_time'])&&!empty($row['approved_end_time']))?max(0,self::minutes($row['approved_end_time'])-self::minutes($row['approved_start_time'])):0;if(isset($holidayMap[$row['attendance_date']])){$holiday=$holidayMap[$row['attendance_date']];$row['is_holiday']=1;$row['holiday_id']=$holiday['id'];$row['late_minutes']=0;$row['early_leave_minutes']=0;$row['normal_overtime_minutes']=0;$row['holiday_overtime_minutes']=$work;$row['display_day_status']=$hasTimes?'holiday_work':'holiday';$row['holiday_title']=$holiday['title'];$row['holiday_is_half_day']=(int)$holiday['is_half_day'];}else{$row['display_day_status']=$row['day_status'];}}
        unset($row);return $rows;
    }

    public static function holidaysBetween(string $from,string $to,string $groupCode): array
    {
        $from=self::date($from);$to=self::date($to);if($to<$from)throw new InvalidArgumentException('بازه گزارش معتبر نیست.');$scope=strtoupper($groupCode)==='SALES'?'sales':'admin_warehouse';$rows=Database::fetchAll('SELECT * FROM hr_month_holidays WHERE active=1 AND holiday_date BETWEEN ? AND ? AND applies_to_group IN("all",?) ORDER BY holiday_date,applies_to_group="all" ASC,id DESC',[$from,$to,$scope]);$byDate=[];foreach($rows as $row){$date=$row['holiday_date'];if(!isset($byDate[$date])||(!(int)$row['is_half_day']&&(int)$byDate[$date]['is_half_day']))$byDate[$date]=$row;}return array_values($byDate);
    }

    public static function holidayForDateContext(string $date,string $groupCode): ?array
    {
        return self::holidayForDate(self::date($date),$groupCode);
    }

    public static function myReportStats(array $rows,array $filters=[]): array
    {
        $from=trim((string)($filters['date_from']??''));$to=trim((string)($filters['date_to']??''));if($from!=='')$from=self::date($from);if($to!=='')$to=self::date($to);if(($from===''||$to==='')&&$rows){$dates=array_column($rows,'attendance_date');sort($dates);$from=$from?:$dates[0];$to=$to?:$dates[count($dates)-1];}$groupCode=(string)($filters['group_code']??($rows[0]['group_code']??'ADMIN_WAREHOUSE'));$holidays=($from&&$to)?self::holidaysBetween($from,$to,$groupCode):[];
        $stats=['work_minutes'=>0,'late_minutes'=>0,'early_leave_minutes'=>0,'approved_overtime_minutes'=>0,'absent'=>0,'leave'=>0,'mission'=>0,'holiday_count'=>count($holidays),'holiday_equivalent'=>0.0,'calendar_days'=>0,'expected_work_days'=>0.0];$leaveDates=[];$missionDates=[];$holidayFractions=[];
        foreach($holidays as $holiday){$fraction=(int)$holiday['is_half_day']?0.5:1.0;$stats['holiday_equivalent']+=$fraction;$holidayFractions[$holiday['holiday_date']]=$fraction;}
        foreach($rows as $row){$stats['work_minutes']+=(int)$row['work_minutes'];$stats['late_minutes']+=(int)$row['late_minutes'];$stats['early_leave_minutes']+=(int)$row['early_leave_minutes'];if($row['overtime_status']==='approved')$stats['approved_overtime_minutes']+=(int)$row['normal_overtime_minutes']+(int)$row['holiday_overtime_minutes'];$stats['absent']+=(int)($row['day_status']==='absent');$stats['leave']+=(int)($row['day_status']==='leave');$stats['mission']+=(int)($row['day_status']==='mission');if($row['day_status']==='leave')$leaveDates[$row['attendance_date']]=true;if($row['day_status']==='mission')$missionDates[$row['attendance_date']]=true;}
        $leaveDeduction=0.0;foreach(array_keys($leaveDates) as $date)$leaveDeduction+=max(0,1.0-($holidayFractions[$date]??0));$missionDeduction=0.0;foreach(array_keys($missionDates) as $date)$missionDeduction+=max(0,1.0-($holidayFractions[$date]??0));if($from&&$to)$stats['calendar_days']=(int)(new DateTimeImmutable($from))->diff(new DateTimeImmutable($to))->days+1;$stats['expected_work_days']=max(0,$stats['calendar_days']-$stats['holiday_equivalent']-$leaveDeduction-$missionDeduction);return $stats;
    }

    public static function groupCodeForUser(int $userId): string
    {
        $employee=Database::fetch('SELECT u.sales_line,COALESCE(r.is_sales_role,0) is_sales_role FROM users u LEFT JOIN org_roles r ON r.id=u.org_role_id WHERE u.id=? AND u.status="active"',[$userId]);return $employee?self::employeeGroupCode($employee):'ADMIN_WAREHOUSE';
    }

    public static function reportStats(array $rows): array
    {
        $stats=['late'=>0,'early'=>0,'normal_overtime'=>0,'holiday_overtime'=>0,'absent'=>0,'leave'=>0,'mission'=>0,'punctual'=>0];$employees=[];$lateEmployees=[];
        foreach($rows as $row){$stats['late']+=(int)$row['late_minutes'];$stats['early']+=(int)$row['early_leave_minutes'];if($row['overtime_status']==='approved'){$stats['normal_overtime']+=(int)$row['normal_overtime_minutes'];$stats['holiday_overtime']+=(int)$row['holiday_overtime_minutes'];}$stats['absent']+=(int)($row['day_status']==='absent');$stats['leave']+=(int)($row['day_status']==='leave');$stats['mission']+=(int)($row['day_status']==='mission');$employees[(int)$row['employee_id']]=true;if((int)$row['late_minutes']>0)$lateEmployees[(int)$row['employee_id']]=true;}
        $stats['punctual']=count(array_diff_key($employees,$lateEmployees));return $stats;
    }

    public static function recentLogs(int $limit=50): array
    {
        if(!Auth::isSuperAdmin())return [];$limit=max(1,min(200,$limit));return Database::fetchAll("SELECT l.*,u.name actor_name,e.employee_id,eu.name employee_name,e.attendance_date FROM hr_attendance_logs l JOIN hr_attendance_entries e ON e.id=l.attendance_entry_id LEFT JOIN users u ON u.id=l.performed_by LEFT JOIN users eu ON eu.id=e.employee_id ORDER BY l.id DESC LIMIT $limit");
    }

    public static function units(): array { return Database::fetchAll('SELECT id,title FROM org_units WHERE active=1 ORDER BY title'); }
    public static function roles(): array { return Database::fetchAll('SELECT id,title FROM org_roles WHERE active=1 ORDER BY title'); }
    public static function salesLines(): array { return array_column(Database::fetchAll('SELECT DISTINCT sales_line FROM users WHERE status="active" AND sales_line IS NOT NULL AND sales_line<>"" ORDER BY sales_line'),'sales_line'); }

    private static function saveEntry(array $employee,string $date,array $row,int $by): void
    {
        $groupCode=in_array($row['group_code']??'', ['SALES','ADMIN_WAREHOUSE'],true)?$row['group_code']:self::employeeGroupCode($employee);$group=Database::fetch('SELECT * FROM hr_work_groups WHERE code=? AND active=1',[$groupCode]);if(!$group)throw new RuntimeException('work_group_missing');
        $status=array_key_exists($row['day_status']??'',self::ENTRY_STATUSES)?$row['day_status']:'present';$in=self::optionalTime($row['actual_in_time']??'');$out=self::optionalTime($row['actual_out_time']??'');
        $holiday=self::holidayForDate($date,$groupCode);
        if($holiday&&$status==='present')$status='holiday_work';
        if($holiday&&$status!=='holiday_work')throw new InvalidArgumentException('«'.$employee['name'].'» در روز تعطیل فقط در صورت حضور واقعی با وضعیت «حضور در روز تعطیل» ثبت می‌شود.');
        if(!$holiday&&$status==='holiday_work')throw new InvalidArgumentException('وضعیت حضور در روز تعطیل برای «'.$employee['name'].'» فقط روی تاریخ تعطیل معتبر است.');
        if(in_array($status,['present','holiday_work'],true)&&(!$in||!$out))throw new InvalidArgumentException('برای کارمند «'.$employee['name'].'» ساعت ورود و خروج را وارد کنید.');
        if(in_array($status,['absent','leave','mission'],true)){$in=null;$out=null;}
        if($in&&$out&&self::minutes($out)<self::minutes($in))throw new InvalidArgumentException('ساعت خروج «'.$employee['name'].'» نمی‌تواند قبل از ورود باشد.');
        $leaveType=$status==='leave'?self::text($row['leave_type']??'',100):'';
        $missionDetails=$status==='mission'?self::text($row['mission_details']??'',5000):'';
        if($status==='leave'&&$leaveType==='')throw new InvalidArgumentException('نوع مرخصی برای «'.$employee['name'].'» الزامی است.');
        if($status==='mission'&&$missionDetails==='')throw new InvalidArgumentException('شرح مأموریت برای «'.$employee['name'].'» الزامی است.');
        $break=0;$setting=self::settingForDate((int)$group['id'],$date);if(!$setting)throw new InvalidArgumentException('برای گروه «'.$group['title'].'» تنظیم ساعت مؤثر ثبت نشده است.');
        $calc=self::calculate($setting,$holiday,$status,$in,$out);$old=Database::fetch('SELECT * FROM hr_attendance_entries WHERE employee_id=? AND attendance_date=?',[(int)$employee['id'],$date]);
        $overtime=$calc['normal_overtime_minutes']+$calc['holiday_overtime_minutes'];$otStatus=$overtime===0?'none':((int)$setting['require_overtime_approval']?'pending':'approved');
        $values=[(int)$group['id'],$calc['is_holiday'],$holiday['id']??null,$setting['default_start_time'],$setting['default_end_time'],$in,$out,$break,$calc['late_minutes'],$calc['early_leave_minutes'],$calc['normal_overtime_minutes'],$calc['holiday_overtime_minutes'],$calc['work_minutes'],$status,$leaveType?:null,$missionDetails?:null,$otStatus,self::text($row['notes']??'',5000),$by];
        if($old){Database::execute('UPDATE hr_attendance_entries SET work_group_id=?,is_holiday=?,holiday_id=?,approved_start_time=?,approved_end_time=?,actual_in_time=?,actual_out_time=?,break_minutes=?,late_minutes=?,early_leave_minutes=?,normal_overtime_minutes=?,holiday_overtime_minutes=?,work_minutes=?,day_status=?,leave_type=?,mission_details=?,overtime_status=?,notes=?,approved_by=NULL,approved_at=NULL,updated_at=NOW() WHERE id=?',[...array_slice($values,0,18),(int)$old['id']]);$id=(int)$old['id'];$action='update';}
        else{Database::execute('INSERT INTO hr_attendance_entries(employee_id,work_group_id,attendance_date,is_holiday,holiday_id,approved_start_time,approved_end_time,actual_in_time,actual_out_time,break_minutes,late_minutes,early_leave_minutes,normal_overtime_minutes,holiday_overtime_minutes,work_minutes,day_status,leave_type,mission_details,overtime_status,notes,created_by,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())',[(int)$employee['id'],$values[0],$date,...array_slice($values,1)]);$id=(int)Database::lastInsertId();$action='create';}
        $new=Database::fetch('SELECT * FROM hr_attendance_entries WHERE id=?',[$id]);self::log($id,$action,$old,$new,$by);Auth::log($by,'hr_attendance_'.$action,'hr_attendance_entries',$id);
    }

    private static function calculate(array $setting,?array $holiday,string $status,?string $in,?string $out): array
    {
        $zero=['is_holiday'=>$holiday?1:0,'late_minutes'=>0,'early_leave_minutes'=>0,'normal_overtime_minutes'=>0,'holiday_overtime_minutes'=>0,'work_minutes'=>0];
        if(!$in||!$out||!in_array($status,['present','half_day','holiday','holiday_work'],true))return $zero;
        $inM=self::minutes($in);$outM=self::minutes($out);$start=self::minutes($setting['default_start_time']);$end=self::minutes($setting['default_end_time']);$work=max(0,$outM-$inM);
        $zero['work_minutes']=$work;
        if($holiday){$zero['holiday_overtime_minutes']=$work;return $zero;}
        $lateThreshold=!empty($setting['allowed_checkin_to'])?self::minutes($setting['allowed_checkin_to']):$start+(int)$setting['late_tolerance_minutes'];$earlyThreshold=!empty($setting['allowed_checkout_from'])?self::minutes($setting['allowed_checkout_from']):$end-(int)$setting['early_leave_tolerance_minutes'];$zero['late_minutes']=max(0,$inM-$lateThreshold);$zero['early_leave_minutes']=max(0,$earlyThreshold-$outM);
        $zero['normal_overtime_minutes']=((int)$setting['allow_before_shift_overtime']?max(0,$start-$inM):0)+((int)$setting['allow_after_shift_overtime']?max(0,$outM-$end):0);
        return $zero;
    }

    private static function settingForDate(int $groupId,string $date): ?array { return Database::fetch('SELECT * FROM hr_attendance_settings WHERE work_group_id=? AND active=1 AND effective_from<=? AND (effective_to IS NULL OR effective_to>=?) ORDER BY effective_from DESC,id DESC LIMIT 1',[$groupId,$date,$date])?:null; }
    private static function holidayForDate(string $date,string $groupCode): ?array { $scope=$groupCode==='SALES'?'sales':'admin_warehouse';return Database::fetch('SELECT * FROM hr_month_holidays WHERE holiday_date=? AND active=1 AND applies_to_group IN("all",?) ORDER BY applies_to_group="all" ASC,id DESC LIMIT 1',[$date,$scope])?:null; }
    private static function employeeGroupCode(array $employee): string { return trim((string)($employee['sales_line']??''))!==''||(int)($employee['is_sales_role']??0)===1?'SALES':'ADMIN_WAREHOUSE'; }
    private static function accessibleEmployeeIds(array $u): array { if(Auth::isAdmin()||Auth::can('hr_attendance','edit')||Auth::can('hr_attendance','create'))return array_map('intval',array_column(Database::fetchAll('SELECT id FROM users WHERE status="active"'),'id'));return OrgAccess::accessibleUserIds($u); }
    private static function scopeSql(array $u,string $column): array { $ids=self::accessibleEmployeeIds($u);if(!$ids)$ids=[-1];return [$column.' IN ('.implode(',',array_fill(0,count($ids),'?')).')',$ids]; }
    private static function entryById(int $id): array { $u=Auth::user();[$scope,$params]=self::scopeSql($u,'employee_id');array_unshift($params,$id);$row=Database::fetch('SELECT * FROM hr_attendance_entries WHERE id=? AND '.$scope,$params);if(!$row)throw new DomainException('رکورد کارکرد در دسترس نیست.');return $row; }
    private static function log(int $id,string $action,?array $old,?array $new,int $by): void { Database::execute('INSERT INTO hr_attendance_logs(attendance_entry_id,action,old_value_json,new_value_json,performed_by,created_at) VALUES(?,?,?,?,?,NOW())',[$id,$action,$old?json_encode($old,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES):null,$new?json_encode($new,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES):null,$by]); }
    private static function date(mixed $value): string { $value=trim((string)$value);if(str_contains($value,'/'))$value=JalaliDate::toGregorian($value)??'';$d=DateTimeImmutable::createFromFormat('!Y-m-d',$value);if(!$d||$d->format('Y-m-d')!==$value)throw new InvalidArgumentException('تاریخ معتبر نیست.');return $value; }
    private static function time(mixed $value): string { $time=self::optionalTime($value);if(!$time)throw new InvalidArgumentException('ساعت معتبر نیست.');return $time; }
    private static function optionalTime(mixed $value): ?string { $value=trim((string)$value);if($value==='')return null;if(!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/',$value))throw new InvalidArgumentException('ساعت معتبر نیست.');return substr($value,0,5).':00'; }
    private static function minutes(string $time): int { $parts=array_map('intval',explode(':',$time));return $parts[0]*60+$parts[1]; }
    private static function boundedInt(mixed $value,int $min,int $max,string $label): int { $v=filter_var($value,FILTER_VALIDATE_INT);if($v===false||$v<$min||$v>$max)throw new InvalidArgumentException($label.' معتبر نیست.');return $v; }
    private static function required(mixed $value,string $label): string { $v=self::text($value,190);if($v==='')throw new InvalidArgumentException($label.' الزامی است.');return $v; }
    private static function text(mixed $value,int $max): string { $v=trim((string)$value);return mb_substr($v,0,$max,'UTF-8'); }
}
