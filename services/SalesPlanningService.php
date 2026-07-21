<?php

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/SalesDataNormalizer.php';
require_once __DIR__ . '/../lib/OrgAccess.php';

final class SalesPlanningService
{
    public const PRIORITIES = [
        'P1' => 'اولویت خیلی بالا',
        'P2' => 'اولویت بالا',
        'P3' => 'اولویت عادی',
        'P4' => 'اولویت پایین',
    ];

    public static function normalizePriorityCode(mixed $value): ?string
    {
        $value = strtoupper(SalesDataNormalizer::normalizePersianArabicDigits($value));
        $value = str_replace([' ', '-', '_'], '', $value);
        $aliases = [
            '1'=>'P1','A'=>'P1','P1'=>'P1','خیلیبالا'=>'P1','فوری'=>'P1','بحرانی'=>'P1',
            '2'=>'P2','B'=>'P2','P2'=>'P2','بالا'=>'P2','زیاد'=>'P2',
            '3'=>'P3','C'=>'P3','P3'=>'P3','عادی'=>'P3','متوسط'=>'P3',
            '4'=>'P4','D'=>'P4','P4'=>'P4','پایین'=>'P4','کم'=>'P4',
        ];
        return $aliases[$value] ?? null;
    }

    public static function normalizeStatus(mixed $value): ?string
    {
        $value = SalesDataNormalizer::normalizeHeader($value);
        if ($value === '') return 'active';
        if (in_array($value, ['active','فعال','1','yes','true'], true)) return 'active';
        if (in_array($value, ['inactive','غیرفعال','غيرفعال','0','no','false'], true)) return 'inactive';
        return null;
    }

    public static function normalizeGuildName(mixed $value): string
    {
        return SalesDataNormalizer::normalizeHeader($value);
    }

    public static function period(int $periodId): array
    {
        $period = Database::fetch(
            'SELECT id,period_key,title,period_type,start_date,end_date,jalali_year,jalali_month
             FROM system_periods WHERE id=? AND is_active=1 LIMIT 1',
            [$periodId]
        );
        if (!$period || !$period['start_date'] || !$period['end_date']) {
            throw new InvalidArgumentException('دوره فعال و دارای بازه معتبر انتخاب نشده است.');
        }
        return $period;
    }

    public static function resolveLine(string $code): array
    {
        $code = strtoupper(self::requiredText($code, 40, 'کد لاین الزامی است.'));
        $line = Database::fetch(
            'SELECT id,code,title,manager_user_id,supervisor_user_id FROM sales_lines WHERE code=? AND active=1 LIMIT 1',
            [$code]
        );
        if (!$line) throw new InvalidArgumentException('کد لاین در ساختار مرکزی فروش پیدا نشد.');
        return $line;
    }

    public static function resolveVisitor(string $code): array
    {
        $code = self::requiredText($code, 100, 'کد فروشنده الزامی است.');
        $visitors = Database::fetchAll(
            "SELECT u.id,u.name,u.employee_no,u.kara_system_code,u.sales_line_id,u.sales_line,
                    COALESCE(r.code,u.role_key) role_code
             FROM users u
             LEFT JOIN org_roles r ON r.id=u.org_role_id
             WHERE u.status='active' AND (u.employee_no=? OR u.kara_system_code=?)
             ORDER BY u.id LIMIT 2",
            [$code, $code]
        );
        if (count($visitors) > 1) {
            throw new InvalidArgumentException('کد فروشنده بین کد پرسنلی و کد کارا مبهم است؛ ابتدا شناسه کاربران اصلاح شود.');
        }
        $visitor = $visitors[0] ?? null;
        if (!$visitor || ($visitor['role_code'] ?? '') !== 'VISITOR') {
            throw new InvalidArgumentException('کد فروشنده باید به یک ویزیتور فعال در ساختار مرکزی کاربران متصل باشد.');
        }
        return $visitor;
    }

    public static function validateTargetContext(
        int $periodId,
        string $visitorCode,
        string $lineCode,
        string $productCode,
        ?array $actor = null
    ): array {
        $period = self::period($periodId);
        $line = self::resolveLine($lineCode);
        $visitor = self::resolveVisitor($visitorCode);
        if ((int)($visitor['sales_line_id'] ?? 0) !== (int)$line['id']) {
            throw new InvalidArgumentException('ویزیتور انتخاب‌شده عضو لاین هدف نیست.');
        }
        if ($actor && !OrgAccess::canAccessUser($actor, (int)$visitor['id'])) {
            throw new DomainException('ویزیتور هدف خارج از دامنه سازمانی مجاز شماست.');
        }
        $productCode = self::requiredText($productCode, 100, 'کد کالا الزامی است.');
        return compact('period', 'line', 'visitor', 'productCode');
    }

