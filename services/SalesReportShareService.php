<?php

require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/ManagerDashboard.php';
require_once __DIR__ . '/../core/ManagerDashboardCalculator.php';
require_once __DIR__ . '/../lib/OrgAccess.php';
require_once __DIR__ . '/../lib/sales_report_snapshot.php';

class SalesReportShareService
{
    public const TYPES = [
        'summary_cards' => 'کارت‌های خلاصه فروش',
        'line_performance' => 'جدول عملکرد لاین‌ها',
        'visitor_performance' => 'جدول عملکرد ویزیتورها',
        'ai_analysis' => 'تحلیل هوش مصنوعی',
        'chart' => 'نمودار فروش',
        'full_report' => 'گزارش کامل فروش',
    ];

    public static function canForward(?array $user = null): bool
    {
        $user ??= Auth::user();
        if (!$user) return false;
        if (in_array($user['role'] ?? '', ['admin','super_admin'], true)) return true;
        $dashboardPermission=Database::fetch('SELECT can_view FROM user_permissions WHERE user_id=? AND module_key="manager_dashboard.view" LIMIT 1',[(int)$user['id']]);
        if (!(int)($dashboardPermission['can_view']??0)) return false;
        $forwardPermission=Database::fetch('SELECT can_create FROM user_permissions WHERE user_id=? AND module_key="manager_dashboard.forward" LIMIT 1',[(int)$user['id']]);
        if ((int)($forwardPermission['can_create']??0)===1) return true;
        $context = OrgAccess::userContext((int)$user['id']) ?: $user;
        $code = strtolower((string)($context['org_role_code'] ?? $context['role_key'] ?? ''));
        return in_array($code, ['sales_manager','sales-manager'], true);
    }

    public static function assertCanForward(array $user): void
    {
        if (!self::canForward($user)) throw new RuntimeException('forward_access_denied');
    }

    public static function build(int $reportId, string $requestedType, array $rawFilters, array $user, string $title = ''): array
    {
        self::assertCanForward($user);
        $report = ManagerDashboard::latestReport($reportId);
        if (!$report || ($report['import_status'] ?? '') !== 'success') throw new InvalidArgumentException('گزارش انتخاب‌شده معتبر نیست.');

        $chartSource = str_starts_with($requestedType, 'chart:') ? substr($requestedType, 6) : '';
        $type = $chartSource !== '' && isset(ManagerDashboard::definitions()[$chartSource]) ? 'chart' : (array_key_exists($requestedType, self::TYPES) || isset(ManagerDashboard::definitions()[$requestedType]) ? $requestedType : 'summary_cards');
        $filters = self::filters($rawFilters, $user);
        $table = ['columns'=>[], 'rows'=>[]];
        $summaryCards = [];
        $aiAnalysis = '';

        if ($type === 'summary_cards' || $type === 'full_report') $summaryCards = self::summaryCards($reportId, $filters, $user);
        if ($type === 'ai_analysis' || $type === 'full_report') $aiAnalysis = self::aiAnalysis($reportId, $user);

        $widget = match ($type) {
            'chart' => $chartSource,
            'line_performance' => 'line_performance',
            'visitor_performance' => 'commission_summary',
            'full_report' => 'commission_summary',
            default => isset(ManagerDashboard::definitions()[$type]) ? $type : null,
        };
        if ($widget) $table = self::table($widget, $reportId, $filters, $user);
        $chart = $type === 'chart' ? self::chart($table) : ['type'=>'', 'image_path'=>'', 'data'=>[]];

        $reportTitle = sales_snapshot_clean_text($title, 190) ?: (self::TYPES[$type] ?? ManagerDashboard::widgets()[$type]['title'] ?? 'گزارش فروش');
        $snapshot = [
            'title' => $reportTitle,
            'source' => 'sales_manager_panel',
            'report_type' => $type,
            'period' => (string)($report['report_date'] ?? ''),
            'filters' => $filters,
            'summary_cards' => $summaryCards,
            'table' => $table,
            'chart' => $chart,
            'ai_analysis' => $aiAnalysis,
            'generated_at' => date('c'),
            'generated_by' => sales_snapshot_clean_text((string)($user['name'] ?? ''), 150),
        ];
        return ['report'=>$report, 'type'=>$type, 'filters'=>$filters, 'snapshot'=>$snapshot];
    }

    private static function filters(array $raw, array $user): array
    {
        $filters = [];
        foreach (['search','line_code','visitor','supervisor'] as $key) {
            $value = sales_snapshot_clean_text((string)($raw[$key] ?? ''), 100);
            if ($value !== '') $filters[$key] = $value;
        }
        if (!in_array($user['role'] ?? '', ['admin','super_admin'], true) && trim((string)($user['sales_line'] ?? '')) !== '') {
            $filters['line_code'] = sales_snapshot_clean_text((string)$user['sales_line'], 50);
        }
        return $filters;
    }

    private static function allowedNames(array $user): array
    {
        if (in_array($user['role'] ?? '', ['admin','super_admin'], true)) return [];
        $ids = OrgAccess::accessibleUserIds($user);
        if (!$ids) return [(string)($user['name'] ?? '')];
        $rows = Database::fetchAll('SELECT name FROM users WHERE id IN ('.implode(',', array_fill(0, count($ids), '?')).')', $ids);
        return array_values(array_filter(array_map(static fn($row) => trim((string)$row['name']), $rows)));
    }

