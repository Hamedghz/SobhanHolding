<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/CeoDashboardExcel.php';
require_once __DIR__ . '/../core/JalaliDate.php';
require_once __DIR__ . '/../core/CeoDashboardManualMetrics.php';

Auth::requirePermission('ceo_dashboard', 'view');
$pageTitle = 'تنظیمات داشبورد مدیرعامل';
$canManage = Auth::can('ceo_dashboard', 'edit') || Auth::can('ceo_dashboard', 'create') || Auth::can('ceo_dashboard', 'delete');
$canImport = $canManage;
$canExport = $canManage;
if (!$canManage) {
    http_response_code(403);
    echo 'دسترسی غیرمجاز';
    exit;
}

$settingFields = [
    'ceo_dashboard_page_title' => ['label' => 'عنوان اصلی صفحه', 'type' => 'text', 'default' => 'داشبورد مدیرعامل'],
    'ceo_dashboard_gross_sales_title' => ['label' => 'عنوان فروش ناخالص', 'type' => 'text', 'default' => 'فروش ناخالص'],
    'ceo_dashboard_discounts_title' => ['label' => 'عنوان تخفیفات', 'type' => 'text', 'default' => 'تخفیفات'],
    'ceo_dashboard_discount_percent_title' => ['label' => 'عنوان درصد تخفیف', 'type' => 'text', 'default' => 'درصد'],
    'ceo_dashboard_net_sales_title' => ['label' => 'عنوان فروش خالص', 'type' => 'text', 'default' => 'فروش خالص'],
    'ceo_dashboard_line_sales_chart_title' => ['label' => 'عنوان نمودار فروش لاین', 'type' => 'text', 'default' => 'ریال فروش لاین'],
    'ceo_dashboard_line_table_title' => ['label' => 'عنوان جدول اطلاعات لاین', 'type' => 'text', 'default' => 'اطلاعات لاین'],
    'ceo_dashboard_visitor_table_title' => ['label' => 'عنوان جدول ویزیتورها', 'type' => 'text', 'default' => 'اطلاعات ویزیتورها'],
    'ceo_dashboard_line_share_chart_title' => ['label' => 'عنوان نمودار سهم هر لاین', 'type' => 'text', 'default' => 'سهم فروش هر لاین'],
    'ceo_dashboard_line_achievement_chart_title' => ['label' => 'عنوان نمودار درصد تحقق لاین', 'type' => 'text', 'default' => 'درصد تحقق لاین'],
    'ceo_dashboard_visitor_achievement_chart_title' => ['label' => 'عنوان نمودار درصد تحقق ویزیتور', 'type' => 'text', 'default' => 'درصد تحقق ویزیتور'],
    'ceo_dashboard_discounts_amount' => ['label' => 'مبلغ تخفیفات', 'type' => 'number', 'default' => '0'],
    'ceo_dashboard_show_charts' => ['label' => 'نمایش نمودارها', 'type' => 'boolean', 'default' => '1'],
    'ceo_dashboard_show_line_table' => ['label' => 'نمایش جدول لاین‌ها', 'type' => 'boolean', 'default' => '1'],
    'ceo_dashboard_show_visitor_table' => ['label' => 'نمایش جدول ویزیتورها', 'type' => 'boolean', 'default' => '1'],
];

function ceo_setting_rows(array $settingFields): array
{
    $rows = [['key', 'title', 'value']];
    foreach ($settingFields as $key => $meta) {
        if ($meta['type'] === 'boolean') continue;
        $rows[] = [$key, $meta['label'], setting($key, $meta['default'])];
    }
    return $rows;
}

function ceo_summary_period_options(?bool &$hadError = null): array
{
    $hadError = false;
    $queries = [
        'SELECT period_key FROM ceo_dashboard_manual_metrics',
        'SELECT CAST(report_date AS CHAR) period_key FROM ceo_dashboard_lines WHERE report_date IS NOT NULL',
        'SELECT CAST(report_date AS CHAR) period_key FROM ceo_dashboard_visitors WHERE report_date IS NOT NULL',
        'SELECT CAST(report_date AS CHAR) period_key FROM pharmacy_dashboard_metrics WHERE report_date IS NOT NULL',
    ];
    $periods = [];
    foreach ($queries as $query) {
        try {
            foreach (Database::fetchAll($query) as $row) {
                $key = trim((string)($row['period_key'] ?? ''));
                if ($key !== '') $periods[$key] = $key;
            }
        } catch (Throwable $e) {
            $hadError = true;
            error_log('CEO dashboard period source: ' . $e->getMessage());
        }
    }
    $periods = array_values($periods);
    rsort($periods, SORT_STRING);
    return $periods;
}

function ceo_line_rows(bool $template = false): array
{
    $rows = [['تاریخ گزارش', 'کد لاین', 'عنوان لاین', 'مبلغ فروش لاین', 'قطعه', 'تارگت', 'تارگت مبلغی لاین', 'نام سرپرست', 'نام مدیر فروش', 'اتصال سرپرست به کاربر', 'اتصال مدیر فروش به کاربر', 'ترتیب نمایش', 'فعال']];
    if ($template) {
        $rows[] = [format_jalali_date(date('Y-m-d')), 'A', 'لاین A', '1000000', '100', '120', '1500000', 'سرپرست نمونه', 'مدیر فروش نمونه', '', '', '1', 'فعال'];
        return $rows;
    }
    foreach (Database::fetchAll('SELECT l.*, su.name supervisor_user_name, su.username supervisor_username, su.email supervisor_email, mu.name sales_manager_user_name, mu.username sales_manager_username, mu.email sales_manager_email FROM ceo_dashboard_lines l LEFT JOIN users su ON su.id = l.supervisor_user_id LEFT JOIN users mu ON mu.id = l.sales_manager_user_id ORDER BY COALESCE(l.report_date, "0000-00-00") DESC, l.sort_order ASC, l.id ASC') as $row) {
        $rows[] = [
            format_jalali_date($row['report_date']),
            $row['line_code'],
            $row['line_title'],
            $row['sales_amount'],
            $row['qty'],
            $row['target_qty'],
            $row['target_amount'],
            $row['supervisor_name'],
            $row['sales_manager_name'],
            ceo_user_label($row['supervisor_user_id'] ?? null, $row['supervisor_user_name'] ?? '', $row['supervisor_username'] ?? '', $row['supervisor_email'] ?? ''),
            ceo_user_label($row['sales_manager_user_id'] ?? null, $row['sales_manager_user_name'] ?? '', $row['sales_manager_username'] ?? '', $row['sales_manager_email'] ?? ''),
            $row['sort_order'],
            (int)$row['active'] === 1 ? 'فعال' : 'غیرفعال',
        ];
    }
    return $rows;
}

function ceo_visitor_rows(bool $template = false): array
{
    $rows = [['تاریخ گزارش', 'کد لاین', 'نام ویزیتور', 'مبلغ فروش ویزیتور', 'قطعه', 'تارگت', 'تارگت مبلغی ویزیتور', 'اتصال ویزیتور به کاربر', 'درصد تحقق', 'ترتیب نمایش', 'فعال']];
    if ($template) {
        $rows[] = [format_jalali_date(date('Y-m-d')), 'A', 'ویزیتور نمونه', '1000000', '100', '120', '1500000', '', '83.33%', '1', 'فعال'];
        return $rows;
    }
    foreach (Database::fetchAll('SELECT v.*, u.name user_name, u.username user_username, u.email user_email FROM ceo_dashboard_visitors v LEFT JOIN users u ON u.id = v.user_id ORDER BY COALESCE(v.report_date, "0000-00-00") DESC, v.sort_order ASC, v.id ASC') as $row) {
        $percent = (int)$row['target_qty'] > 0 ? ((int)$row['qty'] / (int)$row['target_qty']) * 100 : 0;
        $rows[] = [
            format_jalali_date($row['report_date']),
            $row['line_code'],
            $row['visitor_name'],
            $row['sales_amount'],
            $row['qty'],
            $row['target_qty'],
            $row['target_amount'],
            ceo_user_label($row['user_id'] ?? null, $row['user_name'] ?? '', $row['user_username'] ?? '', $row['user_email'] ?? ''),
            format_percent($percent, 2),
            $row['sort_order'],
            (int)$row['active'] === 1 ? 'فعال' : 'غیرفعال',
        ];
    }
    return $rows;
}

function ceo_export_file(array $sheets): void
{
    $fileName = 'ceo-dashboard-export-' . date('Y-m-d-H-i') . '.xlsx';
    CeoDashboardExcel::output($sheets, $fileName);
}

