<?php

require_once __DIR__ . '/Config.php';

class EmailCrypto
{
    private static function key(): string
    {
        $configured = trim((string)(getenv('SOBHAN_EMAIL_ENCRYPTION_KEY') ?: ''));
        if ($configured !== '') return hash('sha256', $configured, true);
        $keyFile=dirname(__DIR__).'/config/email.key';
        if(is_file($keyFile)){$fileKey=trim((string)file_get_contents($keyFile));if(strlen($fileKey)>=32)return hash('sha256',$fileKey,true);}
        if(is_writable(dirname($keyFile))){$fileKey=bin2hex(random_bytes(32));if(file_put_contents($keyFile,$fileKey,LOCK_EX)!==false){@chmod($keyFile,0600);return hash('sha256',$fileKey,true);}}
        $db = Config::db();
        $material = implode('|', [(string)($db['host'] ?? ''), (string)($db['name'] ?? ''), (string)($db['user'] ?? ''), (string)($db['pass'] ?? '')]);
        if (trim($material, '|') === '') throw new RuntimeException('email_encryption_key_missing');
        return hash('sha256', $material, true);
    }

    public static function encrypt(?string $plain): ?string
    {
        if ($plain === null || $plain === '') return null;
        if (!function_exists('openssl_encrypt')) throw new RuntimeException('openssl_extension_required');
        $iv = random_bytes(12); $tag = '';
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag, 'sobhan-email-v1');
        if ($cipher === false) throw new RuntimeException('email_encryption_failed');
        return 'v1:' . base64_encode($iv . $tag . $cipher);
    }

    public static function decrypt(?string $encoded): ?string
    {
        if ($encoded === null || $encoded === '') return null;
        if (!str_starts_with($encoded, 'v1:')) throw new RuntimeException('email_cipher_version_invalid');
        $raw = base64_decode(substr($encoded, 3), true);
        if ($raw === false || strlen($raw) < 29) throw new RuntimeException('email_cipher_invalid');
        $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16), 'sobhan-email-v1');
        if ($plain === false) throw new RuntimeException('email_decryption_failed');
        return $plain;
    }
}
