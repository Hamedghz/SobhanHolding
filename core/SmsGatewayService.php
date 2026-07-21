<?php
require_once __DIR__.'/Database.php';

class SmsGatewayService
{
    private const ERRORS=['101','102','103','104','105','106','301','302','303','304','401','402','403','404','405','406'];
    private array $config;
    public function __construct(array $config){$this->config=$config;}

    public static function active(): self
    {
        $row=Database::fetch('SELECT * FROM sms_settings WHERE is_active=1 ORDER BY id DESC LIMIT 1');if(!$row)throw new RuntimeException('sms_inactive');return self::fromRow($row);
    }
    public static function configured(): self
    {
        $row=Database::fetch('SELECT * FROM sms_settings ORDER BY id DESC LIMIT 1');if(!$row)throw new RuntimeException('sms_not_configured');return self::fromRow($row);
    }
    private static function fromRow(array $row): self
    {
        try{$row['password']=self::decrypt((string)$row['password_encrypted']);}catch(Throwable $e){self::writeSafeLog('CREDENTIAL_DECRYPT_FAILED','رمز سامانه پیامکی قابل خواندن نیست.');throw new RuntimeException('sms_credentials_unavailable');}return new self($row);
    }
    public static function encrypt(string $plain): string
    {
        if(!function_exists('openssl_encrypt'))throw new RuntimeException('openssl_extension_required');$iv=random_bytes(12);$tag='';$cipher=openssl_encrypt($plain,'aes-256-gcm',self::key(),OPENSSL_RAW_DATA,$iv,$tag,'sobhan-sms-v1');if($cipher===false)throw new RuntimeException('sms_encryption_failed');return 'v1:'.base64_encode($iv.$tag.$cipher);
    }
    private static function decrypt(string $encoded): string
    {
        if(!str_starts_with($encoded,'v1:'))throw new RuntimeException('sms_cipher_invalid');$raw=base64_decode(substr($encoded,3),true);if($raw===false||strlen($raw)<29)throw new RuntimeException('sms_cipher_invalid');$plain=openssl_decrypt(substr($raw,28),'aes-256-gcm',self::key(),OPENSSL_RAW_DATA,substr($raw,0,12),substr($raw,12,16),'sobhan-sms-v1');if($plain===false)throw new RuntimeException('sms_decryption_failed');return $plain;
    }
    private static function key(): string
    {
        $value=trim((string)(getenv('SOBHAN_SMS_ENCRYPTION_KEY')?:''));if($value!=='')return hash('sha256',$value,true);$path=dirname(__DIR__).'/config/sms.key';if(is_file($path)&&strlen($value=trim((string)file_get_contents($path)))>=32)return hash('sha256',$value,true);if(is_writable(dirname($path))){$value=bin2hex(random_bytes(32));if(file_put_contents($path,$value,LOCK_EX)!==false){@chmod($path,0600);return hash('sha256',$value,true);}}throw new RuntimeException('sms_encryption_key_missing');
    }

    public function getDiagnostics(): array
    {
        $items=[];$items['soap_extension']=$this->stage(class_exists('SoapClient'),'افزونه SOAP روی PHP فعال است.','SOAP_EXTENSION_MISSING','افزونه SOAP روی PHP هاست فعال نیست.');if(!$items['soap_extension']['success'])return $this->diagnosticResult($items);
        $wsdl=trim((string)($this->config['wsdl_url']??''));$valid=$wsdl!==''&&filter_var($wsdl,FILTER_VALIDATE_URL)&&str_contains(strtolower($wsdl),'?wsdl');$items['wsdl_format']=$this->stage((bool)$valid,'آدرس WSDL معتبر است.','INVALID_WSDL','آدرس WSDL سامانه پیامکی معتبر نیست.');if(!$valid)return $this->diagnosticResult($items);
        try{$this->client();$items['wsdl']=$this->stage(true,'WSDL سامانه پیامکی قابل دسترس است.','','');}catch(Throwable $e){$this->logSafeError('WSDL_CONNECTION_FAILED','بارگذاری WSDL ناموفق بود.',['exception'=>get_class($e)]);$items['wsdl']=$this->stage(false,'','WSDL_CONNECTION_FAILED','امکان اتصال به WSDL سامانه پیامکی وجود ندارد.');return $this->diagnosticResult($items);}
        $credentials=trim((string)($this->config['username']??''))!==''&&trim((string)($this->config['password']??''))!=='';$items['credentials']=$this->stage($credentials,'اطلاعات ورود ثبت شده است.','MISSING_CREDENTIALS','نام کاربری یا رمز عبور سامانه پیامکی ثبت نشده است.');if(!$credentials)return $this->diagnosticResult($items);
        $credit=$this->getCredit();$items['provider']=['success'=>$credit['success'],'error_code'=>$credit['error_code'],'message'=>$credit['message'],'credit'=>$credit['credit']];return $this->diagnosticResult($items,$credit['credit']);
    }
    public function testConnection(): array{return $this->getDiagnostics();}

