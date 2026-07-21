<?php
require_once __DIR__ . '/../../core/DashboardModule.php';

return ['seed_key'=>'dashboard_defaults','run'=>static function(PDO $pdo,array $options):array{
    $expected = array_sum(array_map('count', DashboardModule::definitions()));
    $existing = (int)$pdo->query('SELECT COUNT(*) FROM dashboard_widget_preferences')->fetchColumn();
    if (($options['mode'] ?? 'safe') === 'dry_run') {
        return [
            'inserted'=>max(0,$expected-$existing),'updated'=>0,'skipped'=>min($expected,$existing),'errors'=>0,
            'details'=>['would_insert_preferences'=>max(0,$expected-$existing),'operational_data_protected'=>0],
        ];
    }
    DashboardModule::repair($pdo);
    $after = (int)$pdo->query('SELECT COUNT(*) FROM dashboard_widget_preferences')->fetchColumn();
    return [
        'inserted'=>max(0,$after-$existing),'updated'=>0,'skipped'=>min($expected,$existing),'errors'=>0,
        'details'=>['dashboard_preferences'=>$after,'operational_data_protected'=>0],
    ];
}];
