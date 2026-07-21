<?php

require_once __DIR__ . '/../lib/FormulaEngine.php';

final class FormulaRuntime
{
    private static array $keyVersionCache = [];
    private static array $definitionVersionCache = [];
    private static bool $databaseUnavailable = false;

    public static function evaluateByKey(string $formulaKey, array $inputValues, ?string $effectiveDate = null): ?array
    {
        if (self::$databaseUnavailable) return null;
        try {
            Database::connection();
            if (!Database::tableExists('formula_definitions') || !Database::tableExists('formula_versions')) return null;
            $effectiveDate = $effectiveDate ?: date('Y-m-d');
            $cacheKey = $formulaKey . '|' . $effectiveDate;
            if (!array_key_exists($cacheKey, self::$keyVersionCache)) {
                self::$keyVersionCache[$cacheKey] = Database::fetch(
                    'SELECT v.*
                     FROM formula_definitions d
                     JOIN formula_versions v ON v.definition_id=d.id
                     WHERE d.formula_key=? AND d.active=1 AND v.status="active"
                       AND (v.effective_from IS NULL OR v.effective_from<=?)
                       AND (v.effective_to IS NULL OR v.effective_to>=?)
                     ORDER BY v.priority ASC,v.version_no DESC LIMIT 1',
                    [$formulaKey, $effectiveDate, $effectiveDate]
                ) ?: false;
            }
            $version = self::$keyVersionCache[$cacheKey];
            return is_array($version) ? self::evaluateVersion($version, $inputValues) : null;
        } catch (Throwable $e) {
            self::$databaseUnavailable = true;
            error_log('Formula runtime fallback: ' . $formulaKey . ': ' . $e->getMessage());
            return null;
        }
    }

    public static function evaluateDefinition(int $definitionId, array $inputValues, ?string $effectiveDate = null): ?array
    {
        if (self::$databaseUnavailable) return null;
        try {
            Database::connection();
            $effectiveDate = $effectiveDate ?: date('Y-m-d');
            $cacheKey = $definitionId . '|' . $effectiveDate;
            if (!array_key_exists($cacheKey, self::$definitionVersionCache)) {
                self::$definitionVersionCache[$cacheKey] = Database::fetch(
                    'SELECT * FROM formula_versions
                     WHERE definition_id=? AND status="active"
                       AND (effective_from IS NULL OR effective_from<=?)
                       AND (effective_to IS NULL OR effective_to>=?)
                     ORDER BY priority ASC,version_no DESC LIMIT 1',
                    [$definitionId, $effectiveDate, $effectiveDate]
                ) ?: false;
            }
            $version = self::$definitionVersionCache[$cacheKey];
            return is_array($version) ? self::evaluateVersion($version, $inputValues) : null;
        } catch (Throwable $e) {
            self::$databaseUnavailable = true;
            error_log('Formula runtime definition fallback: ' . $definitionId . ': ' . $e->getMessage());
            return null;
        }
    }

    private static function evaluateVersion(array $version, array $inputValues): ?array
    {
        $source = FormulaSourceRegistry::source((string)$version['data_source_key']);
        if (!$source || ($source['table'] ?? null) !== null) return null;
        $rule = json_decode((string)$version['rule_json'], true);
        if (!is_array($rule)) return null;
        $result = FormulaEngine::evaluate($rule, [$inputValues]);
        return $result + [
            'formula_version_id' => (int)$version['id'],
            'formula_version_no' => (int)$version['version_no'],
        ];
    }
}
