<?php
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Response.php';
require_once __DIR__ . '/../../lib/messenger/MessengerService.php';
require_once __DIR__ . '/../../lib/messenger/MessengerFileService.php';
require_once __DIR__ . '/../../lib/messenger/MessengerLocationService.php';

header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
function messenger_json(array $data,int $status=200):never{http_response_code($status);$success=(bool)($data['ok']??false);$message=(string)($data['message']??'');$payload=['success'=>$success,'ok'=>$success,'data'=>$data['data']??null,'meta'=>$data['meta']??new stdClass(),'error'=>$success?null:['code'=>$status===403?'FORBIDDEN':($status===401?'UNAUTHENTICATED':'REQUEST_FAILED'),'message'=>$message?:'عملیات انجام نشد.']];if($message!=='')$payload['message']=$message;echo json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
function messenger_input():array{$raw=file_get_contents('php://input');$json=$raw!==''?json_decode($raw,true):null;return is_array($json)?$json:$_POST;}
function messenger_run(callable $callback,bool $mutating=false):never{try{$user=Auth::user();if(!$user)messenger_json(['ok'=>false,'message'=>'ابتدا وارد سامانه شوید.'],401);MessengerSecurity::requirePermission('messenger.view');if($mutating)MessengerSecurity::csrf();$result=$callback($user);messenger_json(['ok'=>true,'data'=>$result]);}catch(DomainException|InvalidArgumentException $e){$code=(int)$e->getCode();messenger_json(['ok'=>false,'message'=>$e->getMessage()],$code>=400&&$code<500?$code:422);}catch(Throwable $e){error_log('messenger-api: '.$e->getMessage());messenger_json(['ok'=>false,'message'=>'عملیات پیام‌رسان انجام نشد.'],500);}}
