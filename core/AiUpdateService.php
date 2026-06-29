<?php
require_once __DIR__ . '/Database.php';require_once __DIR__ . '/SobhanApiClient.php';

class AiUpdateService
{
    private const TYPES=['test_connection','test_windows','test_reporting','test_ai','test_all','dashboard_manager_update','dashboard_ceo_update','hr_kpi_update','knowledge_index','full_update'];

    public static function createAndRun(string $type,int $userId): array
    {
        if(!in_array($type,self::TYPES,true))throw new InvalidArgumentException('invalid_job_type');
        $started=microtime(true);Database::execute('INSERT INTO ai_update_jobs(job_type,requested_by,status,progress,message,created_at,updated_at) VALUES (?,?,"pending",0,"در صف اجرا",NOW(),NOW())',[$type,$userId]);$id=(int)Database::lastInsertId();
        $service=self::serviceForType($type);$config=self::config($service);$preflight=self::preflight($service,$type,$config);
        if(!$preflight['ok'])return self::fail($id,$preflight['message'],$preflight['technical'],$config['base_url'],microtime(true)-$started);
        Database::execute('UPDATE ai_update_jobs SET status="running",progress=10,message=?,endpoint=?,started_at=NOW(),updated_at=NOW() WHERE id=?',['در حال اتصال به '.$config['label'],$config['base_url'],$id]);

        if($type==='test_all'){
            $checks=[];foreach(['windows','reporting','ai'] as $target){$targetConfig=self::config($target);$check=self::preflight($target,'test_'.$target,$targetConfig);if(!$check['ok'])return self::fail($id,$check['message'],$check['technical'],$targetConfig['base_url'],microtime(true)-$started);$checks[$target]=self::requestWithRetry($targetConfig,'GET','/health',[]);if(!$checks[$target]['ok'])return self::fail($id,$targetConfig['label'].' پاسخ نداد.',$checks[$target]['error']['technical']??'request_failed',$targetConfig['base_url'].'/health',microtime(true)-$started);}
            Database::execute('UPDATE ai_update_jobs SET status="completed",progress=100,message="اتصال هر سه سرویس با موفقیت بررسی شد.",result_json=?,duration_ms=?,finished_at=NOW(),updated_at=NOW() WHERE id=?',[json_encode($checks,JSON_UNESCAPED_UNICODE),(int)((microtime(true)-$started)*1000),$id]);return self::find($id);
        }

        if(str_starts_with($type,'test_')||$type==='test_connection'){$path='/health';$method='GET';$payload=[];}
        elseif($type==='full_update'){$path='/api/ai/complete-settings';$method='POST';$payload=['requested_by'=>(string)$userId,'job_type'=>$type,'model'=>setting('sobhan_ai_model','')?:setting('sobhan_api_model','')];}
        else{$path='/api/dashboard/refresh';$method='POST';$dashboard=['dashboard_manager_update'=>'manager_dashboard','dashboard_ceo_update'=>'ceo_dashboard','hr_kpi_update'=>'hr_kpi','knowledge_index'=>'knowledge'][$type];$payload=['dashboard_key'=>$dashboard,'scope_key'=>'all','requested_by'=>(string)$userId];}
        $endpoint=$config['base_url'].$path;Database::execute('UPDATE ai_update_jobs SET endpoint=? WHERE id=?',[$endpoint,$id]);$response=self::requestWithRetry($config,$method,$path,$payload);
        if(!$response['ok']){$message=$response['error']['message_fa']??($config['label'].' پاسخ نداد.');return self::fail($id,$message,$response['error']['technical']??'request_failed',$endpoint,microtime(true)-$started);}
        $data=is_array($response['data']??null)?$response['data']:[];$remote=(string)($data['job_id']??$data['id']??'');$remoteStatus=(string)($data['status']??'completed');$pending=in_array($remoteStatus,['pending','queued','running'],true);
        Database::execute('UPDATE ai_update_jobs SET remote_job_id=?,status=?,progress=?,message=?,result_json=?,duration_ms=?,finished_at='.($pending?'NULL':'NOW()').',updated_at=NOW() WHERE id=?',[$remote,$pending?'running':'completed',$pending?max(10,(int)($data['progress']??20)):100,$pending?'در حال اجرا روی سرویس':'بروزرسانی با موفقیت انجام شد.',json_encode($data,JSON_UNESCAPED_UNICODE),(int)((microtime(true)-$started)*1000),$id]);if(!$pending&&!str_starts_with($type,'test'))self::storeDashboardCache($type,$data,$userId);return self::find($id);
    }

    private static function serviceForType(string $type): string
    {
        if(in_array($type,['test_windows','test_connection'],true))return 'windows';
        if(in_array($type,['test_ai','full_update'],true))return 'ai';
        return 'reporting';
    }

