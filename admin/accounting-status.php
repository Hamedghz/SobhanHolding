<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';

header('Content-Type: application/json; charset=utf-8');
Auth::requirePermission('accounting', 'edit');

$statusLabels = [
    'sent' => 'ارسال شده',
    'registered' => 'ثبت شده',
    'needs_followup' => 'نیاز به پیگیری',
];

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'درخواست نامعتبر است'], JSON_UNESCAPED_UNICODE);
    exit;
}

$id = (int)($_POST['id'] ?? 0);
$status = $_POST['status'] ?? '';
if (!$id || !isset($statusLabels[$status])) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'وضعیت معتبر نیست'], JSON_UNESCAPED_UNICODE);
    exit;
}

Database::execute('UPDATE accounting_collections SET status = ?, updated_at = NOW() WHERE id = ?', [$status, $id]);
echo json_encode(['ok' => true, 'label' => $statusLabels[$status]], JSON_UNESCAPED_UNICODE);
