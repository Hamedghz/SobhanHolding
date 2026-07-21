<?php

require_once __DIR__ . '/Database.php';

final class ActionHubModule
{
    public const PERMISSIONS = [
        'action_hub.view' => ['مشاهده مرکز اقدامات', 781],
        'action_hub.create_own' => ['ایجاد اقدام شخصی', 782],
        'action_hub.assign' => ['تخصیص اقدام به زیرمجموعه', 783],
        'action_hub.manage_templates' => ['مدیریت قالب‌های اقدام', 784],
        'action_hub.manage_types' => ['مدیریت انواع اقدام', 785],
        'action_hub.approve' => ['تأیید اقدامات نیازمند بررسی', 786],
    ];

    public static function repair(PDO $pdo): void
    {
        foreach (self::schema() as $sql) $pdo->exec($sql);
        self::seedPermissions($pdo);
        self::seedGeneralType($pdo);
        self::backfillLegacyTemplates($pdo);
        self::backfillLegacyActions($pdo);
        self::backfillUniversalSources($pdo);
    }

    public static function schema(): array
    {
        $engine = ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        return [
            "CREATE TABLE IF NOT EXISTS action_types (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                code VARCHAR(100) NOT NULL,
                title VARCHAR(190) NOT NULL,
                description TEXT NULL,
                color VARCHAR(20) NOT NULL DEFAULT '#2563eb',
                icon VARCHAR(80) NULL,
                active TINYINT(1) NOT NULL DEFAULT 1,
                requires_approval TINYINT(1) NOT NULL DEFAULT 0,
                required_fields_csv VARCHAR(500) NULL,
                sort_order INT NOT NULL DEFAULT 0,
                created_by INT UNSIGNED NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                UNIQUE KEY uq_action_types_code(code),
                INDEX idx_action_types_active(active,sort_order)
            ){$engine}",
            "CREATE TABLE IF NOT EXISTS action_templates (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                action_type_id INT UNSIGNED NOT NULL,
                template_code VARCHAR(100) NOT NULL,
                title VARCHAR(190) NOT NULL,
                description TEXT NULL,
                instructions TEXT NULL,
                active TINYINT(1) NOT NULL DEFAULT 1,
                legacy_source_type VARCHAR(60) NULL,
                legacy_source_id BIGINT UNSIGNED NULL,
                created_by INT UNSIGNED NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                UNIQUE KEY uq_action_templates_code(template_code),
                UNIQUE KEY uq_action_templates_legacy(legacy_source_type,legacy_source_id),
                INDEX idx_action_templates_type(action_type_id,active),
                CONSTRAINT fk_action_templates_type FOREIGN KEY(action_type_id) REFERENCES action_types(id) ON DELETE RESTRICT
            ){$engine}",
            "CREATE TABLE IF NOT EXISTS action_template_fields (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                template_id BIGINT UNSIGNED NOT NULL,
                field_key VARCHAR(100) NOT NULL,
                field_label VARCHAR(190) NOT NULL,
                field_type VARCHAR(50) NOT NULL,
                help_text TEXT NULL,
                placeholder VARCHAR(255) NULL,
                options_json LONGTEXT NULL,
                data_source VARCHAR(100) NULL,
                formula_expression TEXT NULL,
                default_value TEXT NULL,
                required TINYINT(1) NOT NULL DEFAULT 0,
                readonly TINYINT(1) NOT NULL DEFAULT 0,
                active TINYINT(1) NOT NULL DEFAULT 1,
                sort_order INT NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                UNIQUE KEY uq_action_template_field(template_id,field_key),
                INDEX idx_action_template_fields(template_id,active,sort_order),
                CONSTRAINT fk_action_template_fields_template FOREIGN KEY(template_id) REFERENCES action_templates(id) ON DELETE CASCADE
            ){$engine}",
            "CREATE TABLE IF NOT EXISTS actions (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(190) NOT NULL,
                description TEXT NULL,
                action_type_id INT UNSIGNED NOT NULL,
                template_id BIGINT UNSIGNED NULL,
                assigned_to INT UNSIGNED NOT NULL,
                assigned_by INT UNSIGNED NOT NULL,
                priority VARCHAR(20) NOT NULL DEFAULT 'normal',
                status VARCHAR(40) NOT NULL DEFAULT 'new',
                start_date DATE NULL,
                due_date DATE NULL,
                source_type VARCHAR(80) NOT NULL DEFAULT 'manual',
                source_id BIGINT UNSIGNED NULL,
                planner_task_id BIGINT UNSIGNED NULL,
                approval_required TINYINT(1) NOT NULL DEFAULT 0,
                approved_by INT UNSIGNED NULL,
                approved_at DATETIME NULL,
                legacy_source_type VARCHAR(60) NULL,
                legacy_source_id BIGINT UNSIGNED NULL,
                completed_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                UNIQUE KEY uq_actions_legacy(legacy_source_type,legacy_source_id),
                INDEX idx_actions_assigned(assigned_to,status,due_date),
                INDEX idx_actions_assigner(assigned_by,status,created_at),
                INDEX idx_actions_type(action_type_id,status),
                INDEX idx_actions_source(source_type,source_id),
                INDEX idx_actions_due(status,due_date),
                CONSTRAINT fk_actions_type FOREIGN KEY(action_type_id) REFERENCES action_types(id) ON DELETE RESTRICT,
                CONSTRAINT fk_actions_template FOREIGN KEY(template_id) REFERENCES action_templates(id) ON DELETE SET NULL,
                CONSTRAINT fk_actions_assigned_to FOREIGN KEY(assigned_to) REFERENCES users(id) ON DELETE RESTRICT,
                CONSTRAINT fk_actions_assigned_by FOREIGN KEY(assigned_by) REFERENCES users(id) ON DELETE RESTRICT
            ){$engine}",
            "CREATE TABLE IF NOT EXISTS action_field_values (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                action_id BIGINT UNSIGNED NOT NULL,
                field_id BIGINT UNSIGNED NULL,
                field_key VARCHAR(100) NOT NULL,
                field_label VARCHAR(190) NULL,
                field_type VARCHAR(50) NOT NULL,
                value_text LONGTEXT NULL,
                value_number DECIMAL(20,4) NULL,
                value_date DATE NULL,
                value_datetime DATETIME NULL,
                value_json LONGTEXT NULL,
                file_path VARCHAR(500) NULL,
                file_name VARCHAR(255) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                UNIQUE KEY uq_action_field_value(action_id,field_key),
                INDEX idx_action_field_values_action(action_id),
                CONSTRAINT fk_action_field_values_action FOREIGN KEY(action_id) REFERENCES actions(id) ON DELETE CASCADE,
                CONSTRAINT fk_action_field_values_field FOREIGN KEY(field_id) REFERENCES action_template_fields(id) ON DELETE SET NULL
            ){$engine}",
            "CREATE TABLE IF NOT EXISTS action_links (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                action_id BIGINT UNSIGNED NOT NULL,
                link_type VARCHAR(60) NOT NULL,
                linked_type VARCHAR(80) NULL,
                linked_id BIGINT UNSIGNED NULL,
                link_url VARCHAR(500) NULL,
                label VARCHAR(190) NULL,
                created_by INT UNSIGNED NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_action_link(action_id,link_type,linked_type,linked_id),
                INDEX idx_action_links_target(linked_type,linked_id),
                CONSTRAINT fk_action_links_action FOREIGN KEY(action_id) REFERENCES actions(id) ON DELETE CASCADE
            ){$engine}",
            "CREATE TABLE IF NOT EXISTS action_logs (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                action_id BIGINT UNSIGNED NOT NULL,
                action_key VARCHAR(60) NOT NULL,
                old_status VARCHAR(40) NULL,
                new_status VARCHAR(40) NULL,
                note TEXT NULL,
                old_value_json LONGTEXT NULL,
                new_value_json LONGTEXT NULL,
                performed_by INT UNSIGNED NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_action_logs_action(action_id,created_at),
                INDEX idx_action_logs_actor(performed_by,created_at),
                CONSTRAINT fk_action_logs_action FOREIGN KEY(action_id) REFERENCES actions(id) ON DELETE CASCADE
            ){$engine}",
        ];
    }

    private static function seedPermissions(PDO $pdo): void
    {
        if (!self::tableExists($pdo, 'modules')) return;
        $stmt = $pdo->prepare(
            'INSERT INTO modules(module_key,module_title,sort_order,status,created_at)
             VALUES (?,?,?,"active",NOW())
             ON DUPLICATE KEY UPDATE module_title=VALUES(module_title),sort_order=VALUES(sort_order),status="active"'
        );
        foreach (self::PERMISSIONS as $key => [$title,$sort]) $stmt->execute([$key,$title,$sort]);
    }

    private static function seedGeneralType(PDO $pdo): void
    {
        $pdo->exec(
            "INSERT INTO action_types(code,title,description,color,icon,active,requires_approval,required_fields_csv,sort_order,created_at,updated_at)
             VALUES ('general','عمومی','نوع پیش‌فرض برای اقدام‌های عمومی سازمان','#2563eb','check-circle',1,0,'title,assigned_to',10,NOW(),NOW())
             ON DUPLICATE KEY UPDATE title=VALUES(title),active=1,updated_at=NOW()"
        );
    }

    private static function backfillLegacyTemplates(PDO $pdo): void
    {
        if (!self::tableExists($pdo, 'sales_scripts')) return;
        $typeId = (int)$pdo->query("SELECT id FROM action_types WHERE code='general' LIMIT 1")->fetchColumn();
        $scripts = $pdo->query(
            "SELECT s.* FROM sales_scripts s
             LEFT JOIN action_templates t ON t.legacy_source_type='sales_script' AND t.legacy_source_id=s.id
             WHERE t.id IS NULL ORDER BY s.id"
        )->fetchAll(PDO::FETCH_ASSOC);
        $templateStmt = $pdo->prepare(
            'INSERT INTO action_templates(action_type_id,template_code,title,description,instructions,active,legacy_source_type,legacy_source_id,created_by,created_at,updated_at)
             VALUES (?,?,?,?,?,?, "sales_script",?,?,NOW(),NOW())
             ON DUPLICATE KEY UPDATE title=VALUES(title),description=VALUES(description),instructions=VALUES(instructions),active=VALUES(active),updated_at=NOW()'
        );
        $fieldStmt = $pdo->prepare(
            'INSERT INTO action_template_fields(template_id,field_key,field_label,field_type,options_json,default_value,required,readonly,active,sort_order,created_at,updated_at)
             VALUES (?,?,?,?,?,?,?,?,1,?,NOW(),NOW())
             ON DUPLICATE KEY UPDATE field_label=VALUES(field_label),field_type=VALUES(field_type),options_json=VALUES(options_json),
                default_value=VALUES(default_value),required=VALUES(required),readonly=VALUES(readonly),sort_order=VALUES(sort_order),active=1,updated_at=NOW()'
        );
        foreach ($scripts as $script) {
            $code = 'legacy_sales_' . (int)$script['id'] . '_' . preg_replace('/[^a-zA-Z0-9_-]+/', '_', (string)$script['script_code']);
            $templateStmt->execute([
                $typeId,$code,(string)$script['title'],null,(string)$script['script_body'],
                (int)$script['active'],(int)$script['id'],(int)($script['created_by'] ?? 0) ?: null,
            ]);
            $templateId = (int)$pdo->query(
                "SELECT id FROM action_templates WHERE legacy_source_type='sales_script' AND legacy_source_id=" . (int)$script['id']
            )->fetchColumn();
            if (!$templateId || !self::tableExists($pdo, 'sales_script_fields')) continue;
            $fieldQuery = $pdo->prepare('SELECT * FROM sales_script_fields WHERE script_id=? ORDER BY sort_order,id');
            $fieldQuery->execute([(int)$script['id']]);
            foreach ($fieldQuery->fetchAll(PDO::FETCH_ASSOC) as $field) {
                $fieldStmt->execute([
                    $templateId,(string)$field['field_key'],(string)$field['field_label'],
                    self::legacyFieldType((string)$field['field_type']),(string)($field['options_json'] ?? '') ?: null,
                    $field['default_value'] ?? null,(int)$field['required'],0,(int)$field['sort_order'],
                ]);
            }
        }
    }

    private static function backfillLegacyActions(PDO $pdo): void
    {
        $typeId = (int)$pdo->query("SELECT id FROM action_types WHERE code='general' LIMIT 1")->fetchColumn();
        if (self::tableExists($pdo, 'sales_actions')) {
            $rows = $pdo->query(
                "SELECT legacy.* FROM sales_actions legacy
                 LEFT JOIN actions a ON a.legacy_source_type='sales_actions' AND a.legacy_source_id=legacy.id
                 WHERE a.id IS NULL ORDER BY legacy.id"
            )->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                self::insertLegacyAction($pdo, $typeId, 'sales_actions', $row, (int)($row['assigned_to'] ?: $row['sales_manager_id']), (int)($row['created_by'] ?: $row['sales_manager_id']));
            }
        }
        if (self::tableExists($pdo, 'supervisor_actions')) {
            $rows = $pdo->query(
                "SELECT legacy.* FROM supervisor_actions legacy
                 LEFT JOIN actions a ON a.legacy_source_type='supervisor_actions' AND a.legacy_source_id=legacy.id
                 WHERE a.id IS NULL ORDER BY legacy.id"
            )->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                self::insertLegacyAction($pdo, $typeId, 'supervisor_actions', $row, (int)$row['supervisor_id'], (int)($row['created_by'] ?: $row['supervisor_id']));
            }
        }
    }

    private static function backfillUniversalSources(PDO $pdo): void
    {
        $typeId = (int)$pdo->query("SELECT id FROM action_types WHERE code='general' LIMIT 1")->fetchColumn();
        if (!$typeId) return;
        if (self::tableExists($pdo, 'work_planner_tasks') && self::sourceNeedsBackfill($pdo, 'work_planner_tasks', 'work_planner_tasks')) {
            $pdo->exec(
                "INSERT IGNORE INTO actions(title,description,action_type_id,assigned_to,assigned_by,priority,status,start_date,due_date,
                    source_type,source_id,planner_task_id,approval_required,legacy_source_type,legacy_source_id,completed_at,created_at,updated_at)
                 SELECT LEFT(t.title,190),t.description,{$typeId},assignee.id,COALESCE(actor.id,assignee.id),
                    IF(t.priority IN ('low','normal','high','urgent'),t.priority,'normal'),
                    CASE t.status WHEN 'in_progress' THEN 'in_progress' WHEN 'waiting' THEN 'paused' WHEN 'blocked' THEN 'paused'
                        WHEN 'done' THEN 'done' WHEN 'cancelled' THEN 'cancelled' WHEN 'overdue' THEN 'overdue' ELSE 'new' END,
                    t.start_date,t.due_date,'planner',t.id,t.id,0,'work_planner_tasks',t.id,t.completed_at,
                    COALESCE(t.created_at,NOW()),t.updated_at
                 FROM work_planner_tasks t
                 JOIN users assignee ON assignee.id=t.user_id
                 LEFT JOIN users actor ON actor.id=t.assigned_by
                 LEFT JOIN actions existing ON existing.legacy_source_type='work_planner_tasks' AND existing.legacy_source_id=t.id
                 WHERE existing.id IS NULL AND COALESCE(t.related_module,'')<>'action_hub'"
            );
        }
        if (self::tableExists($pdo, 'personal_planner_tasks') && self::sourceNeedsBackfill($pdo, 'personal_planner_tasks', 'personal_planner_tasks')) {
            $pdo->exec(
                "INSERT IGNORE INTO actions(title,description,action_type_id,assigned_to,assigned_by,priority,status,start_date,due_date,
                    source_type,source_id,approval_required,legacy_source_type,legacy_source_id,completed_at,created_at,updated_at)
                 SELECT LEFT(t.title,190),t.description,{$typeId},u.id,u.id,
                    IF(t.priority IN ('low','normal','high','urgent'),t.priority,'normal'),
                    CASE t.status WHEN 'in_progress' THEN 'in_progress' WHEN 'waiting' THEN 'paused' WHEN 'done' THEN 'done'
                        WHEN 'cancelled' THEN 'cancelled' WHEN 'overdue' THEN 'overdue' ELSE 'new' END,
                    t.task_date,DATE(COALESCE(t.due_at,t.task_date)),'planner',t.id,0,'personal_planner_tasks',t.id,
                    IF(t.status='done',t.updated_at,NULL),COALESCE(t.created_at,NOW()),t.updated_at
                 FROM personal_planner_tasks t JOIN users u ON u.id=t.user_id
                 LEFT JOIN actions existing ON existing.legacy_source_type='personal_planner_tasks' AND existing.legacy_source_id=t.id
                 WHERE existing.id IS NULL"
            );
        }
        if (self::tableExists($pdo, 'management_decisions') && self::sourceNeedsBackfill($pdo, 'management_decisions', 'management_decisions')) {
            $pdo->exec(
                "INSERT IGNORE INTO actions(title,description,action_type_id,assigned_to,assigned_by,priority,status,due_date,
                    source_type,source_id,approval_required,legacy_source_type,legacy_source_id,completed_at,created_at,updated_at)
                 SELECT LEFT(d.title,190),d.description,{$typeId},responsible.id,COALESCE(actor.id,responsible.id),
                    IF(d.priority IN ('low','normal','high','urgent'),d.priority,'normal'),
                    CASE d.followup_status WHEN 'in_progress' THEN 'in_progress' WHEN 'waiting' THEN 'paused'
                        WHEN 'needs_revision' THEN 'needs_review' WHEN 'done' THEN 'needs_review' WHEN 'verified' THEN 'done'
                        WHEN 'cancelled' THEN 'cancelled' WHEN 'overdue' THEN 'overdue' ELSE 'new' END,
                    d.due_date,'meeting_decision',d.id,IF(d.supervisor_user_id IS NULL,0,1),'management_decisions',d.id,
                    IF(d.followup_status='verified',COALESCE(d.closed_at,d.updated_at),NULL),COALESCE(d.created_at,NOW()),d.updated_at
                 FROM management_decisions d JOIN users responsible ON responsible.id=d.responsible_user_id
                 LEFT JOIN users actor ON actor.id=d.created_by
                 LEFT JOIN actions existing ON existing.legacy_source_type='management_decisions' AND existing.legacy_source_id=d.id
                 WHERE existing.id IS NULL"
            );
        }
        if (self::tableExists($pdo, 'okr_initiatives') && self::sourceNeedsBackfill($pdo, 'okr_initiatives', 'okr_initiatives')) {
            $pdo->exec(
                "INSERT IGNORE INTO actions(title,description,action_type_id,assigned_to,assigned_by,priority,status,start_date,due_date,
                    source_type,source_id,planner_task_id,approval_required,legacy_source_type,legacy_source_id,completed_at,created_at,updated_at)
                 SELECT LEFT(i.title,190),i.description,{$typeId},owner_user.id,COALESCE(actor.id,owner_user.id),
                    IF(i.priority IN ('low','normal','high','urgent'),i.priority,'normal'),
                    CASE i.status WHEN 'in_progress' THEN 'in_progress' WHEN 'paused' THEN 'paused' WHEN 'done' THEN 'done'
                        WHEN 'cancelled' THEN 'cancelled' WHEN 'overdue' THEN 'overdue' ELSE 'new' END,
                    i.start_date,i.due_date,'okr',i.id,i.planner_task_id,0,'okr_initiatives',i.id,
                    IF(i.status='done',i.updated_at,NULL),COALESCE(i.created_at,NOW()),i.updated_at
                 FROM okr_initiatives i JOIN users owner_user ON owner_user.id=i.owner_user_id
                 LEFT JOIN users actor ON actor.id=i.created_by
                 LEFT JOIN actions existing ON existing.legacy_source_type='okr_initiatives' AND existing.legacy_source_id=i.id
                 WHERE existing.id IS NULL"
            );
        }
    }

    private static function insertLegacyAction(PDO $pdo, int $typeId, string $legacyType, array $row, int $assignedTo, int $assignedBy): void
    {
        if ($assignedTo < 1 || $assignedBy < 1) return;
        $userStmt = $pdo->prepare('SELECT id FROM users WHERE id IN (?,?)');
        $userStmt->execute([$assignedTo,$assignedBy]);
        $existingUserIds = array_map('intval', $userStmt->fetchAll(PDO::FETCH_COLUMN));
        if (!in_array($assignedTo, $existingUserIds, true)) return;
        if (!in_array($assignedBy, $existingUserIds, true)) $assignedBy = $assignedTo;
        $sourceType = trim((string)($row['source_type'] ?? '')) ?: $legacyType;
        $sourceId = (int)($row['source_id'] ?? 0) ?: (int)$row['id'];
        $status = self::legacyStatus((string)($row['status'] ?? 'open'));
        $stmt = $pdo->prepare(
            'INSERT INTO actions(title,description,action_type_id,assigned_to,assigned_by,priority,status,start_date,due_date,
                source_type,source_id,planner_task_id,approval_required,legacy_source_type,legacy_source_id,completed_at,created_at,updated_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,0,?,?,?,COALESCE(?,NOW()),?)
             ON DUPLICATE KEY UPDATE title=VALUES(title),description=VALUES(description),assigned_to=VALUES(assigned_to),
                priority=VALUES(priority),status=VALUES(status),due_date=VALUES(due_date),planner_task_id=VALUES(planner_task_id),updated_at=VALUES(updated_at)'
        );
        $stmt->execute([
            (string)$row['title'],$row['description'] ?? null,$typeId,$assignedTo,$assignedBy,
            self::priority((string)($row['priority'] ?? 'normal')),$status,null,$row['due_date'] ?? null,
            $sourceType,$sourceId,(int)($row['planner_task_id'] ?? 0) ?: null,$legacyType,(int)$row['id'],
            $row['completed_at'] ?? null,$row['created_at'] ?? null,$row['updated_at'] ?? null,
        ]);
    }

    private static function legacyFieldType(string $type): string
    {
        return [
            'text'=>'short_text','textarea'=>'long_text','number'=>'number','money'=>'money','percent'=>'percentage',
            'date'=>'jalali_date','datetime'=>'datetime','time'=>'time','select'=>'single_select','multi_select'=>'multi_select',
            'checkbox'=>'checkbox','boolean'=>'yes_no','user'=>'user','visitor_select'=>'user','customer_select'=>'short_text',
            'file'=>'file','url'=>'url','link'=>'url',
        ][$type] ?? 'short_text';
    }

    private static function legacyStatus(string $status): string
    {
        return [
            'open'=>'new','draft'=>'new','in_progress'=>'in_progress','paused'=>'paused',
            'needs_manager_review'=>'needs_review','done'=>'done','completed'=>'done',
            'cancelled'=>'cancelled','overdue'=>'overdue',
        ][$status] ?? 'new';
    }

    private static function priority(string $priority): string
    {
        return in_array($priority, ['low','normal','high','urgent'], true) ? $priority : 'normal';
    }

    private static function tableExists(PDO $pdo, string $table): bool
    {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
        $stmt->execute([$table]);
        return (int)$stmt->fetchColumn() > 0;
    }

    private static function sourceNeedsBackfill(PDO $pdo, string $table, string $legacyType): bool
    {
        if (!preg_match('/^[a-z0-9_]+$/i', $table)) return false;
        $sourceMax = (int)$pdo->query("SELECT COALESCE(MAX(id),0) FROM `{$table}`")->fetchColumn();
        if ($sourceMax < 1) return false;
        $stmt = $pdo->prepare('SELECT COALESCE(MAX(legacy_source_id),0) FROM actions WHERE legacy_source_type=?');
        $stmt->execute([$legacyType]);
        return $sourceMax > (int)$stmt->fetchColumn();
    }
}
