<?php
require_once __DIR__.'/../../core/Database.php';
require_once __DIR__.'/../../core/Response.php';
require_once __DIR__.'/../../core/SyncQueueService.php';

ini_set('display_errors','0');ini_set('display_startup_errors','0');

function sync_input(): array
{ $type=strtolower((string)($_SERVER['CONTENT_TYPE']??''));if(str_contains($type,'application/json')){$data=json_decode((string)file_get_contents('php://input'),true);return is_array($data)?$data:[];}return $_POST; }
function sync_fail(string $message,string $code,int $status,?Throwable $error=null): never
{ SyncQueueService::log(basename((string)($_SERVER['SCRIPT_NAME']??'sync')) ,false,$status,$code);json_error($message,$code,$status,$error,'Sync API'); }
function sync_guard(): void
{
    if(!SyncQueueService::enabled())sync_fail('سرویس همگام‌سازی غیرفعال است.','SYNC_DISABLED',503);
    $provided=trim((string)($_SERVER['HTTP_X_API_KEY']??''));
    if($provided==='')sync_fail('کلید دسترسی معتبر نیست.','UNAUTHORIZED',401);
    $stored=SyncQueueService::apiKeyHash();if($stored==='')sync_fail('سرویس همگام‌سازی پیکربندی نشده است.','SYNC_NOT_CONFIGURED',503);
    if(!hash_equals($stored,hash('sha256',$provided)))sync_fail('کلید دسترسی معتبر نیست.','UNAUTHORIZED',401);
    if(!SyncQueueService::ipAllowed((string)($_SERVER['REMOTE_ADDR']??'')))sync_fail('این نشانی شبکه مجاز نیست.','IP_NOT_ALLOWED',403);
}
function sync_run(callable $callback): never
{
    try{sync_guard();$result=$callback();SyncQueueService::log(basename((string)($_SERVER['SCRIPT_NAME']??'sync')),true,200);json_success($result['data']??[],$result['message']??'', $result['meta']??[]);}
    catch(InvalidArgumentException $e){$code=$e->getMessage()==='invalid_entity_id'?'INVALID_ENTITY_ID':'ENTITY_NOT_SUPPORTED';sync_fail('ورودی یا نوع موجودیت معتبر نیست.',$code,422);}
    catch(RuntimeException $e){$map=['entity_not_supported'=>['ENTITY_NOT_SUPPORTED',404,'این نوع موجودیت پشتیبانی نمی‌شود.'],'record_not_found'=>['RECORD_NOT_FOUND',404,'رکورد درخواستی پیدا نشد.'],'queue_not_found'=>['QUEUE_NOT_FOUND',404,'آیتم صف پیدا نشد.'],'queue_state_invalid'=>['QUEUE_STATE_INVALID',409,'وضعیت آیتم صف قابل تغییر نیست.']];[$code,$status,$message]=$map[$e->getMessage()]??['SYNC_FAILED',500,'پردازش همگام‌سازی انجام نشد.'];sync_fail($message,$code,$status,$status>=500?$e:null);}
    catch(Throwable $e){sync_fail('پردازش همگام‌سازی انجام نشد.','SYNC_INTERNAL_ERROR',500,$e);}
}
