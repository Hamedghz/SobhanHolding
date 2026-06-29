<?php
require_once __DIR__.'/../lib/PayrollSlipRenderer.php';if(!PayrollRepository::canManage()&&!Auth::can('payroll.view_all')){http_response_code(403);exit('دسترسی غیرمجاز است.');}try{echo PayrollSlipRenderer::page(PayrollSlipRenderer::data((int)($_GET['id']??0)),true);}catch(Throwable $e){http_response_code(404);echo 'فیش در دسترس نیست.';}
