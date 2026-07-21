<?php

require_once __DIR__ . '/AppDate.php';
require_once __DIR__ . '/FormulaSourceRegistry.php';

final class FormulaEngine
{
    public const CATEGORIES = [
        'commission' => 'پورسانت',
        'penalty' => 'ضریب کاهنده',
        'target' => 'تارگت',
        'brand_bonus' => 'پاداش برند',
        'customer_coverage' => 'پوشش مشتری',
        'return' => 'مرجوعی',
        'three_three_three' => '۳-۳-۳',
        'kpi' => 'KPI',
        'attendance' => 'کارکرد',
        'management_report' => 'گزارشات مدیریتی',
        'offer_budget' => 'بودجه آفر',
        'payroll' => 'حقوق و دستمزد',
    ];
    public const AGGREGATIONS = [
        'SUM' => 'جمع',
        'COUNT' => 'تعداد ردیف',
        'COUNT_DISTINCT' => 'تعداد یکتا',
        'AVERAGE' => 'میانگین',
        'MIN' => 'کمینه',
        'MAX' => 'بیشینه',
        'PERCENT' => 'درصد',
        'RATIO' => 'نسبت',
    ];
    public const OPERATORS = [
        '=' => 'برابر',
        '!=' => 'نابرابر',
        '>' => 'بزرگ‌تر',
        '>=' => 'بزرگ‌تر یا برابر',
        '<' => 'کوچک‌تر',
        '<=' => 'کوچک‌تر یا برابر',
        'BETWEEN' => 'بین',
        'IN' => 'در فهرست',
        'NOT_IN' => 'خارج از فهرست',
    ];
    public const RESULT_TYPES = [
        'fixed' => 'مقدار ثابت',
        'metric' => 'خود مقدار شاخص',
        'percent_of_metric' => 'درصدی از شاخص',
        'difference_from_condition' => 'اختلاف با مقدار شرط',
        'boolean' => 'صفر یا یک',
    ];

