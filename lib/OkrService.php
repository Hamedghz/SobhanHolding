<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/OrgAccess.php';
require_once __DIR__ . '/OkrDataSourceRegistry.php';
require_once __DIR__ . '/../services/WorkPlannerService.php';

class OkrService
{
    public const CYCLE_TYPES = ['monthly'=>'ماهانه','quarterly'=>'فصلی','semiannual'=>'شش‌ماهه','annual'=>'سالانه','project'=>'پروژه‌ای'];
    public const CYCLE_STATUSES = ['draft'=>'پیش‌نویس','open'=>'باز','active'=>'فعال','closed'=>'بسته'];
    public const OBJECTIVE_LEVELS = ['company'=>'شرکت','executive'=>'مدیرعامل','unit'=>'واحد','sales_manager'=>'مدیر فروش','sales_line'=>'لاین فروش','supervisor'=>'سرپرست','employee'=>'کارمند','project'=>'پروژه'];
    public const OBJECTIVE_TYPES = ['committed'=>'قطعی (Committed)','aspirational'=>'توسعه‌ای (Aspirational)'];
    public const PRIORITIES = ['low'=>'کم','normal'=>'عادی','high'=>'بالا','urgent'=>'فوری'];
    public const OBJECTIVE_STATUSES = ['draft'=>'پیش‌نویس','pending_approval'=>'در انتظار تأیید','approved'=>'تأییدشده','active'=>'فعال','at_risk'=>'در معرض خطر','off_track'=>'عقب‌افتاده','completed'=>'تکمیل‌شده','cancelled'=>'لغوشده'];
    public const HEALTH_STATUSES = ['on_track'=>'در مسیر','at_risk'=>'در معرض خطر','off_track'=>'خارج از مسیر','completed'=>'تکمیل‌شده'];
    public const CONFIDENCE_LEVELS = ['high'=>'بالا','medium'=>'متوسط','low'=>'پایین'];
    public const METRIC_TYPES = ['number'=>'عدد','percentage'=>'درصد','currency'=>'مبلغ','count'=>'تعداد','duration'=>'مدت','score'=>'امتیاز'];
    public const UNITS = ['rial'=>'ریال','percent'=>'درصد','count'=>'تعداد','day'=>'روز','hour'=>'ساعت','person'=>'نفر','customer'=>'مشتری','invoice'=>'فاکتور','product'=>'کالا','brand'=>'برند','score'=>'امتیاز'];
    public const DIRECTIONS = ['increase'=>'افزایشی','decrease'=>'کاهشی'];
    public const ALIGNMENT_TYPES = ['contributes'=>'مشارکت مستقیم','supports'=>'پشتیبانی','depends_on'=>'وابسته به'];

    private const PILOT_ROLE_CODES = ['CEO','SALES_MANAGER','SALES_SUPERVISOR','IT_STAFF','IT_MANAGER','TECHNOLOGY_MANAGER','PLANNING_STAFF'];

    public static function menuAllowed(): bool
    {
        return self::canView();
    }

    public static function canView(?array $user = null): bool
    {
        $user ??= Auth::user();
        if (!$user) return false;
        return OrgAccess::isAdmin($user) || Auth::can('okr.view') || Auth::can('okr.manage') || self::isPilotUser($user);
    }

    public static function canCreate(?array $user = null): bool
    {
        $user ??= Auth::user();
        if (!$user) return false;
        return OrgAccess::isAdmin($user) || Auth::can('okr.manage', 'create') || Auth::can('okr.manage', 'edit') || self::isPilotUser($user);
    }

    public static function canManageCycles(): bool
    {
        return Auth::isAdmin() || Auth::can('okr.cycles', 'edit') || Auth::can('okr.cycles', 'create');
    }

    public static function isPilotUser(array $user): bool
    {
        return in_array(self::normalizedRoleCode($user), self::PILOT_ROLE_CODES, true);
    }

    public static function normalizedRoleCode(array $user): string
    {
        $code = (string)($user['org_role_code'] ?? $user['role_key'] ?? '');
        return strtoupper(str_replace('-', '_', trim($code)));
    }

    public static function accessibleOwnerIds(array $user): array
    {
        $ids = OrgAccess::accessibleUserIds($user);
        return $ids ?: [(int)$user['id']];
    }

