<?php
require_once __DIR__ . '/EmailOAuthService.php';

require_once __DIR__ . '/../core/EmailHubModule.php';

class EmailProtocolException extends RuntimeException {}

class EmailSocket
{
    protected $stream = null;
    protected int $timeout = 20;

    protected function open(string $host,int $port,string $encryption): void
    {
        if(!preg_match('/^[a-z0-9.-]+$/i',$host)||$port<1||$port>65535)throw new EmailProtocolException('invalid_mail_endpoint');
        $remote=($encryption==='ssl'?'ssl://':'tcp://').$host.':'.$port;
        $context=stream_context_create(['ssl'=>['verify_peer'=>true,'verify_peer_name'=>true,'allow_self_signed'=>false,'SNI_enabled'=>true,'peer_name'=>$host]]);
        $errorCode=0;$error='';$this->stream=@stream_socket_client($remote,$errorCode,$error,$this->timeout,STREAM_CLIENT_CONNECT,$context);
        if(!is_resource($this->stream))throw new EmailProtocolException('mail_connect_failed:'.$errorCode.':'.$error);
        stream_set_timeout($this->stream,$this->timeout);
    }
    protected function write(string $data): void { if(!is_resource($this->stream)||fwrite($this->stream,$data)===false)throw new EmailProtocolException('mail_write_failed'); }
    protected function line(): string { $line=is_resource($this->stream)?fgets($this->stream,65536):false;if($line===false)throw new EmailProtocolException('mail_read_failed');return $line; }
    public function close(): void { if(is_resource($this->stream))fclose($this->stream);$this->stream=null; }
    public function __destruct(){ $this->close(); }
}

