<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/JalaliDate.php';
require_once __DIR__ . '/../core/SobhanApiClient.php';
require_once __DIR__ . '/../core/CeoDashboardManualMetrics.php';

Auth::requireLogin();
if (!Auth::can('view_ceo_dashboard') && !Auth::can('ceo_dashboard')) {
    http_response_code(403);
    echo 'دسترسی غیرمجاز';
    exit;
}

$labels = [
    'page_title' => setting('ceo_dashboard_page_title', 'داشبورد مدیرعامل'),
    'gross_sales' => setting('ceo_dashboard_gross_sales_title', 'فروش ناخالص'),
    'discounts' => setting('ceo_dashboard_discounts_title', 'تخفیفات'),
    'discount_percent' => setting('ceo_dashboard_discount_percent_title', 'درصد'),
    'net_sales' => setting('ceo_dashboard_net_sales_title', 'فروش خالص'),
    'line_sales_chart' => setting('ceo_dashboard_line_sales_chart_title', 'ریال فروش لاین'),
    'line_table' => setting('ceo_dashboard_line_table_title', 'اطلاعات لاین'),
    'visitor_table' => setting('ceo_dashboard_visitor_table_title', 'اطلاعات ویزیتورها'),
    'line_share_chart' => setting('ceo_dashboard_line_share_chart_title', 'سهم فروش هر لاین'),
    'line_achievement_chart' => setting('ceo_dashboard_line_achievement_chart_title', 'درصد تحقق لاین'),
    'visitor_achievement_chart' => setting('ceo_dashboard_visitor_achievement_chart_title', 'درصد تحقق ویزیتور'),
];
$showCharts = setting('ceo_dashboard_show_charts', '1') === '1';
$showLineTable = setting('ceo_dashboard_show_line_table', '1') === '1';
$showVisitorTable = setting('ceo_dashboard_show_visitor_table', '1') === '1';
$pageTitle = $labels['page_title'];
$aiDashboardEnabled=setting('sobhan_api_enabled','0')==='1'&&setting('sobhan_ai_autofill_enabled','0')==='1';$dashboardCache=Database::fetch('SELECT source,updated_at FROM dashboard_data_cache WHERE dashboard_key="ceo_dashboard" AND scope_key="all" LIMIT 1');$dashboardSource=$aiDashboardEnabled?($dashboardCache['source']??'Windows Server API - در انتظار بروزرسانی'):'دستی / دیتابیس';
$showAiChat=setting('ceo_dashboard_show_ai_chat','1')==='1';

$latestDateRow = Database::fetch(
    'SELECT MAX(report_date) latest_date FROM (
        SELECT report_date FROM ceo_dashboard_lines WHERE active = 1 AND report_date IS NOT NULL
        UNION ALL
        SELECT report_date FROM ceo_dashboard_visitors WHERE active = 1 AND report_date IS NOT NULL
        UNION ALL
        SELECT period_key report_date FROM ceo_dashboard_manual_metrics WHERE period_key <> ""
    ) dates'
);
$requestedReportDate = trim($_GET['report_date'] ?? '');
$hasExplicitReportDate = $requestedReportDate !== '';
$filters = [
    'report_date' => $hasExplicitReportDate ? $requestedReportDate : ($latestDateRow['latest_date'] ?? ''),
    'line_code' => trim($_GET['line_code'] ?? ''),
    'pharmacy_id' => (int)($_GET['pharmacy_id'] ?? 0),
];
if ($filters['report_date'] !== '') {
    $filters['report_date'] = JalaliDate::toGregorian($filters['report_date']) ?: $filters['report_date'];
}
$dateOptions = array_column(Database::fetchAll(
    'SELECT DISTINCT report_date
     FROM (
        SELECT report_date FROM ceo_dashboard_lines WHERE report_date IS NOT NULL
        UNION ALL
        SELECT report_date FROM ceo_dashboard_visitors WHERE report_date IS NOT NULL
        UNION ALL
        SELECT period_key report_date FROM ceo_dashboard_manual_metrics WHERE period_key <> ""
        UNION ALL
        SELECT m.report_date
        FROM pharmacy_dashboard_metrics m
        JOIN pharmacies p ON p.id = m.pharmacy_id
        WHERE m.active = 1 AND p.active = 1 AND m.report_date IS NOT NULL
     ) dates
     ORDER BY report_date DESC'
), 'report_date');
$lineOptions = array_column(Database::fetchAll('SELECT DISTINCT line_code FROM (SELECT line_code FROM ceo_dashboard_lines WHERE line_code <> "" UNION ALL SELECT line_code FROM ceo_dashboard_visitors WHERE line_code <> "") line_sources ORDER BY line_code ASC'), 'line_code');
$pharmacyOptions = Database::fetchAll('SELECT id,title FROM pharmacies WHERE active = 1 ORDER BY sort_order ASC, id ASC');

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
$lineWhereSql = str_replace(['active', 'report_date', 'line_code'], ['l.active', 'l.report_date', 'l.line_code'], $whereSql);
$visitorWhereSql = str_replace(['active', 'report_date', 'line_code'], ['v.active', 'v.report_date', 'v.line_code'], $whereSql);

$lineRows = Database::fetchAll(
    "SELECT MIN(l.id) line_id,
            l.line_code,
            COALESCE(NULLIF(l.line_title, ''), l.line_code) line_title,
            SUM(l.sales_amount) sales_amount,
            SUM(l.qty) qty,
            SUM(l.target_qty) target_qty,
            SUM(l.target_amount) target_amount,
            MAX(l.supervisor_user_id) supervisor_user_id,
            MAX(l.sales_manager_user_id) sales_manager_user_id,
            COALESCE(NULLIF(MAX(su.name), ''), NULLIF(MAX(l.supervisor_name), ''), 'نامشخص') supervisor_name,
            COALESCE(NULLIF(MAX(mu.name), ''), NULLIF(MAX(l.sales_manager_name), ''), 'نامشخص') sales_manager_name
     FROM ceo_dashboard_lines l
     LEFT JOIN users su ON su.id = l.supervisor_user_id
     LEFT JOIN users mu ON mu.id = l.sales_manager_user_id
     WHERE {$lineWhereSql}
     GROUP BY l.line_code,
              l.line_title,
              CASE
                  WHEN COALESCE(l.supervisor_user_id, 0) > 0 THEN CONCAT('user:', l.supervisor_user_id)
                  WHEN NULLIF(l.supervisor_name, '') IS NOT NULL THEN CONCAT('name:', l.supervisor_name)
                  ELSE 'name:نامشخص'
              END,
              CASE
                  WHEN COALESCE(l.sales_manager_user_id, 0) > 0 THEN CONCAT('user:', l.sales_manager_user_id)
                  WHEN NULLIF(l.sales_manager_name, '') IS NOT NULL THEN CONCAT('name:', l.sales_manager_name)
                  ELSE 'name:نامشخص'
              END
     ORDER BY MIN(l.sort_order) ASC, l.line_code ASC",
    $params
);

$visitorRows = Database::fetchAll(
    "SELECT v.line_code,
            v.visitor_name,
            MAX(u.name) user_name,
            SUM(v.target_qty) target_qty,
            SUM(v.qty) qty,
            SUM(v.target_amount) target_amount,
            SUM(v.sales_amount) sales_amount
     FROM ceo_dashboard_visitors v
     LEFT JOIN users u ON u.id = v.user_id
     WHERE {$visitorWhereSql}
     GROUP BY v.line_code, v.visitor_name
     ORDER BY MIN(v.sort_order) ASC, v.line_code ASC, v.visitor_name ASC",
    $params
);

foreach ($lineRows as &$row) {
    $row['sales_value'] = (int)$row['sales_amount'];
    $row['qty_value'] = (int)$row['qty'];
    $row['target_value'] = (int)$row['target_qty'];
    $row['achievement_percent'] = (int)$row['target_qty'] > 0 ? ((float)$row['qty'] / (float)$row['target_qty']) * 100 : 0;
}
unset($row);
foreach ($visitorRows as &$row) {
    $row['sales_value'] = (int)$row['sales_amount'];
    $row['qty_value'] = (int)$row['qty'];
    $row['target_value'] = (int)$row['target_qty'];
    $row['achievement_percent'] = (int)$row['target_qty'] > 0 ? ((float)$row['qty'] / (float)$row['target_qty']) * 100 : 0;
}
unset($row);

$grossSales = array_sum(array_map(static fn($row) => (int)$row['sales_amount'], $lineRows));
$discounts = max(0, (int)setting('ceo_dashboard_discounts_amount', '0'));
$netSales = max(0, $grossSales - $discounts);
$discountPercent = $grossSales > 0 ? ($discounts / $grossSales) * 100 : 0;
$totalQty = array_sum(array_map(static fn($row) => (int)$row['qty'], $lineRows));
$totalTarget = array_sum(array_map(static fn($row) => (int)$row['target_qty'], $lineRows));
$totalTargetValue = $totalTarget;
$totalAchievement = $totalTarget > 0 ? ($totalQty / $totalTarget) * 100 : 0;
$hasData = (bool)$lineRows || (bool)$visitorRows;

