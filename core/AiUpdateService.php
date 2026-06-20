<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/SobhanApiClient.php';

class AiUpdateService
{
    private const TYPES=['test_connection','dashboard_manager_update','dashboard_ceo_update','hr_kpi_update','knowledge_index','full_update'];

    public static function createAndRun(string $type,int $userId): array
    {
        if(!in_array($type,self::TYPES,true))throw new InvalidArgumentException('invalid_job_type');
        Database::execute('INSERT INTO ai_update_jobs(job_type,requested_by,status,progress,message,created_at,updated_at) VALUES (?,?,"pending",0,"در صف اجرا",NOW(),NOW())',[$type,$userId]);
        $id=(int)Database::lastInsertId();
        if($type!=='test_connection'&&trim(setting('sobhan_api_model',''))===''){Database::execute('UPDATE ai_update_jobs SET status="failed",progress=100,message="مدل هوش مصنوعی تنظیم نشده است.",error_message="مدل هوش مصنوعی تنظیم نشده است.",finished_at=NOW() WHERE id=?',[$id]);return self::find($id);}
        Database::execute('UPDATE ai_update_jobs SET status="running",progress=10,message="در حال اتصال به Windows Server API",started_at=NOW(),updated_at=NOW() WHERE id=?',[$id]);
        $client=new SobhanApiClient();
        if($type==='test_connection')$response=$client->get('/health');
        elseif($type==='full_update')$response=$client->post('/api/ai/complete-settings',['requested_by'=>(string)$userId,'job_type'=>$type,'model'=>setting('sobhan_api_model','qwen2.5:1.5b')]);
        else{$dashboard=['dashboard_manager_update'=>'manager_dashboard','dashboard_ceo_update'=>'ceo_dashboard','hr_kpi_update'=>'hr_kpi','knowledge_index'=>'knowledge'][$type];$response=$client->post('/api/dashboard/refresh',['dashboard_key'=>$dashboard,'scope_key'=>'all','requested_by'=>(string)$userId]);}
        if(!$response['ok']){
            $message=$response['error']['message_fa']??'اتصال به سرویس Windows Server برقرار نشد.';
            Database::execute('UPDATE ai_update_jobs SET status="failed",progress=100,message=?,error_message=?,finished_at=NOW(),updated_at=NOW() WHERE id=?',[$message,$message,$id]);
            return self::find($id);
        }
        $data=is_array($response['data']??null)?$response['data']:[];$remote=(string)($data['job_id']??$data['id']??'');$remoteStatus=(string)($data['status']??'completed');$pending=in_array($remoteStatus,['pending','queued','running'],true);
        Database::execute('UPDATE ai_update_jobs SET remote_job_id=?,status=?,progress=?,message=?,result_json=?,finished_at='.($pending?'NULL':'NOW()').',updated_at=NOW() WHERE id=?',[$remote,$pending?'running':'completed',$pending?max(10,(int)($data['progress']??20)):100,$pending?'در حال اجرا روی Windows Server':'بروزرسانی با موفقیت انجام شد.',json_encode($data,JSON_UNESCAPED_UNICODE),$id]);
        if(!$pending&&$type!=='test_connection')self::storeDashboardCache($type,$data,$userId);
        return self::find($id);
    }

    public static function refreshStatus(int $id): array
    {
        $job=self::find($id);if(!$job)return [];
        if($job['status']==='running'&&!empty($job['remote_job_id'])){
            $response=(new SobhanApiClient())->get('/api/dashboard/status/'.rawurlencode($job['remote_job_id']));
            if($response['ok']){$data=is_array($response['data']??null)?$response['data']:[];$status=(string)($data['status']??'running');$done=in_array($status,['completed','success','done'],true);$failed=in_array($status,['failed','error'],true);Database::execute('UPDATE ai_update_jobs SET status=?,progress=?,message=?,result_json=?,error_message=?,finished_at='.($done||$failed?'NOW()':'NULL').',updated_at=NOW() WHERE id=?',[$failed?'failed':($done?'completed':'running'),$done||$failed?100:max(10,(int)($data['progress']??$job['progress'])),$failed?'بروزرسانی ناموفق بود.':($done?'بروزرسانی با موفقیت انجام شد.':'در حال اجرا'),json_encode($data,JSON_UNESCAPED_UNICODE),$failed?'خطای سرویس Windows Server':null,$id]);}
        }
        return self::find($id);
    }

    public static function find(int $id): array { return Database::fetch('SELECT id,remote_job_id,job_type,status,progress,message,started_at,finished_at,created_at,updated_at FROM ai_update_jobs WHERE id=?',[$id])?:[]; }

    private static function storeDashboardCache(string $type,array $data,int $userId): void
    {
        $key=['dashboard_manager_update'=>'manager_dashboard','dashboard_ceo_update'=>'ceo_dashboard','hr_kpi_update'=>'hr_kpi','knowledge_index'=>'knowledge','full_update'=>'all'][$type]??$type;
        Database::execute('INSERT INTO dashboard_data_cache(dashboard_key,scope_key,source,payload_json,updated_by,updated_at,created_at) VALUES (?,"all","Windows Server API",?,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE source=VALUES(source),payload_json=VALUES(payload_json),updated_by=VALUES(updated_by),updated_at=NOW()',[$key,json_encode($data,JSON_UNESCAPED_UNICODE),$userId]);
    }
}
