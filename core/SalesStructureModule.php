<?php

require_once __DIR__ . '/Database.php';

class SalesStructureModule
{
    public static function repair(PDO $pdo): void
    {
        foreach (self::schema() as $sql) {
            $pdo->exec($sql);
        }

        self::seedDefaults($pdo);
        $stmt = $pdo->prepare('INSERT INTO modules(module_key,module_title,sort_order,status,created_at) VALUES (?,?,?,"active",NOW()) ON DUPLICATE KEY UPDATE module_title=VALUES(module_title),status="active"');
        $stmt->execute(['sales_structure', 'ساختار فروش، لاین و مناطق', 711]);
    }

    public static function schema(): array
    {
        $engine = ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        return [
            "CREATE TABLE IF NOT EXISTS sales_lines (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                code VARCHAR(40) NOT NULL,
                title VARCHAR(190) NOT NULL,
                manager_user_id INT UNSIGNED NULL,
                supervisor_user_id INT UNSIGNED NULL,
                active TINYINT(1) NOT NULL DEFAULT 1,
                sort_order INT NOT NULL DEFAULT 0,
                description TEXT NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_sales_lines_code (code),
                INDEX idx_sales_lines_manager (manager_user_id),
                INDEX idx_sales_lines_supervisor (supervisor_user_id),
                INDEX idx_sales_lines_active (active),
                CONSTRAINT fk_sales_lines_manager FOREIGN KEY (manager_user_id) REFERENCES users(id) ON DELETE SET NULL,
                CONSTRAINT fk_sales_lines_supervisor FOREIGN KEY (supervisor_user_id) REFERENCES users(id) ON DELETE SET NULL
            ){$engine}",
            "CREATE TABLE IF NOT EXISTS sales_line_brands (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                line_id INT UNSIGNED NOT NULL,
                brand_code VARCHAR(80) NULL,
                brand_name VARCHAR(190) NOT NULL,
                active TINYINT(1) NOT NULL DEFAULT 1,
                sort_order INT NOT NULL DEFAULT 0,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_sales_line_brand_name (line_id, brand_name),
                INDEX idx_sales_line_brands_line (line_id),
                INDEX idx_sales_line_brands_active (active),
                CONSTRAINT fk_sales_line_brands_line FOREIGN KEY (line_id) REFERENCES sales_lines(id) ON DELETE CASCADE
            ){$engine}",
            "CREATE TABLE IF NOT EXISTS sales_geographies (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                parent_id INT UNSIGNED NULL,
                type ENUM('city','region') NOT NULL DEFAULT 'city',
                code VARCHAR(80) NOT NULL,
                title VARCHAR(190) NOT NULL,
                active TINYINT(1) NOT NULL DEFAULT 1,
                sort_order INT NOT NULL DEFAULT 0,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_sales_geographies_code (code),
                INDEX idx_sales_geographies_parent (parent_id),
                INDEX idx_sales_geographies_type (type),
                INDEX idx_sales_geographies_active (active),
                CONSTRAINT fk_sales_geographies_parent FOREIGN KEY (parent_id) REFERENCES sales_geographies(id) ON DELETE SET NULL
            ){$engine}",
            "CREATE TABLE IF NOT EXISTS sales_visitor_territories (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                visitor_user_id INT UNSIGNED NOT NULL,
                line_id INT UNSIGNED NOT NULL,
                geography_id INT UNSIGNED NOT NULL,
                is_primary TINYINT(1) NOT NULL DEFAULT 0,
                active TINYINT(1) NOT NULL DEFAULT 1,
                notes TEXT NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_sales_visitor_geography (visitor_user_id, geography_id),
                INDEX idx_sales_visitor_territories_visitor (visitor_user_id),
                INDEX idx_sales_visitor_territories_line (line_id),
                INDEX idx_sales_visitor_territories_geo (geography_id),
                INDEX idx_sales_visitor_territories_active (active),
                CONSTRAINT fk_sales_visitor_territories_visitor FOREIGN KEY (visitor_user_id) REFERENCES users(id) ON DELETE CASCADE,
                CONSTRAINT fk_sales_visitor_territories_line FOREIGN KEY (line_id) REFERENCES sales_lines(id) ON DELETE CASCADE,
                CONSTRAINT fk_sales_visitor_territories_geo FOREIGN KEY (geography_id) REFERENCES sales_geographies(id) ON DELETE CASCADE
            ){$engine}",
            "CREATE TABLE IF NOT EXISTS sales_structure_audit_logs (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                action VARCHAR(80) NOT NULL,
                entity_type VARCHAR(80) NOT NULL,
                entity_id INT UNSIGNED NULL,
                performed_by INT UNSIGNED NULL,
                payload_json LONGTEXT NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_sales_structure_logs_entity (entity_type, entity_id),
                INDEX idx_sales_structure_logs_actor (performed_by),
                INDEX idx_sales_structure_logs_created (created_at)
            ){$engine}",
        ];
    }

    public static function seedDefaults(PDO $pdo): void
    {
        $lineStmt = $pdo->prepare('INSERT IGNORE INTO sales_lines(code,title,sort_order,active,created_at,updated_at) VALUES (?,?,?,?,NOW(),NOW())');
        foreach ([
            ['A', 'لاین A', 10, 1],
            ['B', 'لاین B', 20, 1],
            ['C', 'لاین C', 30, 1],
            ['D', 'لاین D', 40, 1],
        ] as $line) {
            $lineStmt->execute($line);
        }

        $geoStmt = $pdo->prepare('INSERT IGNORE INTO sales_geographies(parent_id,type,code,title,sort_order,active,created_at,updated_at) VALUES (?,?,?,?,?,1,NOW(),NOW())');
        foreach ([
            [null, 'city', 'ZABOL', 'زابل', 10],
            [null, 'city', 'ZAHEDAN', 'زاهدان', 20],
            [null, 'city', 'KHASH', 'خاش', 30],
            [null, 'city', 'SARAVAN', 'سراوان', 40],
            [null, 'city', 'IRANSHAHR', 'ایرانشهر', 50],
            [null, 'city', 'NIKSHAHR', 'نیکشهر', 60],
            [null, 'city', 'KONARAK', 'کنارک', 70],
            [null, 'city', 'CHABAHAR', 'چابهار', 80],
        ] as $geo) {
            $geoStmt->execute($geo);
        }

        $zahedanId = (int)($pdo->query("SELECT id FROM sales_geographies WHERE code='ZAHEDAN' LIMIT 1")->fetchColumn() ?: 0);
        if ($zahedanId > 0) {
            foreach ([
                [$zahedanId, 'region', 'ZAHEDAN_R1', 'زاهدان منطقه ۱', 21],
                [$zahedanId, 'region', 'ZAHEDAN_R2', 'زاهدان منطقه ۲', 22],
                [$zahedanId, 'region', 'ZAHEDAN_R3', 'زاهدان منطقه ۳', 23],
            ] as $region) {
                $geoStmt->execute($region);
            }
        }
    }

    public static function managers(): array
    {
        return Database::fetchAll(
            "SELECT u.id,u.name,u.sales_line FROM users u
             LEFT JOIN org_roles r ON r.id=u.org_role_id
             WHERE u.status='active' AND (r.code='SALES_MANAGER' OR u.role_key='SALES_MANAGER')
             ORDER BY u.display_order,u.name"
        );
    }

    public static function supervisors(): array
    {
        return Database::fetchAll(
            "SELECT u.id,u.name,u.sales_line,u.parent_user_id FROM users u
             LEFT JOIN org_roles r ON r.id=u.org_role_id
             WHERE u.status='active' AND (r.code='SALES_SUPERVISOR' OR u.role_key='SALES_SUPERVISOR')
             ORDER BY u.display_order,u.name"
        );
    }

    public static function visitors(): array
    {
        return Database::fetchAll(
            "SELECT u.id,u.name,u.sales_line,u.parent_user_id,u.supervisor_id,u.organization_manager_id FROM users u
             LEFT JOIN org_roles r ON r.id=u.org_role_id
             WHERE u.status='active' AND (r.code='VISITOR' OR u.role_key='VISITOR')
             ORDER BY u.display_order,u.name"
        );
    }

    public static function lines(): array
    {
        return Database::fetchAll("SELECT sl.*,mu.name manager_name,su.name supervisor_name,(SELECT COUNT(*) FROM sales_line_brands b WHERE b.line_id=sl.id AND b.active=1) brand_count,(SELECT COUNT(DISTINCT vt.visitor_user_id) FROM sales_visitor_territories vt WHERE vt.line_id=sl.id AND vt.active=1) visitor_count,(SELECT COUNT(DISTINCT vt.geography_id) FROM sales_visitor_territories vt WHERE vt.line_id=sl.id AND vt.active=1) territory_count FROM sales_lines sl LEFT JOIN users mu ON mu.id=sl.manager_user_id LEFT JOIN users su ON su.id=sl.supervisor_user_id ORDER BY sl.sort_order,sl.code");
    }

    public static function geographies(): array
    {
        return Database::fetchAll('SELECT g.*,p.title parent_title FROM sales_geographies g LEFT JOIN sales_geographies p ON p.id=g.parent_id ORDER BY COALESCE(p.sort_order,g.sort_order),g.parent_id IS NOT NULL,g.sort_order,g.title');
    }

    public static function lineBrands(?int $lineId = null): array
    {
        $sql='SELECT b.*,sl.title line_title,sl.code line_code FROM sales_line_brands b JOIN sales_lines sl ON sl.id=b.line_id';
        return Database::fetchAll($sql.($lineId?' WHERE b.line_id=?':'').' ORDER BY sl.sort_order,b.sort_order,b.brand_name',$lineId?[$lineId]:[]);
    }

    public static function visitorTerritories(?int $visitorId = null): array
    {
        $sql="SELECT vt.*,u.name visitor_name,sl.code line_code,sl.title line_title,g.title geography_title,g.type geography_type,p.title parent_title FROM sales_visitor_territories vt JOIN users u ON u.id=vt.visitor_user_id JOIN sales_lines sl ON sl.id=vt.line_id JOIN sales_geographies g ON g.id=vt.geography_id LEFT JOIN sales_geographies p ON p.id=g.parent_id WHERE vt.active=1";
        return Database::fetchAll($sql.($visitorId?' AND vt.visitor_user_id=?':'').' ORDER BY sl.sort_order,u.name,vt.is_primary DESC,g.sort_order',$visitorId?[$visitorId]:[]);
    }

    public static function visitorsMissingTerritory(): array
    {
        return Database::fetchAll("SELECT u.id,u.name,u.sales_line FROM users u LEFT JOIN org_roles r ON r.id=u.org_role_id WHERE u.status='active' AND (r.code='VISITOR' OR u.role_key='VISITOR') AND NOT EXISTS(SELECT 1 FROM sales_visitor_territories vt WHERE vt.visitor_user_id=u.id AND vt.active=1) ORDER BY u.name");
    }

    public static function visitorsMissingZahedanRegion(): array
    {
        return Database::fetchAll("SELECT u.id,u.name,u.sales_line FROM users u LEFT JOIN org_roles r ON r.id=u.org_role_id WHERE u.status='active' AND (r.code='VISITOR' OR u.role_key='VISITOR') AND NOT EXISTS(SELECT 1 FROM sales_visitor_territories vt JOIN sales_geographies g ON g.id=vt.geography_id JOIN sales_geographies p ON p.id=g.parent_id WHERE vt.visitor_user_id=u.id AND vt.active=1 AND g.active=1 AND g.type='region' AND p.code='ZAHEDAN') ORDER BY u.name");
    }

    public static function validateSupervisorLineUniqueness(int $supervisorId, ?int $lineId = null): bool
    {
        return !Database::fetch('SELECT id FROM sales_lines WHERE supervisor_user_id=? AND id<>? AND active=1 LIMIT 1',[$supervisorId,$lineId?:0]);
    }

    public static function syncSupervisorCompatibilityFields(int $supervisorId, int $lineId, ?int $actorId = null): void
    {
        $line=Database::fetch('SELECT code,manager_user_id FROM sales_lines WHERE id=?',[$lineId]);if(!$line)return;
        Database::execute('UPDATE users SET sales_line=?,parent_user_id=?,organization_manager_id=?,updated_at=NOW() WHERE id=?',[$line['code'],$line['manager_user_id'],$line['manager_user_id'],$supervisorId]);
        if(!empty($line['manager_user_id']))Database::execute('INSERT IGNORE INTO manager_employees(manager_id,employee_id,assigned_by,created_at) VALUES (?,?,?,NOW())',[(int)$line['manager_user_id'],$supervisorId,$actorId]);
    }

    public static function syncUserCompatibilityFields(int $visitorId, int $lineId, ?int $actorId = null): void
    {
        $line=Database::fetch('SELECT code,manager_user_id,supervisor_user_id FROM sales_lines WHERE id=?',[$lineId]);if(!$line)return;
        Database::execute('UPDATE users SET sales_line=?,supervisor_id=?,parent_user_id=?,organization_manager_id=?,updated_at=NOW() WHERE id=?',[$line['code'],$line['supervisor_user_id'],$line['supervisor_user_id'],$line['manager_user_id'],$visitorId]);
        if(!empty($line['supervisor_user_id']))Database::execute('INSERT IGNORE INTO manager_employees(manager_id,employee_id,assigned_by,created_at) VALUES (?,?,?,NOW())',[(int)$line['supervisor_user_id'],$visitorId,$actorId]);
    }

    public static function diagnostics(): array
    {
        return ['lines_without_manager'=>Database::fetchAll('SELECT id,code,title FROM sales_lines WHERE active=1 AND manager_user_id IS NULL ORDER BY sort_order,code'),'lines_without_supervisor'=>Database::fetchAll('SELECT id,code,title FROM sales_lines WHERE active=1 AND supervisor_user_id IS NULL ORDER BY sort_order,code'),'duplicate_supervisors'=>Database::fetchAll('SELECT supervisor_user_id,COUNT(*) line_count,GROUP_CONCAT(code ORDER BY code SEPARATOR ", ") line_codes FROM sales_lines WHERE active=1 AND supervisor_user_id IS NOT NULL GROUP BY supervisor_user_id HAVING COUNT(*)>1'),'visitors_without_territory'=>self::visitorsMissingTerritory(),'visitors_without_zahedan'=>self::visitorsMissingZahedanRegion(),'multiple_primary'=>Database::fetchAll('SELECT vt.visitor_user_id,vt.line_id,u.name visitor_name,sl.code line_code,COUNT(*) primary_count FROM sales_visitor_territories vt JOIN users u ON u.id=vt.visitor_user_id JOIN sales_lines sl ON sl.id=vt.line_id WHERE vt.active=1 AND vt.is_primary=1 GROUP BY vt.visitor_user_id,vt.line_id,u.name,sl.code HAVING COUNT(*)>1')];
    }

    public static function log(string $action, string $entityType, ?int $entityId, ?int $performedBy, array $payload = []): void
    {
        try {
            Database::execute(
                'INSERT INTO sales_structure_audit_logs(action,entity_type,entity_id,performed_by,payload_json,created_at) VALUES (?,?,?,?,?,NOW())',
                [$action, $entityType, $entityId, $performedBy, json_encode($payload, JSON_UNESCAPED_UNICODE)]
            );
        } catch (Throwable $e) {
            error_log('sales structure audit: ' . $e->getMessage());
        }
    }
}
