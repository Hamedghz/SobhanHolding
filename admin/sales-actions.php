<?php
require_once __DIR__ . '/../core/Auth.php';

Auth::requireLogin();
header('Location: /admin/action-hub.php', true, 302);
exit;
