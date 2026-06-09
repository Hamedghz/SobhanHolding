<?php
$config = file_exists(__DIR__ . '/config.php') ? require __DIR__ . '/config.php' : [];
return $config['app'] ?? ['url' => '', 'name' => 'شرکت پخش سبحان', 'debug' => false];
