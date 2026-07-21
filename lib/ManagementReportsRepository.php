<?php
require_once __DIR__.'/../core/Auth.php';
require_once __DIR__.'/../core/Database.php';
require_once __DIR__.'/AppDate.php';
require_once __DIR__.'/../services/ActionHubService.php';
require_once __DIR__.'/../services/WorkPlannerService.php';

class ManagementReportsRepository
{
    public const TYPES = [
        'sales' => 'گزارش مدیران فروش',
        'finance' => 'گزارش مدیر مالی',
        'warehouse' => 'گزارش مدیر انبار',
        'technology' => 'گزارش مدیر فناوری',
    ];
    public const STATUSES = ['draft'=>'پیش‌نویس','submitted'=>'ثبت‌شده','returned'=>'برگشت برای اصلاح','approved'=>'تأییدشده','archived'=>'بایگانی‌شده'];
    public const FIELD_TYPES = [
        'editable'=>'فیلد قابل ویرایش',
        'readonly_calculated'=>'فیلد محاسباتی فقط‌خواندنی',
        'task_selector'=>'انتخاب وظیفه',
        'action_selector'=>'انتخاب اقدام',
        'user_selector'=>'انتخاب کاربر',
        'jalali_date'=>'تاریخ شمسی',
        'jalali_deadline'=>'مهلت شمسی',
        'number'=>'عدد',
        'percentage'=>'درصد',
        'amount'=>'مبلغ',
        'text'=>'متن کوتاه',
        'attachment'=>'پیوست',
        // Legacy types remain readable for backward compatibility.
        'textarea'=>'متن بلند قدیمی',
        'currency'=>'مبلغ قدیمی',
        'percent'=>'درصد قدیمی',
        'date'=>'تاریخ قدیمی',
        'select'=>'انتخابی قدیمی',
        'checkbox'=>'تیک تأیید قدیمی',
        'table'=>'جدول قدیمی',
        'repeater'=>'فهرست تکرارشونده قدیمی',
        'file'=>'فایل قدیمی',
        'readonly_metric'=>'شاخص فقط‌خواندنی قدیمی',
    ];
    public const SOURCE_KEYS = [
        ''=>'ورودی دستی',
        'action_hub'=>'مرکز اقدام',
        'work_planner'=>'برنامه کاری',
        'authorized_users'=>'کاربران مجاز',
        'sales_gross_amount'=>'فروش ناخالص',
        'sales_net_amount'=>'فروش خالص',
        'target_achievement_percent'=>'درصد تحقق تارگت',
        'finance_receivables_amount'=>'مطالبات مالی',
        'inventory_accuracy'=>'دقت موجودی',
        'warehouse_damage_rate'=>'نرخ آسیب انبار',
        'it_backup_status'=>'وضعیت پشتیبان‌گیری فناوری',
    ];

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

