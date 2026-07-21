<?php

$root = dirname(__DIR__);
$pharmacy = (string)file_get_contents($root . '/admin/pharmacy-settings.php');

foreach ([
    '$result[\'errors\'][] = $e->getMessage()',
    'flash($e->getMessage()',
] as $unsafeToken) {
    if (str_contains($pharmacy, $unsafeToken)) {
        throw new RuntimeException('Raw exception text is exposed by pharmacy import/export: ' . $unsafeToken);
    }
}

foreach ([
    'Pharmacy import apply:',
    'Pharmacy export:',
    'Pharmacy import preview:',
    'جزئیات فنی در لاگ ثبت شد',
] as $safeToken) {
    if (!str_contains($pharmacy, $safeToken)) {
        throw new RuntimeException('Sanitized pharmacy error contract is missing: ' . $safeToken);
    }
}

echo "UI error sanitization contract: PASS\n";
