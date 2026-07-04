<?php

require_once __DIR__.'/../core/Auth.php';
require_once __DIR__.'/../core/Config.php';
require_once __DIR__.'/../core/Response.php';

class NotificationHubService
{
    public const MODULES=['cartable','ticketing','messenger','messenger_group','messenger_channel','approval','planner','hr','sales','management','system'];

    public static function createPairingCode(int $userId): array
    {
        Database::execute('UPDATE sobhan_notification_pairing_codes SET used_at=NOW() WHERE user_id=? AND used_at IS NULL',[$userId]);
        for($i=0;$i<5;$i++){
            $code=(string)random_int(100000,999999);$hash=hash('sha256',$code);
            try{Database::execute('INSERT INTO sobhan_notification_pairing_codes(user_id,code_hash,expires_at,created_ip,created_at) VALUES(?,?,DATE_ADD(NOW(),INTERVAL 5 MINUTE),?,NOW())',[$userId,$hash,mb_substr((string)($_SERVER['REMOTE_ADDR']??''),0,45)]);return ['code'=>$code,'expires_at'=>date('Y-m-d H:i:s',time()+300)];}catch(Throwable){continue;}
        }
        throw new RuntimeException('pairing_code_failed');
    }

    public static function pair(array $input): array
    {
        $ipHash=hash('sha256',(string)($_SERVER['REMOTE_ADDR']??'unknown'));
        $recent=Database::fetch('SELECT COUNT(*) c FROM sobhan_notification_pairing_attempts WHERE ip_hash=? AND attempted_at>DATE_SUB(NOW(),INTERVAL 10 MINUTE)',[$ipHash]);
        if((int)($recent['c']??0)>=20)throw new RuntimeException('pairing_rate_limited');
        Database::execute('INSERT INTO sobhan_notification_pairing_attempts(ip_hash,success,attempted_at) VALUES(?,0,NOW())',[$ipHash]);$attemptId=(int)Database::lastInsertId();
        $code=preg_replace('/\D/','',(string)($input['pairing_code']??''));
        if(strlen($code)!==6)throw new InvalidArgumentException('کد اتصال معتبر نیست.');
        $row=Database::fetch('SELECT * FROM sobhan_notification_pairing_codes WHERE code_hash=? AND used_at IS NULL AND expires_at>NOW() LIMIT 1',[hash('sha256',$code)]);
        if(!$row){usleep(250000);throw new InvalidArgumentException('کد اتصال نامعتبر یا منقضی شده است.');}
        $name=self::text((string)($input['device_name']??''),190);$version=self::text((string)($input['app_version']??''),30);$fingerprint=trim((string)($input['machine_fingerprint']??''));
        if($name===''||$version===''||strlen($fingerprint)<16)throw new InvalidArgumentException('مشخصات دستگاه کامل نیست.');
        $uid=self::uuid();$token=self::token();$fingerprintHash=hash('sha256',$fingerprint);$pdo=Database::connection();$pdo->beginTransaction();
        try{
            Database::execute('UPDATE sobhan_notification_pairing_codes SET used_at=NOW(),attempts=attempts+1 WHERE id=? AND used_at IS NULL',[(int)$row['id']]);
            Database::execute('UPDATE sobhan_notification_devices SET active=0,revoked_at=NOW(),updated_at=NOW() WHERE user_id=? AND machine_fingerprint_hash=? AND active=1',[(int)$row['user_id'],$fingerprintHash]);
            Database::execute('INSERT INTO sobhan_notification_devices(user_id,device_uid,device_name,device_type,app_version,machine_fingerprint_hash,token_hash,last_seen_at,active,created_at,updated_at) VALUES(?,?,?,?,?,?,?,NOW(),1,NOW(),NOW())',[(int)$row['user_id'],$uid,$name,'windows',$version,$fingerprintHash,hash('sha256',$token)]);
            $pdo->commit();
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
        Auth::log((int)$row['user_id'],'notification_device_paired','notification_hub',(int)Database::lastInsertId());
        Database::execute('UPDATE sobhan_notification_pairing_attempts SET success=1 WHERE id=?',[$attemptId]);
        return ['device_uid'=>$uid,'device_token'=>$token];
    }

    public static function authenticate(array $headers): array
    {
        $uid=trim((string)($headers['x-device-uid']??''));$token=trim((string)($headers['x-device-token']??''));$version=self::text((string)($headers['x-app-version']??''),30);
        if($uid===''||$token===''||$version==='')throw new RuntimeException('device_auth_required');
        $device=Database::fetch('SELECT d.*,u.name display_name,u.role,u.department,u.org_unit_id,ou.title unit_name,r.title role_title FROM sobhan_notification_devices d JOIN users u ON u.id=d.user_id LEFT JOIN org_units ou ON ou.id=u.org_unit_id LEFT JOIN org_roles r ON r.id=u.org_role_id WHERE d.device_uid=? AND d.active=1 AND u.status="active" LIMIT 1',[$uid]);
        if(!$device||!hash_equals((string)$device['token_hash'],hash('sha256',$token)))throw new RuntimeException('device_auth_invalid');
        Database::execute('UPDATE sobhan_notification_devices SET last_seen_at=NOW(),app_version=?,updated_at=NOW() WHERE id=?',[$version,(int)$device['id']]);
        return $device;
    }

    public static function clientConfig(array $device): array
    {
        $wide=Database::fetch('SELECT * FROM sobhan_user_notification_settings WHERE user_id=?',[(int)$device['user_id']])?:[];
        $moduleRows=Database::fetchAll('SELECT * FROM sobhan_user_notification_module_settings WHERE user_id=?',[(int)$device['user_id']]);$byModule=array_column($moduleRows,null,'module');$modules=[];
        foreach(self::MODULES as $module){$row=$byModule[$module]??[];$sensitive=in_array($module,['hr','management'],true);$modules[$module]=[
            'enabled'=>(bool)($row['enabled']??1)&&(bool)($row['desktop_enabled']??1),'show_body'=>(bool)($row['show_body']??($sensitive?0:1)),
            'sound'=>(string)($row['sound']??($module==='approval'?'important':'default')),'priority'=>(string)($row['priority']??($module==='approval'?'important':'normal')),
            'allow_quick_reply'=>(bool)($row['allow_quick_reply']??($module==='messenger'?1:0)),'direct_action_enabled'=>(bool)($row['direct_action_enabled']??0),
        ];}
        return ['user'=>['id'=>(int)$device['user_id'],'display_name'=>$device['display_name'],'role'=>$device['role_title']?:$device['role'],'unit'=>$device['unit_name']?:$device['department']],
            'client'=>['poll_seconds'=>self::intSetting('notification_hub_poll_seconds',20,10,300),'enable_realtime'=>self::boolSetting('notification_hub_enable_realtime'),
                'enable_sound'=>self::boolSetting('notification_hub_enable_sound'),'enable_quick_reply'=>self::boolSetting('notification_hub_enable_quick_reply'),
                'enable_action_buttons'=>self::boolSetting('notification_hub_enable_action_buttons'),'silent_hours_enabled'=>(bool)($wide['quiet_hours_start']??false),
                'silent_hours_from'=>$wide['quiet_hours_start']??null,'silent_hours_to'=>$wide['quiet_hours_end']??null,'max_notifications_per_poll'=>self::intSetting('notification_hub_max_per_poll',20,1,100)],
            'modules'=>$modules];
    }

    public static function moduleSettings(int $userId): array
    {
        $rows=Database::fetchAll('SELECT * FROM sobhan_user_notification_module_settings WHERE user_id=?',[$userId]);return array_column($rows,null,'module');
    }

    public static function saveModuleSettings(int $userId,array $input): void
    {
        $pdo=Database::connection();$stmt=$pdo->prepare('INSERT INTO sobhan_user_notification_module_settings(user_id,module,enabled,show_body,sound,priority,allow_quick_reply,direct_action_enabled,desktop_enabled,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE enabled=VALUES(enabled),show_body=VALUES(show_body),sound=VALUES(sound),priority=VALUES(priority),allow_quick_reply=VALUES(allow_quick_reply),direct_action_enabled=VALUES(direct_action_enabled),desktop_enabled=VALUES(desktop_enabled),updated_at=NOW()');
        foreach(self::MODULES as $module){$row=(array)($input[$module]??[]);$sound=in_array($row['sound']??'', ['default','important','message','silent'],true)?$row['sound']:'default';$priority=in_array($row['priority']??'', ['low','normal','important'],true)?$row['priority']:'normal';$stmt->execute([$userId,$module,!empty($row['enabled'])?1:0,!empty($row['show_body'])?1:0,$sound,$priority,!empty($row['allow_quick_reply'])&&$module==='messenger'?1:0,!empty($row['direct_action_enabled'])&&$module==='approval'?1:0,!empty($row['desktop_enabled'])?1:0]);}
    }

    public static function pending(array $device,int $sinceId,int $limit): array
    {
        $limit=max(1,min(100,$limit));$rows=Database::fetchAll("SELECT n.*,u.name sender_name FROM sobhan_notifications n LEFT JOIN users u ON u.id=COALESCE(n.sender_user_id,n.actor_user_id) LEFT JOIN sobhan_notification_delivery_logs dl ON dl.notification_id=n.id AND dl.device_id=? WHERE n.user_id=? AND n.id>? AND n.status<>'archived' AND dl.id IS NULL ORDER BY FIELD(n.priority,'urgent','high','normal','low'),n.id LIMIT {$limit}",[(int)$device['id'],(int)$device['user_id'],max(0,$sinceId)]);
        $config=self::clientConfig($device);$out=[];foreach($rows as $row){$module=self::module((string)($row['module']?:$row['event_type']));$moduleConfig=$config['modules'][$module]??['enabled'=>true,'show_body'=>true,'allow_quick_reply'=>false,'direct_action_enabled'=>false];if(!$moduleConfig['enabled'])continue;$actions=self::allowedActions($row,$moduleConfig);$out[]=[
            'id'=>(int)$row['id'],'module'=>$module,'type'=>(string)($row['type']?:$row['event_type']),'priority'=>self::apiPriority((string)$row['priority']),
            'title'=>(string)$row['title'],'body'=>$moduleConfig['show_body']?(string)$row['body']:(string)($row['safe_body']?:$row['safe_push_body']?:'یک اعلان جدید دریافت کردید.'),
            'safe_body'=>(string)($row['safe_body']?:$row['safe_push_body']?:'یک اعلان جدید دریافت کردید.'),'sender_name'=>$row['sender_name'],
            'conversation_id'=>$row['conversation_id']?(int)$row['conversation_id']:null,'related_record_id'=>$row['related_id']?(int)$row['related_id']:null,
            'action_url'=>self::absoluteUrl((string)$row['action_url']),'actions'=>$actions,'created_at'=>(string)$row['created_at']];}
        return $out;
    }

    public static function acknowledge(array $device,int $notificationId,string $status='delivered'): void
    {
        if(!in_array($status,['delivered','displayed','failed'],true))$status='delivered';self::assertNotification($device,$notificationId);
        Database::execute('INSERT INTO sobhan_notification_delivery_logs(notification_id,device_id,status,delivered_at,created_at) VALUES(?,?,?,IF(?="delivered",NOW(),NULL),NOW()) ON DUPLICATE KEY UPDATE status=VALUES(status),delivered_at=COALESCE(delivered_at,VALUES(delivered_at))',[$notificationId,(int)$device['id'],$status,$status]);
    }

    public static function action(array $device,int $notificationId,string $action,?string $reply): array
    {
        $notification=self::assertNotification($device,$notificationId);$reply=self::text((string)$reply,2000);
        try{$config=self::clientConfig($device);$module=self::module((string)($notification['module']?:$notification['event_type']));$allowed=array_column(self::allowedActions($notification,$config['modules'][$module]??[]),'id');
            if(!in_array($action,$allowed,true))throw new RuntimeException('action_not_allowed');
            if(in_array($action,['mark_read','open','view_ticket','view_cartable'],true))Database::execute('UPDATE sobhan_notifications SET status="read",is_read=1,read_at=COALESCE(read_at,NOW()),updated_at=NOW() WHERE id=?',[$notificationId]);
            elseif($action==='reply'){if($module!=='messenger'||$reply==='')throw new RuntimeException('reply_not_allowed');if(!is_callable('sobhan_messenger_quick_reply'))throw new RuntimeException('action_not_supported');call_user_func('sobhan_messenger_quick_reply',$notification,$reply,(int)$device['user_id']);}
            elseif(in_array($action,['approve','reject','mute','comment'],true)){if(!is_callable('sobhan_notification_direct_action'))throw new RuntimeException('action_not_supported');call_user_func('sobhan_notification_direct_action',$notification,$action,(int)$device['user_id']);}
            Database::execute('INSERT INTO sobhan_notification_delivery_logs(notification_id,device_id,status,action,reply_text,delivered_at,clicked_at,created_at) VALUES(?,?,"acted",?,?,NOW(),NOW(),NOW()) ON DUPLICATE KEY UPDATE status="acted",action=VALUES(action),reply_text=VALUES(reply_text),clicked_at=NOW()',[$notificationId,(int)$device['id'],$action,$reply?:null]);
            return ['action'=>$action,'action_url'=>in_array($action,['open','view_ticket','view_cartable'],true)?self::absoluteUrl((string)$notification['action_url']):null];
        }catch(Throwable $e){$code=preg_match('/^[a-z0-9_.-]+$/i',$e->getMessage())?$e->getMessage():'action_failed';Database::execute('INSERT INTO sobhan_notification_delivery_logs(notification_id,device_id,status,action,error_message,created_at) VALUES(?,?,"action_failed",?,?,NOW()) ON DUPLICATE KEY UPDATE status="action_failed",action=VALUES(action),error_message=VALUES(error_message)',[$notificationId,(int)$device['id'],self::text($action,40),$code]);throw $e;}
    }

    public static function unregister(array $device): void{Database::execute('UPDATE sobhan_notification_devices SET active=0,revoked_at=NOW(),updated_at=NOW() WHERE id=?',[(int)$device['id']]);}
    public static function updateDevice(array $device,array $input): void{Database::execute('UPDATE sobhan_notification_devices SET device_name=?,app_version=?,last_seen_at=NOW(),updated_at=NOW() WHERE id=?',[self::text((string)($input['device_name']??$device['device_name']),190),self::text((string)($input['app_version']??$device['app_version']),30),(int)$device['id']]);}

    private static function assertNotification(array $device,int $id): array{$row=Database::fetch('SELECT * FROM sobhan_notifications WHERE id=? AND user_id=?',[$id,(int)$device['user_id']]);if(!$row)throw new RuntimeException('notification_not_found');return $row;}
    private static function allowedActions(array $row,array $config): array{$actions=json_decode((string)($row['actions_json']??''),true);if(!is_array($actions))$actions=[['id'=>'open','label'=>'باز کردن'],['id'=>'mark_read','label'=>'خوانده شد']];$out=[];foreach($actions as $a){$id=(string)($a['id']??'');if($id==='reply'&&empty($config['allow_quick_reply']))continue;if(in_array($id,['approve','reject'],true)&&empty($config['direct_action_enabled']))continue;if(in_array($id,['open','mark_read','reply','approve','reject','mute','comment','view_cartable','view_ticket'],true))$out[]=['id'=>$id,'label'=>self::text((string)($a['label']??$id),60)];}return array_slice($out,0,5);}
    public static function module(string $event): string{$event=strtolower($event);return match(true){str_contains($event,'ticket'),str_contains($event,'sla')=>'ticketing',str_contains($event,'cartable')=>'cartable',str_contains($event,'approval')=>'approval',str_contains($event,'messenger_group'),str_contains($event,'group_message')=>'messenger_group',str_contains($event,'channel')=>'messenger_channel',str_contains($event,'message'),str_contains($event,'messenger'),str_contains($event,'forwarded_report')=>'messenger',str_contains($event,'hr'),str_contains($event,'assessment'),str_contains($event,'payroll')=>'hr',str_contains($event,'sale'),str_contains($event,'manager_dashboard')=>'sales',str_contains($event,'management'),str_contains($event,'meeting'),str_contains($event,'resolution'),str_contains($event,'finance')=>'management',default=>'system'};}
    private static function apiPriority(string $priority): string{return in_array($priority,['urgent','high'],true)?'important':($priority==='low'?'low':'normal');}
    private static function absoluteUrl(string $url): string{if($url==='')return '';$base=rtrim((string)(Config::app()['url']??''),'/');if($base==='')return '';if(preg_match('#^https://#i',$url)){return parse_url($url,PHP_URL_HOST)===parse_url($base,PHP_URL_HOST)?$url:'';}return $base.($url[0]==='/'?'':'/').$url;}
    private static function boolSetting(string $key): bool{return setting($key,'0')==='1';}private static function intSetting(string $key,int $default,int $min,int $max): int{return max($min,min($max,(int)setting($key,(string)$default)));}
    private static function text(string $value,int $max): string{return mb_substr(trim(strip_tags($value)),0,$max);}private static function token(): string{return rtrim(strtr(base64_encode(random_bytes(32)),'+/','-_'),'=');}private static function uuid(): string{$d=random_bytes(16);$d[6]=chr((ord($d[6])&0x0f)|0x40);$d[8]=chr((ord($d[8])&0x3f)|0x80);return vsprintf('%s%s-%s-%s-%s-%s%s%s',str_split(bin2hex($d),4));}
}