    private static function scopedRows(array $rows, array $user): array
    {
        if (in_array($user['role'] ?? '', ['admin','super_admin'], true)) return $rows;
        $names = self::allowedNames($user);
        $line = trim((string)($user['sales_line'] ?? ''));
        return array_values(array_filter($rows, static function(array $row) use ($names, $line): bool {
            $rowLine = trim((string)($row['line_code'] ?? $row['line_group'] ?? ''));
            if ($rowLine !== '') return $line !== '' && $rowLine === $line;
            foreach (['visitor_name','person_name','supervisor_name','sales_manager_name'] as $key) {
                if (trim((string)($row[$key] ?? '')) !== '') return in_array(trim((string)$row[$key]), $names, true);
            }
            return false;
        }));
    }

    private static function table(string $widget, int $reportId, array $filters, array $user): array
    {
        $definition = ManagerDashboard::definitions()[$widget] ?? null;
        if (!$definition) return ['columns'=>[], 'rows'=>[]];
        $rows = self::scopedRows(ManagerDashboard::filteredRows($widget, $reportId, $filters, 500), $user);
        $columns = array_map(static fn($field) => ['key'=>$field[0], 'label'=>$field[1], 'type'=>$field[2]], $definition['fields']);
        $safeRows = [];
        foreach ($rows as $row) {
            $out = [];
            foreach ($definition['fields'] as [$key,,$type]) $out[$key] = sales_snapshot_value($row[$key] ?? '', $type);
            $safeRows[] = $out;
        }
        return ['columns'=>$columns, 'rows'=>$safeRows];
    }

    private static function summaryCards(int $reportId, array $filters, array $user): array
    {
        $rows = self::scopedRows(ManagerDashboard::filteredRows('commission_summary', $reportId, $filters, 500), $user);
        $sum = static fn(string $field): float => array_sum(array_map(static fn($row) => (float)($row[$field] ?? 0), $rows));
        $average = count($rows) ? $sum('achievement_percent') / count($rows) : 0;
        return [
            ['label'=>'مجموع فروش','value'=>number_format($sum('sales_amount')).' ریال'],
            ['label'=>'مجموع پورسانت نهایی','value'=>number_format($sum('final_commission')).' ریال'],
            ['label'=>'میانگین تحقق','value'=>number_format($average,1).'٪'],
            ['label'=>'تعداد ویزیتورها','value'=>number_format(count($rows))],
        ];
    }

    private static function chart(array $table): array
    {
        $columns=$table['columns']??[];$rows=array_slice($table['rows']??[],0,30);$labelColumn=null;$valueColumn=null;
        foreach($columns as $column){if(!$labelColumn&&!in_array($column['type'],['money','signed_money','number','signed_number','percent'],true))$labelColumn=$column;if(!$valueColumn&&in_array($column['type'],['money','number','percent'],true))$valueColumn=$column;}
        if(!$labelColumn||!$valueColumn)return ['type'=>'bar','image_path'=>'','data'=>[]];
        $data=[];foreach($rows as $row){$raw=preg_replace('/[^0-9.\-]/','',(string)($row[$valueColumn['key']]??'0'));$data[]=['label'=>(string)($row[$labelColumn['key']]??'—'),'value'=>(float)$raw,'value_label'=>(string)($row[$valueColumn['key']]??'۰')];}
        return ['type'=>'bar','image_path'=>'','label'=>$valueColumn['label'],'data'=>$data];
    }

    private static function aiAnalysis(int $reportId, array $user): string
    {
        $params = [$reportId];
        $sql = 'SELECT response_text FROM manager_dashboard_ai_logs WHERE report_id=? AND status="success"';
        if (!in_array($user['role'] ?? '', ['admin','super_admin'], true)) { $sql .= ' AND user_id=?'; $params[] = (int)$user['id']; }
        $row = Database::fetch($sql.' ORDER BY id DESC LIMIT 1', $params);
        return sales_snapshot_clean_text((string)($row['response_text'] ?? ''), 20000);
    }

    public static function createCsv(array $snapshot): ?array
    {
        $columns = $snapshot['table']['columns'] ?? [];
        $rows = $snapshot['table']['rows'] ?? [];
        if (!$columns || !$rows) return null;
        $relativeDir = 'messenger-reports/'.date('Y/m');
        $absoluteDir = dirname(__DIR__).'/uploads/'.$relativeDir;
        if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0750, true) && !is_dir($absoluteDir)) throw new RuntimeException('attachment_directory_failed');
        $name = 'sales-report-'.date('Ymd-His').'-'.bin2hex(random_bytes(5)).'.csv';
        $absolute = $absoluteDir.'/'.$name;
        $handle = fopen($absolute, 'xb');
        if (!$handle) throw new RuntimeException('attachment_create_failed');
        try {
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, array_column($columns, 'label'));
            foreach ($rows as $row) fputcsv($handle, array_map(static fn($column) => (string)($row[$column['key']] ?? ''), $columns));
        } finally { fclose($handle); }
        $relative = $relativeDir.'/'.$name;
        try {
            require_once __DIR__.'/../lib/FileBackupService.php';
            FileBackupService::registerSavedFile('/uploads/'.$relative, $name, 'text/csv', filesize($absolute));
        } catch (Throwable $e) { error_log('forwarded report backup registration: '.$e->getMessage()); }
        return ['path'=>$relative, 'name'=>$name, 'mime'=>'text/csv'];
    }
}
