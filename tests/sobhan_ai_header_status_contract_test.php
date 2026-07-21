<?php

$root = dirname(__DIR__);
$service = file_get_contents($root . '/core/SobhanAiStatus.php');
$endpoint = file_get_contents($root . '/admin/ajax/sobhan-ai-status.php');
$header = file_get_contents($root . '/views/partials/admin-header.php');
$footer = file_get_contents($root . '/views/partials/admin-footer.php');
$script = file_get_contents($root . '/assets/js/sobhan-ai-status.js');
$css = file_get_contents($root . '/assets/css/admin.css');

$checks = [
    'header reads cached status only' => str_contains($header, 'SobhanAiStatus::cached()') && !str_contains($header, 'SobhanAiStatus::current('),
    'network refresh is deferred to browser' => str_contains($script, "fetch('/admin/ajax/sobhan-ai-status.php'"),
    'browser refresh is scheduled and overlap-safe' => str_contains($script, 'setInterval(refresh,60000)') && str_contains($script, 'refreshing') && str_contains($script, 'document.hidden'),
    'browser request has a bounded timeout' => str_contains($script, 'AbortController') && str_contains($script, 'controller.abort()'),
    'remote request uses short timeout' => str_contains($service, 'SobhanApiClient($baseUrl, $apiKey, 2, true)'),
    'status cache has a bounded ttl' => str_contains($service, 'TTL_SECONDS = 60'),
    'health endpoint uses existing authenticated client' => str_contains($service, "->get('/health')"),
    'response excludes secrets and raw errors' => !preg_match('/api[_ -]?key|error_message|exception/i', $endpoint),
    'header link is permission aware' => str_contains($header, "Auth::can('view_sobhan_api_settings')"),
    'last successful check is exposed as tooltip' => str_contains($header, 'last_success_at') && str_contains($script, 'last_success_at'),
    'healthy and unavailable states are styled' => str_contains($css, '.sobhan-ai-indicator.is-healthy') && str_contains($css, '.sobhan-ai-indicator.is-unavailable'),
    'status script is loaded once in shared footer' => substr_count($footer, '/assets/js/sobhan-ai-status.js') === 1,
];

$failed = array_keys(array_filter($checks, static fn(bool $passed): bool => !$passed));
if ($failed) {
    fwrite(STDERR, "Sobhan AI header status contract FAILED:\n- " . implode("\n- ", $failed) . "\n");
    exit(1);
}

echo "Sobhan AI header status contract PASS (" . count($checks) . " checks)\n";
