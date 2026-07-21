<?php
require_once __DIR__.'/SpreadsheetRows.php';
require_once __DIR__.'/../core/Database.php';
require_once __DIR__.'/../core/SalesStructureModule.php';
require_once __DIR__.'/OrgAccess.php';
require_once __DIR__.'/UserOrganizationService.php';
class UserImportService
{
    public const HEADERS=[
        'employee_no','kara_system_code','full_name','username','mobile','email','department','organizational_role',
        'manager_employee_no','sales_line','supervisor_employee_no','sales_manager_employee_no','region_code',
        'status','password_optional'
    ];
    public static function preview(string $path,string $fileName,string $mode,bool $allowEmpty,int $userId): int
    {
        SalesStructureModule::repair(Database::connection());
        $mode=in_array($mode,['create','update','upsert'],true)?$mode:'create';
        $ext=strtolower(pathinfo($fileName,PATHINFO_EXTENSION));
        $rows=SpreadsheetRows::associative(SpreadsheetRows::read($path,$ext));
        $actor=Database::fetch('SELECT * FROM users WHERE id=? AND status="active"',[$userId]);
        if(!$actor)throw new InvalidArgumentException('کاربر اجراکننده ایمپورت معتبر نیست.');
        Database::execute(
            'INSERT INTO user_import_batches(file_name,imported_by,mode,allow_empty_employee_no,total_rows,status,created_at) VALUES(?,?,?,?,?,"preview",NOW())',
            [$fileName,$userId,$mode,$allowEmpty?1:0,count($rows)]
        );
        $batch=(int)Database::lastInsertId();
        $numbers=array_values(array_filter(array_map(static fn($r)=>trim((string)($r['employee_no']??'')),$rows)));
        $seen=[];
        $seenKara=[];
        $valid=0;
        $errors=0;

        foreach($rows as $row){
            $messages=[];
            $no=trim((string)($row['employee_no']??''));
            if($no===''&&!$allowEmpty)$messages[]='شماره پرسنلی خالی است.';
            if($no!==''&&isset($seen[$no]))$messages[]='شماره پرسنلی در فایل تکراری است.';
            if($no!=='')$seen[$no]=true;
            if(trim((string)($row['full_name']??''))==='')$messages[]='نام و نام خانوادگی خالی است.';
            $email=trim((string)($row['email']??''));
            if($email===''||!filter_var($email,FILTER_VALIDATE_EMAIL))$messages[]='ایمیل معتبر نیست.';
            $existing=$no!==''?Database::fetch('SELECT id FROM users WHERE employee_no=?',[$no]):null;
            if($mode==='create'&&$existing)$messages[]='شماره پرسنلی قبلاً ثبت شده است.';
            if($mode==='update'&&!$existing)$messages[]='کاربر موجود برای بروزرسانی پیدا نشد.';

            try {
                $kara=UserOrganizationService::normalizeKaraSystemCode($row['kara_system_code']??null);
                if($kara!==null&&isset($seenKara[$kara]))$messages[]='کد سیستم کارا در فایل تکراری است.';
                if($kara!==null){
                    $seenKara[$kara]=true;
                    if(Database::fetch('SELECT id FROM users WHERE kara_system_code=? AND id<>? LIMIT 1',[$kara,(int)($existing['id']??0)])){
                        $messages[]='کد سیستم کارا قبلاً ثبت شده است.';
                    }
                }
                $context=self::resolveOrganizationContext($row,$existing?(int)$existing['id']:null);
                self::assertActorScope($actor,$context['assignment'],$existing?(int)$existing['id']:null,!$existing);
            } catch (InvalidArgumentException $e) {
                $messages[]=$e->getMessage();
            }

            $manager=trim((string)($row['manager_employee_no']??''));
            if($manager!==''&&!in_array($manager,$numbers,true)&&!Database::fetch('SELECT id FROM users WHERE employee_no=?',[$manager])){
                $messages[]='شماره پرسنلی مدیر مستقیم پیدا نشد.';
            }

            $status=$messages?'error':'valid';
            $messages?$errors++:$valid++;
            Database::execute(
                'INSERT INTO user_import_rows(batch_id,source_row,employee_no,raw_data_json,status,error_message,created_at) VALUES(?,?,?,?,?,?,NOW())',
                [$batch,(int)$row['_row'],$no?:null,json_encode($row,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),$status,$messages?implode(' ',$messages):null]
            );
        }
        Database::execute('UPDATE user_import_batches SET success_count=?,error_count=? WHERE id=?',[$valid,$errors,$batch]);
        return $batch;
    }
    private static function resolveOrganizationContext(array $data,?int $userId=null): array
    {
        $unitValue=trim((string)($data['department']??''));
        $unit=$unitValue!==''?Database::fetch('SELECT id,title FROM org_units WHERE active=1 AND (title=? OR code=?) LIMIT 1',[$unitValue,$unitValue]):null;
        if($unitValue!==''&&!$unit)throw new InvalidArgumentException('واحد سازمانی پیدا نشد.');

        $roleValue=trim((string)($data['organizational_role']??''));
        $role=$roleValue!==''?Database::fetch('SELECT id,title,code FROM org_roles WHERE active=1 AND (title=? OR code=?) LIMIT 1',[$roleValue,$roleValue]):null;
        if($roleValue!==''&&!$role)throw new InvalidArgumentException('نقش سازمانی پیدا نشد.');

        $lineValue=trim((string)($data['sales_line']??''));
        if($lineValue==='')$lineValue=trim((string)($data['sales_line_code']??''));
        $line=$lineValue!==''?Database::fetch('SELECT id,code,manager_user_id,supervisor_user_id FROM sales_lines WHERE active=1 AND (code=? OR title=?) LIMIT 1',[$lineValue,$lineValue]):null;
        if($lineValue!==''&&!$line)throw new InvalidArgumentException('کد لاین فروش در ساختار مرکزی پیدا نشد.');

        $managerNo=trim((string)($data['sales_manager_employee_no']??''));
        $supervisorNo=trim((string)($data['supervisor_employee_no']??''));
        $legacyManagerNo=trim((string)($data['manager_employee_no']??''));
        $roleCode=(string)($role['code']??'');
        if($roleCode==='SALES_SUPERVISOR'&&$managerNo==='')$managerNo=$legacyManagerNo;
        if($roleCode==='VISITOR'&&$supervisorNo==='')$supervisorNo=$legacyManagerNo;

        $manager=$managerNo!==''?Database::fetch('SELECT id FROM users WHERE employee_no=? AND status="active"',[$managerNo]):null;
        $supervisor=$supervisorNo!==''?Database::fetch('SELECT id FROM users WHERE employee_no=? AND status="active"',[$supervisorNo]):null;
        if($managerNo!==''&&!$manager)throw new InvalidArgumentException('شماره پرسنلی مدیر فروش پیدا نشد.');
        if($supervisorNo!==''&&!$supervisor)throw new InvalidArgumentException('شماره پرسنلی سرپرست فروش پیدا نشد.');

        $regionValue=trim((string)($data['region_code']??''));
        $region=$regionValue!==''?Database::fetch('SELECT id FROM sales_geographies WHERE active=1 AND (code=? OR title=?) LIMIT 1',[$regionValue,$regionValue]):null;
        if($regionValue!==''&&!$region)throw new InvalidArgumentException('کد شهر یا منطقه فروش پیدا نشد.');

        $directManager=$legacyManagerNo!==''?Database::fetch('SELECT id FROM users WHERE employee_no=? AND status="active"',[$legacyManagerNo]):null;
        $assignment=UserOrganizationService::validateAssignment([
            'org_unit_id'=>$unit['id']??null,
            'org_role_id'=>$role['id']??null,
            'sales_line_id'=>$line['id']??null,
            'supervisor_id'=>$supervisor['id']??null,
            'organization_manager_id'=>$manager['id']??null,
            'parent_user_id'=>$directManager['id']??null,
            'primary_geography_id'=>$region['id']??null,
        ],$userId,true);

        return ['unit'=>$unit,'role'=>$role,'line'=>$line,'assignment'=>$assignment];
    }

