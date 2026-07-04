<?php
require_once __DIR__.'/Database.php';

class SyncQueueService
{
    private const ENTITY_MAP = [
        'users' => ['table'=>'users','fields'=>['id','name','username','role','department','status','employee_no','org_unit_id','org_role_id','sales_line','created_at','updated_at']],
        'reports' => ['table'=>'management_report_submissions','fields'=>['id','report_type','template_id','period_key','submitter_id','org_unit_id','status','created_at','updated_at']],
    ];

    public static function setting(string $key, string $default=''): string
    { return (string)(Database::fetch('SELECT setting_value FROM site_settings WHERE setting_key=?',[$key])['setting_value']??$default); }
    public static function enabled(): bool { return self::setting('sync_api_enabled','0')==='1'; }
    public static function allowedEntities(): array
    { $configured=preg_split('/[\s,;]+/',self::setting('sync_allowed_entities','users,reports'),-1,PREG_SPLIT_NO_EMPTY)?:[];return array_values(array_intersect(array_keys(self::ENTITY_MAP),$configured)); }
    public static function apiKeyHash(): string { return self::setting('sync_api_key_hash'); }
    public static function setApiKey(string $plain): void
    { Database::execute('INSERT INTO site_settings(setting_key,setting_value,setting_type,updated_at) VALUES("sync_api_key_hash",?,"password",NOW()) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),updated_at=NOW()',[hash('sha256',$plain)]); }
    public static function saveSettings(array $input): void
    {
        $values=['sync_api_enabled'=>!empty($input['sync_api_enabled'])?'1':'0','sync_ip_allowlist'=>trim((string)($input['sync_ip_allowlist']??'')),'sync_batch_default'=>(string)max(1,min(100,(int)($input['sync_batch_default']??50))),'sync_batch_max'=>(string)max(1,min(500,(int)($input['sync_batch_max']??100))),'sync_max_attempts'=>(string)max(1,min(20,(int)($input['sync_max_attempts']??5)))];
        $allowed=array_values(array_intersect(array_keys(self::ENTITY_MAP),(array)($input['sync_allowed_entities']??[])));$values['sync_allowed_entities']=implode(',',$allowed?:['users']);
        foreach($values as $key=>$value)Database::execute('INSERT INTO site_settings(setting_key,setting_value,setting_type,updated_at) VALUES(?,?,"text",NOW()) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),updated_at=NOW()',[$key,$value]);
    }
    public static function enqueue(string $entityType,int $entityId,string $operation='upsert'): int
    { self::assertEntity($entityType);if($entityId<1||!in_array($operation,['upsert','delete','status_change'],true))throw new InvalidArgumentException('invalid_queue_item');Database::execute('INSERT INTO sync_queue(entity_type,entity_id,operation,status,attempts,created_at,updated_at) VALUES(?,?,?,"pending",0,NOW(),NOW())',[$entityType,$entityId,$operation]);return (int)Database::lastInsertId(); }
    public static function enqueueOnce(string $entityType,int $entityId,string $operation='upsert'): int
    { self::assertEntity($entityType);$existing=Database::fetch('SELECT id FROM sync_queue WHERE entity_type=? AND entity_id=? AND operation=? AND status IN ("pending","error","locked") ORDER BY id DESC LIMIT 1',[$entityType,$entityId,$operation]);if($existing){Database::execute('UPDATE sync_queue SET updated_at=NOW() WHERE id=?',[(int)$existing['id']]);return (int)$existing['id'];}return self::enqueue($entityType,$entityId,$operation); }
    public static function getPending(array $filters=[]): array
    { $max=max(1,min(500,(int)self::setting('sync_batch_max','100')));$requested=(int)($filters['batch_size']??0);$limit=max(1,min($max,$requested>0?$requested:(int)self::setting('sync_batch_default','50')));$attempts=max(1,(int)self::setting('sync_max_attempts','5'));$where=['(status="pending" OR (status="error" AND attempts<?))'];$params=[$attempts];if(!empty($filters['entity_type'])){self::assertEntity((string)$filters['entity_type']);$where[]='entity_type=?';$params[]=(string)$filters['entity_type'];}if((int)($filters['since_id']??0)>0){$where[]='id>?';$params[]=(int)$filters['since_id'];}$rows=Database::fetchAll('SELECT id queue_id,entity_type,entity_id,operation,status,attempts,created_at FROM sync_queue WHERE '.implode(' AND ',$where).' ORDER BY created_at,id LIMIT '.$limit,$params);return ['items'=>$rows,'limit'=>$limit]; }
    public static function record(string $entityType,int $entityId): array
    { self::assertEntity($entityType);if($entityId<1)throw new InvalidArgumentException('invalid_entity_id');$map=self::ENTITY_MAP[$entityType];if(!Database::tableExists($map['table']))throw new RuntimeException('entity_not_supported');$available=array_values(array_filter($map['fields'],static fn($field)=>Database::columnExists($map['table'],$field)));$row=Database::fetch('SELECT '.implode(',',$available).' FROM '.$map['table'].' WHERE id=? LIMIT 1',[$entityId]);if(!$row)throw new RuntimeException('record_not_found');return ['entity_type'=>$entityType,'entity_id'=>$entityId,'operation'=>'upsert','payload'=>$row,'source_updated_at'=>$row['updated_at']??$row['created_at']??null]; }
    public static function markSynced(int $queueId): string
    { $row=Database::fetch('SELECT status FROM sync_queue WHERE id=?',[$queueId]);if(!$row)throw new RuntimeException('queue_not_found');if($row['status']==='synced')return 'already_synced';if(!in_array($row['status'],['pending','error','locked'],true))throw new RuntimeException('queue_state_invalid');Database::execute('UPDATE sync_queue SET status="synced",synced_at=NOW(),updated_at=NOW(),locked_at=NULL,locked_by=NULL,last_error=NULL WHERE id=?',[$queueId]);return 'synced'; }
    public static function markError(int $queueId,string $message): int
    { $row=Database::fetch('SELECT attempts,status FROM sync_queue WHERE id=?',[$queueId]);if(!$row)throw new RuntimeException('queue_not_found');if($row['status']==='synced')return (int)$row['attempts'];$message=mb_substr(trim($message),0,2000);Database::execute('UPDATE sync_queue SET status="error",attempts=attempts+1,last_error=?,updated_at=NOW(),locked_at=NULL,locked_by=NULL WHERE id=?',[$message?:'خطای گزارش‌شده توسط worker',$queueId]);return (int)$row['attempts']+1; }
    public static function log(string $endpoint,bool $success,int $status,?string $error=null,?string $entityType=null,?int $entityId=null,?int $queueId=null): void
    { try{Database::execute('INSERT INTO sync_api_logs(endpoint,method,remote_ip,entity_type,entity_id,queue_id,status_code,success,error_message,created_at) VALUES(?,?,?,?,?,?,?,?,?,NOW())',[mb_substr($endpoint,0,100),mb_substr((string)($_SERVER['REQUEST_METHOD']??'GET'),0,10),mb_substr((string)($_SERVER['REMOTE_ADDR']??''),0,64),$entityType,$entityId,$queueId,$status,$success?1:0,$error?mb_substr($error,0,2000):null]);}catch(Throwable $e){error_log('Sync API log: '.$e->getMessage());} }
    public static function ipAllowed(string $ip): bool
    { $rules=preg_split('/[\s,;]+/',trim(self::setting('sync_ip_allowlist')),-1,PREG_SPLIT_NO_EMPTY)?:[];if(!$rules)return true;return in_array($ip,$rules,true); }
    private static function assertEntity(string $entityType): void
    { if(!in_array($entityType,self::allowedEntities(),true)||!isset(self::ENTITY_MAP[$entityType]))throw new InvalidArgumentException('entity_not_supported'); }
}