class EmailSmtpClient extends EmailSocket
{
    private array $account;
    public function __construct(array $account){$this->account=$account;}
    private function response(array $codes): string
    {
        $all='';$code=0;
        do{$line=$this->line();$all.=$line;$code=(int)substr($line,0,3);$more=isset($line[3])&&$line[3]==='-';}while($more);
        if(!in_array($code,$codes,true))throw new EmailProtocolException('smtp_'.$code.':'.trim($all));
        return $all;
    }
    private function command(string $command,array $codes): string{$this->write($command."\r\n");return $this->response($codes);}
    public function connect(): void
    {
        if (($this->account['auth_type'] ?? '') === 'oauth2') $this->account = EmailOAuthService::ensureAccessToken($this->account);
        $this->open((string)$this->account['smtp_host'],(int)$this->account['smtp_port'],(string)$this->account['smtp_encryption']);
        $this->response([220]);$host=preg_replace('/[^a-z0-9.-]/i','',gethostname()?:'localhost')?:'localhost';$this->command('EHLO '.$host,[250]);
        if($this->account['smtp_encryption']==='tls'){$this->command('STARTTLS',[220]);if(!stream_socket_enable_crypto($this->stream,true,STREAM_CRYPTO_METHOD_TLS_CLIENT))throw new EmailProtocolException('smtp_tls_failed');$this->command('EHLO '.$host,[250]);}
        $credentials=EmailHubModule::credentials($this->account);$auth=$this->account['auth_type']??'password';
        if($auth==='oauth2'){$token=(string)($credentials['access_token']??'');if($token==='')throw new EmailProtocolException('oauth_token_missing');$payload=base64_encode('user='.$this->account['username']."\x01auth=Bearer ".$token."\x01\x01");$this->command('AUTH XOAUTH2 '.$payload,[235]);}
        else{$password=(string)($credentials['password']??'');if($password==='')throw new EmailProtocolException('email_password_missing');$this->command('AUTH LOGIN',[334]);$this->command(base64_encode((string)$this->account['username']),[334]);$this->command(base64_encode($password),[235]);}
    }
    public function test(): void{$this->connect();$this->command('NOOP',[250]);$this->command('QUIT',[221]);}
    private static function header(string $value): string{$value=preg_replace('/[\r\n]+/u',' ',$value)??'';return function_exists('mb_encode_mimeheader')?mb_encode_mimeheader($value,'UTF-8','B',"\r\n"):$value;}
    private static function addressList(array $items): array
    {
        $result=[];foreach($items as $item){$email=is_array($item)?trim((string)($item['email']??'')):trim((string)$item);$name=is_array($item)?trim((string)($item['name']??'')):'';if(!filter_var($email,FILTER_VALIDATE_EMAIL))throw new InvalidArgumentException('نشانی ایمیل معتبر نیست: '.$email);$result[]=['email'=>$email,'name'=>$name];}return $result;
    }
    private static function formatAddresses(array $items): string{return implode(', ',array_map(static fn($a)=>$a['name']!==''?self::header($a['name']).' <'.$a['email'].'>':$a['email'],$items));}
    public function send(array $mail,array $attachmentRows=[]): string
    {
        $to=self::addressList($mail['to']??[]);$cc=self::addressList($mail['cc']??[]);$bcc=self::addressList($mail['bcc']??[]);if(!$to)throw new InvalidArgumentException('حداقل یک گیرنده لازم است.');
        $this->connect();$from=(string)$this->account['email_address'];$this->command('MAIL FROM:<'.$from.'>',[250]);foreach(array_merge($to,$cc,$bcc) as $recipient)$this->command('RCPT TO:<'.$recipient['email'].'>',[250,251]);$this->command('DATA',[354]);
        $boundary='sobhan_'.bin2hex(random_bytes(12));$alt='alt_'.bin2hex(random_bytes(12));$domain=str_contains($from,'@')?substr(strrchr($from,'@'),1):'sobhan.local';$domain=preg_replace('/[^a-z0-9.-]/i','',$domain)?:'sobhan.local';$id='<'.bin2hex(random_bytes(12)).'@'.$domain.'>';
        $headers=['Date: '.date(DATE_RFC2822),'Message-ID: '.$id,'From: '.self::formatAddresses([['email'=>$from,'name'=>(string)($this->account['display_name']?:$this->account['account_title'])]]),'To: '.self::formatAddresses($to),'Subject: '.self::header((string)($mail['subject']??'')),'MIME-Version: 1.0'];if($cc)$headers[]='Cc: '.self::formatAddresses($cc);if(!empty($mail['in_reply_to'])){$headers[]='In-Reply-To: '.preg_replace('/[\r\n]/','',(string)$mail['in_reply_to']);$headers[]='References: '.preg_replace('/[\r\n]/','',(string)$mail['in_reply_to']);}
        $bodyText=(string)($mail['body_text']??strip_tags((string)($mail['body_html']??'')));$bodyHtml=EmailHubModule::sanitizeHtml($mail['body_html']??'');$headers[]='Content-Type: multipart/mixed; boundary="'.$boundary.'"';
        $raw=implode("\r\n",$headers)."\r\n\r\n--{$boundary}\r\nContent-Type: multipart/alternative; boundary=\"{$alt}\"\r\n\r\n--{$alt}\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n".chunk_split(base64_encode($bodyText))."\r\n--{$alt}\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n".chunk_split(base64_encode($bodyHtml))."\r\n--{$alt}--\r\n";
        $root=realpath(dirname(__DIR__));$allowed=$root?realpath($root.'/uploads/email'):false;
        foreach($attachmentRows as $attachment){$full=$root?realpath($root.str_replace('/',DIRECTORY_SEPARATOR,(string)$attachment['storage_path'])):false;if(!$full||!$allowed||!str_starts_with(strtolower($full),strtolower($allowed.DIRECTORY_SEPARATOR))||!is_file($full))continue;$name=preg_replace('/[\r\n"\\]/u','_',basename((string)$attachment['file_name']));$mime=preg_replace('/[^a-z0-9.+-\/]/i','',(string)$attachment['mime_type'])?:'application/octet-stream';$raw.="--{$boundary}\r\nContent-Type: {$mime}; name=\"{$name}\"\r\nContent-Disposition: attachment; filename=\"{$name}\"\r\nContent-Transfer-Encoding: base64\r\n\r\n".chunk_split(base64_encode((string)file_get_contents($full)))."\r\n";}
        $raw.="--{$boundary}--\r\n";$raw=preg_replace('/^\./m','..',$raw)??$raw;$this->write($raw."\r\n.\r\n");$this->response([250]);$this->command('QUIT',[221]);return $id;
    }
}

