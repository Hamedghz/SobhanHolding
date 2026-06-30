<?php
require_once __DIR__.'/../ManagementMeetingsRepository.php';
final class DecisionRepository
{
    public const UI_STATUSES=['new'=>'جدید','in_progress'=>'در حال انجام','waiting'=>'در انتظار','done'=>'انجام‌شده','verified'=>'تأییدشده','cancelled'=>'لغوشده','overdue'=>'عقب‌افتاده'];
    public static function all(array $filters=[],?array $user=null):array{return ManagementMeetingsRepository::getDecisions($filters,$user);}
    public static function find(int $id,?array $user=null):?array{return ManagementMeetingsRepository::getDecisionById($id,$user);}
    public static function create(array $data,int $actorId):int{$data['followup_status']=$data['followup_status']??'new';return ManagementMeetingsRepository::createDecision($data,$actorId);}
    public static function update(int $id,array $data,int $actorId):void{ManagementMeetingsRepository::updateDecision($id,$data,$actorId);}
    public static function setStatus(int $id,string $status,int $progress,string $note,int $actorId,?string $nextDate=null,?string $attachmentJson=null):void{ManagementMeetingsRepository::updateDecisionStatus($id,$status,$progress,$note,$actorId,$nextDate,$attachmentJson);}
}
