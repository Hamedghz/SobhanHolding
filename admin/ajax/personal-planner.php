<?php
require_once __DIR__.'/../../core/Auth.php';require_once __DIR__.'/../../core/Response.php';require_once __DIR__.'/../../services/PersonalPlannerService.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
function planner_json(bool $success,mixed $data=null,string $message='',?string $error=null,int $status=200):never{http_response_code($status);echo json_encode(['success'=>$success,'ok'=>$success,'data'=>$data,'message'=>$message,'error'=>$error],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
try{
 $user=Auth::user();if(!$user)planner_json(false,null,'نشست کاربری معتبر نیست.','UNAUTHENTICATED',401);if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST')planner_json(false,null,'روش درخواست معتبر نیست.','METHOD_NOT_ALLOWED',405);$raw=json_decode((string)file_get_contents('php://input'),true);$in=is_array($raw)?$raw:$_POST;$csrf=$_SERVER['HTTP_X_CSRF_TOKEN']??($in['csrf_token']??null);if(!Auth::verifyCsrf(is_string($csrf)?$csrf:null))planner_json(false,null,'اعتبار درخواست منقضی شده است.','CSRF_EXPIRED',419);
 $uid=(int)$user['id'];$action=trim((string)($in['action']??'load_day'));$date=trim((string)($in['date']??date('Y-m-d')));$id=(int)($in['id']??$in['task_id']??0);$message='اطلاعات بروزرسانی شد.';$data=null;
 switch($action){
  case 'load_day':$data=PersonalPlannerService::loadDay($uid,$date,$user);break;
  case 'load_week':$data=PersonalPlannerService::loadWeek($uid,$date);break;
  case 'load_month':$j=JalaliDate::toJalali($date);[$jy,$jm]=array_map('intval',array_slice(explode('/',$j),0,2));$data=PersonalPlannerService::loadMonth($uid,(int)($in['year']??$jy),(int)($in['month']??$jm));break;
  case 'add_task':$task=PersonalPlannerService::addTask($uid,(string)($in['title']??''),$date,$in);$data=['task'=>$task];$message='کار جدید اضافه شد.';break;
  case 'update_task':$data=['task'=>PersonalPlannerService::updateTask($uid,$id,$in)];$message='کار ویرایش شد.';break;
  case 'toggle_task':$data=['task'=>PersonalPlannerService::toggleTask($uid,$id)];$message='وضعیت کار تغییر کرد.';break;
  case 'delete_task':PersonalPlannerService::deleteTask($uid,$id);$message='کار بایگانی شد.';break;
  case 'toggle_important':$data=['task'=>PersonalPlannerService::toggleImportant($uid,$id)];break;
  case 'reorder_tasks':PersonalPlannerService::reorderTasks($uid,is_array($in['ids']??null)?$in['ids']:(json_decode((string)($in['ids']??'[]'),true)?:[]));break;
  case 'move_task_to_tomorrow':$data=['task'=>PersonalPlannerService::moveTaskToTomorrow($uid,$id)];$message='کار به فردا منتقل شد.';break;
  case 'move_task':$data=['task'=>PersonalPlannerService::moveTask($uid,$id,(string)($in['target_date']??''))];$message='تاریخ کار تغییر کرد.';break;
  case 'move_unfinished_to_tomorrow':$count=PersonalPlannerService::moveUnfinishedToTomorrow($uid,$date);$data=['moved_count'=>$count];$message="{$count} کار به فردا منتقل شد.";break;
  case 'create_recurring_next':$data=['task'=>PersonalPlannerService::createNextRecurringTask($uid,$id)];break;
  case 'save_note':PersonalPlannerService::saveNote($uid,$date,(string)($in['note_text']??''));$message='یادداشت ذخیره شد.';break;
  case 'load_checks':$data=['checks'=>PersonalPlannerService::getChecks($uid,$date)];break;
  case 'add_check':PersonalPlannerService::addCheck($uid,$date,(string)($in['title']??''));$message='پیگیری اضافه شد.';break;
  case 'update_check':PersonalPlannerService::updateCheck($uid,$id,(string)($in['title']??''));break;
  case 'toggle_check':PersonalPlannerService::toggleCheck($uid,$id);break;
  case 'delete_check':PersonalPlannerService::deleteCheck($uid,$id);$message='پیگیری حذف شد.';break;
  case 'load_reminders':PersonalPlannerService::generateDueNotifications($uid);$data=['items'=>PersonalPlannerService::dueNotifications($uid)];break;
  case 'mark_notification_sent':PersonalPlannerService::markNotificationSent($uid,$id);$message='یادآوری ثبت شد.';break;
  case 'get_report_summary':$data=PersonalPlannerService::report($uid,(string)($in['from']??$date),(string)($in['to']??$date));break;
  case 'get_settings':$data=PersonalPlannerService::bootstrap($uid,$user);break;
  case 'save_settings':$data=PersonalPlannerService::saveSettings($uid,$in);$message='تنظیمات ذخیره شد.';break;
  default:throw new InvalidArgumentException('عملیات درخواستی معتبر نیست.');
 }
 planner_json(true,$data,$message,null);
}catch(InvalidArgumentException|DomainException $e){planner_json(false,null,$e->getMessage(),'VALIDATION_ERROR',422);}catch(Throwable $e){error_log('Personal planner AJAX: '.$e->getMessage());planner_json(false,null,'انجام عملیات ممکن نیست. دوباره تلاش کنید.','INTERNAL_ERROR',500);}