function ceo_summary_rows(bool $template = false, string $selectedPeriod = ''): array
{
    if ($template) {
        $period = CeoDashboardManualMetrics::normalizePeriodKey($selectedPeriod);
        if ($period === '') $period = date('Y-m-d');
        return [
            ['period_key', 'gross_sales', 'discounts', 'net_sales'],
            [$period, '1000000', '100000', '900000'],
        ];
    }

    $rows = [['دوره گزارش', 'فروش ناخالص', 'تخفیفات', 'فروش خالص', 'منبع داده']];
    $periods = [];
    if ($selectedPeriod !== '') {
        $periods[] = CeoDashboardManualMetrics::normalizePeriodKey($selectedPeriod);
    } else {
        $periods = ceo_summary_period_options();
    }
    foreach (array_unique(array_filter($periods)) as $period) {
        $metrics = CeoDashboardManualMetrics::exportForPeriod($period);
        $rows[] = [$metrics['period_key'], $metrics['gross_sales'], $metrics['discounts'], $metrics['net_sales'], $metrics['data_source']];
    }
    return $rows;
}

function ceo_read_csv(string $path): array
{
    $handle = fopen($path, 'rb');
    if ($handle === false) throw new RuntimeException('فایل CSV قابل خواندن نیست.');
    $sample = (string)fgets($handle);
    rewind($handle);
    $delimiters = [',' => substr_count($sample, ','), ';' => substr_count($sample, ';'), "\t" => substr_count($sample, "\t")];
    arsort($delimiters);
    $delimiter = (string)array_key_first($delimiters);
    if (($delimiters[$delimiter] ?? 0) === 0) $delimiter = ',';
    $rows = [];
    while (($row = fgetcsv($handle, 0, $delimiter)) !== false) $rows[] = $row;
    fclose($handle);
    return $rows;
}

function ceo_summary_import_rows(array $rows, string $selectedPeriod): array
{
    if (!$rows) throw new InvalidArgumentException('فایل انتخاب‌شده خالی است.');
    $aliases = [
        'period_key' => ['period_key', 'دوره گزارش'],
        'gross_sales' => ['gross_sales', 'فروش ناخالص'],
        'discounts' => ['discounts', 'تخفیفات'],
        'net_sales' => ['net_sales', 'فروش خالص'],
    ];
    $headers = [];
    foreach ($rows[0] as $index => $header) {
        $header = trim((string)$header);
        if ($index === 0) $header = preg_replace('/^\xEF\xBB\xBF/', '', $header) ?? $header;
        foreach ($aliases as $field => $names) {
            if (in_array($header, $names, true)) $headers[$field] = $index;
        }
    }
    foreach (['gross_sales', 'discounts', 'net_sales'] as $required) {
        if (!array_key_exists($required, $headers)) throw new InvalidArgumentException('ستون‌های فروش ناخالص، تخفیفات و فروش خالص در فایل پیدا نشد.');
    }

    $selectedPeriod = CeoDashboardManualMetrics::normalizePeriodKey($selectedPeriod);
    $items = [];
    $warnings = [];
    foreach (array_slice($rows, 1) as $offset => $row) {
        if (!array_filter($row, static fn($value) => trim((string)$value) !== '')) continue;
        $period = array_key_exists('period_key', $headers) ? CeoDashboardManualMetrics::normalizePeriodKey($row[$headers['period_key']] ?? '') : '';
        if ($period === '') $period = $selectedPeriod;
        if ($period === '') throw new InvalidArgumentException('دوره گزارش در ردیف ' . ($offset + 2) . ' مشخص نشده است.');
        $gross = CeoDashboardManualMetrics::normalizeMoneyValue($row[$headers['gross_sales']] ?? '');
        $discounts = CeoDashboardManualMetrics::normalizeMoneyValue($row[$headers['discounts']] ?? '');
        $net = CeoDashboardManualMetrics::normalizeMoneyValue($row[$headers['net_sales']] ?? '');
        if (abs($net - ($gross - $discounts)) > 0.009) {
            $warnings[] = 'مبلغ فروش خالص با فروش ناخالص منهای تخفیفات برابر نیست. مقدار واردشده به‌صورت دستی ثبت شد.';
        }
        $items[$period] = ['period_key' => $period, 'gross_sales' => $gross, 'discounts' => $discounts, 'net_sales' => $net];
    }
    if (!$items) throw new InvalidArgumentException('هیچ ردیف معتبری برای ثبت در فایل وجود ندارد.');
    return ['items' => array_values($items), 'warnings' => array_values(array_unique($warnings))];
}

function ceo_valid_date(?string $value): bool
{
    return trim((string)$value) === '' || JalaliDate::toGregorian($value) !== null;
}

function ceo_money_value($value): int
{
    $value = JalaliDate::normalize(strtr(trim((string)$value), ['،' => '', ',' => '', ' ' => '', '٬' => '']));
    return max(0, (int)$value);
}

function ceo_numeric_value_is_valid($value): bool
{
    $value = trim((string)$value);
    if ($value === '') return true;
    $value = JalaliDate::normalize(strtr($value, ['،' => '', ',' => '', ' ' => '', '٬' => '']));
    return preg_match('/^\d+$/', $value) === 1;
}

function ceo_validate_numeric_columns(array $row, array $columns, string $sheet, array &$errors): void
{
    foreach ($columns as $column) {
        if (!ceo_numeric_value_is_valid($row[$column] ?? '')) {
            $errors[] = ['sheet' => $sheet, 'row' => (int)$row['_row'], 'column' => $column, 'error' => 'مقدار باید عدد صحیح معتبر باشد.'];
        }
    }
}

function ceo_user_label($id, string $name, string $username, string $email): string
{
    if (!$id) return '';
    $label = trim($name) !== '' ? trim($name) : trim($username);
    $suffix = trim($username) !== '' ? trim($username) : trim($email);
    return trim((string)$id . ' - ' . $label . ($suffix !== '' ? ' (' . $suffix . ')' : ''));
}

function ceo_active_value($value): int
{
    $value = strtolower(trim((string)$value));
    return in_array($value, ['1', 'فعال', 'active', 'yes', 'true', 'on'], true) ? 1 : 0;
}

function ceo_resolve_user_id($value, array &$errors, string $sheet, int $row, string $column): ?int
{
    $value = trim((string)$value);
    if ($value === '') return null;
    if (preg_match('/^\s*(\d+)/', $value, $match)) {
        $user = Database::fetch('SELECT id FROM users WHERE id = ? LIMIT 1', [(int)$match[1]]);
        if ($user) return (int)$user['id'];
    }
    $identity = '';
    if (preg_match('/\(([^)]+)\)\s*$/u', $value, $match)) {
        $identity = trim($match[1]);
    }
    $clean = trim(preg_replace('/\s*\(.+\)\s*$/u', '', $value));
    $clean = trim(preg_replace('/^\d+\s*[-–]\s*/u', '', $clean));
    $user = Database::fetch('SELECT id FROM users WHERE username = ? OR email = ? OR name = ? OR username = ? OR email = ? LIMIT 1', [$value, $value, $clean, $identity, $identity]);
    if ($user) return (int)$user['id'];
    $errors[] = ['sheet' => $sheet, 'row' => $row, 'column' => $column, 'error' => 'کاربر «' . $value . '» پیدا نشد.'];
    return null;
}

function ceo_rows_to_assoc(array $rows, array $required, string $sheetName, array &$errors): array
{
    if (!$rows) {
        $errors[] = ['sheet' => $sheetName, 'row' => 1, 'column' => '-', 'error' => 'شیت خالی است.'];
        return [];
    }
    $headers = array_map('trim', $rows[0]);
    foreach ($required as $index => $column) {
        if (($headers[$index] ?? '') !== $column) {
            $errors[] = ['sheet' => $sheetName, 'row' => 1, 'column' => $column, 'error' => 'نام یا ترتیب ستون معتبر نیست.'];
        }
    }
    $items = [];
    foreach (array_slice($rows, 1) as $offset => $row) {
        if (!array_filter($row, static fn($value) => trim((string)$value) !== '')) continue;
        $item = [];
        foreach ($required as $index => $column) {
            $item[$column] = trim((string)($row[$index] ?? ''));
        }
        $item['_row'] = $offset + 2;
        $items[] = $item;
    }
    return $items;
}

