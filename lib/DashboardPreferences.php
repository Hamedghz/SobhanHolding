<?php

require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/DashboardModule.php';

final class DashboardPreferences
{
    private const SIZES = ['third', 'half', 'wide'];
    private const PERIODS = ['daily', 'weekly', 'monthly', 'quarterly', 'half_yearly', 'yearly'];
    private const FILTER_MODES = ['authorized_scope', 'own_scope', 'all_authorized'];

    public static function scopeLabels(): array
    {
        return DashboardModule::SCOPES;
    }

    public static function definitions(string $scope): array
    {
        return DashboardModule::definitions()[$scope] ?? [];
    }

    public static function canManage(string $scope, ?array $user = null): bool
    {
        $user ??= Auth::user();
        if (!$user) return false;
        if (in_array((string)($user['role'] ?? ''), ['admin', 'super_admin'], true)) return true;
        return match ($scope) {
            'ceo' => Auth::can('ceo_dashboard', 'edit'),
            'sales_manager' => Auth::can('manager_dashboard.settings'),
            'supervisor' => Auth::can('admin.supervisor_settings.manage'),
            'employee', 'department_manager' => Auth::can('settings'),
            default => false,
        };
    }

    public static function allowedManageScopes(?array $user = null): array
    {
        return array_values(array_filter(
            array_keys(self::scopeLabels()),
            static fn(string $scope): bool => self::canManage($scope, $user)
        ));
    }

    public static function forScope(string $scope, int $scopeId = 0, int $userId = 0): array
    {
        $definitions = self::definitions($scope);
        if (!$definitions) return [];
        $rows = Database::fetchAll(
            'SELECT * FROM dashboard_widget_preferences
             WHERE scope_type=? AND scope_id IN (0,?) AND user_id IN (0,?)
             ORDER BY scope_id,user_id,sort_order,id',
            [$scope, $scopeId, $userId]
        );
        $resolved = [];
        foreach ($definitions as $key => $definition) {
            $resolved[$key] = array_merge([
                'scope_type' => $scope,
                'scope_id' => 0,
                'user_id' => 0,
                'widget_key' => $key,
                'title_override' => $definition['title'],
                'visible' => 1,
                'sort_order' => $definition['sort_order'],
                'size_key' => $definition['size_key'],
                'default_period_key' => $definition['default_period_key'],
                'default_filters_json' => json_encode(['mode' => 'authorized_scope'], JSON_UNESCAPED_UNICODE),
                'refresh_seconds' => 0,
                'drilldown_enabled' => $definition['drilldown_enabled'] ? 1 : 0,
                'data_source_key' => $definition['data_source_key'],
                'settings_json' => null,
            ], $definition);
        }
        foreach ($rows as $row) {
            $key = (string)$row['widget_key'];
            if (isset($resolved[$key])) $resolved[$key] = array_merge($resolved[$key], $row);
        }
        uasort($resolved, static fn(array $a, array $b): int =>
            [(int)$a['sort_order'], (string)$a['widget_key']] <=> [(int)$b['sort_order'], (string)$b['widget_key']]
        );
        return $resolved;
    }