$latestPharmacyDate = Database::fetch(
    'SELECT MAX(m.report_date) latest_date
     FROM pharmacy_dashboard_metrics m
     JOIN pharmacies p ON p.id = m.pharmacy_id
     WHERE m.active = 1 AND p.active = 1 AND m.report_date IS NOT NULL'
);
$pharmacyMetricDate = $hasExplicitReportDate ? $filters['report_date'] : ($latestPharmacyDate['latest_date'] ?? null);
$pharmacyWhere = $filters['pharmacy_id'] > 0 ? ' AND p.id = ?' : '';
$pharmacyParams = [$pharmacyMetricDate];
if ($filters['pharmacy_id'] > 0) $pharmacyParams[] = $filters['pharmacy_id'];
$pharmacyRows = Database::fetchAll(
    'SELECT p.id, p.title,
            COALESCE(SUM(m.daily_sales), 0) daily_sales,
            COALESCE(SUM(m.monthly_sales), 0) monthly_sales,
            COALESCE(SUM(m.supplier_purchase_amount), 0) supplier_purchase_amount,
            COALESCE(SUM(m.open_invoice_amount), 0) open_invoice_amount,
            COALESCE(SUM(m.expenses_amount), 0) expenses_amount,
            COALESCE(SUM(m.pending_checks_amount), 0) pending_checks_amount
     FROM pharmacies p
     LEFT JOIN pharmacy_dashboard_metrics m ON m.pharmacy_id = p.id AND m.active = 1 AND (m.report_date <=> ?)
     WHERE p.active = 1' . $pharmacyWhere . '
     GROUP BY p.id, p.title, p.sort_order
     ORDER BY p.sort_order ASC, p.id ASC',
    $pharmacyParams
);
$pharmacyActiveParams = [$pharmacyMetricDate];
if ($filters['pharmacy_id'] > 0) $pharmacyActiveParams[] = $filters['pharmacy_id'];
$pharmacyActiveCount = Database::fetch(
    'SELECT COUNT(*) active_count
     FROM pharmacy_dashboard_metrics m
     JOIN pharmacies p ON p.id = m.pharmacy_id
     WHERE m.active = 1 AND p.active = 1 AND (m.report_date <=> ?)' . $pharmacyWhere,
    $pharmacyActiveParams
);
$pharmacyTotals = [
    'title' => 'Total',
    'daily_sales' => array_sum(array_map(static fn($row) => (int)$row['daily_sales'], $pharmacyRows)),
    'monthly_sales' => array_sum(array_map(static fn($row) => (int)$row['monthly_sales'], $pharmacyRows)),
    'supplier_purchase_amount' => array_sum(array_map(static fn($row) => (int)$row['supplier_purchase_amount'], $pharmacyRows)),
    'open_invoice_amount' => array_sum(array_map(static fn($row) => (int)$row['open_invoice_amount'], $pharmacyRows)),
    'expenses_amount' => array_sum(array_map(static fn($row) => (int)$row['expenses_amount'], $pharmacyRows)),
    'pending_checks_amount' => array_sum(array_map(static fn($row) => (int)$row['pending_checks_amount'], $pharmacyRows)),
];
$pharmacyHasData = $pharmacyMetricDate !== null && (int)($pharmacyActiveCount['active_count'] ?? 0) > 0;

function ceo_status_class(float $percent): string
{
    if ($percent > 100) return 'achievement-good';
    if ($percent >= 90) return 'achievement-mid';
    if ($percent >= 70) return 'achievement-warn';
    return 'achievement-bad';
}

function ceo_progress_class(float $percent): string
{
    if ($percent < 70) return 'progress-bad';
    if ($percent < 90) return 'progress-warn';
    if ($percent <= 100) return 'progress-mid';
    return 'progress-good';
}

function ceo_person_group_key(array $lineRow, string $prefix): string
{
    $userId = (int)($lineRow[$prefix . '_user_id'] ?? 0);
    if ($userId > 0) return 'user:' . $userId;

    $name = trim((string)($lineRow[$prefix . '_name'] ?? ''));
    if ($name === '' || $name === '-') $name = 'نامشخص';
    return 'name:' . $name;
}

function ceo_line_summary(array $lineRow): array
{
    return [
        'code' => (string)($lineRow['line_code'] ?? ''),
        'title' => (string)($lineRow['line_title'] ?: ($lineRow['line_code'] ?? '')),
        'sales_value' => (int)($lineRow['sales_value'] ?? 0),
        'qty_value' => (int)($lineRow['qty_value'] ?? 0),
        'target_value' => (int)($lineRow['target_value'] ?? 0),
        'achievement_percent' => (float)($lineRow['achievement_percent'] ?? 0),
    ];
}

function ceo_add_line_to_person_group(array &$groups, string $key, string $name, array $lineRow): void
{
    if ($name === '' || $name === '-') $name = 'نامشخص';
    if (!isset($groups[$key])) {
        $groups[$key] = [
            'name' => $name,
            'lines' => [],
            'sales_value' => 0,
            'qty_value' => 0,
            'target_value' => 0,
            'achievement_percent' => 0,
        ];
    }

    $groups[$key]['lines'][] = ceo_line_summary($lineRow);
    $groups[$key]['sales_value'] += (int)$lineRow['sales_value'];
    $groups[$key]['qty_value'] += (int)$lineRow['qty_value'];
    $groups[$key]['target_value'] += (int)$lineRow['target_value'];
}

function sobhan_payload_data(array $result): mixed
{
    $data = $result['data'] ?? [];
    if (is_array($data) && array_key_exists('data', $data)) return $data['data'];
    if (is_array($data) && array_key_exists('result', $data)) return $data['result'];
    return $data;
}

function sobhan_list_from_result(array $result): array
{
    $data = sobhan_payload_data($result);
    if (!is_array($data)) return [];
    foreach (['items', 'rows', 'data', 'results'] as $key) {
        if (isset($data[$key]) && is_array($data[$key])) return array_values($data[$key]);
    }
    $isList = $data === [] || array_keys($data) === range(0, count($data) - 1);
    return $isList ? $data : [];
}

function sobhan_number(array $source, array $keys, int $fallback = 0): int
{
    foreach ($keys as $key) {
        if (isset($source[$key]) && is_numeric($source[$key])) return (int)$source[$key];
    }
    return $fallback;
}

function sobhan_text(array $source, array $keys, string $fallback = ''): string
{
    foreach ($keys as $key) {
        if (isset($source[$key]) && trim((string)$source[$key]) !== '') return (string)$source[$key];
    }
    return $fallback;
}

$lineTotalRow = [
    'line_code' => 'Total',
    'line_title' => 'Total',
    'sales_amount' => $grossSales,
    'qty' => $totalQty,
    'target_qty' => $totalTarget,
    'target_amount' => array_sum(array_map(static fn($row) => (int)($row['target_amount'] ?? 0), $lineRows)),
    'target_value' => $totalTargetValue,
    'sales_value' => $grossSales,
    'qty_value' => $totalQty,
    'supervisor_name' => '-',
    'sales_manager_name' => '-',
    'achievement_percent' => $totalAchievement,
];
$lineProgressRows = array_merge($lineRows, [$lineTotalRow]);
$topVisitors = array_values(array_filter($visitorRows, static fn($row) => (int)$row['sales_value'] > 0));
usort($topVisitors, static fn($a, $b) => (int)$b['sales_value'] <=> (int)$a['sales_value']);
$topVisitors = array_slice($topVisitors, 0, 5);
$topProducts = $lineRows;
usort($topProducts, static fn($a, $b) => (int)$b['sales_amount'] <=> (int)$a['sales_amount']);
$topProducts = array_slice($topProducts, 0, 5);
$pharmacyRowsWithTotal = array_merge($pharmacyRows, [$pharmacyTotals]);
$salesManagerRows = [];
$supervisorRows = [];
foreach ($lineRows as $lineRow) {
    ceo_add_line_to_person_group(
        $salesManagerRows,
        ceo_person_group_key($lineRow, 'sales_manager'),
        trim((string)$lineRow['sales_manager_name']),
        $lineRow
    );
    ceo_add_line_to_person_group(
        $supervisorRows,
        ceo_person_group_key($lineRow, 'supervisor'),
        trim((string)$lineRow['supervisor_name']),
        $lineRow
    );
}
foreach ($salesManagerRows as &$row) {
    $row['achievement_percent'] = $row['target_value'] > 0 ? ($row['qty_value'] / $row['target_value']) * 100 : 0;
}
unset($row);
foreach ($supervisorRows as &$row) {
    $row['achievement_percent'] = $row['target_value'] > 0 ? ($row['qty_value'] / $row['target_value']) * 100 : 0;
}
unset($row);
$salesManagerRows = array_values($salesManagerRows);
$supervisorRows = array_values($supervisorRows);

