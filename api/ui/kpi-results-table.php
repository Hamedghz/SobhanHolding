<?php
require __DIR__ . '/_bootstrap.php';

ui_run(function (): array {
    $user = ui_require_any_permission(['hr_kpi.results']);
    [$page, $perPage, $offset] = ui_pagination();
    [$scopeSql, $scopeParams] = OrgAccess::scopeSql($user, 'u.id');
    $where = [$scopeSql];
    $params = $scopeParams;
    foreach (['employee_id' => 's.employee_id', 'period_id' => 's.period_id', 'template_id' => 's.template_id'] as $key => $column) {
        $value = (int)($_GET[$key] ?? 0);
        if ($value > 0) { $where[] = $column . '=?'; $params[] = $value; }
    }
    $search = ui_search_term();
    if ($search !== '') {
        $where[] = '(u.name LIKE ? OR u.employee_no LIKE ? OR t.title LIKE ? OR p.title LIKE ?)';
        $like = '%' . $search . '%';
        array_push($params, $like, $like, $like, $like);
    }
    foreach (['from_date' => '>=', 'to_date' => '<='] as $key => $operator) {
        $value = ui_date($key);
        if ($value) { $where[] = 'DATE(s.scored_at)' . $operator . '?'; $params[] = $value; }
    }
    $from = ' FROM hr_kpi_scores s JOIN users u ON u.id=s.employee_id LEFT JOIN hr_kpi_templates t ON t.id=s.template_id LEFT JOIN hr_kpi_periods p ON p.id=s.period_id LEFT JOIN hr_kpi_criteria c ON c.id=s.criteria_id LEFT JOIN org_units ou ON ou.id=u.org_unit_id LEFT JOIN org_roles orr ON orr.id=u.org_role_id LEFT JOIN users pu ON pu.id=COALESCE(u.parent_user_id,u.supervisor_id,u.organization_manager_id)';
    $group = ' GROUP BY s.employee_id,s.template_id,s.period_id,u.name,u.employee_no,ou.title,orr.title,u.sales_line,pu.name,t.title,p.title';
    $having = '';
    $status = (string)($_GET['status'] ?? '');
    if ($status === 'good') $having = ' HAVING average_score>=75';
    elseif ($status === 'needs_followup') $having = ' HAVING average_score<60';
    $whereSql = ' WHERE ' . implode(' AND ', $where);
    $select = 'SELECT s.employee_id,u.name,u.employee_no,ou.title org_unit_title,orr.title org_role_title,u.sales_line,pu.name parent_name,t.title template_title,s.template_id,p.title period_title,s.period_id,ROUND(SUM(COALESCE(s.score,0)*COALESCE(c.weight,1)),2) total_score,ROUND(SUM(COALESCE(s.score,0)*COALESCE(c.weight,1))/NULLIF(SUM(COALESCE(c.max_score,0)*COALESCE(c.weight,1)),0)*100,2) average_score,MAX(s.scored_at) scored_at,MAX(s.updated_at) updated_at';
    $baseSql = $select . $from . $whereSql . $group . $having;
    $total = (int)(Database::fetch('SELECT COUNT(*) total FROM (' . $baseSql . ') scoped_kpi_results', $params)['total'] ?? 0);
    $rows = Database::fetchAll($baseSql . ' ORDER BY scored_at DESC LIMIT ' . $perPage . ' OFFSET ' . $offset, $params);
    foreach ($rows as &$row) {
        $score = $row['average_score'] === null ? 0.0 : (float)$row['average_score'];
        $row['average_score'] = $score;
        $row['status'] = $score >= 75 ? 'خوب' : ($score >= 60 ? 'پایدار' : 'نیازمند پیگیری');
    }
    unset($row);
    return ['data' => ['items' => $rows], 'meta' => ui_pagination_meta($page, $perPage, $total)];
}, 'ui-kpi-results-table');