class EmailImapClient extends EmailSocket
{
    private array $account;private int $tag=0;
    public function __construct(array $account){$this->account=$account;}
    private static function quoted(string $value): string{if(preg_match('/[\x00-\x1F\x7F]/',$value))throw new EmailProtocolException('imap_credential_contains_control_character');return '"'.str_replace(['\\','"'],['\\\\','\\"'],$value).'"';}
    public function connect(): void
    {
        if (($this->account['auth_type'] ?? '') === 'oauth2') $this->account = EmailOAuthService::ensureAccessToken($this->account);
        $this->open((string)$this->account['imap_host'],(int)$this->account['imap_port'],(string)$this->account['imap_encryption']);$greeting=$this->line();if(!str_contains($greeting,'OK'))throw new EmailProtocolException('imap_greeting_failed:'.trim($greeting));
        if($this->account['imap_encryption']==='tls'){$this->command('STARTTLS');if(!stream_socket_enable_crypto($this->stream,true,STREAM_CRYPTO_METHOD_TLS_CLIENT))throw new EmailProtocolException('imap_tls_failed');}
        $credentials=EmailHubModule::credentials($this->account);if(($this->account['auth_type']??'')==='oauth2'){$payload=base64_encode('user='.$this->account['username']."\x01auth=Bearer ".($credentials['access_token']??'')."\x01\x01");$this->command('AUTHENTICATE XOAUTH2 '.$payload);}else{$this->command('LOGIN '.self::quoted((string)$this->account['username']).' '.self::quoted((string)($credentials['password']??'')));}
    }
    public function command(string $command): array
    {
        $tag='S'.str_pad((string)(++$this->tag),4,'0',STR_PAD_LEFT);$this->write($tag.' '.$command."\r\n");$lines=[];$literals=[];
        while(true){$line=$this->line();$lines[]=$line;if(preg_match('/\{(\d+)\}\r?\n$/',$line,$m)){ $remaining=(int)$m[1];$data='';while($remaining>0){$chunk=fread($this->stream,$remaining);if($chunk===false||$chunk==='')throw new EmailProtocolException('imap_literal_failed');$data.=$chunk;$remaining-=strlen($chunk);}$literals[]=$data; }if(str_starts_with($line,$tag.' ')){if(!str_contains($line,$tag.' OK'))throw new EmailProtocolException('imap_command_failed:'.trim($line));break;}}
        return ['lines'=>$lines,'literals'=>$literals,'raw'=>implode('',$lines)];
    }
    public function test(): void{$this->connect();$this->command('NOOP');$this->command('LOGOUT');}
    public function folders(): array
    {
        $this->connect();$result=$this->command('LIST "" "*"');$folders=[];foreach($result['lines'] as $line){if(!str_starts_with($line,'* LIST'))continue;if(preg_match('/\* LIST \((.*?)\) "([^"]*)" (.+)\r?\n$/i',$line,$m)){ $remote=trim($m[3]," \t\r\n\"");$path=$remote;if(function_exists('mb_convert_encoding')){$decoded=@mb_convert_encoding($remote,'UTF-8','UTF7-IMAP');if($decoded)$path=$decoded;}$folders[]=['flags'=>$m[1],'delimiter'=>$m[2],'path'=>$path,'remote_path'=>$remote];}}return $folders;
    }
    public function select(string $folder): array{$r=$this->command('SELECT '.self::quoted($folder));$raw=$r['raw'];preg_match('/\* (\d+) EXISTS/i',$raw,$e);preg_match('/UIDVALIDITY (\d+)/i',$raw,$u);return ['exists'=>(int)($e[1]??0),'uid_validity'=>(int)($u[1]??0)];}
    public function searchAfter(int $uid): array{$r=$this->command('UID SEARCH UID '.max(1,$uid+1).':*');foreach($r['lines'] as $line)if(str_starts_with($line,'* SEARCH'))return array_values(array_filter(array_map('intval',preg_split('/\s+/',trim(substr($line,8))))));return [];}
    public function fetch(int $uid): array{$r=$this->command('UID FETCH '.$uid.' (UID FLAGS RFC822)');if(!$r['literals'])throw new EmailProtocolException('imap_message_missing');$raw=end($r['literals']);$flags=implode('',$r['lines']);return ['raw'=>$raw,'flags'=>$flags];}
    public function markSeen(int $uid): void{$this->command('UID STORE '.$uid.' +FLAGS.SILENT (\\Seen)');}
    public function move(int $uid,string $folder): void{try{$this->command('UID MOVE '.$uid.' '.self::quoted($folder));}catch(Throwable $e){$this->command('UID COPY '.$uid.' '.self::quoted($folder));$this->command('UID STORE '.$uid.' +FLAGS.SILENT (\\Deleted)');}}
}

