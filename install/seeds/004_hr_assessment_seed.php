<?php
return ['seed_key'=>'hr_assessment','run'=>static function(PDO $pdo,array $options):array{
    $data = HrModule::sobhanAssessmentData();
    $expected = (int)($data['meta']['test_count'] ?? 0) + (int)($data['meta']['question_count'] ?? 0);
    if(($options['mode']??'safe')==='dry_run'){
        $existing=(int)$pdo->query('SELECT COUNT(*) FROM hr_assessment_tests WHERE active=1 AND COALESCE(is_archived,0)=0')->fetchColumn()+(int)$pdo->query('SELECT COUNT(*) FROM hr_assessment_questions WHERE active=1')->fetchColumn();
        return ['inserted'=>max(0,$expected-$existing),'updated'=>0,'skipped'=>min($expected,$existing),'errors'=>0,'details'=>['target_tests'=>$data['meta']['test_count'] ?? 0,'target_questions'=>$data['meta']['question_count'] ?? 0,'seed_version'=>$data['meta']['seed_version'] ?? '']]];
    }
    $counts=HrModule::seed($pdo,['assessment']);
    return [
        'inserted' => $expected,
        'updated' => (int)($counts['updated'] ?? 0),
        'skipped' => 0,
        'errors' => 0,
        'details' => $counts,
    ];
}];