function ceo_validate_import(array $workbook, array $settingFields): array
{
    $errors = [];
    $settings = [];
    $summaryMetrics = [];
    $summaryWarnings = [];
    $lineHeaders = ['تاریخ گزارش', 'کد لاین', 'عنوان لاین', 'مبلغ فروش لاین', 'قطعه', 'تارگت', 'تارگت مبلغی لاین', 'نام سرپرست', 'نام مدیر فروش', 'اتصال سرپرست به کاربر', 'اتصال مدیر فروش به کاربر', 'ترتیب نمایش', 'فعال'];
    $visitorHeaders = ['تاریخ گزارش', 'کد لاین', 'نام ویزیتور', 'مبلغ فروش ویزیتور', 'قطعه', 'تارگت', 'تارگت مبلغی ویزیتور', 'اتصال ویزیتور به کاربر', 'درصد تحقق', 'ترتیب نمایش', 'فعال'];
    $lines = array_key_exists('Lines', $workbook) ? ceo_rows_to_assoc($workbook['Lines'], $lineHeaders, 'Lines', $errors) : [];
    $visitors = array_key_exists('Visitors', $workbook) ? ceo_rows_to_assoc($workbook['Visitors'], $visitorHeaders, 'Visitors', $errors) : [];
    if (!array_key_exists('Lines', $workbook) && !array_key_exists('Visitors', $workbook)) {
        if (!array_key_exists('dashboard_summary', $workbook) && !array_key_exists('summary', $workbook)) {
            $errors[] = ['sheet' => '-', 'row' => 1, 'column' => '-', 'error' => 'حداقل یکی از شیت‌های Lines، Visitors یا dashboard_summary باید در فایل وجود داشته باشد.'];
        }
    }
    $summaryRows = $workbook['dashboard_summary'] ?? $workbook['summary'] ?? [];
    if ($summaryRows) {
        try {
            $summaryImport = ceo_summary_import_rows($summaryRows, '');
            $summaryMetrics = $summaryImport['items'];
            $summaryWarnings = $summaryImport['warnings'];
        } catch (InvalidArgumentException $e) {
            $errors[] = ['sheet' => 'dashboard_summary', 'row' => 1, 'column' => '-', 'error' => $e->getMessage()];
        }
    }

    $settingRows = $workbook['Settings'] ?? [];
    if ($settingRows) {
        $headers = array_map('trim', $settingRows[0]);
        foreach (['key', 'title', 'value'] as $index => $column) {
            if (($headers[$index] ?? '') !== $column) {
                $errors[] = ['sheet' => 'Settings', 'row' => 1, 'column' => $column, 'error' => 'نام یا ترتیب ستون معتبر نیست.'];
            }
        }
        foreach (array_slice($settingRows, 1) as $offset => $row) {
            if (!array_filter($row, static fn($value) => trim((string)$value) !== '')) continue;
            $key = trim((string)($row[0] ?? ''));
            $value = trim((string)($row[2] ?? ''));
            $rowNumber = $offset + 2;
            if ($key === '') {
                $errors[] = ['sheet' => 'Settings', 'row' => $rowNumber, 'column' => 'key', 'error' => 'کلید تنظیمات الزامی است.'];
            } elseif (substr($key, 0, 14) !== 'ceo_dashboard_') {
                $errors[] = ['sheet' => 'Settings', 'row' => $rowNumber, 'column' => 'key', 'error' => 'فقط کلیدهای ceo_dashboard_ مجاز هستند.'];
            } elseif (!isset($settingFields[$key])) {
                $errors[] = ['sheet' => 'Settings', 'row' => $rowNumber, 'column' => 'key', 'error' => 'این کلید در تنظیمات داشبورد تعریف نشده است.'];
            }
            if ($value === '') {
                $errors[] = ['sheet' => 'Settings', 'row' => $rowNumber, 'column' => 'value', 'error' => 'مقدار تنظیمات الزامی است.'];
            }
            if ($key !== '' && isset($settingFields[$key])) {
                $settings[$key] = $value;
            }
        }
    }

    foreach ($lines as $index => &$line) {
        $row = (int)$line['_row'];
        $blank = [];
        foreach ($lineHeaders as $column) $blank[$column] = trim((string)($line[$column] ?? '')) === '';
        if ($line['کد لاین'] === '') $errors[] = ['sheet' => 'Lines', 'row' => $row, 'column' => 'کد لاین', 'error' => 'کد لاین الزامی است.'];
        if (!ceo_valid_date($line['تاریخ گزارش'])) $errors[] = ['sheet' => 'Lines', 'row' => $row, 'column' => 'تاریخ گزارش', 'error' => 'تاریخ باید با قالب شمسی 1404/09/15 باشد.'];
        ceo_validate_numeric_columns($line, ['مبلغ فروش لاین', 'قطعه', 'تارگت', 'تارگت مبلغی لاین', 'ترتیب نمایش'], 'Lines', $errors);
        $line = [
            '_row' => $row,
            '_blank' => $blank,
            'report_date' => $blank['تاریخ گزارش'] ? null : JalaliDate::toGregorian($line['تاریخ گزارش']),
            'line_code' => $line['کد لاین'],
            'line_title' => $line['عنوان لاین'],
            'sales_amount' => ceo_money_value($line['مبلغ فروش لاین']),
            'qty' => ceo_money_value($line['قطعه']),
            'target_qty' => ceo_money_value($line['تارگت']),
            'target_amount' => ceo_money_value($line['تارگت مبلغی لاین']),
            'supervisor_name' => $line['نام سرپرست'],
            'sales_manager_name' => $line['نام مدیر فروش'],
            'supervisor_user_id' => ceo_resolve_user_id($line['اتصال سرپرست به کاربر'], $errors, 'Lines', $row, 'اتصال سرپرست به کاربر'),
            'sales_manager_user_id' => ceo_resolve_user_id($line['اتصال مدیر فروش به کاربر'], $errors, 'Lines', $row, 'اتصال مدیر فروش به کاربر'),
            'sort_order' => ceo_money_value($line['ترتیب نمایش']),
            'active' => ceo_active_value($line['فعال']),
        ];
    }
    unset($line);

    foreach ($visitors as $index => &$visitor) {
        $row = (int)$visitor['_row'];
        $blank = [];
        foreach ($visitorHeaders as $column) $blank[$column] = trim((string)($visitor[$column] ?? '')) === '';
        if ($visitor['کد لاین'] === '') $errors[] = ['sheet' => 'Visitors', 'row' => $row, 'column' => 'کد لاین', 'error' => 'کد لاین الزامی است.'];
        if ($visitor['نام ویزیتور'] === '') $errors[] = ['sheet' => 'Visitors', 'row' => $row, 'column' => 'نام ویزیتور', 'error' => 'نام ویزیتور الزامی است.'];
        if (!ceo_valid_date($visitor['تاریخ گزارش'])) $errors[] = ['sheet' => 'Visitors', 'row' => $row, 'column' => 'تاریخ گزارش', 'error' => 'تاریخ باید با قالب شمسی 1404/09/15 باشد.'];
        ceo_validate_numeric_columns($visitor, ['مبلغ فروش ویزیتور', 'قطعه', 'تارگت', 'تارگت مبلغی ویزیتور', 'ترتیب نمایش'], 'Visitors', $errors);
        $visitor = [
            '_row' => $row,
            '_blank' => $blank,
            'report_date' => $blank['تاریخ گزارش'] ? null : JalaliDate::toGregorian($visitor['تاریخ گزارش']),
            'line_code' => $visitor['کد لاین'],
            'visitor_name' => $visitor['نام ویزیتور'],
            'sales_amount' => ceo_money_value($visitor['مبلغ فروش ویزیتور']),
            'qty' => ceo_money_value($visitor['قطعه']),
            'target_qty' => ceo_money_value($visitor['تارگت']),
            'target_amount' => ceo_money_value($visitor['تارگت مبلغی ویزیتور']),
            'user_id' => ceo_resolve_user_id($visitor['اتصال ویزیتور به کاربر'], $errors, 'Visitors', $row, 'اتصال ویزیتور به کاربر'),
            'sort_order' => ceo_money_value($visitor['ترتیب نمایش']),
            'active' => ceo_active_value($visitor['فعال']),
        ];
    }
    unset($visitor);

    $knownLineCodes = [];
    foreach (Database::fetchAll('SELECT DISTINCT line_code FROM ceo_dashboard_lines WHERE line_code <> ""') as $existingLine) {
        $knownLineCodes[(string)$existingLine['line_code']] = true;
    }
    foreach ($lines as $line) {
        if ($line['line_code'] !== '') $knownLineCodes[(string)$line['line_code']] = true;
    }
    foreach ($visitors as $visitor) {
        if ($visitor['line_code'] !== '' && !isset($knownLineCodes[(string)$visitor['line_code']])) {
            $errors[] = ['sheet' => 'Visitors', 'row' => (int)$visitor['_row'], 'column' => 'کد لاین', 'error' => 'این کد لاین در اطلاعات لاین‌ها پیدا نشد.'];
        }
    }

    return ['settings' => $settings, 'lines' => $lines, 'visitors' => $visitors, 'summary_metrics' => $summaryMetrics, 'summary_warnings' => $summaryWarnings, 'errors' => $errors];
}

