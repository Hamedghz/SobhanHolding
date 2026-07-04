<?php
require __DIR__ . '/_bootstrap.php';

ui_run(function (): array {
    $user = ui_require_any_permission(['hr_assessments.results']);
    [$page, $perPage, $offset] = ui_pagination();
    [$scopeSql, $scopeParams] = OrgAccess::scopeSql($user, 'u.id');
    $where = [$scopeSql];
    $params = $scopeParams;
    foreach (['employee_id' => 'r.employee_id', 'test_id' => 'r.test_id'] as $key => $column) {
        $value = (int)($_GET[$key] ?? 0);
        if ($value > 0) { $where[] = $column . '=?'; $params[] = $value; }
    }
    $riskLevel = mb_substr(trim((string)($_GET['risk_level'] ?? '')), 0, 40);
    if ($riskLevel !== '') { $where[] = 'r.risk_level=?'; $params[] = $riskLevel; }
    $search = ui_search_term();
    if ($search !== '') {
        $where[] = '(u.name LIKE ? OR u.employee_no LIKE ? OR t.title LIKE ?)';
        $like = '%' . $search . '%';
        array_push($params, $like, $like, $like);
    }
    $whereSql = ' WHERE ' . implode(' AND ', $where);
    $from = ' FROM hr_assessment_results r JOIN users u ON u.id=r.employee_id JOIN hr_assessment_tests t ON t.id=r.test_id LEFT JOIN hr_assessment_assignments a ON a.id=r.assignment_id LEFT JOIN org_units ou ON ou.id=u.org_unit_id LEFT JOIN org_roles orr ON orr.id=u.org_role_id';
    $total = (int)(Database::fetch('SELECT COUNT(*) total' . $from . $whereSql, $params)['total'] ?? 0);
    $rows = Database::fetchAll('SELECT r.id,r.assignment_id,r.employee_id,r.test_id,u.name,u.employee_no,ou.title org_unit_title,orr.title org_role_title,t.title test_title,a.period_key,a.status assignment_status,r.final_result,r.risk_level,r.profile_summary,r.recommendation_text,r.calculated_at,r.created_at' . $from . $whereSql . ' ORDER BY COALESCE(r.calculated_at,r.created_at) DESC,r.id DESC LIMIT ' . $perPage . ' OFFSET ' . $offset, $params);
    return ['data' => ['items' => $rows], 'meta' => ui_pagination_meta($page, $perPage, $total)];
}, 'ui-assessment-results-table');
