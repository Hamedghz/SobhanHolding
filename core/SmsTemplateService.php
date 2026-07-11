<?php
require_once __DIR__.'/Database.php';
class SmsTemplateService
{
    public const PLACEHOLDERS=['name','date','time','request_title','ticket_code','task_title','employee_name','manager_name','amount','status','link'];
    public static function find(string $code): ?array{return Database::fetch('SELECT * FROM sms_templates WHERE code=? AND is_active=1 LIMIT 1',[$code]);}
    public static function findForEvent(string $moduleKey,string $eventKey): ?array{return Database::fetch('SELECT * FROM sms_templates WHERE module_key=? AND event_key=? AND is_active=1 ORDER BY id LIMIT 1',[$moduleKey,$eventKey]);}
    public static function render(array $template,array $values): string
    {
        $body=(string)($template['body']??'');preg_match_all('/\{([a-z_]+)\}/i',$body,$matches);$missing=[];foreach(array_unique($matches[1]??[]) as $key){if(!in_array($key,self::PLACEHOLDERS,true)||!array_key_exists($key,$values))$missing[]=$key;else $body=str_replace('{'.$key.'}',trim((string)$values[$key]),$body);}if($missing)throw new InvalidArgumentException('مقدار جای‌گذارهای لازم کامل نیست: '.implode('، ',$missing));return trim($body);
    }
}