    public function getCredit(): array
    {
        if(!class_exists('SoapClient'))return $this->creditFailure('soap_extension','SOAP_EXTENSION_MISSING','افزونه SOAP روی PHP هاست فعال نیست.');$wsdl=trim((string)($this->config['wsdl_url']??''));if($wsdl===''||!str_contains(strtolower($wsdl),'?wsdl'))return $this->creditFailure('wsdl','INVALID_WSDL','آدرس WSDL سامانه پیامکی معتبر نیست.');if(trim((string)($this->config['username']??''))===''||trim((string)($this->config['password']??''))==='')return $this->creditFailure('credentials','MISSING_CREDENTIALS','نام کاربری یا رمز عبور سامانه پیامکی ثبت نشده است.');
        try{$decoded=$this->decodeProviderResponse($this->client()->GetCredit($this->config['username'],$this->config['password']));$result=trim((string)($decoded['result']??''));if(in_array($result,self::ERRORS,true))return $this->creditFailure('provider',$result,$this->translateProviderError($result));if($result!==''&&is_numeric(str_replace([',',' '],'',$result))){$this->updateTest('success','دریافت اعتبار موفق بود.',$result);return ['success'=>true,'stage'=>'provider','error_code'=>null,'message'=>'اعتبار پنل با موفقیت دریافت شد.','credit'=>$result,'raw'=>null];}$this->logSafeError('UNKNOWN_CREDIT_RESPONSE','پاسخ اعتبار سامانه قابل تشخیص نبود.',['response'=>$this->maskSensitive($result)]);return $this->creditFailure('parse','UNKNOWN_RESPONSE','پاسخ سامانه پیامکی قابل تشخیص نیست.');}
        catch(Throwable $e){$code=$e instanceof SoapFault?'WSDL_CONNECTION_FAILED':'SMS_CONNECTION_FAILED';$message=$e instanceof SoapFault?'امکان اتصال به WSDL سامانه پیامکی وجود ندارد.':'ارتباط با سامانه پیامکی برقرار نشد.';$this->logSafeError($code,$message,['exception'=>get_class($e)]);return $this->creditFailure('wsdl',$code,$message);}
    }

