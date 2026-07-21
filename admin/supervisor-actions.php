<?php
require_once __DIR__ . '/../core/Auth.php';

Auth::requireLogin();
header('Location: /admin/supervisor-dashboard.php?action_panel=1#supervisor-actions', true, 302);
exit;