    public static function normalizeBuilderInput(array $input): array
    {
        $formulaKey = strtolower(trim((string)($input['formula_key'] ?? '')));
        if (!preg_match('/^[a-z][a-z0-9_]{2,99}$/', $formulaKey)) {
            throw new InvalidArgumentException('کلید فرمول باید انگلیسی، یکتا و حداقل سه نویسه باشد.');
        }
        $title = trim((string)($input['title'] ?? ''));
        if ($title === '') throw new InvalidArgumentException('عنوان فرمول الزامی است.');
        $category = (string)($input['category_key'] ?? '');
        if (!isset(self::CATEGORIES[$category])) throw new InvalidArgumentException('دسته فرمول معتبر نیست.');
        $sourceKey = (string)($input['data_source_key'] ?? '');
        $source = FormulaSourceRegistry::source($sourceKey);
        if (!$source) throw new InvalidArgumentException('منبع داده فرمول معتبر نیست.');
        $metric = (string)($input['metric_key'] ?? '');
        if (!isset($source['metrics'][$metric])) throw new InvalidArgumentException('شاخص انتخاب‌شده برای این منبع مجاز نیست.');
        $aggregation = (string)($input['aggregation_key'] ?? '');
        if (!isset(self::AGGREGATIONS[$aggregation])) throw new InvalidArgumentException('نوع تجمیع معتبر نیست.');
        $comparisonMetric = trim((string)($input['comparison_metric_key'] ?? '')) ?: null;
        if (in_array($aggregation, ['PERCENT', 'RATIO'], true)) {
            if ($comparisonMetric === null || !isset($source['metrics'][$comparisonMetric]) || $comparisonMetric === $metric) {
                throw new InvalidArgumentException('برای درصد یا نسبت، شاخص مقایسه معتبر انتخاب کنید.');
            }
        } else {
            $comparisonMetric = null;
        }
        $operator = (string)($input['operator_key'] ?? '');
        if (!isset(self::OPERATORS[$operator])) throw new InvalidArgumentException('عملگر شرط معتبر نیست.');
        $conditionValues = self::conditionValues($input['condition_value'] ?? '', $operator);
        $resultType = (string)($input['result_type'] ?? '');
        if (!isset(self::RESULT_TYPES[$resultType])) throw new InvalidArgumentException('نوع نتیجه معتبر نیست.');
        $resultValue = self::number($input['result_value'] ?? 0, 'مقدار نتیجه');
        $effectiveFrom = self::date($input['effective_from'] ?? null, 'شروع اعتبار');
        $effectiveTo = self::date($input['effective_to'] ?? null, 'پایان اعتبار');
        if ($effectiveFrom && $effectiveTo && $effectiveFrom > $effectiveTo) {
            throw new InvalidArgumentException('شروع اعتبار باید پیش از پایان اعتبار باشد.');
        }

        $filters = [];
        $fieldInputs = is_array($input['filter_field'] ?? null) ? $input['filter_field'] : [];
        $operatorInputs = is_array($input['filter_operator'] ?? null) ? $input['filter_operator'] : [];
        $valueInputs = is_array($input['filter_value'] ?? null) ? $input['filter_value'] : [];
        foreach ($fieldInputs as $index => $field) {
            $field = trim((string)$field);
            if ($field === '') continue;
            if (!isset($source['filters'][$field])) throw new InvalidArgumentException('فیلتر انتخاب‌شده برای منبع داده مجاز نیست.');
            $filterOperator = (string)($operatorInputs[$index] ?? '=');
            if (!isset(self::OPERATORS[$filterOperator])) throw new InvalidArgumentException('عملگر فیلتر معتبر نیست.');
            $filters[] = [
                'field_key' => $field,
                'operator_key' => $filterOperator,
                'values' => self::conditionValues($valueInputs[$index] ?? '', $filterOperator, false),
            ];
        }
        $dependencies = array_values(array_unique(array_filter(array_map(
            'intval',
            is_array($input['dependency_ids'] ?? null) ? $input['dependency_ids'] : []
        ))));

        $rule = [
            'data_source_key' => $sourceKey,
            'metric_key' => $metric,
            'comparison_metric_key' => $comparisonMetric,
            'aggregation_key' => $aggregation,
            'filters' => $filters,
            'condition' => ['operator' => $operator, 'values' => $conditionValues],
            'result' => ['type' => $resultType, 'value' => $resultValue],
        ];
        return [
            'formula_key' => $formulaKey,
            'title' => $title,
            'category_key' => $category,
            'description' => trim((string)($input['description'] ?? '')) ?: null,
            'owner_scope' => trim((string)($input['owner_scope'] ?? '')) ?: null,
            'effective_from' => $effectiveFrom,
            'effective_to' => $effectiveTo,
            'data_source_key' => $sourceKey,
            'metric_key' => $metric,
            'comparison_metric_key' => $comparisonMetric,
            'aggregation_key' => $aggregation,
            'operator_key' => $operator,
            'condition_values' => $conditionValues,
            'result_type' => $resultType,
            'result_value' => $resultValue,
            'priority' => max(0, min(10000, (int)($input['priority'] ?? 100))),
            'active' => isset($input['active']) ? 1 : 0,
            'user_note' => trim((string)($input['user_note'] ?? '')) ?: null,
            'filters' => $filters,
            'dependency_ids' => $dependencies,
            'rule' => $rule,
        ];
    }

    public static function evaluate(array $rule, array $rows): array
    {
        $filteredRows = self::applyFilters($rows, is_array($rule['filters'] ?? null) ? $rule['filters'] : []);
        $aggregation = (string)($rule['aggregation_key'] ?? '');
        $metric = (string)($rule['metric_key'] ?? '');
        $comparison = (string)($rule['comparison_metric_key'] ?? '');
        $value = self::aggregate($filteredRows, $metric, $aggregation, $comparison);
        $condition = is_array($rule['condition'] ?? null) ? $rule['condition'] : [];
        $operator = (string)($condition['operator'] ?? '=');
        $conditionValues = is_array($condition['values'] ?? null) ? $condition['values'] : [];
        $matched = self::matches($value, $operator, $conditionValues);
        $result = 0.0;
        $resultRule = is_array($rule['result'] ?? null) ? $rule['result'] : [];
        if ($matched) {
            $resultValue = (float)($resultRule['value'] ?? 0);
            $result = match ((string)($resultRule['type'] ?? 'fixed')) {
                'metric' => $value,
                'percent_of_metric' => $value * $resultValue / 100,
                'difference_from_condition' => $value - (float)($conditionValues[0] ?? 0),
                'boolean' => 1.0,
                default => $resultValue,
            };
        }
        return [
            'input_count' => count($rows),
            'filtered_count' => count($filteredRows),
            'aggregate_value' => round($value, 6),
            'matched' => $matched,
            'final_result' => round($result, 6),
            'trace' => [
                ['step' => 'source', 'label' => 'تعداد ورودی منبع', 'value' => count($rows)],
                ['step' => 'filters', 'label' => 'تعداد ردیف پس از فیلتر', 'value' => count($filteredRows)],
                ['step' => 'aggregation', 'label' => self::AGGREGATIONS[$aggregation] ?? $aggregation, 'value' => round($value, 6)],
                ['step' => 'condition', 'label' => 'نتیجه شرط ' . (self::OPERATORS[$operator] ?? $operator), 'value' => $matched ? 'برقرار' : 'برقرار نیست'],
                ['step' => 'result', 'label' => 'نتیجه نهایی', 'value' => round($result, 6)],
            ],
        ];
    }

