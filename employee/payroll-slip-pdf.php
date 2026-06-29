<?php
require_once __DIR__.'/../core/Auth.php';require_once __DIR__.'/../lib/PayrollExportService.php';Auth::requireLogin();try{PayrollExportService::pdf((int)($_GET['id']??0),true);}catch(Throwable $e){error_log('Employee payroll PDF: '.$e->getMessage());http_response_code(404);exit('خروجی فیش در دسترس نیست.');}
