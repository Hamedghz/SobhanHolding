<?php
require_once __DIR__ . '/Database.php';

class Auth
{
    public static function start(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_name('sobhan_session');
            session_start();
        }
    }

    public static function user(): ?array
    {
        self::start();
        if (empty($_SESSION['user_id'])) return null;
        static $user = null;
        if ($user === null) {
            $user = Database::fetch('SELECT id,name,email,username,employee_no,mobile,force_password_change,role,status,description,upload_quota_mb,department,role_key,sales_line,supervisor_id,organization_manager_id,org_unit_id,org_role_id,parent_user_id,access_scope,employee_panel_enabled,admin_panel_enabled,display_order,last_login_at FROM users WHERE id = ? AND status = "active"', [$_SESSION['user_id']]);
        }
        return $user ?: null;
    }

    public static function attempt(string $login, string $password): bool
    {
        self::start();
        $user = Database::fetch('SELECT * FROM users WHERE (email = ? OR username = ?) AND status = "active" LIMIT 1', [$login, $login]);
        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['role'] = $user['role'];
            Database::execute('UPDATE users SET last_login_at=NOW() WHERE id=?', [(int)$user['id']]);
            self::log((int)$user['id'], 'login', 'auth');
            return true;
        }
        return false;
    }

    public static function logout(): void
    {
        self::start();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }

    public static function requireLogin(): void
    {
        if (!self::user()) {
            header('Location: /login.php');
            exit;
        }
    }

    public static function requireRole(string $role): void
    {
        self::requireLogin();
        $user = self::user();
        if (($user['role'] ?? '') !== $role && !(($user['role'] ?? '') === 'super_admin' && $role === 'admin')) {
            http_response_code(403);
            echo 'دسترسی غیرمجاز';
            exit;
        }
    }

    public static function requireAnyRole(array $roles): void
    {
        self::requireLogin();
        $user = self::user();
        if (!in_array($user['role'] ?? '', $roles, true) && !(($user['role'] ?? '') === 'super_admin' && in_array('admin', $roles, true))) {
            http_response_code(403);
            echo 'دسترسی غیرمجاز';
            exit;
        }
    }

    public static function isAdmin(): bool
    {
        $user = self::user();
        return in_array($user['role'] ?? '', ['admin', 'super_admin'], true);
    }

    public static function isSuperAdmin(): bool
    {
        return (self::user()['role'] ?? '') === 'super_admin';
    }

    public static function canManageSystemTools(): bool
    {
        return self::isAdmin() && self::can('system_maintenance');
    }

    public static function isManager(): bool
    {
        $user = self::user();
        return ($user['role'] ?? '') === 'manager';
    }

    public static function isEmployee(): bool
    {
        $user = self::user();
        return ($user['role'] ?? '') === 'employee';
    }

    public static function can(string $moduleKey, string $action = 'view'): bool
    {
        $user = self::user();
        if (!$user) return false;
        if (in_array($user['role'] ?? '', ['admin', 'super_admin'], true)) return true;

        $aliases = [
            'view_ceo_dashboard' => ['ceo_dashboard'],
            'ceo_dashboard' => ['view_ceo_dashboard'],
            'view_sobhan_api_settings' => ['manage_sobhan_api_settings'],
            'view_ai_chat' => ['ai_chat'],
            'ai_chat' => ['view_ai_chat'],
            'ai_assistant' => ['use_ai_assistant'],
            'use_ai_assistant' => ['ai_assistant'],
            'manager_dashboard.ai' => ['manager_dashboard.ai_run'],
            'manager_dashboard.ai_run' => ['manager_dashboard.ai'],
            'messenger.live_location.send' => ['messenger.location.live'],
            'messenger.location.live' => ['messenger.live_location.send'],
            'messenger.message.moderate' => ['messenger.moderate'],
            'messenger.moderate' => ['messenger.message.moderate'],
        ];

        $column = match ($action) {
            'create' => 'can_create',
            'edit' => 'can_edit',
            'delete' => 'can_delete',
            default => 'can_view',
        };
        $permission = Database::fetch("SELECT {$column} allowed FROM user_permissions WHERE user_id = ? AND module_key = ? LIMIT 1", [(int)$user['id'], $moduleKey]);
        if ($permission) {
            return (int)$permission['allowed'] === 1;
        }

        foreach ($aliases[$moduleKey] ?? [] as $aliasKey) {
            $permission = Database::fetch("SELECT {$column} allowed FROM user_permissions WHERE user_id = ? AND module_key = ? LIMIT 1", [(int)$user['id'], $aliasKey]);
            if ($permission && (int)$permission['allowed'] === 1) {
                return true;
            }
        }

        if ($moduleKey === 'dashboard' && $action === 'view') return true;
        if (in_array($moduleKey, ['files', 'survey_results'], true) && in_array($action, ['view', 'create'], true) && self::isManager()) return true;
        if ($moduleKey === 'files' && in_array($action, ['view', 'create'], true) && self::isEmployee()) return true;
        return false;
    }

    public static function requirePermission(string $moduleKey, string $action = 'view'): void
    {
        self::requireLogin();
        if (!self::can($moduleKey, $action)) {
            http_response_code(403);
            echo 'دسترسی غیرمجاز';
            exit;
        }
    }

    public static function assignedEmployeeIds(?int $managerId = null): array
    {
        $user = self::user();
        $managerId = $managerId ?: (int)($user['id'] ?? 0);
        $rows = Database::fetchAll('SELECT employee_id FROM manager_employees WHERE manager_id = ?', [$managerId]);
        return array_map('intval', array_column($rows, 'employee_id'));
    }

    public static function canAccessEmployee(int $employeeId): bool
    {
        $user = self::user();
        if (!$user) return false;
        require_once __DIR__ . '/../lib/OrgAccess.php';
        return OrgAccess::canAccessUser($user, $employeeId);
    }

    public static function csrfToken(): string
    {
        self::start();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function verifyCsrf(?string $token): bool
    {
        self::start();
        return is_string($token) && hash_equals($_SESSION['csrf_token'] ?? '', $token);
    }

    public static function log(?int $userId, string $action, string $module, ?int $recordId = null): void
    {
        try {
            Database::execute('INSERT INTO activity_logs (user_id, action, module, record_id, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())', [
                $userId, $action, $module, $recordId, $_SERVER['REMOTE_ADDR'] ?? '', substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)
            ]);
        } catch (Throwable $e) {}
    }
}
