<?php
require_once __DIR__.'/../../core/SalesOperationsModule.php';
return ['seed_key'=>'sales_operations','run'=>static function(PDO $pdo,array $options):array{
    if(($options['mode']??'safe')==='dry_run')return ['inserted'=>0,'updated'=>0,'skipped'=>1,'errors'=>0,'details'=>['repair'=>'dry_run']];
    SalesOperationsModule::repair($pdo);
    return ['inserted'=>0,'updated'=>0,'skipped'=>0,'errors'=>0,'details'=>['repair'=>'completed']];
}];
