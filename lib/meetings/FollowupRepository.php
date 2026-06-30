<?php
require_once __DIR__.'/../ManagementMeetingsRepository.php';
final class FollowupRepository
{
    public static function all(int $decisionId):array{return ManagementMeetingsRepository::getDecisionFollowups($decisionId);}
    public static function add(int $decisionId,?string $oldStatus,string $newStatus,int $progress,string $note,?string $nextDate,?string $attachmentJson,int $actorId):void{ManagementMeetingsRepository::addFollowup($decisionId,$oldStatus,$newStatus,$progress,$note,$nextDate,$attachmentJson,$actorId);}
}