    public static function templateSnapshot(array $template): string
    {
        $snapshot = json_encode($template, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        if ($snapshot === false) throw new RuntimeException('ساخت نسخه ثابت قالب گزارش انجام نشد.');
        return $snapshot;
    }

    public static function templateForSubmission(array $submission): ?array
    {
        $snapshot = json_decode((string)($submission['schema_snapshot_json'] ?? ''), true);
        if (is_array($snapshot) && !empty($snapshot['sections'])) {
            foreach ($snapshot['sections'] as &$section) {
                foreach ($section['fields'] ?? [] as &$field) {
                    $field['options'] = json_decode((string)($field['options_json'] ?? ''), true) ?: [];
                }
                unset($field);
            }
            unset($section);
            return $snapshot;
        }
        return self::template((string)($submission['report_type'] ?? ''), false);
    }

    public static function bumpTemplateVersion(int $templateId): void
    {
        Database::execute('UPDATE management_report_templates SET version_no=version_no+1,updated_by=?,updated_at=NOW() WHERE id=?', [(int)(Auth::user()['id'] ?? 0) ?: null,$templateId]);
    }

    public static function optionsText(?string $optionsJson, string $fieldType): string
    {
        $options = json_decode((string)$optionsJson, true);
        if (!is_array($options)) return '';
        if (in_array($fieldType, ['table','repeater'], true)) {
            $options = $options['columns'] ?? [];
        }
        $lines = [];
        foreach ($options as $option) {
            if (is_array($option)) {
                $value = (string)($option['value'] ?? $option['key'] ?? '');
                $label = (string)($option['label'] ?? $value);
                if ($value !== '') $lines[] = $value.'|'.$label;
            } elseif ((string)$option !== '') {
                $lines[] = (string)$option;
            }
        }
        return implode("\n",$lines);
    }

    public static function parseOptionsText(string $text, string $fieldType): ?string
    {
        $rows = array_values(array_filter(array_map('trim', preg_split('/\R/u', $text) ?: [])));
        if (!$rows) return null;
        $options = [];
        foreach ($rows as $row) {
            [$value,$label] = array_pad(array_map('trim', explode('|',$row,2)),2,'');
            $value = strtolower(preg_replace('/[^a-zA-Z0-9_.-]+/','_',$value));
            if ($value === '') continue;
            $options[] = in_array($fieldType, ['table','repeater'], true)
                ? ['key'=>$value,'label'=>$label !== '' ? $label : $value]
                : ['value'=>$value,'label'=>$label !== '' ? $label : $value];
        }
        if (!$options) return null;
        $payload = in_array($fieldType, ['table','repeater'], true) ? ['columns'=>$options] : $options;
        return json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?: null;
    }

    public static function periods(): array
    {
        try{
            $rows=AppDate::periods(['daily','weekly','monthly','quarterly','half_yearly','yearly']);
            if($rows)return array_map(static fn($row)=>['period_key'=>$row['period_key'],'period_title'=>$row['title'],'period_start'=>$row['start_date'],'period_end'=>$row['end_date'],'period_type'=>$row['period_type']],$rows);
        }catch(Throwable $e){error_log('Management report periods: '.$e->getMessage());}
        return array_map(static fn($row)=>['period_key'=>$row['period_key'],'period_title'=>$row['title'],'period_start'=>$row['start_date'],'period_end'=>$row['end_date'],'period_type'=>$row['period_type']],array_filter(AppDate::defaultPeriodCatalog(),static fn($row)=>$row['period_type']!=='custom'));
    }

    public static function submission(int $id): ?array
    {
        $row=Database::fetch('SELECT s.*,t.title template_title,u.name submitter_name,u.employee_no,ou.title unit_title,a.name approver_name FROM management_report_submissions s JOIN management_report_templates t ON t.id=s.template_id JOIN users u ON u.id=s.submitter_id LEFT JOIN org_units ou ON ou.id=s.unit_id LEFT JOIN users a ON a.id=s.approved_by WHERE s.id=?',[$id]);
        if(!$row||!self::canView($row))return null;
        $schema=self::templateForSubmission($row);$fieldMap=[];$order=[];
        foreach($schema['sections']??[] as $section){
            foreach($section['fields']??[] as $field){
                $fieldId=(int)($field['id']??0);if(!$fieldId)continue;
                $field['section_title']=$section['title']??'گزارش';
                $field['section_sort']=(int)($section['sort_order']??0);
                $fieldMap[$fieldId]=$field;
                $order[$fieldId]=sprintf('%010d:%010d:%010d',(int)($section['sort_order']??0),(int)($field['sort_order']??0),$fieldId);
            }
        }
        $values=Database::fetchAll('SELECT * FROM management_report_values WHERE submission_id=?',[$id]);
        foreach($values as &$value){
            $field=$fieldMap[(int)$value['field_id']]??[];
            $value=array_merge($value,[
                'field_key'=>$field['field_key']??('field_'.$value['field_id']),
                'label'=>$field['label']??'فیلد گزارش',
                'field_type'=>$field['field_type']??'text',
                'linked_source_key'=>$field['linked_source_key']??null,
                'options_json'=>$field['options_json']??null,
                'section_title'=>$field['section_title']??'گزارش',
                'section_sort'=>$field['section_sort']??0,
                'field_sort'=>$field['sort_order']??0,
            ]);
        }
        unset($value);
        usort($values,static fn($a,$b)=>strcmp($order[(int)$a['field_id']]??'9999999999',$order[(int)$b['field_id']]??'9999999999'));
        $row['values']=$values;
        $row['attachments']=Database::fetchAll('SELECT a.* FROM management_report_attachments a WHERE a.submission_id=? ORDER BY a.id',[$id]);
        foreach($row['attachments'] as &$attachment)$attachment['field_label']=$fieldMap[(int)($attachment['field_id']??0)]['label']??'پیوست';
        unset($attachment);
        $row['links']=Database::tableExists('management_report_links')?Database::fetchAll('SELECT * FROM management_report_links WHERE submission_id=? AND active=1 ORDER BY id',[$id]):[];
        $row['schema']=$schema;
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
        $dateFrom=AppDate::toGregorian((string)($filters['date_from']??''));$dateTo=AppDate::toGregorian((string)($filters['date_to']??''));if($dateFrom){$where[]='DATE(s.created_at)>=?';$params[]=$dateFrom;}if($dateTo){$where[]='DATE(s.created_at)<=?';$params[]=$dateTo;}
        return Database::fetchAll('SELECT s.*,t.title template_title,u.name submitter_name,u.employee_no,ou.title unit_title,a.name approver_name FROM management_report_submissions s JOIN management_report_templates t ON t.id=s.template_id JOIN users u ON u.id=s.submitter_id LEFT JOIN org_units ou ON ou.id=s.unit_id LEFT JOIN users a ON a.id=s.approved_by WHERE '.implode(' AND ',$where).' ORDER BY s.updated_at DESC,s.id DESC',$params);
    }

    public static function saveSubmission(string $type, int $id, array $period, array $postedValues, string $targetStatus): int
    {
        $user=Auth::user();if(!$user||!self::canPrepare($type,$user))throw new RuntimeException('permission_denied');
        if(!in_array($targetStatus,['draft','submitted'],true))$targetStatus='draft';
        $periodKey=trim((string)($period['period_key']??''));$periodTitle=trim((string)($period['period_title']??''));if($periodKey===''||$periodTitle==='')throw new InvalidArgumentException('دوره گزارش را انتخاب کنید.');
        $currentTemplate=self::template($type);if(!$currentTemplate)throw new InvalidArgumentException('قالب فعال برای این نوع گزارش پیدا نشد.');
        $existing=$id?Database::fetch('SELECT * FROM management_report_submissions WHERE id=?',[$id]):Database::fetch('SELECT * FROM management_report_submissions WHERE template_id=? AND period_key=? AND submitter_id=?',[(int)$currentTemplate['id'],$periodKey,(int)$user['id']]);
        if($existing){if((int)$existing['submitter_id']!==(int)$user['id']||!in_array($existing['status'],['draft','returned'],true))throw new InvalidArgumentException('این گزارش در وضعیت قابل ویرایش نیست.');$id=(int)$existing['id'];}
        $template=$existing?(self::templateForSubmission($existing)?:$currentTemplate):$currentTemplate;
        $persistStatus=$targetStatus==='draft'&&($existing['status']??'')==='returned'?'returned':$targetStatus;
        $fields=[];foreach($template['sections'] as $section)foreach($section['fields'] as $field)$fields[(int)$field['id']]=$field;
        $normalized=[];$errors=[];
        foreach($fields as $fieldId=>$field){
            $raw=$postedValues[$fieldId]??null;$typeName=$field['field_type'];
            if(in_array($typeName,['readonly_metric','readonly_calculated'],true)){$text=trim((string)($field['default_value']??''));$normalized[$fieldId]=['text'=>$text,'number'=>is_numeric($text)?(float)$text:null,'json'=>null];$empty=$text==='';}
            elseif(in_array($typeName,['table','repeater'],true)){$decoded=is_array($raw)?$raw:json_decode((string)$raw,true);$decoded=is_array($decoded)?array_values($decoded):[];$normalized[$fieldId]=['text'=>null,'number'=>null,'json'=>json_encode($decoded,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)];$empty=!$decoded;}
            elseif(in_array($typeName,['number','currency','percent','amount','percentage'],true)){$empty=trim((string)$raw)==='';$clean=strtr((string)$raw,['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','٬'=>'','،'=>'']);$clean=str_replace([',',' '],'',$clean);if(!$empty&&!is_numeric($clean))$errors[]='مقدار عددی «'.$field['label'].'» معتبر نیست.';$number=self::number($raw);if(in_array($typeName,['percent','percentage'],true)&&($number<0||$number>100))$errors[]='مقدار «'.$field['label'].'» باید بین صفر تا صد باشد.';$normalized[$fieldId]=['text'=>null,'number'=>$number,'json'=>null];}
            elseif(in_array($typeName,['date','jalali_date','jalali_deadline'],true)){$rawDate=trim((string)$raw);$text=$rawDate===''?'':(AppDate::toGregorian($rawDate)??'');if($rawDate!==''&&$text==='')$errors[]='تاریخ «'.$field['label'].'» معتبر نیست.';$normalized[$fieldId]=['text'=>$text,'number'=>null,'json'=>null];$empty=$text==='';}
            elseif($typeName==='select'){$text=trim((string)$raw);$options=json_decode($field['options_json']?:'[]',true)?:[];$allowed=[];foreach($options as $option)$allowed[]=(string)(is_array($option)?($option['value']??''):$option);if($text!==''&&!in_array($text,$allowed,true))$errors[]='گزینه انتخاب‌شده برای «'.$field['label'].'» معتبر نیست.';$normalized[$fieldId]=['text'=>$text,'number'=>null,'json'=>null];$empty=$text==='';}
            elseif(in_array($typeName,['task_selector','action_selector','user_selector'],true)){
                $selected=max(0,(int)$raw);$empty=$selected<1;
                if($selected){
                    if($typeName==='task_selector'&&!WorkPlannerService::canUserAccessTask((int)$user['id'],$selected))$errors[]='وظیفه انتخاب‌شده برای «'.$field['label'].'» در محدوده دسترسی شما نیست.';
                    if($typeName==='action_selector'&&!ActionHubService::action($selected,$user))$errors[]='اقدام انتخاب‌شده برای «'.$field['label'].'» در محدوده دسترسی شما نیست.';
                    if($typeName==='user_selector'&&!OrgAccess::canAccessUser($user,$selected))$errors[]='کاربر انتخاب‌شده برای «'.$field['label'].'» در محدوده دسترسی شما نیست.';
                }
                $normalized[$fieldId]=['text'=>null,'number'=>$selected?:null,'json'=>null];
            }
            elseif($typeName==='checkbox'){$checked=!empty($raw)?'1':'0';$normalized[$fieldId]=['text'=>$checked,'number'=>(float)$checked,'json'=>null];$empty=$checked==='0';}
            elseif(in_array($typeName,['file','attachment'],true))continue;
            else{$text=trim((string)$raw);if(strlen($text)>2000000)$errors[]='حجم فیلد «'.$field['label'].'» بیش از حد مجاز است.';$normalized[$fieldId]=['text'=>$text,'number'=>null,'json'=>null];$empty=$text==='';}
            if($targetStatus==='submitted'&&(int)$field['is_required']&&$empty)$errors[]='تکمیل فیلد «'.$field['label'].'» الزامی است.';
        }
        if($errors)throw new InvalidArgumentException(implode(' ',$errors));
        $pdo=Database::connection();$pdo->beginTransaction();try{
            $oldStatus=$existing['status']??null;
            if($id)Database::execute('UPDATE management_report_submissions SET period_key=?,period_title=?,period_start=?,period_end=?,unit_id=?,status=?,submitted_at=IF(?="submitted",NOW(),submitted_at),return_note=IF(?="submitted",NULL,return_note),updated_at=NOW() WHERE id=?',[$periodKey,$periodTitle,$period['period_start']?:null,$period['period_end']?:null,(int)($user['org_unit_id']??0)?:null,$persistStatus,$targetStatus,$targetStatus,$id]);
            else{Database::execute('INSERT INTO management_report_submissions(template_id,report_type,period_key,period_title,period_start,period_end,submitter_id,unit_id,template_version_no,schema_snapshot_json,status,submitted_at,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,IF(?="submitted",NOW(),NULL),NOW(),NOW())',[(int)$template['id'],$type,$periodKey,$periodTitle,$period['period_start']?:null,$period['period_end']?:null,(int)$user['id'],(int)($user['org_unit_id']??0)?:null,(int)($template['version_no']??1),self::templateSnapshot($template),$persistStatus,$targetStatus]);$id=(int)Database::lastInsertId();}
            foreach($normalized as $fieldId=>$value){
                Database::execute('INSERT INTO management_report_values(submission_id,field_id,value_text,value_number,value_json,created_at,updated_at) VALUES(?,?,?,?,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE value_text=VALUES(value_text),value_number=VALUES(value_number),value_json=VALUES(value_json),updated_at=NOW()',[$id,$fieldId,$value['text'],$value['number'],$value['json']]);
                $fieldType=(string)($fields[$fieldId]['field_type']??'');
                if(in_array($fieldType,['task_selector','action_selector','user_selector'],true)){
                    if($value['number'])self::syncSelectorLink($id,$fieldId,$fieldType,(int)$value['number'],(int)$user['id']);
                    else self::deactivateSelectorLink($id,$fieldId,$fieldType);
                }
            }
            $action=$targetStatus==='submitted'?'submitted':($existing?'draft_saved':'created');self::reviewLog($id,$action,$oldStatus,$persistStatus,null,(int)$user['id']);
            $pdo->commit();return $id;
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }

    public static function createActionFromReport(int $submissionId, array $input): int
    {
        $actor=Auth::user();if(!$actor)throw new RuntimeException('permission_denied');
        $submission=Database::fetch('SELECT * FROM management_report_submissions WHERE id=?',[$submissionId]);
        if(!$submission||(int)$submission['submitter_id']!==(int)$actor['id']||!self::canPrepare((string)$submission['report_type'],$actor))throw new RuntimeException('permission_denied');
        if(!in_array((string)$submission['status'],['draft','returned'],true))throw new InvalidArgumentException('تبدیل پیشنهاد به اقدام فقط در پیش‌نویس یا گزارش برگشتی مجاز است.');
        $assignable=array_column(ActionHubService::assignableUsers($actor),'id');
        $assignedTo=(int)($input['assigned_to']??0);
        if(!$assignedTo||!in_array($assignedTo,array_map('intval',$assignable),true))throw new InvalidArgumentException('مسئول اقدام باید از کاربران مجاز انتخاب شود.');
        $title=trim((string)($input['title']??''));if($title==='')throw new InvalidArgumentException('عنوان اقدام الزامی است.');
        $actionId=ActionHubService::createAction([
            'title'=>$title,
            'description'=>trim((string)($input['description']??'')),
            'action_type_id'=>(int)($input['action_type_id']??0),
            'assigned_to'=>$assignedTo,
            'priority'=>(string)($input['priority']??'normal'),
            'due_date'=>(string)($input['due_date']??''),
            'source_type'=>'manager_report',
            'source_id'=>$submissionId,
            'add_to_planner'=>!empty($input['add_to_planner']),
        ],$actor);
        self::upsertLink($submissionId,(int)($input['field_id']??0)?:null,'generated_action','actions',$actionId,'/admin/action-view.php?id='.$actionId,$title,(int)$actor['id']);
        Auth::log((int)$actor['id'],'management_report_action_created','management_report_submissions',$submissionId);
        return $actionId;
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

    private static function syncSelectorLink(int $submissionId,int $fieldId,string $fieldType,int $linkedId,int $actorId): void
    {
        if($fieldType==='action_selector'){
            $row=Database::fetch('SELECT title FROM actions WHERE id=?',[$linkedId]);
            self::upsertLink($submissionId,$fieldId,'selected_action','actions',$linkedId,'/admin/action-view.php?id='.$linkedId,(string)($row['title']??'اقدام مرتبط'),$actorId);
        }elseif($fieldType==='task_selector'){
            $row=Database::fetch('SELECT title FROM work_planner_tasks WHERE id=?',[$linkedId]);
            self::upsertLink($submissionId,$fieldId,'selected_task','work_planner_tasks',$linkedId,'/admin/work-planner.php?task_id='.$linkedId,(string)($row['title']??'وظیفه مرتبط'),$actorId);
        }elseif($fieldType==='user_selector'){
            $row=Database::fetch('SELECT name FROM users WHERE id=?',[$linkedId]);
            self::upsertLink($submissionId,$fieldId,'selected_user','users',$linkedId,null,(string)($row['name']??'کاربر مرتبط'),$actorId);
        }
    }

    private static function deactivateSelectorLink(int $submissionId,int $fieldId,string $fieldType): void
    {
        $linkType=['action_selector'=>'selected_action','task_selector'=>'selected_task','user_selector'=>'selected_user'][$fieldType]??'';
        if($linkType!=='')Database::execute('UPDATE management_report_links SET active=0,updated_at=NOW() WHERE submission_id=? AND field_id=? AND link_type=?',[$submissionId,$fieldId,$linkType]);
    }

    private static function upsertLink(int $submissionId,?int $fieldId,string $linkType,string $linkedType,int $linkedId,?string $url,?string $label,int $actorId): void
    {
        Database::execute(
            'INSERT INTO management_report_links(submission_id,field_id,link_type,linked_type,linked_id,link_url,label,created_by,active,created_at,updated_at)
             VALUES(?,?,?,?,?,?,?,?,1,NOW(),NOW())
             ON DUPLICATE KEY UPDATE linked_type=VALUES(linked_type),linked_id=VALUES(linked_id),link_url=VALUES(link_url),
             label=VALUES(label),created_by=VALUES(created_by),active=1,updated_at=NOW()',
            [$submissionId,$fieldId,$linkType,$linkedType,$linkedId,$url,$label,$actorId]
        );
    }

    private static function reviewLog(int $submissionId,string $action,?string $old,?string $new,?string $note,int $userId): void
    {Database::execute('INSERT INTO management_report_reviews(submission_id,action,old_status,new_status,note,created_by,created_at) VALUES(?,?,?,?,?,?,NOW())',[$submissionId,$action,$old,$new,$note,$userId]);}
    private static function number(mixed $value): float
    {$value=strtr((string)$value,['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','٬'=>'','،'=>'']);$value=str_replace([',',' '],'',$value);return is_numeric($value)?(float)$value:0.0;}
}
