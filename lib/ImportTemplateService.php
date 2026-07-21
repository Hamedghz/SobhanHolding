<?php

require_once __DIR__ . '/ImportSourceRegistry.php';

final class ImportTemplateService
{
    public static function workbook(array $sourceKeys): array
    {
        $sheets = [];
        $guideRows = [['منبع', 'ستون فایل', 'کلید داخلی', 'الزامی', 'نوع داده']];

        foreach (array_values(array_unique($sourceKeys)) as $sourceKey) {
            $source = ImportSourceRegistry::get($sourceKey);
            $columns = self::columns($source);
            if (!$columns) continue;

            $headers = array_column($columns, 'source_header');
            $examples = array_map(
                static fn(array $column): string => self::exampleValue((string)$column['normalized_key'], (string)$column['data_type']),
                $columns
            );
            $sheetName = self::sheetName((string)$source['title'], $sourceKey, array_keys($sheets));
            $sheets[$sheetName] = [$headers, $examples];

            foreach ($columns as $column) {
                $guideRows[] = [
                    (string)$source['title'],
                    (string)$column['source_header'],
                    (string)$column['normalized_key'],
                    !empty($column['required']) ? 'بله' : 'خیر',
                    self::typeLabel((string)$column['data_type']),
                ];
            }
        }

        if (!$sheets) throw new InvalidArgumentException('برای منبع انتخاب‌شده قالبی تعریف نشده است.');
        $sheets['راهنما'] = $guideRows;
        return $sheets;
    }

    public static function fileName(array $sourceKeys): string
    {
        $keys = array_values(array_unique($sourceKeys));
        return count($keys) === 1
            ? 'sobhan-' . preg_replace('/[^a-z0-9_-]+/i', '-', $keys[0]) . '-import-template.xlsx'
            : 'sobhan-import-templates.xlsx';
    }

    public static function columns(array $source): array
    {
        $columns = [];
        foreach (($source['mappings'] ?? []) as $mapping) {
            $key = trim((string)($mapping['normalized_key'] ?? ''));
            $header = trim((string)($mapping['source_header'] ?? ''));
            if ($key === '' || $header === '') continue;
            if (!isset($columns[$key])) {
                $columns[$key] = [
                    'source_header' => $header,
                    'normalized_key' => $key,
                    'required' => !empty($mapping['required']) ? 1 : 0,
                    'data_type' => (string)($mapping['data_type'] ?? 'string'),
                ];
                continue;
            }
            if (!empty($mapping['required'])) $columns[$key]['required'] = 1;
        }
        return array_values($columns);
    }

    private static function exampleValue(string $key, string $type): string
    {
        $examples = [
            'invoice_type'=>'فروش','invoice_number'=>'1001','supplier_invoice_number'=>'SUP-1001',
            'invoice_date_raw'=>'1405/04/25','attendance_date_raw'=>'1405/04/25',
            'effective_from_raw'=>'1405/04/01','effective_to_raw'=>'1405/04/31',
            'last_purchase_date_raw'=>'1405/04/20','expire_date_raw'=>'1406/12/29',
            'visitor_code'=>'V100','supervisor_code'=>'S100','sales_manager_code'=>'M100',
            'customer_code'=>'C10001','supplier_code'=>'SUP100','product_code'=>'P10001',
            'line_code'=>'A','brand_code'=>'BR01','brand_name'=>'برند نمونه',
            'product_name'=>'کالای نمونه','customer_name'=>'مشتری نمونه','supplier_name'=>'تأمین‌کننده نمونه',
            'employee_no'=>'10001','kara_system_code'=>'KARA-10001',
            'approved_start_time'=>'07:30','actual_in_time'=>'07:35',
            'approved_end_time'=>'16:30','actual_out_time'=>'16:45',
            'period_id'=>'1','target_year'=>'1405','target_month'=>'4',
            'period_key'=>'1405-04','priority_code'=>'P1','priority_rank'=>'1',
            'customer_class_code'=>'RETAIL','customer_class_title'=>'خرده‌فروشی',
            'coefficient'=>'1.15','allocation_percent'=>'100',
            'quantity'=>'12','target_quantity'=>'100','target_amount'=>'100000000',
            'gross_amount'=>'12000000','discount_amount'=>'500000','net_amount'=>'11500000',
            'carton_size'=>'12','current_period_total_qty'=>'120','current_total_stock'=>'120',
            'daily_work_minutes'=>'480','work_minutes'=>'480','late_minutes'=>'5','overtime_minutes'=>'15',
        ];
        if (isset($examples[$key])) return $examples[$key];
        if ($type === 'date' || str_ends_with($key, '_date_raw')) return '1405/04/25';
        if ($type === 'decimal') return '0';
        if (str_contains($key, 'name') || str_contains($key, 'title')) return 'نمونه';
        if (str_contains($key, 'code')) return '100';
        return '';
    }

    private static function typeLabel(string $type): string
    {
        return ['decimal'=>'عدد', 'date'=>'تاریخ', 'string'=>'متن'][$type] ?? $type;
    }

    private static function sheetName(string $title, string $fallback, array $existing): string
    {
        $name = trim($title) !== '' ? trim($title) : $fallback;
        $name = self::textSubstring(str_replace(['\\','/','?','*','[',']',':'], '-', $name), 0, 31);
        $candidate = $name;
        $suffix = 2;
        while (in_array($candidate, $existing, true)) {
            $tail = '-' . $suffix++;
            $candidate = self::textSubstring($name, 0, 31 - self::textLength($tail)) . $tail;
        }
        return $candidate;
    }

    private static function textSubstring(string $value, int $start, int $length): string
    {
        if (function_exists('mb_substr')) return mb_substr($value, $start, $length, 'UTF-8');
        $characters = preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($characters)) return substr($value, $start, $length);
        return implode('', array_slice($characters, $start, $length));
    }

    private static function textLength(string $value): int
    {
        if (function_exists('mb_strlen')) return mb_strlen($value, 'UTF-8');
        $characters = preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY);
        return is_array($characters) ? count($characters) : strlen($value);
    }
}
