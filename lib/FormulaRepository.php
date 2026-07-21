<?php

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/FormulaEngine.php';

final class FormulaRepository
{
    public static function listDefinitions(?string $category = null): array
    {
        $params = [];
        $where = 'd.active=1';
        if ($category !== null && $category !== '') {
            $where .= ' AND d.category_key=?';
            $params[] = $category;
        }
        return Database::fetchAll(
            "SELECT d.*,
                    av.id active_version_id,av.version_no active_version_no,av.effective_from,av.effective_to,
                    dv.id draft_version_id,dv.version_no draft_version_no
             FROM formula_definitions d
             LEFT JOIN formula_versions av ON av.definition_id=d.id AND av.status='active'
             LEFT JOIN formula_versions dv ON dv.id=(
                SELECT v2.id FROM formula_versions v2
                WHERE v2.definition_id=d.id AND v2.status='draft'
                ORDER BY v2.version_no DESC LIMIT 1
             )
             WHERE {$where}
             ORDER BY d.category_key,d.title,d.id",
            $params
        );
    }

    public static function definition(int $id): ?array
    {
        return Database::fetch('SELECT * FROM formula_definitions WHERE id=?', [$id]) ?: null;
    }

    public static function version(int $id): ?array
    {
        $version = Database::fetch(
            'SELECT v.*,d.formula_key,d.title,d.category_key,d.description,d.owner_scope,d.active definition_active
             FROM formula_versions v
             JOIN formula_definitions d ON d.id=v.definition_id
             WHERE v.id=?',
            [$id]
        );
        if (!$version) return null;
        $version['rule'] = json_decode((string)$version['rule_json'], true) ?: [];
        $version['condition_values'] = json_decode((string)$version['condition_value_json'], true) ?: [];
        $version['filters'] = self::filters($id);
        $version['dependency_ids'] = array_map(
            'intval',
            array_column(Database::fetchAll(
                'SELECT depends_on_definition_id FROM formula_dependencies WHERE formula_version_id=? ORDER BY depends_on_definition_id',
                [$id]
            ), 'depends_on_definition_id')
        );
        return $version;
    }

    public static function versions(int $definitionId): array
    {
        return Database::fetchAll(
            'SELECT v.*,u.name created_by_name,p.name published_by_name
             FROM formula_versions v
             LEFT JOIN users u ON u.id=v.created_by
             LEFT JOIN users p ON p.id=v.published_by
             WHERE v.definition_id=?
             ORDER BY v.version_no DESC',
            [$definitionId]
        );
    }

    public static function saveDraft(array $normalized, int $actorId, ?int $definitionId = null): int
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            if ($definitionId > 0) {
                $definition = Database::fetch('SELECT * FROM formula_definitions WHERE id=? FOR UPDATE', [$definitionId]);
                if (!$definition) throw new InvalidArgumentException('فرمول انتخاب‌شده پیدا نشد.');
                $duplicate = Database::fetch(
                    'SELECT id FROM formula_definitions WHERE formula_key=? AND id<>?',
                    [$normalized['formula_key'], $definitionId]
                );
                if ($duplicate) throw new InvalidArgumentException('کلید فرمول قبلاً استفاده شده است.');
                Database::execute(
                    'UPDATE formula_definitions
                     SET formula_key=?,title=?,category_key=?,description=?,owner_scope=?,active=?,updated_at=NOW()
                     WHERE id=?',
                    [
                        $normalized['formula_key'],
                        $normalized['title'],
                        $normalized['category_key'],
                        $normalized['description'],
                        $normalized['owner_scope'],
                        $normalized['active'],
                        $definitionId,
                    ]
                );
            } else {
                if (Database::fetch('SELECT id FROM formula_definitions WHERE formula_key=?', [$normalized['formula_key']])) {
                    throw new InvalidArgumentException('کلید فرمول قبلاً استفاده شده است.');
                }
                Database::execute(
                    'INSERT INTO formula_definitions
                     (formula_key,title,category_key,description,owner_scope,active,created_by,created_at,updated_at)
                     VALUES (?,?,?,?,?,?,?,NOW(),NOW())',
                    [
                        $normalized['formula_key'],
                        $normalized['title'],
                        $normalized['category_key'],
                        $normalized['description'],
                        $normalized['owner_scope'],
                        $normalized['active'],
                        $actorId ?: null,
                    ]
                );
                $definitionId = (int)$pdo->lastInsertId();
            }

