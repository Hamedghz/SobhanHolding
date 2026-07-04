<?php

require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Response.php';
require_once __DIR__ . '/../../lib/OrgAccess.php';

ini_set('display_errors', '0');
json_require_method('GET');

function ui_user(): array
{
    $user = Auth::user();
    if (!$user) json_error('ابتدا وارد سامانه شوید.', 'UNAUTHENTICATED', 401);
    return $user;
}

function ui_require_any_permission(array $moduleKeys, string $action = 'view'): array
{
    $user = ui_user();
    foreach ($moduleKeys as $moduleKey) {
        if (Auth::can($moduleKey, $action)) return $user;
    }
    json_error('برای مشاهده این اطلاعات دسترسی ندارید.', 'FORBIDDEN', 403);
}

function ui_pagination(int $defaultPerPage = 25, int $maxPerPage = 100): array
{
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = max(1, min($maxPerPage, (int)($_GET['per_page'] ?? $defaultPerPage)));
    return [$page, $perPage, ($page - 1) * $perPage];
}

function ui_pagination_meta(int $page, int $perPage, int $total): array
{
    return [
        'page' => $page,
        'per_page' => $perPage,
        'total' => $total,
        'total_pages' => $total > 0 ? (int)ceil($total / $perPage) : 0,
    ];
}

function ui_search_term(): string
{
    return mb_substr(trim((string)($_GET['search'] ?? $_GET['q'] ?? '')), 0, 100);
}

function ui_date(string $key, ?string $default = null): ?string
{
    $value = trim((string)($_GET[$key] ?? $default ?? ''));
    if ($value === '') return null;
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    if (!$date || $date->format('Y-m-d') !== $value) throw new InvalidArgumentException('تاریخ واردشده معتبر نیست.');
    return $value;
}

function ui_run(callable $callback, string $context): never
{
    try {
        $result = $callback();
        json_success($result['data'] ?? [], $result['message'] ?? '', $result['meta'] ?? []);
    } catch (InvalidArgumentException|DomainException $e) {
        $message = trim($e->getMessage());
        if ($message === '' || preg_match('/^(?:[a-z0-9_.-]+|.*(?:SQLSTATE|PDO|SELECT|INSERT|UPDATE|DELETE|Stack trace|\.php).*)$/i', $message)) {
            $message = 'اطلاعات واردشده معتبر نیست.';
        }
        json_error($message, 'VALIDATION_ERROR', 422);
    } catch (Throwable $e) {
        json_error('دریافت اطلاعات انجام نشد. لطفاً دوباره تلاش کنید.', 'INTERNAL_ERROR', 500, $e, $context);
    }
}
