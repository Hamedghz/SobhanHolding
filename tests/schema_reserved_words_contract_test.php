<?php

$root = dirname(__DIR__);
$schema = (string)file_get_contents($root . '/database/schema.sql');
$database = (string)file_get_contents($root . '/core/Database.php');

foreach ([
    $schema => ['`from_date` DATE NULL', '`to_date` DATE NULL'],
    $database => ['`from_date` DATE NULL', '`to_date` DATE NULL', 'AFTER `from_date`', 'AFTER `to_date`'],
] as $source => $tokens) {
    foreach ($tokens as $token) {
        if (!str_contains($source, $token)) {
            throw new RuntimeException('Reserved date identifier is not quoted: ' . $token);
        }
    }
}

echo "Schema reserved words contract: PASS\n";
