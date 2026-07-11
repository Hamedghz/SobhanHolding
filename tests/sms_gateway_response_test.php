<?php
if(!defined('WSDL_CACHE_NONE'))define('WSDL_CACHE_NONE',0);
class SoapClient
{
    public static mixed $credit='{"result":"1500"}';
    public function __construct(string $wsdl,array $options=[]){if(!str_contains($wsdl,'?wsdl'))throw new RuntimeException('bad wsdl');}
    public function GetCredit(string $username,string $password): mixed{return self::$credit;}
}
require_once dirname(__DIR__).'/core/SmsGatewayService.php';
$service=new SmsGatewayService(['wsdl_url'=>'http://example.test/server.php?wsdl','username'=>'hamed','password'=>'secret','default_sender'=>'3000','is_active'=>1]);
$credit=$service->getCredit();if(!$credit['success']||$credit['credit']!=='1500')throw new RuntimeException('JSON credit parsing failed');
SoapClient::$credit='2500';$credit=$service->getCredit();if(!$credit['success']||$credit['credit']!=='2500')throw new RuntimeException('Numeric credit parsing failed');
SoapClient::$credit='{"result":"301"}';$credit=$service->getCredit();if($credit['success']||$credit['error_code']!=='301'||$credit['message']!=='نام کاربری یا رمز عبور اشتباه است.')throw new RuntimeException('Provider error classification failed');
SoapClient::$credit='{"result":"3000"}';$diagnostics=$service->getDiagnostics();if(!$diagnostics['success']||count($diagnostics['items'])!==5)throw new RuntimeException('Diagnostic sequence failed');
echo "SMS_GATEWAY_RESPONSE_OK\n";
