<?php
function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function flash(?string $message = null, string $type = 'success'): ?array
{
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    if ($message !== null) {
        $type = ['error' => 'danger', 'danger' => 'danger', 'warning' => 'warning', 'info' => 'info', 'success' => 'success'][$type] ?? 'info';
        $_SESSION['flash'] = ['message' => $message, 'type' => $type];
        return null;
    }
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

function setting(string $key, string $default = ''): string
{
    static $settings = null;
    if ($settings === null) {
        try {
            $rows = Database::fetchAll('SELECT setting_key, setting_value FROM site_settings');
            $settings = array_column($rows, 'setting_value', 'setting_key');
        } catch (Throwable $e) {
            $settings = [];
        }
    }
    return $settings[$key] ?? $default;
}

function format_number($value): string
{
    return number_format((float)$value, 0, '.', ',');
}

function format_money($value): string
{
    return format_number($value);
}

function sobhan_contains_phrase(string $text, string $phrase): bool
{
    if (function_exists('mb_strpos')) {
        return mb_strpos($text, $phrase, 0, 'UTF-8') !== false;
    }

    return strpos($text, $phrase) !== false;
}

function sobhan_is_visitor_list_question(string $question): bool
{
    $phrases = [
        'اسامی ویزیتورها',
        'نام ویزیتورها',
        'لیست ویزیتورها',
        'کل ویزیتورها',
        'همه ویزیتورها',
        'ویزیتورها را بفرست',
        'ویزیتور برتر',
        'ویزیتورهای برتر',
    ];

    foreach ($phrases as $phrase) {
        if (sobhan_contains_phrase($question, $phrase)) return true;
    }

    return false;
}

function ai_question_asks_for_visitor_names(string $question): bool
{
    return sobhan_is_visitor_list_question($question);
}

function sobhan_wants_all_visitors(string $question): bool
{
    foreach (['کل', 'همه'] as $phrase) {
        if (sobhan_contains_phrase($question, $phrase)) return true;
    }

    return false;
}

function sobhan_wants_three_visitors(string $question): bool
{
    return sobhan_contains_phrase($question, 'سه') && sobhan_contains_phrase($question, 'ویزیتور');
}

function strip_ai_markdown_fences(string $answer): string
{
    $answer = preg_replace('/^\s*```[a-zA-Z0-9_-]*\s*/u', '', $answer) ?? $answer;
    $answer = preg_replace('/\s*```\s*$/u', '', $answer) ?? $answer;
    $answer = str_replace('```', '', $answer);
    return trim($answer);
}

function ai_unwrap_response_data(array $payload): array
{
    if (array_key_exists('ok', $payload) && is_array($payload['data'] ?? null)) {
        return $payload['data'];
    }

    return $payload;
}

function ai_knowledge_sources_from_data(array $data): array
{
    $sources = $data['knowledge_sources'] ?? [];
    if (!is_array($sources)) return [];

    return array_values(array_filter($sources, static fn($source) => is_array($source)));
}

function admin_debug_enabled(): bool
{
    if (!class_exists('Config')) {
        $configPath = __DIR__ . '/Config.php';
        if (file_exists($configPath)) require_once $configPath;
    }

    if (!class_exists('Config')) return false;

    $app = Config::app();
    return !empty($app['debug']);
}

function ai_top_visitors_from_data(array $data): array
{
    $topVisitors = $data['top_visitors'] ?? $data['topVisitors'] ?? [];
    if (is_array($topVisitors)) return $topVisitors;
    return [];
}

function sobhan_rows_from_api_result(array $result): array
{
    $data = $result['data'] ?? [];
    if (is_array($data) && array_key_exists('data', $data)) $data = $data['data'];
    if (is_array($data) && array_key_exists('result', $data)) $data = $data['result'];
    if (!is_array($data)) return [];

    foreach (['items', 'rows', 'data', 'results'] as $key) {
        if (isset($data[$key]) && is_array($data[$key])) return array_values($data[$key]);
    }

    $isList = $data === [] || array_keys($data) === range(0, count($data) - 1);
    return $isList ? $data : [];
}

function ai_value_from_keys(array $row, array $keys, mixed $fallback = ''): mixed
{
    foreach ($keys as $key) {
        if (array_key_exists($key, $row) && $row[$key] !== null && $row[$key] !== '') {
            return $row[$key];
        }
    }

    return $fallback;
}

function ai_numeric_value(mixed $value): float
{
    if (is_string($value)) {
        $value = str_replace([',', '،', ' '], '', $value);
    }

    return (float)$value;
}

function format_ai_top_visitors(array $topVisitors): string
{
    return sobhan_format_visitor_list($topVisitors, true);
}

function sobhan_format_visitor_list(array $rows, bool $all): string
{
    if (!$rows) {
        return '';
    }

    $visibleRows = $all ? array_slice($rows, 0, 100) : array_slice($rows, 0, 10);
    if (!$visibleRows) {
        return '';
    }

    $lines = ['لیست ویزیتورها:'];
    $index = 1;
    foreach ($visibleRows as $visitor) {
        if (!is_array($visitor)) continue;

        $name = (string)ai_value_from_keys($visitor, ['visitor_name', 'name', 'full_name', 'visitorName', 'title'], 'بدون نام');
        $code = (string)ai_value_from_keys($visitor, ['visitor_id', 'visitor_code', 'id', 'code', 'visitorId', 'visitorCode'], '');
        $sales = ai_value_from_keys($visitor, ['net_sales', 'gross_sales', 'sales', 'amount', 'total_sales', 'sales_value'], 0);
        $invoiceCount = ai_value_from_keys($visitor, ['invoice_count', 'invoiceCount', 'invoices', 'factor_count', 'factorCount'], 0);
        $codeText = $code !== '' ? 'کد ' . $code : 'کد نامشخص';

        $lines[] = $index . '. ' . $name . ' - ' . $codeText . ' - فروش ' . format_money(ai_numeric_value($sales)) . ' - تعداد فاکتور ' . format_number(ai_numeric_value($invoiceCount));
        $index++;
    }

    if (!$all) {
        $lines[] = 'برای مشاهده همه، عبارت «همه ویزیتورها» را ارسال کنید.';
    }

    return count($lines) > 1 ? implode("\n", $lines) : '';
}

function ai_display_answer_from_result(array $askResult, string $question, string $fallback = 'دستیار هوش مصنوعی در حال حاضر در دسترس نیست.'): string
{
    return ai_answer_payload_from_result($askResult, $question, $fallback)['answer'];
}

function ai_answer_payload_from_result(array $askResult, string $question, string $fallback = 'دستیار هوش مصنوعی در حال حاضر در دسترس نیست.'): array
{
    if (!($askResult['ok'] ?? false)) {
        return [
            'answer' => (string)($askResult['error']['message_fa'] ?? $fallback),
            'knowledge_sources' => [],
        ];
    }

    $payload = is_array($askResult['data'] ?? null) ? $askResult['data'] : [];
    $data = ai_unwrap_response_data($payload);
    $topVisitors = ai_top_visitors_from_data($data);
    $knowledgeSources = ai_knowledge_sources_from_data($data);

    if (ai_question_asks_for_visitor_names($question)) {
        $visitorAnswer = format_ai_top_visitors($topVisitors);
        if ($visitorAnswer !== '') {
            return [
                'answer' => $visitorAnswer,
                'knowledge_sources' => $knowledgeSources,
            ];
        }
    }

    $answer = '';
    foreach (['answer', 'message', 'result'] as $key) {
        if (isset($data[$key]) && is_scalar($data[$key])) {
            $answer = (string)$data[$key];
            break;
        }
    }

    $answer = strip_ai_markdown_fences($answer);
    if ($answer !== '') {
        return [
            'answer' => $answer,
            'knowledge_sources' => $knowledgeSources,
        ];
    }

    $visitorAnswer = format_ai_top_visitors($topVisitors);
    if ($visitorAnswer !== '') {
        return [
            'answer' => $visitorAnswer,
            'knowledge_sources' => $knowledgeSources,
        ];
    }

    return [
        'answer' => $fallback,
        'knowledge_sources' => $knowledgeSources,
    ];
}

function render_ai_knowledge_sources(array $sources, ?bool $debug = null): string
{
    if (!$sources) return '';

    $debug ??= admin_debug_enabled();
    $html = '<div class="ai-knowledge-sources" dir="rtl"><strong>منابع استفاده‌شده</strong><ul>';
    foreach ($sources as $source) {
        if (!is_array($source)) continue;

        $sourceFile = ai_value_from_keys($source, ['source_file', 'sourceFile', 'file'], 'نامشخص');
        $chunkIndex = ai_value_from_keys($source, ['chunk_index', 'chunkIndex'], 'نامشخص');
        $sourceFile = is_scalar($sourceFile) ? (string)$sourceFile : 'نامشخص';
        $chunkIndex = is_scalar($chunkIndex) ? (string)$chunkIndex : 'نامشخص';
        $line = e($sourceFile) . ' - بخش ' . e($chunkIndex);

        if ($debug && array_key_exists('distance', $source) && is_scalar($source['distance'])) {
            $line .= ' - فاصله ' . e((string)$source['distance']);
        }

        $html .= '<li>' . $line . '</li>';
    }
    $html .= '</ul></div>';

    return $html;
}

function format_large_number($value): string
{
    $number = (float)$value;
    $abs = abs($number);
    if ($abs >= 1000000000) {
        $short = $number / 1000000000;
        return rtrim(rtrim(number_format($short, $short >= 100 ? 0 : 1, '.', ','), '0'), '.') . ' میلیارد';
    }
    if ($abs >= 1000000) {
        $short = $number / 1000000;
        return rtrim(rtrim(number_format($short, $short >= 100 ? 0 : 1, '.', ','), '0'), '.') . ' میلیون';
    }
    return format_number($number);
}

function format_percent($value, int $decimals = 0): string
{
    return number_format((float)$value, $decimals, '.', ',') . '%';
}

function format_jalali_date(?string $value): string
{
    require_once __DIR__ . '/../lib/AppDate.php';
    return AppDate::formatDate($value);
}

function format_jalali_datetime(?string $value): string
{
    require_once __DIR__ . '/../lib/AppDate.php';
    return AppDate::formatDateTime($value);
}

function jalali_input_value(?string $value): string
{
    require_once __DIR__ . '/../lib/AppDate.php';
    return AppDate::formatDate($value);
}

function app_date_to_iso(?string $value): ?string
{
    require_once __DIR__ . '/../lib/AppDate.php';
    return AppDate::toGregorian($value);
}

function app_datetime_to_iso(?string $value): ?string
{
    require_once __DIR__ . '/../lib/AppDate.php';
    return AppDate::toGregorianDateTime($value);
}

function app_date_input(string $name, ?string $value = null, array $options = []): string
{
    require_once __DIR__ . '/../lib/AppDate.php';
    $datetime = !empty($options['datetime']);
    $displayValue = $datetime ? AppDate::formatDateTime($value, !empty($options['seconds'])) : AppDate::formatDate($value);
    $attributes = [
        'type' => 'text',
        'name' => $name,
        'value' => $displayValue,
        'data-jalali-date' => '',
        'class' => trim('jalali-date-input ' . (string)($options['class'] ?? '')),
        'placeholder' => (string)($options['placeholder'] ?? ($datetime ? '1405/04/25 13:30' : '1405/04/25')),
    ];
    foreach (['id', 'min', 'max', 'iso_target', 'disabled_dates'] as $key) {
        if (!isset($options[$key]) || $options[$key] === '') continue;
        $attribute = match ($key) {
            'min' => 'data-jalali-min',
            'max' => 'data-jalali-max',
            'iso_target' => 'data-iso-target',
            'disabled_dates' => 'data-jalali-disabled-dates',
            default => $key,
        };
        $attributes[$attribute] = in_array($key, ['min', 'max'], true)
            ? AppDate::formatDate((string)$options[$key])
            : (is_array($options[$key]) ? json_encode($options[$key], JSON_UNESCAPED_UNICODE) : (string)$options[$key]);
    }
    if ($datetime) $attributes['data-jalali-datetime'] = '';
    if (!empty($options['month'])) $attributes['data-jalali-month'] = '';
    if (!empty($options['range'])) $attributes['data-jalali-mode'] = 'range';
    if (!empty($options['required'])) $attributes['required'] = '';
    if (!empty($options['disabled'])) $attributes['disabled'] = '';
    if (!empty($options['readonly'])) $attributes['readonly'] = '';
    if (!empty($options['autocomplete'])) $attributes['autocomplete'] = (string)$options['autocomplete'];

    $html = '<input';
    foreach ($attributes as $attribute => $attributeValue) {
        $html .= ' ' . $attribute;
        if ($attributeValue !== '') $html .= '="' . e((string)$attributeValue) . '"';
    }
    return $html . '>';
}

function app_period_select(
    string $name,
    ?string $selected = null,
    array $types = [],
    array $options = []
): string {
    require_once __DIR__ . '/../lib/AppDate.php';
    try {
        $periods = AppDate::periods($types, (string)($options['scope'] ?? 'global'));
    } catch (Throwable $error) {
        error_log('period selector: ' . $error->getMessage());
        $periods = [];
    }
    $attributes = [
        'name' => $name,
        'data-period-selector' => '',
    ];
    if (!empty($options['id'])) $attributes['id'] = (string)$options['id'];
    if (!empty($options['class'])) $attributes['class'] = (string)$options['class'];
    if (!empty($options['custom_target'])) $attributes['data-custom-period-target'] = (string)$options['custom_target'];
    if (!empty($options['required'])) $attributes['required'] = '';

    $html = '<select';
    foreach ($attributes as $attribute => $attributeValue) {
        $html .= ' ' . $attribute;
        if ($attributeValue !== '') $html .= '="' . e((string)$attributeValue) . '"';
    }
    $html .= '>';
    if (!empty($options['placeholder'])) $html .= '<option value="">' . e((string)$options['placeholder']) . '</option>';
    $grouped = [];
    foreach ($periods as $period) $grouped[$period['period_type']][] = $period;
    foreach (AppDate::PERIOD_TYPES as $type => $label) {
        if (empty($grouped[$type])) continue;
        $html .= '<optgroup label="' . e($label) . '">';
        foreach ($grouped[$type] as $period) {
            $isSelected = (string)$selected === (string)$period['period_key'];
            $html .= '<option value="' . e((string)$period['period_key']) . '"' . ($isSelected ? ' selected' : '') . '>';
            $html .= e((string)$period['title']) . '</option>';
        }
        $html .= '</optgroup>';
    }
    return $html . '</select>';
}