$chartData = [
    'moneyLabels' => [$labels['net_sales'], $labels['discounts']],
    'moneyValues' => [$netSales, $discounts],
    'lineLabels' => array_map(static fn($row) => $row['line_title'] ?: $row['line_code'], $lineRows),
    'lineSales' => array_map(static fn($row) => (int)$row['sales_amount'], $lineRows),
    'lineAchievement' => array_map(static fn($row) => round((float)$row['achievement_percent'], 2), $lineRows),
    'visitorLabels' => array_map(static fn($row) => $row['visitor_name'], $visitorRows),
    'visitorAchievement' => array_map(static fn($row) => round((float)$row['achievement_percent'], 2), $visitorRows),
    'visitorSales' => array_map(static fn($row) => (int)$row['sales_value'], $visitorRows),
    'visitorTargets' => array_map(static fn($row) => (int)$row['target_value'], $visitorRows),
    'pharmacyLabels' => array_map(static fn($row) => $row['title'], $pharmacyRows),
    'pharmacyLabelsTotal' => array_merge(array_map(static fn($row) => $row['title'], $pharmacyRows), ['Total']),
    'pharmacyDailySales' => array_merge(array_map(static fn($row) => (int)$row['daily_sales'], $pharmacyRows), [(int)$pharmacyTotals['daily_sales']]),
    'pharmacyMonthlySales' => array_merge(array_map(static fn($row) => (int)$row['monthly_sales'], $pharmacyRows), [(int)$pharmacyTotals['monthly_sales']]),
    'pharmacySupplierPurchase' => array_map(static fn($row) => (int)$row['supplier_purchase_amount'], $pharmacyRows),
    'pharmacyOpenInvoice' => array_merge(array_map(static fn($row) => (int)$row['open_invoice_amount'], $pharmacyRows), [(int)$pharmacyTotals['open_invoice_amount']]),
    'pharmacyExpenses' => array_merge(array_map(static fn($row) => (int)$row['expenses_amount'], $pharmacyRows), [(int)$pharmacyTotals['expenses_amount']]),
    'pharmacyChecks' => array_map(static fn($row) => (int)$row['pending_checks_amount'], $pharmacyRows),
];

$distributionDataMode = setting('sobhan_distribution_data_mode', 'import_file') === 'ai_api' ? 'ai_api' : 'import_file';
$aiAutofillEnabled = setting('sobhan_ai_autofill_enabled', '0') === '1';
$aiOverwriteManual = setting('sobhan_ai_overwrite_manual_data', '0') === '1';
$distributionSourceBadge = $distributionDataMode === 'ai_api' ? 'منبع داده: API/هوش مصنوعی سبحان' : 'منبع: فایل ایمپورت';
$sobhanClient = new SobhanApiClient();
$sobhanEnabled = $sobhanClient->isEnabled();
$useSobhanForDashboardValues = $distributionDataMode === 'ai_api' && $sobhanEnabled;
$sobhanDashboardResult = $useSobhanForDashboardValues ? $sobhanClient->get('/dashboard/ceo', ['range' => 'mtd']) : ['ok' => false, 'error' => ['message_fa' => $distributionDataMode === 'import_file' ? 'منبع داده روی فایل ایمپورت تنظیم شده است.' : 'سرویس گزارش‌گیری سبحان غیرفعال است.', 'technical' => $distributionDataMode === 'import_file' ? 'distribution mode import_file' : 'disabled'], 'status' => 0, 'data' => null];
$sobhanDailyResult = $useSobhanForDashboardValues ? $sobhanClient->get('/sales/daily') : $sobhanDashboardResult;
$sobhanVisitorsResult = $useSobhanForDashboardValues ? $sobhanClient->get('/sales/by-visitor') : $sobhanDashboardResult;
$sobhanProductsResult = $useSobhanForDashboardValues ? $sobhanClient->get('/sales/by-product') : $sobhanDashboardResult;
$sobhanDashboardData = is_array(sobhan_payload_data($sobhanDashboardResult)) ? sobhan_payload_data($sobhanDashboardResult) : [];
$sobhanApiOk = $sobhanDashboardResult['ok'] || $sobhanDailyResult['ok'] || $sobhanVisitorsResult['ok'] || $sobhanProductsResult['ok'];
$sobhanErrorMessage = $sobhanEnabled
    ? 'اتصال به سرویس گزارش‌گیری سبحان برقرار نشد. لطفاً تنظیمات API را بررسی کنید.'
    : 'سرویس گزارش‌گیری سبحان غیرفعال است.';
if ($distributionDataMode === 'import_file') {
    $sobhanErrorMessage = 'داشبورد شرکت پخش از فایل ایمپورت‌شده خوانده می‌شود و برای تکمیل مقادیر با API سبحان تماسی برقرار نمی‌شود.';
}
$sobhanMetrics = [
    'gross_sales' => sobhan_number($sobhanDashboardData, ['gross_sales', 'grossSales', 'total_gross_sales'], $grossSales),
    'discount_total' => sobhan_number($sobhanDashboardData, ['discount_total', 'discountTotal', 'discounts'], $discounts),
    'net_sales' => sobhan_number($sobhanDashboardData, ['net_sales', 'netSales', 'total_net_sales'], $netSales),
    'invoice_count' => sobhan_number($sobhanDashboardData, ['invoice_count', 'invoiceCount', 'invoices'], 0),
    'last_sync_at' => sobhan_text($sobhanDashboardData, ['last_sync_at', 'lastSyncAt', 'updated_at'], ''),
];
$dashboardUsesApiMetrics = $distributionDataMode === 'ai_api' && $sobhanDashboardResult['ok'];
$dashboardSourceBadge = $dashboardUsesApiMetrics ? 'منبع: API سبحان' : 'منبع: فایل ایمپورت';
$dashboardGrossSales = $dashboardUsesApiMetrics ? $sobhanMetrics['gross_sales'] : $grossSales;
$dashboardDiscounts = $dashboardUsesApiMetrics ? $sobhanMetrics['discount_total'] : $discounts;
$dashboardNetSales = $dashboardUsesApiMetrics ? $sobhanMetrics['net_sales'] : $netSales;
$dashboardInvoiceCount = $dashboardUsesApiMetrics ? $sobhanMetrics['invoice_count'] : 0;
$reportPeriodKey = CeoDashboardManualMetrics::normalizePeriodKey($filters['report_date']);
$manualSummaryMetrics = $reportPeriodKey !== '' ? CeoDashboardManualMetrics::get($reportPeriodKey) : null;
if ($manualSummaryMetrics) {
    $dashboardGrossSales = (float)$manualSummaryMetrics['gross_sales'];
    $dashboardDiscounts = (float)$manualSummaryMetrics['discounts'];
    $dashboardNetSales = (float)$manualSummaryMetrics['net_sales'];
    $hasData = true;
}
$summarySourceBadge = $manualSummaryMetrics ? 'ورودی دستی از اکسل' : 'محاسبه خودکار';
$dashboardDiscountPercent = $dashboardGrossSales > 0 ? ($dashboardDiscounts / $dashboardGrossSales) * 100 : 0;
$chartData['moneyValues'] = [$dashboardNetSales, $dashboardDiscounts];
$sobhanDailyRows = sobhan_list_from_result($sobhanDailyResult);
$sobhanVisitorRows = array_slice(sobhan_list_from_result($sobhanVisitorsResult), 0, 10);
$sobhanProductRows = array_slice(sobhan_list_from_result($sobhanProductsResult), 0, 10);
$chartData['sobhanDailyLabels'] = array_map(static fn($row) => sobhan_text((array)$row, ['date', 'day', 'sales_date', 'label'], ''), $sobhanDailyRows);
$chartData['sobhanDailySales'] = array_map(static fn($row) => sobhan_number((array)$row, ['net_sales', 'gross_sales', 'sales', 'amount'], 0), $sobhanDailyRows);

$aiDashboardAnswer = '';
$aiDashboardQuestion = '';
$aiDashboardKnowledgeSources = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['dashboard_action'] ?? '') === 'ai_ask') {
    if (!Auth::can('use_ai_assistant')) {
        flash('برای استفاده از دستیار هوش مصنوعی دسترسی ندارید.', 'danger');
        redirect('/admin/ceo-dashboard.php');
    }
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('درخواست نامعتبر است.', 'danger');
        redirect('/admin/ceo-dashboard.php');
    }
    $aiDashboardQuestion = trim((string)($_POST['question'] ?? ''));
    if ($aiDashboardQuestion === '' || (function_exists('mb_strlen') ? mb_strlen($aiDashboardQuestion, 'UTF-8') : strlen($aiDashboardQuestion)) > 1000) {
        flash('متن پرسش باید بین ۱ تا ۱۰۰۰ کاراکتر باشد.', 'danger');
        redirect('/admin/ceo-dashboard.php');
    }
    if (sobhan_is_visitor_list_question($aiDashboardQuestion)) {
        $visitorsResult = $sobhanClient->get('/sales/by-visitor');
        if ($visitorsResult['ok']) {
            $visitorRows = sobhan_rows_from_api_result($visitorsResult);
            if (sobhan_wants_three_visitors($aiDashboardQuestion)) {
                $visitorRows = array_slice($visitorRows, 0, 3);
            }
            $aiDashboardAnswer = sobhan_format_visitor_list($visitorRows, sobhan_wants_all_visitors($aiDashboardQuestion) || sobhan_wants_three_visitors($aiDashboardQuestion));
            if ($aiDashboardAnswer === '') {
                $aiDashboardAnswer = 'اطلاعاتی برای ویزیتورها یافت نشد.';
            }
        } else {
            $aiDashboardAnswer = $visitorsResult['error']['message_fa'] ?? 'دستیار هوش مصنوعی در حال حاضر در دسترس نیست.';
        }
    } else {
        $askResult = $sobhanClient->post('/ai/ask', ['question' => $aiDashboardQuestion]);
        $answerPayload = ai_answer_payload_from_result($askResult, $aiDashboardQuestion);
        $aiDashboardAnswer = $answerPayload['answer'];
        $aiDashboardKnowledgeSources = $answerPayload['knowledge_sources'];
    }
}