    public static function availableOwners(?array $user = null): array
    {
        $user ??= Auth::user();
        if (!$user) return [];
        $ids = self::accessibleOwnerIds($user);
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $rows = Database::fetchAll("SELECT u.id,u.name,u.department,u.role_key,u.sales_line,u.org_unit_id,r.code org_role_code,r.title org_role_title,ou.title org_unit_title,
            MAX(CASE WHEN p.module_key IN ('okr.view','okr.manage','okr.checkin') AND (p.can_view=1 OR p.can_create=1 OR p.can_edit=1) THEN 1 ELSE 0 END) okr_permission
            FROM users u
            LEFT JOIN org_roles r ON r.id=u.org_role_id
            LEFT JOIN org_units ou ON ou.id=u.org_unit_id
            LEFT JOIN user_permissions p ON p.user_id=u.id
            WHERE u.status='active' AND u.id IN ({$ph})
            GROUP BY u.id,u.name,u.department,u.role_key,u.sales_line,u.org_unit_id,r.code,r.title,ou.title
            ORDER BY u.name", $ids);
        if (OrgAccess::isAdmin($user)) return $rows;
        return array_values(array_filter($rows, static function(array $row): bool {
            $code = strtoupper(str_replace('-', '_', trim((string)($row['org_role_code'] ?: $row['role_key']))));
            return in_array($code, self::PILOT_ROLE_CODES, true) || (int)$row['okr_permission'] === 1;
        }));
    }

    public static function cycles(bool $includeClosed = false): array
    {
        $where = $includeClosed ? '1=1' : 'status<>"closed"';
        return Database::fetchAll("SELECT c.*,u.name creator_name,(SELECT COUNT(*) FROM okr_objectives o WHERE o.cycle_id=c.id) objective_count FROM okr_cycles c LEFT JOIN users u ON u.id=c.created_by WHERE {$where} ORDER BY c.start_date DESC,c.id DESC");
    }

    public static function saveCycle(array $input, int $actorId, int $id = 0): int
    {
        if (!self::canManageCycles()) throw new DomainException('برای مدیریت دوره‌های OKR دسترسی ندارید.');
        $title = self::text($input['title'] ?? '', 190);
        $type = self::enum($input['cycle_type'] ?? '', self::CYCLE_TYPES, 'quarterly');
        $status = self::enum($input['status'] ?? '', self::CYCLE_STATUSES, 'draft');
        $frequency = self::enum($input['checkin_frequency'] ?? '', ['weekly'=>1,'monthly'=>1,'none'=>1], 'weekly');
        $start = self::date((string)($input['start_date'] ?? ''));
        $end = self::date((string)($input['end_date'] ?? ''));
        $registration = self::nullableDate((string)($input['registration_deadline'] ?? ''));
        $approval = self::nullableDate((string)($input['approval_deadline'] ?? ''));
        if ($title === '' || !$start || !$end || $start > $end) throw new InvalidArgumentException('عنوان و بازه معتبر دوره الزامی است.');
        if ($registration && ($registration < $start || $registration > $end)) throw new InvalidArgumentException('مهلت ثبت هدف باید داخل بازه دوره باشد.');
        if ($approval && ($approval < $start || $approval > $end)) throw new InvalidArgumentException('مهلت تأیید باید داخل بازه دوره باشد.');
        $count = max(0, min(366, (int)($input['checkin_count'] ?? 0)));
        if ($id > 0) {
            $old = Database::fetch('SELECT * FROM okr_cycles WHERE id=?', [$id]);
            if (!$old) throw new InvalidArgumentException('دوره OKR یافت نشد.');
            if (Database::fetch('SELECT id FROM okr_objectives WHERE cycle_id=? AND (start_date<? OR due_date>?) LIMIT 1', [$id,$start,$end])) throw new InvalidArgumentException('بازه جدید با یکی از اهداف موجود این دوره سازگار نیست.');
            Database::execute('UPDATE okr_cycles SET title=?,cycle_type=?,start_date=?,end_date=?,status=?,registration_deadline=?,approval_deadline=?,checkin_frequency=?,checkin_count=?,updated_at=NOW() WHERE id=?', [$title,$type,$start,$end,$status,$registration,$approval,$frequency,$count,$id]);
            self::audit(null, null, $actorId, 'cycle_updated', $old, compact('title','type','start','end','status','registration','approval','frequency','count'), 'ویرایش دوره #'.$id);
            return $id;
        }
        Database::execute('INSERT INTO okr_cycles(title,cycle_type,start_date,end_date,status,registration_deadline,approval_deadline,checkin_frequency,checkin_count,created_by,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,NOW(),NOW())', [$title,$type,$start,$end,$status,$registration,$approval,$frequency,$count,$actorId]);
        $id = (int)Database::lastInsertId();
        self::audit(null, null, $actorId, 'cycle_created', null, ['cycle_id'=>$id,'title'=>$title], 'ایجاد دوره OKR');
        return $id;
    }

    public static function objective(int $id, bool $enforceScope = true): ?array
    {
        $row = Database::fetch('SELECT o.*,c.title cycle_title,c.status cycle_status,c.start_date cycle_start_date,c.end_date cycle_end_date,u.name owner_name,u.role_key owner_role_key,r.code owner_role_code,r.title owner_role_title,ou.title org_unit_title,p.title parent_title,creator.name creator_name,approver.name approver_name FROM okr_objectives o JOIN okr_cycles c ON c.id=o.cycle_id JOIN users u ON u.id=o.owner_user_id LEFT JOIN org_roles r ON r.id=u.org_role_id LEFT JOIN org_units ou ON ou.id=o.org_unit_id LEFT JOIN okr_objectives p ON p.id=o.parent_objective_id LEFT JOIN users creator ON creator.id=o.created_by LEFT JOIN users approver ON approver.id=o.approved_by WHERE o.id=?', [$id]);
        if (!$row) return null;
        if ($enforceScope && !self::canViewObjective($row)) return null;
        return $row;
    }

    public static function canViewObjective(array $objective, ?array $user = null): bool
    {
        $user ??= Auth::user();
        if (!$user || !self::canView($user)) return false;
        if (OrgAccess::isAdmin($user)) return true;
        return (int)$objective['created_by'] === (int)$user['id'] || in_array((int)$objective['owner_user_id'], self::accessibleOwnerIds($user), true);
    }

    public static function canManageObjective(array $objective, ?array $user = null): bool
    {
        $user ??= Auth::user();
        if (!$user || !self::canViewObjective($objective, $user)) return false;
        if (OrgAccess::isAdmin($user)) return true;
        if ((int)$objective['owner_user_id'] === (int)$user['id'] || (int)$objective['created_by'] === (int)$user['id']) return true;
        $code = self::normalizedRoleCode($user);
        $managerRole = in_array($code, ['CEO','SALES_MANAGER','SALES_SUPERVISOR','IT_MANAGER','TECHNOLOGY_MANAGER'], true);
        return (Auth::can('okr.manage', 'edit') || $managerRole) && in_array((int)$objective['owner_user_id'], self::accessibleOwnerIds($user), true);
    }

    public static function canApproveObjective(array $objective, ?array $user = null): bool
    {
        $user ??= Auth::user();
        if (!$user || !self::canViewObjective($objective, $user)) return false;
        if (OrgAccess::isAdmin($user) || Auth::can('okr.approve', 'edit') || Auth::can('okr.approve')) return true;
        if ((int)$objective['owner_user_id'] === (int)$user['id']) return false;
        $code = self::normalizedRoleCode($user);
        return in_array($code, ['CEO','SALES_MANAGER','SALES_SUPERVISOR','IT_MANAGER','TECHNOLOGY_MANAGER'], true)
            && in_array((int)$objective['owner_user_id'], self::accessibleOwnerIds($user), true);
    }

    public static function saveObjective(array $input, int $actorId, int $id = 0): int
    {
        $actor = Auth::user();
        if (!$actor || !self::canCreate($actor)) throw new DomainException('برای ایجاد یا ویرایش OKR دسترسی ندارید.');
        $old = $id ? self::objective($id) : null;
        if ($id && (!$old || !self::canManageObjective($old, $actor))) throw new DomainException('این هدف در محدوده مدیریت شما نیست.');
        if ($old && $old['status'] === 'pending_approval' && !OrgAccess::isAdmin($actor)) throw new DomainException('هدف در انتظار تأیید است و فعلاً قابل ویرایش نیست.');
        $cycleId = (int)($input['cycle_id'] ?? 0);
        $cycle = Database::fetch('SELECT * FROM okr_cycles WHERE id=?', [$cycleId]);
        $ownerId = (int)($input['owner_user_id'] ?? 0);
        $owner = Database::fetch('SELECT u.*,r.code org_role_code FROM users u LEFT JOIN org_roles r ON r.id=u.org_role_id WHERE u.id=? AND u.status="active"', [$ownerId]);
        if (!$cycle || !$owner) throw new InvalidArgumentException('دوره و مالک معتبر هدف الزامی است.');
        if ($cycle['status'] === 'closed') throw new InvalidArgumentException('دوره بسته‌شده برای ثبت یا انتقال هدف قابل استفاده نیست.');
        if (!OrgAccess::isAdmin($actor) && !in_array($ownerId, self::accessibleOwnerIds($actor), true)) throw new DomainException('مالک انتخاب‌شده خارج از محدوده سازمانی شماست.');
        $allowedOwnerIds = array_map('intval', array_column(self::availableOwners($actor), 'id'));
        if (!in_array($ownerId, $allowedOwnerIds, true)) throw new DomainException('مالک انتخاب‌شده در گروه آزمایشی OKR یا مجوز صریح این ماژول نیست.');
        $parentId = (int)($input['parent_objective_id'] ?? 0) ?: null;
        if ($parentId) {
            $parent = self::objective($parentId);
            if (!$parent || $parentId === $id) throw new InvalidArgumentException('هدف والد معتبر نیست.');
            $cursor = $parent;
            for ($depth = 0; $id > 0 && $depth < 50 && $cursor; $depth++) {
                if ((int)$cursor['id'] === $id) throw new InvalidArgumentException('انتخاب هدف والد باعث حلقه در ساختار OKR می‌شود.');
                $nextId = (int)($cursor['parent_objective_id'] ?? 0);
                $cursor = $nextId ? self::objective($nextId) : null;
            }
        }
        $title = self::text($input['title'] ?? '', 255);
        $description = self::text($input['description'] ?? '', 10000);
        $level = self::enum($input['objective_level'] ?? '', self::OBJECTIVE_LEVELS, 'employee');
        $type = self::enum($input['okr_type'] ?? '', self::OBJECTIVE_TYPES, 'committed');
        $priority = self::enum($input['priority'] ?? '', self::PRIORITIES, 'normal');
        $weight = self::decimal($input['weight'] ?? 100, 0, 100);
        $start = self::date((string)($input['start_date'] ?? ''));
        $due = self::date((string)($input['due_date'] ?? ''));
        if ($title === '' || !$start || !$due || $start > $due) throw new InvalidArgumentException('عنوان و بازه معتبر هدف الزامی است.');
        if ($start < $cycle['start_date'] || $due > $cycle['end_date']) throw new InvalidArgumentException('بازه هدف باید داخل دوره OKR باشد.');
        $salesLine = self::text($input['sales_line'] ?? ($owner['sales_line'] ?? ''), 50) ?: null;
        if ($salesLine !== null && Database::tableExists('sales_lines')) {
            $line = Database::fetch('SELECT code FROM sales_lines WHERE code=? AND active=1 LIMIT 1', [$salesLine]);
            $legacyLineUnchanged = $old
                && trim((string)($old['sales_line'] ?? '')) !== ''
                && hash_equals(trim((string)$old['sales_line']), $salesLine);
            if (!$line && !$legacyLineUnchanged) {
                throw new InvalidArgumentException('لاین فروش باید از فهرست فعال ساختار فروش انتخاب شود.');
            }
        }
        $orgUnitId = (int)($owner['org_unit_id'] ?? 0) ?: null;
        if ($id) {
            Database::execute('UPDATE okr_objectives SET cycle_id=?,parent_objective_id=?,owner_user_id=?,org_unit_id=?,sales_line=?,objective_level=?,title=?,description=?,okr_type=?,priority=?,weight=?,start_date=?,due_date=?,updated_at=NOW() WHERE id=?', [$cycleId,$parentId,$ownerId,$orgUnitId,$salesLine,$level,$title,$description ?: null,$type,$priority,$weight,$start,$due,$id]);
            self::audit($id, null, $actorId, 'objective_updated', $old, compact('cycleId','parentId','ownerId','orgUnitId','salesLine','level','title','type','priority','weight','start','due'), 'ویرایش هدف');
            return $id;
        }
        Database::execute('INSERT INTO okr_objectives(cycle_id,parent_objective_id,owner_user_id,org_unit_id,sales_line,objective_level,title,description,okr_type,priority,weight,status,progress_score,health_status,start_date,due_date,created_by,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,"draft",0,"on_track",?,?,?,NOW(),NOW())', [$cycleId,$parentId,$ownerId,$orgUnitId,$salesLine,$level,$title,$description ?: null,$type,$priority,$weight,$start,$due,$actorId]);
        $id = (int)Database::lastInsertId();
        self::audit($id, null, $actorId, 'objective_created', null, ['title'=>$title,'owner_user_id'=>$ownerId,'cycle_id'=>$cycleId], 'ایجاد هدف');
        Auth::log($actorId, 'okr_objective_created', 'okr', $id);
        return $id;
    }

    public static function saveKeyResult(int $objectiveId, array $input, int $actorId, int $id = 0): int
    {
        $objective = self::objective($objectiveId);
        if (!$objective || !self::canManageObjective($objective)) throw new DomainException('برای مدیریت نتایج کلیدی این هدف دسترسی ندارید.');
        if ($objective['status'] === 'pending_approval' && !Auth::isAdmin()) throw new DomainException('هدف در انتظار تأیید است و نتیجه کلیدی قابل ویرایش نیست.');
        $old = $id ? Database::fetch('SELECT * FROM okr_key_results WHERE id=? AND objective_id=?', [$id,$objectiveId]) : null;
        if ($id && !$old) throw new InvalidArgumentException('نتیجه کلیدی یافت نشد.');
        $ownerId = (int)($input['owner_user_id'] ?? $objective['owner_user_id']);
        if (!in_array($ownerId, self::accessibleOwnerIds(Auth::user()), true) && !Auth::isAdmin()) throw new DomainException('مسئول نتیجه کلیدی خارج از محدوده شماست.');
        if (!in_array($ownerId, array_map('intval', array_column(self::availableOwners(), 'id')), true)) throw new DomainException('مسئول نتیجه کلیدی در گروه آزمایشی OKR یا دارای مجوز صریح نیست.');
        $title = self::text($input['title'] ?? '', 255);
        $metric = self::enum($input['metric_type'] ?? '', self::METRIC_TYPES, 'number');
        $unit = self::enum($input['unit'] ?? '', self::UNITS, 'count');
        $direction = self::enum($input['direction'] ?? '', self::DIRECTIONS, 'increase');
        $baseline = self::decimal($input['baseline_value'] ?? 0, -9999999999999999, 9999999999999999);
        $target = self::decimal($input['target_value'] ?? 0, -9999999999999999, 9999999999999999);
        $weight = self::decimal($input['weight'] ?? 0, 0, 100);
        $dataSourceType = in_array((string)($input['data_source_type'] ?? 'manual'), ['manual', 'automatic'], true)
            ? (string)$input['data_source_type'] : 'manual';
        $dataSourceConfig = $dataSourceType === 'automatic' ? OkrDataSourceRegistry::configFromInput($input) : null;
        $dataSourceJson = self::json($dataSourceConfig);
        $due = self::date((string)($input['due_date'] ?? ''));
        if ($title === '' || !$due) throw new InvalidArgumentException('عنوان و مهلت نتیجه کلیدی الزامی است.');
        if ($baseline === $target) throw new InvalidArgumentException('مقدار مبنا و هدف باید متفاوت باشند.');
        if ($direction === 'increase' && $target < $baseline) throw new InvalidArgumentException('برای نتیجه افزایشی، مقدار هدف باید بیشتر از مقدار مبنا باشد.');
        if ($direction === 'decrease' && $target > $baseline) throw new InvalidArgumentException('برای نتیجه کاهشی، مقدار هدف باید کمتر از مقدار مبنا باشد.');
        if ($due < $objective['start_date'] || $due > $objective['due_date']) throw new InvalidArgumentException('مهلت نتیجه کلیدی باید داخل بازه هدف باشد.');
        $current = $old ? (float)$old['current_value'] : $baseline;
        $progress = self::progressPercent($baseline, $target, $current, $direction);
        if ($id) {
            Database::execute('UPDATE okr_key_results SET title=?,metric_type=?,baseline_value=?,target_value=?,unit=?,direction=?,weight=?,data_source_type=?,data_source_config_json=?,calculation_formula=NULL,owner_user_id=?,due_date=?,progress_percent=?,updated_at=NOW() WHERE id=?', [$title,$metric,$baseline,$target,$unit,$direction,$weight,$dataSourceType,$dataSourceJson,$ownerId,$due,$progress,$id]);
            self::audit($objectiveId, $id, $actorId, 'key_result_updated', $old, compact('title','metric','baseline','target','unit','direction','weight','dataSourceType','dataSourceConfig','ownerId','due','progress'), 'ویرایش نتیجه کلیدی');
            self::recalculateObjective($objectiveId, $actorId, 'key_result_update');
            return $id;
        }
        Database::execute('INSERT INTO okr_key_results(objective_id,title,metric_type,baseline_value,target_value,current_value,unit,direction,weight,data_source_type,data_source_config_json,owner_user_id,status,health_status,progress_percent,due_date,last_calculated_at,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?, "active","on_track",?,?,NULL,NOW(),NOW())', [$objectiveId,$title,$metric,$baseline,$target,$current,$unit,$direction,$weight,$dataSourceType,$dataSourceJson,$ownerId,$progress,$due]);
        $id = (int)Database::lastInsertId();
        self::audit($objectiveId, $id, $actorId, 'key_result_created', null, ['title'=>$title,'weight'=>$weight,'target'=>$target,'data_source_type'=>$dataSourceType], 'ایجاد نتیجه کلیدی');
        self::recalculateObjective($objectiveId, $actorId, 'key_result_create');
        return $id;
    }

    public static function dataSourceDefinitions(): array
    {
        return OkrDataSourceRegistry::definitions();
    }

    public static function keyResultSourceConfig(array $keyResult): array
    {
        return OkrDataSourceRegistry::decodeConfig($keyResult['data_source_config_json'] ?? null);
    }

    public static function keyResultSourceLabel(array $keyResult): string
    {
        return ($keyResult['data_source_type'] ?? 'manual') === 'automatic'
            ? OkrDataSourceRegistry::displayLabel($keyResult['data_source_config_json'] ?? null)
            : 'ثبت دستی';
    }

    public static function refreshKeyResult(int $objectiveId, int $keyResultId, int $actorId): array
    {
        $objective = self::objective($objectiveId);
        $keyResult = Database::fetch('SELECT * FROM okr_key_results WHERE id=? AND objective_id=?', [$keyResultId, $objectiveId]);
        if (!$objective || !$keyResult || !self::canManageObjective($objective)) {
            throw new DomainException('برای بروزرسانی این نتیجه کلیدی دسترسی ندارید.');
        }
        if (($keyResult['data_source_type'] ?? 'manual') !== 'automatic') {
            throw new InvalidArgumentException('این نتیجه کلیدی منبع خودکار ندارد.');
        }
        $actor = Auth::user();
        if (!$actor || (int)$actor['id'] !== $actorId) throw new DomainException('کاربر معتبر برای محاسبه خودکار یافت نشد.');
        $config = OkrDataSourceRegistry::decodeConfig($keyResult['data_source_config_json'] ?? null);
        $result = OkrDataSourceRegistry::calculate($config, $keyResult, $actor);
        if ((int)$result['row_count'] < 1) {
            throw new InvalidArgumentException('برای فیلترهای این منبع داده‌ای یافت نشد؛ مقدار فعلی KR تغییر نکرد.');
        }
        $value = (float)$result['value'];
        $progress = self::progressPercent((float)$keyResult['baseline_value'], (float)$keyResult['target_value'], $value, (string)$keyResult['direction']);
        $health = $progress >= 100 ? 'completed' : (((string)$keyResult['due_date'] < date('Y-m-d')) ? 'off_track' : (string)$keyResult['health_status']);
        if ($health === 'completed' && $progress < 100) $health = 'on_track';
        Database::execute('UPDATE okr_key_results SET current_value=?,progress_percent=?,health_status=?,status=?,last_calculated_at=NOW(),updated_at=NOW() WHERE id=?', [$value,$progress,$health,$health === 'completed' ? 'completed' : 'active',$keyResultId]);
        self::audit($objectiveId, $keyResultId, $actorId, 'key_result_auto_refreshed', ['current_value'=>$keyResult['current_value'],'progress_percent'=>$keyResult['progress_percent']], ['current_value'=>$value,'progress_percent'=>$progress,'row_count'=>$result['row_count'],'source'=>$result['label']], 'بروزرسانی از منبع داده امن');
        self::recalculateObjective($objectiveId, $actorId, 'automatic_source');
        return $result + ['progress_percent'=>$progress];
    }

    public static function refreshAutomaticResults(int $objectiveId, int $actorId): array
    {
        $objective = self::objective($objectiveId);
        if (!$objective || !self::canManageObjective($objective)) throw new DomainException('برای بروزرسانی نتایج این هدف دسترسی ندارید.');
        $rows = Database::fetchAll('SELECT id FROM okr_key_results WHERE objective_id=? AND data_source_type="automatic" AND status<>"cancelled" ORDER BY id', [$objectiveId]);
        $results = [];
        foreach ($rows as $row) {
            $results[] = self::refreshKeyResult($objectiveId, (int)$row['id'], $actorId);
        }
        return $results;
    }

    public static function alignmentCandidates(array $objective): array
    {
        return array_values(array_filter(self::listObjectives(['cycle_id'=>(int)$objective['cycle_id']]), static fn(array $row): bool =>
            (int)$row['id'] !== (int)$objective['id'] && $row['status'] !== 'cancelled'
        ));
    }

    public static function saveAlignment(int $childObjectiveId, array $input, int $actorId): int
    {
        $child = self::objective($childObjectiveId);
        if (!$child || !self::canManageObjective($child)) throw new DomainException('برای مدیریت هم‌راستایی این هدف دسترسی ندارید.');
        $parentId = (int)($input['parent_objective_id'] ?? 0);
        $parent = $parentId > 0 ? self::objective($parentId) : null;
        if (!$parent || $parentId === $childObjectiveId) throw new InvalidArgumentException('هدف بالادستی معتبر نیست.');
        if ((int)$parent['cycle_id'] !== (int)$child['cycle_id']) throw new InvalidArgumentException('اهداف هم‌راستا باید در یک دوره OKR باشند.');
        if (self::alignmentWouldCycle($childObjectiveId, $parentId)) throw new InvalidArgumentException('این هم‌راستایی باعث حلقه در زنجیره اهداف می‌شود.');
        $type = self::enum($input['alignment_type'] ?? '', self::ALIGNMENT_TYPES, 'contributes');
        $weight = self::decimal($input['contribution_weight'] ?? 100, 0, 100);
        $note = self::text($input['alignment_note'] ?? '', 500);
        $existing = Database::fetch('SELECT * FROM okr_alignments WHERE child_objective_id=? AND parent_objective_id=?', [$childObjectiveId,$parentId]);
        if ($existing) {
            Database::execute('UPDATE okr_alignments SET alignment_type=?,contribution_weight=?,note=?,active=1,updated_at=NOW() WHERE id=?', [$type,$weight,$note ?: null,(int)$existing['id']]);
            $id = (int)$existing['id'];
        } else {
            Database::execute('INSERT INTO okr_alignments(child_objective_id,parent_objective_id,alignment_type,contribution_weight,note,active,created_by,created_at,updated_at) VALUES (?,?,?,?,?,1,?,NOW(),NOW())', [$childObjectiveId,$parentId,$type,$weight,$note ?: null,$actorId]);
            $id = (int)Database::lastInsertId();
        }
        if ($type === 'contributes' && empty($child['parent_objective_id'])) {
            Database::execute('UPDATE okr_objectives SET parent_objective_id=?,updated_at=NOW() WHERE id=?', [$parentId,$childObjectiveId]);
        }
        self::audit($childObjectiveId, null, $actorId, 'objective_alignment_saved', $existing, ['parent_objective_id'=>$parentId,'alignment_type'=>$type,'contribution_weight'=>$weight], 'ثبت هم‌راستایی هدف');
        return $id;
    }

    public static function deactivateAlignment(int $childObjectiveId, int $alignmentId, int $actorId): void
    {
        $child = self::objective($childObjectiveId);
        if (!$child || !self::canManageObjective($child)) throw new DomainException('برای مدیریت هم‌راستایی این هدف دسترسی ندارید.');
        $alignment = Database::fetch('SELECT * FROM okr_alignments WHERE id=? AND child_objective_id=? AND active=1', [$alignmentId,$childObjectiveId]);
        if (!$alignment) throw new InvalidArgumentException('هم‌راستایی فعال یافت نشد.');
        Database::execute('UPDATE okr_alignments SET active=0,updated_at=NOW() WHERE id=?', [$alignmentId]);
        self::audit($childObjectiveId, null, $actorId, 'objective_alignment_disabled', $alignment, ['active'=>0], 'غیرفعال‌سازی هم‌راستایی بدون حذف داده');
    }

    public static function submitObjective(int $objectiveId, int $actorId): void
    {
        $objective = self::objective($objectiveId);
        if (!$objective || !self::canManageObjective($objective)) throw new DomainException('برای ارسال این هدف دسترسی ندارید.');
        if ($objective['status'] !== 'draft') throw new InvalidArgumentException('فقط هدف پیش‌نویس قابل ارسال برای تأیید است.');
        $summary = Database::fetch('SELECT COUNT(*) kr_count,COALESCE(SUM(weight),0) total_weight FROM okr_key_results WHERE objective_id=? AND status<>"cancelled"', [$objectiveId]);
        if ((int)($summary['kr_count'] ?? 0) < 1) throw new InvalidArgumentException('حداقل یک نتیجه کلیدی برای هدف ثبت کنید.');
        if (abs((float)$summary['total_weight'] - 100.0) > 0.01) throw new InvalidArgumentException('مجموع وزن نتایج کلیدی باید دقیقاً ۱۰۰ درصد باشد.');
        Database::execute('UPDATE okr_objectives SET status="pending_approval",updated_at=NOW() WHERE id=?', [$objectiveId]);
        Database::execute('INSERT INTO okr_approvals(objective_id,requested_by,decision,created_at) VALUES (?, ?,"pending",NOW())', [$objectiveId,$actorId]);
        self::audit($objectiveId, null, $actorId, 'objective_submitted', ['status'=>$objective['status']], ['status'=>'pending_approval'], 'ارسال برای تأیید');
        Auth::log($actorId, 'okr_objective_submitted', 'okr', $objectiveId);
    }

    public static function decideObjective(int $objectiveId, string $decision, string $note, int $actorId): void
    {
        $objective = self::objective($objectiveId);
        if (!$objective || !self::canApproveObjective($objective)) throw new DomainException('برای تأیید یا رد این هدف دسترسی ندارید.');
        if ($objective['status'] !== 'pending_approval') throw new InvalidArgumentException('این هدف در وضعیت انتظار تأیید نیست.');
        if (!in_array($decision, ['approved','rejected'], true)) throw new InvalidArgumentException('تصمیم تأیید نامعتبر است.');
        $note = self::text($note, 5000);
        $status = $decision === 'approved' ? 'active' : 'draft';
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            Database::execute('UPDATE okr_objectives SET status=?,approved_by=?,approved_at=?,updated_at=NOW() WHERE id=?', [$status,$decision === 'approved' ? $actorId : null,$decision === 'approved' ? date('Y-m-d H:i:s') : null,$objectiveId]);
            $pending = Database::fetch('SELECT id FROM okr_approvals WHERE objective_id=? AND decision="pending" ORDER BY id DESC LIMIT 1', [$objectiveId]);
            if ($pending) Database::execute('UPDATE okr_approvals SET approver_user_id=?,decision=?,note=?,decided_at=NOW() WHERE id=?', [$actorId,$decision,$note ?: null,(int)$pending['id']]);
            else Database::execute('INSERT INTO okr_approvals(objective_id,requested_by,approver_user_id,decision,note,decided_at,created_at) VALUES (?,?,?,?,?,NOW(),NOW())', [$objectiveId,(int)$objective['created_by'],$actorId,$decision,$note ?: null]);
            self::audit($objectiveId, null, $actorId, 'objective_'.$decision, ['status'=>$objective['status']], ['status'=>$status], $note ?: ($decision === 'approved' ? 'تأیید هدف' : 'بازگشت هدف'));
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
        Auth::log($actorId, 'okr_objective_'.$decision, 'okr', $objectiveId);
    }

    public static function addCheckin(int $objectiveId, int $keyResultId, array $input, array $file, int $actorId): int
    {
        $objective = self::objective($objectiveId);
        $kr = Database::fetch('SELECT * FROM okr_key_results WHERE id=? AND objective_id=?', [$keyResultId,$objectiveId]);
        if (!$objective || !$kr || !self::canCheckin($objective, $kr)) throw new DomainException('برای ثبت Check-in این نتیجه کلیدی دسترسی ندارید.');
        if (($kr['data_source_type'] ?? 'manual') === 'automatic') throw new InvalidArgumentException('مقدار این نتیجه کلیدی از منبع خودکار بروزرسانی می‌شود.');
        if (!in_array($objective['status'], ['active','at_risk','off_track','approved'], true)) throw new InvalidArgumentException('Check-in فقط برای هدف تأییدشده یا فعال قابل ثبت است.');
        $current = self::decimal($input['current_value'] ?? $kr['current_value'], -9999999999999999, 9999999999999999);
        $confidence = self::enum($input['confidence_level'] ?? '', self::CONFIDENCE_LEVELS, 'medium');
        $health = self::enum($input['health_status'] ?? '', self::HEALTH_STATUSES, 'on_track');
        $progress = self::progressPercent((float)$kr['baseline_value'], (float)$kr['target_value'], $current, (string)$kr['direction']);
        $blocker = self::text($input['blocker_text'] ?? '', 10000);
        $nextAction = self::text($input['next_action'] ?? '', 10000);
        $note = self::text($input['note'] ?? '', 10000);
        $prepared = self::prepareEvidence($file);
        $movedPath = null;
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            Database::execute('INSERT INTO okr_checkins(objective_id,key_result_id,current_value,progress_percent,confidence_level,health_status,blocker_text,next_action,note,created_by,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,NOW())', [$objectiveId,$keyResultId,$current,$progress,$confidence,$health,$blocker ?: null,$nextAction ?: null,$note ?: null,$actorId]);
            $checkinId = (int)Database::lastInsertId();
            Database::execute('UPDATE okr_key_results SET current_value=?,progress_percent=?,health_status=?,status=?,last_checkin_at=NOW(),last_calculated_at=NOW(),updated_at=NOW() WHERE id=?', [$current,$progress,$health,$health === 'completed' ? 'completed' : 'active',$keyResultId]);
            if ($prepared) {
                [$storedName,$movedPath] = self::moveEvidence($prepared);
                Database::execute('INSERT INTO okr_evidence(objective_id,key_result_id,checkin_id,original_name,stored_name,mime_type,file_size,uploaded_by,created_at) VALUES (?,?,?,?,?,?,?,?,NOW())', [$objectiveId,$keyResultId,$checkinId,$prepared['original_name'],$storedName,$prepared['mime_type'],$prepared['file_size'],$actorId]);
            }
            self::audit($objectiveId, $keyResultId, $actorId, 'checkin_created', ['current_value'=>$kr['current_value'],'progress_percent'=>$kr['progress_percent']], ['current_value'=>$current,'progress_percent'=>$progress,'health_status'=>$health], $blocker ? 'Check-in همراه با مانع' : 'ثبت Check-in');
            self::recalculateObjective($objectiveId, $actorId, 'checkin');
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ($movedPath && is_file($movedPath)) @unlink($movedPath);
            throw $e;
        }
        Auth::log($actorId, 'okr_checkin_created', 'okr', $objectiveId);
        return $checkinId;
    }

