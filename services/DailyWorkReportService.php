<?php

require_once __DIR__ . '/../core/DailyWorkReportModule.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../lib/AppDate.php';
require_once __DIR__ . '/../lib/OrgAccess.php';
require_once __DIR__ . '/ActionHubService.php';

final class DailyWorkReportService
{
    public const INPUT_TYPES = [
        'short_text' => 'متن کوتاه',
        'long_text' => 'متن بلند',
        'number' => 'عدد',
        'yes_no' => 'بله / خیر',
        'readonly' => 'نمایش محاسبه‌شده',
        'readonly_list' => 'فهرست پیوندی',
    ];

    public const SOURCE_TYPES = [
        'manual' => 'ورودی دستی',
        'assigned_actions' => 'اقدامات محول‌شده',
        'planner_tasks' => 'وظایف پلنر',
        'completed_tasks' => 'وظایف تکمیل‌شده',
        'open_tasks' => 'وظایف باز',
        'kpi_values' => 'مقادیر KPI',
        'imported_data' => 'داده‌های واردشده',
        'attendance' => 'حضور و کارکرد',
        'calculated' => 'فیلد محاسباتی',
    ];

    public const SCOPES = [
        'user' => 'کاربر',
        'role' => 'نقش سازمانی',
        'department' => 'واحد سازمانی',
        'sales_line' => 'لاین فروش',
        'supervisor_team' => 'تیم سرپرست',
        'manager_team' => 'تیم مدیر',
        'company' => 'کل شرکت',
    ];

    public const SOURCE_KEYS = [
        'assigned_actions' => ['open'=>'اقدامات باز','done_today'=>'اقدامات انجام‌شده امروز','all'=>'همه اقدامات قابل دسترس'],
        'planner_tasks' => ['today'=>'وظایف مرتبط با امروز','all_open'=>'همه وظایف باز'],
        'completed_tasks' => ['today'=>'تکمیل‌شده امروز'],
        'open_tasks' => ['today'=>'باز و مرتبط با امروز','all'=>'همه وظایف باز'],
        'kpi_values' => ['latest'=>'میانگین آخرین دوره KPI'],
        'imported_data' => ['user_batches'=>'Batchهای ثبت‌شده توسط کاربر'],
        'attendance' => ['today'=>'خلاصه کارکرد روز'],
    ];

    public static function boot(): void
    {
        DailyWorkReportModule::repair(Database::connection());
    }

    public static function canView(?array $actor = null): bool
    {
        $actor ??= Auth::user();
        return (bool)$actor && (
            OrgAccess::isAdmin($actor)
            || Auth::can('daily_reports.view')
            || Auth::can('daily_reports.submit')
            || Auth::can('daily_reports.view_team')
            || Auth::can('daily_reports.manage_templates')
            || Auth::can('sales_manager.daily_logs.manage')
        );
    }

    public static function canSubmit(?array $actor = null): bool
    {
        $actor ??= Auth::user();
        return (bool)$actor && (
            OrgAccess::isAdmin($actor)
            || Auth::can('daily_reports.submit', 'create')
            || Auth::can('daily_reports.submit')
            || Auth::can('sales_manager.daily_logs.manage')
        );
    }

    public static function canViewTeam(?array $actor = null): bool
    {
        $actor ??= Auth::user();
        return (bool)$actor && (
            OrgAccess::isAdmin($actor)
            || ($actor['role'] ?? '') === 'manager'
            || Auth::can('daily_reports.view_team')
        );
    }

    public static function canManageTemplates(?array $actor = null): bool
    {
        $actor ??= Auth::user();
        if (!$actor) return false;
        $roleKey = strtoupper((string)($actor['role_key'] ?? ''));
        return OrgAccess::isAdmin($actor)
            || ($actor['role'] ?? '') === 'manager'
            || in_array($roleKey, ['SALES_MANAGER','DEPARTMENT_MANAGER'], true)
            || Auth::can('daily_reports.manage_templates', 'edit')
            || Auth::can('daily_reports.manage_templates');
    }

    public static function templatesForUser(array $user): array
    {
        $rows = Database::fetchAll(
            'SELECT DISTINCT t.* FROM daily_report_templates t
             JOIN daily_report_template_assignments a ON a.template_id=t.id AND a.active=1
             WHERE t.active=1 ORDER BY t.title,t.version_no DESC'
        );
        return array_values(array_filter($rows, static fn(array $template): bool => self::templateMatchesUser((int)$template['id'], $user)));
    }

    public static function templates(bool $activeOnly = false): array
    {
        return Database::fetchAll(
            'SELECT t.*,COUNT(DISTINCT f.id) field_count,COUNT(DISTINCT a.id) assignment_count
             FROM daily_report_templates t
             LEFT JOIN daily_report_template_fields f ON f.template_id=t.id AND f.active=1
             LEFT JOIN daily_report_template_assignments a ON a.template_id=t.id AND a.active=1
             ' . ($activeOnly ? 'WHERE t.active=1 ' : '') . '
             GROUP BY t.id ORDER BY t.active DESC,t.title,t.version_no DESC'
        );
    }

