<?php
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../services/ReportingViewRepository.php';

final class OkrDataSourceRegistry
{
    public static function definitions(): array
    {
        return [
            'sales_net_amount' => [
                'label' => 'فروش خالص',
                'description' => 'جمع فروش خالص مالک KR از نمای گزارش فروش',
                'view' => 'vw_sales_by_period',
                'metric' => 'net_sales_amount',
                'owner_column' => 'visitor_user_id',
                'filters' => ['period_key', 'line_code'],
            ],
            'sales_net_quantity' => [
                'label' => 'تعداد خالص فروش',
                'description' => 'جمع تعداد خالص فروش مالک KR',
                'view' => 'vw_sales_by_period',
                'metric' => 'net_quantity',
                'owner_column' => 'visitor_user_id',
                'filters' => ['period_key', 'line_code'],
            ],
            'sales_invoice_count' => [
                'label' => 'تعداد فاکتور فروش',
                'description' => 'جمع تعداد فاکتورهای مالک KR',
                'view' => 'vw_sales_by_period',
                'metric' => 'invoice_count',
                'owner_column' => 'visitor_user_id',
                'filters' => ['period_key', 'line_code'],
            ],
            'attendance_work_minutes' => [
                'label' => 'دقایق کارکرد',
                'description' => 'جمع کارکرد ثبت‌شده مالک KR در دوره حضور و غیاب',
                'view' => 'vw_attendance_period_summary',
                'metric' => 'work_minutes',
                'owner_column' => 'employee_id',
                'filters' => ['year', 'month'],
            ],
            'attendance_absent_days' => [
                'label' => 'روزهای غیبت',
                'description' => 'جمع روزهای غیبت مالک KR',
                'view' => 'vw_attendance_period_summary',
                'metric' => 'absent_days',
                'owner_column' => 'employee_id',
                'filters' => ['year', 'month'],
            ],
            'action_count' => [
                'label' => 'تعداد اقدامات',
                'description' => 'جمع اقدامات تخصیص‌یافته به مالک KR',
                'view' => 'vw_action_workload',
                'metric' => 'action_count',
                'owner_column' => 'user_id',
                'filters' => ['status', 'priority'],
            ],
            'action_overdue_count' => [
                'label' => 'اقدامات سررسیدگذشته',
                'description' => 'جمع اقدامات عقب‌افتاده مالک KR',
                'view' => 'vw_action_workload',
                'metric' => 'overdue_count',
                'owner_column' => 'user_id',
                'filters' => ['status', 'priority'],
            ],
            'kpi_average_score' => [
                'label' => 'میانگین امتیاز KPI',
                'description' => 'میانگین امتیاز KPI مالک KR با فیلترهای اختیاری',
                'metric' => 'score',
                'filters' => ['kpi_period_id', 'kpi_template_id', 'kpi_criteria_id'],
            ],
        ];
    }

    public static function definition(string $key): array
    {
        $definitions = self::definitions();
        if (!isset($definitions[$key])) {
            throw new InvalidArgumentException('منبع داده انتخاب‌شده برای OKR مجاز نیست.');
        }
        return $definitions[$key];
    }

    public static function configFromInput(array $input): array
    {
        $key = trim((string)($input['data_source_key'] ?? ''));
        $definition = self::definition($key);
        $config = ['source_key' => $key];
        foreach ($definition['filters'] as $filter) {
            $value = self::filterValue($filter, $input['source_' . $filter] ?? null);
            if ($value !== null) {
                $config[$filter] = $value;
            }
        }
        return $config;
    }

