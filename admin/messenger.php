<?php require_once __DIR__.'/../core/Auth.php';Auth::requireLogin();header('Location: /employee/messenger.php');exit;