require __DIR__ . '/../views/partials/admin-header.php';
?>
<div class="dashboard-source-bar"><span>منبع بروزرسانی: <strong><?=e($dashboardSource)?></strong></span><span>آخرین بروزرسانی: <?=e($dashboardCache['updated_at']??'ثبت نشده')?></span><?php if(Auth::isAdmin()||Auth::can('ai_updates')):?><form data-dashboard-refresh><input type="hidden" name="csrf_token" value="<?=e(Auth::csrfToken())?>"><input type="hidden" name="dashboard_key" value="ceo_dashboard"><button class="btn btn-small">بروزرسانی داشبورد</button></form><?php endif?></div>
<section class="ceo-mobile-shell">
    <header class="ceo-mobile-page-head">
        <div>
            <h1><?= e($labels['page_title']) ?></h1>
            <p><?= e(format_jalali_date($filters['report_date'] ?: date('Y-m-d'))) ?></p>
        </div>
        <span><?= e($distributionSourceBadge) ?></span>
    </header>

    <form class="ceo-mobile-filterbar" method="get">
        <label><span>تاریخ</span><select name="report_date"><option value="">آخرین</option><?php foreach ($dateOptions as $date): ?><option value="<?= e($date) ?>" <?= $filters['report_date'] === $date ? 'selected' : '' ?>><?= e(format_jalali_date($date)) ?></option><?php endforeach; ?></select></label>
        <label><span>لاین</span><select name="line_code"><option value="">همه</option><?php foreach ($lineOptions as $lineCode): ?><option value="<?= e($lineCode) ?>" <?= $filters['line_code'] === $lineCode ? 'selected' : '' ?>><?= e($lineCode) ?></option><?php endforeach; ?></select></label>
        <label><span>داروخانه</span><select name="pharmacy_id"><option value="0">همه</option><?php foreach ($pharmacyOptions as $pharmacy): ?><option value="<?= e($pharmacy['id']) ?>" <?= $filters['pharmacy_id'] === (int)$pharmacy['id'] ? 'selected' : '' ?>><?= e($pharmacy['title']) ?></option><?php endforeach; ?></select></label>
        <button class="btn btn-primary">اعمال</button>
    </form>

    <section class="ceo-mobile-section ceo-section-distribution">
        <div class="ceo-section-title">
            <h2>داشبورد شرکت پخش سبحان</h2>
            <p>نمای خلاصه فروش، تخفیف، لاین‌ها و عملکرد ویزیتورها</p>
            <span class="badge"><?= e($summarySourceBadge) ?></span>
        </div>
        <?php if (!$hasData): ?>
            <div class="ceo-mobile-card"><p class="muted">هنوز اطلاعاتی برای شرکت پخش ثبت نشده است.</p></div>
        <?php else: ?>
            <div class="ceo-mobile-kpi-grid">
                <article class="ceo-mobile-kpi"><span>فروش ناخالص</span><strong title="<?= e(format_money($dashboardGrossSales)) ?>"><?= e(format_large_number($dashboardGrossSales)) ?></strong></article>
                <article class="ceo-mobile-kpi"><span>تخفیفات</span><strong title="<?= e(format_money($dashboardDiscounts)) ?>"><?= e(format_large_number($dashboardDiscounts)) ?></strong></article>
                <article class="ceo-mobile-kpi"><span>درصد تخفیف</span><strong><?= e(format_percent($dashboardDiscountPercent, 1)) ?></strong></article>
                <article class="ceo-mobile-kpi"><span>فروش خالص</span><strong title="<?= e(format_money($dashboardNetSales)) ?>"><?= e(format_large_number($dashboardNetSales)) ?></strong></article>
                <?php if ($dashboardUsesApiMetrics): ?><article class="ceo-mobile-kpi"><span>تعداد فاکتور</span><strong><?= e(format_number($dashboardInvoiceCount)) ?></strong></article><?php endif; ?>
            </div>

            <?php if ($showCharts): ?>
                <article class="ceo-mobile-card ceo-mobile-chart-card">
                    <h3>نمودار فروش لاین‌ها</h3>
                    <canvas id="mobileLineSalesChart"></canvas>
                </article>
            <?php endif; ?>

            <article class="ceo-mobile-card">
                <h3>تحقق تارگت لاین‌ها</h3>
                <div class="ceo-progress-list">
                    <?php foreach ($lineProgressRows as $row): $percent = min(100, max(0, (float)$row['achievement_percent'])); ?>
                        <div class="ceo-progress-item">
                            <div><strong><?= e($row['line_title'] ?: $row['line_code']) ?></strong><span><?= e(format_percent((float)$row['achievement_percent'], 2)) ?></span></div>
                            <div class="ceo-progress-meta">
                                <span>فروش<em title="<?= e(format_money($row['sales_value'])) ?>"><?= e(format_large_number($row['sales_value'])) ?></em></span>
                                <span>قطعه<em><?= e(format_number($row['qty_value'])) ?></em></span>
                                <span>تارگت<em><?= e(format_number($row['target_value'])) ?></em></span>
                            </div>
                            <b class="<?= e(ceo_progress_class((float)$row['achievement_percent'])) ?>" style="--progress: <?= e((string)$percent) ?>%"></b>
                        </div>
                    <?php endforeach; ?>
                </div>
            </article>

            <article class="ceo-mobile-card">
                <h3>مشخصات لاین‌ها</h3>
                <div class="ceo-line-meta-list">
                    <?php foreach ($lineRows as $row): ?>
                        <article class="ceo-line-meta-card">
                            <header><strong><?= e($row['line_title'] ?: $row['line_code']) ?></strong><small><?= e(format_percent((float)$row['achievement_percent'], 2)) ?></small></header>
                            <dl>
                                <div><dt>مدیر فروش</dt><dd><?= e($row['sales_manager_name'] ?: '-') ?></dd></div>
                                <div><dt>سرپرست</dt><dd><?= e($row['supervisor_name'] ?: '-') ?></dd></div>
                                <div><dt>فروش</dt><dd title="<?= e(format_money($row['sales_value'])) ?>"><?= e(format_large_number($row['sales_value'])) ?></dd></div>
                                <div><dt>قطعه</dt><dd><?= e(format_number($row['qty_value'])) ?></dd></div>
                                <div><dt>تارگت</dt><dd><?= e(format_number($row['target_value'])) ?></dd></div>
                            </dl>
                            <b class="<?= e(ceo_progress_class((float)$row['achievement_percent'])) ?>" style="--progress: <?= e((string)min(100, max(0, (float)$row['achievement_percent']))) ?>%"></b>
                        </article>
                    <?php endforeach; ?>
                </div>
            </article>

            <?php if ($showCharts): ?>
                <article class="ceo-mobile-card ceo-mobile-chart-card">
                    <h3>نمودار درصد تحقق لاین‌ها</h3>
                    <canvas id="mobileLineAchievementChart"></canvas>
                </article>
                <article class="ceo-mobile-card ceo-mobile-chart-card ceo-mobile-only-chart">
                    <h3>درصد تحقق ویزیتورها</h3>
                    <canvas id="mobileVisitorAchievementChart"></canvas>
                </article>
            <?php endif; ?>

            <article class="ceo-mobile-card ceo-mobile-rank-card">
                <h3>Top 5 ویزیتور</h3>
                <?php if (!$topVisitors): ?><p class="muted">داده‌ای برای ویزیتورها ثبت نشده است.</p><?php endif; ?>
                <div class="ceo-mobile-rank-list">
                    <?php foreach ($topVisitors as $index => $row): ?>
                        <div><b><?= e((string)($index + 1)) ?></b><span><?= e($row['visitor_name']) ?></span><strong><?= e(format_large_number($row['sales_value'])) ?></strong></div>
                    <?php endforeach; ?>
                </div>
            </article>

            <article class="ceo-mobile-card ceo-mobile-rank-card">
                <h3>Top 5 کالا</h3>
                <?php if (!$topProducts): ?><p class="muted">داده‌ای برای کالاها ثبت نشده است.</p><?php endif; ?>
                <div class="ceo-mobile-rank-list">
                    <?php foreach ($topProducts as $index => $row): ?>
                        <div><b><?= e((string)($index + 1)) ?></b><span><?= e($row['line_title'] ?: $row['line_code']) ?></span><strong title="<?= e(format_money($row['sales_amount'])) ?>"><?= e(format_large_number($row['sales_amount'])) ?></strong></div>
                    <?php endforeach; ?>
                </div>
            </article>

            <details class="ceo-mobile-card ceo-mobile-details">
                <summary>مشاهده جزئیات ویزیتورها</summary>
                <div class="ceo-visitor-progress-list">
                    <?php foreach ($visitorRows as $row): ?>
                        <article class="ceo-visitor-progress-card">
                            <header><strong><?= e($row['visitor_name']) ?></strong><small><?= e(format_percent((float)$row['achievement_percent'], 2)) ?></small></header>
                            <dl>
                                <div><dt>لاین</dt><dd><?= e($row['line_code']) ?></dd></div>
                                <div><dt>کاربر متصل</dt><dd><?= e($row['user_name'] ?: '-') ?></dd></div>
                                <div><dt>فروش</dt><dd title="<?= e(format_money($row['sales_value'])) ?>"><?= e(format_large_number($row['sales_value'])) ?></dd></div>
                                <div><dt>قطعه</dt><dd><?= e(format_number($row['qty_value'])) ?></dd></div>
                                <div><dt>تارگت</dt><dd><?= e(format_number($row['target_value'])) ?></dd></div>
                            </dl>
                            <b class="<?= e(ceo_progress_class((float)$row['achievement_percent'])) ?>" style="--progress: <?= e((string)min(100, max(0, (float)$row['achievement_percent']))) ?>%"></b>
                        </article>
                    <?php endforeach; ?>
                </div>
            </details>
        <?php endif; ?>
    </section>

    <section class="ceo-mobile-section ceo-section-pharmacy">
        <div class="ceo-section-title">
            <h2>داشبورد داروخانه‌ها</h2>
            <p>نمای جداگانه فروش، خرید، هزینه، چک‌ها و مبلغ فاکتور باز</p>
        </div>
        <?php if (!$pharmacyRows): ?>
            <div class="ceo-mobile-card"><p class="muted">هنوز داروخانه فعالی ثبت نشده است.</p></div>
        <?php elseif (!$pharmacyHasData): ?>
            <div class="ceo-mobile-card"><p class="muted">هنوز داده‌ای برای داشبورد داروخانه‌ها ثبت نشده است.</p></div>
        <?php else: ?>
            <div class="ceo-mobile-kpi-grid pharmacy-kpis">
                <article class="ceo-mobile-kpi"><span>فروش روزانه</span><strong title="<?= e(format_money($pharmacyTotals['daily_sales'])) ?>"><?= e(format_large_number($pharmacyTotals['daily_sales'])) ?></strong></article>
                <article class="ceo-mobile-kpi"><span>فروش ماهانه</span><strong title="<?= e(format_money($pharmacyTotals['monthly_sales'])) ?>"><?= e(format_large_number($pharmacyTotals['monthly_sales'])) ?></strong></article>
                <article class="ceo-mobile-kpi"><span>مبلغ خرید</span><strong title="<?= e(format_money($pharmacyTotals['supplier_purchase_amount'])) ?>"><?= e(format_large_number($pharmacyTotals['supplier_purchase_amount'])) ?></strong></article>
                <article class="ceo-mobile-kpi"><span>مبلغ هزینه‌ها</span><strong title="<?= e(format_money($pharmacyTotals['expenses_amount'])) ?>"><?= e(format_large_number($pharmacyTotals['expenses_amount'])) ?></strong></article>
                <article class="ceo-mobile-kpi"><span>چک‌های در جریان وصول</span><strong title="<?= e(format_money($pharmacyTotals['pending_checks_amount'])) ?>"><?= e(format_large_number($pharmacyTotals['pending_checks_amount'])) ?></strong></article>
                <article class="ceo-mobile-kpi"><span>مبلغ فاکتور باز</span><strong title="<?= e(format_money($pharmacyTotals['open_invoice_amount'])) ?>"><?= e(format_large_number($pharmacyTotals['open_invoice_amount'])) ?></strong></article>
            </div>

            <?php if ($showCharts): ?>
                <article class="ceo-mobile-card ceo-mobile-chart-card"><h3>فروش روزانه / ماهانه</h3><canvas id="mobilePharmacySalesChart"></canvas></article>
                <article class="ceo-mobile-card ceo-mobile-chart-card"><h3>مبلغ خرید</h3><canvas id="mobilePharmacyPurchaseChart"></canvas></article>
                <article class="ceo-mobile-card ceo-mobile-chart-card"><h3>مبلغ هزینه‌ها</h3><canvas id="mobilePharmacyExpensesChart"></canvas></article>
                <article class="ceo-mobile-card ceo-mobile-chart-card"><h3>چک‌های در جریان وصول</h3><canvas id="mobilePharmacyChecksChart"></canvas></article>
                <article class="ceo-mobile-card ceo-mobile-chart-card"><h3>مبلغ فاکتور باز</h3><canvas id="mobilePharmacyOpenInvoiceChart"></canvas></article>
            <?php endif; ?>

            <article class="ceo-mobile-card">
                <h3>وضعیت داروخانه‌ها</h3>
                <div class="ceo-mobile-row-cards pharmacy-rows">
                    <?php foreach ($pharmacyRowsWithTotal as $row): ?>
                        <article>
                            <strong><?= e($row['title'] === 'Total' ? 'مجموع کل' : $row['title']) ?></strong>
                            <span>روزانه: <?= e(format_large_number($row['daily_sales'])) ?></span>
                            <span>ماهانه: <?= e(format_large_number($row['monthly_sales'])) ?></span>
                            <span>فاکتور باز: <?= e(format_large_number($row['open_invoice_amount'])) ?></span>
                        </article>
                    <?php endforeach; ?>
                </div>
            </article>

            <details class="ceo-mobile-card ceo-mobile-details">
                <summary>مشاهده جزئیات داروخانه‌ها</summary>
                <div class="ceo-mobile-row-cards pharmacy-rows">
                    <?php foreach ($pharmacyRowsWithTotal as $row): ?>
                        <article>
                            <strong><?= e($row['title'] === 'Total' ? 'مجموع کل' : $row['title']) ?></strong>
                            <span>خرید: <?= e(format_large_number($row['supplier_purchase_amount'])) ?></span>
                            <span>هزینه: <?= e(format_large_number($row['expenses_amount'])) ?></span>
                            <span>چک: <?= e(format_large_number($row['pending_checks_amount'])) ?></span>
                        </article>
                    <?php endforeach; ?>
                </div>
            </details>
        <?php endif; ?>
    </section>
