<?php
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/HrModule.php';

header('Content-Type: application/json; charset=utf-8');

$respond = static function (bool $success, string $message, mixed $data = null, ?string $code = null, int $status = 200): never {
    http_response_code($status);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'meta' => [],
        'error' => $code ? ['code' => $code] : null,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
};

try {
    Auth::requireLogin();
    if (!Auth::can('hr_kpi.score')) $respond(false, 'دسترسی مشاهده قالب‌های ارزیابی را ندارید.', null, 'PERMISSION_DENIED', 403);
    if (!Auth::verifyCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) $respond(false, 'اعتبار درخواست منقضی شده است.', null, 'CSRF_INVALID', 419);

    $employeeId = max(0, (int)($_GET['employee_id'] ?? 0));
    $templateId = max(0, (int)($_GET['template_id'] ?? 0));
    $periodId = max(0, (int)($_GET['period_id'] ?? 0));
    $accessibleIds = HrModule::accessibleEmployeeIds(Auth::user());
    if (!$employeeId || !in_array($employeeId, $accessibleIds, true)) $respond(false, 'پرسنل انتخاب‌شده در محدوده دسترسی شما نیست.', null, 'EMPLOYEE_OUT_OF_SCOPE', 403);

    $templates = Database::fetchAll(
        'SELECT t.id,t.title,t.description,t.category,t.role_key,t.department
         FROM hr_kpi_templates t
         JOIN hr_kpi_employee_assignments a ON a.template_id=t.id AND a.employee_id=? AND a.active=1
         WHERE t.active=1 ORDER BY t.sort_order,t.id',
        [$employeeId]
    );
    $data = ['templates' => $templates, 'template' => null, 'criteria' => []];
    if (!$templateId) $respond(true, $templates ? 'قالب‌های مجاز دریافت شدند.' : 'قالب فعالی به این پرسنل تخصیص داده نشده است.', $data);

    $template = Database::fetch(
        'SELECT t.id,t.title,t.description,t.category,t.role_key,t.department
         FROM hr_kpi_templates t
         JOIN hr_kpi_employee_assignments a ON a.template_id=t.id AND a.employee_id=? AND a.active=1
         WHERE t.id=? AND t.active=1 LIMIT 1',
        [$employeeId, $templateId]
    );
    if (!$template) $respond(false, 'قالب انتخاب‌شده فعال یا مجاز نیست.', null, 'TEMPLATE_OUT_OF_SCOPE', 404);
    $criteria = Database::fetchAll(
        'SELECT c.id,c.criteria_text,c.category,c.weight,c.max_score,c.sort_order,s.score,s.notes
         FROM hr_kpi_criteria c
         LEFT JOIN hr_kpi_scores s ON s.criteria_id=c.id AND s.employee_id=? AND s.period_id=?
         WHERE c.template_id=? AND c.active=1 ORDER BY c.sort_order,c.id',
        [$employeeId, $periodId, $templateId]
    );
    $data['template'] = $template;
    $data['criteria'] = $criteria;
    $respond(true, 'قالب ارزیابی با موفقیت بارگذاری شد.', $data);
} catch (Throwable $error) {
    error_log('HR KPI template endpoint [' . get_class($error) . ']');
    $respond(false, 'بارگذاری قالب ارزیابی امکان‌پذیر نبود.', null, 'TEMPLATE_LOAD_FAILED', 500);
}
