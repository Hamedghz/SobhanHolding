<?php
require_once __DIR__.'/../lib/PayrollExportService.php';if(!PayrollRepository::canManage()&&!Auth::can('payroll.view_all')){http_response_code(403);exit('دسترسی غیرمجاز است.');}try{PayrollExportService::pdf((int)($_GET['id']??0),false);}catch(Throwable $e){error_log('Payroll PDF: '.$e->getMessage());http_response_code(404);exit('خروجی فیش در دسترس نیست.');}