</section>

<div class="ceo-desktop-dashboard">
<form class="card admin-form ceo-filter" method="get">
    <div class="grid grid-3">
        <label class="form-field">
            <span>تاریخ گزارش</span>
            <select name="report_date">
                <option value="">همه</option>
                <?php foreach ($dateOptions as $date): ?><option value="<?= e($date) ?>" <?= $filters['report_date'] === $date ? 'selected' : '' ?>><?= e(format_jalali_date($date)) ?></option><?php endforeach; ?>
            </select>
        </label>
        <label class="form-field">
            <span>لاین</span>
            <select name="line_code">
                <option value="">همه</option>
                <?php foreach ($lineOptions as $lineCode): ?><option value="<?= e($lineCode) ?>" <?= $filters['line_code'] === $lineCode ? 'selected' : '' ?>><?= e($lineCode) ?></option><?php endforeach; ?>
            </select>
        </label>
        <label class="form-field">
            <span>داروخانه</span>
            <select name="pharmacy_id">
                <option value="0">همه</option>
                <?php foreach ($pharmacyOptions as $pharmacy): ?><option value="<?= e($pharmacy['id']) ?>" <?= $filters['pharmacy_id'] === (int)$pharmacy['id'] ? 'selected' : '' ?>><?= e($pharmacy['title']) ?></option><?php endforeach; ?>
            </select>
        </label>
        <div class="form-actions ceo-filter-actions">
            <button class="btn btn-primary">اعمال فیلتر</button>
            <a class="btn" href="/admin/ceo-dashboard.php">پاکسازی</a>
            <?php if (Auth::can('ceo_dashboard', 'edit')): ?><a class="btn" href="/admin/ceo-dashboard-settings.php?period_key=<?= e(urlencode($reportPeriodKey)) ?>#summary-import">تنظیمات داشبورد مدیرعامل</a><?php endif; ?>
        </div>
    </div>
</form>

<section class="card sobhan-api-section">
    <div class="sobhan-api-status">
        <div>
            <h2>گزارش زنده سبحان</h2>
            <p class="muted"><?= e($sobhanMetrics['last_sync_at'] ? 'آخرین همگام‌سازی: ' . $sobhanMetrics['last_sync_at'] : ($distributionDataMode === 'ai_api' ? 'داده‌های API در کنار داده‌های محلی نمایش داده می‌شود.' : 'در حالت فایل ایمپورت، API برای تکمیل مقادیر داشبورد فراخوانی نمی‌شود.')) ?></p>
        </div>
        <div class="source-badge-stack">
            <span class="badge"><?= e($distributionDataMode === 'ai_api' ? 'منبع: API سبحان' : 'منبع: فایل ایمپورت') ?></span>
            <span class="badge <?= $sobhanApiOk ? '' : 'badge-off' ?>"><?= $sobhanApiOk ? 'API فعال' : 'API غیرفعال/ناموفق' ?></span>
        </div>
    </div>
    <?php if (!$sobhanApiOk): ?>
        <div class="alert alert-error"><?= e($sobhanErrorMessage) ?></div>
    <?php else: ?>
        <div class="sobhan-api-kpis">
            <div class="stat-card"><span>فروش ناخالص</span><strong><?= e(format_money($sobhanMetrics['gross_sales'])) ?></strong></div>
            <div class="stat-card"><span>تخفیف کل</span><strong><?= e(format_money($sobhanMetrics['discount_total'])) ?></strong></div>
            <div class="stat-card"><span>فروش خالص</span><strong><?= e(format_money($sobhanMetrics['net_sales'])) ?></strong></div>
            <div class="stat-card"><span>تعداد فاکتور</span><strong><?= e(format_number($sobhanMetrics['invoice_count'])) ?></strong></div>
        </div>
        <?php if ($sobhanDailyRows && $showCharts): ?>
            <section class="sobhan-api-chart ceo-chart-card">
                <h2>فروش روزانه</h2>
                <canvas id="sobhanDailySalesChart"></canvas>
            </section>
        <?php endif; ?>
        <div class="sobhan-api-tables">
            <section>
                <h3>ویزیتورهای برتر</h3>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>ویزیتور</th><th>فروش</th><th>فاکتور</th></tr></thead>
                        <tbody>
                        <?php foreach ($sobhanVisitorRows as $row): $row = (array)$row; ?>
                            <tr><td><?= e(sobhan_text($row, ['visitor_name', 'visitor', 'name'], 'نامشخص')) ?></td><td><?= e(format_money(sobhan_number($row, ['net_sales', 'gross_sales', 'sales', 'amount']))) ?></td><td><?= e(format_number(sobhan_number($row, ['invoice_count', 'invoices', 'count']))) ?></td></tr>
                        <?php endforeach; ?>
                        <?php if (!$sobhanVisitorRows): ?><tr><td colspan="3">داده‌ای دریافت نشد.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
            <section>
                <h3>کالاهای پرفروش</h3>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>کالا</th><th>فروش</th><th>تعداد</th></tr></thead>
                        <tbody>
                        <?php foreach ($sobhanProductRows as $row): $row = (array)$row; ?>
                            <tr><td><?= e(sobhan_text($row, ['product_name', 'product', 'name'], 'نامشخص')) ?></td><td><?= e(format_money(sobhan_number($row, ['net_sales', 'gross_sales', 'sales', 'amount']))) ?></td><td><?= e(format_number(sobhan_number($row, ['quantity', 'qty', 'count']))) ?></td></tr>
                        <?php endforeach; ?>
                        <?php if (!$sobhanProductRows): ?><tr><td colspan="3">داده‌ای دریافت نشد.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    <?php endif; ?>
