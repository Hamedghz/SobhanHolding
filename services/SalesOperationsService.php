<?php
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/SalesOperationsModule.php';

class SalesOperationsService
{
    public const SALES_MANAGER_ROLE_KEYS = ['SALES_MANAGER','sales_manager','مدیر فروش'];
    public const SUPERVISOR_ROLE_KEYS = ['SALES_SUPERVISOR','supervisor','سرپرست فروش'];

    public static function boot(): void
    {
        SalesOperationsModule::repair(Database::connection());
    }

    public static function isSalesManager(?array $user = null): bool
    {
        $user = $user ?: Auth::user();
        return Auth::isAdmin() || in_array((string)($user['role_key'] ?? ''), self::SALES_MANAGER_ROLE_KEYS, true) || Auth::can('sales_manager.supervisors.view');
    }

    public static function isSupervisor(?array $user = null): bool
    {
        $user = $user ?: Auth::user();
        return Auth::isAdmin() || in_array((string)($user['role_key'] ?? ''), self::SUPERVISOR_ROLE_KEYS, true) || Auth::can('supervisor.panel.view');
    }

    public static function canViewAll(?array $user = null): bool
    {
        $user = $user ?: Auth::user();
        return in_array((string)($user['role'] ?? ''), ['admin','super_admin'], true);
    }

    public static function ensureSupervisorAccess(): void
    {
        Auth::requireLogin();
        if (!self::isSupervisor(Auth::user())) { http_response_code(403); echo 'دسترسی غیرمجاز'; exit; }
    }

    public static function ensureSalesManagerAccess(): void
    {
        Auth::requireLogin();
        if (!self::isSalesManager(Auth::user())) { http_response_code(403); echo 'دسترسی غیرمجاز'; exit; }
    }

    public static function supervisorManagerId(int $supervisorId): ?int
    {
        $row = Database::fetch('SELECT sales_manager_id FROM sales_team_assignments WHERE supervisor_id=? AND active=1 AND sales_manager_id IS NOT NULL ORDER BY id DESC LIMIT 1', [$supervisorId]);
        if ($row) return (int)$row['sales_manager_id'];
        $user = Database::fetch('SELECT organization_manager_id,parent_user_id,supervisor_id FROM users WHERE id=?', [$supervisorId]);
        foreach (['organization_manager_id','parent_user_id','supervisor_id'] as $field) {
            if (!empty($user[$field])) return (int)$user[$field];
        }
        return null;
    }

    public static function getSupervisorVisitors(int $supervisorId): array
    {
        $rows = Database::fetchAll('SELECT u.id,u.name,u.sales_line,u.role_key FROM users u WHERE u.status="active" AND (u.supervisor_id=? OR u.parent_user_id=? OR u.id IN (SELECT visitor_id FROM sales_team_assignments WHERE supervisor_id=? AND active=1)) ORDER BY u.display_order,u.name', [$supervisorId,$supervisorId,$supervisorId]);
        return $rows;
    }

    public static function assertVisitorBelongsToSupervisor(int $visitorId, int $supervisorId): bool
    {
        if ($visitorId <= 0) return true;
        return (bool)Database::fetch('SELECT id FROM users WHERE id=? AND status="active" AND (supervisor_id=? OR parent_user_id=? OR id IN (SELECT visitor_id FROM sales_team_assignments WHERE supervisor_id=? AND active=1)) LIMIT 1', [$visitorId,$supervisorId,$supervisorId,$supervisorId]);
    }

    public static function getSalesManagerSupervisorIds(int $managerId): array
    {
        $rows = Database::fetchAll('SELECT DISTINCT u.id FROM users u WHERE u.status="active" AND (u.organization_manager_id=? OR u.parent_user_id=? OR u.id IN (SELECT supervisor_id FROM sales_team_assignments WHERE sales_manager_id=? AND active=1)) ORDER BY u.id', [$managerId,$managerId,$managerId]);
        return array_map('intval', array_column($rows, 'id'));
    }

    public static function canAccessSupervisor(int $supervisorId, ?array $viewer = null): bool
    {
        $viewer = $viewer ?: Auth::user();
        if (!$viewer) return false;
        if (self::canViewAll($viewer)) return true;
        if ((int)$viewer['id'] === $supervisorId) return true;
        return in_array($supervisorId, self::getSalesManagerSupervisorIds((int)$viewer['id']), true);
    }

    public static function dateFilters(array $input): array
    {
        $from = trim((string)($input['from'] ?? '')) ?: date('Y-m-01');
        $to = trim((string)($input['to'] ?? '')) ?: date('Y-m-d');
        return [$from, $to];
    }

