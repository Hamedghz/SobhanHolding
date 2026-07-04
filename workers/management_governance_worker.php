<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../lib/ManagementMeetingsRepository.php';
try {
    $result=ManagementMeetingsRepository::notifyOverdueDecisions((int)($argv[1]??200));
    echo json_encode(['ok'=>true,'data'=>$result],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL;
} catch (Throwable $e) {
    error_log('Management governance worker: '.$e->getMessage());
    fwrite(STDERR,"اجرای پایش مصوبات عقب‌افتاده ناموفق بود.\n");
    exit(1);
}