            self::assertDependencies($definitionId, $normalized['dependency_ids']);
            $nextVersion = (int)(Database::fetch(
                'SELECT COALESCE(MAX(version_no),0)+1 next_version FROM formula_versions WHERE definition_id=? FOR UPDATE',
                [$definitionId]
            )['next_version'] ?? 1);
            Database::execute(
                'INSERT INTO formula_versions
                 (definition_id,version_no,status,effective_from,effective_to,data_source_key,metric_key,comparison_metric_key,
                  aggregation_key,operator_key,condition_value_json,result_type,result_value,priority,user_note,rule_json,created_by,created_at,updated_at)
                 VALUES (?,?,"draft",?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())',
                [
                    $definitionId,
                    $nextVersion,
                    $normalized['effective_from'],
                    $normalized['effective_to'],
                    $normalized['data_source_key'],
                    $normalized['metric_key'],
                    $normalized['comparison_metric_key'],
                    $normalized['aggregation_key'],
                    $normalized['operator_key'],
                    self::json($normalized['condition_values']),
                    $normalized['result_type'],
                    $normalized['result_value'],
                    $normalized['priority'],
                    $normalized['user_note'],
                    self::json($normalized['rule']),
                    $actorId ?: null,
                ]
            );
            $versionId = (int)$pdo->lastInsertId();
            foreach ($normalized['filters'] as $index => $filter) {
                Database::execute(
                    'INSERT INTO formula_filters
                     (formula_version_id,field_key,operator_key,value_json,sort_order,created_at)
                     VALUES (?,?,?,?,?,NOW())',
                    [
                        $versionId,
                        $filter['field_key'],
                        $filter['operator_key'],
                        self::json($filter['values']),
                        $index,
                    ]
                );
            }
            foreach ($normalized['dependency_ids'] as $dependencyId) {
                Database::execute(
                    'INSERT INTO formula_dependencies(formula_version_id,depends_on_definition_id,created_at)
                     VALUES (?,?,NOW())',
                    [$versionId, $dependencyId]
                );
            }
            self::audit($definitionId, $versionId, $actorId, 'draft_created', null, [
                'version_no' => $nextVersion,
                'rule' => $normalized['rule'],
            ]);
            $pdo->commit();
            return $versionId;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public static function publish(int $versionId, int $actorId): void
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $locked = Database::fetch('SELECT id,status FROM formula_versions WHERE id=? FOR UPDATE', [$versionId]);
            if (!$locked || $locked['status'] !== 'draft') {
                throw new InvalidArgumentException('فقط نسخه پیش‌نویس قابل انتشار است.');
            }
            Database::fetchAll('SELECT id FROM formula_versions WHERE status="active" FOR UPDATE');
            $version = self::version($versionId);
            if (!$version) throw new InvalidArgumentException('نسخه فرمول پیدا نشد.');
            $conflicts = self::conflicts($version);
            if ($conflicts) {
                throw new InvalidArgumentException(
                    'انتشار به‌دلیل تداخل با فرمول فعال «' . ($conflicts[0]['title'] ?? 'نامشخص') . '» متوقف شد.'
                );
            }
            self::assertDependencies((int)$version['definition_id'], $version['dependency_ids']);
            $oldActive = Database::fetch(
                'SELECT * FROM formula_versions WHERE definition_id=? AND status="active" FOR UPDATE',
                [(int)$version['definition_id']]
            );
            Database::execute(
                'UPDATE formula_versions SET status="retired",updated_at=NOW()
                 WHERE definition_id=? AND status="active"',
                [(int)$version['definition_id']]
            );
            Database::execute(
                'UPDATE formula_versions
                 SET status="active",published_by=?,published_at=NOW(),updated_at=NOW()
                 WHERE id=? AND status="draft"',
                [$actorId ?: null, $versionId]
            );
            self::audit(
                (int)$version['definition_id'],
                $versionId,
                $actorId,
                'published',
                $oldActive,
                ['version_no' => $version['version_no']]
            );
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public static function rollbackToVersion(int $versionId, int $actorId): int
    {
        $version = self::version($versionId);
        if (!$version || !in_array($version['status'], ['active', 'retired'], true)) {
            throw new InvalidArgumentException('نسخه انتخاب‌شده قابل بازگردانی نیست.');
        }
        $input = [
            'formula_key' => $version['formula_key'],
            'title' => $version['title'],
            'category_key' => $version['category_key'],
            'description' => $version['description'],
            'owner_scope' => $version['owner_scope'],
            'data_source_key' => $version['data_source_key'],
            'metric_key' => $version['metric_key'],
            'comparison_metric_key' => $version['comparison_metric_key'],
            'aggregation_key' => $version['aggregation_key'],
            'operator_key' => $version['operator_key'],
            'condition_value' => implode(',', $version['condition_values']),
            'result_type' => $version['result_type'],
            'result_value' => $version['result_value'],
            'priority' => $version['priority'],
            'effective_from' => $version['effective_from'],
            'effective_to' => $version['effective_to'],
            'active' => (int)$version['definition_active'] === 1 ? '1' : null,
            'user_note' => 'بازگردانی از نسخه ' . $version['version_no'],
            'dependency_ids' => $version['dependency_ids'],
            'filter_field' => array_column($version['filters'], 'field_key'),
            'filter_operator' => array_column($version['filters'], 'operator_key'),
            'filter_value' => array_map(
                static fn(array $filter): string => implode(',', $filter['values']),
                $version['filters']
            ),
        ];
        $normalized = FormulaEngine::normalizeBuilderInput($input);
        $newVersionId = self::saveDraft($normalized, $actorId, (int)$version['definition_id']);
        self::audit(
            (int)$version['definition_id'],
            $newVersionId,
            $actorId,
            'rollback_draft_created',
            ['source_version_id' => $versionId],
            ['new_version_id' => $newVersionId]
        );
        return $newVersionId;
    }

