<?php
require __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../services/TicketService.php';

ui_run(function (): array {
    $user = ui_user();
    $canManage = TicketService::canManage();
    if (!$canManage && !Auth::can('ticketing.view')) json_error('برای مشاهده تیکت‌ها دسترسی ندارید.', 'FORBIDDEN', 403);
    [$page, $perPage, $offset] = ui_pagination();
    $where = [];
    $params = [];
    if (!$canManage) {
        $where[] = '(t.requester_user_id=? OR t.assigned_user_id=?)';
        array_push($params, (int)$user['id'], (int)$user['id']);
    }
    foreach (['category_id' => 't.category_id'] as $key => $column) {
        $value = (int)($_GET[$key] ?? 0);
        if ($value > 0) { $where[] = $column . '=?'; $params[] = $value; }
    }
    foreach (['status' => 't.status', 'priority' => 't.priority'] as $key => $column) {
        $value = mb_substr(trim((string)($_GET[$key] ?? '')), 0, 30);
        if ($value !== '') { $where[] = $column . '=?'; $params[] = $value; }
    }
    $search = ui_search_term();
    if ($search !== '') {
        $where[] = '(t.ticket_no LIKE ? OR t.subject LIKE ? OR r.name LIKE ?)';
        $like = '%' . $search . '%';
        array_push($params, $like, $like, $like);
    }
    $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
    $joins = ' FROM tickets t JOIN ticket_categories c ON c.id=t.category_id JOIN users r ON r.id=t.requester_user_id LEFT JOIN users a ON a.id=t.assigned_user_id LEFT JOIN org_units u ON u.id=t.assigned_unit_id';
    $total = (int)(Database::fetch('SELECT COUNT(*) total' . $joins . $whereSql, $params)['total'] ?? 0);
    $rows = Database::fetchAll('SELECT t.id,t.ticket_no,t.subject,t.priority,t.status,t.requester_user_id,t.assigned_user_id,t.assigned_unit_id,t.due_at,t.last_message_at,t.created_at,c.title category_title,r.name requester_name,a.name assignee_name,u.title unit_title' . $joins . $whereSql . ' ORDER BY t.updated_at DESC,t.id DESC LIMIT ' . $perPage . ' OFFSET ' . $offset, $params);
    return ['data' => ['items' => $rows], 'meta' => ui_pagination_meta($page, $perPage, $total)];
}, 'ui-tickets-table');
