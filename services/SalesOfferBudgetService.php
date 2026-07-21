<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/SalesOfferBudgetModule.php';
require_once __DIR__ . '/../lib/AppDate.php';
require_once __DIR__ . '/FormulaRuntime.php';

class SalesOfferBudgetService
{
    public const PERMISSION='sales_manager.offer_budget.manage';
    public const STATUSES=['draft','submitted','under_review','approved','rejected','needs_revision'];

    public static function repairSchema(): void { SalesOfferBudgetModule::repair(Database::connection()); }
    public static function requireAccess(): void { Auth::requireLogin(); if(!Auth::isAdmin()&&!Auth::can(self::PERMISSION)){http_response_code(403);echo 'دسترسی غیرمجاز';exit;} }
    public static function isAdmin(?array $user=null): bool { $user=$user?:Auth::user(); return in_array((string)($user['role']??''),['admin','super_admin'],true); }
    public static function calculateProvisionalBudget(array $data): array
    {
        $price=self::number($data['purchase_price']??0,'قیمت خرید');$qty=self::number($data['requested_offer_qty']??0,'تعداد آفر');$soldQty=self::number($data['sold_qty']??0,'تعداد فروش');$soldAmount=self::number($data['sold_amount']??0,'مبلغ فروش');$rate=self::number($data['provisional_offer_rate']??0,'نرخ آفر');
        if($price<0||$qty<0||$soldQty<0||$soldAmount<0||$rate<0)throw new InvalidArgumentException('مقادیر عددی نمی‌توانند منفی باشند.');
        $base=$price*$qty;$budget=$base*$rate;$formulaVersion='provisional_v1';
        $runtime=FormulaRuntime::evaluateByKey('offer_budget_provisional',[
            'purchase_price'=>$price,'requested_offer_qty'=>$qty,'purchase_base'=>$base,
            'sold_qty'=>$soldQty,'sold_amount'=>$soldAmount,'offer_rate_percent'=>$rate*100,
        ]);
        if($runtime!==null){$budget=max(0,(float)$runtime['final_result']);$formulaVersion='formula_builder_v'.$runtime['formula_version_no'];}
        return ['purchase_price'=>$price,'requested_offer_qty'=>$qty,'purchase_base'=>$base,'sold_qty'=>$soldQty,'sold_amount'=>$soldAmount,'sales_ratio'=>$base>0?$soldAmount/$base:0,'provisional_offer_rate'=>$rate,'provisional_budget'=>$budget,'formula_version'=>$formulaVersion,'calculated_at'=>date('c')];
    }
    private static function number(mixed $value,string $label): float { if($value===''||$value===null)return 0;if(!is_numeric($value))throw new InvalidArgumentException($label.' معتبر نیست.');return (float)$value; }
    private static function normalized(array $data): array
    {
        $snapshot=self::calculateProvisionalBudget($data);$periodKey=trim((string)($data['period_key']??''));$rawFrom=trim((string)($data['date_from']??''));$rawTo=trim((string)($data['date_to']??''));$from=$rawFrom===''?null:AppDate::toGregorian($rawFrom);$to=$rawTo===''?null:AppDate::toGregorian($rawTo);
        if(($rawFrom!==''&&$from===null)||($rawTo!==''&&$to===null))throw new InvalidArgumentException('بازه تاریخ معتبر نیست.');
        if($periodKey!==''&&(!$from||!$to)){try{$period=AppDate::resolvePeriod($periodKey,$rawFrom,$rawTo);$from=$period['start_date'];$to=$period['end_date'];}catch(Throwable $e){if(!$from||!$to)throw new InvalidArgumentException('دوره انتخاب‌شده معتبر نیست.');}}
        if($from&&$to&&$from>$to)throw new InvalidArgumentException('تاریخ شروع باید پیش از تاریخ پایان باشد.');
        return ['period_key'=>$periodKey?:null,'date_from'=>$from,'date_to'=>$to,'sales_line'=>trim((string)($data['sales_line']??''))?:null,'product_code'=>trim((string)($data['product_code']??''))?:null,'product_name'=>trim((string)($data['product_name']??''))?:null,'brand_name'=>trim((string)($data['brand_name']??''))?:null,'supplier_name'=>trim((string)($data['supplier_name']??''))?:null,'manager_note'=>trim((string)($data['manager_note']??''))?:null,'snapshot'=>$snapshot];
    }
    public static function createRequest(array $data,int $userId): int
    {
        $n=self::normalized($data);$user=Auth::user();$managerId=self::isAdmin($user)?((int)($data['sales_manager_id']??0)?:$userId):$userId;$line=self::isAdmin($user)?$n['sales_line']:((string)($user['sales_line']??'')?:$n['sales_line']);$code='OFR-'.date('YmdHis').'-'.str_pad((string)random_int(0,9999),4,'0',STR_PAD_LEFT);$s=$n['snapshot'];
        Database::execute('INSERT INTO sales_offer_budget_requests(request_code,requested_by,sales_manager_id,sales_line,period_key,date_from,date_to,product_code,product_name,brand_name,supplier_name,purchase_price,requested_offer_qty,sold_qty,sold_amount,provisional_offer_rate,provisional_budget,formula_version,formula_snapshot_json,status,manager_note,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,"draft",?,NOW(),NOW())',[$code,$userId,$managerId,$line,$n['period_key'],$n['date_from'],$n['date_to'],$n['product_code'],$n['product_name'],$n['brand_name'],$n['supplier_name'],$s['purchase_price'],$s['requested_offer_qty'],$s['sold_qty'],$s['sold_amount'],$s['provisional_offer_rate'],$s['provisional_budget'],$s['formula_version'],self::json($s),$n['manager_note']]);$id=(int)Database::connection()->lastInsertId();self::logAction($id,'create',null,['request_code'=>$code,'status'=>'draft','formula_snapshot'=>$s],$userId);return $id;
    }
    public static function updateRequest(int $id,array $data,int $userId): void
    {
        $old=self::getRequest($id,Auth::user());if(!$old)throw new InvalidArgumentException('استعلام پیدا نشد.');if(!self::isAdmin()&&($old['status']!=='draft'||(int)$old['requested_by']!==$userId))throw new InvalidArgumentException('فقط پیش‌نویس خودتان قابل ویرایش است.');$n=self::normalized($data);$s=$n['snapshot'];$line=self::isAdmin()?$n['sales_line']:((string)(Auth::user()['sales_line']??'')?:$n['sales_line']);Database::execute('UPDATE sales_offer_budget_requests SET sales_line=?,period_key=?,date_from=?,date_to=?,product_code=?,product_name=?,brand_name=?,supplier_name=?,purchase_price=?,requested_offer_qty=?,sold_qty=?,sold_amount=?,provisional_offer_rate=?,provisional_budget=?,formula_version=?,formula_snapshot_json=?,manager_note=?,updated_at=NOW() WHERE id=?',[$line,$n['period_key'],$n['date_from'],$n['date_to'],$n['product_code'],$n['product_name'],$n['brand_name'],$n['supplier_name'],$s['purchase_price'],$s['requested_offer_qty'],$s['sold_qty'],$s['sold_amount'],$s['provisional_offer_rate'],$s['provisional_budget'],$s['formula_version'],self::json($s),$n['manager_note'],$id]);self::logAction($id,'update',$old,['formula_snapshot'=>$s],$userId);
    }
    public static function getRequest(int $id,array $userContext): ?array {$r=Database::fetch('SELECT r.*,u.name requested_by_name,m.name sales_manager_name,rv.name reviewed_by_name FROM sales_offer_budget_requests r LEFT JOIN users u ON u.id=r.requested_by LEFT JOIN users m ON m.id=r.sales_manager_id LEFT JOIN users rv ON rv.id=r.reviewed_by WHERE r.id=?',[$id]);return $r&&self::canAccessRequest($r,$userContext)?$r:null;}
    public static function listRequests(array $filters,array $userContext): array
    {
        $where=[];$p=[];if(!self::isAdmin($userContext)){$where[]='(r.requested_by=? OR r.sales_manager_id=?'.(!empty($userContext['sales_line'])?' OR r.sales_line=?':'').')';$p[]=(int)$userContext['id'];$p[]=(int)$userContext['id'];if(!empty($userContext['sales_line']))$p[]=$userContext['sales_line'];}
        foreach(['period_key','sales_line','status'] as $k)if(trim((string)($filters[$k]??''))!==''){$where[]="r.{$k}=?";$p[]=trim((string)$filters[$k]);}foreach(['product','brand_name'] as $k)if(trim((string)($filters[$k]??''))!==''){$column=$k==='product'?'CONCAT_WS(" ",r.product_code,r.product_name)':'r.brand_name';$where[]="$column LIKE ?";$p[]='%'.trim((string)$filters[$k]).'%';}$dateFrom=AppDate::toGregorian((string)($filters['date_from']??''));$dateTo=AppDate::toGregorian((string)($filters['date_to']??''));if($dateFrom){$where[]='r.date_to>=?';$p[]=$dateFrom;}if($dateTo){$where[]='r.date_from<=?';$p[]=$dateTo;}
        return Database::fetchAll('SELECT r.*,u.name requested_by_name FROM sales_offer_budget_requests r LEFT JOIN users u ON u.id=r.requested_by'.($where?' WHERE '.implode(' AND ',$where):'').' ORDER BY r.id DESC LIMIT 500',$p);
    }
    public static function changeStatus(int $id,string $status,string $note,int $userId): void {$old=self::getRequest($id,Auth::user());if(!$old)throw new InvalidArgumentException('استعلام پیدا نشد.');if(!self::isAdmin()){if($status!=='submitted'||$old['status']!=='draft'||(int)$old['requested_by']!==$userId)throw new InvalidArgumentException('مجوز تغییر این وضعیت را ندارید.');Database::execute('UPDATE sales_offer_budget_requests SET status="submitted",updated_at=NOW() WHERE id=?',[$id]);self::logAction($id,'submit',['status'=>'draft'],['status'=>'submitted'],$userId);return;}if(!in_array($status,self::STATUSES,true)||$status==='draft')throw new InvalidArgumentException('وضعیت معتبر نیست.');Database::execute('UPDATE sales_offer_budget_requests SET status=?,admin_note=?,reviewed_by=?,reviewed_at=NOW(),updated_at=NOW() WHERE id=?',[$status,trim($note)?:null,$userId,$id]);self::logAction($id,'status_change',['status'=>$old['status'],'admin_note'=>$old['admin_note']],['status'=>$status,'admin_note'=>$note],$userId);}
    public static function logAction(int $requestId,string $action,mixed $oldValue,mixed $newValue,int $userId): void {Database::execute('INSERT INTO sales_offer_budget_logs(request_id,action,performed_by,old_value_json,new_value_json,created_at) VALUES (?,?,?,?,?,NOW())',[$requestId,$action,$userId?:null,self::json($oldValue),self::json($newValue)]);}
    public static function canAccessRequest(array $request,array $userContext): bool {if(self::isAdmin($userContext))return true;$id=(int)($userContext['id']??0);return (int)$request['requested_by']===$id||(int)$request['sales_manager_id']===$id||(!empty($userContext['sales_line'])&&hash_equals((string)$userContext['sales_line'],(string)$request['sales_line']));}
    public static function getAvailableFormulaSettings(): array {return Database::fetchAll('SELECT * FROM sales_offer_formula_settings WHERE active=1 ORDER BY id');}
    public static function logs(int $id): array {return Database::fetchAll('SELECT l.*,u.name performed_by_name FROM sales_offer_budget_logs l LEFT JOIN users u ON u.id=l.performed_by WHERE l.request_id=? ORDER BY l.id DESC',[$id]);}
    public static function statusLabel(string $s): string{return ['draft'=>'پیش‌نویس','submitted'=>'ارسال‌شده','under_review'=>'در حال بررسی','approved'=>'تأیید شده','rejected'=>'رد شده','needs_revision'=>'نیازمند اصلاح'][$s]??$s;}
    public static function uiError(Throwable $e,string $fallback): string {if($e instanceof InvalidArgumentException)return $e->getMessage();error_log('Sales offer budget: '.$e->getMessage());return $fallback;}
    private static function json(mixed $v): ?string {if($v===null)return null;$j=json_encode($v,JSON_UNESCAPED_UNICODE|JSON_PRESERVE_ZERO_FRACTION);if($j===false)throw new RuntimeException('JSON encode failed');return $j;}
}