    public static function calculate(array $config, array $keyResult, array $actor): array
    {
        $sourceKey = trim((string)($config['source_key'] ?? ''));
        $definition = self::definition($sourceKey);
        $ownerId = (int)($keyResult['owner_user_id'] ?? 0);
        if ($ownerId <= 0) {
            throw new InvalidArgumentException('مالک معتبر برای محاسبه خودکار KR یافت نشد.');
        }

        if ($sourceKey === 'kpi_average_score') {
            return self::calculateKpi($definition, $config, $ownerId);
        }

        $filters = [$definition['owner_column'] => $ownerId];
        foreach ($definition['filters'] as $filter) {
            if (array_key_exists($filter, $config)) {
                $value = self::filterValue($filter, $config[$filter]);
                if ($value !== null) {
                    $filters[$filter] = $value;
                }
            }
        }
        $rows = ReportingViewRepository::fetch($definition['view'], $actor, $filters, 2000);
        return self::result($definition, self::sumRows($rows, $definition['metric']), count($rows));
    }

    public static function displayLabel(?string $json): string
    {
        $config = self::decodeConfig($json);
        if (!$config) return 'منبع خودکار نامعتبر';
        try {
            return (string)self::definition((string)$config['source_key'])['label'];
        } catch (Throwable) {
            return 'منبع خودکار نامعتبر';
        }
    }

    public static function decodeConfig(?string $json): array
    {
        if (!$json) return [];
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }

    public static function sumRows(array $rows, string $metric): float
    {
        $sum = 0.0;
        foreach ($rows as $row) {
            $value = $row[$metric] ?? 0;
            if (is_numeric($value)) $sum += (float)$value;
        }
        return round($sum, 4);
    }

    private static function calculateKpi(array $definition, array $config, int $ownerId): array
    {
        $where = ['employee_id=?'];
        $params = [$ownerId];
        $columns = [
            'kpi_period_id' => 'period_id',
            'kpi_template_id' => 'template_id',
            'kpi_criteria_id' => 'criteria_id',
        ];
        foreach ($columns as $filter => $column) {
            $value = self::filterValue($filter, $config[$filter] ?? null);
            if ($value !== null) {
                $where[] = $column . '=?';
                $params[] = $value;
            }
        }
        $row = Database::fetch(
            'SELECT AVG(score) calculated_value,COUNT(*) row_count FROM hr_kpi_scores WHERE ' . implode(' AND ', $where),
            $params
        ) ?: [];
        return self::result($definition, round((float)($row['calculated_value'] ?? 0), 4), (int)($row['row_count'] ?? 0));
    }

    private static function result(array $definition, float $value, int $rowCount): array
    {
        return [
            'value' => $value,
            'row_count' => $rowCount,
            'label' => (string)$definition['label'],
            'description' => (string)$definition['description'],
        ];
    }

    private static function filterValue(string $filter, mixed $value): string|int|null
    {
        $value = trim((string)$value);
        if ($value === '') return null;
        if (in_array($filter, ['year', 'month', 'kpi_period_id', 'kpi_template_id', 'kpi_criteria_id'], true)) {
            $number = (int)$value;
            if ($number <= 0) return null;
            if ($filter === 'year' && ($number < 2000 || $number > 2100)) throw new InvalidArgumentException('سال میلادی منبع داده معتبر نیست.');
            if ($filter === 'month' && ($number < 1 || $number > 12)) throw new InvalidArgumentException('ماه منبع داده معتبر نیست.');
            return $number;
        }
        if ($filter === 'period_key' && !preg_match('/^[0-9]{4}-[0-9]{2}$/', $value)) {
            throw new InvalidArgumentException('کلید دوره فروش باید مانند 2026-07 باشد.');
        }
        if ($filter === 'line_code' && !preg_match('/^[A-Za-z0-9_-]{1,50}$/', $value)) {
            throw new InvalidArgumentException('کد لاین فروش معتبر نیست.');
        }
        if ($filter === 'status' && !preg_match('/^[a-z][a-z0-9_-]{0,29}$/', $value)) {
            throw new InvalidArgumentException('وضعیت اقدام معتبر نیست.');
        }
        if ($filter === 'priority' && !in_array($value, ['low', 'normal', 'high', 'urgent'], true)) {
            throw new InvalidArgumentException('اولویت اقدام معتبر نیست.');
        }
        return mb_substr($value, 0, 50);
    }
}
