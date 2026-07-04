<?php
require_once __DIR__.'/../core/Auth.php';
require_once __DIR__.'/../core/Database.php';
require_once __DIR__.'/../core/JalaliDate.php';

class ManagementReportsRepository
{
    public const TYPES = [
        'sales' => 'گزارش مدیران فروش',
        'finance' => 'گزارش مدیر مالی',
        'warehouse' => 'گزارش مدیر انبار',
        'technology' => 'گزارش مدیر فناوری',
    ];
    public const STATUSES = ['draft'=>'پیش‌نویس','submitted'=>'ثبت‌شده','returned'=>'برگشت برای اصلاح','approved'=>'تأییدشده','archived'=>'بایگانی‌شده'];
    public const FIELD_TYPES = ['text'=>'متن کوتاه','textarea'=>'متن بلند','number'=>'عدد','currency'=>'مبلغ','percent'=>'درصد','date'=>'تاریخ','select'=>'انتخابی','checkbox'=>'تیک تأیید','table'=>'جدول','repeater'=>'فهرست تکرارشونده','file'=>'فایل','readonly_metric'=>'شاخص فقط‌خواندنی'];

    public static function normalizedRole(?array $user=null): string
    {
        $user ??= Auth::user() ?: [];
        return strtoupper(preg_replace('/[^A-Z0-9]+/','_',strtoupper((string)($user['role_key']??''))));
    }

    public static function canPrepare(string $type, ?array $user=null): bool
    {
        $user ??= Auth::user();if(!$user||!isset(self::TYPES[$type]))return false;
        if(in_array($user['role']??'',['admin','super_admin'],true))return true;
        if(Auth::can('management_reports.'.$type,'create')||Auth::can('management_reports.'.$type,'edit'))return true;
        $roles=['sales'=>['SALES_MANAGER','SALES-MANAGER'],'finance'=>['FINANCE_MANAGER'],'warehouse'=>['WAREHOUSE_MANAGER'],'technology'=>['IT_MANAGER','TECHNOLOGY_MANAGER']];
        return in_array(self::normalizedRole($user),$roles[$type]??[],true);
    }

    public static function canReview(?array $user=null): bool
    {
        $user ??= Auth::user();if(!$user)return false;
        return in_array($user['role']??'',['admin','super_admin'],true)||Auth::can('management_reports.review','edit')||self::normalizedRole($user)==='INTERNAL_MANAGER';
    }

    public static function isCeo(?array $user=null): bool
    {
        $user ??= Auth::user();return self::normalizedRole($user)==='CEO';
    }

    public static function canAggregate(?array $user=null): bool
    {
        $user ??= Auth::user();if(!$user)return false;
        return self::canReview($user)||self::isCeo($user)||Auth::can('management_reports.aggregate');
    }

    public static function canManageTemplates(): bool
    {
        return Auth::isAdmin()||Auth::can('management_reports.templates','edit');
    }

    public static function template(string $type, bool $activeOnly=true): ?array
    {
        if(!isset(self::TYPES[$type]))return null;
        $template=Database::fetch('SELECT * FROM management_report_templates WHERE report_type=?'.($activeOnly?' AND active=1':'').' LIMIT 1',[$type]);
        if(!$template)return null;
        $template['sections']=Database::fetchAll('SELECT * FROM management_report_sections WHERE template_id=?'.($activeOnly?' AND active=1':'').' ORDER BY sort_order,id',[(int)$template['id']]);
        foreach($template['sections'] as &$section){
            $section['fields']=Database::fetchAll('SELECT * FROM management_report_fields WHERE section_id=?'.($activeOnly?' AND active=1':'').' ORDER BY sort_order,id',[(int)$section['id']]);
            foreach($section['fields'] as &$field)$field['options']=json_decode($field['options_json']?:'[]',true)?:[];
            unset($field);
        }unset($section);
        return $template;
    }

    public static function periods(): array
    {
        $rows=[];
        if(Database::tableExists('management_report_periods'))$rows=Database::fetchAll('SELECT code period_key,title period_title,start_date period_start,end_date period_end FROM management_report_periods WHERE active=1 ORDER BY sort_order,start_date DESC,id DESC');
        if($rows)return $rows;
        if(Database::tableExists('ceo_dashboard_periods'))$rows=Database::fetchAll('SELECT CONCAT("ceo-",id) period_key,title period_title,from_date period_start,to_date period_end FROM ceo_dashboard_periods WHERE active=1 ORDER BY COALESCE(to_date,from_date) DESC,id DESC LIMIT 24');
        if($rows)return $rows;
        for($i=0;$i<12;$i++){$date=(new DateTimeImmutable('first day of this month'))->modify('-'.$i.' month');$rows[]=['period_key'=>$date->format('Y-m'),'period_title'=>'دوره '.$date->format('Y-m'),'period_start'=>$date->format('Y-m-01'),'period_end'=>$date->format('Y-m-t')];}
        return $rows;
    }

