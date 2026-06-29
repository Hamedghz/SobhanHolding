<?php

require_once __DIR__.'/../core/Auth.php';
require_once __DIR__.'/../core/Response.php';
require_once __DIR__.'/../services/MessengerForwardService.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
ini_set('display_errors','0');

function forward_json(bool $ok, mixed $data = null, ?array $error = null, int $status = 200): never
{
    http_response_code($status);
    echo json_encode(['ok'=>$ok,'data'=>$data,'error'=>$error,'timestamp'=>date('c')], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    if (!Auth::user()) forward_json(false,null,['code'=>'authentication_required','message'=>'ابتدا وارد حساب کاربری شوید.'],401);
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') forward_json(false,null,['code'=>'method_not_allowed','message'=>'روش درخواست معتبر نیست.'],405);
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null))) forward_json(false,null,['code'=>'csrf_failed','message'=>'اعتبار درخواست منقضی شده است.'],419);
    $user = Auth::user();
    SalesReportShareService::assertCanForward($user);
    $filters = json_decode((string)($_POST['filters_json'] ?? '{}'),true);
    if (!is_array($filters)) $filters=[];
    $input = $_POST;
    $input['filters']=$filters;
    $input['recipient_ids']=$_POST['recipient_ids'] ?? [];
    if (($input['action'] ?? 'send') === 'preview') {
        $built=SalesReportShareService::build((int)($input['report_id']??0),(string)($input['report_type']??'summary_cards'),$filters,$user,(string)($input['title']??''));
        forward_json(true,['snapshot'=>$built['snapshot']]);
    }
    $result=MessengerForwardService::send($input,$user);
    forward_json(true,$result+['message'=>'گزارش با موفقیت در پیام‌رسان ارسال شد.']);
} catch (InvalidArgumentException $e) {
    forward_json(false,null,['code'=>'validation_failed','message'=>$e->getMessage()],422);
} catch (Throwable $e) {
    error_log('sales manager forward endpoint: '.$e->getMessage());
    $status=$e->getMessage()==='forward_access_denied'?403:500;
    forward_json(false,null,['code'=>$status===403?'access_denied':'forward_failed','message'=>$status===403?'دسترسی ارسال این گزارش را ندارید.':'ارسال گزارش انجام نشد. لطفاً دوباره تلاش کنید.'],$status);
}
