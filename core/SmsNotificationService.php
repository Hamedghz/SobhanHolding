<?php
require_once __DIR__.'/SmsGatewayService.php';require_once __DIR__.'/SmsTemplateService.php';
class SmsNotificationService
{
    public static function sendSystemSms(string $moduleKey,string $eventKey,array $mobiles,array $placeholders=[],?int $sourceId=null,?int $actorId=null): array
    {
        $template=SmsTemplateService::findForEvent($moduleKey,$eventKey);if(!$template)return ['success'=>false,'message'=>'قالب پیامک این رویداد تعریف نشده است.','batches'=>[]];$message=SmsTemplateService::render($template,$placeholders);return SmsGatewayService::active()->sendSimpleSms($mobiles,$message,null,$actorId,$moduleKey,$sourceId);
    }
}
