<?php

return [
    'seed_key' => 'hr_assessment',

    'run' => static function (PDO $pdo, array $options): array {
        $data = HrModule::sobhanAssessmentData();
        $expected = count($data['tests'])
            + array_sum(array_map(static fn(array $test): int => count($test['questions'] ?? []), $data['tests']))
            + count($data['packages'] ?? [])
            + array_sum(array_map(static fn(array $package): int => count($package['tests'] ?? []), $data['packages'] ?? []));
        $mode = $options['mode'] ?? 'safe';

        if ($mode === 'dry_run') {
            $testCount = (int) $pdo
                ->query('SELECT COUNT(*) FROM hr_assessment_tests')
                ->fetchColumn();

            $questionCount = (int) $pdo
                ->query('SELECT COUNT(*) FROM hr_assessment_questions')
                ->fetchColumn();

            $existing = $testCount + $questionCount;

            return [
                'inserted' => max(0, $expected - $existing),
                'updated' => 0,
                'skipped' => min($expected, $existing),
                'errors' => 0,
                'details' => [
                    'would_insert' => max(0, $expected - $existing),
                ],
            ];
        }

        $counts = HrModule::seed($pdo, ['assessment']);

        return sobhan_seed_result($counts, $expected);
    },
];
