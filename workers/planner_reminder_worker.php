<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../services/WorkPlannerService.php';
try {
    $result=WorkPlannerService::sendDueReminders((int)($argv[1]??200));
    echo json_encode(['ok'=>true,'data'=>$result],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL;
} catch (Throwable $e) {
    error_log('Planner reminder worker: '.$e->getMessage());
    fwrite(STDERR,"اجرای یادآوری برنامه کاری ناموفق بود.\n");
    exit(1);
}
