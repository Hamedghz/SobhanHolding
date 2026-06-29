<?php
require_once __DIR__.'/../core/Database.php';

class FileBackupService
{
    public static function uploadsRoot(): string
    {
        $root=realpath(__DIR__.'/../uploads');if(!$root)throw new RuntimeException('uploads_not_available');return rtrim($root,DIRECTORY_SEPARATOR);
    }

    public static function normalizeRelativePath(string $path): string
    {
        $path=str_replace('\\','/',trim($path));if(str_starts_with($path,'//')||preg_match('#^[a-zA-Z]:#',$path))throw new InvalidArgumentException('invalid_relative_path');$path=preg_replace('#^/?uploads/#i','',$path)??$path;$path=ltrim($path,'/');
        if($path===''||str_contains($path,"\0")||preg_match('#(^|/)\.\.(/|$)#',$path))throw new InvalidArgumentException('invalid_relative_path');
        $parts=array_values(array_filter(explode('/',$path),static fn($part)=>$part!==''&&$part!=='.'));if(!$parts)throw new InvalidArgumentException('invalid_relative_path');return implode('/',$parts);
    }

    public static function resolveExistingFile(string $relativePath): string
    {
        $relative=self::normalizeRelativePath($relativePath);$root=self::uploadsRoot();$candidate=$root.DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,$relative);$real=realpath($candidate);
        if(!$real||!is_file($real)||is_link($real)||!str_starts_with(strtolower($real),strtolower($root.DIRECTORY_SEPARATOR)))throw new RuntimeException('file_not_available');return $real;
    }

    public static function registerSavedFile(string $filePath,string $originalName='',string $mimeType='application/octet-stream',?int $knownSize=null): int
    {
        $relative=self::normalizeRelativePath($filePath);$full=self::resolveExistingFile($relative);$size=$knownSize??filesize($full);$hash=hash_file('sha256',$full);$originalName=trim($originalName)!==''?basename($originalName):basename($relative);$fileKey=hash('sha256',$relative.'|'.bin2hex(random_bytes(16)));
        $existing=Database::fetch('SELECT * FROM uploaded_files_backup WHERE relative_path=?',[$relative]);
        if($existing){$changed=(int)$existing['file_size']!==(int)$size||!hash_equals((string)($existing['file_hash']??''),(string)$hash)||!empty($existing['deleted_from_host']);if($changed){Database::execute('UPDATE uploaded_files_backup SET file_key=?,original_name=?,file_size=?,file_hash=?,mime_type=?,backup_status="pending",backup_confirmed_at=NULL,deleted_from_host=0,deleted_from_host_at=NULL,last_error=NULL,download_attempts=0,last_attempt_at=NULL,updated_at=NOW() WHERE id=?',[$fileKey,substr($originalName,0,255),$size,$hash,substr($mimeType,0,190),(int)$existing['id']]);self::log((int)$existing['id'],'registered','pending','نسخه جدید یا تغییرکرده فایل برای بکاپ ثبت شد.');}return (int)$existing['id'];}
        Database::execute('INSERT INTO uploaded_files_backup(file_key,original_name,relative_path,file_size,file_hash,mime_type,backup_status,created_at,updated_at) VALUES(?,?,?,?,?,? ,"pending",NOW(),NOW())',[$fileKey,substr($originalName,0,255),$relative,$size,$hash,substr($mimeType,0,190)]);$id=(int)Database::lastInsertId();self::log($id,'registered','pending','فایل برای بکاپ ثبت شد.');return $id;
    }

    public static function scanUploads(): array
    {
        $root=self::uploadsRoot();$registered=0;$unchanged=0;$errors=0;$iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS));
        foreach($iterator as $file){try{if(!$file->isFile()||$file->isLink())continue;$name=$file->getFilename();if(str_starts_with($name,'.')||strtolower($name)==='index.html')continue;$full=$file->getRealPath();if(!$full)continue;$relative=str_replace('\\','/',substr($full,strlen($root)+1));$before=Database::fetch('SELECT id,file_size,file_hash FROM uploaded_files_backup WHERE relative_path=?',[$relative]);self::registerSavedFile($relative,$name,self::detectMime($full),$file->getSize());$before?$unchanged++:$registered++;}catch(Throwable $e){$errors++;error_log('Backup scan: '.$e->getMessage());}}
        self::log(null,'scan',$errors?'partial':'success',"ثبت جدید: {$registered}، موجود: {$unchanged}، خطا: {$errors}",'admin');return compact('registered','unchanged','errors');
    }

    public static function pending(int $limit=100): array
    {
        $limit=max(1,min(500,$limit));return Database::fetchAll("SELECT id,relative_path,file_size,file_hash,original_name,mime_type FROM uploaded_files_backup WHERE backup_status IN ('pending','error') AND deleted_from_host=0 ORDER BY CASE backup_status WHEN 'pending' THEN 0 ELSE 1 END,updated_at,id LIMIT {$limit}");
    }

    public static function markDownloadAttempt(int $id): array
    {
        $row=Database::fetch('SELECT * FROM uploaded_files_backup WHERE id=?',[$id]);if(!$row)throw new RuntimeException('file_not_registered');if((int)$row['deleted_from_host'])throw new RuntimeException('file_deleted_from_host');self::resolveExistingFile($row['relative_path']);Database::execute('UPDATE uploaded_files_backup SET download_attempts=download_attempts+1,last_attempt_at=NOW(),updated_at=NOW() WHERE id=?',[$id]);self::log($id,'download','success','فایل توسط سرویس بکاپ دریافت شد.','api');return $row;
    }

    public static function acknowledge(int $id,?string $confirmedHash=null): void
    {
        $row=Database::fetch('SELECT * FROM uploaded_files_backup WHERE id=?',[$id]);if(!$row)throw new RuntimeException('file_not_registered');if($confirmedHash!==null&&$confirmedHash!==''&&!hash_equals(strtolower((string)$row['file_hash']),strtolower($confirmedHash)))throw new RuntimeException('hash_mismatch');Database::execute('UPDATE uploaded_files_backup SET backup_status="synced",backup_confirmed_at=NOW(),last_error=NULL,updated_at=NOW() WHERE id=?',[$id]);self::log($id,'ack','synced','Windows Server صحت بکاپ را تأیید کرد.','api');
    }

    public static function markError(int $id,string $message): string
    {
        $row=Database::fetch('SELECT id,backup_status FROM uploaded_files_backup WHERE id=?',[$id]);if(!$row)throw new RuntimeException('file_not_registered');$message=mb_substr(trim($message),0,2000);if($message==='')$message='خطای نامشخص در دریافت فایل';if($row['backup_status']==='synced'){self::log($id,'error_ignored','synced','خطای دیرهنگام پس از تأیید بکاپ نادیده گرفته شد: '.$message,'api');return 'synced';}Database::execute('UPDATE uploaded_files_backup SET backup_status="error",last_error=?,updated_at=NOW() WHERE id=?',[$message,$id]);self::log($id,'error','error',$message,'api');return 'error';
    }

    public static function deleteFromHost(int $id,int $adminUserId): void
    {
        $pdo=Database::connection();$pdo->beginTransaction();try{$stmt=$pdo->prepare('SELECT * FROM uploaded_files_backup WHERE id=? FOR UPDATE');$stmt->execute([$id]);$row=$stmt->fetch();if(!$row)throw new InvalidArgumentException('رکورد فایل پیدا نشد.');if($row['backup_status']!=='synced'||empty($row['backup_confirmed_at'])||(int)$row['deleted_from_host'])throw new InvalidArgumentException('این فایل هنوز شرایط حذف از هاست را ندارد.');$path=self::resolveExistingFile($row['relative_path']);if(!unlink($path))throw new RuntimeException('host_delete_failed');Database::execute('UPDATE uploaded_files_backup SET deleted_from_host=1,deleted_from_host_at=NOW(),updated_at=NOW() WHERE id=?',[$id]);self::log($id,'host_delete','success','فقط نسخه روی هاست توسط ادمین حذف شد.','admin',$adminUserId);$pdo->commit();}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }

    public static function apiKeyHash(): string{return (string)(Database::fetch('SELECT setting_value FROM site_settings WHERE setting_key="file_backup_api_key_hash"')['setting_value']??'');}
    public static function allowedIps(): string{return (string)(Database::fetch('SELECT setting_value FROM site_settings WHERE setting_key="file_backup_allowed_ips"')['setting_value']??'');}
    public static function setApiKey(string $plain): void{Database::execute('INSERT INTO site_settings(setting_key,setting_value,setting_type,updated_at) VALUES("file_backup_api_key_hash",?,"password",NOW()) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),setting_type="password",updated_at=NOW()',[hash('sha256',$plain)]);}
    public static function setAllowedIps(string $value): void{Database::execute('INSERT INTO site_settings(setting_key,setting_value,setting_type,updated_at) VALUES("file_backup_allowed_ips",?,"text",NOW()) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),updated_at=NOW()',[$value]);}

    public static function ipAllowed(string $ip,string $rules): bool
    {
        $rules=preg_split('/[\s,;]+/',trim($rules),-1,PREG_SPLIT_NO_EMPTY);if(!$rules)return true;foreach($rules as $rule){if($rule===$ip)return true;if(str_contains($rule,'/')&&self::cidrContains($rule,$ip))return true;}return false;
    }
    public static function validIpRule(string $rule): bool{$parts=explode('/',$rule,2);if(!filter_var($parts[0],FILTER_VALIDATE_IP))return false;if(count($parts)===1)return true;if(!ctype_digit($parts[1]))return false;$max=str_contains($parts[0],':')?128:32;return (int)$parts[1]>=0&&(int)$parts[1]<=$max;}

    public static function log(?int $fileId,string $action,string $status,?string $message=null,string $actorType='system',?int $userId=null): void
    {try{Database::execute('INSERT INTO uploaded_files_backup_logs(file_id,action,status,message,actor_type,actor_user_id,ip_address,created_at) VALUES(?,?,?,?,?,?,?,NOW())',[$fileId,$action,$status,$message,$actorType,$userId,substr((string)($_SERVER['REMOTE_ADDR']??''),0,45)]);}catch(Throwable $e){error_log('File backup log: '.$e->getMessage());}}
    private static function detectMime(string $path): string{$mime='application/octet-stream';if(function_exists('finfo_open')){$finfo=finfo_open(FILEINFO_MIME_TYPE);if($finfo){$mime=finfo_file($finfo,$path)?:$mime;finfo_close($finfo);}}return $mime;}
    private static function cidrContains(string $cidr,string $ip): bool{[$network,$prefix]=array_pad(explode('/',$cidr,2),2,null);$networkBin=@inet_pton($network);$ipBin=@inet_pton($ip);if($networkBin===false||$ipBin===false||strlen($networkBin)!==strlen($ipBin)||!is_numeric($prefix))return false;$bits=(int)$prefix;$max=strlen($networkBin)*8;if($bits<0||$bits>$max)return false;$bytes=intdiv($bits,8);$rest=$bits%8;if(substr($networkBin,0,$bytes)!==substr($ipBin,0,$bytes))return false;if($rest===0)return true;$mask=(0xFF<<(8-$rest))&0xFF;return (ord($networkBin[$bytes])&$mask)===(ord($ipBin[$bytes])&$mask);}
}
