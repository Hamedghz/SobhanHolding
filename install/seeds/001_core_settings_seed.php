<?php
return ['seed_key' => 'core_settings', 'run' => static function(PDO $pdo, array $options): array {
    $items = ['company_name'=>['شرکت پخش سبحان','text'],'site_title'=>['شرکت پخش سبحان','text'],'sobhan_api_base_url'=>['','text'],'sobhan_api_key'=>['','password'],'sobhan_api_timeout'=>['10','number'],'sobhan_api_model'=>['qwen2.5:1.5b','text'],'sobhan_api_enabled'=>['0','boolean'],'sobhan_distribution_data_mode'=>['import_file','select'],'sobhan_ai_autofill_enabled'=>['0','boolean'],'sobhan_ai_overwrite_manual_data'=>['0','boolean'],'knowledge_upload_max_mb'=>['10','number']];
    $inserted=0;$skipped=0;$dry=($options['mode']??'safe')==='dry_run';$stmt=$pdo->prepare('INSERT IGNORE INTO site_settings(setting_key,setting_value,setting_type,updated_at) VALUES (?,?,?,NOW())');
    foreach($items as $key=>[$value,$type]){if($pdo->query('SELECT COUNT(*) FROM site_settings WHERE setting_key='.$pdo->quote($key))->fetchColumn()){$skipped++;continue;}if(!$dry){$stmt->execute([$key,$value,$type]);$inserted+=$stmt->rowCount();}else $inserted++;}
    return ['inserted'=>$inserted,'updated'=>0,'skipped'=>$skipped,'errors'=>0,'details'=>['settings'=>$inserted]];
}];