    public static function getSupervisorSalesSummary(int $supervisorId, array $filters = []): array
    {
        [$from,$to] = self::dateFilters($filters);
        $visitorIds = array_map('intval', array_column(self::getSupervisorVisitors($supervisorId), 'id'));
        if (!$visitorIds || !Database::tableExists('ceo_dashboard_visitors')) return ['net_sales'=>0,'invoice_count'=>0,'customer_count'=>0,'visitors'=>0,'rows'=>[]];
        $placeholders = implode(',', array_fill(0, count($visitorIds), '?'));
        $params = array_merge([$from,$to], $visitorIds);
        $rows = Database::fetchAll("SELECT user_id,visitor_name,line_code,SUM(sales_amount) net_sales,SUM(qty) qty,AVG(CASE WHEN target_amount>0 THEN sales_amount/target_amount*100 ELSE 0 END) achievement_percent FROM ceo_dashboard_visitors WHERE report_date BETWEEN ? AND ? AND user_id IN ({$placeholders}) GROUP BY user_id,visitor_name,line_code ORDER BY net_sales DESC", $params);
        return ['net_sales'=>array_sum(array_map(fn($r)=>(float)($r['net_sales'] ?? 0), $rows)), 'invoice_count'=>0, 'customer_count'=>0, 'visitors'=>count($visitorIds), 'rows'=>$rows];
    }

    public static function getSalesManagerSupervisorSummary(int $managerId, array $filters = []): array
    {
        $viewer = Auth::user();
        $supervisorIds = self::canViewAll($viewer) ? array_map('intval', array_column(Database::fetchAll('SELECT id FROM users WHERE status="active" AND (role_key IN ("SALES_SUPERVISOR","supervisor") OR id IN (SELECT supervisor_id FROM sales_team_assignments WHERE active=1)) ORDER BY name'), 'id')) : self::getSalesManagerSupervisorIds($managerId);
        if (!$supervisorIds) return [];
        $placeholders = implode(',', array_fill(0, count($supervisorIds), '?'));
        $supervisors = Database::fetchAll("SELECT id,name,sales_line FROM users WHERE id IN ({$placeholders}) ORDER BY display_order,name", $supervisorIds);
        $result = [];
        foreach ($supervisors as $sup) {
            $summary = self::getSupervisorSalesSummary((int)$sup['id'], $filters);
            $actions = Database::fetch('SELECT COUNT(*) total, SUM(status IN ("open","in_progress")) open_count, SUM(priority="urgent") urgent_count, SUM(due_date<CURDATE() AND status NOT IN ("done","cancelled")) overdue_count FROM supervisor_actions WHERE supervisor_id=?', [(int)$sup['id']]) ?: [];
            $reports = Database::fetch('SELECT COUNT(*) total, SUM(status IN ("submitted_by_supervisor","pending_sales_manager_review")) pending_count FROM sales_supervisor_reports WHERE supervisor_id=?', [(int)$sup['id']]) ?: [];
            $result[] = ['supervisor'=>$sup, 'summary'=>$summary, 'actions'=>$actions, 'reports'=>$reports, 'manager_suggestion'=>self::managerSuggestion($summary, $actions)];
        }
        return $result;
    }

    private static function managerSuggestion(array $summary, array $actions): string
    {
        if ((int)($actions['overdue_count'] ?? 0) > 0) return 'اولویت با تعیین تکلیف اقدامات سررسید گذشته و پیگیری مستقیم مدیر فروش است.';
        if ((float)($summary['net_sales'] ?? 0) <= 0) return 'نیاز به بررسی مسیر، مشتریان بدون خرید و برنامه اصلاحی روز بعد وجود دارد.';
        if ((int)($actions['urgent_count'] ?? 0) > 0) return 'اقدامات فوری باید در جلسه روزانه فروش بررسی و مالک پیگیری مشخص شود.';
        return 'وضعیت قابل پیگیری است؛ تمرکز روی رشد پوشش مشتری و کنترل اجرای اسکریپت فروش باشد.';
    }

    public static function getAssignedScriptsForSupervisor(int $supervisorId): array
    {
        $user = Database::fetch('SELECT sales_line FROM users WHERE id=?', [$supervisorId]) ?: [];
        return Database::fetchAll('SELECT DISTINCT s.* FROM sales_scripts s LEFT JOIN sales_script_assignments a ON a.script_id=s.id AND a.active=1 WHERE s.active=1 AND (s.supervisor_id=? OR a.assigned_to_type="supervisor" AND a.assigned_to_id=? OR s.sales_line=? OR a.sales_line=? OR s.target_scope="all") ORDER BY s.created_at DESC', [$supervisorId,$supervisorId,$user['sales_line'] ?? '',$user['sales_line'] ?? '']);
    }

