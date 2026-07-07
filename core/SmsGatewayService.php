<?php

require_once __DIR__ . '/Database.php';

class SmsGatewayService
{
    private const MAX_BATCH = 90;
    private array $config;

    public function __construct(array $config) { $this->config = $config; }

    public static function active(): self
    {
        $row = Database::fetch('SELECT * FROM sms_settings WHERE is_active=1 ORDER BY id DESC LIMIT 1');
        if (!$row) throw new RuntimeException('sms_not_configured');
        $row['password'] = self::decrypt((string)$row['password_encrypted']);
        return new self($row);
    }

    public static function encrypt(string $plain): string
    {
        if (!function_exists('openssl_encrypt')) throw new RuntimeException('openssl_extension_required');
        $iv=random_bytes(12);$tag='';$cipher=openssl_encrypt($plain,'aes-256-gcm',self::key(),OPENSSL_RAW_DATA,$iv,$tag,'sobhan-sms-v1');
        if ($cipher === false) throw new RuntimeException('sms_encryption_failed');
        return 'v1:'.base64_encode($iv.$tag.$cipher);
    }

    private static function decrypt(string $encoded): string
    {
        if (!str_starts_with($encoded,'v1:')) throw new RuntimeException('sms_cipher_invalid');
        $raw=base64_decode(substr($encoded,3),true);
        if ($raw===false || strlen($raw)<29) throw new RuntimeException('sms_cipher_invalid');
        $plain=openssl_decrypt(substr($raw,28),'aes-256-gcm',self::key(),OPENSSL_RAW_DATA,substr($raw,0,12),substr($raw,12,16),'sobhan-sms-v1');
        if ($plain===false) throw new RuntimeException('sms_decryption_failed');
        return $plain;
    }

    private static function key(): string
    {
        $configured=trim((string)(getenv('SOBHAN_SMS_ENCRYPTION_KEY')?:''));
        if($configured!=='')return hash('sha256',$configured,true);
        $path=dirname(__DIR__).'/config/sms.key';
        if(is_file($path)){ $value=trim((string)file_get_contents($path)); if(strlen($value)>=32)return hash('sha256',$value,true); }
        if(is_writable(dirname($path))){$value=bin2hex(random_bytes(32));if(file_put_contents($path,$value,LOCK_EX)!==false){@chmod($path,0600);return hash('sha256',$value,true);}}
        throw new RuntimeException('sms_encryption_key_missing');
    }

    private function client(): SoapClient
    {
        if (!class_exists('SoapClient')) throw new RuntimeException('soap_extension_required');
        return new SoapClient((string)$this->config['wsdl_url'],['trace'=>false,'exceptions'=>true,'connection_timeout'=>20,'cache_wsdl'=>WSDL_CACHE_NONE]);
    }

    public function sendSimpleSms(array $mobiles,string $message,?string $sender=null): array
    {
        $mobiles=$this->normalizeMobiles($mobiles);$message=trim($message);$sender=trim((string)($sender?:$this->config['default_sender']));
        if(!$mobiles)return self::failure('NO_VALID_MOBILE','شماره موبایل معتبری برای ارسال وجود ندارد.');
        if(count($mobiles)>self::MAX_BATCH)return self::failure('106',$this->translateProviderError('106'));
        if($message==='')return self::failure('103',$this->translateProviderError('103'));
        if($sender==='')return self::failure('105',$this->translateProviderError('105'));
        try{
            $raw=$this->client()->SendSimpleSMS($this->config['username'],$this->config['password'],$sender,$mobiles,$message);
            $decoded=$this->decode($raw);$result=(string)($decoded['result']??'');
            if($result!==''&&ctype_digit($result))return ['success'=>true,'bulk_code'=>$result,'error_code'=>null,'error_message'=>null];
            return self::failure($result?:'PROVIDER_ERROR',$this->translateProviderError($result));
        }catch(Throwable $e){error_log('SMS send: '.$e->getMessage());return self::failure('SMS_CONNECTION','ارتباط با سامانه پیامکی برقرار نشد.');}
    }

    public function getCredit(): array
    {
        try{$decoded=$this->decode($this->client()->GetCredit($this->config['username'],$this->config['password']));$credit=(string)($decoded['result']??'');if(in_array($credit,['101','102','301','302','303','304','401'],true))return ['success'=>false,'credit'=>null,'error_message'=>$this->translateProviderError($credit)];return ['success'=>true,'credit'=>$credit];}
        catch(Throwable $e){error_log('SMS credit: '.$e->getMessage());return ['success'=>false,'credit'=>null,'error_message'=>'امکان دریافت اعتبار پیامک وجود ندارد.'];}
    }

    public function getStatus(string $bulkCode): array
    {
        try{$decoded=$this->decode($this->client()->GetStatus($this->config['username'],$this->config['password'],trim($bulkCode)));return ['success'=>true,'statuses'=>array_is_list($decoded)?$decoded:[]];}
        catch(Throwable $e){error_log('SMS status: '.$e->getMessage());return ['success'=>false,'statuses'=>[],'error_message'=>'امکان دریافت وضعیت پیامک وجود ندارد.'];}
    }

    public function normalizeMobiles(array $mobiles): array
    {
        $valid=[];foreach($mobiles as $mobile){$mobile=preg_replace('/[^0-9]/','',trim((string)$mobile));if(str_starts_with($mobile,'0098'))$mobile='0'.substr($mobile,4);elseif(str_starts_with($mobile,'98'))$mobile='0'.substr($mobile,2);elseif(strlen($mobile)===10&&str_starts_with($mobile,'9'))$mobile='0'.$mobile;if(preg_match('/^09[0-9]{9}$/',$mobile))$valid[]=$mobile;}return array_values(array_unique($valid));
    }

    public function translateProviderError(string $code): string
    {
        return ['101'=>'نام کاربری وارد نشده است.','102'=>'رمز عبور وارد نشده است.','103'=>'متن پیام وارد نشده است.','104'=>'لیست گیرندگان صحیح نیست.','105'=>'شماره ارسال صحیح نیست.','106'=>'تعداد گیرندگان هر ارسال نباید بیش از ۹۰ شماره باشد.','301'=>'نام کاربری یا رمز عبور اشتباه است.','302'=>'مدارک پنل تأیید نشده است.','303'=>'پنل غیرفعال یا منقضی شده است.','304'=>'پنل غیرفعال یا ثبت‌نام ناقص است.','401'=>'اطلاعات درخواستی متعلق به این کاربر نیست.','402'=>'اعتبار پنل کافی نیست.','403'=>'خط غیرفعال یا برای فروش است.','404'=>'خط فقط برای ارسال ویژه است.','405'=>'خط غیرفعال است.','406'=>'درگاه اپراتور موقتاً قطع است.'][$code]??'خطای نامشخص از سامانه پیامکی.';
    }

    private function decode(mixed $raw): array {if(is_array($raw))return $raw;if(is_object($raw))return json_decode(json_encode($raw),true)?:[];$value=json_decode((string)$raw,true);return json_last_error()===JSON_ERROR_NONE?(is_array($value)?$value:['result'=>$value]):['result'=>$raw];}
    private static function failure(string $code,string $message): array{return ['success'=>false,'bulk_code'=>null,'error_code'=>$code,'error_message'=>$message];}
}