</section>

<?php if (!$hasData): ?>
    <div class="card ceo-empty">
        <h2>هنوز اطلاعاتی برای داشبورد مدیرعامل ثبت نشده است.</h2>
        <div class="form-actions">
            <?php if (Auth::can('ceo_dashboard', 'create')): ?><a class="btn btn-primary" href="/admin/ceo-dashboard-settings.php#lines">افزودن اطلاعات لاین</a><?php endif; ?>
            <?php if (Auth::can('ceo_dashboard', 'create')): ?><a class="btn" href="/admin/ceo-dashboard-settings.php#visitors">افزودن اطلاعات ویزیتور</a><?php endif; ?>
        </div>
    </div>
<?php else: ?>
    <div class="ceo-dashboard-grid">
        <section class="card ceo-kpi-card">
            <h2><?= e($labels['page_title']) ?> <span class="badge"><?= e($summarySourceBadge) ?></span></h2>
            <div class="ceo-kpi-list">
                <div><span><?= e($labels['gross_sales']) ?></span><strong><?= e(format_money($dashboardGrossSales)) ?></strong></div>
                <div><span><?= e($labels['discounts']) ?></span><strong><?= e(format_money($dashboardDiscounts)) ?></strong></div>
                <div><span><?= e($labels['discount_percent']) ?></span><strong><?= e(format_percent($dashboardDiscountPercent, 1)) ?></strong></div>
                <div><span><?= e($labels['net_sales']) ?></span><strong><?= e(format_money($dashboardNetSales)) ?></strong></div>
                <?php if ($dashboardUsesApiMetrics): ?><div><span>تعداد فاکتور</span><strong><?= e(format_number($dashboardInvoiceCount)) ?></strong></div><?php endif; ?>
            </div>
        </section>

        <?php if ($showCharts): ?><section class="card ceo-chart-card">
            <h2><?= e($labels['net_sales']) ?> / <?= e($labels['discounts']) ?></h2>
            <canvas id="ceoMoneyDonut"></canvas>
        </section>

        <section class="card ceo-chart-card">
            <h2><?= e($labels['line_sales_chart']) ?></h2>
            <canvas id="ceoLineSales"></canvas>
        </section><?php endif; ?>
    </div>

    <?php if ($showLineTable || $showCharts): ?>
    <div class="ceo-dashboard-grid ceo-dashboard-grid-2">
        <?php if ($showLineTable): ?>
        <section class="card">
            <h2><?= e($labels['line_table']) ?> <span class="badge">منبع: فایل ایمپورت</span></h2>
            <div class="table-wrap ceo-table-wrap">
                <table>
                    <thead><tr><th>لاین</th><th>مبلغ فروش</th><th>تعداد قطعه</th><th>تارگت</th><th>درصد تحقق</th><th>Progress Bar</th></tr></thead>
                    <tbody>
                    <?php foreach ($lineRows as $row): ?>
                        <tr>
                            <td><?= e($row['line_title'] ?: $row['line_code']) ?></td>
                            <td><?= e(format_money($row['sales_amount'])) ?></td>
                            <td><?= e(format_number($row['qty'])) ?></td>
                            <td><?= e(format_number($row['target_qty'])) ?></td>
                            <td><span class="achievement-pill <?= e(ceo_status_class((float)$row['achievement_percent'])) ?>"><?= e(format_percent((float)$row['achievement_percent'], 2)) ?></span></td>
                            <td><span class="ceo-inline-progress"><b class="<?= e(ceo_progress_class((float)$row['achievement_percent'])) ?>" style="--progress: <?= e((string)min(100, max(0, (float)$row['achievement_percent']))) ?>%"></b></span></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="ceo-total-row">
                        <td>فروش کل لاین‌ها</td>
                        <td><?= e(format_money($grossSales)) ?></td>
                        <td><?= e(format_number($totalQty)) ?></td>
                        <td><?= e(format_number($totalTarget)) ?></td>
                        <td><span class="achievement-pill <?= e(ceo_status_class($totalAchievement)) ?>"><?= e(format_percent($totalAchievement, 2)) ?></span></td>
                        <td><span class="ceo-inline-progress"><b class="<?= e(ceo_progress_class($totalAchievement)) ?>" style="--progress: <?= e((string)min(100, max(0, $totalAchievement))) ?>%"></b></span></td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </section>
        <?php endif; ?>

        <?php if ($showCharts): ?><section class="card ceo-chart-card">
            <h2><?= e($labels['line_achievement_chart']) ?></h2>
            <canvas id="ceoLineAchievement"></canvas>
        </section><?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($showVisitorTable): ?>
    <section class="card">
        <h2><?= e($labels['visitor_table']) ?> <span class="badge">منبع: فایل ایمپورت</span></h2>
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
                        <td><span class="achievement-pill <?= e(ceo_status_class((float)$row['achievement_percent'])) ?>"><?= e(format_percent((float)$row['achievement_percent'], 2)) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($showCharts): ?><section class="card ceo-chart-card ceo-wide-chart">
        <h2><?= e($labels['visitor_achievement_chart']) ?></h2>
        <canvas id="ceoVisitorAchievement"></canvas>
    </section><?php endif; ?>
<?php endif; ?>

<?php if ($showAiChat && Auth::can('use_ai_assistant')): ?>
<section class="card sobhan-ai-panel">
    <h2>دستیار هوش مصنوعی <span class="badge">منبع: هوش مصنوعی</span></h2>
    <?php if ($aiAutofillEnabled): ?>
        <div class="alert alert-success">تکمیل خودکار با هوش مصنوعی فعال است. پیشنهادها فقط در حالت پیش‌نمایش نمایش داده می‌شوند و بدون تأیید ذخیره نمی‌شوند.</div>
    <?php endif; ?>
    <?php if ($aiOverwriteManual): ?>
        <div class="alert alert-error">با فعال‌سازی این گزینه، داده‌های دستی یا ایمپورت‌شده ممکن است با داده‌های API/هوش مصنوعی جایگزین شوند.</div>
    <?php endif; ?>
    <?php if (!$sobhanEnabled): ?>
        <div class="alert alert-error">سرویس گزارش‌گیری سبحان غیرفعال است.</div>
    <?php endif; ?>
    <div class="suggested-prompts">
        <?php foreach (['فروش کل و تعداد فاکتورها را خلاصه کن', 'سه ویزیتور برتر را تحلیل کن', 'پرفروش‌ترین کالاها را معرفی کن', 'یک گزارش مدیریتی کوتاه بده'] as $prompt): ?>
            <button type="button" class="btn btn-small" data-dashboard-prompt="<?= e($prompt) ?>"><?= e($prompt) ?></button>
        <?php endforeach; ?>
    </div>
    <form method="post" data-loading-text="در حال تحلیل...">
        <input type="hidden" name="csrf_token" value="<?= e(Auth::csrfToken()) ?>">
        <input type="hidden" name="dashboard_action" value="ai_ask">
        <label class="form-field">
            <span>پرسش مدیریتی</span>
            <textarea name="question" maxlength="1000" rows="4" required><?= e($aiDashboardQuestion) ?></textarea>
        </label>
        <button class="btn btn-primary">تحلیل کن</button>
    </form>
    <?php if ($aiDashboardAnswer !== ''): ?>
        <div class="sobhan-ai-answer">
            <?= nl2br(e($aiDashboardAnswer)) ?>
            <?= render_ai_knowledge_sources($aiDashboardKnowledgeSources) ?>
        </div>
        <?php if ($aiAutofillEnabled): ?>
            <div class="sobhan-ai-preview">
                <strong>پیش‌نمایش پیشنهاد هوش مصنوعی</strong>
                <p class="muted">AI suggestions are not final data until confirmed.</p>
                <button class="btn" type="button" disabled>تأیید و اعمال مقادیر پیشنهادی</button>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</section>