class EmailMimeParser
{
    public static function headers(string $raw): array
    {
        [$head]=preg_split("/\r?\n\r?\n/",$raw,2)+[''];$head=preg_replace("/\r?\n[ \t]+/",' ',$head)??$head;$result=[];foreach(preg_split('/\r?\n/',$head) as $line){$p=strpos($line,':');if($p===false)continue;$key=strtolower(trim(substr($line,0,$p)));$value=trim(substr($line,$p+1));$result[$key]=isset($result[$key])?$result[$key].', '.$value:$value;}return $result;
    }
    public static function decodeHeader(?string $value): string{if(!$value)return '';if(function_exists('mb_decode_mimeheader'))return trim(mb_decode_mimeheader($value));return trim($value);}
    public static function addresses(?string $value): array
    {
        $items=[];foreach(preg_split('/,(?=(?:[^\"]*\"[^\"]*\")*[^\"]*$)/',(string)$value) as $part){if(preg_match('/^(.*?)<([^>]+)>$/',trim($part),$m)){$items[]=['name'=>self::decodeHeader(trim($m[1]," \t\"")),'email'=>strtolower(trim($m[2]))];}elseif(filter_var(trim($part),FILTER_VALIDATE_EMAIL))$items[]=['name'=>'','email'=>strtolower(trim($part))];}return $items;
    }
    private static function bodyParts(string $raw): array
    {
        [$head,$body]=array_pad(preg_split("/\r?\n\r?\n/",$raw,2),2,'');$headers=self::headers($head."\r\n\r\n");$type=$headers['content-type']??'text/plain';
        if(str_starts_with(strtolower($type),'multipart/')&&preg_match('/boundary="?([^";]+)"?/i',$type,$m)){ $parts=[];foreach(preg_split('/--'.preg_quote($m[1],'/').'(?:--)?\r?\n/',$body) as $part){$part=trim($part,"\r\n");if($part!==''&&str_contains($part,':'))$parts=array_merge($parts,self::bodyParts($part));}return $parts; }
        $encoding=strtolower($headers['content-transfer-encoding']??'');if($encoding==='base64')$body=base64_decode(preg_replace('/\s/','',$body)??'',true)?:'';elseif($encoding==='quoted-printable')$body=quoted_printable_decode($body);
        return [['headers'=>$headers,'content_type'=>$type,'body'=>$body]];
    }
    public static function parse(string $raw): array
    {
        $headers=self::headers($raw);$text='';$html='';$attachments=[];foreach(self::bodyParts($raw) as $part){$type=strtolower($part['content_type']);$disposition=strtolower($part['headers']['content-disposition']??'');$name='';if(preg_match('/(?:filename|name)\*?=(?:UTF-8\'\')?"?([^";]+)"?/i',$disposition.';'.$type,$m))$name=rawurldecode(trim($m[1]));$isAttachment=$name!==''||str_contains($disposition,'attachment');if($isAttachment){$attachments[]=['name'=>self::decodeHeader($name?:'attachment.bin'),'mime'=>trim(explode(';',$type)[0]),'data'=>$part['body'],'content_id'=>trim((string)($part['headers']['content-id']??''),'<>'),'inline'=>str_contains($disposition,'inline')];}elseif(str_starts_with($type,'text/html'))$html=$part['body'];elseif(str_starts_with($type,'text/plain'))$text=$part['body'];}
        return ['headers'=>$headers,'text'=>$text,'html'=>$html,'attachments'=>$attachments];
    }
}
