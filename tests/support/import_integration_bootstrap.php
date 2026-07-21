<?php

function importIntegrationPdo(string $root): PDO
{
    $dsn = getenv('SOBHAN_TEST_DSN') ?: '';
    $user = getenv('SOBHAN_TEST_DB_USER') ?: 'root';
    $password = getenv('SOBHAN_TEST_DB_PASSWORD') ?: '';
    if ($dsn === '') {
        fwrite(STDERR, "SOBHAN_TEST_DSN is required.\n");
        exit(2);
    }

    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    $sql = (string)file_get_contents($root . '/database/schema.sql');
    $statements = preg_split('/;\s*(?:\r?\n|$)/', $sql, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    for ($pass = 1; $pass <= 2; $pass++) {
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if ($statement === '' || str_starts_with($statement, '--')) {
                $statement = preg_replace('/^(?:--[^\r\n]*(?:\r?\n|$))+/', '', $statement) ?? $statement;
                $statement = trim($statement);
            }
            if ($statement !== '') $pdo->exec($statement);
        }
    }

    $reflection = new ReflectionClass(Database::class);
    $reflection->getProperty('pdo')->setValue(null, $pdo);
    $reflection->getProperty('migrated')->setValue(null, true);

    return $pdo;
}

function seedImportIntegrationMappings(PDO $pdo): void
{
    $statement = $pdo->prepare(
        'INSERT INTO sales_import_column_mappings
         (source_module,source_header,normalized_key,required,data_type,active,created_at,updated_at)
         VALUES (?,?,?,?,?,1,NOW(),NOW())
         ON DUPLICATE KEY UPDATE
           normalized_key=VALUES(normalized_key),
           required=VALUES(required),
           data_type=VALUES(data_type),
           active=1,
           updated_at=NOW()'
    );
    if (class_exists('SalesDataNormalizer')) {
        foreach (SalesDataNormalizer::mappingDefinitions() as $mapping) {
            $statement->execute([
                'sales_aggregate',
                $mapping['source_header'],
                $mapping['normalized_key'],
                $mapping['required'],
                $mapping['data_type'],
            ]);
        }
    }
    if (class_exists('InventoryImportService')) {
        foreach (InventoryImportService::mappingDefinitions() as $mapping) {
            $statement->execute([
                'inventory_aggregate',
                $mapping['source_header'],
                $mapping['normalized_key'],
                $mapping['required'],
                $mapping['data_type'],
            ]);
        }
    }
}