    public static function productReference(string $productCode, int $periodId): array
    {
        $product = Database::fetch(
            'SELECT product_name,brand_code,brand_name
             FROM vw_active_product_priorities
             WHERE product_code=? AND (period_id=? OR period_id IS NULL)
             ORDER BY period_id=? DESC,id DESC LIMIT 1',
            [$productCode, $periodId, $periodId]
        );
        if (!$product) {
            $product = Database::fetch(
                'SELECT product_name,brand_code,brand_name
                 FROM vw_active_sales_aggregate_rows
                 WHERE product_code=? ORDER BY invoice_date DESC,id DESC LIMIT 1',
                [$productCode]
            );
        }
        return $product ?: ['product_name'=>null,'brand_code'=>null,'brand_name'=>null];
    }

    public static function saveCoefficient(array $input, int $actorId): int
    {
        $period = self::period((int)($input['period_id'] ?? 0));
        $code = self::text($input['guild_code'] ?? '', 100);
        $title = self::text($input['guild_name'] ?? '', 255);
        $normalized = self::normalizeGuildName($title);
        if ($code === '' && $normalized === '') {
            throw new InvalidArgumentException('کد صنف یا نام صنف الزامی است.');
        }
        $coefficient = SalesDataNormalizer::normalizeDecimal($input['coefficient'] ?? '');
        if ($coefficient === null || (float)$coefficient < 0) {
            throw new InvalidArgumentException('ضریب باید یک عدد صفر یا بزرگ‌تر باشد.');
        }
        $identity = $code !== '' ? 'code:' . strtolower($code) : 'name:' . $normalized;
        $active = !empty($input['active']) ? 1 : 0;
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $version = (int)(Database::fetch(
                'SELECT COALESCE(MAX(version_no),0)+1 next_version
                 FROM sales_customer_class_coefficients
                 WHERE guild_identity_key=? AND period_id=? FOR UPDATE',
                [$identity, (int)$period['id']]
            )['next_version'] ?? 1);
            if ($active) {
                Database::execute(
                    'UPDATE sales_customer_class_coefficients
                     SET active=0,updated_at=NOW()
                     WHERE import_batch_id IS NULL AND guild_identity_key=? AND period_id=? AND active=1',
                    [$identity, (int)$period['id']]
                );
            }
            $sourceKey = hash('sha256', implode('|', ['manual-coefficient',$identity,$period['id'],$version]));
            Database::execute(
                'INSERT INTO sales_customer_class_coefficients
                 (import_batch_id,source_unique_key,period_id,guild_identity_key,customer_class_code,
                  customer_class_title,normalized_guild_name,coefficient,effective_from,effective_to,
                  version_no,source_type,active,created_by,created_at,updated_at)
                 VALUES (NULL,?,?,?,?,?,?,?,?,?,?,"manual",?,?,NOW(),NOW())',
                [
                    $sourceKey,(int)$period['id'],$identity,$code ?: null,$title ?: null,$normalized ?: null,
                    $coefficient,$period['start_date'],$period['end_date'],$version,$active,$actorId,
                ]
            );
            $id = (int)Database::lastInsertId();
            $pdo->commit();
            return $id;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public static function setCoefficientActive(int $id, bool $active, int $actorId): void
    {
        $row = Database::fetch(
            'SELECT * FROM sales_customer_class_coefficients WHERE id=? AND import_batch_id IS NULL LIMIT 1',
            [$id]
        );
        if (!$row) throw new InvalidArgumentException('نسخه دستی ضریب پیدا نشد.');
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            if ($active) {
                Database::execute(
                    'UPDATE sales_customer_class_coefficients SET active=0,updated_at=NOW()
                     WHERE import_batch_id IS NULL AND guild_identity_key=? AND period_id=? AND id<>?',
                    [$row['guild_identity_key'],(int)$row['period_id'],$id]
                );
            }
            Database::execute(
                'UPDATE sales_customer_class_coefficients SET active=?,created_by=?,updated_at=NOW() WHERE id=?',
                [$active ? 1 : 0,$actorId,$id]
            );
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public static function saveTarget(array $input, array $actor): int
    {
        $periodId = (int)($input['period_id'] ?? 0);
        $context = self::validateTargetContext(
            $periodId,
            (string)($input['visitor_code'] ?? ''),
            (string)($input['line_code'] ?? ''),
            (string)($input['product_code'] ?? ''),
            $actor
        );
        $quantity = SalesDataNormalizer::normalizeDecimal($input['target_quantity'] ?? '');
        $amount = SalesDataNormalizer::normalizeDecimal($input['target_amount'] ?? '');
        if ($quantity === null && $amount === null) {
            throw new InvalidArgumentException('حداقل یکی از هدف تعداد یا هدف مبلغ الزامی است.');
        }
        if (($quantity !== null && (float)$quantity < 0) || ($amount !== null && (float)$amount < 0)) {
            throw new InvalidArgumentException('مقادیر هدف نمی‌توانند منفی باشند.');
        }
        $allocation = SalesDataNormalizer::normalizeDecimal($input['allocation_percent'] ?? '');
        if ($allocation !== null && ((float)$allocation < 0 || (float)$allocation > 100)) {
            throw new InvalidArgumentException('درصد تخصیص باید بین صفر تا صد باشد.');
        }
        $product = self::productReference($context['productCode'], $periodId);
        $sourceKey = hash('sha256', implode('|', [
            'manual-target',$periodId,$context['visitor']['id'],$context['line']['id'],$context['productCode'],
        ]));
        $existing = Database::fetch(
            'SELECT id FROM sales_targets
             WHERE import_batch_id IS NULL AND period_id=? AND visitor_user_id=? AND line_id=? AND product_code=?
             ORDER BY id DESC LIMIT 1',
            [$periodId,(int)$context['visitor']['id'],(int)$context['line']['id'],$context['productCode']]
        );
        $values = [
            $sourceKey,$periodId,(int)$context['visitor']['id'],(int)$context['line']['id'],
            $context['period']['jalali_year'] ?: null,$context['period']['jalali_month'] ?: null,
            $context['line']['code'],$context['productCode'],$product['product_name'] ?? null,
            $product['brand_code'] ?? null,$product['brand_name'] ?? null,
            $context['visitor']['employee_no'] ?: $context['visitor']['kara_system_code'],
            $quantity,$amount,$allocation,(int)$actor['id'],
        ];
        if ($existing) {
            Database::execute(
                'UPDATE sales_targets SET source_unique_key=?,period_id=?,visitor_user_id=?,line_id=?,
                    target_year=?,target_month=?,line_code=?,product_code=?,product_name=?,brand_code=?,brand_name=?,
                    visitor_code=?,target_quantity=?,target_amount=?,allocation_percent=?,active=1,source_type="manual",
                    created_by=?,updated_at=NOW() WHERE id=?',
                array_merge($values, [(int)$existing['id']])
            );
            return (int)$existing['id'];
        }
        Database::execute(
            'INSERT INTO sales_targets
             (import_batch_id,source_unique_key,period_id,visitor_user_id,line_id,target_year,target_month,line_code,
              product_code,product_name,brand_code,brand_name,visitor_code,target_quantity,target_amount,
              allocation_percent,active,source_type,created_by,created_at,updated_at)
             VALUES (NULL,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1,"manual",?,NOW(),NOW())',
            $values
        );
        return (int)Database::lastInsertId();
    }

    public static function setTargetActive(int $id, bool $active, array $actor): void
    {
        $target = Database::fetch('SELECT visitor_user_id FROM sales_targets WHERE id=? AND import_batch_id IS NULL', [$id]);
        if (!$target || !OrgAccess::canAccessUser($actor, (int)$target['visitor_user_id'])) {
            throw new DomainException('هدف دستی در دامنه مجاز شما پیدا نشد.');
        }
        Database::execute('UPDATE sales_targets SET active=?,updated_at=NOW() WHERE id=?', [$active ? 1 : 0,$id]);
    }

    public static function periods(): array
    {
        return Database::fetchAll(
            "SELECT id,period_key,title,start_date,end_date,is_current
             FROM system_periods
             WHERE is_active=1 AND period_type IN ('monthly','quarterly','half_yearly','yearly','custom')
               AND start_date IS NOT NULL AND end_date IS NOT NULL
             ORDER BY is_current DESC,start_date DESC,id DESC"
        );
    }

    public static function linesForActor(array $actor): array
    {
        if (OrgAccess::isAdmin($actor)) {
            return Database::fetchAll('SELECT id,code,title FROM sales_lines WHERE active=1 ORDER BY sort_order,code');
        }
        $ids = OrgAccess::accessibleUserIds($actor);
        if (!$ids) return [];
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        return Database::fetchAll(
            "SELECT DISTINCT l.id,l.code,l.title
             FROM sales_lines l
             LEFT JOIN users u ON u.sales_line_id=l.id
             WHERE l.active=1 AND (
                l.manager_user_id IN ({$placeholders}) OR l.supervisor_user_id IN ({$placeholders})
                OR u.id IN ({$placeholders})
             )
             ORDER BY l.sort_order,l.code",
            array_merge($ids,$ids,$ids)
        );
    }

    public static function visitorsForActor(array $actor): array
    {
        $ids = OrgAccess::accessibleUserIds($actor);
        if (!$ids) return [];
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        return Database::fetchAll(
            "SELECT u.id,u.name,u.employee_no,u.kara_system_code,u.sales_line_id,l.code line_code,l.title line_title
             FROM users u
             LEFT JOIN org_roles r ON r.id=u.org_role_id
             LEFT JOIN sales_lines l ON l.id=u.sales_line_id
             WHERE u.status='active' AND COALESCE(r.code,u.role_key)='VISITOR' AND u.id IN ({$placeholders})
             ORDER BY l.sort_order,u.display_order,u.name",
            $ids
        );
    }

    public static function coefficients(?int $periodId = null): array
    {
        $where = $periodId ? 'WHERE c.period_id=?' : '';
        return Database::fetchAll(
            "SELECT c.*,p.title period_title,u.name creator_name
             FROM sales_customer_class_coefficients c
             LEFT JOIN system_periods p ON p.id=c.period_id
             LEFT JOIN users u ON u.id=c.created_by
             {$where}
             ORDER BY c.active DESC,p.start_date DESC,c.guild_identity_key,c.version_no DESC,c.id DESC",
            $periodId ? [$periodId] : []
        );
    }

    public static function priorities(?int $periodId = null): array
    {
        $where = $periodId ? 'WHERE p.period_id=?' : '';
        return Database::fetchAll(
            "SELECT p.*,sp.title period_title
             FROM vw_active_product_priorities p
             LEFT JOIN system_periods sp ON sp.id=p.period_id
             {$where}
             ORDER BY FIELD(p.priority_code,'P1','P2','P3','P4'),p.priority_rank,p.product_name,p.product_code
             LIMIT 500",
            $periodId ? [$periodId] : []
        );
    }

    public static function targets(array $actor, ?int $periodId = null): array
    {
        $ids = OrgAccess::accessibleUserIds($actor);
        if (!$ids) return [];
        $where = ['t.visitor_user_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')'];
        $params = $ids;
        if ($periodId) {
            $where[] = 't.period_id=?';
            $params[] = $periodId;
        }
        return Database::fetchAll(
            'SELECT t.*,p.title period_title,u.name visitor_name,l.code line_code,l.title line_title
             FROM vw_active_sales_targets t
             JOIN system_periods p ON p.id=t.period_id
             JOIN users u ON u.id=t.visitor_user_id
             JOIN sales_lines l ON l.id=t.line_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY p.start_date DESC,l.sort_order,u.name,t.product_name,t.product_code
             LIMIT 500',
            $params
        );
    }

    public static function achievement(array $actor, int $periodId, string $grain): array
    {
        $ids = OrgAccess::accessibleUserIds($actor);
        if (!$ids) return [];
        $definitions = [
            'visitor_product' => ['vw_sales_target_achievement', 'visitor_user_id', 'visitor_name,product_name,product_code'],
            'visitor_total' => ['vw_sales_target_visitor_totals', 'visitor_user_id', 'visitor_name'],
            'line_product' => ['vw_sales_target_achievement', 'visitor_user_id', 'line_title,product_name,product_code'],
            'line_total' => ['vw_sales_target_achievement', 'visitor_user_id', 'line_title'],
            'brand' => ['vw_sales_target_achievement', 'visitor_user_id', 'brand_name'],
        ];
        if (!isset($definitions[$grain])) $grain = 'visitor_product';
        [$view,$scopeColumn,$group] = $definitions[$grain];
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        if ($grain === 'visitor_product') {
            return Database::fetchAll(
                "SELECT * FROM {$view} WHERE period_id=? AND {$scopeColumn} IN ({$placeholders})
                 ORDER BY visitor_name,product_name,product_code LIMIT 500",
                array_merge([$periodId],$ids)
            );
        }
        $selectLabels = $group;
        return Database::fetchAll(
            "SELECT period_id,{$selectLabels},
                    SUM(target_quantity) target_quantity,SUM(target_amount) target_amount,
                    SUM(achievement_quantity) achievement_quantity,SUM(achievement_amount) achievement_amount
             FROM {$view}
             WHERE period_id=? AND {$scopeColumn} IN ({$placeholders})
             GROUP BY period_id,{$group}
             ORDER BY {$group} LIMIT 500",
            array_merge([$periodId],$ids)
        );
    }

    private static function text(mixed $value, int $max): string
    {
        $value = trim((string)$value);
        return function_exists('mb_substr') ? mb_substr($value, 0, $max, 'UTF-8') : substr($value, 0, $max);
    }

    private static function requiredText(mixed $value, int $max, string $message): string
    {
        $value = self::text($value, $max);
        if ($value === '') throw new InvalidArgumentException($message);
        return $value;
    }
}
