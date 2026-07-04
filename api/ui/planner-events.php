<?php
require __DIR__ . '/_bootstrap.php';

ui_run(function (): array {
    $user = ui_user();
    [$page, $perPage, $offset] = ui_pagination(100, 500);
    $today = new DateTimeImmutable('today');
    $from = ui_date('from', $today->modify('first day of this month')->format('Y-m-d'));
    $to = ui_date('to', $today->modify('last day of this month')->format('Y-m-d'));
    if (!$from || !$to || $from > $to) throw new InvalidArgumentException('بازه تاریخ معتبر نیست.');
    $fromDate = new DateTimeImmutable($from);
    $toDate = new DateTimeImmutable($to);
    if ($fromDate->diff($toDate)->days > 366) throw new InvalidArgumentException('بازه تقویم نمی‌تواند بیشتر از یک سال باشد.');
    $where = ['user_id=?', 'deleted_at IS NULL', 'task_date BETWEEN ? AND ?'];
    $params = [(int)$user['id'], $from, $to];
    $status = mb_substr(trim((string)($_GET['status'] ?? '')), 0, 30);
    if ($status !== '') { $where[] = 'status=?'; $params[] = $status; }
    $search = ui_search_term();
    if ($search !== '') { $where[] = '(title LIKE ? OR description LIKE ?)'; $like = '%' . $search . '%'; array_push($params, $like, $like); }
    $whereSql = implode(' AND ', $where);
    $total = (int)(Database::fetch('SELECT COUNT(*) total FROM personal_planner_tasks WHERE ' . $whereSql, $params)['total'] ?? 0);
    $rows = Database::fetchAll('SELECT id,title,description,task_date,due_at,status,priority,is_important,is_recurring,recurrence_type,parent_task_id,reminder_enabled,reminder_at,completed_at,updated_at FROM personal_planner_tasks WHERE ' . $whereSql . ' ORDER BY task_date,due_at,sort_order,id LIMIT ' . $perPage . ' OFFSET ' . $offset, $params);
    $items = array_map(static fn(array $row): array => [
        'id' => (int)$row['id'],
        'title' => $row['title'],
        'description' => $row['description'],
        'start' => $row['task_date'],
        'due_at' => $row['due_at'],
        'status' => $row['status'],
        'priority' => $row['priority'],
        'important' => (bool)$row['is_important'],
        'recurring' => (bool)$row['is_recurring'],
        'recurrence_type' => $row['recurrence_type'],
        'parent_task_id' => $row['parent_task_id'] ? (int)$row['parent_task_id'] : null,
        'reminder_enabled' => (bool)$row['reminder_enabled'],
        'reminder_at' => $row['reminder_at'],
        'completed_at' => $row['completed_at'],
        'updated_at' => $row['updated_at'],
    ], $rows);
    $meta = ui_pagination_meta($page, $perPage, $total);
    $meta['range'] = ['from' => $from, 'to' => $to];
    return ['data' => ['items' => $items], 'meta' => $meta];
}, 'ui-planner-events');
