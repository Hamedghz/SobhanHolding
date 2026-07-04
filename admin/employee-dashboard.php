<?php
require_once __DIR__.'/../core/Auth.php';require_once __DIR__.'/../core/Response.php';Auth::requireLogin();redirect('/admin/index.php');
