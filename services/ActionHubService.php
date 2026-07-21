<?php

require_once __DIR__ . '/../core/ActionHubModule.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../lib/AppDate.php';
require_once __DIR__ . '/../lib/OrgAccess.php';
require_once __DIR__ . '/WorkPlannerService.php';

final class ActionHubService
{
    public const FIELD_TYPES = [
        'short_text' => 'متن کوتاه',
        'long_text' => 'متن بلند',
        'number' => 'عدد',
        'money' => 'مبلغ',
        'percentage' => 'درصد',
        'jalali_date' => 'تاریخ شمسی',
        'datetime' => 'تاریخ و ساعت',
        'time' => 'ساعت',
        'single_select' => 'انتخاب تکی',
        'multi_select' => 'انتخاب چندتایی',
        'yes_no' => 'بله / خیر',
        'checkbox' => 'چک‌باکس',
        'user' => 'کاربر',
        'org_unit' => 'واحد سازمانی',
        'sales_line' => 'لاین فروش',
        'file' => 'فایل',
        'url' => 'پیوند',
        'calculated' => 'محاسباتی',
        'readonly' => 'فقط خواندنی',
        'action_link' => 'پیوند اقدام',
        'planner_link' => 'پیوند برنامه کاری',
    ];

    public const STATUSES = [
        'new' => 'جدید',
        'in_progress' => 'در حال انجام',
        'paused' => 'متوقف',
        'needs_review' => 'نیازمند بررسی',
        'done' => 'انجام‌شده',
        'cancelled' => 'لغوشده',
        'overdue' => 'سررسید گذشته',
    ];

    public const PRIORITIES = [
        'low' => 'کم',
        'normal' => 'عادی',
        'high' => 'بالا',
        'urgent' => 'فوری',
    ];

    public const SOURCES = [
        'manager_report' => 'گزارش مدیر',
        'daily_work_report' => 'گزارش کار روزانه',
        'planner' => 'برنامه کاری',
        'meeting_decision' => 'مصوبه جلسه',
        'okr' => 'OKR',
        'kpi' => 'KPI',
        'correspondence' => 'مکاتبه',
        'manual' => 'اقدام دستی',
        'ai_suggestion' => 'پیشنهاد هوش مصنوعی',
        'sales_actions' => 'اقدام فروش قدیمی',
        'supervisor_actions' => 'اقدام سرپرست قدیمی',
    ];

    public static function boot(): void
    {
        ActionHubModule::repair(Database::connection());
    }

    public static function canView(?array $actor = null): bool
    {
        $actor ??= Auth::user();
        if (!$actor) return false;
        if (
            OrgAccess::isAdmin($actor)
            || self::isOrganizationalAssigner($actor)
            || Auth::can('action_hub.view')
            || Auth::can('action_hub.create_own')
            || Auth::can('action_hub.assign')
            || Auth::can('sales_manager.actions.manage')
            || Auth::can('supervisor.actions.manage')
        ) return true;
        try {
            return Database::tableExists('actions') && (bool)Database::fetch(
                'SELECT id FROM actions WHERE assigned_to=? OR assigned_by=? LIMIT 1',
                [(int)$actor['id'],(int)$actor['id']]
            );
        } catch (Throwable) {
            return false;
        }
    }

    public static function canCreateOwn(?array $actor = null): bool
    {
        $actor ??= Auth::user();
        return (bool)$actor && (
            OrgAccess::isAdmin($actor)
            || self::isOrganizationalAssigner($actor)
            || Auth::can('action_hub.create_own', 'create')
            || Auth::can('action_hub.assign', 'create')
            || Auth::can('sales_manager.actions.manage', 'create')
            || Auth::can('supervisor.actions.manage', 'create')
        );
    }

    public static function canAssign(?array $actor = null): bool
    {
        $actor ??= Auth::user();
        return (bool)$actor && (
            OrgAccess::isAdmin($actor)
            || self::isOrganizationalAssigner($actor)
            || Auth::can('action_hub.assign', 'create')
            || Auth::can('sales_manager.actions.manage', 'create')
            || Auth::can('supervisor.actions.manage', 'create')
        );
    }

    public static function canManageTypes(): bool
    {
        return Auth::isAdmin() || Auth::can('action_hub.manage_types', 'edit');
    }

    public static function canManageTemplates(): bool
    {
        return Auth::isAdmin()
            || Auth::can('action_hub.manage_templates', 'edit')
            || Auth::can('sales_manager.scripts.manage', 'edit');
    }

