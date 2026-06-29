<?php
require_once __DIR__.'/../core/Auth.php';require_once __DIR__.'/../core/Database.php';
class PayrollRepository
{
    public static function canManage(): bool{return Auth::isAdmin()||Auth::can('payroll.manage','edit');}
    public static function canViewAll(): bool{return self::canManage()||Auth::can('payroll.manage')||Auth::can('payroll.view_all');}
    public static function canImport(): bool{return Auth::isAdmin()||Auth::can('payroll.import','create');}
    public static function canPublish(): bool{return Auth::isAdmin()||Auth::can('payroll.publish','edit');}
    public static function slip(int $id,bool $employeeView=false): ?array{$slip=Database::fetch('SELECT s.*,p.title period_title,p.period_key,p.year,p.month,p.status period_status,u.name employee_name,u.department,u.role_key,u.employee_no current_employee_no,ou.title unit_title,orr.title job_title FROM payroll_slips s JOIN payroll_periods p ON p.id=s.period_id JOIN users u ON u.id=s.user_id LEFT JOIN org_units ou ON ou.id=u.org_unit_id LEFT JOIN org_roles orr ON orr.id=u.org_role_id WHERE s.id=?',[$id]);if(!$slip)return null;$user=Auth::user();if(!$user)return null;if($employeeView||!self::canViewAll()){if((int)$slip['user_id']!==(int)$user['id']||$slip['status']!=='published'||!in_array($slip['period_status'],['published','locked'],true))return null;}return $slip;}
    public static function values(int $slipId,bool $employee=false,bool $pdf=false): array{$where=['v.slip_id=?'];$params=[$slipId];if($employee)$where[]='f.visible_to_employee=1';if($pdf)$where[]='f.visible_in_pdf=1';return Database::fetchAll('SELECT v.*,f.field_type,f.data_type,f.visible_to_employee,f.visible_in_pdf FROM payroll_slip_values v LEFT JOIN payroll_fields f ON f.id=v.field_id WHERE '.implode(' AND ',$where).' ORDER BY v.sort_order,v.id',$params);}
    public static function assertPeriodEditable(int $periodId): array{$period=Database::fetch('SELECT * FROM payroll_periods WHERE id=?',[$periodId]);if(!$period)throw new InvalidArgumentException('دوره حقوقی معتبر نیست.');if($period['status']==='locked'&&!Auth::isSuperAdmin())throw new InvalidArgumentException('دوره قفل شده و فقط سوپرادمین امکان تغییر آن را دارد.');if($period['status']==='cancelled')throw new InvalidArgumentException('دوره لغوشده قابل تغییر نیست.');return $period;}
}
