<?php
class Upload
{
    public const FILE_EXTENSIONS = [];
    public const IMAGE_EXTENSIONS = ['jpg','jpeg','png','webp'];

    public static function save(array $file, string $directory, ?array $allowedExtensions = null, ?int $maxSizeBytes = null, bool $registerBackup = true): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => 'فایل به درستی ارسال نشد.'];
        }
        if ($maxSizeBytes !== null && ($file['size'] ?? 0) > $maxSizeBytes) {
            return ['ok' => false, 'error' => 'حجم فایل از سهمیه مجاز شما بیشتر است.'];
        }
        $original = $file['name'] ?? 'file';
        $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        if ($allowedExtensions !== null && !in_array($extension, $allowedExtensions, true)) {
            return ['ok' => false, 'error' => 'پسوند فایل مجاز نیست.'];
        }
        $root = realpath(__DIR__ . '/..') ?: dirname(__DIR__);
        $targetDir = rtrim($root . '/' . trim($directory, '/'), '/');
        if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true)) {
            return ['ok' => false, 'error' => 'امکان ساخت پوشه آپلود وجود ندارد.'];
        }
        $stored = bin2hex(random_bytes(16)) . ($extension !== '' ? '.' . $extension : '');
        $path = $targetDir . '/' . $stored;
        if (!move_uploaded_file($file['tmp_name'], $path)) {
            return ['ok' => false, 'error' => 'ذخیره فایل ناموفق بود.'];
        }
        @chmod($path, 0644);
        $mime = 'application/octet-stream';
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $mime = finfo_file($finfo, $path) ?: $mime;
                finfo_close($finfo);
            }
        } elseif (function_exists('mime_content_type')) {
            $mime = mime_content_type($path) ?: $mime;
        }
        $imageMimes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
        ];
        if ($allowedExtensions !== null && isset($imageMimes[$extension])) {
            $allowedMimes = array_values(array_intersect_key($imageMimes, array_flip($allowedExtensions)));
            if (!in_array($mime, $allowedMimes, true)) {
                @unlink($path);
                return ['ok' => false, 'error' => 'نوع فایل تصویری معتبر نیست.'];
            }
        }
        $result = [
            'ok' => true,
            'original_name' => $original,
            'stored_name' => $stored,
            'file_path' => '/' . trim($directory, '/') . '/' . $stored,
            'mime_type' => $mime,
            'file_size' => (int)$file['size'],
        ];
        if ($registerBackup) {
            try {
                require_once __DIR__ . '/../lib/FileBackupService.php';
                $result['backup_id'] = FileBackupService::registerSavedFile($result['file_path'], $result['original_name'], $result['mime_type'], $result['file_size']);
            } catch (Throwable $e) {
                error_log('Upload backup registration: ' . $e->getMessage());
                $result['backup_id'] = null;
            }
        } else {
            $result['backup_id'] = null;
        }
        return $result;
    }
}
