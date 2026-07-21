<?php
require_once __DIR__ . '/../lib/ImportSettings.php';

class SpreadsheetImportReader
{
    public const MAX_UNCOMPRESSED_SIZE=209715200,MAX_ROWS=150000,SAMPLE_ROWS=500;

    public static function store(array $file,string $folder,bool $allowStoredFile=false):array
    {
        if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)throw new InvalidArgumentException('فایل بارگذاری‌شده معتبر نیست.');
        $tmp=(string)($file['tmp_name']??'');$isUpload=is_uploaded_file($tmp);$valid=$isUpload||(PHP_SAPI==='cli'&&is_file($tmp))||($allowStoredFile&&self::trustedStoredPath($tmp,$folder));if(!$valid)throw new InvalidArgumentException('فایل بارگذاری‌شده معتبر نیست.');
        $size=(int)($file['size']??filesize($tmp));if($size<1||$size>ImportSettings::effectiveUploadBytes())throw new InvalidArgumentException('حجم فایل از سقف مؤثر ورود اطلاعات بیشتر است.');
        $name=mb_substr(basename((string)($file['name']??'data')),0,255);$ext=strtolower(pathinfo($name,PATHINFO_EXTENSION));if(!in_array($ext,ImportSettings::allowedExtensions(),true))throw new InvalidArgumentException('پسوند فایل در تنظیمات ورود اطلاعات مجاز نیست.');
        $mime=(new finfo(FILEINFO_MIME_TYPE))->file($tmp)?:'';$allowed=$ext==='xlsx'?['application/zip','application/x-zip-compressed','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','application/octet-stream']:['text/plain','text/csv','application/csv','application/vnd.ms-excel','application/octet-stream'];if(!in_array($mime,$allowed,true))throw new InvalidArgumentException('نوع محتوای فایل با پسوند آن سازگار نیست.');
        $dir=dirname(__DIR__).DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.$folder;if(!is_dir($dir)&&!mkdir($dir,0750,true)&&!is_dir($dir))throw new RuntimeException('فضای ذخیره امن در دسترس نیست.');
        $stored=bin2hex(random_bytes(20)).'.'.$ext;$path=$dir.DIRECTORY_SEPARATOR.$stored;$moved=$isUpload?move_uploaded_file($tmp,$path):copy($tmp,$path);if(!$moved)throw new RuntimeException('ذخیره امن فایل انجام نشد.');@chmod($path,0640);
        return ['path'=>$path,'stored_name'=>$stored,'extension'=>$ext,'mime'=>$mime,'file_name'=>$name,'file_hash'=>hash_file('sha256',$path),'source_type'=>$ext==='xlsx'?'excel_upload':'csv_upload'];
    }

    private static function trustedStoredPath(string $path,string $folder):bool
    {
        $base=realpath(dirname(__DIR__).DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.$folder);$real=realpath($path);
        return $base!==false&&$real!==false&&str_starts_with($real,$base.DIRECTORY_SEPARATOR)&&is_file($real);
    }

    public static function read(string $path,string $extension):array{return $extension==='csv'?self::csv($path):self::xlsx($path);}
    public static function candidateRows(array $candidate):iterable
    {
        if(empty($candidate['stream'])){foreach($candidate['rows']??[] as $index=>$values)yield ['row_number'=>$index+1,'values'=>$values];return;}
        $stream=$candidate['stream'];$ref=(string)($candidate['ref']??'');$bounds=null;if($ref!==''&&preg_match('/^([A-Z]+)(\d+):([A-Z]+)(\d+)$/i',$ref,$m))$bounds=['c1'=>self::col($m[1]),'r1'=>(int)$m[2],'c2'=>self::col($m[3]),'r2'=>(int)$m[4]];
        foreach(self::streamRows((string)$stream['path'],(string)$stream['entry'],(array)($stream['shared']??[])) as $item){$rowNumber=$item['row_number'];$values=$item['values'];if($bounds){if($rowNumber<$bounds['r1']||$rowNumber>$bounds['r2'])continue;$values=array_slice($values,$bounds['c1'],$bounds['c2']-$bounds['c1']+1);}yield ['row_number'=>$rowNumber,'values'=>$values];}
    }
    public static function resolveStored(string $folder,string $name):string{$name=basename($name);if($name==='')throw new InvalidArgumentException('فایل Batch در دسترس نیست.');$path=dirname(__DIR__).DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.$folder.DIRECTORY_SEPARATOR.$name;if(!is_file($path))throw new InvalidArgumentException('فایل Batch در دسترس نیست.');return $path;}

    private static function csv(string $path):array
    {
        $sample=file_get_contents($path,false,null,0,4096);$utf8=$sample===false?false:preg_replace('/^\xEF\xBB\xBF/','',$sample);if($utf8===false||!mb_check_encoding($utf8,'UTF-8'))throw new InvalidArgumentException('فایل CSV باید با UTF-8 ذخیره شده باشد.');
        $first=strtok($sample,"\r\n")?:'';$ds=[','=>substr_count($first,','),';'=>substr_count($first,';'),"\t"=>substr_count($first,"\t")];arsort($ds);$delimiter=(string)array_key_first($ds);$h=fopen($path,'rb');if(!$h)throw new RuntimeException('فایل CSV قابل خواندن نیست.');$prefix=fread($h,3);if($prefix!=="\xEF\xBB\xBF")rewind($h);$rows=[];while(($row=fgetcsv($h,0,$delimiter,'"','\\'))!==false){if(count($rows)>self::MAX_ROWS){fclose($h);throw new InvalidArgumentException('تعداد ردیف‌های فایل بیش از حد مجاز است.');}$rows[]=$row;}fclose($h);return ['sheets'=>[['name'=>'CSV','visible'=>true,'rows'=>$rows,'tables'=>[]]]];
    }

    private static function xlsx(string $path):array
    {
        if(!class_exists('ZipArchive')||!class_exists('XMLReader')||!function_exists('simplexml_load_string'))throw new RuntimeException('افزونه‌های ZipArchive، XMLReader و SimpleXML برای XLSX لازم هستند.');$z=new ZipArchive();if($z->open($path)!==true)throw new InvalidArgumentException('فایل XLSX قابل خواندن نیست.');$total=0;for($i=0;$i<$z->numFiles;$i++){$s=$z->statIndex($i);$total+=(int)($s['size']??0);if($total>self::MAX_UNCOMPRESSED_SIZE||$z->numFiles>2000){$z->close();throw new InvalidArgumentException('ساختار فایل XLSX بیش از حد مجاز است.');}}
        $shared=self::shared($z);$wb=self::xml($z,'xl/workbook.xml');$rels=self::rels(self::xml($z,'xl/_rels/workbook.xml.rels'));$main=$wb->children('http://schemas.openxmlformats.org/spreadsheetml/2006/main');$sheets=[];
        foreach($main->sheets->sheet as $sn){$a=$sn->attributes();$ra=$sn->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');$target=$rels[(string)$ra['id']]??'';if($target==='')continue;$sp=self::resolve('xl/workbook.xml',$target);$sheet=self::sheet($path,$sp,$shared);$rows=$sheet['rows'];$tables=[];$srp=dirname($sp).'/_rels/'.basename($sp).'.rels';$sr=$z->locateName($srp)!==false?self::rels(self::xml($z,$srp)):[];foreach($sheet['table_relationship_ids'] as $relationshipId){$tt=$sr[$relationshipId]??'';if($tt==='')continue;$tx=self::xml($z,self::resolve($sp,$tt));$ta=$tx->attributes();$tables[]=['name'=>(string)($ta['displayName']??$ta['name']??''),'ref'=>(string)($ta['ref']??'')];}$sheets[]=['name'=>(string)$a['name'],'visible'=>((string)($a['state']??'visible'))==='visible','rows'=>$rows,'tables'=>$tables,'stream'=>$sheet['stream'],'populated_rows'=>$sheet['populated_rows']];}
        $z->close();return ['sheets'=>$sheets];
    }
    private static function sheet(string $path,string $entry,array $shared):array
    {
        $real=realpath($path);if($real===false)throw new InvalidArgumentException('فایل XLSX در دسترس نیست.');$uri='zip://'.str_replace('\\','/',$real).'#'.$entry;$reader=new XMLReader();if(!$reader->open($uri,null,LIBXML_NONET|LIBXML_COMPACT))throw new InvalidArgumentException('ساختار worksheet فایل XLSX معتبر نیست.');$rows=[];$tableIds=[];$populated=0;
        try{while($reader->read()){if($reader->nodeType!==XMLReader::ELEMENT)continue;if($reader->localName==='row'&&$reader->namespaceURI==='http://schemas.openxmlformats.org/spreadsheetml/2006/main'){$line=self::readRow($reader,$shared);if($line!==null){$populated++;if($populated<=self::SAMPLE_ROWS)$rows[]=$line;}if($populated>self::MAX_ROWS+1)throw new InvalidArgumentException('تعداد ردیف‌های فایل بیش از حد مجاز است.');}elseif($reader->localName==='tablePart'){$id=(string)$reader->getAttributeNs('id','http://schemas.openxmlformats.org/officeDocument/2006/relationships');if($id!=='')$tableIds[]=$id;}}}finally{$reader->close();}
        return ['rows'=>$rows,'populated_rows'=>$populated,'table_relationship_ids'=>array_values(array_unique($tableIds)),'stream'=>['path'=>$real,'entry'=>$entry,'shared'=>$shared]];
    }
    private static function streamRows(string $path,string $entry,array $shared):iterable
    {
        $uri='zip://'.str_replace('\\','/',$path).'#'.$entry;$reader=new XMLReader();if(!$reader->open($uri,null,LIBXML_NONET|LIBXML_COMPACT))throw new InvalidArgumentException('ساختار worksheet فایل XLSX معتبر نیست.');$count=0;
        try{while($reader->read()){if($reader->nodeType!==XMLReader::ELEMENT||$reader->localName!=='row'||$reader->namespaceURI!=='http://schemas.openxmlformats.org/spreadsheetml/2006/main')continue;$rowNumber=max(1,(int)$reader->getAttribute('r'));$line=self::readRow($reader,$shared);if($line===null)continue;$count++;if($count>self::MAX_ROWS+1)throw new InvalidArgumentException('تعداد ردیف‌های فایل بیش از حد مجاز است.');yield ['row_number'=>$rowNumber,'values'=>$line];} }finally{$reader->close();}
    }
    private static function readRow(XMLReader $reader,array $shared):?array
    {
        $rowDepth=$reader->depth;$values=[];while($reader->read()){if($reader->nodeType===XMLReader::END_ELEMENT&&$reader->localName==='row'&&$reader->depth===$rowDepth)break;if($reader->nodeType!==XMLReader::ELEMENT||$reader->localName!=='c')continue;$cellDepth=$reader->depth;$index=self::col(preg_replace('/\d+/','',(string)$reader->getAttribute('r')));$type=(string)($reader->getAttribute('t')??'');$raw='';$hasValue=false;while($reader->read()){if($reader->nodeType===XMLReader::END_ELEMENT&&$reader->localName==='c'&&$reader->depth===$cellDepth)break;if($reader->nodeType===XMLReader::ELEMENT&&in_array($reader->localName,['v','t'],true)){$raw.=$reader->readString();$hasValue=$raw!=='';}}if(!$hasValue)continue;$value=$type==='s'?($shared[(int)$raw]??''):($type==='b'?($raw==='1'?'1':'0'):$raw);$values[$index]=$value;}
        if(!$values)return null;$max=max(array_keys($values));$line=[];for($i=0;$i<=$max;$i++)$line[]=$values[$i]??'';return$line;
    }
    private static function rows(SimpleXMLElement $m,array $shared):array{$out=[];foreach($m->sheetData->row as $row){$v=[];foreach($row->children('http://schemas.openxmlformats.org/spreadsheetml/2006/main')->c as $c){$a=$c->attributes();$cm=$c->children('http://schemas.openxmlformats.org/spreadsheetml/2006/main');$i=self::col(preg_replace('/\d+/','',(string)$a['r']));$t=(string)($a['t']??'');if($t==='inlineStr'){$x='';foreach($cm->is->xpath('.//*[local-name()="t"]')?:[] as $n)$x.=(string)$n;}elseif($t==='s')$x=$shared[(int)$cm->v]??'';elseif($t==='b')$x=((string)$cm->v)==='1'?'1':'0';else$x=(string)$cm->v;$v[$i]=$x;}if($v){$max=max(array_keys($v));$line=[];for($i=0;$i<=$max;$i++)$line[]=$v[$i]??'';$out[]=$line;}if(count($out)>self::MAX_ROWS+1)throw new InvalidArgumentException('تعداد ردیف‌های فایل بیش از حد مجاز است.');}return$out;}
    private static function shared(ZipArchive $z):array{if($z->locateName('xl/sharedStrings.xml')===false)return[];$x=self::xml($z,'xl/sharedStrings.xml');$m=$x->children('http://schemas.openxmlformats.org/spreadsheetml/2006/main');$out=[];foreach($m->si as $si){$t='';foreach($si->xpath('.//*[local-name()="t"]')?:[] as $n)$t.=(string)$n;$out[]=$t;}return$out;}
    private static function xml(ZipArchive $z,string $n):SimpleXMLElement{$c=$z->getFromName($n);if($c===false)throw new InvalidArgumentException('ساختار داخلی XLSX ناقص است.');libxml_use_internal_errors(true);$x=simplexml_load_string($c,SimpleXMLElement::class,LIBXML_NONET);libxml_clear_errors();if(!$x)throw new InvalidArgumentException('ساختار XML فایل XLSX معتبر نیست.');return$x;}
    private static function rels(SimpleXMLElement $x):array{$out=[];foreach($x->children('http://schemas.openxmlformats.org/package/2006/relationships')->Relationship as $r){$a=$r->attributes();$out[(string)$a['Id']]=(string)$a['Target'];}return$out;}
    private static function resolve(string $base,string $target):string{if(str_starts_with($target,'/'))return ltrim($target,'/');$out=[];foreach(explode('/',str_replace('\\','/',dirname($base).'/'.$target))as$p){if($p===''||$p==='.')continue;if($p==='..'){array_pop($out);continue;}$out[]=$p;}return implode('/',$out);}
    public static function crop(array $rows,string $ref):array{if(!preg_match('/^([A-Z]+)(\d+):([A-Z]+)(\d+)$/i',$ref,$m))return$rows;$c1=self::col($m[1]);$r1=(int)$m[2]-1;$c2=self::col($m[3]);$r2=(int)$m[4]-1;$out=[];for($r=max(0,$r1);$r<=min($r2,count($rows)-1);$r++)$out[]=array_slice($rows[$r]??[],$c1,$c2-$c1+1);return$out;}
    private static function col(string $s):int{$i=0;foreach(str_split(strtoupper($s))as$c)$i=$i*26+(ord($c)-64);return max(0,$i-1);}
}
