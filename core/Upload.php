<?php
class Upload
{
    public const FILE_EXTENSIONS = ['jpg','jpeg','png','pdf','doc','docx','xls','xlsx','zip'];
    public const IMAGE_EXTENSIONS = ['jpg','jpeg','png'];
    public const MAX_SIZE = 10485760;

    public static function save(array $file, string $directory, array $allowedExtensions = self::FILE_EXTENSIONS): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => 'فایل به درستی ارسال نشد.'];
        }
        if (($file['size'] ?? 0) > self::MAX_SIZE) {
            return ['ok' => false, 'error' => 'حداکثر حجم مجاز فایل ۱۰ مگابایت است.'];
        }
        $original = $file['name'] ?? 'file';
        $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        if (!in_array($extension, $allowedExtensions, true)) {
            return ['ok' => false, 'error' => 'پسوند فایل مجاز نیست.'];
        }
        $root = realpath(__DIR__ . '/..') ?: dirname(__DIR__);
        $targetDir = rtrim($root . '/' . trim($directory, '/'), '/');
        if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true)) {
            return ['ok' => false, 'error' => 'امکان ساخت پوشه آپلود وجود ندارد.'];
        }
        $stored = bin2hex(random_bytes(16)) . '.' . $extension;
        $path = $targetDir . '/' . $stored;
        if (!move_uploaded_file($file['tmp_name'], $path)) {
            return ['ok' => false, 'error' => 'ذخیره فایل ناموفق بود.'];
        }
        @chmod($path, 0644);
        return [
            'ok' => true,
            'original_name' => $original,
            'stored_name' => $stored,
            'file_path' => '/' . trim($directory, '/') . '/' . $stored,
            'mime_type' => mime_content_type($path) ?: 'application/octet-stream',
            'file_size' => (int)$file['size'],
        ];
    }
}
