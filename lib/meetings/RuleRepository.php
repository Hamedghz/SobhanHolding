<?php
require_once __DIR__.'/../ManagementMeetingsRepository.php';
final class RuleRepository
{
    public static function all(array $filters=[],?array $user=null):array{$rows=ManagementMeetingsRepository::getActiveRules($filters,$user);if(!$rows)return [];$ids=array_values(array_unique(array_map('intval',array_column($rows,'decision_id'))));$eligible=Database::fetchAll('SELECT d.id FROM management_decisions d JOIN management_meetings m ON m.id=d.meeting_id WHERE d.id IN ('.implode(',',array_fill(0,count($ids),'?')).') AND d.is_rule=1 AND (d.followup_status="verified" OR m.status="finalized")',$ids);$allowed=array_flip(array_map('intval',array_column($eligible,'id')));return array_values(array_filter($rows,static fn(array $rule):bool=>isset($allowed[(int)$rule['decision_id']])));}
    public static function publish(int $decisionId,array $data,int $actorId):int{
        $decision=ManagementMeetingsRepository::getDecisionById($decisionId,Auth::user());if(!$decision||(int)$decision['is_rule']!==1)throw new DomainException('این مصوبه به عنوان قانون علامت‌گذاری نشده است.');if(($decision['followup_status']??'')!=='verified'||($decision['verification_status']??'')!=='verified')throw new DomainException('پیش از انتشار قانون، مصوبه باید تأیید نهایی شود.');return ManagementMeetingsRepository::convertDecisionToRule($decisionId,$data,$actorId);
    }
    public static function versions(int $decisionId):array{return ManagementMeetingsRepository::getRuleVersions($decisionId);}
    public static function archive(int $id,int $actorId):void{ManagementMeetingsRepository::archiveRule($id,$actorId);}
}