<?php endif; ?>

<section class="card ceo-pharmacy-section">
    <div class="section-heading-row">
        <div>
            <h2>داروخانه‌ها</h2>
            <p class="muted">آخرین تاریخ گزارش: <?= e($pharmacyMetricDate ? format_jalali_date($pharmacyMetricDate) : 'بدون داده') ?></p>
            <span class="badge">منبع داروخانه‌ها: فایل استاتیک</span>
        </div>
        <?php if (Auth::can('pharmacy_settings', 'edit')): ?><a class="btn" href="/admin/pharmacy-settings.php">تنظیمات داروخانه</a><?php endif; ?>
    </div>
    <?php if (!$pharmacyRows): ?>
        <p class="muted">هنوز داروخانه فعالی ثبت نشده است.</p>
    <?php elseif (!$pharmacyHasData): ?>
        <p class="muted">هنوز داده‌ای برای داشبورد داروخانه‌ها ثبت نشده است.</p>
    <?php else: ?>
        <div class="stats pharmacy-stats">
            <?php foreach (array_merge($pharmacyRows, [$pharmacyTotals]) as $row): ?>
                <div class="stat-card">
                    <span><?= e($row['title']) ?></span>
                    <strong><?= e(format_money($row['daily_sales'])) ?></strong>
                    <small>روزانه</small>
                    <strong><?= e(format_money($row['monthly_sales'])) ?></strong>
                    <small>ماهانه</small>
                </div>
            <?php endforeach; ?>
        </div>
        <?php if ($showCharts): ?>
            <div class="ceo-dashboard-grid ceo-dashboard-grid-2">
                <section class="card ceo-chart-card">
                    <h2>فروش روزانه و ماهانه</h2>
                    <canvas id="pharmacySalesBar"></canvas>
                </section>
                <section class="card ceo-chart-card">
                    <h2>سهم مبلغ خرید</h2>
                    <canvas id="pharmacySupplierPie"></canvas>
                </section>
                <section class="card ceo-chart-card">
                    <h2>مبلغ فاکتور باز</h2>
                    <canvas id="pharmacyOpenInvoiceBar"></canvas>
                </section>
                <section class="card ceo-chart-card">
                    <h2>گزارش مبلغ هزینه‌ها</h2>
                    <canvas id="pharmacyExpensesBar"></canvas>
                </section>
                <section class="card ceo-chart-card">
                    <h2>چک‌های در جریان وصول</h2>
                    <canvas id="pharmacyChecksPie"></canvas>
                </section>
            </div>
        <?php endif; ?>
        <div class="table-wrap ceo-table-wrap">
            <table>
                <thead><tr><th>داروخانه</th><th>فروش روزانه</th><th>فروش ماهانه</th><th>مبلغ خرید</th><th>هزینه‌ها</th><th>چک‌های در جریان وصول</th><th>مبلغ فاکتور باز</th></tr></thead>
                <tbody>
                <?php foreach (array_merge($pharmacyRows, [$pharmacyTotals]) as $row): ?>
                    <tr class="<?= $row['title'] === 'Total' ? 'ceo-total-row' : '' ?>">
                        <td><?= e($row['title']) ?></td><td><?= e(format_money($row['daily_sales'])) ?></td><td><?= e(format_money($row['monthly_sales'])) ?></td><td><?= e(format_money($row['supplier_purchase_amount'])) ?></td><td><?= e(format_money($row['expenses_amount'])) ?></td><td><?= e(format_money($row['pending_checks_amount'])) ?></td><td><?= e(format_money($row['open_invoice_amount'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php if (($hasData || $pharmacyHasData || ($sobhanApiOk && $sobhanDailyRows)) && $showCharts): ?>
<script src="/assets/js/chart.umd.min.js"></script>
<script>
const ceoChartData = <?= json_encode($chartData, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK) ?>;
const percentTick = value => value + '%';
const compactMoneyTick = value => {
    const number = Number(value || 0);
    const abs = Math.abs(number);
    if (abs >= 1000000000) return (number / 1000000000).toLocaleString('en-US', {maximumFractionDigits: number >= 100000000000 ? 0 : 1}) + ' میلیارد';
    if (abs >= 1000000) return (number / 1000000).toLocaleString('en-US', {maximumFractionDigits: number >= 100000000 ? 0 : 1}) + ' میلیون';
    return number.toLocaleString('en-US');
};
const fullNumberLabel = ctx => {
    const value = ctx.parsed?.y ?? ctx.parsed?.x ?? ctx.parsed ?? 0;
    return (ctx.dataset.label ? ctx.dataset.label + ': ' : '') + Number(value || 0).toLocaleString('en-US');
};
const chartFont = {family: 'Tahoma, Arial, sans-serif'};
const ceoTheme = getComputedStyle(document.body);
const ceoThemeColor = name => ceoTheme.getPropertyValue(name).trim();
const ceoNeon = ceoThemeColor('--ceo-neon') || '#00D5FF';
const ceoBlue = '#2563EB';
const ceoEmerald = '#10B981';
const ceoGold = '#F59E0B';
const ceoGoldSoft = 'rgba(245,158,11,.12)';
const ceoEmeraldSoft = 'rgba(16,185,129,.12)';
const ceoOlive = '#0EA5E9';
const ceoSage = '#8B5CF6';
const ceoBronze = '#EC4899';
const ceoDanger = '#EF4444';
const ceoChartPalette = [ceoNeon, ceoBlue, ceoEmerald, ceoGold, ceoSage, ceoDanger, ceoBronze, ceoOlive];
Chart.defaults.font.family = chartFont.family;
Chart.defaults.color = ceoThemeColor('--ceo-text-muted');
Chart.defaults.borderColor = ceoThemeColor('--ceo-chart-grid');
Chart.defaults.animation.duration = document.body.classList.contains('theme-effects-reduced') ? 0 : 260;
Chart.defaults.devicePixelRatio = Math.min(window.devicePixelRatio || 1, 1.5);
Chart.defaults.resizeDelay = 100;

if (document.getElementById('ceoMoneyDonut')) new Chart(document.getElementById('ceoMoneyDonut'), {
    type: 'doughnut',
    data: {labels: ceoChartData.moneyLabels, datasets: [{data: ceoChartData.moneyValues, backgroundColor: [ceoBlue, ceoDanger], borderWidth: 0}]},
    options: {responsive: true, maintainAspectRatio: false, plugins: {legend: {position: 'bottom'}}}
});

if (document.getElementById('sobhanDailySalesChart')) new Chart(document.getElementById('sobhanDailySalesChart'), {
    type: 'line',
    data: {labels: ceoChartData.sobhanDailyLabels, datasets: [{label: 'فروش روزانه', data: ceoChartData.sobhanDailySales, borderColor: ceoBlue, backgroundColor: 'rgba(37,99,235,.11)', tension: .3, fill: true, pointRadius: 2}]},
    options: {responsive: true, maintainAspectRatio: false, plugins: {legend: {display: false}, tooltip: {rtl: true, callbacks: {label: fullNumberLabel}}}, scales: {y: {beginAtZero: true, ticks: {callback: compactMoneyTick}}}}
});

if (document.getElementById('mobileLineSalesChart')) new Chart(document.getElementById('mobileLineSalesChart'), {
    type: 'bar',
    data: {labels: ceoChartData.lineLabels, datasets: [{label: 'فروش لاین‌ها', data: ceoChartData.lineSales, backgroundColor: ceoBlue, borderRadius: 8, maxBarThickness: 34}]},
    options: {responsive: true, maintainAspectRatio: false, plugins: {legend: {display: false}, tooltip: {rtl: true, callbacks: {label: fullNumberLabel}}}, scales: {x: {grid: {display: false}}, y: {beginAtZero: true, ticks: {maxTicksLimit: 4, callback: compactMoneyTick}}}}
});

if (document.getElementById('mobileLineAchievementChart')) new Chart(document.getElementById('mobileLineAchievementChart'), {
    type: 'bar',
    data: {labels: ceoChartData.lineLabels, datasets: [{label: 'درصد تحقق', data: ceoChartData.lineAchievement, backgroundColor: ceoGold, borderRadius: 8, maxBarThickness: 30}]},
    options: {responsive: true, maintainAspectRatio: false, plugins: {legend: {position: 'bottom', labels: {boxWidth: 10, font: {size: 10}}}, tooltip: {rtl: true, callbacks: {label: ctx => ctx.parsed.y + '%'}}}, scales: {x: {grid: {display: false}}, y: {beginAtZero: true, ticks: {callback: percentTick, maxTicksLimit: 4}}}}
});

if (document.getElementById('mobileVisitorAchievementChart')) new Chart(document.getElementById('mobileVisitorAchievementChart'), {
    type: 'bar',
    data: {labels: ceoChartData.visitorLabels, datasets: [{label: 'درصد تحقق ویزیتور', data: ceoChartData.visitorAchievement, backgroundColor: ceoEmerald, borderRadius: 8, maxBarThickness: 24}]},
    options: {indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: {legend: {position: 'bottom', labels: {boxWidth: 10, font: {size: 10}}}, tooltip: {rtl: true, callbacks: {label: ctx => {
        const index = ctx.dataIndex;
        const sales = Number(ceoChartData.visitorSales[index] || 0).toLocaleString('en-US');
        const target = Number(ceoChartData.visitorTargets[index] || 0).toLocaleString('en-US');
        return `${ctx.parsed.x}% | فروش: ${sales} | تارگت: ${target}`;
    }}}}, scales: {x: {beginAtZero: true, ticks: {callback: percentTick, maxTicksLimit: 4}}, y: {grid: {display: false}, ticks: {font: {size: 10}}}}}
});

if (document.getElementById('mobilePharmacySalesChart')) new Chart(document.getElementById('mobilePharmacySalesChart'), {
    type: 'bar',
    data: {labels: ceoChartData.pharmacyLabelsTotal, datasets: [
        {label: 'روزانه', data: ceoChartData.pharmacyDailySales, backgroundColor: ceoEmerald, borderRadius: 8, maxBarThickness: 24},
        {label: 'ماهانه', data: ceoChartData.pharmacyMonthlySales, backgroundColor: ceoGold, borderRadius: 8, maxBarThickness: 24}
    ]},
    options: {responsive: true, maintainAspectRatio: false, plugins: {legend: {position: 'bottom', labels: {boxWidth: 10, font: {size: 10}}}, tooltip: {rtl: true, callbacks: {label: fullNumberLabel}}}, scales: {x: {grid: {display: false}}, y: {beginAtZero: true, ticks: {maxTicksLimit: 4, callback: compactMoneyTick}}}}
});

if (document.getElementById('mobilePharmacyPurchaseChart')) new Chart(document.getElementById('mobilePharmacyPurchaseChart'), {
    type: 'doughnut',
    data: {labels: ceoChartData.pharmacyLabels, datasets: [{data: ceoChartData.pharmacySupplierPurchase, backgroundColor: ceoChartPalette, borderWidth: 0}]},
    options: {responsive: true, maintainAspectRatio: false, cutout: '58%', plugins: {legend: {position: 'bottom', labels: {boxWidth: 10, font: {size: 10}}}, tooltip: {rtl: true}}}
});

if (document.getElementById('mobilePharmacyExpensesChart')) new Chart(document.getElementById('mobilePharmacyExpensesChart'), {
    type: 'bar',
    data: {labels: ceoChartData.pharmacyLabelsTotal, datasets: [{label: 'هزینه', data: ceoChartData.pharmacyExpenses, backgroundColor: ceoDanger, borderRadius: 8, maxBarThickness: 26}]},
    options: {indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: {legend: {display: false}, tooltip: {rtl: true, callbacks: {label: fullNumberLabel}}}, scales: {x: {beginAtZero: true, ticks: {maxTicksLimit: 4, callback: compactMoneyTick}}, y: {grid: {display: false}}}}
});

if (document.getElementById('mobilePharmacyChecksChart')) new Chart(document.getElementById('mobilePharmacyChecksChart'), {
    type: 'doughnut',
    data: {labels: ceoChartData.pharmacyLabels, datasets: [{data: ceoChartData.pharmacyChecks, backgroundColor: ceoChartPalette, borderWidth: 0}]},
    options: {responsive: true, maintainAspectRatio: false, cutout: '60%', plugins: {legend: {position: 'bottom', labels: {boxWidth: 10, font: {size: 10}}}, tooltip: {rtl: true}}}
});

if (document.getElementById('mobilePharmacyOpenInvoiceChart')) new Chart(document.getElementById('mobilePharmacyOpenInvoiceChart'), {
    type: 'bar',
    data: {labels: ceoChartData.pharmacyLabelsTotal, datasets: [{label: 'مبلغ فاکتور باز', data: ceoChartData.pharmacyOpenInvoice, backgroundColor: ceoBronze, borderRadius: 8, maxBarThickness: 30}]},
    options: {responsive: true, maintainAspectRatio: false, plugins: {legend: {position: 'bottom', labels: {boxWidth: 10, font: {size: 10}}}, tooltip: {rtl: true, callbacks: {label: fullNumberLabel}}}, scales: {x: {grid: {display: false}}, y: {beginAtZero: true, ticks: {maxTicksLimit: 4, callback: compactMoneyTick}}}}
});

if (document.getElementById('ceoLineSales')) new Chart(document.getElementById('ceoLineSales'), {
    type: 'bar',
    data: {labels: ceoChartData.lineLabels, datasets: [{label: '<?= e($labels['line_sales_chart']) ?>', data: ceoChartData.lineSales, backgroundColor: ceoGold, borderRadius: 6}]},
    options: {responsive: true, maintainAspectRatio: false, plugins: {legend: {display: false}}, scales: {y: {beginAtZero: true}}}
});

if (document.getElementById('ceoLineAchievement')) new Chart(document.getElementById('ceoLineAchievement'), {
    type: 'doughnut',
    data: {labels: ceoChartData.lineLabels, datasets: [{label: '<?= e($labels['line_share_chart']) ?>', data: ceoChartData.lineAchievement, backgroundColor: ceoChartPalette, borderWidth: 0}]},
    options: {responsive: true, maintainAspectRatio: false, plugins: {legend: {position: 'bottom'}, tooltip: {callbacks: {label: ctx => ctx.label + ': ' + ctx.parsed + '%'}}}}
});

if (document.getElementById('ceoVisitorAchievement')) new Chart(document.getElementById('ceoVisitorAchievement'), {
    type: 'line',
    data: {labels: ceoChartData.visitorLabels, datasets: [{label: '<?= e($labels['visitor_achievement_chart']) ?>', data: ceoChartData.visitorAchievement, borderColor: ceoGold, backgroundColor: ceoGoldSoft, tension: .35, fill: true, pointRadius: 4, pointHoverRadius: 6}]},
    options: {responsive: true, maintainAspectRatio: false, plugins: {legend: {display: false}, tooltip: {callbacks: {label: ctx => ctx.parsed.y + '%'}}}, scales: {y: {beginAtZero: true, ticks: {callback: percentTick}}}}
});

if (document.getElementById('pharmacySalesBar')) new Chart(document.getElementById('pharmacySalesBar'), {
    type: 'bar',
    data: {labels: ceoChartData.pharmacyLabelsTotal, datasets: [
        {label: 'فروش روزانه', data: ceoChartData.pharmacyDailySales, backgroundColor: ceoGold, borderRadius: 6},
        {label: 'فروش ماهانه', data: ceoChartData.pharmacyMonthlySales, backgroundColor: ceoEmerald, borderRadius: 6}
    ]},
    options: {responsive: true, maintainAspectRatio: false, plugins: {legend: {position: 'bottom'}}, scales: {y: {beginAtZero: true}}}
});

if (document.getElementById('pharmacySupplierPie')) new Chart(document.getElementById('pharmacySupplierPie'), {
    type: 'pie',
    data: {labels: ceoChartData.pharmacyLabels, datasets: [{data: ceoChartData.pharmacySupplierPurchase, backgroundColor: ceoChartPalette, borderWidth: 0}]},
    options: {responsive: true, maintainAspectRatio: false, plugins: {legend: {position: 'bottom'}}}
});

if (document.getElementById('pharmacyOpenInvoiceBar')) new Chart(document.getElementById('pharmacyOpenInvoiceBar'), {
    type: 'bar',
    data: {labels: ceoChartData.pharmacyLabelsTotal, datasets: [{label: 'مبلغ فاکتور باز', data: ceoChartData.pharmacyOpenInvoice, backgroundColor: ceoBronze, borderRadius: 6}]},
    options: {responsive: true, maintainAspectRatio: false, plugins: {legend: {display: false}}, scales: {y: {beginAtZero: true}}}
});

if (document.getElementById('pharmacyExpensesBar')) new Chart(document.getElementById('pharmacyExpensesBar'), {
    type: 'bar',
    data: {labels: ceoChartData.pharmacyLabelsTotal, datasets: [{label: 'هزینه‌ها', data: ceoChartData.pharmacyExpenses, backgroundColor: ceoDanger, borderRadius: 6}]},
    options: {indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: {legend: {display: false}}, scales: {x: {beginAtZero: true}}}
});

if (document.getElementById('pharmacyChecksPie')) new Chart(document.getElementById('pharmacyChecksPie'), {
    type: 'doughnut',
    data: {labels: ceoChartData.pharmacyLabels, datasets: [{data: ceoChartData.pharmacyChecks, backgroundColor: ceoChartPalette, borderWidth: 0}]},
    options: {responsive: true, maintainAspectRatio: false, plugins: {legend: {position: 'bottom'}}}
});
</script>
<?php endif; ?>
<script>
document.querySelectorAll('[data-dashboard-prompt]').forEach(button => {
    button.addEventListener('click', () => {
        const textarea = document.querySelector('.sobhan-ai-panel textarea[name="question"]');
        if (textarea) textarea.value = button.dataset.dashboardPrompt || '';
    });
});
document.querySelector('.sobhan-ai-panel form')?.addEventListener('submit', event => {
    const button = event.currentTarget.querySelector('button');
    if (button) {
        button.disabled = true;
        button.textContent = event.currentTarget.dataset.loadingText || button.textContent;
    }
});
</script>
</div>
</section></main></div><script src="/assets/js/app.js"></script></body></html>
