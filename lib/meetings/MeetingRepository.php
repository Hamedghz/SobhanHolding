<?php
require_once __DIR__.'/../ManagementMeetingsRepository.php';
final class MeetingRepository
{
    public static function canCreate(?array $user=null):bool{return ManagementMeetingsRepository::canCreateMeeting($user);}
    public static function all(array $filters=[],?array $user=null):array{return ManagementMeetingsRepository::getMeetings($filters,$user);}
    public static function find(int $id,?array $user=null):?array{return ManagementMeetingsRepository::getMeetingById($id,$user);}
    public static function create(array $data,int $actorId):int{return ManagementMeetingsRepository::createMeeting($data,$actorId);}
    public static function update(int $id,array $data,int $actorId):void{ManagementMeetingsRepository::updateMeeting($id,$data,$actorId);}
    public static function finalize(int $id,int $actorId):void{ManagementMeetingsRepository::finalizeMeeting($id,$actorId);}
    public static function archive(int $id,int $actorId):void{ManagementMeetingsRepository::archiveMeeting($id,$actorId);}
}