    public static function submission(int $id): ?array
    {
        $row=Database::fetch('SELECT s.*,t.title template_title,u.name submitter_name,u.employee_no,ou.title unit_title,a.name approver_name FROM management_report_submissions s JOIN management_report_templates t ON t.id=s.template_id JOIN users u ON u.id=s.submitter_id LEFT JOIN org_units ou ON ou.id=s.unit_id LEFT JOIN users a ON a.id=s.approved_by WHERE s.id=?',[$id]);
        if(!$row||!self::canView($row))return null;
        $row['values']=Database::fetchAll('SELECT v.*,f.field_key,f.label,f.field_type,f.linked_source_key,f.options_json,sec.title section_title,sec.sort_order section_sort,f.sort_order field_sort FROM management_report_values v JOIN management_report_fields f ON f.id=v.field_id JOIN management_report_sections sec ON sec.id=f.section_id WHERE v.submission_id=? ORDER BY sec.sort_order,sec.id,f.sort_order,f.id',[$id]);
        $row['attachments']=Database::fetchAll('SELECT a.*,f.label field_label FROM management_report_attachments a LEFT JOIN management_report_fields f ON f.id=a.field_id WHERE a.submission_id=? ORDER BY a.id',[$id]);
        $row['reviews']=Database::fetchAll('SELECT r.*,u.name reviewer_name FROM management_report_reviews r LEFT JOIN users u ON u.id=r.created_by WHERE r.submission_id=? ORDER BY r.id DESC',[$id]);
        return $row;
    }

    public static function canView(array $submission, ?array $user=null): bool
    {
        $user ??= Auth::user();if(!$user)return false;
        if(self::canReview($user))return true;
        if(self::isCeo($user)||Auth::can('management_reports.aggregate'))return in_array($submission['status']??'',['approved','archived'],true);
        return (int)($submission['submitter_id']??0)===(int)$user['id']&&self::canPrepare((string)($submission['report_type']??''),$user);
    }

    public static function list(array $filters=[]): array
    {
        $user=Auth::user();if(!$user)return [];$where=['1=1'];$params=[];
        if(!self::canReview($user)){
            if(self::isCeo($user)||Auth::can('management_reports.aggregate'))$where[]="s.status IN ('approved','archived')";
            else{$where[]='s.submitter_id=?';$params[]=(int)$user['id'];$types=array_keys(array_filter(self::TYPES,static fn($v,$k)=>self::canPrepare($k),ARRAY_FILTER_USE_BOTH));if(!$types)return [];$where[]='s.report_type IN ('.implode(',',array_fill(0,count($types),'?')).')';array_push($params,...$types);}
        }
        foreach(['period_key','report_type','status'] as $key){$value=trim((string)($filters[$key]??''));if($value!==''){$where[]='s.'.$key.'=?';$params[]=$value;}}
        foreach(['submitter_id','unit_id'] as $key){$value=(int)($filters[$key]??0);if($value){$where[]='s.'.$key.'=?';$params[]=$value;}}
        if(!empty($filters['date_from'])){$where[]='DATE(s.created_at)>=?';$params[]=$filters['date_from'];}if(!empty($filters['date_to'])){$where[]='DATE(s.created_at)<=?';$params[]=$filters['date_to'];}
        return Database::fetchAll('SELECT s.*,t.title template_title,u.name submitter_name,u.employee_no,ou.title unit_title,a.name approver_name FROM management_report_submissions s JOIN management_report_templates t ON t.id=s.template_id JOIN users u ON u.id=s.submitter_id LEFT JOIN org_units ou ON ou.id=s.unit_id LEFT JOIN users a ON a.id=s.approved_by WHERE '.implode(' AND ',$where).' ORDER BY s.updated_at DESC,s.id DESC',$params);
    }

