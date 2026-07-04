<?php
require __DIR__ . '/_bootstrap.php';

ui_run(function (): array {
    ui_require_any_permission(['view_ceo_dashboard', 'ceo_dashboard']);
    $reportDate = ui_date('report_date');
    if (!$reportDate && trim((string)($_GET['report_date'] ?? '')) === '') {
        $latest = Database::fetch('SELECT MAX(report_date) report_date FROM (SELECT report_date FROM ceo_dashboard_lines WHERE active=1 UNION ALL SELECT report_date FROM ceo_dashboard_visitors WHERE active=1) dashboard_dates');
        $reportDate = $latest['report_date'] ?? null;
    }
    $lineCode = mb_substr(trim((string)($_GET['line_code'] ?? '')), 0, 50);
    $where = ['active=1'];
    $params = [];
    if ($reportDate) { $where[] = 'report_date=?'; $params[] = $reportDate; }
    if ($lineCode !== '') { $where[] = 'line_code=?'; $params[] = $lineCode; }
    $whereSql = implode(' AND ', $where);

    $lines = Database::fetchAll(
        'SELECT line_code,COALESCE(NULLIF(MAX(line_title),""),line_code) line_title,SUM(sales_amount) sales_amount,SUM(qty) qty,SUM(target_qty) target_qty,SUM(target_amount) target_amount FROM ceo_dashboard_lines WHERE ' . $whereSql . ' GROUP BY line_code ORDER BY MIN(sort_order),line_code LIMIT 200',
        $params
    );
    $visitors = Database::fetchAll(
        'SELECT line_code,visitor_name,SUM(sales_amount) sales_amount,SUM(qty) qty,SUM(target_qty) target_qty,SUM(target_amount) target_amount FROM ceo_dashboard_visitors WHERE ' . $whereSql . ' GROUP BY line_code,visitor_name ORDER BY SUM(sales_amount) DESC,visitor_name LIMIT 200',
        $params
    );
    $grossSales = array_sum(array_map(static fn(array $row): int => (int)$row['sales_amount'], $lines));
    $quantity = array_sum(array_map(static fn(array $row): int => (int)$row['qty'], $lines));
    $targetQuantity = array_sum(array_map(static fn(array $row): int => (int)$row['target_qty'], $lines));
    foreach ($lines as &$line) $line['achievement_percent'] = (int)$line['target_qty'] > 0 ? round((int)$line['qty'] / (int)$line['target_qty'] * 100, 2) : 0;
    unset($line);
    foreach ($visitors as &$visitor) $visitor['achievement_percent'] = (int)$visitor['target_qty'] > 0 ? round((int)$visitor['qty'] / (int)$visitor['target_qty'] * 100, 2) : 0;
    unset($visitor);

    $charts = [
        'sales_by_line' => [
            'type' => 'bar',
            'labels' => array_values(array_map(static fn(array $row): string => (string)$row['line_title'], $lines)),
            'datasets' => [
                ['key' => 'sales_amount', 'label' => 'فروش', 'values' => array_values(array_map(static fn(array $row): int => (int)$row['sales_amount'], $lines))],
                ['key' => 'target_amount', 'label' => 'هدف فروش', 'values' => array_values(array_map(static fn(array $row): int => (int)$row['target_amount'], $lines))],
            ],
        ],
        'achievement_by_line' => [
            'type' => 'bar',
            'labels' => array_values(array_map(static fn(array $row): string => (string)$row['line_title'], $lines)),
            'datasets' => [['key' => 'achievement_percent', 'label' => 'درصد تحقق', 'values' => array_values(array_map(static fn(array $row): float => (float)$row['achievement_percent'], $lines))]],
        ],
    ];

    return ['data' => [
        'summary' => ['gross_sales' => $grossSales, 'quantity' => $quantity, 'target_quantity' => $targetQuantity, 'achievement_percent' => $targetQuantity > 0 ? round($quantity / $targetQuantity * 100, 2) : 0],
        'lines' => $lines,
        'visitors' => $visitors,
        'charts' => $charts,
    ], 'meta' => ['filters' => ['report_date' => $reportDate, 'line_code' => $lineCode], 'limits' => ['lines' => 200, 'visitors' => 200]]];
}, 'ui-dashboard-ceo');