    private static function applyFilters(array $rows, array $filters): array
    {
        return array_values(array_filter($rows, static function (array $row) use ($filters): bool {
            foreach ($filters as $filter) {
                $value = $row[(string)($filter['field_key'] ?? '')] ?? null;
                if (!self::matches($value, (string)($filter['operator_key'] ?? '='), (array)($filter['values'] ?? []))) {
                    return false;
                }
            }
            return true;
        }));
    }

    private static function aggregate(array $rows, string $metric, string $aggregation, string $comparisonMetric): float
    {
        $values = array_map(static fn(array $row): mixed => $row[$metric] ?? null, $rows);
        if ($aggregation === 'COUNT') return (float)count($rows);
        if ($aggregation === 'COUNT_DISTINCT') {
            return (float)count(array_unique(array_map(static fn(mixed $value): string => (string)$value, $values)));
        }
        $numbers = array_map(static fn(mixed $value): float => is_numeric($value) ? (float)$value : 0.0, $values);
        if (!$numbers) return 0.0;
        if ($aggregation === 'AVERAGE') return array_sum($numbers) / count($numbers);
        if ($aggregation === 'MIN') return min($numbers);
        if ($aggregation === 'MAX') return max($numbers);
        if (in_array($aggregation, ['PERCENT', 'RATIO'], true)) {
            $denominator = array_sum(array_map(
                static fn(array $row): float => is_numeric($row[$comparisonMetric] ?? null) ? (float)$row[$comparisonMetric] : 0.0,
                $rows
            ));
            if (abs($denominator) < 0.0000001) return 0.0;
            $ratio = array_sum($numbers) / $denominator;
            return $aggregation === 'PERCENT' ? $ratio * 100 : $ratio;
        }
        return array_sum($numbers);
    }

    private static function matches(mixed $actual, string $operator, array $expected): bool
    {
        $first = $expected[0] ?? null;
        $second = $expected[1] ?? null;
        $numeric = is_numeric($actual) && ($first === null || is_numeric($first));
        $left = $numeric ? (float)$actual : (string)$actual;
        $right = $numeric ? (float)$first : (string)$first;
        return match ($operator) {
            '=' => $left == $right,
            '!=' => $left != $right,
            '>' => $left > $right,
            '>=' => $left >= $right,
            '<' => $left < $right,
            '<=' => $left <= $right,
            'BETWEEN' => $left >= ($numeric ? (float)$first : (string)$first)
                && $left <= ($numeric ? (float)$second : (string)$second),
            'IN' => in_array((string)$actual, array_map('strval', $expected), true),
            'NOT_IN' => !in_array((string)$actual, array_map('strval', $expected), true),
            default => false,
        };
    }

    private static function conditionValues(mixed $value, string $operator, bool $numericPreferred = true): array
    {
        $raw = is_array($value) ? $value : preg_split('/[\r\n,،]+/u', (string)$value);
        $values = array_values(array_filter(array_map(static function (mixed $item) use ($numericPreferred): mixed {
            $item = trim(AppDate::normalizeDigits((string)$item));
            if ($item === '') return null;
            return $numericPreferred && is_numeric($item) ? (float)$item : $item;
        }, $raw ?: []), static fn(mixed $item): bool => $item !== null));
        $required = $operator === 'BETWEEN' ? 2 : 1;
        if (count($values) < $required) throw new InvalidArgumentException('مقدار شرط با عملگر انتخاب‌شده سازگار نیست.');
        if (!in_array($operator, ['IN', 'NOT_IN', 'BETWEEN'], true)) $values = [reset($values)];
        if ($operator === 'BETWEEN') $values = array_slice($values, 0, 2);
        return $values;
    }

    private static function number(mixed $value, string $label): float
    {
        $value = trim(AppDate::normalizeDigits((string)$value));
        if ($value === '') return 0.0;
        if (!is_numeric($value)) throw new InvalidArgumentException($label . ' معتبر نیست.');
        return (float)$value;
    }

    private static function date(mixed $value, string $label): ?string
    {
        $value = trim((string)$value);
        if ($value === '') return null;
        $date = AppDate::toGregorian($value);
        if ($date === null) throw new InvalidArgumentException($label . ' معتبر نیست.');
        return $date;
    }
}
