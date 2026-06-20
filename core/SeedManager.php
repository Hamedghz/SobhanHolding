<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/HrModule.php';
require_once __DIR__ . '/SystemMaintenance.php';
require_once __DIR__ . '/../lib/sobhan_hr_seed_helpers.php';

class SeedManager
{
    public static function registry(): array
    {
        return require __DIR__ . '/../install/seed_registry.php';
    }

    public static function run(string $key, string $mode, int $userId): array
    {
        $registry = self::registry();
        if (!isset($registry[$key])) throw new InvalidArgumentException('seed_not_found');
        if (!in_array($mode, ['safe', 'repair', 'force_update', 'dry_run'], true)) $mode = 'safe';

        $pdo = Database::connection();
        SystemMaintenance::repair($pdo);
        HrModule::repair($pdo);
        Database::execute('INSERT INTO seed_runs(seed_group,mode,status,requested_by,started_at,created_at) VALUES (?,? ,"running",?,NOW(),NOW())', [$key, $mode, $userId]);
        $runId = (int)Database::lastInsertId();

        try {
            $seed = require __DIR__ . '/../install/' . $registry[$key]['file'];
            $runner = $seed['run'] ?? null;
            if (!is_callable($runner)) throw new RuntimeException('invalid_seed');
            $result = $runner($pdo, ['mode' => $mode, 'user_id' => $userId]);
            $result += ['inserted' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0, 'details' => []];
            foreach ((array)$result['details'] as $itemKey => $count) {
                $action = (int)$count > 0 ? 'inserted' : 'skipped_existing';
                Database::execute('INSERT INTO seed_run_items(seed_run_id,seed_key,action,status,table_name,record_key,message,created_at) VALUES (?,?,?,"completed",NULL,?,?,NOW())', [$runId, (string)$itemKey, $action, (string)$itemKey, (string)$count]);
            }
            Database::execute('UPDATE seed_runs SET status="completed",finished_at=NOW(),inserted_count=?,updated_count=?,skipped_count=?,error_count=?,message=? WHERE id=?', [(int)$result['inserted'], (int)$result['updated'], (int)$result['skipped'], (int)$result['errors'], $mode === 'dry_run' ? 'بررسی بدون اجرا انجام شد.' : 'Seed با موفقیت اجرا شد.', $runId]);
            return ['run_id' => $runId, 'status' => 'completed'] + $result;
        } catch (Throwable $e) {
            error_log('Seed manager [' . $key . ']: ' . $e->getMessage());
            Database::execute('UPDATE seed_runs SET status="failed",finished_at=NOW(),error_count=1,message=? WHERE id=?', ['اجرای Seed ناموفق بود.', $runId]);
            return ['run_id' => $runId, 'status' => 'failed', 'inserted' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 1, 'details' => []];
        }
    }

    public static function runMany(array $keys, string $mode, int $userId): array
    {
        $results = [];
        foreach ($keys as $key) $results[$key] = self::run((string)$key, $mode, $userId);
        return $results;
    }

    public static function latestRuns(): array
    {
        return Database::fetchAll('SELECT r.* FROM seed_runs r JOIN (SELECT seed_group,MAX(id) id FROM seed_runs GROUP BY seed_group) x ON x.id=r.id ORDER BY r.id DESC');
    }
}
