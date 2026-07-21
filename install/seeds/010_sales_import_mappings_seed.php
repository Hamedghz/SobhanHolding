<?php

require_once dirname(__DIR__, 2) . '/lib/ImportSourceRegistry.php';

return [
    'seed_key' => 'sales_import_mappings',
    'run' => static function (PDO $pdo, array $options): array {
        $definitions = [];
        foreach (ImportSourceRegistry::all() as $sourceModule => $source) {
            foreach ($source['mappings'] ?? [] as $mapping) {
                $definitions[$sourceModule . "\0" . $mapping['source_header']] = [
                    'source_module' => $sourceModule,
                    'source_header' => $mapping['source_header'],
                    'normalized_key' => $mapping['normalized_key'],
                    'required' => (int)($mapping['required'] ?? 0),
                    'data_type' => $mapping['data_type'] ?? 'string',
                ];
            }
        }

        $existing = [];
        foreach ($pdo->query('SELECT source_module,source_header FROM sales_import_column_mappings')->fetchAll() as $row) {
            $existing[$row['source_module'] . "\0" . $row['source_header']] = true;
        }
        $missing = array_diff_key($definitions, $existing);
        if (($options['mode'] ?? 'safe') === 'dry_run') {
            return [
                'inserted' => count($missing),
                'updated' => 0,
                'skipped' => count($definitions) - count($missing),
                'errors' => 0,
                'details' => ['would_insert_mappings' => count($missing), 'existing_mappings_preserved' => count($definitions) - count($missing)],
            ];
        }

        $stmt = $pdo->prepare(
            'INSERT IGNORE INTO sales_import_column_mappings
                (source_module,source_header,normalized_key,required,data_type,active,created_at,updated_at)
             VALUES (?,?,?,?,?,1,NOW(),NOW())'
        );
        $inserted = 0;
        foreach ($missing as $mapping) {
            $stmt->execute([
                $mapping['source_module'],
                $mapping['source_header'],
                $mapping['normalized_key'],
                $mapping['required'],
                $mapping['data_type'],
            ]);
            $inserted += $stmt->rowCount();
        }

        return [
            'inserted' => $inserted,
            'updated' => 0,
            'skipped' => count($definitions) - $inserted,
            'errors' => 0,
            'details' => ['default_mappings' => count($definitions), 'existing_mappings_preserved' => count($definitions) - $inserted],
        ];
    },
];
