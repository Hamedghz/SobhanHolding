<?php
require_once __DIR__ . '/MessengerService.php';

final class MessengerFileService
{
    public static function upload(int $conversationId,array $file,array $actor): array
    {
        MessengerSecurity::requirePermission('messenger.files.upload','create');MessengerSecurity::assertSend($conversationId,(int)$actor['id']);MessengerSecurity::rate('upload:'.$actor['id'],20,60);
        if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||!is_uploaded_file($file['tmp_name']??''))throw new InvalidArgumentException('فایل بارگذاری‌شده معتبر نیست.');
        $max=max(1,(int)MessengerService::setting('messenger.max_file_mb','25'))*1024*1024;$size=(int)($file['size']??0);if($size<1||$size>$max)throw new InvalidArgumentException('حجم فایل از حد مجاز بیشتر است.');
        $finfo=new finfo(FILEINFO_MIME_TYPE);$mime=(string)$finfo->file($file['tmp_name']);$allowed=array_filter(array_map('trim',explode(',',MessengerService::setting('messenger.allowed_mimes','image/jpeg,image/png,image/webp,application/pdf,text/plain'))));if(!in_array($mime,$allowed,true))throw new InvalidArgumentException('نوع فایل مجاز نیست.');
        $ext=self::extension($mime);$uuid=MessengerSecurity::uuid();$relative=date('Y/m').'/'.$uuid.'.'.$ext;$root=dirname(__DIR__,2).'/storage/private/messenger';$target=$root.'/'.$relative;if(!is_dir(dirname($target))&&!mkdir(dirname($target),0750,true)&&!is_dir(dirname($target)))throw new RuntimeException('ساخت فضای امن فایل انجام نشد.');
        if(!move_uploaded_file($file['tmp_name'],$target))throw new RuntimeException('ذخیره فایل انجام نشد.');@chmod($target,0640);$hash=hash_file('sha256',$target);$width=null;$height=null;if(str_starts_with($mime,'image/')){$dim=@getimagesize($target);if(is_array($dim)){[$width,$height]=$dim;}}
        try{Database::execute('INSERT INTO chat_files(uuid,file_uuid,conversation_id,uploaded_by,original_name,stored_name,storage_path,mime_type,extension,file_size,file_hash,width,height,scan_status,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,"clean",NOW(),NOW())',[$uuid,$uuid,$conversationId,(int)$actor['id'],MessengerSecurity::text($file['name']??'file',255),basename($relative),$relative,$mime,$ext,$size,$hash,$width,$height]);$id=(int)Database::lastInsertId();MessengerService::audit((int)$actor['id'],'file_uploaded',$conversationId,null,['file_id'=>$id,'size'=>$size,'mime'=>$mime]);return ['id'=>$id,'uuid'=>$uuid,'name'=>MessengerSecurity::text($file['name']??'file',255),'mime_type'=>$mime,'file_size'=>$size,'width'=>$width,'height'=>$height];}catch(Throwable $e){@unlink($target);throw $e;}
    }
    public static function download(int $id,array $actor): array
    {
        MessengerSecurity::requirePermission('messenger.files.download');$row=Database::fetch('SELECT * FROM chat_files WHERE id=? AND deleted_at IS NULL AND scan_status="clean"',[$id]);if(!$row)throw new DomainException('فایل پیدا نشد.',404);MessengerSecurity::participant((int)$row['conversation_id'],(int)$actor['id']);$root=realpath(dirname(__DIR__,2).'/storage/private/messenger');$path=realpath(dirname(__DIR__,2).'/storage/private/messenger/'.str_replace('\\','/',trim($row['storage_path'],'/')));if(!$root||!$path||!is_file($path)||is_link($path)||!str_starts_with(strtolower($path),strtolower($root.DIRECTORY_SEPARATOR))||!hash_equals($row['file_hash'],hash_file('sha256',$path)))throw new DomainException('فایل امن در دسترس نیست.',404);MessengerService::audit((int)$actor['id'],'file_downloaded',(int)$row['conversation_id'],(int)($row['message_id']??0),['file_id'=>$id]);return ['path'=>$path,'name'=>$row['original_name'],'mime'=>$row['mime_type'],'size'=>(int)$row['file_size']];
    }
    private static function extension(string $mime): string{return ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','video/mp4'=>'mp4','video/webm'=>'webm','audio/mpeg'=>'mp3','audio/ogg'=>'ogg','audio/webm'=>'webm','application/pdf'=>'pdf','text/plain'=>'txt','application/zip'=>'zip'][$mime]??'bin';}
}