    public static function createSupervisorAction(array $data): int
    {
        $user = Auth::user();
        $supervisorId = (int)($data['supervisor_id'] ?? ($user['id'] ?? 0));
        if (!self::canViewAll($user) && (int)$user['id'] !== $supervisorId) throw new RuntimeException('دسترسی ثبت اقدام برای این سرپرست وجود ندارد.');
        $visitorId = (int)($data['visitor_id'] ?? 0);
        if ($visitorId && !self::assertVisitorBelongsToSupervisor($visitorId, $supervisorId)) throw new RuntimeException('ویزیتور انتخاب‌شده در زیرمجموعه این سرپرست نیست.');
        $managerId = self::supervisorManagerId($supervisorId);
        Database::execute('INSERT INTO supervisor_actions(supervisor_id,sales_manager_id,sales_line,section_id,visitor_id,customer_id,title,description,action_type,priority,status,due_date,dynamic_values_json,created_by,updated_by,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())', [$supervisorId,$managerId,$data['sales_line'] ?? null,(int)($data['section_id'] ?? 0) ?: null,$visitorId ?: null,(int)($data['customer_id'] ?? 0) ?: null,trim((string)$data['title']),trim((string)($data['description'] ?? '')),trim((string)($data['action_type'] ?? '')),self::validPriority($data['priority'] ?? 'normal'),self::validSupervisorStatus($data['status'] ?? 'open'),trim((string)($data['due_date'] ?? '')) ?: null,json_encode($data['dynamic_values'] ?? [], JSON_UNESCAPED_UNICODE),(int)$user['id'],(int)$user['id']]);
        $id = (int)Database::connection()->lastInsertId();
        self::logSupervisorAction($id, 'create', null, $data);
        if (!empty($data['add_to_planner'])) self::syncSupervisorActionToPlanner($id);
        return $id;
    }

    public static function createSalesAction(array $data): int
    {
        $user = Auth::user();
        $managerId = self::canViewAll($user) ? (int)($data['sales_manager_id'] ?? $user['id']) : (int)$user['id'];
        Database::execute('INSERT INTO sales_actions(source_type,source_id,sales_manager_id,supervisor_id,visitor_id,customer_id,brand_id,product_id,sales_line,assigned_to,title,description,priority,status,due_date,dynamic_values_json,created_by,updated_by,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())', [$data['source_type'] ?? null,(int)($data['source_id'] ?? 0) ?: null,$managerId,(int)($data['supervisor_id'] ?? 0) ?: null,(int)($data['visitor_id'] ?? 0) ?: null,(int)($data['customer_id'] ?? 0) ?: null,(int)($data['brand_id'] ?? 0) ?: null,(int)($data['product_id'] ?? 0) ?: null,$data['sales_line'] ?? null,(int)($data['assigned_to'] ?? 0) ?: $managerId,trim((string)$data['title']),trim((string)($data['description'] ?? '')),self::validPriority($data['priority'] ?? 'normal'),self::validActionStatus($data['status'] ?? 'open'),trim((string)($data['due_date'] ?? '')) ?: null,json_encode($data['dynamic_values'] ?? [], JSON_UNESCAPED_UNICODE),(int)$user['id'],(int)$user['id']]);
        $id = (int)Database::connection()->lastInsertId();
        if (!empty($data['add_to_planner'])) self::syncSalesActionToPlanner($id);
        return $id;
    }

    public static function syncSupervisorActionToPlanner(int $actionId): ?int
    {
        if (!Database::tableExists('work_planner_tasks')) return null;
        $action = Database::fetch('SELECT * FROM supervisor_actions WHERE id=?', [$actionId]);
        if (!$action) return null;
        if (!empty($action['planner_task_id'])) return (int)$action['planner_task_id'];
        $existing = Database::fetch('SELECT id FROM work_planner_tasks WHERE related_module="supervisor_actions" AND related_record_id=? LIMIT 1', [$actionId]);
        if ($existing) { Database::execute('UPDATE supervisor_actions SET planner_task_id=? WHERE id=?', [(int)$existing['id'],$actionId]); return (int)$existing['id']; }
        Database::execute('INSERT INTO work_planner_tasks(user_id,employee_id,assigned_by,title,description,task_type,priority,status,due_date,related_module,related_record_id,is_visible_on_dashboard,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,1,NOW(),NOW())', [(int)$action['supervisor_id'],(int)$action['supervisor_id'],(int)($action['created_by'] ?: $action['supervisor_id']),$action['title'],$action['description'],'sales_action',$action['priority'],'todo',$action['due_date'],'supervisor_actions',$actionId]);
        $taskId = (int)Database::connection()->lastInsertId();
        Database::execute('UPDATE supervisor_actions SET planner_task_id=? WHERE id=?', [$taskId,$actionId]);
        return $taskId;
    }