    public static function saveSubmission(string $type, int $id, array $period, array $postedValues, string $targetStatus): int
    {
        $user=Auth::user();if(!$user||!self::canPrepare($type,$user))throw new RuntimeException('permission_denied');
        $template=self::template($type);if(!$template)throw new InvalidArgumentException('قالب فعال برای این نوع گزارش پیدا نشد.');
        if(!in_array($targetStatus,['draft','submitted'],true))$targetStatus='draft';
        $periodKey=trim((string)($period['period_key']??''));$periodTitle=trim((string)($period['period_title']??''));if($periodKey===''||$periodTitle==='')throw new InvalidArgumentException('دوره گزارش را انتخاب کنید.');
        $existing=$id?Database::fetch('SELECT * FROM management_report_submissions WHERE id=?',[$id]):Database::fetch('SELECT * FROM management_report_submissions WHERE template_id=? AND period_key=? AND submitter_id=?',[(int)$template['id'],$periodKey,(int)$user['id']]);
        if($existing){if((int)$existing['submitter_id']!==(int)$user['id']||!in_array($existing['status'],['draft','returned'],true))throw new InvalidArgumentException('این گزارش در وضعیت قابل ویرایش نیست.');$id=(int)$existing['id'];}
        $persistStatus=$targetStatus==='draft'&&($existing['status']??'')==='returned'?'returned':$targetStatus;
        $fields=[];foreach($template['sections'] as $section)foreach($section['fields'] as $field)$fields[(int)$field['id']]=$field;
        $normalized=[];$errors=[];
        foreach($fields as $fieldId=>$field){
            $raw=$postedValues[$fieldId]??null;$typeName=$field['field_type'];
            if($typeName==='readonly_metric'){$text=trim((string)($field['default_value']??''));$normalized[$fieldId]=['text'=>$text,'number'=>is_numeric($text)?(float)$text:null,'json'=>null];$empty=$text==='';}
            elseif(in_array($typeName,['table','repeater'],true)){$decoded=is_array($raw)?$raw:json_decode((string)$raw,true);$decoded=is_array($decoded)?array_values($decoded):[];$normalized[$fieldId]=['text'=>null,'number'=>null,'json'=>json_encode($decoded,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)];$empty=!$decoded;}
            elseif(in_array($typeName,['number','currency','percent'],true)){$empty=trim((string)$raw)==='';$clean=strtr((string)$raw,['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','٬'=>'','،'=>'']);$clean=str_replace([',',' '],'',$clean);if(!$empty&&!is_numeric($clean))$errors[]='مقدار عددی «'.$field['label'].'» معتبر نیست.';$number=self::number($raw);if($typeName==='percent'&&($number<0||$number>100))$errors[]='مقدار «'.$field['label'].'» باید بین صفر تا صد باشد.';$normalized[$fieldId]=['text'=>null,'number'=>$number,'json'=>null];}
            elseif($typeName==='date'){$text=trim((string)$raw);if(str_contains($text,'/'))$text=JalaliDate::toGregorian($text)??'';$date=$text!==''?DateTimeImmutable::createFromFormat('!Y-m-d',$text):false;if($text!==''&&(!$date||$date->format('Y-m-d')!==$text))$errors[]='تاریخ «'.$field['label'].'» معتبر نیست.';$normalized[$fieldId]=['text'=>$text,'number'=>null,'json'=>null];$empty=$text==='';}
            elseif($typeName==='select'){$text=trim((string)$raw);$options=json_decode($field['options_json']?:'[]',true)?:[];$allowed=[];foreach($options as $option)$allowed[]=(string)(is_array($option)?($option['value']??''):$option);if($text!==''&&!in_array($text,$allowed,true))$errors[]='گزینه انتخاب‌شده برای «'.$field['label'].'» معتبر نیست.';$normalized[$fieldId]=['text'=>$text,'number'=>null,'json'=>null];$empty=$text==='';}
            elseif($typeName==='checkbox'){$checked=!empty($raw)?'1':'0';$normalized[$fieldId]=['text'=>$checked,'number'=>(float)$checked,'json'=>null];$empty=$checked==='0';}
            elseif($typeName==='file')continue;
            else{$text=trim((string)$raw);if(strlen($text)>2000000)$errors[]='حجم فیلد «'.$field['label'].'» بیش از حد مجاز است.';$normalized[$fieldId]=['text'=>$text,'number'=>null,'json'=>null];$empty=$text==='';}
            if($targetStatus==='submitted'&&(int)$field['is_required']&&$empty)$errors[]='تکمیل فیلد «'.$field['label'].'» الزامی است.';
        }
        if($errors)throw new InvalidArgumentException(implode(' ',$errors));
        $pdo=Database::connection();$pdo->beginTransaction();try{
            $oldStatus=$existing['status']??null;
            if($id)Database::execute('UPDATE management_report_submissions SET period_key=?,period_title=?,period_start=?,period_end=?,unit_id=?,status=?,submitted_at=IF(?="submitted",NOW(),submitted_at),return_note=IF(?="submitted",NULL,return_note),updated_at=NOW() WHERE id=?',[$periodKey,$periodTitle,$period['period_start']?:null,$period['period_end']?:null,(int)($user['org_unit_id']??0)?:null,$persistStatus,$targetStatus,$targetStatus,$id]);
            else{Database::execute('INSERT INTO management_report_submissions(template_id,report_type,period_key,period_title,period_start,period_end,submitter_id,unit_id,status,submitted_at,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,IF(?="submitted",NOW(),NULL),NOW(),NOW())',[(int)$template['id'],$type,$periodKey,$periodTitle,$period['period_start']?:null,$period['period_end']?:null,(int)$user['id'],(int)($user['org_unit_id']??0)?:null,$persistStatus,$targetStatus]);$id=(int)Database::lastInsertId();}
            foreach($normalized as $fieldId=>$value)Database::execute('INSERT INTO management_report_values(submission_id,field_id,value_text,value_number,value_json,created_at,updated_at) VALUES(?,?,?,?,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE value_text=VALUES(value_text),value_number=VALUES(value_number),value_json=VALUES(value_json),updated_at=NOW()',[$id,$fieldId,$value['text'],$value['number'],$value['json']]);
            $action=$targetStatus==='submitted'?'submitted':($existing?'draft_saved':'created');self::reviewLog($id,$action,$oldStatus,$persistStatus,null,(int)$user['id']);
            $pdo->commit();return $id;
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }

    public static function review(int $id, string $action, string $note=''): void
    {
        if(!self::canReview())throw new RuntimeException('permission_denied');$row=Database::fetch('SELECT * FROM management_report_submissions WHERE id=?',[$id]);if(!$row)throw new InvalidArgumentException('گزارش پیدا نشد.');
        $pdo=Database::connection();$pdo->beginTransaction();try{
            if($action==='approve'){if($row['status']!=='submitted')throw new InvalidArgumentException('فقط گزارش ثبت‌شده قابل تأیید است.');$new='approved';Database::execute('UPDATE management_report_submissions SET status="approved",approved_at=NOW(),approved_by=?,return_note=NULL,updated_at=NOW() WHERE id=?',[(int)Auth::user()['id'],$id]);}
            elseif($action==='return'){if($row['status']!=='submitted')throw new InvalidArgumentException('فقط گزارش ثبت‌شده قابل برگشت است.');if(trim($note)==='')throw new InvalidArgumentException('توضیح برگشت برای اصلاح الزامی است.');$new='returned';Database::execute('UPDATE management_report_submissions SET status="returned",returned_at=NOW(),return_note=?,approved_at=NULL,approved_by=NULL,updated_at=NOW() WHERE id=?',[$note,$id]);}
            elseif($action==='archive'){if(!in_array($row['status'],['approved','returned'],true))throw new InvalidArgumentException('این گزارش قابل بایگانی نیست.');$new='archived';Database::execute('UPDATE management_report_submissions SET status="archived",archived_at=NOW(),updated_at=NOW() WHERE id=?',[$id]);}
            else throw new InvalidArgumentException('عملیات بررسی معتبر نیست.');
            self::reviewLog($id,$action==='approve'?'approved':($action==='return'?'returned':'archived'),$row['status'],$new,$note,(int)Auth::user()['id']);Auth::log((int)Auth::user()['id'],'management_report_'.$action,'management_report_submissions',$id);$pdo->commit();
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }

    public static function addAttachment(int $submissionId, int $fieldId, array $upload, bool $allowJustSubmitted=false): void
    {
        $submission=Database::fetch('SELECT * FROM management_report_submissions WHERE id=?',[$submissionId]);$editable=in_array($submission['status']??'',['draft','returned'],true)||($allowJustSubmitted&&($submission['status']??'')==='submitted');if(!$submission||!self::canView($submission)||!$editable||(int)$submission['submitter_id']!==(int)Auth::user()['id'])throw new RuntimeException('permission_denied');
        Database::execute('INSERT INTO management_report_attachments(submission_id,field_id,original_name,storage_path,mime_type,file_size,created_by,created_at) VALUES(?,?,?,?,?,?,?,NOW())',[$submissionId,$fieldId?:null,substr((string)$upload['original_name'],0,255),$upload['file_path'],$upload['mime_type'],(int)$upload['file_size'],(int)Auth::user()['id']]);
    }

    public static function stats(array $rows): array
    {
        $stats=array_fill_keys(array_keys(self::STATUSES),0);foreach($rows as $row)if(isset($stats[$row['status']]))$stats[$row['status']]++;return $stats;
    }

    private static function reviewLog(int $submissionId,string $action,?string $old,?string $new,?string $note,int $userId): void
    {Database::execute('INSERT INTO management_report_reviews(submission_id,action,old_status,new_status,note,created_by,created_at) VALUES(?,?,?,?,?,?,NOW())',[$submissionId,$action,$old,$new,$note,$userId]);}
    private static function number(mixed $value): float
    {$value=strtr((string)$value,['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','٬'=>'','،'=>'']);$value=str_replace([',',' '],'',$value);return is_numeric($value)?(float)$value:0.0;}
}
