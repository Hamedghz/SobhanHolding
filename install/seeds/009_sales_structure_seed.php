<?php
return ['seed_key'=>'sales_structure','run'=>static function(PDO $pdo,array $options):array{
    require_once dirname(__DIR__, 2) . '/core/Database.php';
    require_once dirname(__DIR__, 2) . '/core/SalesStructureModule.php';

    $before = [];
    foreach (['sales_lines','sales_geographies','sales_line_brands','sales_visitor_territories'] as $table) {
        try { $before[$table] = (int)$pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn(); }
        catch (Throwable $e) { $before[$table] = 0; }
    }

    if (($options['mode'] ?? 'safe') !== 'dry_run') {
        SalesStructureModule::repair($pdo);
    }

    $after = [];
    foreach (['sales_lines','sales_geographies','sales_line_brands','sales_visitor_territories'] as $table) {
        try { $after[$table] = (int)$pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn(); }
        catch (Throwable $e) { $after[$table] = $before[$table] ?? 0; }
    }

    $inserted = 0;
    foreach ($after as $table => $count) $inserted += max(0, $count - ($before[$table] ?? 0));

    return ['inserted'=>$inserted,'updated'=>0,'skipped'=>0,'errors'=>0,'details'=>[
        'sales_lines'=>$after['sales_lines'] ?? 0,
        'sales_geographies'=>$after['sales_geographies'] ?? 0,
        'sales_line_brands'=>$after['sales_line_brands'] ?? 0,
        'sales_visitor_territories'=>$after['sales_visitor_territories'] ?? 0,
    ]];
}];