    public static function syncSalesActionToPlanner(int $actionId): ?int
    {
        if (!Database::tableExists('work_planner_tasks')) return null;
        $action = Database::fetch('SELECT * FROM sales_actions WHERE id=?', [$actionId]);
        if (!$action) return null;
        if (!empty($action['planner_task_id'])) return (int)$action['planner_task_id'];
        $existing = Database::fetch('SELECT id FROM work_planner_tasks WHERE related_module="sales_actions" AND related_record_id=? LIMIT 1', [$actionId]);
        if ($existing) { Database::execute('UPDATE sales_actions SET planner_task_id=? WHERE id=?', [(int)$existing['id'],$actionId]); return (int)$existing['id']; }
        Database::execute('INSERT INTO work_planner_tasks(user_id,employee_id,assigned_by,title,description,task_type,priority,status,due_date,related_module,related_record_id,is_visible_on_dashboard,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,1,NOW(),NOW())', [(int)($action['assigned_to'] ?: $action['sales_manager_id']),(int)($action['assigned_to'] ?: $action['sales_manager_id']),(int)($action['created_by'] ?: $action['sales_manager_id']),$action['title'],$action['description'],'sales_action',$action['priority'],'todo',$action['due_date'],'sales_actions',$actionId]);
        $taskId = (int)Database::connection()->lastInsertId();
        Database::execute('UPDATE sales_actions SET planner_task_id=? WHERE id=?', [$taskId,$actionId]);
        return $taskId;
    }

    public static function logSupervisorAction(int $actionId, string $action, mixed $old, mixed $new): void
    {
        try { Database::execute('INSERT INTO supervisor_action_logs(action_id,action,old_value_json,new_value_json,performed_by,created_at) VALUES (?,?,?,?,?,NOW())', [$actionId,$action,json_encode($old,JSON_UNESCAPED_UNICODE),json_encode($new,JSON_UNESCAPED_UNICODE),(int)(Auth::user()['id'] ?? 0)]); } catch (Throwable $e) {}
    }

    public static function validPriority(string $priority): string
    {
        return in_array($priority, ['low','normal','high','urgent'], true) ? $priority : 'normal';
    }

    public static function validActionStatus(string $status): string
    {
        return in_array($status, ['open','in_progress','done','cancelled','overdue'], true) ? $status : 'open';
    }

    public static function validSupervisorStatus(string $status): string
    {
        return in_array($status, ['draft','open','in_progress','done','cancelled','needs_manager_review'], true) ? $status : 'open';
    }

    public static function statusLabel(string $status): string
    {
        return ['submitted_by_supervisor'=>'ارسال‌شده توسط سرپرست','pending_sales_manager_review'=>'در انتظار بررسی مدیر فروش','reviewed_by_sales_manager'=>'بررسی‌شده','needs_correction'=>'نیازمند اصلاح','converted_to_action'=>'تبدیل‌شده به اقدام','closed'=>'بسته‌شده','open'=>'باز','in_progress'=>'در حال پیگیری','done'=>'انجام‌شده','cancelled'=>'لغوشده','overdue'=>'سررسید گذشته','draft'=>'پیش‌نویس','submitted'=>'ارسال‌شده','under_review'=>'در حال بررسی','approved'=>'تأیید شد','rejected'=>'رد شد','needs_revision'=>'نیازمند بازبینی','converted_to_purchase_order'=>'تبدیل‌شده به اردر خرید'][$status] ?? $status;
    }

    public static function priorityLabel(string $priority): string
    {
        return ['low'=>'پایین','normal'=>'متوسط','high'=>'بالا','urgent'=>'فوری'][$priority] ?? $priority;
    }

    public static function dailyLogDefaults(): array
    {
        return ['time_spent'=>'امروز روی چه موضوعاتی وقت گذاشتم؟','reports_reviewed'=>'چه گزارش‌هایی بررسی شد؟','supervisors_reviewed'=>'کدام سرپرستان بررسی شدند؟','visitors_followup'=>'کدام ویزیتورها نیازمند پیگیری بودند؟','key_customers'=>'کدام مشتریان کلیدی بررسی شدند؟','market_problems'=>'چه مشکلاتی از بازار دریافت شد؟','actions_defined'=>'چه اقداماتی تعریف شد؟','purchase_suggestions'=>'چه پیشنهاد خریدی ثبت شد؟','management_decisions'=>'چه مواردی نیازمند تصمیم مدیریت است؟','tomorrow_plan'=>'برنامه فردا چیست؟'];
    }
}
