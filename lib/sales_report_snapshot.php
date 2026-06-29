<?php

function sales_snapshot_value(mixed $value, string $type): string
{
    if ($value === null || $value === '') return '—';
    if (in_array($type, ['money','signed_money'], true)) return number_format((float)$value) . ' ریال';
    if ($type === 'percent') return number_format((float)$value, 1) . '٪';
    if (in_array($type, ['number','signed_number'], true)) return number_format((float)$value, 0);
    if ($type === 'date') {
        try { return format_jalali_date((string)$value); } catch (Throwable) { return (string)$value; }
    }
    if ($type === 'entity') return ['visitor'=>'ویزیتور','supervisor'=>'سرپرست','manager'=>'مدیر فروش'][(string)$value] ?? (string)$value;
    if ($type === 'status') return ['ok'=>'واجد شرایط'][(string)$value] ?? (string)$value;
    return trim(strip_tags((string)$value));
}

function sales_snapshot_clean_text(string $value, int $limit = 4000): string
{
    $value = trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', strip_tags($value)) ?? '');
    return mb_substr($value, 0, $limit);
}

function sales_snapshot_json(array $snapshot): string
{
    $json = json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    if (!is_string($json)) throw new RuntimeException('snapshot_encode_failed');
    return $json;
}
