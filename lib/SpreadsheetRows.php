<?php
require_once __DIR__.'/../core/CeoDashboardExcel.php';
class SpreadsheetRows
{
    public static function read(string $path,string $extension): array{if($extension==='xlsx'){$sheets=CeoDashboardExcel::read($path);return $sheets?array_values($sheets)[0]:[];}if($extension!=='csv')throw new InvalidArgumentException('فقط فایل CSV یا XLSX مجاز است.');$handle=fopen($path,'rb');if(!$handle)throw new RuntimeException('فایل قابل خواندن نیست.');$rows=[];while(($row=fgetcsv($handle,0,','))!==false){if(isset($row[0]))$row[0]=preg_replace('/^\xEF\xBB\xBF/','',(string)$row[0]);$rows[]=$row;}fclose($handle);return $rows;}
    public static function associative(array $rows): array{if(count($rows)<2)return [];$headers=array_map(static fn($h)=>strtolower(trim((string)$h)),array_shift($rows));$result=[];foreach($rows as $index=>$row){if(!array_filter($row,static fn($v)=>trim((string)$v)!==''))continue;$item=[];foreach($headers as $i=>$header)if($header!=='')$item[$header]=trim((string)($row[$i]??''));$item['_row']=$index+2;$result[]=$item;}return $result;}
    public static function number(mixed $value): float{$value=strtr((string)$value,['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9','٬'=>'','،'=>'','%'=>'']);$value=str_replace([',',' '],'',$value);return is_numeric($value)?(float)$value:0.0;}
}