    public static function save(string $scope, array $input, int $actorId): void
    {
        $definitions = self::definitions($scope);
        if (!$definitions) throw new InvalidArgumentException('دامنه داشبورد معتبر نیست.');
        $pdo = Database::connection();
        $statement = $pdo->prepare(
            'INSERT INTO dashboard_widget_preferences
             (scope_type,scope_id,user_id,widget_key,title_override,visible,sort_order,size_key,default_period_key,default_filters_json,refresh_seconds,drilldown_enabled,data_source_key,settings_json,created_at,updated_at)
             VALUES (?,0,0,?,?,?,?,?,?,?,?,?,?,NULL,NOW(),NOW())
             ON DUPLICATE KEY UPDATE
                title_override=VALUES(title_override),
                visible=VALUES(visible),
                sort_order=VALUES(sort_order),
                size_key=VALUES(size_key),
                default_period_key=VALUES(default_period_key),
                default_filters_json=VALUES(default_filters_json),
                refresh_seconds=VALUES(refresh_seconds),
                drilldown_enabled=VALUES(drilldown_enabled),
                data_source_key=VALUES(data_source_key),
                updated_at=NOW()'
        );
        $pdo->beginTransaction();
        try {
            foreach ($definitions as $key => $definition) {
                $size = (string)($input['size'][$key] ?? $definition['size_key']);
                if (!in_array($size, self::SIZES, true)) $size = $definition['size_key'];
                $period = (string)($input['period'][$key] ?? $definition['default_period_key']);
                if (!in_array($period, self::PERIODS, true)) $period = $definition['default_period_key'];
                $filterMode = (string)($input['filter_mode'][$key] ?? 'authorized_scope');
                if (!in_array($filterMode, self::FILTER_MODES, true)) $filterMode = 'authorized_scope';
                $refresh = max(0, min(3600, (int)($input['refresh'][$key] ?? 0)));
                $title = trim((string)($input['title'][$key] ?? $definition['title']));
                if ($title === '') $title = $definition['title'];
                $title = function_exists('mb_substr') ? mb_substr($title, 0, 190) : substr($title, 0, 190);
                $statement->execute([
                    $scope,
                    $key,
                    $title,
                    isset($input['visible'][$key]) ? 1 : 0,
                    max(0, (int)($input['sort'][$key] ?? $definition['sort_order'])),
                    $size,
                    $period,
                    json_encode(['mode' => $filterMode], JSON_UNESCAPED_UNICODE),
                    $refresh,
                    isset($input['drilldown'][$key]) ? 1 : 0,
                    $definition['data_source_key'],
                ]);
            }
            $pdo->commit();
            Auth::log($actorId, 'dashboard_preferences_updated', 'dashboard_widget_preferences', null);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public static function isVisible(array $preferences, string $key): bool
    {
        return isset($preferences[$key]) && (int)($preferences[$key]['visible'] ?? 0) === 1;
    }

    public static function title(array $preferences, string $key, string $fallback): string
    {
        return trim((string)($preferences[$key]['title_override'] ?? '')) ?: $fallback;
    }

    public static function sortRows(array $rows, array $preferences, string $keyField = 'widget_key'): array
    {
        $visible = array_filter($rows, static fn(array $row): bool =>
            self::isVisible($preferences, (string)($row[$keyField] ?? ''))
        );
        usort($visible, static fn(array $a, array $b): int =>
            (int)($preferences[$a[$keyField]]['sort_order'] ?? 9999)
            <=> (int)($preferences[$b[$keyField]]['sort_order'] ?? 9999)
        );
        return $visible;
    }

    public static function render(array $preferences, array $content): string
    {
        $html = '<div class="dashboard-widget-layout">';
        foreach ($preferences as $key => $preference) {
            if ((int)($preference['visible'] ?? 0) !== 1 || !isset($content[$key])) continue;
            $size = in_array((string)($preference['size_key'] ?? ''), self::SIZES, true)
                ? (string)$preference['size_key']
                : 'wide';
            $safeSize = htmlspecialchars($size, ENT_QUOTES, 'UTF-8');
            $safeKey = htmlspecialchars((string)$key, ENT_QUOTES, 'UTF-8');
            $refresh = max(0, min(3600, (int)($preference['refresh_seconds'] ?? 0)));
            $drilldown = (int)($preference['drilldown_enabled'] ?? 0) === 1 ? '1' : '0';
            $filterMode = htmlspecialchars(self::filterMode($preference), ENT_QUOTES, 'UTF-8');
            $period = htmlspecialchars(self::defaultPeriod($preferences, (string)$key), ENT_QUOTES, 'UTF-8');
            $html .= '<div class="dashboard-widget-shell dashboard-widget-size-' . $safeSize
                . '" data-dashboard-widget="' . $safeKey
                . '" data-dashboard-refresh-seconds="' . $refresh
                . '" data-dashboard-drilldown-enabled="' . $drilldown
                . '" data-dashboard-filter-mode="' . $filterMode
                . '" data-dashboard-default-period="' . $period . '">';
            $html .= $content[$key];
            $html .= '</div>';
        }
        return $html . '</div>';
    }

    public static function filterMode(array $preference): string
    {
        $filters = json_decode((string)($preference['default_filters_json'] ?? ''), true);
        $mode = (string)($filters['mode'] ?? 'authorized_scope');
        return in_array($mode, self::FILTER_MODES, true) ? $mode : 'authorized_scope';
    }

    public static function defaultPeriod(array $preferences, string $key = 'summary_kpis'): string
    {
        $period = (string)($preferences[$key]['default_period_key'] ?? 'monthly');
        return in_array($period, self::PERIODS, true) ? $period : 'monthly';
    }
}