    private static function config(string $service): array
    {
        $fallback=setting('sobhan_api_base_url','');$map=['windows'=>['sobhan_windows_api_url','sobhan_windows_api_enabled','Windows Server API'],'reporting'=>['sobhan_reporting_api_url','sobhan_reporting_api_enabled','Reporting API'],'ai'=>['sobhan_ai_model_api_url','sobhan_ai_model_api_enabled','AI Model API']];[$urlKey,$enabledKey,$label]=$map[$service];return ['service'=>$service,'label'=>$label,'base_url'=>rtrim(setting($urlKey,$fallback),'/'),'enabled'=>setting($enabledKey,setting('sobhan_api_enabled','0'))==='1','api_key'=>setting('sobhan_api_key',''),'timeout'=>max(1,min(60,(int)setting('sobhan_api_timeout','10'))),'retry'=>max(0,min(5,(int)setting('sobhan_api_retry_count','1')))];
    }

    private static function preflight(string $service,string $type,array $config): array
    {
        if(!$config['enabled'])return ['ok'=>false,'message'=>$config['label'].' غیرفعال است.','technical'=>'service_disabled'];
        if($config['base_url']===''||!filter_var($config['base_url'],FILTER_VALIDATE_URL))return ['ok'=>false,'message'=>'آدرس '.$config['label'].' تنظیم نشده است.','technical'=>'missing_or_invalid_url'];
        if($config['api_key']==='')return ['ok'=>false,'message'=>'API Key تنظیم نشده است.','technical'=>'missing_api_key'];
        if($service==='ai'&&!str_starts_with($type,'test')&&trim(setting('sobhan_ai_model','')?:setting('sobhan_api_model',''))==='')return ['ok'=>false,'message'=>'مدل هوش مصنوعی تنظیم نشده است.','technical'=>'missing_ai_model'];
        return ['ok'=>true,'message'=>'','technical'=>''];
    }

    private static function requestWithRetry(array $config,string $method,string $path,array $payload): array
    {
        $result=[];for($attempt=0;$attempt<=$config['retry'];$attempt++){$client=new SobhanApiClient($config['base_url'],$config['api_key'],$config['timeout'],$config['enabled']);$result=$method==='GET'?$client->get($path,$payload):$client->post($path,$payload);if($result['ok'])break;}return $result;
    }

    private static function fail(int $id,string $message,string $technical,string $endpoint,float $duration): array
    {
        Database::execute('UPDATE ai_update_jobs SET status="failed",progress=100,message=?,error_message=?,technical_details=?,endpoint=?,duration_ms=?,finished_at=NOW(),updated_at=NOW() WHERE id=?',[$message,$message,$technical,$endpoint,(int)($duration*1000),$id]);return self::find($id);
    }

    public static function refreshStatus(int $id): array
    {
        $job=self::find($id);if(!$job)return [];
        if($job['status']==='running'&&!empty($job['remote_job_id'])){$config=self::config(self::serviceForType($job['job_type']));$response=self::requestWithRetry($config,'GET','/api/dashboard/status/'.rawurlencode($job['remote_job_id']),[]);if($response['ok']){$data=is_array($response['data']??null)?$response['data']:[];$status=(string)($data['status']??'running');$done=in_array($status,['completed','success','done'],true);$failed=in_array($status,['failed','error'],true);Database::execute('UPDATE ai_update_jobs SET status=?,progress=?,message=?,result_json=?,error_message=?,technical_details=?,finished_at='.($done||$failed?'NOW()':'NULL').',updated_at=NOW() WHERE id=?',[$failed?'failed':($done?'completed':'running'),$done||$failed?100:max(10,(int)($data['progress']??$job['progress'])),$failed?'بروزرسانی ناموفق بود.':($done?'بروزرسانی با موفقیت انجام شد.':'در حال اجرا'),json_encode($data,JSON_UNESCAPED_UNICODE),$failed?'سرویس عملیات را ناموفق اعلام کرد.':null,$failed?json_encode($data,JSON_UNESCAPED_UNICODE):null,$id]);}}
        return self::find($id);
    }

    public static function find(int $id): array{return Database::fetch('SELECT id,remote_job_id,job_type,requested_by,status,progress,message,endpoint,duration_ms,started_at,finished_at,created_at,updated_at FROM ai_update_jobs WHERE id=?',[$id])?:[];}
    private static function storeDashboardCache(string $type,array $data,int $userId): void{$key=['dashboard_manager_update'=>'manager_dashboard','dashboard_ceo_update'=>'ceo_dashboard','hr_kpi_update'=>'hr_kpi','knowledge_index'=>'knowledge','full_update'=>'all'][$type]??$type;Database::execute('INSERT INTO dashboard_data_cache(dashboard_key,scope_key,source,payload_json,updated_by,updated_at,created_at) VALUES (?,"all","Windows Server API",?,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE source=VALUES(source),payload_json=VALUES(payload_json),updated_by=VALUES(updated_by),updated_at=NOW()',[$key,json_encode($data,JSON_UNESCAPED_UNICODE),$userId]);}
}
