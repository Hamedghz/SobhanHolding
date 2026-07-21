<?php
$root=dirname(__DIR__);
require_once $root.'/core/SalesAggregateImportService.php';
require_once $root.'/tests/support/import_integration_bootstrap.php';
$pdo=importIntegrationPdo($root);
require_once $root.'/core/SalesReferenceSchema.php';
SalesReferenceSchema::repair($pdo);
seedImportIntegrationMappings($pdo);

$headers=['نوع فاکتور','شماره فاکتور','تاریخ','کد فروشنده','نام فروشنده','کد مشتری','نام مشتری','کد کالا','نام کالا','تعداد کل','مبلغ ناخالص','مجموع مبلغ تخفیف سطری','مبلغ خالص','کد یکتا','ستون آینده','تعداد در کارتن'];
$rows=[
    ['فروش','1001','1403/03/01','V01','فروشنده یک','C01','مشتری یک','P01','کالای یک','۱٬۲۰۰','2,000,000','100,000','1,900,000','U-1001','future-a',''],
    ['برگشت','1002','2024-05-22','V02','فروشنده دو','C02','مشتری دو','P02','کالای دو','-2','-500000','0','-500000','U-1002','future-b',''],
];
$mapped=SalesAggregateImportService::mapColumns($headers);if(!isset($mapped['columns'][0])||$mapped['columns'][0]['normalized_key']!=='invoice_type')throw new RuntimeException('Invoice type mapping missing: '.json_encode($mapped,JSON_UNESCAPED_UNICODE));

function fixture(array $headers,array $rows):string{
    $path=tempnam(sys_get_temp_dir(),'sales-aggregate-').'.csv';$h=fopen($path,'wb');fwrite($h,"\xEF\xBB\xBF");fputcsv($h,$headers,',','"','\\');foreach($rows as $row)fputcsv($h,$row,',','"','\\');fclose($h);return $path;
}
function upload(string $path,string $mode,int $actor=1):array{return SalesAggregateImportService::readUploadedFile(['error'=>UPLOAD_ERR_OK,'tmp_name'=>$path,'size'=>filesize($path),'name'=>'arbitrary-name.csv'],$mode,$actor);}

$path=fixture($headers,$rows);$reader=new ReflectionMethod(SalesAggregateImportService::class,'readCsv');$book=$reader->invoke(null,$path);$csvMap=SalesAggregateImportService::mapColumns($book['sheets'][0]['rows'][0]);if(!isset($csvMap['columns'][0]))throw new RuntimeException('CSV first header mapping missing: '.bin2hex((string)$book['sheets'][0]['rows'][0][0]));$first=upload($path,'skip_duplicates');@unlink($path);
if($first['needs_selection']||$first['summary']['valid_rows']!==2||$first['summary']['ready_rows']!==2){$errors=Database::fetchAll('SELECT `row_number`,error_code,error_message,normalized_json FROM sales_import_errors WHERE import_batch_id=?',[$first['batch_id']]);throw new RuntimeException('Initial staging failed: '.json_encode(['result'=>$first,'errors'=>$errors],JSON_UNESCAPED_UNICODE));}
$commit=SalesAggregateImportService::commitValidRows($first['batch_id'],1,true);if($commit!==['imported'=>2,'updated'=>0,'skipped'=>0])throw new RuntimeException('Initial commit failed.');
$sourceKeyU1001=sha1('sales_aggregate|U-1001');$saved=Database::fetch('SELECT raw_json,quantity,invoice_date,carton_size FROM sales_aggregate_rows WHERE source_unique_key=?',[$sourceKeyU1001]);$raw=json_decode($saved['raw_json']??'',true);if(($raw['ستون آینده']??'')!=='future-a'||(float)$saved['quantity']!==1200.0||$saved['invoice_date']!=='2024-05-21'||$saved['carton_size']!==null)throw new RuntimeException('Normalization/raw/null preservation failed.');

$path=fixture($headers,$rows);$skip=upload($path,'skip_duplicates');@unlink($path);if($skip['summary']['duplicate_rows']!==2||$skip['summary']['ready_rows']!==0)throw new RuntimeException('Skip duplicate preview failed.');$commit=SalesAggregateImportService::commitValidRows($skip['batch_id'],1,true);if($commit['skipped']!==2)throw new RuntimeException('Skip duplicate commit failed.');

$updated=$rows;$updated[0][12]='2,100,000';$path=fixture($headers,$updated);$update=upload($path,'update_existing');@unlink($path);$commit=SalesAggregateImportService::commitValidRows($update['batch_id'],1,true);if($commit['updated']!==2)throw new RuntimeException('Update duplicate failed.');$net=Database::fetch('SELECT net_amount FROM sales_aggregate_rows WHERE source_unique_key=?',[$sourceKeyU1001]);if((float)$net['net_amount']!==2100000.0)throw new RuntimeException('Updated value missing.');

$path=fixture($headers,$rows);$fail=upload($path,'fail_on_duplicate');@unlink($path);$failed=false;try{SalesAggregateImportService::commitValidRows($fail['batch_id'],1,true);}catch(DomainException $e){$failed=true;}if(!$failed)throw new RuntimeException('Fail-on-duplicate did not block commit.');

$newRows=[$rows[0]];$newRows[0][1]='2001';$newRows[0][13]='U-2001';$path=fixture($headers,$newRows);$cancel=upload($path,'skip_duplicates');@unlink($path);SalesAggregateImportService::rollbackBatch($cancel['batch_id'],1,true);$batch=Database::fetch('SELECT status FROM sales_import_batches WHERE id=?',[$cancel['batch_id']]);if(($batch['status']??'')!=='cancelled'||SalesAggregateRepository::sourceKeyExists(sha1('sales_aggregate|U-2001')))throw new RuntimeException('Batch rollback failed.');

echo "Sales aggregate import integration: PASS\n";