    public static function template(int $id): ?array
    {
        if ($id < 1) return null;
        $template = Database::fetch('SELECT * FROM daily_report_templates WHERE id=?', [$id]);
        if (!$template) return null;
        $template['fields'] = self::templateFields($id);
        $template['assignments'] = Database::fetchAll(
            'SELECT a.*,u.name user_name,r.title role_title,ou.title unit_title,sl.title sales_line_title,
                    team.name team_user_name
             FROM daily_report_template_assignments a
             LEFT JOIN users u ON a.scope_type="user" AND u.id=a.scope_id
             LEFT JOIN org_roles r ON a.scope_type="role" AND r.id=a.scope_id
             LEFT JOIN org_units ou ON a.scope_type="department" AND ou.id=a.scope_id
             LEFT JOIN sales_lines sl ON a.scope_type="sales_line" AND sl.id=a.scope_id
             LEFT JOIN users team ON a.scope_type IN ("supervisor_team","manager_team") AND team.id=a.scope_id
             WHERE a.template_id=? ORDER BY a.active DESC,a.scope_type,a.id',
            [$id]
        );
        return $template;
    }

    public static function templateFields(int $templateId): array
    {
        $rows = Database::fetchAll(
            'SELECT * FROM daily_report_template_fields WHERE template_id=? AND active=1 ORDER BY sort_order,id',
            [$templateId]
        );
        foreach ($rows as &$row) $row['options'] = self::decodeOptions($row['options_json'] ?? null);
        unset($row);
        return $rows;
    }

    public static function saveTemplate(array $input, array $actor): int
    {
        if (!self::canManageTemplates($actor)) throw new DomainException('مجوز مدیریت قالب گزارش را ندارید.');
        $id = max(0, (int)($input['id'] ?? 0));
        $code = self::safeKey((string)($input['template_code'] ?? ''));
        $title = trim((string)($input['title'] ?? ''));
        if ($code === '' || $title === '') throw new InvalidArgumentException('کد و عنوان قالب الزامی است.');
        $params = [
            $code,self::cut($title,190),self::nullableText($input['description'] ?? null),
            max(1,(int)($input['version_no'] ?? 1)),!empty($input['active'])?1:0,(int)$actor['id'],
        ];
        if ($id) {
            Database::execute(
                'UPDATE daily_report_templates SET template_code=?,title=?,description=?,version_no=?,active=?,
                 created_by=COALESCE(created_by,?),updated_at=NOW() WHERE id=?',
                array_merge($params,[$id])
            );
            return $id;
        }
        Database::execute(
            'INSERT INTO daily_report_templates(template_code,title,description,version_no,active,created_by,created_at,updated_at)
             VALUES (?,?,?,?,?,?,NOW(),NOW())',
            $params
        );
        return (int)Database::lastInsertId();
    }

