<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Response.php';
Auth::requireLogin();
if(!Auth::isAdmin()){http_response_code(403);exit('دسترسی غیرمجاز است.');}
header('Location: /admin/system-maintenance.php');
exit;
