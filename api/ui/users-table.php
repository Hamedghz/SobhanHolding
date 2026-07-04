<?php
require __DIR__ . '/_bootstrap.php';

ui_run(function (): array {
    $user = ui_require_any_permission(['users']);
    [$page, $perPage, $offset] = ui_pagination();
    $where = ['1=1'];
    $params = [];
    if (!OrgAccess::isAdmin($user)) {
        [$scopeSql, $scopeParams] = OrgAccess::scopeSql($user, 'u.id');
        $where[] = $scopeSql;
        array_push($params, ...$scopeParams);
    }
    $search = ui_search_term();
    if ($search !== '') {
        $where[] = '(u.name LIKE ? OR u.username LIKE ? OR u.email LIKE ? OR u.employee_no LIKE ?)';
        $like = '%' . $search . '%';
        array_push($params, $like, $like, $like, $like);
    }
    foreach (['org_unit_id' => 'u.org_unit_id', 'org_role_id' => 'u.org_role_id'] as $key => $column) {
        $value = (int)($_GET[$key] ?? 0);
        if ($value > 0) { $where[] = $column . '=?'; $params[] = $value; }
    }
    foreach (['role' => 'u.role', 'status' => 'u.status', 'sales_line' => 'u.sales_line'] as $key => $column) {
        $value = mb_substr(trim((string)($_GET[$key] ?? '')), 0, 50);
        if ($value !== '') { $where[] = $column . '=?'; $params[] = $value; }
    }
    $whereSql = implode(' AND ', $where);
    $total = (int)(Database::fetch('SELECT COUNT(*) total FROM users u WHERE ' . $whereSql, $params)['total'] ?? 0);
    $rows = Database::fetchAll(
        'SELECT u.id,u.name,u.username,u.email,u.employee_no,u.mobile,u.role,u.status,u.department,u.role_key,u.sales_line,u.org_unit_id,u.org_role_id,u.parent_user_id,u.access_scope,u.employee_panel_enabled,u.admin_panel_enabled,u.last_login_at,ou.title org_unit_title,orr.title org_role_title,p.name parent_name FROM users u LEFT JOIN org_units ou ON ou.id=u.org_unit_id LEFT JOIN org_roles orr ON orr.id=u.org_role_id LEFT JOIN users p ON p.id=u.parent_user_id WHERE ' . $whereSql . ' ORDER BY u.display_order,u.id DESC LIMIT ' . $perPage . ' OFFSET ' . $offset,
        $params
    );
    return ['data' => ['items' => $rows], 'meta' => ui_pagination_meta($page, $perPage, $total)];
}, 'ui-users-table');