    public static function assignableUsers(array $actor): array
    {
        $ids = self::canAssign($actor) ? OrgAccess::accessibleUserIds($actor) : [(int)$actor['id']];
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids) return [];
        $sql = 'SELECT u.id,u.name,u.employee_no,u.role_key,u.org_unit_id,ou.title org_unit_title
                FROM users u LEFT JOIN org_units ou ON ou.id=u.org_unit_id
                WHERE u.status="active" AND u.id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')
                ORDER BY CASE WHEN u.id=? THEN 0 ELSE 1 END,u.display_order,u.name';
        return Database::fetchAll($sql, array_merge($ids, [(int)$actor['id']]));
    }

    public static function types(bool $activeOnly = true): array
    {
        return Database::fetchAll(
            'SELECT * FROM action_types ' . ($activeOnly ? 'WHERE active=1 ' : '') . 'ORDER BY sort_order,title'
        );
    }

    public static function templates(bool $activeOnly = true): array
    {
        return Database::fetchAll(
            'SELECT t.*,at.title action_type_title,COUNT(f.id) field_count
             FROM action_templates t
             JOIN action_types at ON at.id=t.action_type_id
             LEFT JOIN action_template_fields f ON f.template_id=t.id AND f.active=1
             ' . ($activeOnly ? 'WHERE t.active=1 AND at.active=1 ' : '') . '
             GROUP BY t.id ORDER BY t.active DESC,t.title'
        );
    }

    public static function template(int $id): ?array
    {
        if ($id < 1) return null;
        $template = Database::fetch(
            'SELECT t.*,at.title action_type_title FROM action_templates t
             JOIN action_types at ON at.id=t.action_type_id WHERE t.id=?',
            [$id]
        );
        if (!$template) return null;
        $template['fields'] = self::templateFields($id);
        return $template;
    }

    public static function templateFields(int $templateId): array
    {
        $rows = Database::fetchAll(
            'SELECT * FROM action_template_fields WHERE template_id=? AND active=1 ORDER BY sort_order,id',
            [$templateId]
        );
        foreach ($rows as &$row) $row['options'] = self::decodeOptions($row['options_json'] ?? null);
        unset($row);
        return $rows;
    }

    public static function saveType(array $input, int $actorId): int
    {
        if (!self::canManageTypes()) throw new DomainException('مجوز مدیریت انواع اقدام را ندارید.');
        $id = max(0, (int)($input['id'] ?? 0));
        $title = trim((string)($input['title'] ?? ''));
        $code = self::safeKey((string)($input['code'] ?? ''));
        if ($title === '' || $code === '') throw new InvalidArgumentException('عنوان و کد نوع اقدام الزامی است.');
        $color = preg_match('/^#[0-9a-fA-F]{6}$/', (string)($input['color'] ?? ''))
            ? (string)$input['color'] : '#2563eb';
        $params = [
            $code, self::cut($title, 190), self::nullableText($input['description'] ?? null),
            $color, self::cut(trim((string)($input['icon'] ?? '')), 80) ?: null,
            !empty($input['active']) ? 1 : 0, !empty($input['requires_approval']) ? 1 : 0,
            self::cut(trim((string)($input['required_fields_csv'] ?? '')), 500) ?: null,
            (int)($input['sort_order'] ?? 0), $actorId,
        ];
        if ($id) {
            Database::execute(
                'UPDATE action_types SET code=?,title=?,description=?,color=?,icon=?,active=?,requires_approval=?,
                 required_fields_csv=?,sort_order=?,created_by=COALESCE(created_by,?),updated_at=NOW() WHERE id=?',
                array_merge($params, [$id])
            );
            return $id;
        }
        Database::execute(
            'INSERT INTO action_types(code,title,description,color,icon,active,requires_approval,required_fields_csv,
             sort_order,created_by,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,NOW(),NOW())',
            $params
        );
        return (int)Database::lastInsertId();
    }

    public static function saveTemplate(array $input, int $actorId): int
    {
        if (!self::canManageTemplates()) throw new DomainException('مجوز مدیریت قالب‌های اقدام را ندارید.');
        $id = max(0, (int)($input['id'] ?? 0));
        $typeId = (int)($input['action_type_id'] ?? 0);
        $title = trim((string)($input['title'] ?? ''));
        $code = self::safeKey((string)($input['template_code'] ?? ''));
        if (!$typeId || $title === '' || $code === '') throw new InvalidArgumentException('نوع، عنوان و کد قالب الزامی است.');
        if (!Database::fetch('SELECT id FROM action_types WHERE id=?', [$typeId])) throw new InvalidArgumentException('نوع اقدام معتبر نیست.');
        $params = [
            $typeId, $code, self::cut($title, 190), self::nullableText($input['description'] ?? null),
            self::nullableText($input['instructions'] ?? null), !empty($input['active']) ? 1 : 0, $actorId,
        ];
        if ($id) {
            Database::execute(
                'UPDATE action_templates SET action_type_id=?,template_code=?,title=?,description=?,instructions=?,
                 active=?,created_by=COALESCE(created_by,?),updated_at=NOW() WHERE id=?',
                array_merge($params, [$id])
            );
            return $id;
        }
        Database::execute(
            'INSERT INTO action_templates(action_type_id,template_code,title,description,instructions,active,created_by,created_at,updated_at)
             VALUES (?,?,?,?,?,?,?,NOW(),NOW())',
            $params
        );
        return (int)Database::lastInsertId();
    }

    public static function saveTemplateField(array $input): int
    {
        if (!self::canManageTemplates()) throw new DomainException('مجوز مدیریت فیلدهای قالب را ندارید.');
        $id = max(0, (int)($input['id'] ?? 0));
        $templateId = (int)($input['template_id'] ?? 0);
        $key = self::safeKey((string)($input['field_key'] ?? ''));
        $label = trim((string)($input['field_label'] ?? ''));
        $type = (string)($input['field_type'] ?? 'short_text');
        if (!$templateId || $key === '' || $label === '') throw new InvalidArgumentException('قالب، کلید و عنوان فیلد الزامی است.');
        if (!isset(self::FIELD_TYPES[$type])) throw new InvalidArgumentException('نوع فیلد معتبر نیست.');
        if (!Database::fetch('SELECT id FROM action_templates WHERE id=?', [$templateId])) throw new InvalidArgumentException('قالب اقدام معتبر نیست.');
        $optionsJson = self::encodeOptions((string)($input['options_text'] ?? ''));
        $readonly = !empty($input['readonly']) || in_array($type, ['calculated','readonly'], true);
        $params = [
            $templateId, $key, self::cut($label, 190), $type,
            self::nullableText($input['help_text'] ?? null), self::cut(trim((string)($input['placeholder'] ?? '')), 255) ?: null,
            $optionsJson, self::cut(trim((string)($input['data_source'] ?? '')), 100) ?: null,
            self::nullableText($input['formula_expression'] ?? null), self::nullableText($input['default_value'] ?? null),
            !empty($input['required']) ? 1 : 0, $readonly ? 1 : 0, !isset($input['active']) || !empty($input['active']) ? 1 : 0,
            (int)($input['sort_order'] ?? 0),
        ];
        if ($id) {
            Database::execute(
                'UPDATE action_template_fields SET template_id=?,field_key=?,field_label=?,field_type=?,help_text=?,
                 placeholder=?,options_json=?,data_source=?,formula_expression=?,default_value=?,required=?,readonly=?,
                 active=?,sort_order=?,updated_at=NOW() WHERE id=?',
                array_merge($params, [$id])
            );
            return $id;
        }
        Database::execute(
            'INSERT INTO action_template_fields(template_id,field_key,field_label,field_type,help_text,placeholder,
             options_json,data_source,formula_expression,default_value,required,readonly,active,sort_order,created_at,updated_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())
             ON DUPLICATE KEY UPDATE field_label=VALUES(field_label),field_type=VALUES(field_type),help_text=VALUES(help_text),
             placeholder=VALUES(placeholder),options_json=VALUES(options_json),data_source=VALUES(data_source),
             formula_expression=VALUES(formula_expression),default_value=VALUES(default_value),required=VALUES(required),
             readonly=VALUES(readonly),active=VALUES(active),sort_order=VALUES(sort_order),updated_at=NOW()',
            $params
        );
        $found = Database::fetch('SELECT id FROM action_template_fields WHERE template_id=? AND field_key=?', [$templateId,$key]);
        return (int)($found['id'] ?? 0);
    }

    public static function createAction(array $input, array $actor, array $files = []): int
    {
        $actorId = (int)($actor['id'] ?? 0);
        if (!$actorId || !self::canCreateOwn($actor)) throw new DomainException('مجوز ایجاد اقدام را ندارید.');
        $assignedTo = (int)($input['assigned_to'] ?? $actorId);
        if ($assignedTo !== $actorId && !self::canAssign($actor)) throw new DomainException('مجوز تخصیص اقدام به دیگران را ندارید.');
        if (!OrgAccess::canAccessUser($actor, $assignedTo)) throw new DomainException('کاربر مقصد خارج از محدوده سازمانی شماست.');
        $target = Database::fetch('SELECT id FROM users WHERE id=? AND status="active"', [$assignedTo]);
        if (!$target) throw new InvalidArgumentException('کاربر مقصد فعال نیست.');

        $title = trim((string)($input['title'] ?? ''));
        $typeId = (int)($input['action_type_id'] ?? 0);
        $templateId = (int)($input['template_id'] ?? 0) ?: null;
        if ($title === '' || !$typeId) throw new InvalidArgumentException('عنوان و نوع اقدام الزامی است.');
        $type = Database::fetch('SELECT * FROM action_types WHERE id=? AND active=1', [$typeId]);
        if (!$type) throw new InvalidArgumentException('نوع اقدام فعال نیست.');
        $template = $templateId ? self::template($templateId) : null;
        if ($templateId && (!$template || (int)$template['action_type_id'] !== $typeId || !(int)$template['active'])) {
            throw new InvalidArgumentException('قالب انتخاب‌شده با نوع اقدام سازگار نیست.');
        }
        $startDate = self::nullableDate($input['start_date'] ?? null);
        $dueDate = self::nullableDate($input['due_date'] ?? null);
        if ($startDate && $dueDate && $startDate > $dueDate) throw new InvalidArgumentException('تاریخ شروع نباید بعد از سررسید باشد.');
        $priority = isset(self::PRIORITIES[$input['priority'] ?? '']) ? (string)$input['priority'] : 'normal';
        $status = isset(self::STATUSES[$input['status'] ?? '']) ? (string)$input['status'] : 'new';
        $sourceType = isset(self::SOURCES[$input['source_type'] ?? '']) ? (string)$input['source_type'] : 'manual';
        $sourceId = max(0, (int)($input['source_id'] ?? 0)) ?: null;
        $pdo = Database::connection();
        $ownsTransaction = !$pdo->inTransaction();
        $storedUploads = [];
        if ($ownsTransaction) $pdo->beginTransaction();
        try {
            Database::execute(
                'INSERT INTO actions(title,description,action_type_id,template_id,assigned_to,assigned_by,priority,status,
                 start_date,due_date,source_type,source_id,approval_required,created_at,updated_at)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())',
                [
                    self::cut($title, 190), self::nullableText($input['description'] ?? null), $typeId, $templateId,
                    $assignedTo, $actorId, $priority, $status, $startDate, $dueDate, $sourceType, $sourceId,
                    (int)$type['requires_approval'],
                ]
            );
            $actionId = (int)$pdo->lastInsertId();
            self::storeFieldValues($actionId, $template['fields'] ?? [], (array)($input['fields'] ?? []), $files, $actor, $storedUploads);
            self::log($actionId, 'created', $actorId, null, ['status'=>$status,'assigned_to'=>$assignedTo]);
            if (!empty($input['add_to_planner']) || $sourceType === 'planner') self::syncToPlanner($actionId, $actorId);
            if ($sourceId) self::addLink($actionId, 'source', $sourceType, $sourceId, null, self::SOURCES[$sourceType] ?? null, $actorId);
            if ($ownsTransaction) $pdo->commit();
            return $actionId;
        } catch (Throwable $e) {
            if ($ownsTransaction && $pdo->inTransaction()) $pdo->rollBack();
            self::cleanupUploads($storedUploads);
            throw $e;
        }
    }

    public static function actions(array $actor, array $filters = [], int $limit = 200): array
    {
        [$scopeSql,$params] = self::scopeSql($actor, 'a');
        $where = [$scopeSql];
        $status = trim((string)($filters['status'] ?? ''));
        if (isset(self::STATUSES[$status])) { $where[] = 'a.status=?'; $params[] = $status; }
        $priority = trim((string)($filters['priority'] ?? ''));
        if (isset(self::PRIORITIES[$priority])) { $where[] = 'a.priority=?'; $params[] = $priority; }
        $typeId = (int)($filters['action_type_id'] ?? 0);
        if ($typeId) { $where[] = 'a.action_type_id=?'; $params[] = $typeId; }
        $mine = (string)($filters['mine'] ?? '');
        if ($mine === 'assigned') { $where[] = 'a.assigned_to=?'; $params[] = (int)$actor['id']; }
        elseif ($mine === 'created') { $where[] = 'a.assigned_by=?'; $params[] = (int)$actor['id']; }
        $search = trim((string)($filters['q'] ?? ''));
        if ($search !== '') { $where[] = '(a.title LIKE ? OR a.description LIKE ?)'; $params[] = "%{$search}%"; $params[] = "%{$search}%"; }
        $limit = max(1, min(500, $limit));
        return Database::fetchAll(
            'SELECT a.*,at.title action_type_title,at.color action_type_color,at.icon action_type_icon,
                    assignee.name assigned_to_name,assigner.name assigned_by_name,t.title template_title
             FROM actions a JOIN action_types at ON at.id=a.action_type_id
             JOIN users assignee ON assignee.id=a.assigned_to JOIN users assigner ON assigner.id=a.assigned_by
             LEFT JOIN action_templates t ON t.id=a.template_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY CASE WHEN a.status NOT IN ("done","cancelled") AND a.due_date<CURDATE() THEN 0
                           WHEN a.priority="urgent" THEN 1 WHEN a.status="needs_review" THEN 2 ELSE 3 END,
                      a.due_date IS NULL,a.due_date,a.created_at DESC LIMIT ' . $limit,
            $params
        );
    }

    public static function teamActions(array $actor, array $teamUserIds, string $bucket = 'open', int $limit = 50): array
    {
        $allowed = array_flip(OrgAccess::accessibleUserIds($actor));
        $ids = array_values(array_unique(array_filter(array_map('intval', $teamUserIds), static fn(int $id): bool => isset($allowed[$id]))));
        if (!$ids) return [];
        $where = ['a.assigned_to IN (' . implode(',', array_fill(0, count($ids), '?')) . ')'];
        $params = $ids;
        if ($bucket === 'completed') {
            $where[] = 'a.status="done"';
        } elseif ($bucket === 'overdue') {
            $where[] = '(a.status="overdue" OR a.due_date<CURDATE()) AND a.status NOT IN ("done","cancelled")';
        } else {
            $where[] = 'a.status NOT IN ("done","cancelled","overdue") AND (a.due_date IS NULL OR a.due_date>=CURDATE())';
        }
        $limit = max(1, min(200, $limit));
        return Database::fetchAll(
            'SELECT a.*,at.title action_type_title,at.color action_type_color,assignee.name assigned_to_name,
                    assigner.name assigned_by_name,t.title template_title
             FROM actions a JOIN action_types at ON at.id=a.action_type_id
             JOIN users assignee ON assignee.id=a.assigned_to JOIN users assigner ON assigner.id=a.assigned_by
             LEFT JOIN action_templates t ON t.id=a.template_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY CASE WHEN a.status NOT IN ("done","cancelled") AND a.due_date<CURDATE() THEN 0
                           WHEN a.priority="urgent" THEN 1 ELSE 2 END,a.due_date IS NULL,a.due_date,a.id DESC LIMIT ' . $limit,
            $params
        );
    }

    public static function teamStats(array $actor, array $teamUserIds): array
    {
        $allowed = array_flip(OrgAccess::accessibleUserIds($actor));
        $ids = array_values(array_unique(array_filter(array_map('intval', $teamUserIds), static fn(int $id): bool => isset($allowed[$id]))));
        if (!$ids) return ['open'=>0,'overdue'=>0,'completed'=>0];
        $row = Database::fetch(
            'SELECT SUM(status NOT IN ("done","cancelled","overdue") AND (due_date IS NULL OR due_date>=CURDATE())) open_count,
                    SUM((status="overdue" OR due_date<CURDATE()) AND status NOT IN ("done","cancelled")) overdue_count,
                    SUM(status="done") completed_count
             FROM actions WHERE assigned_to IN (' . implode(',', array_fill(0, count($ids), '?')) . ')',
            $ids
        ) ?: [];
        return ['open'=>(int)($row['open_count']??0),'overdue'=>(int)($row['overdue_count']??0),'completed'=>(int)($row['completed_count']??0)];
    }

    public static function action(int $id, array $actor): ?array
    {
        [$scopeSql,$params] = self::scopeSql($actor, 'a');
        array_unshift($params, $id);
        $row = Database::fetch(
            'SELECT a.*,at.title action_type_title,at.color action_type_color,at.requires_approval,
                    assignee.name assigned_to_name,assigner.name assigned_by_name,t.title template_title
             FROM actions a JOIN action_types at ON at.id=a.action_type_id
             JOIN users assignee ON assignee.id=a.assigned_to JOIN users assigner ON assigner.id=a.assigned_by
             LEFT JOIN action_templates t ON t.id=a.template_id WHERE a.id=? AND (' . $scopeSql . ')',
            $params
        );
        if (!$row) return null;
        $row['fields'] = Database::fetchAll('SELECT * FROM action_field_values WHERE action_id=? ORDER BY id', [$id]);
        $row['links'] = Database::fetchAll('SELECT * FROM action_links WHERE action_id=? ORDER BY id', [$id]);
        $row['logs'] = Database::fetchAll(
            'SELECT l.*,u.name performed_by_name FROM action_logs l LEFT JOIN users u ON u.id=l.performed_by
             WHERE l.action_id=? ORDER BY l.created_at DESC,l.id DESC',
            [$id]
        );
        return $row;
    }

    public static function updateStatus(int $id, string $status, array $actor, string $note = ''): void
    {
        if (!isset(self::STATUSES[$status])) throw new InvalidArgumentException('وضعیت اقدام معتبر نیست.');
        $action = self::action($id, $actor);
        if (!$action) throw new DomainException('اقدام در محدوده دسترسی شما نیست.');
        $actorId = (int)$actor['id'];
        $isOwner = (int)$action['assigned_to'] === $actorId || (int)$action['assigned_by'] === $actorId;
        if (!$isOwner && !OrgAccess::isAdmin($actor)) throw new DomainException('مجوز تغییر وضعیت این اقدام را ندارید.');
        $sourceApprover = false;
        if ($status === 'done' && ($action['legacy_source_type'] ?? '') === 'management_decisions' && !OrgAccess::isAdmin($actor)) {
            $decision = Database::fetch('SELECT supervisor_user_id FROM management_decisions WHERE id=?', [(int)$action['legacy_source_id']]);
            $sourceApprover = (int)($decision['supervisor_user_id'] ?? 0) === $actorId;
            if (!$sourceApprover) $status = 'needs_review';
        }
        if ($status === 'done' && (int)$action['approval_required'] && !$sourceApprover && !Auth::isAdmin() && !Auth::can('action_hub.approve', 'edit')) {
            $status = 'needs_review';
        }
        $pdo = Database::connection();
        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) $pdo->beginTransaction();
        try {
            Database::execute(
                'UPDATE actions SET status=?,completed_at=IF(?="done",NOW(),NULL),
                 approved_by=IF(?="done" AND approval_required=1,?,approved_by),
                 approved_at=IF(?="done" AND approval_required=1,NOW(),approved_at),updated_at=NOW() WHERE id=?',
                [$status,$status,$status,$actorId,$status,$id]
            );
            self::syncStatusToSource($action, $status, $actorId, $note);
            self::log($id, 'status_changed', $actorId, ['status'=>$action['status']], ['status'=>$status], $note);
            if (!empty($action['planner_task_id'])) {
                $plannerStatus = match ($status) {
                    'in_progress' => 'in_progress', 'done' => 'done', 'cancelled' => 'cancelled',
                    'overdue' => 'overdue', 'paused' => 'waiting', default => 'todo',
                };
                WorkPlannerService::updateTaskStatus((int)$action['planner_task_id'], $plannerStatus, $actorId);
            }
            if ($ownsTransaction) $pdo->commit();
        } catch (Throwable $e) {
            if ($ownsTransaction && $pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public static function syncToPlanner(int $actionId, int $actorId): ?int
    {
        if (!Database::tableExists('work_planner_tasks')) return null;
        $action = Database::fetch('SELECT * FROM actions WHERE id=?', [$actionId]);
        if (!$action) return null;
        if (!empty($action['planner_task_id'])) return (int)$action['planner_task_id'];
        $existing = Database::fetch(
            'SELECT id FROM work_planner_tasks WHERE related_module="action_hub" AND related_record_id=? ORDER BY id LIMIT 1',
            [$actionId]
        );
        $taskId = $existing ? (int)$existing['id'] : WorkPlannerService::createLinkedTask(
            $actorId,
            (int)$action['assigned_to'],
            [
                'title'=>$action['title'], 'description'=>$action['description'], 'priority'=>$action['priority'],
                'start_date'=>$action['start_date'], 'due_date'=>$action['due_date'],
            ],
            'action_hub',
            $actionId
        );
        Database::execute('UPDATE actions SET planner_task_id=?,updated_at=NOW() WHERE id=?', [$taskId,$actionId]);
        self::addLink($actionId, 'planner', 'work_planner_tasks', $taskId, '/admin/work-planner.php?task_id=' . $taskId, 'برنامه کاری', $actorId);
        return $taskId;
    }

    public static function mirrorLegacyAction(string $table, int $legacyId): ?int
    {
        if (!in_array($table, ['sales_actions','supervisor_actions'], true) || $legacyId < 1 || !Database::tableExists($table)) return null;
        ActionHubModule::repair(Database::connection());
        $row = Database::fetch("SELECT * FROM {$table} WHERE id=?", [$legacyId]);
        if (!$row) return null;
        $existing = Database::fetch('SELECT id FROM actions WHERE legacy_source_type=? AND legacy_source_id=?', [$table,$legacyId]);
        $actionId = (int)($existing['id'] ?? 0);
        if ($actionId < 1) return null;

        $assignedTo = $table === 'supervisor_actions'
            ? (int)($row['supervisor_id'] ?? 0)
            : (int)($row['assigned_to'] ?: $row['sales_manager_id'] ?? 0);
        $assignedBy = (int)($row['created_by'] ?: $assignedTo);
        Database::execute(
            'UPDATE actions SET title=?,description=?,
             assigned_to=COALESCE((SELECT id FROM users WHERE id=?),assigned_to),
             assigned_by=COALESCE((SELECT id FROM users WHERE id=?),assigned_by),
             priority=?,status=?,due_date=?,planner_task_id=?,completed_at=?,updated_at=NOW()
             WHERE id=?',
            [
                self::cut((string)($row['title'] ?? ''), 190),
                $row['description'] ?? null,
                $assignedTo,
                $assignedBy,
                isset(self::PRIORITIES[$row['priority'] ?? '']) ? $row['priority'] : 'normal',
                self::legacyActionStatus((string)($row['status'] ?? 'open')),
                $row['due_date'] ?? null,
                (int)($row['planner_task_id'] ?? 0) ?: null,
                $row['completed_at'] ?? null,
                $actionId,
            ]
        );
        return $actionId;
    }

    public static function mirrorSourceRecord(string $sourceTable, int $sourceId): ?int
    {
        if (!in_array($sourceTable, ['work_planner_tasks','personal_planner_tasks','management_decisions','okr_initiatives'], true) || $sourceId < 1) return null;
        $pdo = Database::connection();
        if ($pdo->inTransaction() || !Database::tableExists($sourceTable)) return null;
        ActionHubModule::repair($pdo);
        $action = Database::fetch('SELECT id FROM actions WHERE legacy_source_type=? AND legacy_source_id=?', [$sourceTable,$sourceId]);
        if (!$action) return null;
        $actionId = (int)$action['id'];
        if ($sourceTable === 'work_planner_tasks') {
            $row = Database::fetch('SELECT * FROM work_planner_tasks WHERE id=? AND COALESCE(related_module,"")<>"action_hub"', [$sourceId]);
            if (!$row) return null;
            $status = self::plannerStatus((string)$row['status']);
            Database::execute(
                'UPDATE actions SET title=?,description=?,assigned_to=?,assigned_by=COALESCE((SELECT id FROM users WHERE id=?),assigned_by),priority=?,status=?,
                 start_date=?,due_date=?,planner_task_id=?,completed_at=?,updated_at=NOW() WHERE id=?',
                [self::cut((string)$row['title'],190),$row['description'],(int)$row['user_id'],(int)($row['assigned_by']??0)?:null,
                 isset(self::PRIORITIES[$row['priority']??''])?$row['priority']:'normal',$status,$row['start_date'],$row['due_date'],
                 $sourceId,$row['completed_at']??null,$actionId]
            );
        } elseif ($sourceTable === 'personal_planner_tasks') {
            $row = Database::fetch('SELECT * FROM personal_planner_tasks WHERE id=?', [$sourceId]);
            if (!$row) return null;
            $status = self::plannerStatus((string)$row['status']);
            Database::execute(
                'UPDATE actions SET title=?,description=?,assigned_to=?,assigned_by=?,priority=?,status=?,start_date=?,due_date=?,
                 completed_at=IF(?="done",COALESCE(?,NOW()),NULL),updated_at=NOW() WHERE id=?',
                [self::cut((string)$row['title'],190),$row['description'],(int)$row['user_id'],(int)$row['user_id'],
                 isset(self::PRIORITIES[$row['priority']??''])?$row['priority']:'normal',$status,$row['task_date'],
                 $row['due_at']?substr((string)$row['due_at'],0,10):$row['task_date'],$status,$row['updated_at']??null,$actionId]
            );
        } elseif ($sourceTable === 'management_decisions') {
            $row = Database::fetch('SELECT * FROM management_decisions WHERE id=? AND responsible_user_id IS NOT NULL', [$sourceId]);
            if (!$row) return null;
            $status = self::decisionStatus((string)$row['followup_status']);
            Database::execute(
                'UPDATE actions SET title=?,description=?,assigned_to=?,assigned_by=COALESCE((SELECT id FROM users WHERE id=?),assigned_by),priority=?,status=?,
                 due_date=?,approval_required=?,completed_at=IF(?="done",COALESCE(?,NOW()),NULL),updated_at=NOW() WHERE id=?',
                [self::cut((string)$row['title'],190),$row['description'],(int)$row['responsible_user_id'],(int)($row['created_by']??0)?:null,
                 isset(self::PRIORITIES[$row['priority']??''])?$row['priority']:'normal',$status,$row['due_date'],
                 empty($row['supervisor_user_id'])?0:1,$status,$row['closed_at']??$row['updated_at']??null,$actionId]
            );
        } else {
            $row = Database::fetch('SELECT * FROM okr_initiatives WHERE id=?', [$sourceId]);
            if (!$row) return null;
            $status = self::initiativeStatus((string)$row['status']);
            Database::execute(
                'UPDATE actions SET title=?,description=?,assigned_to=?,assigned_by=COALESCE((SELECT id FROM users WHERE id=?),assigned_by),priority=?,status=?,
                 start_date=?,due_date=?,planner_task_id=?,completed_at=IF(?="done",COALESCE(?,NOW()),NULL),updated_at=NOW() WHERE id=?',
                [self::cut((string)$row['title'],190),$row['description'],(int)$row['owner_user_id'],(int)($row['created_by']??0)?:null,
                 isset(self::PRIORITIES[$row['priority']??''])?$row['priority']:'normal',$status,$row['start_date'],$row['due_date'],
                 (int)($row['planner_task_id']??0)?:null,$status,$row['updated_at']??null,$actionId]
            );
        }
        return $actionId;
    }

    public static function syncLegacyTemplates(): void
    {
        ActionHubModule::repair(Database::connection());
    }

    public static function optionsText(?string $json): string
    {
        $lines = [];
        foreach (self::decodeOptions($json) as $value => $label) $lines[] = $value . '|' . $label;
        return implode("\n", $lines);
    }

    private static function scopeSql(array $actor, string $alias): array
    {
        if (OrgAccess::isAdmin($actor)) return ['1=1', []];
        $ids = OrgAccess::accessibleUserIds($actor);
        if (!$ids) $ids = [(int)$actor['id']];
        $holders = implode(',', array_fill(0, count($ids), '?'));
        return ["({$alias}.assigned_to IN ({$holders}) OR {$alias}.assigned_by=?)", array_merge($ids, [(int)$actor['id']])];
    }

    private static function isOrganizationalAssigner(array $actor): bool
    {
        if (($actor['role'] ?? '') === 'manager') return true;
        $roleKey = strtoupper(trim((string)($actor['role_key'] ?? '')));
        if (in_array($roleKey, ['SALES_MANAGER','SALES_SUPERVISOR'], true)) return true;
        try {
            return count(OrgAccess::accessibleUserIds($actor)) > 1;
        } catch (Throwable) {
            return false;
        }
    }

    private static function plannerStatus(string $status): string
    {
        return [
            'todo'=>'new','open'=>'new','in_progress'=>'in_progress','waiting'=>'paused','blocked'=>'paused',
            'done'=>'done','completed'=>'done','cancelled'=>'cancelled','overdue'=>'overdue',
        ][$status] ?? 'new';
    }

    private static function legacyActionStatus(string $status): string
    {
        return [
            'open'=>'new',
            'new'=>'new',
            'in_progress'=>'in_progress',
            'waiting'=>'paused',
            'paused'=>'paused',
            'needs_manager_review'=>'needs_review',
            'needs_review'=>'needs_review',
            'done'=>'done',
            'completed'=>'done',
            'cancelled'=>'cancelled',
            'overdue'=>'overdue',
        ][$status] ?? 'new';
    }

    private static function decisionStatus(string $status): string
    {
        return [
            'new'=>'new','not_started'=>'new','assigned'=>'new','in_progress'=>'in_progress','waiting'=>'paused',
            'needs_revision'=>'needs_review','done'=>'needs_review','verified'=>'done','cancelled'=>'cancelled','overdue'=>'overdue',
        ][$status] ?? 'new';
    }

    private static function initiativeStatus(string $status): string
    {
        return [
            'open'=>'new','new'=>'new','in_progress'=>'in_progress','paused'=>'paused','done'=>'done',
            'completed'=>'done','cancelled'=>'cancelled','overdue'=>'overdue',
        ][$status] ?? 'new';
    }

    private static function syncStatusToSource(array $action, string $status, int $actorId, string $note): void
    {
        $table = (string)($action['legacy_source_type'] ?? '');
        $sourceId = (int)($action['legacy_source_id'] ?? 0);
        if ($sourceId < 1) return;
        if ($table === 'personal_planner_tasks') {
            $sourceStatus = match ($status) {
                'in_progress'=>'in_progress','paused'=>'waiting','done'=>'done','cancelled'=>'cancelled','overdue'=>'overdue',default=>'todo',
            };
            Database::execute('UPDATE personal_planner_tasks SET status=?,completed_at=IF(?="done",NOW(),NULL),updated_at=NOW() WHERE id=? AND user_id=?', [$sourceStatus,$sourceStatus,$sourceId,$actorId]);
        } elseif ($table === 'management_decisions') {
            $sourceStatus = match ($status) {
                'in_progress'=>'in_progress','paused'=>'waiting','needs_review'=>'done','done'=>'verified',
                'cancelled'=>'cancelled','overdue'=>'overdue',default=>'not_started',
            };
            $decision = Database::fetch('SELECT followup_status,progress_percent FROM management_decisions WHERE id=?', [$sourceId]);
            if (!$decision) return;
            $progress = in_array($sourceStatus, ['done','verified'], true) ? 100 : (int)$decision['progress_percent'];
            Database::execute(
                'UPDATE management_decisions SET followup_status=?,progress_percent=IF(? IN ("done","verified"),100,progress_percent),
                 latest_followup_note=?,closed_at=IF(? IN ("done","verified"),COALESCE(closed_at,NOW()),closed_at),
                 closed_by=IF(?="done",?,closed_by),verified_by=IF(?="verified",?,verified_by),
                 verification_status=IF(?="verified","verified",verification_status),updated_by=?,updated_at=NOW() WHERE id=?',
                [$sourceStatus,$sourceStatus,self::cut($note,5000)?:null,$sourceStatus,$sourceStatus,$actorId,$sourceStatus,$actorId,$sourceStatus,$actorId,$sourceId]
            );
            Database::execute(
                'INSERT INTO management_decision_followups(decision_id,old_status,new_status,progress_percent,followup_note,created_by,created_at)
                 VALUES (?,?,?,?,?,?,NOW())',
                [$sourceId,$decision['followup_status'],$sourceStatus,$progress,self::cut($note,5000),$actorId]
            );
        } elseif ($table === 'okr_initiatives') {
            $sourceStatus = match ($status) {
                'in_progress'=>'in_progress','paused'=>'paused','done'=>'done','cancelled'=>'cancelled','overdue'=>'overdue',default=>'open',
            };
            Database::execute('UPDATE okr_initiatives SET status=?,updated_at=NOW() WHERE id=? AND (owner_user_id=? OR created_by=?)', [$sourceStatus,$sourceId,$actorId,$actorId]);
        } elseif (in_array($table, ['sales_actions','supervisor_actions'], true)) {
            $sourceStatus = match ($status) {
                'in_progress'=>'in_progress','done'=>'done','cancelled'=>'cancelled','overdue'=>'overdue',
                'needs_review'=>$table==='supervisor_actions'?'needs_manager_review':'in_progress',default=>'open',
            };
            Database::execute("UPDATE {$table} SET status=?,completed_at=IF(?='done',NOW(),NULL),updated_at=NOW() WHERE id=?", [$sourceStatus,$sourceStatus,$sourceId]);
        }
    }

    private static function storeFieldValues(int $actionId, array $fields, array $values, array $files, array $actor, array &$storedUploads): void
    {
        $context = [];
        foreach ($fields as $field) {
            $key = (string)$field['field_key'];
            $type = (string)$field['field_type'];
            $raw = $values[$key] ?? $field['default_value'] ?? null;
            if ($type === 'file') {
                $raw = self::storeUpload($files[$key] ?? null, $actionId, $key);
                if (!empty($raw['path'])) $storedUploads[] = (string)$raw['path'];
            }
            if ($type === 'calculated') {
                $raw = self::calculateExpression((string)($field['formula_expression'] ?? ''), $context, (string)$field['field_label']);
            } elseif ((int)$field['readonly'] && $type !== 'file') {
                $raw = $field['default_value'] ?? null;
            }
            if ((int)$field['required'] && self::isEmptyValue($raw)) {
                throw new InvalidArgumentException('تکمیل فیلد «' . $field['field_label'] . '» الزامی است.');
            }
            if (self::isEmptyValue($raw) && !(int)$field['required']) continue;
            $normalized = self::normalizeFieldValue($field, $raw, $actor);
            Database::execute(
                'INSERT INTO action_field_values(action_id,field_id,field_key,field_label,field_type,value_text,value_number,
                 value_date,value_datetime,value_json,file_path,file_name,created_at,updated_at)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())',
                [
                    $actionId,(int)$field['id'],$key,$field['field_label'],$type,$normalized['text'],$normalized['number'],
                    $normalized['date'],$normalized['datetime'],$normalized['json'],$normalized['file_path'],$normalized['file_name'],
                ]
            );
            if ($type === 'action_link' && $normalized['number']) {
                self::addLink($actionId, 'action', 'actions', (int)$normalized['number'], '/admin/action-view.php?id=' . (int)$normalized['number'], (string)$field['field_label'], (int)$actor['id']);
            } elseif ($type === 'planner_link' && $normalized['number']) {
                self::addLink($actionId, 'planner', 'work_planner_tasks', (int)$normalized['number'], '/admin/work-planner.php?task_id=' . (int)$normalized['number'], (string)$field['field_label'], (int)$actor['id']);
            }
            $context[$key] = $normalized['number'] ?? $normalized['text'] ?? $normalized['date'] ?? $normalized['datetime'] ?? 0;
        }
    }

    private static function normalizeFieldValue(array $field, mixed $raw, array $actor): array
    {
        $type = (string)$field['field_type'];
        $result = ['text'=>null,'number'=>null,'date'=>null,'datetime'=>null,'json'=>null,'file_path'=>null,'file_name'=>null];
        if ($type === 'file' && is_array($raw)) {
            $result['file_path'] = $raw['path'] ?? null;
            $result['file_name'] = $raw['name'] ?? null;
            return $result;
        }
        if (in_array($type, ['number','money','percentage','calculated'], true)) {
            $number = str_replace([',','٬',' '], '', AppDate::normalizeDigits((string)$raw));
            if (!is_numeric($number)) throw new InvalidArgumentException('مقدار «' . $field['field_label'] . '» باید عددی باشد.');
            $result['number'] = (float)$number;
            if ($type === 'percentage' && ($result['number'] < 0 || $result['number'] > 100)) {
                throw new InvalidArgumentException('درصد باید بین صفر تا صد باشد.');
            }
        } elseif ($type === 'jalali_date') {
            $result['date'] = AppDate::toGregorian((string)$raw);
            if (!$result['date']) throw new InvalidArgumentException('تاریخ «' . $field['field_label'] . '» معتبر نیست.');
        } elseif ($type === 'datetime') {
            $result['datetime'] = AppDate::toGregorianDateTime((string)$raw);
            if (!$result['datetime']) throw new InvalidArgumentException('تاریخ و ساعت «' . $field['field_label'] . '» معتبر نیست.');
        } elseif ($type === 'multi_select') {
            $selected = array_values(array_filter(array_map('strval', is_array($raw) ? $raw : [$raw])));
            self::validateOptions($field, $selected);
            $result['json'] = json_encode($selected, JSON_UNESCAPED_UNICODE);
        } elseif ($type === 'single_select') {
            self::validateOptions($field, [(string)$raw]);
            $result['text'] = (string)$raw;
        } elseif ($type === 'user') {
            $userId = (int)$raw;
            if (!OrgAccess::canAccessUser($actor, $userId)) throw new DomainException('کاربر انتخاب‌شده خارج از محدوده سازمانی شماست.');
            $result['number'] = $userId;
        } elseif ($type === 'org_unit') {
            if (!Database::fetch('SELECT id FROM org_units WHERE id=? AND active=1', [(int)$raw])) throw new InvalidArgumentException('واحد سازمانی معتبر نیست.');
            $result['number'] = (int)$raw;
        } elseif ($type === 'sales_line') {
            if (!Database::fetch('SELECT id FROM sales_lines WHERE id=? AND active=1', [(int)$raw])) throw new InvalidArgumentException('لاین فروش معتبر نیست.');
            $result['number'] = (int)$raw;
        } elseif ($type === 'action_link') {
            $linkedActionId = (int)$raw;
            if ($linkedActionId < 1 || !self::action($linkedActionId, $actor)) throw new DomainException('اقدام مرتبط در محدوده دسترسی شما نیست.');
            $result['number'] = $linkedActionId;
        } elseif ($type === 'planner_link') {
            $taskId = (int)$raw;
            if ($taskId < 1 || !WorkPlannerService::canUserAccessTask((int)$actor['id'], $taskId)) throw new DomainException('وظیفه مرتبط در محدوده دسترسی شما نیست.');
            $result['number'] = $taskId;
        } elseif ($type === 'url') {
            $url = trim((string)$raw);
            if (!filter_var($url, FILTER_VALIDATE_URL)) throw new InvalidArgumentException('پیوند «' . $field['field_label'] . '» معتبر نیست.');
            $result['text'] = self::cut($url, 2000);
        } else {
            $result['text'] = self::cut(is_array($raw) ? implode('، ', $raw) : (string)$raw, 10000);
        }
        return $result;
    }

    private static function calculateExpression(string $expression, array $context, string $label): float
    {
        $expression = trim($expression);
        if ($expression === '') throw new InvalidArgumentException('فرمول فیلد محاسباتی «' . $label . '» تعریف نشده است.');
        $expression = preg_replace_callback('/\{([a-zA-Z0-9_-]+)\}/', static function(array $match) use ($context, $label): string {
            $value = $context[$match[1]] ?? null;
            if (!is_numeric($value)) throw new InvalidArgumentException('ورودی عددی «' . $match[1] . '» برای محاسبه «' . $label . '» موجود نیست.');
            return (string)(float)$value;
        }, $expression) ?? '';
        if ($expression === '' || !preg_match('/^[0-9eE+\-*\/().\s]+$/', $expression)) {
            throw new InvalidArgumentException('فرمول محاسباتی «' . $label . '» فقط می‌تواند شامل عدد، پرانتز و عملگرهای ریاضی باشد.');
        }
        $compact = str_replace([" ","\t","\r","\n"], '', $expression);
        preg_match_all('/(?:\d+(?:\.\d+)?(?:[eE][+\-]?\d+)?)|[()+\-*\/]/', $compact, $matches);
        $tokens = $matches[0] ?? [];
        if (!$tokens || implode('', $tokens) !== $compact) throw new InvalidArgumentException('ساختار فرمول محاسباتی «' . $label . '» معتبر نیست.');
        $output = [];
        $operators = [];
        $precedence = ['+'=>1,'-'=>1,'*'=>2,'/'=>2,'u-'=>3];
        $previous = null;
        foreach ($tokens as $token) {
            if (is_numeric($token)) {
                $output[] = (float)$token;
            } elseif ($token === '(') {
                $operators[] = $token;
            } elseif ($token === ')') {
                while ($operators && end($operators) !== '(') $output[] = array_pop($operators);
                if (!$operators || array_pop($operators) !== '(') throw new InvalidArgumentException('پرانتزهای فرمول «' . $label . '» متوازن نیست.');
            } else {
                if ($token === '-' && ($previous === null || $previous === '(' || isset($precedence[$previous]))) $token = 'u-';
                while ($operators && isset($precedence[end($operators)]) && $precedence[end($operators)] >= $precedence[$token]) $output[] = array_pop($operators);
                $operators[] = $token;
            }
            $previous = $token;
        }
        while ($operators) {
            $operator = array_pop($operators);
            if ($operator === '(') throw new InvalidArgumentException('پرانتزهای فرمول «' . $label . '» متوازن نیست.');
            $output[] = $operator;
        }
        $stack = [];
        foreach ($output as $token) {
            if (is_float($token) || is_int($token)) { $stack[] = (float)$token; continue; }
            if ($token === 'u-') {
                if (!$stack) throw new InvalidArgumentException('فرمول محاسباتی «' . $label . '» کامل نیست.');
                $stack[] = -array_pop($stack);
                continue;
            }
            if (count($stack) < 2) throw new InvalidArgumentException('فرمول محاسباتی «' . $label . '» کامل نیست.');
            $right = array_pop($stack);
            $left = array_pop($stack);
            if ($token === '/' && abs($right) < 0.0000000001) throw new InvalidArgumentException('تقسیم بر صفر در فرمول «' . $label . '» مجاز نیست.');
            $stack[] = match ($token) {
                '+' => $left + $right,
                '-' => $left - $right,
                '*' => $left * $right,
                '/' => $left / $right,
                default => throw new InvalidArgumentException('عملگر فرمول معتبر نیست.'),
            };
        }
        if (count($stack) !== 1 || !is_finite($stack[0])) throw new InvalidArgumentException('نتیجه فرمول محاسباتی «' . $label . '» معتبر نیست.');
        return round($stack[0], 4);
    }

    private static function validateOptions(array $field, array $selected): void
    {
        $allowed = array_keys(self::decodeOptions($field['options_json'] ?? null));
        if ($allowed && array_diff($selected, $allowed)) throw new InvalidArgumentException('گزینه انتخاب‌شده برای «' . $field['field_label'] . '» معتبر نیست.');
    }

    private static function storeUpload(mixed $file, int $actionId, string $key): ?array
    {
        if (!is_array($file) || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
        if ((int)$file['error'] !== UPLOAD_ERR_OK) throw new InvalidArgumentException('بارگذاری فایل کامل نشد.');
        if ((int)($file['size'] ?? 0) > 10 * 1024 * 1024) throw new InvalidArgumentException('حجم فایل نباید بیشتر از ۱۰ مگابایت باشد.');
        $name = basename((string)($file['name'] ?? 'file'));
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $allowed = ['pdf','doc','docx','xls','xlsx','csv','txt','jpg','jpeg','png','webp','zip'];
        if (!in_array($extension, $allowed, true)) throw new InvalidArgumentException('نوع فایل مجاز نیست.');
        $relativeDir = 'storage/action-files/' . $actionId;
        $absoluteDir = dirname(__DIR__) . '/' . $relativeDir;
        if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0775, true) && !is_dir($absoluteDir)) throw new RuntimeException('مسیر ذخیره فایل آماده نشد.');
        $stored = $key . '-' . bin2hex(random_bytes(12)) . '.' . $extension;
        if (!move_uploaded_file((string)$file['tmp_name'], $absoluteDir . '/' . $stored)) throw new RuntimeException('ذخیره فایل انجام نشد.');
        return ['path'=>$relativeDir . '/' . $stored,'name'=>self::cut($name, 255)];
    }

    private static function cleanupUploads(array $paths): void
    {
        $root = realpath(dirname(__DIR__) . '/storage/action-files');
        if (!$root) return;
        foreach ($paths as $relative) {
            $path = realpath(dirname(__DIR__) . '/' . ltrim((string)$relative, '/\\'));
            if (!$path || !str_starts_with(strtolower($path), strtolower($root . DIRECTORY_SEPARATOR)) || !is_file($path)) continue;
            @unlink($path);
            $directory = dirname($path);
            if ($directory !== $root && is_dir($directory) && count(scandir($directory) ?: []) <= 2) @rmdir($directory);
        }
    }

    private static function addLink(int $actionId, string $linkType, ?string $linkedType, ?int $linkedId, ?string $url, ?string $label, int $actorId): void
    {
        Database::execute(
            'INSERT INTO action_links(action_id,link_type,linked_type,linked_id,link_url,label,created_by,created_at)
             VALUES (?,?,?,?,?,?,?,NOW())
             ON DUPLICATE KEY UPDATE link_url=VALUES(link_url),label=VALUES(label)',
            [$actionId,$linkType,$linkedType,$linkedId,$url,$label,$actorId]
        );
    }

    private static function log(int $actionId, string $key, int $actorId, mixed $old, mixed $new, string $note = ''): void
    {
        Database::execute(
            'INSERT INTO action_logs(action_id,action_key,note,old_value_json,new_value_json,performed_by,created_at)
             VALUES (?,?,?,?,?,?,NOW())',
            [$actionId,$key,self::cut($note,5000) ?: null,json_encode($old,JSON_UNESCAPED_UNICODE),json_encode($new,JSON_UNESCAPED_UNICODE),$actorId]
        );
    }

    private static function encodeOptions(string $text): ?string
    {
        $options = [];
        foreach (preg_split('/\R/u', trim($text)) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') continue;
            [$value,$label] = array_pad(explode('|', $line, 2), 2, '');
            $value = self::safeKey($value);
            $label = trim($label);
            if ($value === '' || $label === '') throw new InvalidArgumentException('هر گزینه باید به شکل «مقدار|عنوان فارسی» باشد.');
            $options[$value] = self::cut($label, 190);
        }
        return $options ? json_encode($options, JSON_UNESCAPED_UNICODE) : null;
    }

    private static function decodeOptions(?string $json): array
    {
        if (!$json) return [];
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) return [];
        $options = [];
        foreach ($decoded as $key => $label) {
            if (is_int($key)) $options[(string)$label] = (string)$label;
            elseif (is_scalar($label)) $options[(string)$key] = (string)$label;
        }
        return $options;
    }

    private static function nullableDate(mixed $value): ?string
    {
        $value = trim((string)$value);
        if ($value === '') return null;
        $date = AppDate::toGregorian($value);
        if (!$date) throw new InvalidArgumentException('تاریخ واردشده معتبر نیست.');
        return $date;
    }

    private static function safeKey(string $value): string
    {
        $value = strtolower(trim(AppDate::normalizeDigits($value)));
        return trim((string)preg_replace('/[^a-z0-9_-]+/', '_', $value), '_');
    }

    private static function nullableText(mixed $value): ?string
    {
        $value = trim((string)$value);
        return $value === '' ? null : self::cut($value, 10000);
    }

    private static function cut(string $value, int $length): string
    {
        return function_exists('mb_substr') ? mb_substr($value, 0, $length, 'UTF-8') : substr($value, 0, $length);
    }

    private static function isEmptyValue(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }
}
