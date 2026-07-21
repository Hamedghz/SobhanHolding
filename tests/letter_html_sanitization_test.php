<?php

require_once dirname(__DIR__) . '/core/LetterModule.php';

$unsafe = '<p onclick="alert(1)" style="color:#123;background-color:#fff;position:fixed">سلام</p>'
    . '<script>alert(1)</script>'
    . '<a href="javascript:alert(1)" target="_blank">بد</a>'
    . '<a href="https://example.com" target="_blank">خوب</a>'
    . '<img src="data:text/html;base64,AAAA" onerror="alert(1)">';
$clean = LetterModule::sanitizeHtml($unsafe);
foreach (['<script','onclick=','onerror=','javascript:','position:fixed','data:text/html'] as $token) {
    if (stripos($clean, $token) !== false) throw new RuntimeException('Unsafe HTML survived sanitization: ' . $token);
}
foreach (['سلام','https://example.com'] as $token) {
    if (!str_contains($clean, $token)) throw new RuntimeException('Expected safe content was removed: ' . $token);
}
$delta = LetterModule::sanitizeDelta('{"ops":[{"insert":"سلام\\n","attributes":{"direction":"rtl"}}]}');
if ($delta === null || !str_contains($delta, '"ops"')) throw new RuntimeException('Valid Delta was not retained.');
try {
    LetterModule::sanitizeDelta('{"unexpected":true}');
    throw new RuntimeException('Invalid Delta was accepted.');
} catch (InvalidArgumentException) {
}

echo "Letter HTML sanitization: PASS\n";
