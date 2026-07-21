<?php
require_once __DIR__ . '/../core/Auth.php';

Auth::requireLogin();
header('Location: /admin/daily-work-report.php', true, 302);
exit;