    public static function saveField(array $input, array $actor): int
    {
        if (!self::canManageTemplates($actor)) throw new DomainException('مجوز مدیریت فیلدهای گزارش را ندارید.');
        $id = max(0,(int)($input['id'] ?? 0));
        $templateId = (int)($input['template_id'] ?? 0);
        $key = self::safeKey((string)($input['field_key'] ?? ''));
        $label = trim((string)($input['field_label'] ?? ''));
        $inputType = (string)($input['input_type'] ?? 'long_text');
        $sourceType = (string)($input['source_type'] ?? 'manual');
        if (!$templateId || $key === '' || $label === '') throw new InvalidArgumentException('قالب، کلید و عنوان فیلد الزامی است.');
        if (!isset(self::INPUT_TYPES[$inputType]) || !isset(self::SOURCE_TYPES[$sourceType])) throw new InvalidArgumentException('نوع ورودی یا منبع معتبر نیست.');
        if ($sourceType !== 'manual') $inputType = in_array($inputType,['readonly','readonly_list'],true) ? $inputType : 'readonly';
        $sourceKey = trim((string)($input['source_key'] ?? '')) ?: null;
        if ($sourceType !== 'manual' && $sourceType !== 'calculated') {
            $allowed = self::SOURCE_KEYS[$sourceType] ?? [];
            if (!$sourceKey || !isset($allowed[$sourceKey])) throw new InvalidArgumentException('گزینه منبع داده معتبر نیست.');
        }
        $formula = self::nullableText($input['formula_expression'] ?? null);
        if ($sourceType === 'calculated' && !$formula) throw new InvalidArgumentException('فرمول محاسباتی الزامی است.');
        $options = self::encodeOptions((string)($input['options_text'] ?? ''));
        $readonly = $sourceType !== 'manual' || !empty($input['readonly']);
        $params = [
            $templateId,$key,self::cut($label,190),$inputType,$sourceType,$sourceKey,
            self::cut(trim((string)($input['aggregation_key'] ?? '')),30) ?: null,$formula,
            self::nullableText($input['help_text'] ?? null),self::cut(trim((string)($input['placeholder'] ?? '')),255) ?: null,
            $options,!empty($input['required'])?1:0,$readonly?1:0,!isset($input['active'])||!empty($input['active'])?1:0,
            (int)($input['sort_order'] ?? 0),
        ];
        if ($id) {
            Database::execute(
                'UPDATE daily_report_template_fields SET template_id=?,field_key=?,field_label=?,input_type=?,source_type=?,
                 source_key=?,aggregation_key=?,formula_expression=?,help_text=?,placeholder=?,options_json=?,required=?,readonly=?,
                 active=?,sort_order=?,updated_at=NOW() WHERE id=?',
                array_merge($params,[$id])
            );
            return $id;
        }
        Database::execute(
            'INSERT INTO daily_report_template_fields(template_id,field_key,field_label,input_type,source_type,source_key,
             aggregation_key,formula_expression,help_text,placeholder,options_json,required,readonly,active,sort_order,created_at,updated_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())
             ON DUPLICATE KEY UPDATE field_label=VALUES(field_label),input_type=VALUES(input_type),source_type=VALUES(source_type),
             source_key=VALUES(source_key),aggregation_key=VALUES(aggregation_key),formula_expression=VALUES(formula_expression),
             help_text=VALUES(help_text),placeholder=VALUES(placeholder),options_json=VALUES(options_json),required=VALUES(required),
             readonly=VALUES(readonly),active=VALUES(active),sort_order=VALUES(sort_order),updated_at=NOW()',
            $params
        );
        $row = Database::fetch('SELECT id FROM daily_report_template_fields WHERE template_id=? AND field_key=?', [$templateId,$key]);
        return (int)($row['id'] ?? 0);
    }

    public static function saveAssignment(array $input, array $actor): int
    {
        if (!self::canManageTemplates($actor)) throw new DomainException('مجوز تخصیص قالب گزارش را ندارید.');
        $templateId = (int)($input['template_id'] ?? 0);
        $scope = (string)($input['scope_type'] ?? '');
        if (!$templateId || !isset(self::assignmentScopes($actor)[$scope])) throw new InvalidArgumentException('قالب و محدوده تخصیص الزامی است.');
        $scopeId = $scope === 'company' ? 0 : max(0,(int)($input['scope_id'] ?? 0));
        $scopeKey = trim((string)($input['scope_key'] ?? ''));
        if ($scope !== 'company' && !$scopeId && $scopeKey === '') throw new InvalidArgumentException('مقدار محدوده تخصیص را انتخاب کنید.');
        if (!OrgAccess::isAdmin($actor)) {
            if ($scope === 'user' && !OrgAccess::canAccessUser($actor,$scopeId)) throw new DomainException('کاربر انتخاب‌شده خارج از محدوده شماست.');
            if ($scope === 'department' && $scopeId !== (int)($actor['org_unit_id']??0)) throw new DomainException('فقط واحد سازمانی خودتان قابل تخصیص است.');
            if ($scope === 'sales_line' && $scopeId !== (int)($actor['sales_line_id']??0)) throw new DomainException('فقط لاین فروش خودتان قابل تخصیص است.');
            if (in_array($scope,['supervisor_team','manager_team'],true) && !OrgAccess::canAccessUser($actor,$scopeId)) {
                throw new DomainException('تیم انتخاب‌شده خارج از محدوده شماست.');
            }
        }
        Database::execute(
            'INSERT INTO daily_report_template_assignments(template_id,scope_type,scope_id,scope_key,active,created_by,created_at,updated_at)
             VALUES (?,?,?,?,1,?,NOW(),NOW())
             ON DUPLICATE KEY UPDATE active=1,created_by=VALUES(created_by),updated_at=NOW()',
            [$templateId,$scope,$scopeId,self::cut($scopeKey,150),(int)$actor['id']]
        );
        $row = Database::fetch(
            'SELECT id FROM daily_report_template_assignments WHERE template_id=? AND scope_type=? AND scope_id=? AND scope_key=?',
            [$templateId,$scope,$scopeId,self::cut($scopeKey,150)]
        );
        return (int)($row['id'] ?? 0);
    }

    public static function reportForUser(int $userId, string $date, int $templateId, array $actor): ?array
    {
        self::assertUserScope($userId, $actor);
        $report = Database::fetch(
            'SELECT r.*,t.title template_title,u.name user_name FROM daily_reports r
             JOIN daily_report_templates t ON t.id=r.template_id JOIN users u ON u.id=r.user_id
             WHERE r.user_id=? AND r.report_date=? AND r.template_id=?',
            [$userId,$date,$templateId]
        );
        if (!$report) return null;
        $report['values'] = [];
        foreach (Database::fetchAll('SELECT * FROM daily_report_values WHERE report_id=? ORDER BY id', [(int)$report['id']]) as $value) {
            $report['values'][(string)$value['field_key']] = $value;
        }
        $report['links'] = Database::fetchAll('SELECT * FROM daily_report_links WHERE report_id=? ORDER BY id DESC', [(int)$report['id']]);
        return $report;
    }

    public static function fieldPresentations(int $userId, string $date, array $template, ?array $report, array $actor): array
    {
        self::assertUserScope($userId, $actor);
        $stored = $report['values'] ?? [];
        $context = [];
        $presentations = [];
        foreach ($template['fields'] as $field) {
            $key = (string)$field['field_key'];
            if ($field['source_type'] === 'manual') {
                $row = $stored[$key] ?? [];
                $value = $row['value_text'] ?? '';
                if ($field['input_type'] === 'number') $value = $row['value_number'] ?? $value;
                $presentations[$key] = ['value'=>$value,'display'=>$row['display_text']??$value,'number'=>is_numeric($value)?(float)$value:0,'links'=>[]];
            } else {
                $resolved = self::resolveField($field,$userId,$date,$context);
                if (isset($stored[$key]) && $report) {
                    $resolved['display'] = $stored[$key]['display_text'] ?: $resolved['display'];
                    $resolved['number'] = $stored[$key]['value_number'] !== null ? (float)$stored[$key]['value_number'] : $resolved['number'];
                }
                $presentations[$key] = $resolved;
            }
            $context[$key] = (float)($presentations[$key]['number'] ?? 0);
        }
        return $presentations;
    }

    public static function saveReport(array $input, array $actor): int
    {
        if (!self::canSubmit($actor)) throw new DomainException('مجوز ثبت گزارش روزانه را ندارید.');
        $userId = (int)($input['user_id'] ?? $actor['id']);
        if ($userId !== (int)$actor['id']) throw new DomainException('هر کاربر فقط می‌تواند گزارش روزانه خودش را ثبت کند.');
        self::assertUserScope($userId,$actor);
        $date = AppDate::toGregorian((string)($input['report_date'] ?? ''));
        $templateId = (int)($input['template_id'] ?? 0);
        if (!$date || !$templateId) throw new InvalidArgumentException('تاریخ و قالب گزارش الزامی است.');
        $template = self::template($templateId);
        $target = Database::fetch('SELECT * FROM users WHERE id=? AND status="active"', [$userId]);
        if (!$template || !$target || !self::templateMatchesUser($templateId,$target)) throw new DomainException('قالب گزارش برای این کاربر تخصیص داده نشده است.');
        $status = ($input['submit_mode'] ?? '') === 'submit' ? 'submitted' : 'draft';
        $pdo = Database::connection();
        $owns = !$pdo->inTransaction();
        if ($owns) $pdo->beginTransaction();
        try {
            $reportId = self::ensureReport($template,$userId,$date,(int)$actor['id']);
            $context = [];
            foreach ($template['fields'] as $field) {
                $key = (string)$field['field_key'];
                if ($field['source_type'] === 'manual') {
                    $raw = $input['fields'][$key] ?? '';
                    $normalized = self::manualValue($field,$raw);
                    if ((int)$field['required'] && trim((string)$normalized['text']) === '') throw new InvalidArgumentException('فیلد «'.$field['field_label'].'» الزامی است.');
                    self::upsertValue($reportId,$field,$normalized['text'],$normalized['number'],$normalized['text'],false);
                    $context[$key] = $normalized['number'] ?? 0;
                    continue;
                }
                $resolved = self::resolveField($field,$userId,$date,$context);
                self::upsertValue($reportId,$field,null,$resolved['number'],$resolved['display'],true);
                foreach ($resolved['links'] as $link) self::upsertLink($reportId,(int)$field['id'],$link,(int)$actor['id']);
                $context[$key] = (float)($resolved['number'] ?? 0);
            }
            Database::execute(
                'UPDATE daily_reports SET status=?,submitted_at=IF(?="submitted",COALESCE(submitted_at,NOW()),submitted_at),updated_at=NOW() WHERE id=?',
                [$status,$status,$reportId]
            );
            self::log($reportId,$status === 'submitted'?'submitted':'saved',(int)$actor['id']);
            if ($owns) $pdo->commit();
            return $reportId;
        } catch (Throwable $e) {
            if ($owns && $pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public static function createActionFromReport(array $input, array $actor): array
    {
        if (!self::canSubmit($actor) || !ActionHubService::canCreateOwn($actor)) throw new DomainException('مجوز ایجاد اقدام از گزارش را ندارید.');
        $userId = (int)($input['user_id'] ?? $actor['id']);
        if ($userId !== (int)$actor['id']) throw new DomainException('ایجاد اقدام فقط از گزارش روزانه خودتان مجاز است.');
        self::assertUserScope($userId,$actor);
        $date = AppDate::toGregorian((string)($input['report_date'] ?? ''));
        $templateId = (int)($input['template_id'] ?? 0);
        $template = self::template($templateId);
        $target = Database::fetch('SELECT * FROM users WHERE id=? AND status="active"', [$userId]);
        if (!$date || !$template || !$target || !self::templateMatchesUser($templateId,$target)) throw new InvalidArgumentException('گزارش یا قالب معتبر نیست.');
        $reportId = self::ensureReport($template,$userId,$date,(int)$actor['id']);
        $actionInput = [
            'title'=>$input['action_title']??'',
            'description'=>$input['action_description']??'',
            'action_type_id'=>$input['action_type_id']??0,
            'assigned_to'=>$input['assigned_to']??$actor['id'],
            'priority'=>$input['priority']??'normal',
            'status'=>'new',
            'due_date'=>$input['due_date']??'',
            'source_type'=>'daily_work_report',
            'source_id'=>$reportId,
            'add_to_planner'=>!empty($input['add_to_planner'])?1:0,
        ];
        $actionId = ActionHubService::createAction($actionInput,$actor);
        $field = Database::fetch(
            'SELECT id FROM daily_report_template_fields WHERE template_id=? AND field_key="suggestions" LIMIT 1',
            [$templateId]
        );
        self::upsertLink($reportId,(int)($field['id']??0),[
            'link_type'=>'action','linked_type'=>'actions','linked_id'=>$actionId,
            'url'=>'/admin/action-view.php?id='.$actionId,'label'=>(string)$actionInput['title'],'snapshot'=>(string)$actionInput['description'],
        ],(int)$actor['id']);
        self::log($reportId,'action_created',(int)$actor['id'],'اقدام شماره '.$actionId);
        return ['report_id'=>$reportId,'action_id'=>$actionId];
    }

    public static function reports(array $actor, int $limit = 100): array
    {
        $ids = self::canViewTeam($actor) ? OrgAccess::accessibleUserIds($actor) : [(int)$actor['id']];
        $ids = array_values(array_unique(array_filter(array_map('intval',$ids))));
        if (!$ids) return [];
        $limit = max(1,min(300,$limit));
        return Database::fetchAll(
            'SELECT r.*,t.title template_title,u.name user_name,
                    (SELECT COUNT(*) FROM daily_report_links l WHERE l.report_id=r.id AND l.link_type="action") action_count
             FROM daily_reports r JOIN daily_report_templates t ON t.id=r.template_id JOIN users u ON u.id=r.user_id
             WHERE r.user_id IN ('.implode(',',array_fill(0,count($ids),'?')).')
             ORDER BY r.report_date DESC,r.updated_at DESC,r.id DESC LIMIT '.$limit,
            $ids
        );
    }

    public static function assignmentScopes(array $actor): array
    {
        if (OrgAccess::isAdmin($actor)) return self::SCOPES;
        return array_intersect_key(self::SCOPES,array_flip(['user','department','sales_line','supervisor_team','manager_team']));
    }

    public static function scopeOptions(array $actor): array
    {
        $ids = OrgAccess::accessibleUserIds($actor);
        if (!$ids) $ids=[(int)$actor['id']];
        $holders=implode(',',array_fill(0,count($ids),'?'));
        $supervisorSql = Database::tableExists('sales_team_assignments')
            ? 'SELECT id,name FROM users WHERE status="active" AND id IN ('.$holders.') AND (role_key IN ("SALES_SUPERVISOR","supervisor") OR id IN (SELECT supervisor_id FROM sales_team_assignments WHERE active=1)) ORDER BY name'
            : 'SELECT id,name FROM users WHERE status="active" AND id IN ('.$holders.') AND role_key IN ("SALES_SUPERVISOR","supervisor") ORDER BY name';
        $users=Database::fetchAll('SELECT id,name FROM users WHERE status="active" AND id IN ('.$holders.') ORDER BY display_order,name LIMIT 1000',$ids);
        $roles=OrgAccess::isAdmin($actor)?Database::fetchAll('SELECT id,title,code FROM org_roles WHERE active=1 ORDER BY sort_order,title'):[];
        $units=OrgAccess::isAdmin($actor)
            ? Database::fetchAll('SELECT id,title,code FROM org_units WHERE active=1 ORDER BY sort_order,title')
            : Database::fetchAll('SELECT id,title,code FROM org_units WHERE active=1 AND id=?',[(int)($actor['org_unit_id']??0)]);
        $lines=Database::tableExists('sales_lines')
            ? (OrgAccess::isAdmin($actor)
                ? Database::fetchAll('SELECT id,title,code FROM sales_lines WHERE active=1 ORDER BY sort_order,code')
                : Database::fetchAll('SELECT id,title,code FROM sales_lines WHERE active=1 AND id=?',[(int)($actor['sales_line_id']??0)]))
            : [];
        return [
            'users'=>$users,
            'roles'=>$roles,
            'units'=>$units,
            'sales_lines'=>$lines,
            'supervisors'=>Database::fetchAll($supervisorSql,$ids),
            'managers'=>Database::fetchAll('SELECT id,name FROM users WHERE status="active" AND id IN ('.$holders.') AND (role="manager" OR role_key IN ("SALES_MANAGER","sales_manager")) ORDER BY name',$ids),
        ];
    }

    public static function optionsText(?string $json): string
    {
        $lines=[];
        foreach(self::decodeOptions($json) as $value=>$label)$lines[]=$value.'|'.$label;
        return implode("\n",$lines);
    }

    private static function ensureReport(array $template, int $userId, string $date, int $actorId): int
    {
        Database::execute(
            'INSERT INTO daily_reports(template_id,template_version_no,user_id,report_date,status,created_by,created_at,updated_at)
             VALUES (?,?,?,?,"draft",?,NOW(),NOW())
             ON DUPLICATE KEY UPDATE updated_at=NOW()',
            [(int)$template['id'],(int)$template['version_no'],$userId,$date,$actorId]
        );
        $row = Database::fetch(
            'SELECT id FROM daily_reports WHERE user_id=? AND report_date=? AND template_id=?',
            [$userId,$date,(int)$template['id']]
        );
        return (int)($row['id'] ?? 0);
    }

    private static function templateMatchesUser(int $templateId, array $user): bool
    {
        $assignments = Database::fetchAll(
            'SELECT * FROM daily_report_template_assignments WHERE template_id=? AND active=1',
            [$templateId]
        );
        foreach ($assignments as $assignment) {
            $scope = (string)$assignment['scope_type'];
            $id = (int)$assignment['scope_id'];
            $key = (string)$assignment['scope_key'];
            if ($scope === 'company') return true;
            if ($scope === 'user' && $id === (int)$user['id']) return true;
            if ($scope === 'role' && ($id === (int)($user['org_role_id']??0) || ($key !== '' && $key === (string)($user['role_key']??'')))) return true;
            if ($scope === 'department' && ($id === (int)($user['org_unit_id']??0) || ($key !== '' && $key === (string)($user['department']??'')))) return true;
            if ($scope === 'sales_line' && ($id === (int)($user['sales_line_id']??0) || ($key !== '' && $key === (string)($user['sales_line']??'')))) return true;
            if ($scope === 'supervisor_team' && in_array($id,[(int)$user['id'],(int)($user['supervisor_id']??0),(int)($user['parent_user_id']??0)],true)) return true;
            if ($scope === 'manager_team' && in_array($id,[(int)$user['id'],(int)($user['organization_manager_id']??0),(int)($user['parent_user_id']??0)],true)) return true;
        }
        return false;
    }

    private static function assertUserScope(int $userId, array $actor): void
    {
        if ($userId < 1 || !OrgAccess::canAccessUser($actor,$userId)) throw new DomainException('کاربر گزارش خارج از محدوده سازمانی شماست.');
    }

    private static function manualValue(array $field, mixed $raw): array
    {
        $text = is_array($raw) ? implode('، ',array_map('strval',$raw)) : trim((string)$raw);
        if ($field['input_type'] === 'number') {
            $numberText = str_replace([',','٬',' '],'',AppDate::normalizeDigits($text));
            if ($numberText !== '' && !is_numeric($numberText)) throw new InvalidArgumentException('مقدار «'.$field['field_label'].'» باید عددی باشد.');
            return ['text'=>$numberText,'number'=>$numberText===''?null:(float)$numberText];
        }
        if ($field['input_type'] === 'yes_no' && !in_array($text,['','0','1'],true)) throw new InvalidArgumentException('مقدار بله/خیر معتبر نیست.');
        return ['text'=>self::cut($text,10000),'number'=>is_numeric($text)?(float)$text:null];
    }

    private static function resolveField(array $field, int $userId, string $date, array $context): array
    {
        $source = (string)$field['source_type'];
        $key = (string)($field['source_key'] ?? '');
        if ($source === 'calculated') {
            $number = self::calculate((string)$field['formula_expression'],$context,(string)$field['field_label']);
            return ['display'=>self::formatNumber($number),'number'=>$number,'links'=>[]];
        }
        if ($source === 'assigned_actions') return self::actionSource($userId,$date,$key);
        if (in_array($source,['planner_tasks','completed_tasks','open_tasks'],true)) return self::plannerSource($userId,$date,$source,$key);
        if ($source === 'kpi_values') return self::kpiSource($userId,$date);
        if ($source === 'imported_data') return self::importSource($userId,$date);
        if ($source === 'attendance') return self::attendanceSource($userId,$date);
        return ['display'=>'داده‌ای موجود نیست.','number'=>0,'links'=>[]];
    }

    private static function actionSource(int $userId, string $date, string $sourceKey): array
    {
        if (!Database::tableExists('actions')) return ['display'=>'مرکز اقدامات آماده نیست.','number'=>0,'links'=>[]];
        $where = ['assigned_to=?'];
        $params = [$userId];
        if ($sourceKey === 'done_today') {
            $where[] = 'status="done" AND DATE(COALESCE(completed_at,updated_at))=?';
            $params[] = $date;
        } elseif ($sourceKey === 'open') {
            $where[] = 'status NOT IN ("done","cancelled")';
        }
        $rows = Database::fetchAll(
            'SELECT id,title,status,due_date FROM actions WHERE '.implode(' AND ',$where).' ORDER BY due_date IS NULL,due_date,id DESC LIMIT 30',
            $params
        );
        $links = array_map(static fn(array $row): array => [
            'link_type'=>'action','linked_type'=>'actions','linked_id'=>(int)$row['id'],
            'url'=>'/admin/action-view.php?id='.(int)$row['id'],'label'=>$row['title'],
            'snapshot'=>$row['title'].' — '.(ActionHubService::STATUSES[$row['status']]??$row['status']),
        ],$rows);
        return ['display'=>self::listDisplay($rows,'title'),'number'=>count($rows),'links'=>$links];
    }

    private static function plannerSource(int $userId, string $date, string $source, string $sourceKey): array
    {
        if (!Database::tableExists('work_planner_tasks')) return ['display'=>'پلنر آماده نیست.','number'=>0,'links'=>[]];
        $where = ['COALESCE(employee_id,user_id)=?'];
        $params = [$userId];
        if ($source === 'completed_tasks') {
            $where[] = 'status="done" AND DATE(COALESCE(completed_at,updated_at))=?';
            $params[] = $date;
        } elseif ($source === 'open_tasks' || $sourceKey === 'all_open') {
            $where[] = 'status NOT IN ("done","cancelled")';
            if ($sourceKey !== 'all' && $sourceKey !== 'all_open') {
                $where[] = '(start_date IS NULL OR start_date<=?) AND (due_date IS NULL OR due_date>=?)';
                $params[] = $date;
                $params[] = $date;
            }
        } else {
            $where[] = '(start_date=? OR due_date=? OR DATE(created_at)=?)';
            array_push($params,$date,$date,$date);
        }
        $rows = Database::fetchAll(
            'SELECT id,title,status,due_date FROM work_planner_tasks WHERE '.implode(' AND ',$where).' ORDER BY due_date IS NULL,due_date,id DESC LIMIT 30',
            $params
        );
        $links = array_map(static fn(array $row): array => [
            'link_type'=>'task','linked_type'=>'work_planner_tasks','linked_id'=>(int)$row['id'],
            'url'=>'/admin/work-planner.php?task_id='.(int)$row['id'],'label'=>$row['title'],'snapshot'=>$row['title'],
        ],$rows);
        return ['display'=>self::listDisplay($rows,'title'),'number'=>count($rows),'links'=>$links];
    }

    private static function kpiSource(int $userId, string $date): array
    {
        if (!Database::tableExists('hr_kpi_scores')) return ['display'=>'داده KPI موجود نیست.','number'=>0,'links'=>[]];
        $period = Database::fetch(
            'SELECT id,title FROM hr_kpi_periods WHERE active=1 AND (end_date IS NULL OR end_date<=?) ORDER BY COALESCE(end_date,"9999-12-31") DESC,id DESC LIMIT 1',
            [$date]
        );
        if (!$period) return ['display'=>'دوره KPI فعالی یافت نشد.','number'=>0,'links'=>[]];
        $row = Database::fetch('SELECT AVG(score) score,COUNT(*) count_score FROM hr_kpi_scores WHERE employee_id=? AND period_id=?', [$userId,(int)$period['id']]) ?: [];
        $number = round((float)($row['score']??0),2);
        $display = (int)($row['count_score']??0) ? 'میانگین '.$number.' در «'.$period['title'].'»' : 'برای این دوره امتیازی ثبت نشده است.';
        $links = (int)($row['count_score']??0) ? [[
            'link_type'=>'kpi','linked_type'=>'hr_kpi_periods','linked_id'=>(int)$period['id'],
            'url'=>'/admin/hr-kpi-results.php?period_id='.(int)$period['id'].'&employee_id='.$userId,
            'label'=>(string)$period['title'],'snapshot'=>$display,
        ]] : [];
        return ['display'=>$display,'number'=>$number,'links'=>$links];
    }

    private static function importSource(int $userId, string $date): array
    {
        if (!Database::tableExists('sales_import_batches')) return ['display'=>'داده ورود اطلاعات موجود نیست.','number'=>0,'links'=>[]];
        $rows = Database::fetchAll(
            'SELECT id,source_module,file_name,pipeline_status,imported_rows,updated_rows
             FROM sales_import_batches WHERE started_by=? AND DATE(created_at)=?
             ORDER BY id DESC LIMIT 20',
            [$userId,$date]
        );
        $links = array_map(static fn(array $row): array => [
            'link_type'=>'import','linked_type'=>'sales_import_batches','linked_id'=>(int)$row['id'],
            'url'=>'/admin/import-history.php?batch_id='.(int)$row['id'],
            'label'=>(string)($row['file_name']?:$row['source_module']),
            'snapshot'=>(string)$row['source_module'].' — '.(string)$row['pipeline_status'],
        ],$rows);
        return ['display'=>self::listDisplay($rows,'file_name'),'number'=>count($rows),'links'=>$links];
    }

    private static function attendanceSource(int $userId, string $date): array
    {
        if (!Database::tableExists('hr_attendance_entries')) return ['display'=>'اطلاعات حضور موجود نیست.','number'=>0,'links'=>[]];
        $row = Database::fetch(
            'SELECT id,day_status,work_minutes,late_minutes,early_leave_minutes FROM hr_attendance_entries WHERE employee_id=? AND attendance_date=?',
            [$userId,$date]
        );
        if (!$row) return ['display'=>'برای این روز کارکردی ثبت نشده است.','number'=>0,'links'=>[]];
        $labels = ['present'=>'حاضر','half_day'=>'نیم‌روز','absent'=>'غایب','leave'=>'مرخصی','mission'=>'ماموریت','holiday'=>'تعطیل'];
        $display = ($labels[$row['day_status']]??$row['day_status']).' — '.self::minutes((int)$row['work_minutes']);
        if ((int)$row['late_minutes'] > 0) $display .= '، تأخیر '.self::minutes((int)$row['late_minutes']);
        return ['display'=>$display,'number'=>(int)$row['work_minutes'],'links'=>[[
            'link_type'=>'attendance','linked_type'=>'hr_attendance_entries','linked_id'=>(int)$row['id'],
            'url'=>'/admin/my-attendance.php?date='.$date,'label'=>'کارکرد روز','snapshot'=>$display,
        ]]];
    }

    private static function upsertValue(int $reportId, array $field, ?string $text, ?float $number, string $display, bool $readonly): void
    {
        Database::execute(
            'INSERT INTO daily_report_values(report_id,field_id,field_key,field_label,source_type,value_text,value_number,display_text,readonly,created_at,updated_at)
             VALUES (?,?,?,?,?,?,?,?,?,NOW(),NOW())
             ON DUPLICATE KEY UPDATE field_id=VALUES(field_id),field_label=VALUES(field_label),source_type=VALUES(source_type),
             value_text=VALUES(value_text),value_number=VALUES(value_number),display_text=VALUES(display_text),readonly=VALUES(readonly),updated_at=NOW()',
            [$reportId,(int)$field['id'],(string)$field['field_key'],(string)$field['field_label'],(string)$field['source_type'],$text,$number,self::cut($display,10000),$readonly?1:0]
        );
    }

    private static function upsertLink(int $reportId, int $fieldId, array $link, int $actorId): void
    {
        Database::execute(
            'INSERT INTO daily_report_links(report_id,field_id,link_type,linked_type,linked_id,link_url,label,snapshot_text,created_by,created_at)
             VALUES (?,?,?,?,?,?,?,?,?,NOW())
             ON DUPLICATE KEY UPDATE field_id=VALUES(field_id),link_url=VALUES(link_url),label=VALUES(label),snapshot_text=VALUES(snapshot_text)',
            [$reportId,$fieldId?:null,$link['link_type'],$link['linked_type'],(int)$link['linked_id'],$link['url']??null,
             self::cut((string)($link['label']??''),190)?:null,self::cut((string)($link['snapshot']??''),5000)?:null,$actorId]
        );
    }

    private static function log(int $reportId, string $key, int $actorId, string $note = ''): void
    {
        Database::execute(
            'INSERT INTO daily_report_logs(report_id,action_key,note,performed_by,created_at) VALUES (?,?,?,?,NOW())',
            [$reportId,$key,self::cut($note,5000)?:null,$actorId]
        );
    }

    private static function calculate(string $expression, array $context, string $label): float
    {
        $expression = preg_replace_callback('/\{([a-zA-Z0-9_-]+)\}/', static function(array $match) use ($context,$label): string {
            if (!array_key_exists($match[1],$context) || !is_numeric($context[$match[1]])) throw new InvalidArgumentException('ورودی «'.$match[1].'» برای محاسبه «'.$label.'» موجود نیست.');
            return (string)(float)$context[$match[1]];
        },trim($expression)) ?? '';
        if ($expression === '' || !preg_match('/^[0-9eE+\-*\/().\s]+$/',$expression)) throw new InvalidArgumentException('فرمول «'.$label.'» معتبر نیست.');
        $compact = preg_replace('/\s+/','',$expression) ?? '';
        preg_match_all('/(?:\d+(?:\.\d+)?(?:[eE][+\-]?\d+)?)|[()+\-*\/]/',$compact,$matches);
        $tokens = $matches[0] ?? [];
        if (!$tokens || implode('',$tokens) !== $compact) throw new InvalidArgumentException('ساختار فرمول «'.$label.'» معتبر نیست.');
        $output=[];$operators=[];$precedence=['+'=>1,'-'=>1,'*'=>2,'/'=>2,'u-'=>3];$previous=null;
        foreach($tokens as $token){
            if(is_numeric($token))$output[]=(float)$token;
            elseif($token==='(')$operators[]=$token;
            elseif($token===')'){while($operators&&end($operators)!=='(')$output[]=array_pop($operators);if(!$operators||array_pop($operators)!=='(')throw new InvalidArgumentException('پرانتز فرمول معتبر نیست.');}
            else{$current=$token;if($current==='-'&&($previous===null||$previous==='('||isset($precedence[$previous])))$current='u-';while($operators&&isset($precedence[end($operators)])&&$precedence[end($operators)]>=$precedence[$current])$output[]=array_pop($operators);$operators[]=$current;$token=$current;}
            $previous=$token;
        }
        while($operators){$operator=array_pop($operators);if($operator==='(')throw new InvalidArgumentException('پرانتز فرمول معتبر نیست.');$output[]=$operator;}
        $stack=[];
        foreach($output as $token){
            if(is_float($token)||is_int($token)){$stack[]=(float)$token;continue;}
            if($token==='u-'){if(!$stack)throw new InvalidArgumentException('فرمول کامل نیست.');$stack[]=-array_pop($stack);continue;}
            if(count($stack)<2)throw new InvalidArgumentException('فرمول کامل نیست.');
            $right=array_pop($stack);$left=array_pop($stack);
            if($token==='/'&&abs($right)<0.0000000001)throw new InvalidArgumentException('تقسیم بر صفر مجاز نیست.');
            $stack[]=match($token){'+'=>$left+$right,'-'=>$left-$right,'*'=>$left*$right,'/'=>$left/$right,default=>0};
        }
        if(count($stack)!==1||!is_finite($stack[0]))throw new InvalidArgumentException('نتیجه فرمول معتبر نیست.');
        return round($stack[0],4);
    }

    private static function listDisplay(array $rows, string $field): string
    {
        if (!$rows) return 'موردی ثبت نشده است.';
        return implode(' • ',array_map(static fn(array $row): string => (string)($row[$field]??'-'),array_slice($rows,0,8)))
            .(count($rows)>8?' و '.(count($rows)-8).' مورد دیگر':'');
    }

    private static function minutes(int $minutes): string
    {
        $hours = intdiv(max(0,$minutes),60);
        $rest = max(0,$minutes)%60;
        return ($hours ? $hours.' ساعت' : '').($hours&&$rest?' و ':'').($rest?$rest.' دقیقه':(!$hours?'۰ دقیقه':''));
    }

    private static function formatNumber(float $number): string
    {
        return number_format($number,abs($number-round($number))<0.0001?0:2);
    }

    private static function encodeOptions(string $text): ?string
    {
        $options=[];
        foreach(preg_split('/\R/u',trim($text))?:[] as $line){
            $line=trim($line);if($line==='')continue;
            [$value,$label]=array_pad(explode('|',$line,2),2,'');
            $value=self::safeKey($value);$label=trim($label);
            if($value===''||$label==='')throw new InvalidArgumentException('گزینه‌ها باید به شکل «مقدار|عنوان فارسی» باشند.');
            $options[$value]=self::cut($label,190);
        }
        return $options?json_encode($options,JSON_UNESCAPED_UNICODE):null;
    }

    private static function decodeOptions(?string $json): array
    {
        $decoded=$json?json_decode($json,true):[];
        return is_array($decoded)?$decoded:[];
    }

    private static function safeKey(string $value): string
    {
        $value=strtolower(trim(AppDate::normalizeDigits($value)));
        return trim((string)preg_replace('/[^a-z0-9_-]+/','_',$value),'_');
    }

    private static function nullableText(mixed $value): ?string
    {
        $value=trim((string)$value);
        return $value===''?null:self::cut($value,10000);
    }

    private static function cut(string $value, int $length): string
    {
        return function_exists('mb_substr')?mb_substr($value,0,$length,'UTF-8'):substr($value,0,$length);
    }
}
