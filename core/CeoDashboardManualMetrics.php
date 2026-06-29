<?php

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/JalaliDate.php';

class CeoDashboardManualMetrics
{
    public static function normalizePeriodKey($value): string
    {
        $value = JalaliDate::normalize(trim((string)$value));
        if ($value === '') return '';

        $date = JalaliDate::toGregorian($value);
        if ($date !== null) return $date;

        $value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value) ?? '';
        return function_exists('mb_substr') ? mb_substr($value, 0, 50, 'UTF-8') : substr($value, 0, 50);
    }

    public static function normalizeMoneyValue($value): float
    {
        $value = strtr(trim((string)$value), [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '٫' => '.',
        ]);
        $value = str_ireplace(['ریال', 'تومان', 'rial', 'toman', ',', '،', '٬', ' ', "\xC2\xA0"], '', $value);
        $value = preg_replace('/[^0-9.\-]/u', '', $value) ?? '';
        if ($value === '' || $value === '-' || !is_numeric($value)) return 0.0;
        return (float)$value;
    }

    public static function get(string $periodKey): ?array
    {
        $periodKey = self::normalizePeriodKey($periodKey);
        if ($periodKey === '') return null;
        return Database::fetch(
            'SELECT period_key,gross_sales,discounts,net_sales,source,uploaded_file_name,imported_by,imported_at,updated_at FROM ceo_dashboard_manual_metrics WHERE period_key = ? LIMIT 1',
            [$periodKey]
        );
    }

    public static function save(string $periodKey, $grossSales, $discounts, $netSales, ?string $fileName, ?int $userId): void
    {
        $periodKey = self::normalizePeriodKey($periodKey);
        if ($periodKey === '') throw new InvalidArgumentException('دوره گزارش الزامی است.');

        $fileName = trim(basename(str_replace('\\', '/', (string)$fileName)));
        if (function_exists('mb_substr')) $fileName = mb_substr($fileName, 0, 255, 'UTF-8');
        else $fileName = substr($fileName, 0, 255);

        Database::execute(
            'INSERT INTO ceo_dashboard_manual_metrics (period_key,gross_sales,discounts,net_sales,source,uploaded_file_name,imported_by,imported_at,updated_at)
             VALUES (?,?,?,? ,"excel_import",?,?,NOW(),NOW())
             ON DUPLICATE KEY UPDATE gross_sales=VALUES(gross_sales), discounts=VALUES(discounts), net_sales=VALUES(net_sales), source=VALUES(source), uploaded_file_name=VALUES(uploaded_file_name), imported_by=VALUES(imported_by), imported_at=NOW(), updated_at=NOW()',
            [
                $periodKey,
                self::normalizeMoneyValue($grossSales),
                self::normalizeMoneyValue($discounts),
                self::normalizeMoneyValue($netSales),
                $fileName !== '' ? $fileName : null,
                $userId && $userId > 0 ? $userId : null,
            ]
        );
    }

    public static function automaticForPeriod(string $periodKey): array
    {
        $periodKey = self::normalizePeriodKey($periodKey);
        $row = Database::fetch(
            'SELECT COALESCE(SUM(sales_amount), 0) gross_sales FROM ceo_dashboard_lines WHERE active = 1 AND report_date = ?',
            [$periodKey]
        );
        $grossSales = (float)($row['gross_sales'] ?? 0);
        $discounts = max(0, (float)setting('ceo_dashboard_discounts_amount', '0'));
        return [
            'period_key' => $periodKey,
            'gross_sales' => $grossSales,
            'discounts' => $discounts,
            'net_sales' => max(0, $grossSales - $discounts),
            'data_source' => 'محاسبه خودکار',
        ];
    }

    public static function exportForPeriod(string $periodKey): array
    {
        $manual = self::get($periodKey);
        if ($manual) {
            return [
                'period_key' => $manual['period_key'],
                'gross_sales' => $manual['gross_sales'],
                'discounts' => $manual['discounts'],
                'net_sales' => $manual['net_sales'],
                'data_source' => 'ورودی دستی از اکسل',
            ];
        }
        return self::automaticForPeriod($periodKey);
    }
}