    private static function uniqueUsername(string $base,?int $ignoreId=null): string
    {
        $base=preg_replace('/[^a-zA-Z0-9_.-]/','',strtolower($base))?:'user';
        $candidate=$base;
        $i=1;
        while(Database::fetch('SELECT id FROM users WHERE username=? AND id<>? LIMIT 1',[$candidate,$ignoreId?:0]))$candidate=$base.'.'.(++$i);
        return $candidate;
    }

    private static function assertActorScope(array $actor,array $assignment,?int $targetUserId,bool $isCreate): void
    {
        if(OrgAccess::isAdmin($actor))return;
        if($targetUserId&& !OrgAccess::canAccessUser($actor,$targetUserId)){
            throw new InvalidArgumentException('کاربر هدف خارج از دامنه سازمانی مجاز شماست.');
        }
        $relationIds=array_values(array_unique(array_filter(array_map(
            'intval',
            [
                $assignment['parent_user_id']??0,
                $assignment['supervisor_id']??0,
                $assignment['organization_manager_id']??0,
            ]
        ))));
        if($relationIds&&!OrgAccess::canAssignScope($actor,$relationIds)){
            throw new InvalidArgumentException('مدیر یا سرپرست انتخاب‌شده خارج از دامنه سازمانی مجاز شماست.');
        }
        if($isCreate&&!$relationIds){
            throw new InvalidArgumentException('ایجاد کاربر سطح‌بالا از طریق ایمپورت فقط برای مدیر سیستم مجاز است.');
        }
    }
    public static function commit(int $batchId,int $actor): array
    {
        $pdo=Database::connection();
        $pdo->beginTransaction();
        try{
            $batch=Database::fetch('SELECT * FROM user_import_batches WHERE id=? FOR UPDATE',[$batchId]);
            if(!$batch||$batch['status']!=='preview')throw new InvalidArgumentException('Batch قابل ثبت نیست.');
            $actorUser=Database::fetch('SELECT * FROM users WHERE id=? AND status="active"',[$actor]);
            if(!$actorUser)throw new InvalidArgumentException('کاربر اجراکننده ایمپورت معتبر نیست.');
            $rows=Database::fetchAll('SELECT * FROM user_import_rows WHERE batch_id=? ORDER BY source_row',[$batchId]);
            $created=0;
            $updated=0;
            $skipped=0;
            $managerLinks=[];

            foreach($rows as $row){
                if($row['status']==='error'){$skipped++;continue;}
                $data=json_decode($row['raw_data_json'],true)?:[];
                $no=trim((string)($data['employee_no']??''));
                $existing=$no!==''?Database::fetch('SELECT * FROM users WHERE employee_no=?',[$no]):null;
                if($batch['mode']==='create'&&$existing){
                    Database::execute("UPDATE user_import_rows SET status='skipped',error_message='کاربر از قبل وجود دارد.' WHERE id=?",[$row['id']]);
                    $skipped++;
                    continue;
                }
                if($batch['mode']==='update'&&!$existing){
                    Database::execute("UPDATE user_import_rows SET status='skipped',error_message='کاربر برای بروزرسانی پیدا نشد.' WHERE id=?",[$row['id']]);
                    $skipped++;
                    continue;
                }

                try {
                    $context=self::resolveOrganizationContext($data,$existing?(int)$existing['id']:null);
                    $assignment=$context['assignment'];
                    self::assertActorScope($actorUser,$assignment,$existing?(int)$existing['id']:null,!$existing);
                    $unitRow=$context['unit'];
                    $roleRow=$context['role'];
                    $kara=UserOrganizationService::normalizeKaraSystemCode($data['kara_system_code']??null);
                    if($kara&&Database::fetch('SELECT id FROM users WHERE kara_system_code=? AND id<>? LIMIT 1',[$kara,(int)($existing['id']??0)])){
                        throw new InvalidArgumentException('کد سیستم کارا قبلاً ثبت شده است.');
                    }

                    $username=trim((string)($data['username']??''));
                    if($username==='')$username=$no?:trim((string)($data['mobile']??''));
                    $username=self::uniqueUsername($username?:'user',$existing?(int)$existing['id']:null);
                    $status=in_array(strtolower((string)($data['status']??'')),['disabled','inactive','0'],true)?'disabled':'active';
                    $password=trim((string)($data['password_optional']??''));

                    if($existing){
                        $params=[
                            trim((string)$data['full_name']),$username,trim((string)$data['mobile']),trim((string)$data['email']),
                            $kara,$unitRow['title']??null,$roleRow['code']??null,$unitRow['id']??null,$roleRow['id']??null,
                            $assignment['sales_line'],$assignment['sales_line_id'],$assignment['supervisor_id'],$assignment['organization_manager_id'],
                            $assignment['parent_user_id'],$status,$no?:null
                        ];
                        $sql='UPDATE users SET name=?,username=?,mobile=?,email=?,kara_system_code=?,department=?,role_key=?,org_unit_id=?,org_role_id=?,sales_line=?,sales_line_id=?,supervisor_id=?,organization_manager_id=?,parent_user_id=?,status=?,employee_no=?,updated_at=NOW()';
                        if($password!==''){
                            $sql.=',password_hash=?,force_password_change=0';
                            $params[]=password_hash($password,PASSWORD_DEFAULT);
                        }
                        $sql.=' WHERE id=?';
                        $params[]=(int)$existing['id'];
                        Database::execute($sql,$params);
                        $userId=(int)$existing['id'];
                        $updated++;
                        $newStatus='updated';
                    }else{
                        $password=$password!==''?$password:bin2hex(random_bytes(8));
                        Database::execute(
                            'INSERT INTO users(name,email,username,password_hash,role,status,department,role_key,sales_line,sales_line_id,supervisor_id,organization_manager_id,parent_user_id,org_unit_id,org_role_id,employee_no,kara_system_code,mobile,force_password_change,created_at,updated_at)
                             VALUES(?,?,?,?,"employee",?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())',
                            [
                                trim((string)$data['full_name']),trim((string)$data['email']),$username,password_hash($password,PASSWORD_DEFAULT),
                                $status,$unitRow['title']??null,$roleRow['code']??null,$assignment['sales_line'],$assignment['sales_line_id'],
                                $assignment['supervisor_id'],$assignment['organization_manager_id'],$assignment['parent_user_id'],
                                $unitRow['id']??null,$roleRow['id']??null,$no?:null,$kara,trim((string)($data['mobile']??'')),
                                trim((string)($data['password_optional']??''))===''?1:0
                            ]
                        );
                        $userId=(int)Database::lastInsertId();
                        $created++;
                        $newStatus='created';
                    }

                    UserOrganizationService::applyAssignment($userId,$assignment,$actor);
                    if(!empty($assignment['parent_user_id'])){
                        Database::execute(
                            'INSERT IGNORE INTO manager_employees(manager_id,employee_id,assigned_by,created_at) VALUES(?,?,?,NOW())',
                            [(int)$assignment['parent_user_id'],$userId,$actor]
                        );
                    }
                    if(!empty($data['manager_employee_no']))$managerLinks[]=['user_id'=>$userId,'manager_no'=>trim((string)$data['manager_employee_no']),'role_code'=>(string)($roleRow['code']??'')];
                    Database::execute('UPDATE user_import_rows SET status=?,error_message=NULL WHERE id=?',[$newStatus,$row['id']]);
                } catch (InvalidArgumentException $e) {
                    Database::execute("UPDATE user_import_rows SET status='skipped',error_message=? WHERE id=?",[$e->getMessage(),$row['id']]);
                    $skipped++;
                }
            }

            foreach($managerLinks as $link){
                if(in_array($link['role_code'],['VISITOR','SALES_SUPERVISOR'],true))continue;
                $manager=Database::fetch('SELECT id FROM users WHERE employee_no=?',[$link['manager_no']]);
                if($manager){
                    Database::execute('UPDATE users SET parent_user_id=? WHERE id=?',[(int)$manager['id'],$link['user_id']]);
                    Database::execute('INSERT IGNORE INTO manager_employees(manager_id,employee_id,assigned_by,created_at) VALUES(?,?,?,NOW())',[(int)$manager['id'],$link['user_id'],$actor]);
                }
            }
            Database::execute("UPDATE user_import_batches SET status='committed',success_count=?,error_count=?,committed_at=NOW() WHERE id=?",[$created+$updated,$skipped,$batchId]);
            $pdo->commit();
            return compact('created','updated','skipped');
        }catch(Throwable $e){
            if($pdo->inTransaction())$pdo->rollBack();
            throw $e;
        }
    }
}
