<?php
$root=dirname(__DIR__);require_once $root.'/core/SalesAggregateImportService.php';
$path='/fixture/sales.xlsx';if(!is_file($path))throw new RuntimeException('XLSX fixture missing.');
$result=SalesAggregateImportService::readUploadedFile(['error'=>UPLOAD_ERR_OK,'tmp_name'=>$path,'size'=>filesize($path),'name'=>'name-must-not-matter.xlsx'],'skip_duplicates',1);
if($result['needs_selection']||$result['summary']['valid_rows']!==1)throw new RuntimeException('XLSX staging failed.');
$batch=Database::fetch('SELECT detected_sheet,detected_table FROM sales_import_batches WHERE id=?',[$result['batch_id']]);
if(($batch['detected_table']??'')!=='tbltajmi'||($batch['detected_sheet']??'')!=='Data')throw new RuntimeException('tbltajmi detection failed.');
$staged=Database::fetch('SELECT raw_json FROM staging_sales_data WHERE import_batch_id=?',[$result['batch_id']]);
if(str_contains((string)($staged['raw_json']??''),'=1+1'))throw new RuntimeException('Formula text must not be executed or imported.');
echo "Sales aggregate XLSX integration: PASS\n";
