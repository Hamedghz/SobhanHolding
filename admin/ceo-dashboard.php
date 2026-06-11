<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Response.php';

Auth::requirePermission('ceo_dashboard', 'view');

$labels = [
    'page_title' => setting('page_title', 'داشبورد مدیرعامل'),
    'gross_sales' => setting('gross_sales_title', 'فروش ناخالص'),
    'discounts' => setting('discounts_title', 'تخفیفات'),
    'discount_percent' => setting('discount_percent_title', 'درصد'),
    'net_sales' => setting('net_sales_title', 'فروش خالص'),
    'line_sales_chart' => setting('line_sales_chart_title', 'ریال فروش لاین'),
    'line_table' => setting('line_table_title', 'اطلاعات لاین'),
    'visitor_table' => setting('visitor_table_title', 'اطلاعات ویزیتورها'),
    'line_share_chart' => setting('line_share_chart_title', 'سهم فروش هر لاین'),
    'line_achievement_chart' => setting('line_achievement_chart_title', 'درصد تحقق لاین'),
    'visitor_achievement_chart' => setting('visitor_achievement_chart_title', 'درصد تحقق ویزیتور'),
];
$pageTitle = $labels['page_title'];

$latestDateRow = Database::fetch('SELECT MAX(report_date) latest_date FROM ceo_dashboard_lines WHERE active = 1 AND report_date IS NOT NULL');
$filters = [
    'report_date' => trim($_GET['report_date'] ?? ($latestDateRow['latest_date'] ?? '')),
    'line_code' => trim($_GET['line_code'] ?? ''),
];
$dateOptions = array_column(Database::fetchAll('SELECT DISTINCT report_date FROM ceo_dashboard_lines WHERE report_date IS NOT NULL ORDER BY report_date DESC'), 'report_date');
$lineOptions = array_column(Database::fetchAll('SELECT DISTINCT line_code FROM ceo_dashboard_lines WHERE line_code <> "" ORDER BY line_code ASC'), 'line_code');

$where = ['active = 1'];
$params = [];
if ($filters['report_date'] !== '') {
    $where[] = 'report_date = ?';
    $params[] = $filters['report_date'];
}
if ($filters['line_code'] !== '') {
    $where[] = 'line_code = ?';
    $params[] = $filters['line_code'];
}
$whereSql = implode(' AND ', $where);

$lineRows = Database::fetchAll(
    "SELECT line_code, COALESCE(NULLIF(line_title, ''), line_code) line_title, SUM(sales_amount) sales_amount, SUM(qty) qty, SUM(target_qty) target_qty
     FROM ceo_dashboard_lines
     WHERE {$whereSql}
     GROUP BY line_code, line_title
     ORDER BY MIN(sort_order) ASC, line_code ASC",
    $params
);

$visitorRows = Database::fetchAll(
    "SELECT line_code, visitor_name, SUM(target_qty) target_qty, SUM(qty) qty
     FROM ceo_dashboard_visitors
     WHERE {$whereSql}
     GROUP BY line_code, visitor_name
     ORDER BY MIN(sort_order) ASC, line_code ASC, visitor_name ASC",
    $params
);

foreach ($lineRows as &$row) {
    $row['achievement_percent'] = (int)$row['target_qty'] > 0 ? ((float)$row['qty'] / (float)$row['target_qty']) * 100 : 0;
}
unset($row);
foreach ($visitorRows as &$row) {
    $row['achievement_percent'] = (int)$row['target_qty'] > 0 ? ((float)$row['qty'] / (float)$row['target_qty']) * 100 : 0;
}
unset($row);

$grossSales = array_sum(array_map(static fn($row) => (int)$row['sales_amount'], $lineRows));
$discounts = max(0, (int)setting('ceo_dashboard_discounts_amount', '0'));
$netSales = max(0, $grossSales - $discounts);
$discountPercent = $grossSales > 0 ? ($discounts / $grossSales) * 100 : 0;
$totalQty = array_sum(array_map(static fn($row) => (int)$row['qty'], $lineRows));
$totalTarget = array_sum(array_map(static fn($row) => (int)$row['target_qty'], $lineRows));
$totalAchievement = $totalTarget > 0 ? ($totalQty / $totalTarget) * 100 : 0;
$hasData = (bool)$lineRows || (bool)$visitorRows;

function ceo_status_class(float $percent): string
{
    if ($percent >= 100) return 'achievement-good';
    if ($percent >= 80) return 'achievement-warn';
    return 'achievement-bad';
}

