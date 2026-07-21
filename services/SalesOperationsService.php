<?php
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/SalesOperationsModule.php';
require_once __DIR__ . '/../lib/AppDate.php';

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

    public static function isSalesManagerUserId(int $userId): bool
    {
        if($userId<1)return false;$user=Database::fetch('SELECT id,role_key,status FROM users WHERE id=?',[$userId]);
        return (bool)$user&&$user['status']==='active'&&(in_array((string)$user['role_key'],self::SALES_MANAGER_ROLE_KEYS,true)||(bool)Database::fetch('SELECT id FROM sales_team_assignments WHERE sales_manager_id=? AND active=1 LIMIT 1',[$userId]));
    }

    public static function isSupervisorUserId(int $userId): bool
    {if($userId<1)return false;$user=Database::fetch('SELECT role_key,status FROM users WHERE id=?',[$userId]);return (bool)$user&&$user['status']==='active'&&in_array((string)$user['role_key'],self::SUPERVISOR_ROLE_KEYS,true);}

    public static function isVisitorUserId(int $userId): bool
    {if($userId<1)return false;$user=Database::fetch('SELECT role_key,status FROM users WHERE id=?',[$userId]);return (bool)$user&&$user['status']==='active'&&in_array((string)$user['role_key'],['VISITOR','visitor','ویزیتور'],true);}

    public static function requireSupervisorPermission(string $permission): void
    {
        Auth::requireLogin();
        if (!self::isSupervisor(Auth::user()) || (!self::canViewAll(Auth::user()) && !Auth::can($permission))) {
            http_response_code(403); echo 'دسترسی غیرمجاز'; exit;
        }
    }

    public static function ensureSalesManagerAccess(): void
    {
        Auth::requireLogin();
        if (!self::isSalesManager(Auth::user())) { http_response_code(403); echo 'دسترسی غیرمجاز'; exit; }
    }

    public static function requireSalesManagerPermission(string $permission): void
    {
        Auth::requireLogin();
        if (!self::isSalesManager(Auth::user()) || (!self::canViewAll(Auth::user()) && !Auth::can($permission))) {
            http_response_code(403); echo 'دسترسی غیرمجاز'; exit;
        }
    }

    public static function uiError(Throwable $error, string $fallback): string
    {
        if ($error instanceof InvalidArgumentException) return $error->getMessage();
        error_log('Sales operations: '.$error->getMessage());
        return $fallback;
    }

    public static function supervisorManagerId(int $supervisorId): ?int
    {
        $user = Database::fetch('SELECT organization_manager_id,parent_user_id,sales_line_id FROM users WHERE id=?', [$supervisorId]);
        foreach (['organization_manager_id','parent_user_id'] as $field) {
            if (!empty($user[$field])) return (int)$user[$field];
        }
        if (!empty($user['sales_line_id'])) {
            $line = Database::fetch('SELECT manager_user_id FROM sales_lines WHERE id=? AND active=1', [(int)$user['sales_line_id']]);
            if (!empty($line['manager_user_id'])) return (int)$line['manager_user_id'];
        }
        $row = Database::fetch('SELECT sales_manager_id FROM sales_team_assignments WHERE supervisor_id=? AND active=1 AND sales_manager_id IS NOT NULL ORDER BY id DESC LIMIT 1', [$supervisorId]);
        if ($row) return (int)$row['sales_manager_id'];
        return null;
    }

    public static function getSupervisorVisitors(int $supervisorId): array
    {
        $rows = Database::fetchAll(
            'SELECT u.id,u.name,u.username,u.employee_no,u.kara_system_code,u.sales_line,u.sales_line_id,u.role_key
             FROM users u
             LEFT JOIN org_roles r ON r.id=u.org_role_id
             WHERE u.status="active" AND (r.code="VISITOR" OR u.role_key IN ("VISITOR","visitor","ویزیتور"))
               AND (u.supervisor_id=? OR u.parent_user_id=?)
             ORDER BY u.display_order,u.name',
            [$supervisorId,$supervisorId]
        );
        if ($rows) return $rows;
        return Database::fetchAll(
            'SELECT u.id,u.name,u.username,u.employee_no,u.kara_system_code,u.sales_line,u.sales_line_id,u.role_key
             FROM users u
             WHERE u.status="active"
               AND u.supervisor_id IS NULL AND u.parent_user_id IS NULL AND u.sales_line_id IS NULL
               AND u.id IN (SELECT visitor_id FROM sales_team_assignments WHERE supervisor_id=? AND active=1)
             ORDER BY u.display_order,u.name',
            [$supervisorId]
        );
    }

    public static function assertVisitorBelongsToSupervisor(int $visitorId, int $supervisorId): bool
    {
        if ($visitorId <= 0) return true;
        $visitor = Database::fetch('SELECT id,supervisor_id,parent_user_id,sales_line_id FROM users WHERE id=? AND status="active"', [$visitorId]);
        if (!$visitor) return false;
        if (!empty($visitor['supervisor_id']) || !empty($visitor['parent_user_id']) || !empty($visitor['sales_line_id'])) {
            return (int)($visitor['supervisor_id'] ?: $visitor['parent_user_id']) === $supervisorId;
        }
        return (bool)Database::fetch('SELECT id FROM sales_team_assignments WHERE visitor_id=? AND supervisor_id=? AND active=1 LIMIT 1', [$visitorId,$supervisorId]);
    }

    public static function getSalesManagerSupervisorIds(int $managerId): array
    {
        $rows = Database::fetchAll(
            'SELECT DISTINCT u.id FROM users u
             LEFT JOIN org_roles r ON r.id=u.org_role_id
             WHERE u.status="active" AND (r.code="SALES_SUPERVISOR" OR u.role_key IN ("SALES_SUPERVISOR","supervisor","سرپرست فروش"))
               AND (u.organization_manager_id=? OR u.parent_user_id=?)
             ORDER BY u.id',
            [$managerId,$managerId]
        );
        if (!$rows) {
            $rows = Database::fetchAll(
                'SELECT DISTINCT u.id FROM users u
                 WHERE u.status="active" AND u.organization_manager_id IS NULL AND u.parent_user_id IS NULL
                   AND u.id IN (SELECT supervisor_id FROM sales_team_assignments WHERE sales_manager_id=? AND active=1)
                 ORDER BY u.id',
                [$managerId]
            );
        }
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
        $periodKey = trim((string)($input['period_key'] ?? ''));
        if ($periodKey !== '') {
            try {
                $period = AppDate::resolvePeriod(
                    $periodKey,
                    (string)($input['from'] ?? $input['date_from'] ?? ''),
                    (string)($input['to'] ?? $input['date_to'] ?? '')
                );
                return [$period['start_date'], $period['end_date']];
            } catch (InvalidArgumentException) {
                // Keep compatibility with the existing explicit date filters below.
            }
        }
        $from = AppDate::toGregorian((string)($input['from'] ?? $input['date_from'] ?? '')) ?: date('Y-m-01');
        $to = AppDate::toGregorian((string)($input['to'] ?? $input['date_to'] ?? '')) ?: date('Y-m-d');
        if ($from > $to) [$from,$to] = [$to,$from];
        return [$from, $to];
    }

    public static function validDate(string $date): bool
    {
        return AppDate::isValidDate($date);
    }

    public static function getSupervisorSalesSummary(int $supervisorId, array $filters = []): array
    {
        [$from,$to] = self::dateFilters($filters);
        $visitors = self::getSupervisorVisitors($supervisorId);
        $visitorIds = array_map('intval', array_column($visitors, 'id'));
        if (!$visitorIds) return ['net_sales'=>0,'invoice_count'=>0,'customer_count'=>0,'visitors'=>0,'rows'=>[],'data_source'=>'none'];

        if (Database::tableExists('vw_active_sales_aggregate_rows')) {
            $codes = array_values(array_unique(array_filter(array_map(
                static fn(array $visitor): string => trim((string)($visitor['employee_no'] ?? '')),
                $visitors
            ))));
            $names = array_values(array_unique(array_filter(array_map(
                static fn(array $visitor): string => trim((string)($visitor['employee_no'] ?? '')) === ''
                    ? trim((string)($visitor['name'] ?? ''))
                    : '',
                $visitors
            ))));
            $identityParts = [];
            $params = [$from, $to];
            if ($codes) {
                $identityParts[] = 'visitor_code IN (' . implode(',', array_fill(0, count($codes), '?')) . ')';
                array_push($params, ...$codes);
            }
            if ($names) {
                $identityParts[] = '((visitor_code IS NULL OR visitor_code="") AND visitor_name IN (' . implode(',', array_fill(0, count($names), '?')) . '))';
                array_push($params, ...$names);
            }
            if ($identityParts) {
                try {
                    $rows = Database::fetchAll(
                        'SELECT visitor_code,visitor_name,line_code,
                                COUNT(DISTINCT invoice_number) invoice_count,
                                COUNT(DISTINCT customer_code) customer_count,
                                COALESCE(SUM(net_amount),0) net_sales,
                                COALESCE(SUM(total_qty),0) qty
                         FROM vw_active_sales_aggregate_rows
                         WHERE invoice_date BETWEEN ? AND ?
                           AND (' . implode(' OR ', $identityParts) . ')
                         GROUP BY visitor_code,visitor_name,line_code
                         ORDER BY net_sales DESC',
                        $params
                    );
                    $totals = Database::fetch(
                        'SELECT COUNT(DISTINCT invoice_number) invoice_count,
                                COUNT(DISTINCT customer_code) customer_count,
                                COALESCE(SUM(net_amount),0) net_sales
                         FROM vw_active_sales_aggregate_rows
                         WHERE invoice_date BETWEEN ? AND ?
                           AND (' . implode(' OR ', $identityParts) . ')',
                        $params
                    ) ?: [];
                    $byCode = [];
                    $byName = [];
                    foreach ($visitors as $visitor) {
                        if (trim((string)($visitor['employee_no'] ?? '')) !== '') {
                            $byCode[(string)$visitor['employee_no']] = (int)$visitor['id'];
                        } elseif (trim((string)($visitor['name'] ?? '')) !== '') {
                            $byName[(string)$visitor['name']] = (int)$visitor['id'];
                        }
                    }
                    foreach ($rows as &$row) {
                        $row['user_id'] = $byCode[(string)($row['visitor_code'] ?? '')] ?? $byName[(string)($row['visitor_name'] ?? '')] ?? null;
                        $row['achievement_percent'] = null;
                    }
                    unset($row);
                    return [
                        'net_sales' => (float)($totals['net_sales'] ?? 0),
                        'invoice_count' => (int)($totals['invoice_count'] ?? 0),
                        'customer_count' => (int)($totals['customer_count'] ?? 0),
                        'visitors' => count($visitorIds),
                        'rows' => $rows,
                        'data_source' => 'active_sales_reference_view',
                    ];
                } catch (Throwable $e) {
                    error_log('Supervisor active sales view: ' . $e->getMessage());
                }
            }
        }

        if (!Database::tableExists('ceo_dashboard_visitors')) {
            return ['net_sales'=>0,'invoice_count'=>0,'customer_count'=>0,'visitors'=>count($visitorIds),'rows'=>[],'data_source'=>'none'];
        }
        $placeholders = implode(',', array_fill(0, count($visitorIds), '?'));
        $params = array_merge([$from,$to], $visitorIds);
        $rows = Database::fetchAll("SELECT user_id,visitor_name,line_code,SUM(sales_amount) net_sales,SUM(qty) qty,AVG(CASE WHEN target_amount>0 THEN sales_amount/target_amount*100 ELSE 0 END) achievement_percent FROM ceo_dashboard_visitors WHERE report_date BETWEEN ? AND ? AND user_id IN ({$placeholders}) GROUP BY user_id,visitor_name,line_code ORDER BY net_sales DESC", $params);
        return ['net_sales'=>array_sum(array_map(fn($r)=>(float)($r['net_sales'] ?? 0), $rows)), 'invoice_count'=>0, 'customer_count'=>0, 'visitors'=>count($visitorIds), 'rows'=>$rows, 'data_source'=>'legacy_ceo_dashboard_visitors'];
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
        if (!self::canViewAll($user) && (int)$user['id'] !== $supervisorId) throw new InvalidArgumentException('دسترسی ثبت اقدام برای این سرپرست وجود ندارد.');
        $visitorId = (int)($data['visitor_id'] ?? 0);
        if ($visitorId && !self::assertVisitorBelongsToSupervisor($visitorId, $supervisorId)) throw new InvalidArgumentException('ویزیتور انتخاب‌شده در زیرمجموعه این سرپرست نیست.');
        $managerId = self::supervisorManagerId($supervisorId);
        $dueDate = self::nullableDate($data['due_date'] ?? null);
        Database::execute('INSERT INTO supervisor_actions(supervisor_id,sales_manager_id,sales_line,section_id,visitor_id,customer_id,title,description,action_type,priority,status,due_date,dynamic_values_json,created_by,updated_by,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())', [$supervisorId,$managerId,$data['sales_line'] ?? null,(int)($data['section_id'] ?? 0) ?: null,$visitorId ?: null,(int)($data['customer_id'] ?? 0) ?: null,trim((string)$data['title']),trim((string)($data['description'] ?? '')),trim((string)($data['action_type'] ?? '')),self::validPriority($data['priority'] ?? 'normal'),self::validSupervisorStatus($data['status'] ?? 'open'),$dueDate,json_encode($data['dynamic_values'] ?? [], JSON_UNESCAPED_UNICODE),(int)$user['id'],(int)$user['id']]);
        $id = (int)Database::connection()->lastInsertId();
        self::logSupervisorAction($id, 'create', null, $data);
        if (!empty($data['add_to_planner'])) self::syncSupervisorActionToPlanner($id);
        try {
            require_once __DIR__ . '/ActionHubService.php';
            ActionHubService::mirrorLegacyAction('supervisor_actions', $id);
        } catch (Throwable $e) {
            error_log('Action hub supervisor mirror: ' . $e->getMessage());
        }
        return $id;
    }

    public static function createSalesAction(array $data): int
    {
        $user = Auth::user();
        $managerId = self::canViewAll($user) ? (int)($data['sales_manager_id'] ?? $user['id']) : (int)$user['id'];
        $supervisorId = (int)($data['supervisor_id'] ?? 0);
        $visitorId = (int)($data['visitor_id'] ?? 0);
        $assignedTo = (int)($data['assigned_to'] ?? 0) ?: $managerId;
        if (!self::canViewAll($user) && $supervisorId && !self::canAccessSupervisor($supervisorId, $user)) throw new InvalidArgumentException('سرپرست انتخاب‌شده خارج از تیم شماست.');
        if ($visitorId) {
            if ($supervisorId && !self::assertVisitorBelongsToSupervisor($visitorId, $supervisorId)) throw new InvalidArgumentException('ویزیتور انتخاب‌شده عضو تیم سرپرست نیست.');
            if (!$supervisorId && !self::canAccessSalesUser($visitorId, $managerId, $user)) throw new InvalidArgumentException('ویزیتور انتخاب‌شده خارج از تیم شماست.');
        }
        if (!self::canAccessSalesUser($assignedTo, $managerId, $user)) throw new InvalidArgumentException('مسئول انتخاب‌شده خارج از دامنه تیم فروش است.');
        $dueDate = self::nullableDate($data['due_date'] ?? null);
        Database::execute('INSERT INTO sales_actions(source_type,source_id,sales_manager_id,supervisor_id,visitor_id,customer_id,brand_id,product_id,sales_line,assigned_to,title,description,priority,status,due_date,dynamic_values_json,created_by,updated_by,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())', [$data['source_type'] ?? null,(int)($data['source_id'] ?? 0) ?: null,$managerId,(int)($data['supervisor_id'] ?? 0) ?: null,(int)($data['visitor_id'] ?? 0) ?: null,(int)($data['customer_id'] ?? 0) ?: null,(int)($data['brand_id'] ?? 0) ?: null,(int)($data['product_id'] ?? 0) ?: null,$data['sales_line'] ?? null,(int)($data['assigned_to'] ?? 0) ?: $managerId,trim((string)$data['title']),trim((string)($data['description'] ?? '')),self::validPriority($data['priority'] ?? 'normal'),self::validActionStatus($data['status'] ?? 'open'),$dueDate,json_encode($data['dynamic_values'] ?? [], JSON_UNESCAPED_UNICODE),(int)$user['id'],(int)$user['id']]);
        $id = (int)Database::connection()->lastInsertId();
        if (!empty($data['add_to_planner'])) self::syncSalesActionToPlanner($id);
        try {
            require_once __DIR__ . '/ActionHubService.php';
            ActionHubService::mirrorLegacyAction('sales_actions', $id);
        } catch (Throwable $e) {
            error_log('Action hub sales mirror: ' . $e->getMessage());
        }
        return $id;
    }

    public static function canAccessSalesUser(int $userId, int $managerId, ?array $viewer = null): bool
    {
        $viewer = $viewer ?: Auth::user();
        if ($userId < 1 || !$viewer) return false;
        if (self::canViewAll($viewer)) return (bool)Database::fetch('SELECT id FROM users WHERE id=? AND status="active"', [$userId]);
        if ($userId === $managerId) return true;
        foreach (self::getSalesManagerSupervisorIds($managerId) as $supervisorId) {
            if ($userId === $supervisorId || self::assertVisitorBelongsToSupervisor($userId, $supervisorId)) return true;
        }
        return false;
    }

    public static function getSalesManagerTeamUserIds(int $managerId, ?array $viewer = null): array
    {
        $viewer = $viewer ?: Auth::user();
        if (self::canViewAll($viewer)) return array_map('intval',array_column(Database::fetchAll('SELECT id FROM users WHERE status="active"'),'id'));
        $ids=[$managerId];
        foreach(self::getSalesManagerSupervisorIds($managerId) as $supervisorId){$ids[]=$supervisorId;foreach(self::getSupervisorVisitors($supervisorId) as $visitor)$ids[]=(int)$visitor['id'];}
        return array_values(array_unique(array_filter($ids)));
    }

    public static function syncSupervisorActionToPlanner(int $actionId): ?int
    {
        return self::syncActionToPlanner('supervisor_actions', $actionId, 'supervisor_id');
    }

    public static function syncSalesActionToPlanner(int $actionId): ?int
    {
        return self::syncActionToPlanner('sales_actions', $actionId, 'assigned_to');
    }

    public static function plannerAvailable(): bool
    {
        return Database::tableExists('work_planner_tasks') && Database::columnExists('work_planner_tasks','related_module') && Database::columnExists('work_planner_tasks','related_record_id');
    }

    private static function syncActionToPlanner(string $table, int $actionId, string $ownerField): ?int
    {
        if (!in_array($table, ['supervisor_actions','sales_actions'], true) || !self::plannerAvailable()) return null;
        $pdo = Database::connection(); $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) $pdo->beginTransaction();
        try {
            $stmt=$pdo->prepare("SELECT * FROM {$table} WHERE id=? FOR UPDATE");$stmt->execute([$actionId]);$action=$stmt->fetch(PDO::FETCH_ASSOC);
            if (!$action) { if($ownsTransaction)$pdo->rollBack(); return null; }
            if (!empty($action['planner_task_id'])) { if($ownsTransaction)$pdo->commit(); return (int)$action['planner_task_id']; }
            $existing=Database::fetch('SELECT id FROM work_planner_tasks WHERE related_module=? AND related_record_id=? ORDER BY id LIMIT 1',[$table,$actionId]);
            if ($existing) {$taskId=(int)$existing['id'];} else {
                $owner=(int)($action[$ownerField]?:($action['sales_manager_id']??0));$assignedBy=(int)($action['created_by']?:$owner);
                Database::execute('INSERT INTO work_planner_tasks(user_id,employee_id,assigned_by,title,description,task_type,priority,status,due_date,related_module,related_record_id,is_visible_on_dashboard,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,1,NOW(),NOW())',[$owner,$owner,$assignedBy,$action['title'],$action['description'],'sales_action',$action['priority'],'todo',$action['due_date'],$table,$actionId]);
                $taskId=(int)$pdo->lastInsertId();
            }
            Database::execute("UPDATE {$table} SET planner_task_id=? WHERE id=?",[$taskId,$actionId]);
            if($ownsTransaction)$pdo->commit();return $taskId;
        } catch(Throwable $e) {if($ownsTransaction&&$pdo->inTransaction())$pdo->rollBack();throw $e;}
    }

    public static function logSupervisorAction(int $actionId, string $action, mixed $old, mixed $new): void
    {
        try { Database::execute('INSERT INTO supervisor_action_logs(action_id,action,old_value_json,new_value_json,performed_by,created_at) VALUES (?,?,?,?,?,NOW())', [$actionId,$action,json_encode($old,JSON_UNESCAPED_UNICODE),json_encode($new,JSON_UNESCAPED_UNICODE),(int)(Auth::user()['id'] ?? 0)]); } catch (Throwable $e) { error_log('Sales action audit: '.$e->getMessage()); }
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

    private static function nullableDate(mixed $value): ?string
    {
        $value = trim((string)$value);
        if ($value === '') return null;
        $date = AppDate::toGregorian($value);
        if ($date === null) throw new InvalidArgumentException('تاریخ مهلت معتبر نیست.');
        return $date;
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