    public static function runTest(int $versionId, array $context, int $actorId): array
    {
        $version = self::version($versionId);
        if (!$version) throw new InvalidArgumentException('نسخه فرمول پیدا نشد.');
        $rows = FormulaSourceRegistry::loadRows((string)$version['data_source_key'], $context);
        $result = FormulaEngine::evaluate($version['rule'], $rows);
        Database::execute(
            'INSERT INTO formula_test_runs
             (formula_version_id,actor_user_id,context_json,input_values_json,trace_json,matched,final_result,created_at)
             VALUES (?,?,?,?,?,?,?,NOW())',
            [
                $versionId,
                $actorId ?: null,
                self::json(array_diff_key($context, ['sample_values' => true])),
                self::json($context['sample_values'] ?? []),
                self::json($result['trace']),
                $result['matched'] ? 1 : 0,
                $result['final_result'],
            ]
        );
        return $result + ['rows' => $rows, 'version' => $version];
    }

    public static function conflicts(array $version): array
    {
        $from = $version['effective_from'] ?: '1000-01-01';
        $to = $version['effective_to'] ?: '9999-12-31';
        return Database::fetchAll(
            'SELECT v.id,v.version_no,d.title,d.formula_key
             FROM formula_versions v
             JOIN formula_definitions d ON d.id=v.definition_id
             WHERE v.status="active"
               AND v.definition_id<>?
               AND d.category_key=?
               AND v.data_source_key=?
               AND v.metric_key=?
               AND v.priority=?
               AND COALESCE(v.effective_from,"1000-01-01")<=?
               AND COALESCE(v.effective_to,"9999-12-31")>=?
             ORDER BY d.title,v.version_no DESC',
            [
                (int)$version['definition_id'],
                $version['category_key'],
                $version['data_source_key'],
                $version['metric_key'],
                (int)$version['priority'],
                $to,
                $from,
            ]
        );
    }

