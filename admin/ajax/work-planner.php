<?php
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Response.php';
require_once __DIR__ . '/../../services/WorkPlannerService.php';

header('Content-Type: application/json; charset=utf-8');
$respond = static function (bool $success, string $message, array $data = [], int $status = 200): never {
    http_response_code($status);
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
};
try {
    Auth::requireLogin();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') $respond(false, 'روش درخواست مجاز نیست.', [], 405);
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) $respond(false, 'اعتبار فرم منقضی شده است.', [], 419);
    $userId = (int)(Auth::user()['id'] ?? 0);
    $action = (string)($_POST['action'] ?? '');
    $taskId = (int)($_POST['task_id'] ?? 0);
    if (in_array($action, ['quick_add', 'planner_quick_add'], true)) {
        $input = $_POST;
        $input['due_date'] = date('Y-m-d');
        $id = WorkPlannerService::createPersonalTask($userId, $input, date('Y-m-d'));
        $respond(true, 'وظیفه امروز افزوده شد.', ['task_id' => $id]);
    }
    if ($action === 'status') {
        $status = (string)($_POST['status'] ?? '');
        if (!WorkPlannerService::updateTaskStatus($taskId, $status, $userId)) throw new InvalidArgumentException('تغییر وضعیت این وظیفه مجاز نیست.');
        $respond(true, $status === 'done' ? 'وظیفه تکمیل شد.' : ($status === 'in_progress' ? 'انجام وظیفه شروع شد.' : 'وظیفه مکث شد.'));
    }
    if (in_array($action, ['tomorrow', 'planner_tomorrow'], true)) {
        if (!WorkPlannerService::moveToTomorrow($taskId, $userId)) throw new InvalidArgumentException('انتقال این وظیفه ممکن نیست.');
        $respond(true, 'وظیفه به فردا منتقل شد.');
    }
    if (in_array($action, ['urgent', 'planner_urgent'], true)) {
        if (!WorkPlannerService::markTaskUrgent($taskId, $userId)) throw new InvalidArgumentException('تغییر اولویت این وظیفه ممکن نیست.');
        $respond(true, 'وظیفه فوری شد.');
    }
    throw new InvalidArgumentException('عملیات پلنر معتبر نیست.');
} catch (InvalidArgumentException|DomainException $error) {
    $respond(false, $error->getMessage(), [], 422);
} catch (Throwable $error) {
    error_log('Dashboard planner AJAX: ' . $error->getMessage());
    $respond(false, 'عملیات پلنر انجام نشد. دوباره تلاش کنید.', [], 500);
}
