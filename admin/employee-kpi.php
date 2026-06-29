<?php
define('SOBHAN_EMPLOYEE_ADMIN_ROUTE', true);
require_once __DIR__ . '/../core/Auth.php';
Auth::requireLogin();
if (!Auth::isAdmin() && !(int)(Auth::user()['employee_panel_enabled'] ?? 0)) {
    http_response_code(403);
    exit('پنل کارمندی برای این حساب فعال نیست.');
}
require __DIR__ . '/../employee/kpi-results.php';
