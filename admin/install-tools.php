<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Response.php';
Auth::requireLogin();
if (!Auth::canManageSystemTools()) {
    http_response_code(403);
    echo 'برای دسترسی به ابزارهای فنی مجوز مدیریت سیستم لازم است.';
    exit;
}
require __DIR__ . '/system-maintenance.php';
