<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Response.php';
Auth::requireLogin();
if(!Auth::canManageSystemTools()){http_response_code(403);exit('برای این بخش مجوز مدیریت سیستم لازم است.');}
header('Location: /admin/install-tools.php');
exit;