function ceo_preserve_blank(array $row, array $existing, string $field, string $column)
{
    return (($row['_blank'][$column] ?? false) && array_key_exists($field, $existing)) ? $existing[$field] : $row[$field];
}

function ceo_apply_import(array $preview): array
{
    global $settingFields;
    $result = ['inserted' => 0, 'updated' => 0, 'errors' => []];
    $pdo = Database::connection();
    $pdo->beginTransaction();
    try {
        foreach ($preview['settings'] as $key => $value) {
            $type = $settingFields[$key]['type'] ?? 'text';
            if ($type === 'number') $value = (string)max(0, (int)$value);
            if ($type === 'boolean') $value = $value === '1' ? '1' : '0';
            Database::execute('INSERT INTO site_settings (setting_key,setting_value,setting_type,updated_at) VALUES (?,?,?,NOW()) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value), setting_type=VALUES(setting_type), updated_at=NOW()', [$key, $value, $type]);
        }
        foreach ($preview['summary_metrics'] ?? [] as $metrics) {
            $existing = CeoDashboardManualMetrics::get($metrics['period_key']);
            $user = Auth::user();
            CeoDashboardManualMetrics::save($metrics['period_key'], $metrics['gross_sales'], $metrics['discounts'], $metrics['net_sales'], $preview['uploaded_file_name'] ?? null, (int)($user['id'] ?? 0));
            if ($existing) $result['updated']++;
            else $result['inserted']++;
        }
        $mode = $preview['mode'];
        if ($mode === 'truncate_and_insert') {
            Database::execute('DELETE FROM ceo_dashboard_lines');
            Database::execute('DELETE FROM ceo_dashboard_visitors');
        } elseif ($mode === 'replace_same_report_date') {
            $dates = [];
            foreach (array_merge($preview['lines'], $preview['visitors']) as $row) {
                $dates[$row['report_date'] ?? 'NULL'] = $row['report_date'] ?? null;
            }
            foreach ($dates as $date) {
                Database::execute('DELETE FROM ceo_dashboard_lines WHERE report_date <=> ?', [$date]);
                Database::execute('DELETE FROM ceo_dashboard_visitors WHERE report_date <=> ?', [$date]);
            }
        }
        foreach ($preview['lines'] as $line) {
            $existing = $mode === 'update_existing'
                ? Database::fetch('SELECT * FROM ceo_dashboard_lines WHERE report_date <=> ? AND line_code = ? LIMIT 1', [$line['report_date'], $line['line_code']])
                : null;
            if ($existing) {
                $data = [
                    $line['report_date'],
                    $line['line_code'],
                    ceo_preserve_blank($line, $existing, 'line_title', 'عنوان لاین'),
                    ceo_preserve_blank($line, $existing, 'sales_amount', 'مبلغ فروش لاین'),
                    ceo_preserve_blank($line, $existing, 'qty', 'قطعه'),
                    ceo_preserve_blank($line, $existing, 'target_qty', 'تارگت'),
                    ceo_preserve_blank($line, $existing, 'target_amount', 'تارگت مبلغی لاین'),
                    ceo_preserve_blank($line, $existing, 'supervisor_name', 'نام سرپرست'),
                    ceo_preserve_blank($line, $existing, 'sales_manager_name', 'نام مدیر فروش'),
                    ceo_preserve_blank($line, $existing, 'supervisor_user_id', 'اتصال سرپرست به کاربر'),
                    ceo_preserve_blank($line, $existing, 'sales_manager_user_id', 'اتصال مدیر فروش به کاربر'),
                    ceo_preserve_blank($line, $existing, 'sort_order', 'ترتیب نمایش'),
                    ceo_preserve_blank($line, $existing, 'active', 'فعال'),
                    (int)$existing['id'],
                ];
                Database::execute('UPDATE ceo_dashboard_lines SET report_date=?, line_code=?, line_title=?, sales_amount=?, qty=?, target_qty=?, target_amount=?, supervisor_name=?, sales_manager_name=?, supervisor_user_id=?, sales_manager_user_id=?, sort_order=?, active=?, updated_at=NOW() WHERE id=?', $data);
                $result['updated']++;
            } else {
                Database::execute('INSERT INTO ceo_dashboard_lines (report_date,line_code,line_title,sales_amount,qty,target_qty,target_amount,supervisor_name,sales_manager_name,supervisor_user_id,sales_manager_user_id,sort_order,active,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())', [$line['report_date'], $line['line_code'], $line['line_title'], (int)$line['sales_amount'], (int)$line['qty'], (int)$line['target_qty'], (int)$line['target_amount'], $line['supervisor_name'], $line['sales_manager_name'], $line['supervisor_user_id'], $line['sales_manager_user_id'], (int)$line['sort_order'], (int)$line['active']]);
                $result['inserted']++;
            }
        }
        foreach ($preview['visitors'] as $visitor) {
            $existing = $mode === 'update_existing'
                ? Database::fetch('SELECT * FROM ceo_dashboard_visitors WHERE report_date <=> ? AND line_code = ? AND visitor_name = ? LIMIT 1', [$visitor['report_date'], $visitor['line_code'], $visitor['visitor_name']])
                : null;
            if ($existing) {
                $data = [
                    $visitor['report_date'],
                    $visitor['line_code'],
                    $visitor['visitor_name'],
                    ceo_preserve_blank($visitor, $existing, 'target_qty', 'تارگت'),
                    ceo_preserve_blank($visitor, $existing, 'qty', 'قطعه'),
                    ceo_preserve_blank($visitor, $existing, 'target_amount', 'تارگت مبلغی ویزیتور'),
                    ceo_preserve_blank($visitor, $existing, 'sales_amount', 'مبلغ فروش ویزیتور'),
                    ceo_preserve_blank($visitor, $existing, 'user_id', 'اتصال ویزیتور به کاربر'),
                    ceo_preserve_blank($visitor, $existing, 'sort_order', 'ترتیب نمایش'),
                    ceo_preserve_blank($visitor, $existing, 'active', 'فعال'),
                    (int)$existing['id'],
                ];
                Database::execute('UPDATE ceo_dashboard_visitors SET report_date=?, line_code=?, visitor_name=?, target_qty=?, qty=?, target_amount=?, sales_amount=?, user_id=?, sort_order=?, active=?, updated_at=NOW() WHERE id=?', $data);
                $result['updated']++;
            } else {
                Database::execute('INSERT INTO ceo_dashboard_visitors (report_date,line_code,visitor_name,target_qty,qty,target_amount,sales_amount,user_id,sort_order,active,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,NOW(),NOW())', [$visitor['report_date'], $visitor['line_code'], $visitor['visitor_name'], (int)$visitor['target_qty'], (int)$visitor['qty'], (int)$visitor['target_amount'], (int)$visitor['sales_amount'], $visitor['user_id'], (int)$visitor['sort_order'], (int)$visitor['active']]);
                $result['inserted']++;
            }
        }
        $pdo->commit();
        return $result;
    } catch (Throwable $e) {
        $pdo->rollBack();
        error_log('CEO dashboard workbook import: ' . $e->getMessage());
        $result['errors'][] = 'ثبت اطلاعات با خطا روبه‌رو شد. لطفاً دوباره تلاش کنید.';
        return $result;
    }
}

