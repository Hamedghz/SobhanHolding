<?php
require_once __DIR__.'/../../../core/Database.php';require_once __DIR__.'/../../../lib/messenger/MessengerService.php';header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
function internal_json(array $p,int $s=200):never{http_response_code($s);echo json_encode($p,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
try{$key=(string)($_SERVER['HTTP_X_MESSENGER_INTERNAL_KEY']??'');$expected=MessengerService::setting('messenger.realtime_internal_key');if($key===''||$expected===''||!hash_equals($expected,$key))internal_json(['ok'=>false],401);}catch(Throwable $e){internal_json(['ok'=>false],503);}
function internal_input():array{$j=json_decode((string)file_get_contents('php://input'),true);return is_array($j)?$j:$_POST;}