$chartData = [
    'moneyLabels' => [$labels['net_sales'], $labels['discounts']],
    'moneyValues' => [$netSales, $discounts],
    'lineLabels' => array_map(static fn($row) => $row['line_title'] ?: $row['line_code'], $lineRows),
    'lineSales' => array_map(static fn($row) => (int)$row['sales_amount'], $lineRows),
    'lineAchievement' => array_map(static fn($row) => round((float)$row['achievement_percent']), $lineRows),
    'visitorLabels' => array_map(static fn($row) => $row['visitor_name'], $visitorRows),
    'visitorAchievement' => array_map(static fn($row) => round((float)$row['achievement_percent']), $visitorRows),
];

require __DIR__ . '/../views/partials/admin-header.php';
?>
<form class="card admin-form ceo-filter" method="get">
    <div class="grid grid-3">
        <label class="form-field">
            <span>تاریخ گزارش</span>
            <select name="report_date">
                <option value="">همه</option>
                <?php foreach ($dateOptions as $date): ?><option value="<?= e($date) ?>" <?= $filters['report_date'] === $date ? 'selected' : '' ?>><?= e($date) ?></option><?php endforeach; ?>
            </select>
        </label>
        <label class="form-field">
            <span>لاین</span>
            <select name="line_code">
                <option value="">همه</option>
                <?php foreach ($lineOptions as $lineCode): ?><option value="<?= e($lineCode) ?>" <?= $filters['line_code'] === $lineCode ? 'selected' : '' ?>><?= e($lineCode) ?></option><?php endforeach; ?>
            </select>
        </label>
        <div class="form-actions ceo-filter-actions">
            <button class="btn btn-primary">اعمال فیلتر</button>
            <a class="btn" href="/admin/ceo-dashboard.php">پاکسازی</a>
            <?php if (Auth::can('ceo_dashboard', 'edit')): ?><a class="btn" href="/admin/ceo-dashboard-lines.php">اطلاعات لاین</a><?php endif; ?>
            <?php if (Auth::can('ceo_dashboard', 'edit')): ?><a class="btn" href="/admin/ceo-dashboard-visitors.php">اطلاعات ویزیتور</a><?php endif; ?>
        </div>
    </div>
</form>

<?php if (!$hasData): ?>
    <div class="card ceo-empty">
        <h2>هنوز اطلاعاتی برای داشبورد مدیرعامل ثبت نشده است.</h2>
        <div class="form-actions">
            <?php if (Auth::can('ceo_dashboard', 'create')): ?><a class="btn btn-primary" href="/admin/ceo-dashboard-lines.php">افزودن اطلاعات لاین</a><?php endif; ?>
            <?php if (Auth::can('ceo_dashboard', 'create')): ?><a class="btn" href="/admin/ceo-dashboard-visitors.php">افزودن اطلاعات ویزیتور</a><?php endif; ?>
        </div>
    </div>