try {
    if (isset($_GET['export'])) {
        if (!$canExport) throw new RuntimeException('برای خروجی اکسل دسترسی ندارید.');
        $type = $_GET['export'];
        $selectedPeriod = CeoDashboardManualMetrics::normalizePeriodKey($_GET['period_key'] ?? '');
        if ($type === 'full') ceo_export_file(['Settings' => ceo_setting_rows($settingFields), 'Lines' => ceo_line_rows(), 'Visitors' => ceo_visitor_rows(), 'dashboard_summary' => ceo_summary_rows(false, $selectedPeriod)]);
        if ($type === 'lines') ceo_export_file(['Lines' => ceo_line_rows()]);
        if ($type === 'visitors') ceo_export_file(['Visitors' => ceo_visitor_rows()]);
        if ($type === 'summary') ceo_export_file(['dashboard_summary' => ceo_summary_rows(false, $selectedPeriod)]);
        if ($type === 'summary_template') ceo_export_file(['dashboard_summary' => ceo_summary_rows(true, $selectedPeriod)]);
        if ($type === 'template') ceo_export_file(['Settings' => ceo_setting_rows($settingFields), 'Lines' => ceo_line_rows(true), 'Visitors' => ceo_visitor_rows(true), 'dashboard_summary' => ceo_summary_rows(true, $selectedPeriod)]);
        throw new RuntimeException('نوع خروجی معتبر نیست.');
    }
} catch (Throwable $e) {
    error_log('CEO dashboard export: ' . $e->getMessage());
    flash('ساخت فایل خروجی انجام نشد. لطفاً دوباره تلاش کنید.', 'danger');
    redirect('/admin/ceo-dashboard-settings.php#export');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('درخواست نامعتبر است.', 'danger');
        redirect('/admin/ceo-dashboard-settings.php');
    }
    if (!$canManage) {
        flash('برای این عملیات دسترسی ندارید.', 'danger');
        redirect('/admin/ceo-dashboard-settings.php');
    }

    if ($action === 'import_summary_metrics') {
        if (!$canImport) {
            flash('برای ورود اکسل دسترسی ندارید.', 'danger');
            redirect('/admin/ceo-dashboard-settings.php#summary-import');
        }
        $file = $_FILES['summary_file'] ?? [];
        $size = (int)($file['size'] ?? 0);
        $extension = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name']) || $size <= 0 || $size > 5 * 1024 * 1024 || !in_array($extension, ['csv', 'xlsx'], true)) {
            flash('فقط فایل معتبر CSV یا XLSX تا حجم ۵ مگابایت پذیرفته می‌شود.', 'danger');
            redirect('/admin/ceo-dashboard-settings.php#summary-import');
        }
        try {
            if ($extension === 'csv') {
                $rows = ceo_read_csv($file['tmp_name']);
            } else {
                $workbook = CeoDashboardExcel::read($file['tmp_name']);
                $rows = $workbook['dashboard_summary'] ?? $workbook['summary'] ?? [];
                if (!$rows && $workbook) $rows = reset($workbook);
            }
            $import = ceo_summary_import_rows($rows, (string)($_POST['period_key'] ?? ''));
            $pdo = Database::connection();
            $pdo->beginTransaction();
            try {
                $user = Auth::user();
                foreach ($import['items'] as $item) {
                    CeoDashboardManualMetrics::save($item['period_key'], $item['gross_sales'], $item['discounts'], $item['net_sales'], (string)$file['name'], (int)($user['id'] ?? 0));
                }
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $e;
            }
            Auth::start();
            $_SESSION['ceo_dashboard_summary_warnings'] = $import['warnings'];
            flash('اطلاعات فروش ناخالص، تخفیفات و فروش خالص با موفقیت از فایل اکسل ثبت شد.');
        } catch (InvalidArgumentException $e) {
            flash($e->getMessage(), 'danger');
        } catch (Throwable $e) {
            error_log('CEO dashboard summary import: ' . $e->getMessage());
            flash('ثبت اطلاعات فایل انجام نشد. لطفاً ساختار فایل را بررسی و دوباره تلاش کنید.', 'danger');
        }
        redirect('/admin/ceo-dashboard-settings.php#summary-import');
    }

    if ($action === 'save_settings') {
        foreach ($settingFields as $key => $meta) {
            $value = $meta['type'] === 'boolean' ? (!empty($_POST[$key]) ? '1' : '0') : trim((string)($_POST[$key] ?? ''));
            if ($meta['type'] === 'number') $value = (string)max(0, (int)$value);
            Database::execute('INSERT INTO site_settings (setting_key,setting_value,setting_type,updated_at) VALUES (?,?,?,NOW()) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value), setting_type=VALUES(setting_type), updated_at=NOW()', [$key, $value, $meta['type']]);
        }
        flash('تنظیمات داشبورد ذخیره شد.');
        redirect('/admin/ceo-dashboard-settings.php#general');
    }

    if ($action === 'save_line') {
        $id = (int)($_POST['id'] ?? 0);
        $lineCode = trim((string)($_POST['line_code'] ?? ''));
        if ($lineCode === '') {
            flash('کد لاین الزامی است.', 'danger');
            redirect('/admin/ceo-dashboard-settings.php#lines');
        }
        $reportDate = JalaliDate::toGregorian($_POST['report_date'] ?? '');
        if ($reportDate === null) {
            flash('تاریخ گزارش باید شمسی و معتبر باشد.', 'danger');
            redirect('/admin/ceo-dashboard-settings.php#lines');
        }
        $supervisorUserId = (int)($_POST['supervisor_user_id'] ?? 0);
        $salesManagerUserId = (int)($_POST['sales_manager_user_id'] ?? 0);
        $data = [
            $reportDate,
            $lineCode,
            trim((string)($_POST['line_title'] ?? '')),
            ceo_money_value($_POST['sales_amount'] ?? 0),
            max(0, (int)($_POST['qty'] ?? 0)),
            max(0, (int)($_POST['target_qty'] ?? 0)),
            ceo_money_value($_POST['target_amount'] ?? 0),
            trim((string)($_POST['supervisor_name'] ?? '')),
            trim((string)($_POST['sales_manager_name'] ?? '')),
            $supervisorUserId > 0 ? $supervisorUserId : null,
            $salesManagerUserId > 0 ? $salesManagerUserId : null,
            max(0, (int)($_POST['sort_order'] ?? 0)),
            !empty($_POST['active']) ? 1 : 0,
        ];
        if ($id) {
            Database::execute('UPDATE ceo_dashboard_lines SET report_date=?, line_code=?, line_title=?, sales_amount=?, qty=?, target_qty=?, target_amount=?, supervisor_name=?, sales_manager_name=?, supervisor_user_id=?, sales_manager_user_id=?, sort_order=?, active=?, updated_at=NOW() WHERE id=?', [...$data, $id]);
        } else {
            Database::execute('INSERT INTO ceo_dashboard_lines (report_date,line_code,line_title,sales_amount,qty,target_qty,target_amount,supervisor_name,sales_manager_name,supervisor_user_id,sales_manager_user_id,sort_order,active,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())', $data);
        }
        flash('اطلاعات لاین ذخیره شد.');
        redirect('/admin/ceo-dashboard-settings.php#lines');
    }

    if ($action === 'delete_line') {
        Database::execute('DELETE FROM ceo_dashboard_lines WHERE id = ?', [(int)($_POST['id'] ?? 0)]);
        flash('اطلاعات لاین حذف شد.');
        redirect('/admin/ceo-dashboard-settings.php#lines');
    }

    if ($action === 'save_visitor') {
        $id = (int)($_POST['id'] ?? 0);
        $lineCode = trim((string)($_POST['line_code'] ?? ''));
        $visitorName = trim((string)($_POST['visitor_name'] ?? ''));
        if ($lineCode === '' || $visitorName === '') {
            flash('کد لاین و نام ویزیتور الزامی است.', 'danger');
            redirect('/admin/ceo-dashboard-settings.php#visitors');
        }
        $reportDate = JalaliDate::toGregorian($_POST['report_date'] ?? '');
        if ($reportDate === null) {
            flash('تاریخ گزارش باید شمسی و معتبر باشد.', 'danger');
            redirect('/admin/ceo-dashboard-settings.php#visitors');
        }
        $userId = (int)($_POST['user_id'] ?? 0);
        $data = [
            $reportDate,
            $lineCode,
            $visitorName,
            max(0, (int)($_POST['target_qty'] ?? 0)),
            max(0, (int)($_POST['qty'] ?? 0)),
            ceo_money_value($_POST['target_amount'] ?? 0),
            ceo_money_value($_POST['sales_amount'] ?? 0),
            $userId > 0 ? $userId : null,
            max(0, (int)($_POST['sort_order'] ?? 0)),
            !empty($_POST['active']) ? 1 : 0,
        ];
        if ($id) {
            Database::execute('UPDATE ceo_dashboard_visitors SET report_date=?, line_code=?, visitor_name=?, target_qty=?, qty=?, target_amount=?, sales_amount=?, user_id=?, sort_order=?, active=?, updated_at=NOW() WHERE id=?', [...$data, $id]);
        } else {
            Database::execute('INSERT INTO ceo_dashboard_visitors (report_date,line_code,visitor_name,target_qty,qty,target_amount,sales_amount,user_id,sort_order,active,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,NOW(),NOW())', $data);
        }
        flash('اطلاعات ویزیتور ذخیره شد.');
        redirect('/admin/ceo-dashboard-settings.php#visitors');
    }

    if ($action === 'delete_visitor') {
        Database::execute('DELETE FROM ceo_dashboard_visitors WHERE id = ?', [(int)($_POST['id'] ?? 0)]);
        flash('اطلاعات ویزیتور حذف شد.');
        redirect('/admin/ceo-dashboard-settings.php#visitors');
    }

    if ($action === 'preview_import') {
        if (!$canImport) {
            flash('برای ورود اکسل دسترسی ندارید.', 'danger');
            redirect('/admin/ceo-dashboard-settings.php#import');
        }
        if (empty($_FILES['excel_file']['tmp_name']) || (int)($_FILES['excel_file']['size'] ?? 0) > 5 * 1024 * 1024) {
            flash('فایل اکسل معتبر نیست یا حجم آن بیش از حد مجاز است.', 'danger');
            redirect('/admin/ceo-dashboard-settings.php#import');
        }
        $ext = strtolower(pathinfo($_FILES['excel_file']['name'] ?? '', PATHINFO_EXTENSION));
        if ($ext !== 'xlsx') {
            flash('فقط فایل XLSX پذیرفته می‌شود.', 'danger');
            redirect('/admin/ceo-dashboard-settings.php#import');
        }
        try {
            $workbook = CeoDashboardExcel::read($_FILES['excel_file']['tmp_name']);
            $preview = ceo_validate_import($workbook, $settingFields);
            $preview['mode'] = in_array($_POST['import_mode'] ?? '', ['update_existing', 'replace_same_report_date', 'append', 'truncate_and_insert'], true) ? $_POST['import_mode'] : 'update_existing';
            $preview['uploaded_file_name'] = basename((string)($_FILES['excel_file']['name'] ?? ''));
            Auth::start();
            $_SESSION['ceo_dashboard_import_preview'] = $preview;
            flash('پیش‌نمایش ورود اکسل آماده شد.', $preview['errors'] ? 'danger' : 'success');
        } catch (Throwable $e) {
            error_log('CEO dashboard workbook preview: ' . $e->getMessage());
            flash('خواندن فایل اکسل انجام نشد. لطفاً فایل و ساختار شیت‌ها را بررسی کنید.', 'danger');
        }
        redirect('/admin/ceo-dashboard-settings.php#import');
    }

    if ($action === 'confirm_import') {
        Auth::start();
        $preview = $_SESSION['ceo_dashboard_import_preview'] ?? null;
        if (!$preview || !empty($preview['errors'])) {
            flash('پیش‌نمایش معتبر برای ثبت وجود ندارد.', 'danger');
            redirect('/admin/ceo-dashboard-settings.php#import');
        }
        try {
            $result = ceo_apply_import($preview);
            $_SESSION['ceo_dashboard_import_result'] = $result;
            $_SESSION['ceo_dashboard_summary_warnings'] = $preview['summary_warnings'] ?? [];
            unset($_SESSION['ceo_dashboard_import_preview']);
            $message = 'نتیجه ورود اکسل - جدید: ' . $result['inserted'] . '، بروزرسانی: ' . $result['updated'] . '، خطا: ' . count($result['errors']);
            flash($message, empty($result['errors']) ? 'success' : 'danger');
        } catch (Throwable $e) {
            error_log('CEO dashboard workbook confirmation: ' . $e->getMessage());
            flash('خطا در ثبت اطلاعات اکسل. لطفاً دوباره تلاش کنید.', 'danger');
        }
        redirect('/admin/ceo-dashboard-settings.php#import');
    }
}

