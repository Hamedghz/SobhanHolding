<?php
$root=dirname(__DIR__);
$required=['core/SalesDataNormalizer.php','core/SalesAggregateRepository.php','core/SalesAggregateImportService.php','admin/sales-aggregate-import.php','install/sales_aggregate_mapping_seed.php','docs/sales-aggregate-import.md','storage/sales-imports/.htaccess'];
foreach($required as $file)if(!is_file($root.'/'.$file))throw new RuntimeException('Missing file: '.$file);
$serviceSource=file_get_contents($root.'/core/SalesAggregateImportService.php');
if(preg_match('/\b(DROP|TRUNCATE|RENAME\s+TABLE|eval\s*\()\b/i',$serviceSource))throw new RuntimeException('Unsafe operation found.');
foreach(['readUploadedFile','detectWorkbookSource','normalizeHeaders','mapColumns','normalizePersianArabicDigits','normalizeDate','normalizeDecimal','validateRow','buildSourceUniqueKey','detectDuplicate','storeToStaging','generateImportSummary','commitValidRows','rollbackBatch'] as $method)if(!str_contains($serviceSource,'function '.$method))throw new RuntimeException('Missing method: '.$method);
foreach(['is_uploaded_file','FILEINFO_MIME_TYPE','move_uploaded_file','awaiting_source_selection','staging_sales_data','beginTransaction','verifyCsrf'] as $token){$sources=$serviceSource.file_get_contents($root.'/admin/sales-aggregate-import.php');if(!str_contains($sources,$token))throw new RuntimeException('Missing security/import contract: '.$token);}
require_once $root.'/core/SalesAggregateImportService.php';
if(SalesAggregateImportService::normalizePersianArabicDigits('۱۲٣')!=='123')throw new RuntimeException('Digit normalization failed.');
if(SalesAggregateImportService::normalizeDecimal('۱٬۲۳۴.۵۰')!=='1234.5')throw new RuntimeException('Decimal normalization failed.');
if(SalesAggregateImportService::normalizeDate('1403/03/01')!=='2024-05-21')throw new RuntimeException('Jalali normalization failed.');
$key=SalesAggregateImportService::buildSourceUniqueKey(['unique_code'=>'ABC-1']);if($key!==sha1('sales_aggregate|ABC-1'))throw new RuntimeException('Unique-code key failed.');
$fallback=SalesAggregateImportService::buildSourceUniqueKey(['invoice_number'=>'1','invoice_type'=>'sale','sub_invoice_number'=>'2','product_code'=>'P','customer_code'=>'C','visitor_code'=>'V','invoice_date_raw'=>'1403/03/01']);if($fallback!==sha1('sales_aggregate|1|sale|2|P|C|V|1403/03/01'))throw new RuntimeException('SHA1 fallback failed.');
$headers=array_keys(SalesDataNormalizer::REQUIRED);$rows=[$headers,array_fill(0,count($headers),'x')];
$workbook=['sheets'=>[
 ['name'=>'تجمیعی','visible'=>true,'rows'=>$rows,'tables'=>[]],
 ['name'=>'Other','visible'=>true,'rows'=>$rows,'tables'=>[['name'=>'tbltajmi','ref'=>'A1:L2']]],
]];
$candidates=SalesAggregateImportService::detectWorkbookSource($workbook);if(count($candidates)!==1||$candidates[0]['detection']!=='table')throw new RuntimeException('Table detection priority failed.');
$negative=['invoice_number'=>'1','invoice_date_raw'=>'1403/03/01','invoice_date'=>'2024-05-21','visitor_code'=>'V','visitor_name'=>'Visitor','customer_code'=>'C','customer_name'=>'Customer','product_code'=>'P','product_name'=>'Product','quantity'=>'-1','gross_amount'=>'100','discount_amount'=>'0','net_amount'=>'100','invoice_type'=>'فروش'];
if(!array_filter(SalesAggregateImportService::validateRow($negative),fn($e)=>$e['code']==='negative_non_return'))throw new RuntimeException('Negative validation failed.');
if(count(SalesDataNormalizer::mappingDefinitions())<100)throw new RuntimeException('Known mapping set is incomplete.');
echo "Sales aggregate import contract: PASS\n";