<?php else: ?>
    <div class="ceo-dashboard-grid">
        <section class="card ceo-kpi-card">
            <h2><?= e($labels['page_title']) ?></h2>
            <div class="ceo-kpi-list">
                <div><span><?= e($labels['gross_sales']) ?></span><strong><?= e(format_money($grossSales)) ?></strong></div>
                <div><span><?= e($labels['discounts']) ?></span><strong><?= e(format_money($discounts)) ?></strong></div>
                <div><span><?= e($labels['discount_percent']) ?></span><strong><?= e(format_percent($discountPercent, 1)) ?></strong></div>
                <div><span><?= e($labels['net_sales']) ?></span><strong><?= e(format_money($netSales)) ?></strong></div>
            </div>
        </section>

        <section class="card ceo-chart-card">
            <h2><?= e($labels['net_sales']) ?> / <?= e($labels['discounts']) ?></h2>
            <canvas id="ceoMoneyDonut"></canvas>
        </section>

        <section class="card ceo-chart-card">
            <h2><?= e($labels['line_sales_chart']) ?></h2>
            <canvas id="ceoLineSales"></canvas>
        </section>
    </div>

    <div class="ceo-dashboard-grid ceo-dashboard-grid-2">
        <section class="card">
            <h2><?= e($labels['line_table']) ?></h2>
            <div class="table-wrap ceo-table-wrap">
                <table>
                    <thead><tr><th>لاین</th><th>فروش لاین</th><th>قطعه</th><th>تارگت</th><th>درصد تحقق</th></tr></thead>
                    <tbody>
                    <?php foreach ($lineRows as $row): ?>
                        <tr>
                            <td><?= e($row['line_title'] ?: $row['line_code']) ?></td>
                            <td><?= e(format_money($row['sales_amount'])) ?></td>
                            <td><?= e(format_number($row['qty'])) ?></td>
                            <td><?= e(format_number($row['target_qty'])) ?></td>
                            <td><span class="achievement-pill <?= e(ceo_status_class((float)$row['achievement_percent'])) ?>"><?= e(format_percent(round((float)$row['achievement_percent']))) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="ceo-total-row">
                        <td>فروش کل لاین‌ها</td>
                        <td><?= e(format_money($grossSales)) ?></td>
                        <td><?= e(format_number($totalQty)) ?></td>
                        <td><?= e(format_number($totalTarget)) ?></td>
                        <td><span class="achievement-pill <?= e(ceo_status_class($totalAchievement)) ?>"><?= e(format_percent(round($totalAchievement))) ?></span></td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="card ceo-chart-card">
            <h2><?= e($labels['line_achievement_chart']) ?></h2>
            <canvas id="ceoLineAchievement"></canvas>
        </section>
    </div>

    <section class="card">
        <h2><?= e($labels['visitor_table']) ?></h2>
        <div class="table-wrap ceo-table-wrap">
            <table>
                <thead><tr><th>لاین</th><th>ویزیتور</th><th>تارگت</th><th>فروش / قطعه</th><th>درصد تحقق</th></tr></thead>
                <tbody>
                <?php foreach ($visitorRows as $row): ?>
                    <tr>
                        <td><?= e($row['line_code']) ?></td>
                        <td><?= e($row['visitor_name']) ?></td>
                        <td><?= e(format_number($row['target_qty'])) ?></td>
                        <td><?= e(format_number($row['qty'])) ?></td>
                        <td><span class="achievement-pill <?= e(ceo_status_class((float)$row['achievement_percent'])) ?>"><?= e(format_percent(round((float)$row['achievement_percent']))) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="card ceo-chart-card ceo-wide-chart">
        <h2><?= e($labels['visitor_achievement_chart']) ?></h2>
        <canvas id="ceoVisitorAchievement"></canvas>
    </section>
<?php endif; ?>

<?php if ($hasData): ?>
<script src="/assets/js/chart.umd.min.js"></script>
<script>
const ceoChartData = <?= json_encode($chartData, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK) ?>;
const percentTick = value => value + '%';
const chartFont = {family: 'Tahoma, Arial, sans-serif'};
Chart.defaults.font.family = chartFont.family;
Chart.defaults.color = '#475569';

new Chart(document.getElementById('ceoMoneyDonut'), {
    type: 'doughnut',
    data: {labels: ceoChartData.moneyLabels, datasets: [{data: ceoChartData.moneyValues, backgroundColor: ['#16a34a', '#ef4444'], borderWidth: 0}]},
    options: {responsive: true, maintainAspectRatio: false, plugins: {legend: {position: 'bottom'}}}
});

new Chart(document.getElementById('ceoLineSales'), {
    type: 'bar',
    data: {labels: ceoChartData.lineLabels, datasets: [{label: '<?= e($labels['line_sales_chart']) ?>', data: ceoChartData.lineSales, backgroundColor: '#2563eb', borderRadius: 6}]},
    options: {responsive: true, maintainAspectRatio: false, plugins: {legend: {display: false}}, scales: {y: {beginAtZero: true}}}
});

new Chart(document.getElementById('ceoLineAchievement'), {
    type: 'doughnut',
    data: {labels: ceoChartData.lineLabels, datasets: [{label: '<?= e($labels['line_share_chart']) ?>', data: ceoChartData.lineAchievement, backgroundColor: ['#2563eb', '#16a34a', '#f59e0b', '#ef4444', '#14b8a6', '#8b5cf6'], borderWidth: 0}]},
    options: {responsive: true, maintainAspectRatio: false, plugins: {legend: {position: 'bottom'}, tooltip: {callbacks: {label: ctx => ctx.label + ': ' + ctx.parsed + '%'}}}}
});

new Chart(document.getElementById('ceoVisitorAchievement'), {
    type: 'line',
    data: {labels: ceoChartData.visitorLabels, datasets: [{label: '<?= e($labels['visitor_achievement_chart']) ?>', data: ceoChartData.visitorAchievement, borderColor: '#2563eb', backgroundColor: 'rgba(37,99,235,.12)', tension: .35, fill: true, pointRadius: 4, pointHoverRadius: 6}]},
    options: {responsive: true, maintainAspectRatio: false, plugins: {legend: {display: false}, tooltip: {callbacks: {label: ctx => ctx.parsed.y + '%'}}}, scales: {y: {beginAtZero: true, ticks: {callback: percentTick}}}}
});
</script>
<?php endif; ?>
</section></main></div><script src="/assets/js/app.js"></script></body></html>
