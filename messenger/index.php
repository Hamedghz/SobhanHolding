<?php
require_once __DIR__.'/../core/Auth.php';
require_once __DIR__.'/../core/Response.php';
require_once __DIR__.'/../services/MessengerForwardService.php';
Auth::requireLogin();
$user=Auth::user();$pageTitle='پیام‌رسان سازمانی';$adminExtraStylesheets=['/assets/css/messenger.css'];
$messages=MessengerForwardService::inbox((int)$user['id']);
require __DIR__.'/../views/partials/admin-header.php';
?>
<div class="messenger-page"><header class="messenger-hero"><div><span>پیام‌های امن و سازمانی</span><h1>پیام‌رسان سازمانی</h1><p>گزارش‌های فورواردشده به‌صورت Snapshot مستقل و فقط‌خواندنی نگهداری می‌شوند.</p></div><b><?=count($messages)?> پیام</b></header>
<section class="messenger-list"><?php foreach($messages as $message):$payload=json_decode((string)($message['payload_json']??''),true)?:[];?><article class="messenger-card <?=$message['status']==='unread'?'is-unread':''?>"><div class="messenger-card-icon">گزارش</div><div><header><h2><?=e($message['title'])?></h2><time><?=e(format_jalali_datetime($message['created_at']))?></time></header><p class="messenger-meta">از طرف <?=e($message['sender_name'])?><?php if($message['report_period']):?> · دوره <?=e(format_jalali_date($message['report_period']))?><?php endif?></p><?php if($message['body']):?><p><?=nl2br(e($message['body']))?></p><?php endif?><p class="messenger-preview"><?=e((string)($payload['preview_text']??''))?></p><footer><a class="btn" href="/messenger/report-view.php?id=<?=(int)$message['share_id']?>">مشاهده گزارش</a><?php if($message['attachment_path']):?><a class="btn btn-light" href="/messenger/report-attachment.php?id=<?=(int)$message['share_id']?>">دانلود پیوست</a><?php endif?><span class="badge"><?=$message['status']==='unread'?'خوانده‌نشده':'خوانده‌شده'?></span></footer></div></article><?php endforeach?><?php if(!$messages):?><div class="card messenger-empty"><h2>صندوق پیام شما خالی است</h2><p>گزارش یا پیام جدیدی برای شما ارسال نشده است.</p></div><?php endif?></section></div>
<?php require __DIR__.'/../views/partials/admin-footer.php';?>
