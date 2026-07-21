<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../lib/OkrService.php';
Auth::requireLogin();
$evidence = OkrService::evidence((int)($_GET['id'] ?? 0));
if (!$evidence) { http_response_code(404); exit('فایل یافت نشد.'); }
$base = realpath(__DIR__ . '/../storage/okr-evidence');
$path = $base ? realpath($base . DIRECTORY_SEPARATOR . basename((string)$evidence['stored_name'])) : false;
if (!$base || !$path || !str_starts_with($path, $base . DIRECTORY_SEPARATOR) || !is_file($path)) { http_response_code(404); exit('فایل یافت نشد.'); }
Auth::log((int)Auth::user()['id'], 'okr_evidence_downloaded', 'okr', (int)$evidence['objective_id']);
header('Content-Type: ' . $evidence['mime_type']);
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
header("Content-Disposition: attachment; filename*=UTF-8''" . rawurlencode((string)$evidence['original_name']));
readfile($path);
exit;