    public function sendSimpleSms(array $mobiles,string $message,?string $sender=null,?int $actorId=null,string $sourceModule='manual',?int $sourceId=null,?string $requestKey=null): array
    {
        if(empty($this->config['is_active']))return ['success'=>false,'message'=>'سامانه پیامکی غیرفعال است.','valid_count'=>0,'invalid_count'=>count($mobiles),'batches'=>[],'duplicate'=>false];$original=array_values($mobiles);$valid=$this->normalizeMobiles($original);$invalid=max(0,count($original)-count($valid));$message=trim($message);$messageLength=self::textLength($message);$sender=trim((string)($sender?:$this->config['default_sender']));if($message===''||$messageLength>2000||!$valid||$sender==='')return ['success'=>false,'message'=>$message===''?'متن پیامک خالی است.':($messageLength>2000?'متن پیامک بیش از ۲۰۰۰ نویسه است.':(!$valid?'شماره موبایل معتبری وجود ندارد.':'خط ارسال ثبت نشده است.')),'valid_count'=>count($valid),'invalid_count'=>$invalid,'batches'=>[],'duplicate'=>false];
        $normalizedRequestKey=$requestKey?hash('sha256',($actorId?:0).'|'.$sourceModule.'|'.trim($requestKey)):null;$lockName=$normalizedRequestKey?'sobhan_sms_'.substr($normalizedRequestKey,0,40):null;$lockAcquired=false;
        try{
            if($lockName){$stmt=Database::connection()->prepare('SELECT GET_LOCK(?,0)');$stmt->execute([$lockName]);$lockAcquired=(int)$stmt->fetchColumn()===1;if(!$lockAcquired)return ['success'=>false,'message'=>'این درخواست هم‌اکنون در حال پردازش است. ارسال تکراری متوقف شد.','valid_count'=>count($valid),'invalid_count'=>$invalid,'batches'=>[],'duplicate'=>true];$existing=Database::fetch('SELECT id,status,bulk_code FROM sms_messages WHERE request_key=? LIMIT 1',[$normalizedRequestKey]);if($existing)return ['success'=>true,'message'=>'این درخواست قبلاً پردازش شده است؛ ارسال تکراری انجام نشد.','valid_count'=>count($valid),'invalid_count'=>$invalid,'batches'=>[['success'=>true,'message_id'=>(int)$existing['id'],'bulk_code'=>$existing['bulk_code']]],'duplicate'=>true];}
            $batches=[];$first=true;foreach(array_chunk($valid,90) as $chunk){$result=$this->sendChunk($chunk,$message,$sender);$id=$this->storeBatch($chunk,$message,$sender,$result,$actorId,$sourceModule,$sourceId,$invalid,$first?$normalizedRequestKey:null);$first=false;$result['message_id']=$id;$batches[]=$result;$invalid=0;}return ['success'=>!array_filter($batches,static fn($b)=>empty($b['success'])),'message'=>'پردازش ارسال پیامک انجام شد.','valid_count'=>count($valid),'invalid_count'=>max(0,count($original)-count($valid)),'batches'=>$batches,'duplicate'=>false];
        }finally{if($lockName&&$lockAcquired){try{$stmt=Database::connection()->prepare('SELECT RELEASE_LOCK(?)');$stmt->execute([$lockName]);}catch(Throwable){}}}
    }