$lineEdit = null;$visitorEdit = null;$lines = [];$visitors = [];$lineOptions = [];$userOptions = [];
try {
    $lineEdit = isset($_GET['line_edit']) ? Database::fetch('SELECT * FROM ceo_dashboard_lines WHERE id = ?', [(int)$_GET['line_edit']]) : null;
    $visitorEdit = isset($_GET['visitor_edit']) ? Database::fetch('SELECT * FROM ceo_dashboard_visitors WHERE id = ?', [(int)$_GET['visitor_edit']]) : null;
    $lines = Database::fetchAll('SELECT l.*, su.name supervisor_user_name, su.username supervisor_username, su.email supervisor_email, mu.name sales_manager_user_name, mu.username sales_manager_username, mu.email sales_manager_email FROM ceo_dashboard_lines l LEFT JOIN users su ON su.id = l.supervisor_user_id LEFT JOIN users mu ON mu.id = l.sales_manager_user_id ORDER BY COALESCE(l.report_date, "0000-00-00") DESC, l.sort_order ASC, l.id DESC');
    $visitors = Database::fetchAll('SELECT v.*, u.name user_name, u.username user_username, u.email user_email FROM ceo_dashboard_visitors v LEFT JOIN users u ON u.id = v.user_id ORDER BY COALESCE(v.report_date, "0000-00-00") DESC, v.sort_order ASC, v.id DESC');
    $lineOptions = array_column(Database::fetchAll('SELECT DISTINCT line_code FROM ceo_dashboard_lines WHERE line_code <> "" ORDER BY line_code ASC'), 'line_code');
    $userOptions = Database::fetchAll('SELECT id,name,username FROM users ORDER BY name ASC, username ASC, id ASC');
} catch (Throwable $e) {
    error_log('CEO dashboard settings data: ' . $e->getMessage());
    flash('دریافت اطلاعات لاین‌ها و ویزیتورها با خطا مواجه شد. لطفاً دوباره تلاش کنید.', 'danger');
}
$periodLoadFailed = false;
$summaryPeriodOptions = ceo_summary_period_options($periodLoadFailed);
if ($periodLoadFailed) flash('دریافت بخشی از دوره‌های گزارش با خطا مواجه شد؛ سایر اطلاعات قابل استفاده است.', 'danger');
$selectedSummaryPeriod = CeoDashboardManualMetrics::normalizePeriodKey($_GET['period_key'] ?? ($summaryPeriodOptions[0] ?? date('Y-m-d')));
Auth::start();
$importPreview = $_SESSION['ceo_dashboard_import_preview'] ?? null;
$importResult = $_SESSION['ceo_dashboard_import_result'] ?? null;
unset($_SESSION['ceo_dashboard_import_result']);
$summaryWarnings = $_SESSION['ceo_dashboard_summary_warnings'] ?? [];
unset($_SESSION['ceo_dashboard_summary_warnings']);

require __DIR__ . '/../views/partials/admin-header.php';
?>
<div class="ceo-settings-tabs">
    <a href="#general">تنظیمات عمومی</a>
    <a href="#lines">اطلاعات لاین‌ها</a>
    <a href="#visitors">اطلاعات ویزیتورها</a>
    <a href="#summary-import">شاخص‌های اصلی</a>
    <a href="#export">خروجی اکسل</a>
    <a href="#import">ورودی اکسل</a>
</div>

<section class="card ceo-settings-card" id="general">
    <h2>تنظیمات عمومی داشبورد</h2>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= e(Auth::csrfToken()) ?>">
        <input type="hidden" name="action" value="save_settings">
        <div class="grid grid-2">
            <?php foreach ($settingFields as $key => $meta): ?>
                <?php if ($meta['type'] === 'boolean'): ?>
                    <label class="checkbox-item"><input type="checkbox" name="<?= e($key) ?>" value="1" <?= setting($key, $meta['default']) === '1' ? 'checked' : '' ?>> <?= e($meta['label']) ?></label>
                <?php else: ?>
                    <label class="form-field">
                        <span><?= e($meta['label']) ?></span>
                        <input <?= $meta['type'] === 'number' ? 'type="number" min="0" step="1"' : ($meta['type'] === 'color' ? 'type="color"' : 'type="text"') ?> name="<?= e($key) ?>" value="<?= e(setting($key, $meta['default'])) ?>">
                    </label>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <div class="form-actions"><button class="btn btn-primary">ذخیره تنظیمات</button><a class="btn" href="/admin/ceo-dashboard.php">مشاهده داشبورد</a></div>
    </form>
</section>