    public static function canCheckin(array $objective, array $kr, ?array $user = null): bool
    {
        if (($kr['data_source_type'] ?? 'manual') === 'automatic') return false;
        $user ??= Auth::user();
        if (!$user || !self::canViewObjective($objective, $user)) return false;
        if (OrgAccess::isAdmin($user) || (int)$kr['owner_user_id'] === (int)$user['id'] || (int)$objective['owner_user_id'] === (int)$user['id']) return true;
        if (Auth::can('okr.checkin', 'create') || Auth::can('okr.checkin', 'edit')) return in_array((int)$kr['owner_user_id'], self::accessibleOwnerIds($user), true);
        $code = self::normalizedRoleCode($user);
        return in_array($code, ['CEO','SALES_MANAGER','SALES_SUPERVISOR','IT_MANAGER','TECHNOLOGY_MANAGER'], true)
            && in_array((int)$kr['owner_user_id'], self::accessibleOwnerIds($user), true);
    }

    public static function addInitiative(int $objectiveId, array $input, int $actorId): int
    {
        $objective = self::objective($objectiveId);
        if (!$objective || !self::canManageObjective($objective)) throw new DomainException('برای افزودن اقدام به این هدف دسترسی ندارید.');
        $ownerId = (int)($input['owner_user_id'] ?? $objective['owner_user_id']);
        if (!Auth::isAdmin() && !in_array($ownerId, self::accessibleOwnerIds(Auth::user()), true)) throw new DomainException('مسئول اقدام خارج از محدوده شماست.');
        if (!in_array($ownerId, array_map('intval', array_column(self::availableOwners(), 'id')), true)) throw new DomainException('مسئول اقدام در گروه آزمایشی OKR یا دارای مجوز صریح نیست.');
        $krId = (int)($input['key_result_id'] ?? 0) ?: null;
        if ($krId && !Database::fetch('SELECT id FROM okr_key_results WHERE id=? AND objective_id=?', [$krId,$objectiveId])) throw new InvalidArgumentException('نتیجه کلیدی مرتبط معتبر نیست.');
        $title = self::text($input['title'] ?? '', 255);
        $description = self::text($input['description'] ?? '', 5000);
        $priority = self::enum($input['priority'] ?? '', self::PRIORITIES, 'normal');
        $start = self::date((string)($input['start_date'] ?? '')) ?: date('Y-m-d');
        $due = self::date((string)($input['due_date'] ?? ''));
        if ($title === '' || !$due || $start > $due) throw new InvalidArgumentException('عنوان و مهلت معتبر اقدام الزامی است.');
        $pdo = Database::connection();
        $startedHere = !$pdo->inTransaction();
        if ($startedHere) $pdo->beginTransaction();
        try {
            Database::execute('INSERT INTO okr_initiatives(objective_id,key_result_id,owner_user_id,title,description,priority,status,start_date,due_date,created_by,created_at,updated_at) VALUES (?,?,?,?,?,?,"open",?,?,?,NOW(),NOW())', [$objectiveId,$krId,$ownerId,$title,$description ?: null,$priority,$start,$due,$actorId]);
            $initiativeId = (int)Database::lastInsertId();
            $taskId = WorkPlannerService::createLinkedTask($actorId, $ownerId, ['title'=>$title,'description'=>$description,'priority'=>$priority,'start_date'=>$start,'due_date'=>$due], 'okr', $objectiveId);
            Database::execute('UPDATE okr_initiatives SET planner_task_id=? WHERE id=?', [$taskId,$initiativeId]);
            Database::execute('INSERT INTO okr_task_links(objective_id,key_result_id,initiative_id,planner_task_id,created_by,created_at) VALUES (?,?,?,?,?,NOW())', [$objectiveId,$krId,$initiativeId,$taskId,$actorId]);
            self::audit($objectiveId, $krId, $actorId, 'initiative_created', null, ['initiative_id'=>$initiativeId,'planner_task_id'=>$taskId,'owner_user_id'=>$ownerId,'title'=>$title], 'ایجاد اقدام و اتصال به پلنر');
            if ($startedHere) $pdo->commit();
        } catch (Throwable $e) {
            if ($startedHere && $pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
        try {
            require_once __DIR__ . '/../services/ActionHubService.php';
            ActionHubService::mirrorSourceRecord('okr_initiatives', $initiativeId);
        } catch (Throwable $e) {
            error_log('Action hub OKR mirror: ' . $e->getMessage());
        }
        return $initiativeId;
    }

    public static function listObjectives(array $filters = []): array
    {
        $user = Auth::user();
        if (!$user || !self::canView($user)) return [];
        $where = ['1=1'];
        $params = [];
        if (!OrgAccess::isAdmin($user)) {
            $ids = self::accessibleOwnerIds($user);
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $where[] = "(o.owner_user_id IN ({$ph}) OR o.created_by=?)";
            $params = array_merge($params, $ids, [(int)$user['id']]);
        }
        if ((int)($filters['cycle_id'] ?? 0) > 0) {$where[]='o.cycle_id=?';$params[]=(int)$filters['cycle_id'];}
        if (($filters['status'] ?? '') !== '' && array_key_exists($filters['status'], self::OBJECTIVE_STATUSES)) {$where[]='o.status=?';$params[]=$filters['status'];}
        if ((int)($filters['owner_user_id'] ?? 0) > 0) {$where[]='o.owner_user_id=?';$params[]=(int)$filters['owner_user_id'];}
        return Database::fetchAll('SELECT o.*,c.title cycle_title,u.name owner_name,ou.title org_unit_title,p.title parent_title,(SELECT COUNT(*) FROM okr_key_results kr WHERE kr.objective_id=o.id AND kr.status<>"cancelled") kr_count,(SELECT MAX(ci.created_at) FROM okr_checkins ci WHERE ci.objective_id=o.id) last_checkin_at FROM okr_objectives o JOIN okr_cycles c ON c.id=o.cycle_id JOIN users u ON u.id=o.owner_user_id LEFT JOIN org_units ou ON ou.id=o.org_unit_id LEFT JOIN okr_objectives p ON p.id=o.parent_objective_id WHERE '.implode(' AND ',$where).' ORDER BY CASE o.status WHEN "off_track" THEN 0 WHEN "at_risk" THEN 1 WHEN "pending_approval" THEN 2 WHEN "active" THEN 3 ELSE 4 END,o.due_date,o.id DESC LIMIT 500', $params);
    }

    public static function dashboard(array $filters = []): array
    {
        $rows = self::listObjectives($filters);
        $summary = ['total'=>count($rows),'active'=>0,'at_risk'=>0,'off_track'=>0,'pending'=>0,'completed'=>0,'without_checkin'=>0,'average_score'=>0.0];
        $score = 0.0;
        foreach ($rows as $row) {
            $status = (string)$row['status'];
            if ($status === 'active') $summary['active']++;
            if ($status === 'at_risk') $summary['at_risk']++;
            if ($status === 'off_track') $summary['off_track']++;
            if ($status === 'pending_approval') $summary['pending']++;
            if ($status === 'completed') $summary['completed']++;
            if (!$row['last_checkin_at'] && in_array($status, ['active','at_risk','off_track'], true)) $summary['without_checkin']++;
            $score += (float)$row['progress_score'];
        }
        $summary['average_score'] = $rows ? round($score / count($rows), 2) : 0.0;
        return ['objectives'=>$rows,'summary'=>$summary];
    }

    public static function detail(int $objectiveId): array
    {
        $objective = self::objective($objectiveId);
        if (!$objective) throw new DomainException('هدف یافت نشد یا در محدوده دسترسی شما نیست.');
        $krs = Database::fetchAll('SELECT kr.*,u.name owner_name,(SELECT COUNT(*) FROM okr_checkins ci WHERE ci.key_result_id=kr.id) checkin_count FROM okr_key_results kr JOIN users u ON u.id=kr.owner_user_id WHERE kr.objective_id=? ORDER BY kr.id', [$objectiveId]);
        $checkins = Database::fetchAll('SELECT ci.*,kr.title key_result_title,u.name creator_name FROM okr_checkins ci JOIN okr_key_results kr ON kr.id=ci.key_result_id JOIN users u ON u.id=ci.created_by WHERE ci.objective_id=? ORDER BY ci.id DESC LIMIT 100', [$objectiveId]);
        $initiatives = Database::fetchAll('SELECT i.*,u.name owner_name,kr.title key_result_title,t.status planner_status,t.progress_percent planner_progress FROM okr_initiatives i JOIN users u ON u.id=i.owner_user_id LEFT JOIN okr_key_results kr ON kr.id=i.key_result_id LEFT JOIN work_planner_tasks t ON t.id=i.planner_task_id WHERE i.objective_id=? ORDER BY i.id DESC', [$objectiveId]);
        $approvals = Database::fetchAll('SELECT a.*,r.name requester_name,p.name approver_name FROM okr_approvals a JOIN users r ON r.id=a.requested_by LEFT JOIN users p ON p.id=a.approver_user_id WHERE a.objective_id=? ORDER BY a.id DESC', [$objectiveId]);
        $evidence = Database::fetchAll('SELECT e.*,u.name uploader_name FROM okr_evidence e JOIN users u ON u.id=e.uploaded_by WHERE e.objective_id=? ORDER BY e.id DESC', [$objectiveId]);
        $scores = Database::fetchAll('SELECT * FROM okr_score_history WHERE objective_id=? ORDER BY id DESC LIMIT 30', [$objectiveId]);
        $audit = Database::fetchAll('SELECT l.*,u.name actor_name FROM okr_audit_logs l LEFT JOIN users u ON u.id=l.actor_user_id WHERE l.objective_id=? ORDER BY l.id DESC LIMIT 100', [$objectiveId]);
        $alignments = Database::fetchAll('SELECT a.*,p.title parent_title,p.progress_score parent_progress,p.status parent_status,u.name parent_owner_name FROM okr_alignments a JOIN okr_objectives p ON p.id=a.parent_objective_id JOIN users u ON u.id=p.owner_user_id WHERE a.child_objective_id=? AND a.active=1 ORDER BY a.id', [$objectiveId]);
        $alignedChildren = Database::fetchAll('SELECT a.*,c.title child_title,c.progress_score child_progress,c.status child_status,u.name child_owner_name FROM okr_alignments a JOIN okr_objectives c ON c.id=a.child_objective_id JOIN users u ON u.id=c.owner_user_id WHERE a.parent_objective_id=? AND a.active=1 ORDER BY a.id', [$objectiveId]);
        return compact('objective','krs','checkins','initiatives','approvals','evidence','scores','audit','alignments','alignedChildren');
    }

    private static function alignmentWouldCycle(int $childId, int $parentId): bool
    {
        $queue = [$parentId];
        $visited = [];
        for ($steps = 0; $queue && $steps < 200; $steps++) {
            $current = (int)array_shift($queue);
            if ($current === $childId) return true;
            if (isset($visited[$current])) continue;
            $visited[$current] = true;
            $row = Database::fetch('SELECT parent_objective_id FROM okr_objectives WHERE id=?', [$current]);
            $legacyParent = (int)($row['parent_objective_id'] ?? 0);
            if ($legacyParent > 0) $queue[] = $legacyParent;
            foreach (Database::fetchAll('SELECT parent_objective_id FROM okr_alignments WHERE child_objective_id=? AND active=1', [$current]) as $alignment) {
                $queue[] = (int)$alignment['parent_objective_id'];
            }
        }
        return false;
    }

    public static function evidence(int $evidenceId): ?array
    {
        $row = Database::fetch('SELECT e.*,o.owner_user_id,o.created_by FROM okr_evidence e JOIN okr_objectives o ON o.id=e.objective_id WHERE e.id=?', [$evidenceId]);
        return $row && self::canViewObjective($row) ? $row : null;
    }

    public static function progressPercent(float $baseline, float $target, float $current, string $direction): float
    {
        if ($baseline === $target) return $current === $target ? 100.0 : 0.0;
        $raw = $direction === 'decrease'
            ? (($baseline - $current) / ($baseline - $target)) * 100
            : (($current - $baseline) / ($target - $baseline)) * 100;
        return round(max(0, min(200, $raw)), 2);
    }

    public static function recalculateObjective(int $objectiveId, ?int $actorId = null, string $source = 'manual'): float
    {
        $row = Database::fetch('SELECT COALESCE(SUM(progress_percent*weight)/NULLIF(SUM(weight),0),0) score,MAX(health_status="off_track") has_off_track,MAX(health_status="at_risk") has_at_risk,MIN(health_status="completed") all_completed FROM okr_key_results WHERE objective_id=? AND status<>"cancelled"', [$objectiveId]) ?: [];
        $score = round((float)($row['score'] ?? 0), 2);
        $health = (int)($row['has_off_track'] ?? 0) ? 'off_track' : ((int)($row['has_at_risk'] ?? 0) ? 'at_risk' : ((int)($row['all_completed'] ?? 0) ? 'completed' : 'on_track'));
        $objective = Database::fetch('SELECT status,progress_score,health_status FROM okr_objectives WHERE id=?', [$objectiveId]);
        $status = (string)($objective['status'] ?? 'draft');
        if (in_array($status, ['active','at_risk','off_track'], true)) $status = $health === 'off_track' ? 'off_track' : ($health === 'at_risk' ? 'at_risk' : 'active');
        Database::execute('UPDATE okr_objectives SET progress_score=?,health_status=?,status=?,updated_at=NOW() WHERE id=?', [$score,$health,$status,$objectiveId]);
        Database::execute('INSERT INTO okr_score_history(objective_id,score_percent,health_status,source,recorded_by,recorded_at) VALUES (?,?,?,?,?,NOW())', [$objectiveId,$score,$health,self::text($source,30),$actorId]);
        return $score;
    }

    public static function scoreBand(float $score): array
    {
        if ($score < 40) return ['critical','بحرانی'];
        if ($score < 70) return ['action','نیازمند اقدام'];
        if ($score < 90) return ['track','در مسیر'];
        if ($score <= 100) return ['achieved','محقق‌شده'];
        return ['exceeded','فراتر از هدف'];
    }

    public static function audit(?int $objectiveId, ?int $keyResultId, ?int $actorId, string $action, mixed $oldValue, mixed $newValue, string $note = ''): void
    {
        Database::execute('INSERT INTO okr_audit_logs(objective_id,key_result_id,actor_user_id,action,old_value_json,new_value_json,note,created_at) VALUES (?,?,?,?,?,?,?,NOW())', [$objectiveId,$keyResultId,$actorId,self::text($action,60),self::json($oldValue),self::json($newValue),self::text($note,500) ?: null]);
    }

    private static function prepareEvidence(array $file): ?array
    {
        $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) return null;
        if ($error !== UPLOAD_ERR_OK || !is_uploaded_file((string)($file['tmp_name'] ?? ''))) throw new InvalidArgumentException('آپلود مدرک انجام نشد.');
        $size = (int)($file['size'] ?? 0);
        if ($size < 1 || $size > 5 * 1024 * 1024) throw new InvalidArgumentException('حجم مدرک باید حداکثر ۵ مگابایت باشد.');
        $original = basename((string)($file['name'] ?? 'evidence'));
        $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        $allowedExt = ['pdf','png','jpg','jpeg','webp','txt','csv','xlsx','docx'];
        if (!in_array($ext, $allowedExt, true)) throw new InvalidArgumentException('نوع فایل مدرک مجاز نیست.');
        $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : false;
        $mime = $finfo ? (string)finfo_file($finfo, (string)$file['tmp_name']) : (string)($file['type'] ?? 'application/octet-stream');
        if ($finfo) finfo_close($finfo);
        $allowedMime = ['application/pdf','image/png','image/jpeg','image/webp','text/plain','text/csv','application/csv','application/vnd.ms-excel','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','application/vnd.openxmlformats-officedocument.wordprocessingml.document','application/zip'];
        if (!in_array($mime, $allowedMime, true)) throw new InvalidArgumentException('محتوای فایل مدرک معتبر نیست.');
        return ['tmp_name'=>(string)$file['tmp_name'],'original_name'=>self::text($original,255),'extension'=>$ext,'mime_type'=>$mime,'file_size'=>$size];
    }

    private static function moveEvidence(array $prepared): array
    {
        $dir = dirname(__DIR__) . '/storage/okr-evidence';
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) throw new RuntimeException('Evidence storage directory is not writable.');
        $stored = bin2hex(random_bytes(20)) . '.' . $prepared['extension'];
        $path = $dir . '/' . $stored;
        if (!move_uploaded_file($prepared['tmp_name'], $path)) throw new RuntimeException('Evidence file could not be stored.');
        return [$stored, $path];
    }

    private static function text(mixed $value, int $max): string
    {
        return mb_substr(trim((string)$value), 0, $max);
    }

    private static function enum(mixed $value, array $allowed, string $default): string
    {
        $value = (string)$value;
        return array_key_exists($value, $allowed) ? $value : $default;
    }

    private static function decimal(mixed $value, float $min, float $max): float
    {
        $normalized = strtr(trim((string)$value), ['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9','٬'=>'',','=>'']);
        if ($normalized === '' || !is_numeric($normalized)) throw new InvalidArgumentException('مقدار عددی واردشده معتبر نیست.');
        return max($min, min($max, (float)$normalized));
    }

    private static function date(string $date): ?string
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        return $parsed && $parsed->format('Y-m-d') === $date ? $date : null;
    }

    private static function nullableDate(string $date): ?string
    {
        return trim($date) === '' ? null : self::date($date);
    }

    private static function json(mixed $value): ?string
    {
        if ($value === null) return null;
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $json === false ? null : $json;
    }
}
