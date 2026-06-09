<?php
$config = file_exists(__DIR__ . '/config.php') ? require __DIR__ . '/config.php' : [];
return $config['db'] ?? [];