    public static function auditLogs(int $definitionId, int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));
        return Database::fetchAll(
            "SELECT l.*,u.name actor_name
             FROM formula_audit_logs l
             LEFT JOIN users u ON u.id=l.actor_user_id
             WHERE l.definition_id=?
             ORDER BY l.id DESC LIMIT {$limit}",
            [$definitionId]
        );
    }

    private static function filters(int $versionId): array
    {
        $rows = Database::fetchAll(
            'SELECT field_key,operator_key,value_json,sort_order
             FROM formula_filters WHERE formula_version_id=? ORDER BY sort_order,id',
            [$versionId]
        );
        foreach ($rows as &$row) $row['values'] = json_decode((string)$row['value_json'], true) ?: [];
        unset($row);
        return $rows;
    }

    private static function assertDependencies(int $definitionId, array $dependencies): void
    {
        if (in_array($definitionId, $dependencies, true)) {
            throw new InvalidArgumentException('یک فرمول نمی‌تواند به خودش وابسته باشد.');
        }
        if ($dependencies) {
            $placeholders = implode(',', array_fill(0, count($dependencies), '?'));
            $count = (int)(Database::fetch(
                "SELECT COUNT(*) c FROM formula_definitions WHERE id IN ({$placeholders}) AND active=1",
                $dependencies
            )['c'] ?? 0);
            if ($count !== count($dependencies)) throw new InvalidArgumentException('یکی از فرمول‌های وابسته معتبر نیست.');
        }
        $edges = [];
        foreach (Database::fetchAll(
            'SELECT v.definition_id,d.depends_on_definition_id
             FROM formula_dependencies d
             JOIN formula_versions v ON v.id=d.formula_version_id
             WHERE v.status IN ("active","draft")'
        ) as $row) {
            $edges[(int)$row['definition_id']][] = (int)$row['depends_on_definition_id'];
        }
        $edges[$definitionId] = $dependencies;
        $visiting = [];
        $visited = [];
        $visit = static function (int $node) use (&$visit, &$edges, &$visiting, &$visited): void {
            if (isset($visiting[$node])) throw new InvalidArgumentException('وابستگی دوری بین فرمول‌ها شناسایی شد.');
            if (isset($visited[$node])) return;
            $visiting[$node] = true;
            foreach ($edges[$node] ?? [] as $next) $visit((int)$next);
            unset($visiting[$node]);
            $visited[$node] = true;
        };
        foreach (array_keys($edges) as $node) $visit((int)$node);
    }

    private static function audit(
        int $definitionId,
        ?int $versionId,
        int $actorId,
        string $action,
        mixed $oldValue,
        mixed $newValue,
        ?string $note = null
    ): void {
        Database::execute(
            'INSERT INTO formula_audit_logs
             (definition_id,formula_version_id,actor_user_id,action,old_value_json,new_value_json,note,created_at)
             VALUES (?,?,?,?,?,?,?,NOW())',
            [
                $definitionId ?: null,
                $versionId ?: null,
                $actorId ?: null,
                $action,
                self::json($oldValue),
                self::json($newValue),
                $note,
            ]
        );
    }

    private static function json(mixed $value): ?string
    {
        if ($value === null) return null;
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
        if ($json === false) throw new RuntimeException('ساخت داده ساختاریافته فرمول انجام نشد.');
        return $json;
    }
}