    public function getStatus(string $bulkCode): array
    {
        $bulkCode=trim($bulkCode);if($bulkCode===''||!preg_match('/^[A-Za-z0-9_-]{1,100}$/',$bulkCode))return ['success'=>false,'message'=>'کد ارسال معتبر نیست.','delivered'=>0,'failed'=>0,'unknown'=>0];
        try{$decoded=$this->decodeProviderResponse($this->client()->GetStatus($this->config['username'],$this->config['password'],$bulkCode));$providerCode=trim((string)($decoded['result']??''));if(in_array($providerCode,self::ERRORS,true))return ['success'=>false,'message'=>$this->translateProviderError($providerCode),'delivered'=>0,'failed'=>0,'unknown'=>0];if(!array_is_list($decoded))throw new UnexpectedValueException('status_response_not_list');$message=Database::fetch('SELECT id FROM sms_messages WHERE bulk_code=? ORDER BY id DESC LIMIT 1',[$bulkCode]);if(!$message)return ['success'=>false,'message'=>'گزارش ارسال مربوط به این کد پیدا نشد.','delivered'=>0,'failed'=>0,'unknown'=>0];$counts=['delivered'=>0,'failed'=>0,'unknown'=>0];$pdo=Database::connection();$pdo->beginTransaction();try{foreach($decoded as $item){if(!is_array($item))continue;$mobile=$this->normalizeMobiles([(string)($item['cell']??'')])[0]??'';if($mobile==='')continue;$status=mb_substr(trim((string)($item['status']??'unknown')),0,50)?:'unknown';$group=$status==='delivered'?'delivered':(in_array($status,['donotsend','failed','rejected','undelivered'],true)?'failed':'unknown');$counts[$group]++;Database::execute('UPDATE sms_message_recipients SET provider_message_id=?,delivery_status=?,checked_at=NOW() WHERE message_id=? AND normalized_mobile=?',[mb_substr((string)($item['id']??''),0,100)?:null,$status,(int)$message['id'],$mobile]);}$total=array_sum($counts);$summary=$total>0&&$counts['delivered']===$total?'delivered':($counts['delivered']>0?'partially_delivered':($counts['failed']>0&&$counts['unknown']===0?'delivery_failed':'unknown'));Database::execute('UPDATE sms_messages SET status=?,last_checked_at=NOW() WHERE id=?',[$summary,(int)$message['id']]);$pdo->commit();}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}return ['success'=>true,'message'=>'وضعیت تحویل بروزرسانی شد.']+$counts;}
        catch(Throwable $e){$this->logSafeError('STATUS_SYNC_FAILED','بروزرسانی وضعیت تحویل ناموفق بود.',['bulk_code'=>$this->maskSensitive($bulkCode),'exception'=>get_class($e)]);return ['success'=>false,'message'=>'امکان دریافت وضعیت تحویل وجود ندارد.','delivered'=>0,'failed'=>0,'unknown'=>0];}
    }

    public function normalizeMobiles(array $mobiles): array
    {
        $valid=[];foreach($mobiles as $mobile){$mobile=preg_replace('/[^0-9]/','',trim((string)$mobile));if(str_starts_with($mobile,'0098'))$mobile='0'.substr($mobile,4);elseif(str_starts_with($mobile,'98'))$mobile='0'.substr($mobile,2);elseif(strlen($mobile)===10&&str_starts_with($mobile,'9'))$mobile='0'.$mobile;if(preg_match('/^09[0-9]{9}$/',$mobile))$valid[]=$mobile;}return array_values(array_unique($valid));
    }
    public function segmentCount(string $message): int
    {
        $length=self::textLength(trim($message));if($length===0)return 0;$unicode=(bool)preg_match('/[^\x00-\x7F]/',$message);$single=$unicode?70:160;$multi=$unicode?67:153;return $length<=$single?1:(int)ceil($length/$multi);
    }
    private static function textLength(string $value): int
    {
        if(function_exists('mb_strlen'))return mb_strlen($value,'UTF-8');$matched=preg_match_all('/./us',$value,$unused);return $matched===false?strlen($value):$matched;
    }
    public function translateProviderError(string $code): string{return ['101'=>'نام کاربری وارد نشده است.','102'=>'رمز عبور وارد نشده است.','103'=>'متن پیام وارد نشده است.','104'=>'لیست گیرندگان به‌درستی انتخاب نشده است.','105'=>'شماره واردشده جهت ارسال صحیح نیست.','106'=>'تعداد ارسالی بیش از ۹۰ مورد است.','301'=>'نام کاربری یا رمز عبور اشتباه است.','302'=>'مدارک پنل پیامکی تأیید نشده است.','303'=>'پنل پیامکی غیرفعال یا منقضی شده است.','304'=>'پنل پیامکی غیرفعال است یا ثبت‌نام کامل نیست.','401'=>'اطلاعات درخواستی مربوط به این کاربر نیست.','402'=>'اعتبار پنل برای ارسال کافی نیست.','403'=>'خط مورد نظر غیرفعال یا برای فروش است.','404'=>'خط مورد نظر فقط برای ارسال ویژه است.','405'=>'خط مورد نظر غیرفعال است.','406'=>'درگاه ارسال اپراتور موقتاً قطع است.'][$code]??'خطای نامشخص از سامانه پیامکی.';}

    private function client(): SoapClient
    {
        if(!class_exists('SoapClient'))throw new RuntimeException('soap_extension_missing');$wsdl=trim((string)($this->config['wsdl_url']??''));if($wsdl===''||!filter_var($wsdl,FILTER_VALIDATE_URL)||!str_contains(strtolower($wsdl),'?wsdl'))throw new InvalidArgumentException('invalid_wsdl');ini_set('soap.wsdl_cache_enabled','0');return new SoapClient($wsdl,['trace'=>false,'exceptions'=>true,'connection_timeout'=>20,'cache_wsdl'=>WSDL_CACHE_NONE]);
    }
    private function decodeProviderResponse(mixed $raw): array{if(is_array($raw))return $raw;if(is_object($raw))return json_decode(json_encode($raw),true)?:[];$rawString=trim((string)$raw);$decoded=json_decode($rawString,true);if(json_last_error()===JSON_ERROR_NONE)return is_array($decoded)?$decoded:['result'=>$decoded];return ['result'=>$rawString];}
    private function sendChunk(array $mobiles,string $message,string $sender): array
    {
        try{$decoded=$this->decodeProviderResponse($this->client()->SendSimpleSMS($this->config['username'],$this->config['password'],$sender,$mobiles,$message));$result=trim((string)($decoded['result']??''));if($result!==''&&ctype_digit($result)&&!in_array($result,self::ERRORS,true))return ['success'=>true,'bulk_code'=>$result,'error_code'=>null,'error_message'=>null];$code=$result?:'UNKNOWN_RESPONSE';$safe=in_array($code,self::ERRORS,true)?$this->translateProviderError($code):'پاسخ سامانه پیامکی قابل تشخیص نیست.';$this->logSafeError($code,$safe,['recipient_count'=>count($mobiles)]);return ['success'=>false,'bulk_code'=>null,'error_code'=>$code,'error_message'=>$safe];}catch(Throwable $e){$this->logSafeError('SMS_SEND_FAILED','ارسال پیامک ناموفق بود.',['recipient_count'=>count($mobiles),'exception'=>get_class($e)]);return ['success'=>false,'bulk_code'=>null,'error_code'=>'SMS_SEND_FAILED','error_message'=>'ارتباط با سامانه پیامکی برقرار نشد.'];}
    }
    private function storeBatch(array $mobiles,string $message,string $sender,array $result,?int $actorId,string $sourceModule,?int $sourceId,int $invalid,?string $requestKey=null): int
    {
        $status=$result['success']?'sent':'failed';$pdo=Database::connection();$pdo->beginTransaction();try{Database::execute('INSERT INTO sms_messages(provider_name,sender,message_body,message_hash,request_key,segment_count,recipients_count,valid_recipients_count,invalid_recipients_count,bulk_code,status,source_module,source_id,created_by,sent_at,error_code,error_message,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,IF(?="sent",NOW(),NULL),?,?,NOW())',[(string)($this->config['provider_name']??'bazyabpayam'),$sender,$message,hash('sha256',$message),$requestKey,$this->segmentCount($message),count($mobiles)+$invalid,count($mobiles),$invalid,$result['bulk_code'],$status,mb_substr($sourceModule,0,100),$sourceId,$actorId,$status,$result['error_code'],$result['error_message']]);$id=(int)Database::lastInsertId();foreach($mobiles as $mobile)Database::execute('INSERT INTO sms_message_recipients(message_id,mobile,normalized_mobile,delivery_status,created_at) VALUES(?,?,?,?,NOW())',[$id,$this->maskSensitive($mobile),$mobile,$status]);$pdo->commit();return $id;}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }
    private function logSafeError(string $code,string $message,array $context=[]): void{self::writeSafeLog($code,$message,$context);}
    private static function writeSafeLog(string $code,string $message,array $context=[]): void{try{unset($context['password'],$context['message'],$context['username']);Database::execute('INSERT INTO sms_gateway_logs(level,error_code,safe_message,context_json,created_by,created_at) VALUES("error",?,?,?,?,NOW())',[$code,mb_substr($message,0,255),$context?json_encode($context,JSON_UNESCAPED_UNICODE):null,class_exists('Auth')?(int)(Auth::user()['id']??0)?:null:null]);}catch(Throwable $ignored){error_log('SMS gateway error code: '.preg_replace('/[^A-Z0-9_-]/i','',$code));}}
    private function maskSensitive(string $value): string{$length=mb_strlen($value);if($length<=4)return str_repeat('*',$length);return mb_substr($value,0,min(3,$length)).str_repeat('*',max(3,$length-7)).mb_substr($value,-4);}
    private function updateTest(string $status,string $message,?string $credit=null): void{if(empty($this->config['id']))return;Database::execute('UPDATE sms_settings SET last_credit=COALESCE(?,last_credit),last_credit_checked_at=IF(? IS NULL,last_credit_checked_at,NOW()),last_test_status=?,last_test_message=?,updated_at=NOW() WHERE id=?',[$credit,$credit,$status,mb_substr($message,0,255),(int)$this->config['id']]);}
    private function creditFailure(string $stage,string $code,string $message): array{$this->updateTest('failed',$message);return ['success'=>false,'stage'=>$stage,'error_code'=>$code,'message'=>$message,'credit'=>null,'raw'=>null];}
    private function stage(bool $success,string $ok,string $code,string $fail): array{return ['success'=>$success,'error_code'=>$success?null:$code,'message'=>$success?$ok:$fail];}
    private function diagnosticResult(array $items,mixed $credit=null): array{$success=!array_filter($items,static fn($item)=>empty($item['success']));$last=end($items);return ['success'=>$success,'stage'=>array_key_last($items),'error_code'=>$success?null:($last['error_code']??'DIAGNOSTIC_FAILED'),'message'=>$success?'اتصال SOAP و اطلاعات حساب معتبر است.':($last['message']??'تست اتصال ناموفق بود.'),'credit'=>$credit,'items'=>$items,'raw'=>null];}
}