<section class="card ceo-settings-card" id="lines">
    <h2>اطلاعات لاین‌ها</h2>
    <form method="post" class="admin-form">
        <input type="hidden" name="csrf_token" value="<?= e(Auth::csrfToken()) ?>">
        <input type="hidden" name="action" value="save_line">
        <input type="hidden" name="id" value="<?= e($lineEdit['id'] ?? '') ?>">
        <div class="grid grid-3">
            <label class="form-field"><span>تاریخ گزارش</span><input class="jalali-date-input" name="report_date" inputmode="numeric" placeholder="1404/09/15" value="<?= e(jalali_input_value($lineEdit['report_date'] ?? date('Y-m-d'))) ?>" required></label>
            <label class="form-field"><span>کد لاین</span><input name="line_code" maxlength="10" value="<?= e($lineEdit['line_code'] ?? '') ?>" required></label>
            <label class="form-field"><span>عنوان لاین</span><input name="line_title" maxlength="100" value="<?= e($lineEdit['line_title'] ?? '') ?>"></label>
            <label class="form-field"><span>مبلغ فروش لاین</span><input type="number" min="0" step="1" name="sales_amount" value="<?= e($lineEdit['sales_amount'] ?? '0') ?>" required></label>
            <label class="form-field"><span>قطعه</span><input type="number" min="0" step="1" name="qty" value="<?= e($lineEdit['qty'] ?? '0') ?>" required></label>
            <label class="form-field"><span>تارگت</span><input type="number" min="0" step="1" name="target_qty" value="<?= e($lineEdit['target_qty'] ?? '0') ?>" required></label>
            <label class="form-field"><span>تارگت مبلغی لاین</span><input type="number" min="0" step="1" name="target_amount" value="<?= e($lineEdit['target_amount'] ?? '0') ?>"></label>
            <label class="form-field"><span>نام سرپرست</span><input name="supervisor_name" maxlength="150" value="<?= e($lineEdit['supervisor_name'] ?? '') ?>"></label>
            <label class="form-field"><span>نام مدیر فروش</span><input name="sales_manager_name" maxlength="150" value="<?= e($lineEdit['sales_manager_name'] ?? '') ?>"></label>
            <label class="form-field"><span>اتصال سرپرست به کاربر</span><select name="supervisor_user_id"><option value="0">بدون اتصال</option><?php foreach ($userOptions as $user): ?><option value="<?= e($user['id']) ?>" <?= (int)($lineEdit['supervisor_user_id'] ?? 0) === (int)$user['id'] ? 'selected' : '' ?>><?= e($user['name'] . ' (' . $user['username'] . ')') ?></option><?php endforeach; ?></select></label>
            <label class="form-field"><span>اتصال مدیر فروش به کاربر</span><select name="sales_manager_user_id"><option value="0">بدون اتصال</option><?php foreach ($userOptions as $user): ?><option value="<?= e($user['id']) ?>" <?= (int)($lineEdit['sales_manager_user_id'] ?? 0) === (int)$user['id'] ? 'selected' : '' ?>><?= e($user['name'] . ' (' . $user['username'] . ')') ?></option><?php endforeach; ?></select></label>
            <label class="form-field"><span>ترتیب نمایش</span><input type="number" min="0" step="1" name="sort_order" value="<?= e($lineEdit['sort_order'] ?? '0') ?>"></label>
            <label class="checkbox-item"><input type="checkbox" name="active" value="1" <?= (int)($lineEdit['active'] ?? 1) === 1 ? 'checked' : '' ?>> فعال</label>
        </div>
        <div class="form-actions"><button class="btn btn-primary">ذخیره لاین</button><a class="btn" href="/admin/ceo-dashboard-settings.php#lines">جدید</a></div>
    </form>
    <div class="table-wrap ceo-table-wrap">
        <table>
            <thead><tr><th>تاریخ گزارش</th><th>کد لاین</th><th>عنوان لاین</th><th>مبلغ فروش لاین</th><th>قطعه</th><th>تارگت</th><th>تارگت مبلغی لاین</th><th>نام سرپرست</th><th>نام مدیر فروش</th><th>اتصال سرپرست به کاربر</th><th>اتصال مدیر فروش به کاربر</th><th>درصد تحقق</th><th>ترتیب نمایش</th><th>فعال</th><th>عملیات</th></tr></thead>
            <tbody>
            <?php foreach ($lines as $item): $lineTargetQty = (int)$item['target_qty']; $lineQty = (int)$item['qty']; $percent = $lineTargetQty > 0 ? ($lineQty / $lineTargetQty) * 100 : 0; ?>
                <tr>
                    <td><?= e(format_jalali_date($item['report_date'])) ?></td><td><?= e($item['line_code']) ?></td><td><?= e($item['line_title']) ?></td><td><?= e(format_money($item['sales_amount'])) ?></td><td><?= e(format_number($item['qty'])) ?></td><td><?= e(format_number($item['target_qty'])) ?></td><td><?= e(format_money($item['target_amount'])) ?></td><td><?= e($item['supervisor_name'] ?: '-') ?></td><td><?= e($item['sales_manager_name'] ?: '-') ?></td><td><?= e(ceo_user_label($item['supervisor_user_id'] ?? null, $item['supervisor_user_name'] ?? '', $item['supervisor_username'] ?? '', $item['supervisor_email'] ?? '') ?: '-') ?></td><td><?= e(ceo_user_label($item['sales_manager_user_id'] ?? null, $item['sales_manager_user_name'] ?? '', $item['sales_manager_username'] ?? '', $item['sales_manager_email'] ?? '') ?: '-') ?></td><td><?= e(format_percent($percent, 2)) ?></td><td><?= e($item['sort_order']) ?></td><td><?= (int)$item['active'] === 1 ? 'فعال' : 'غیرفعال' ?></td>
                    <td class="actions"><a class="btn btn-small" href="?line_edit=<?= e($item['id']) ?>#lines">ویرایش</a><form class="inline-form" method="post" onsubmit="return confirm('حذف شود؟')"><input type="hidden" name="csrf_token" value="<?= e(Auth::csrfToken()) ?>"><input type="hidden" name="action" value="delete_line"><input type="hidden" name="id" value="<?= e($item['id']) ?>"><button class="btn btn-small btn-danger">حذف</button></form></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="card ceo-settings-card" id="visitors">
    <h2>اطلاعات ویزیتورها</h2>
    <form method="post" class="admin-form">
        <input type="hidden" name="csrf_token" value="<?= e(Auth::csrfToken()) ?>">
        <input type="hidden" name="action" value="save_visitor">
        <input type="hidden" name="id" value="<?= e($visitorEdit['id'] ?? '') ?>">
        <div class="grid grid-3">
            <label class="form-field"><span>تاریخ گزارش</span><input class="jalali-date-input" name="report_date" inputmode="numeric" placeholder="1404/09/15" value="<?= e(jalali_input_value($visitorEdit['report_date'] ?? date('Y-m-d'))) ?>" required></label>
            <label class="form-field"><span>کد لاین</span><input name="line_code" list="ceoLineCodes" maxlength="10" value="<?= e($visitorEdit['line_code'] ?? '') ?>" required></label>
            <datalist id="ceoLineCodes"><?php foreach ($lineOptions as $lineCode): ?><option value="<?= e($lineCode) ?>"></option><?php endforeach; ?></datalist>
            <label class="form-field"><span>نام ویزیتور</span><input name="visitor_name" maxlength="150" value="<?= e($visitorEdit['visitor_name'] ?? '') ?>" required></label>
            <label class="form-field"><span>تارگت</span><input type="number" min="0" step="1" name="target_qty" value="<?= e($visitorEdit['target_qty'] ?? '0') ?>" required></label>
            <label class="form-field"><span>قطعه</span><input type="number" min="0" step="1" name="qty" value="<?= e($visitorEdit['qty'] ?? '0') ?>" required></label>
            <label class="form-field"><span>تارگت مبلغی ویزیتور</span><input type="number" min="0" step="1" name="target_amount" value="<?= e($visitorEdit['target_amount'] ?? '0') ?>"></label>
            <label class="form-field"><span>مبلغ فروش ویزیتور</span><input type="number" min="0" step="1" name="sales_amount" value="<?= e($visitorEdit['sales_amount'] ?? '0') ?>"></label>
            <label class="form-field"><span>اتصال ویزیتور به کاربر</span><select name="user_id"><option value="0">بدون اتصال</option><?php foreach ($userOptions as $user): ?><option value="<?= e($user['id']) ?>" <?= (int)($visitorEdit['user_id'] ?? 0) === (int)$user['id'] ? 'selected' : '' ?>><?= e($user['name'] . ' (' . $user['username'] . ')') ?></option><?php endforeach; ?></select></label>
            <label class="form-field"><span>ترتیب نمایش</span><input type="number" min="0" step="1" name="sort_order" value="<?= e($visitorEdit['sort_order'] ?? '0') ?>"></label>
            <label class="checkbox-item"><input type="checkbox" name="active" value="1" <?= (int)($visitorEdit['active'] ?? 1) === 1 ? 'checked' : '' ?>> فعال</label>
        </div>
        <div class="form-actions"><button class="btn btn-primary">ذخیره ویزیتور</button><a class="btn" href="/admin/ceo-dashboard-settings.php#visitors">جدید</a></div>
    </form>
    <div class="table-wrap ceo-table-wrap">
        <table>
            <thead><tr><th>تاریخ گزارش</th><th>کد لاین</th><th>نام ویزیتور</th><th>مبلغ فروش ویزیتور</th><th>قطعه</th><th>تارگت</th><th>تارگت مبلغی ویزیتور</th><th>اتصال ویزیتور به کاربر</th><th>درصد تحقق</th><th>ترتیب نمایش</th><th>فعال</th><th>عملیات</th></tr></thead>
            <tbody>
            <?php foreach ($visitors as $item): $visitorTargetQty = (int)$item['target_qty']; $visitorQty = (int)$item['qty']; $percent = $visitorTargetQty > 0 ? ($visitorQty / $visitorTargetQty) * 100 : 0; ?>
                <tr>
                    <td><?= e(format_jalali_date($item['report_date'])) ?></td><td><?= e($item['line_code']) ?></td><td><?= e($item['visitor_name']) ?></td><td><?= e(format_money($item['sales_amount'])) ?></td><td><?= e(format_number($item['qty'])) ?></td><td><?= e(format_number($item['target_qty'])) ?></td><td><?= e(format_money($item['target_amount'])) ?></td><td><?= e(ceo_user_label($item['user_id'] ?? null, $item['user_name'] ?? '', $item['user_username'] ?? '', $item['user_email'] ?? '') ?: '-') ?></td><td><?= e(format_percent($percent, 2)) ?></td><td><?= e($item['sort_order']) ?></td><td><?= (int)$item['active'] === 1 ? 'فعال' : 'غیرفعال' ?></td>
                    <td class="actions"><a class="btn btn-small" href="?visitor_edit=<?= e($item['id']) ?>#visitors">ویرایش</a><form class="inline-form" method="post" onsubmit="return confirm('حذف شود؟')"><input type="hidden" name="csrf_token" value="<?= e(Auth::csrfToken()) ?>"><input type="hidden" name="action" value="delete_visitor"><input type="hidden" name="id" value="<?= e($item['id']) ?>"><button class="btn btn-small btn-danger">حذف</button></form></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="card ceo-settings-card" id="summary-import">
    <h2>ورود دستی شاخص‌های اصلی مدیرعامل از اکسل</h2>
    <?php foreach ($summaryWarnings as $warning): ?>
        <div class="alert alert-warning"><?= e($warning) ?></div>
    <?php endforeach; ?>
    <form method="post" enctype="multipart/form-data" class="admin-form">
        <input type="hidden" name="csrf_token" value="<?= e(Auth::csrfToken()) ?>">
        <input type="hidden" name="action" value="import_summary_metrics">
        <div class="grid grid-2">
            <label class="form-field">
                <span>دوره گزارش</span>
                <select name="period_key" required>
                    <?php if (!in_array($selectedSummaryPeriod, $summaryPeriodOptions, true)): ?><option value="<?= e($selectedSummaryPeriod) ?>"><?= e(format_jalali_date($selectedSummaryPeriod)) ?></option><?php endif; ?>
                    <?php foreach ($summaryPeriodOptions as $period): ?><option value="<?= e($period) ?>" <?= $selectedSummaryPeriod === $period ? 'selected' : '' ?>><?= e(format_jalali_date($period)) ?></option><?php endforeach; ?>
                </select>
            </label>
            <label class="form-field">
                <span>فایل CSV یا XLSX</span>
                <input type="file" name="summary_file" accept=".csv,.xlsx" required>
            </label>
        </div>
        <div class="form-actions">
            <a class="btn" href="?export=summary_template&amp;period_key=<?= e(urlencode($selectedSummaryPeriod)) ?>">دریافت قالب اکسل</a>
            <button class="btn btn-primary">آپلود فایل اکسل</button>
            <a class="btn" href="?export=summary&amp;period_key=<?= e(urlencode($selectedSummaryPeriod)) ?>">خروجی اکسل</a>
        </div>
    </form>
    <p class="muted">در صورت ورود دستی این مقادیر، فروش ناخالص، تخفیفات و فروش خالص داشبورد مدیرعامل از فایل اکسل خوانده می‌شود و محاسبه خودکار فقط زمانی استفاده می‌شود که برای دوره انتخاب‌شده داده دستی وجود نداشته باشد.</p>
