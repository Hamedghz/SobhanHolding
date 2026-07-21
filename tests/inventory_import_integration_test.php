<?php
$root=dirname(__DIR__);
require_once $root.'/core/InventoryImportService.php';
require_once $root.'/tests/support/import_integration_bootstrap.php';
require_once $root.'/tests/support/xlsx_fixture.php';
$pdo=importIntegrationPdo($root);
seedImportIntegrationMappings($pdo);
$headers=['کد کالا','نام کالا','کد شاخص','کد تولیدکننده','موجودی دوره کل','موجودی فعلی کل','تعداد در کارتن','برند','کد گروه','نام گروه','قیمت مصرف کننده خرده'];
$rows=[['1100011','کالای موجودی','4111286','400015','139','140','1','متفرقه','70','متفرقه','250000']];
$path=tempnam(sys_get_temp_dir(),'inventory-xlsx-').'.xlsx';
createXlsxFixture($path,'Data','tblanbar',$headers,$rows);
$r=InventoryImportService::readUploadedFile(['error'=>UPLOAD_ERR_OK,'tmp_name'=>$path,'size'=>filesize($path),'name'=>'ignored.xlsx'],'skip_duplicates',1);if($r['needs_selection']||$r['summary']['valid_rows']!==1||$r['summary']['ready_rows']!==1)throw new RuntimeException('Real tblanbar staging failed: '.json_encode($r,JSON_UNESCAPED_UNICODE));$b=Database::fetch('SELECT detected_table,detected_sheet FROM sales_import_batches WHERE id=?',[$r['batch_id']]);if(($b['detected_table']??'')!=='tblanbar')throw new RuntimeException('Real table detection failed.');$commit=InventoryImportService::commitValidRows($r['batch_id'],1,true);if($commit['inserted']!==1)throw new RuntimeException('Inventory commit failed.');$row=Database::fetch('SELECT product_code,index_code,current_period_total_qty,current_total_stock,raw_json FROM inventory_aggregate_rows LIMIT 1');$raw=json_decode($row['raw_json']??'',true);if((string)$row['product_code']!=='1100011'||(float)$row['current_period_total_qty']!==139.0||!array_key_exists('قیمت مصرف کننده خرده',$raw))throw new RuntimeException('Inventory values/raw JSON failed.');
$r2=InventoryImportService::readUploadedFile(['error'=>UPLOAD_ERR_OK,'tmp_name'=>$path,'size'=>filesize($path),'name'=>'another.xlsx'],'skip_duplicates',1);$c2=InventoryImportService::commitValidRows($r2['batch_id'],1,true);if($r2['summary']['duplicate_rows']!==1||$c2['skipped']!==1)throw new RuntimeException('Inventory duplicate skip failed.');
@unlink($path);
function invCsv(array $values):string{$p=tempnam(sys_get_temp_dir(),'inventory-').'.csv';$h=fopen($p,'wb');fwrite($h,"\xEF\xBB\xBF");fputcsv($h,['کد کالا','نام کالا','کد شاخص','کد تولیدکننده','موجودی دوره کل','موجودی فعلی کل','تعداد در کارتن','برند','کد گروه','نام گروه'],',','"','\\');fputcsv($h,$values,',','"','\\');fclose($h);return$p;}
$p=invCsv(['1100011','Updated',4111286,'400015',150,151,1,'متفرقه','70','متفرقه']);$up=InventoryImportService::readUploadedFile(['error'=>UPLOAD_ERR_OK,'tmp_name'=>$p,'size'=>filesize($p),'name'=>'update.csv'],'update_existing',1);@unlink($p);$cu=InventoryImportService::commitValidRows($up['batch_id'],1,true);if($cu['updated']!==1)throw new RuntimeException('Inventory update failed.');
$p=invCsv(['NEW-1','New product','IDX','M',1,1,1,'Brand','G','Group']);$cancel=InventoryImportService::readUploadedFile(['error'=>UPLOAD_ERR_OK,'tmp_name'=>$p,'size'=>filesize($p),'name'=>'cancel.csv'],'skip_duplicates',1);@unlink($p);InventoryImportService::rollbackBatch($cancel['batch_id'],1,true);$cb=Database::fetch('SELECT status FROM sales_import_batches WHERE id=?',[$cancel['batch_id']]);if(($cb['status']??'')!=='cancelled')throw new RuntimeException('Inventory rollback failed.');
echo "Inventory import integration: PASS\n";