</section>

<section class="card ceo-settings-card" id="export">
    <h2>خروجی اکسل</h2>
    <div class="form-actions">
        <a class="btn btn-primary" href="?export=full">خروجی کامل اکسل</a>
        <a class="btn" href="?export=lines">خروجی فقط اطلاعات لاین‌ها</a>
        <a class="btn" href="?export=visitors">خروجی فقط اطلاعات ویزیتورها</a>
        <a class="btn" href="?export=template">دانلود فایل نمونه ورودی</a>
    </div>
</section>

<section class="card ceo-settings-card" id="import">
    <h2>ورودی اکسل</h2>
    <form method="post" enctype="multipart/form-data" class="admin-form">
        <input type="hidden" name="csrf_token" value="<?= e(Auth::csrfToken()) ?>">
        <input type="hidden" name="action" value="preview_import">
        <div class="grid grid-2">
            <label class="form-field"><span>حالت ورود اطلاعات</span><select name="import_mode"><option value="update_existing">بروزرسانی رکوردهای موجود و حفظ مقدار ستون‌های خالی</option><option value="replace_same_report_date">جایگزینی اطلاعات همان تاریخ گزارش</option><option value="append">افزودن به اطلاعات موجود</option><option value="truncate_and_insert">حذف همه و ثبت مجدد</option></select></label>
            <label class="form-field"><span>فایل اکسل</span><input type="file" name="excel_file" accept=".xlsx" required></label>
        </div>
        <button class="btn btn-primary">بررسی و پیش‌نمایش</button>
    </form>
    <?php if ($importResult): ?>
        <div class="stats">
            <div class="stat-card"><span>رکورد جدید</span><strong><?= e((string)$importResult['inserted']) ?></strong></div>
            <div class="stat-card"><span>بروزرسانی‌شده</span><strong><?= e((string)$importResult['updated']) ?></strong></div>
            <div class="stat-card"><span>خطای اجرا</span><strong><?= e((string)count($importResult['errors'] ?? [])) ?></strong></div>
        </div>
        <?php if (!empty($importResult['errors'])): ?>
            <div class="table-wrap"><table><thead><tr><th>خطا</th></tr></thead><tbody><?php foreach ($importResult['errors'] as $error): ?><tr><td><?= e($error) ?></td></tr><?php endforeach; ?></tbody></table></div>
        <?php endif; ?>
    <?php endif; ?>
    <?php if ($importPreview): ?>
        <div class="stats">
            <div class="stat-card"><span>تنظیمات</span><strong><?= e((string)count($importPreview['settings'])) ?></strong></div>
            <div class="stat-card"><span>ردیف لاین</span><strong><?= e((string)count($importPreview['lines'])) ?></strong></div>
            <div class="stat-card"><span>ردیف ویزیتور</span><strong><?= e((string)count($importPreview['visitors'])) ?></strong></div>
            <div class="stat-card"><span>شاخص‌های اصلی</span><strong><?= e((string)count($importPreview['summary_metrics'] ?? [])) ?></strong></div>
            <div class="stat-card"><span>خطا</span><strong><?= e((string)count($importPreview['errors'])) ?></strong></div>
        </div>
        <?php if (!empty($importPreview['errors'])): ?>
            <div class="table-wrap"><table><thead><tr><th>Sheet</th><th>Row</th><th>Column</th><th>Error</th></tr></thead><tbody><?php foreach ($importPreview['errors'] as $error): ?><tr><td><?= e($error['sheet']) ?></td><td><?= e((string)$error['row']) ?></td><td><?= e($error['column']) ?></td><td><?= e($error['error']) ?></td></tr><?php endforeach; ?></tbody></table></div>
        <?php else: ?>
            <form method="post"><input type="hidden" name="csrf_token" value="<?= e(Auth::csrfToken()) ?>"><input type="hidden" name="action" value="confirm_import"><button class="btn btn-primary">تایید و ثبت اطلاعات</button></form>
        <?php endif; ?>
    <?php endif; ?>
</section>
</section></main></div><script src="/assets/js/app.js"></script></body></html>
