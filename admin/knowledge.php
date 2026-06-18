<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/JalaliDate.php';
require_once __DIR__ . '/../core/SobhanApiClient.php';

Auth::requirePermission('manage_knowledge', 'view');

$pageTitle = 'منابع دانش هوش مصنوعی';
$user = Auth::user();
$allowedExtensions = ['txt', 'docx', 'xlsx', 'csv'];
$unsafeExtensions = ['php', 'phtml', 'php3', 'php4', 'php5', 'phar', 'exe', 'bat', 'cmd', 'com', 'scr', 'ps1', 'vbs', 'js', 'jar', 'msi', 'sh'];
$maxUploadMb = max(1, min(100, (int)setting('knowledge_upload_max_mb', '10')));
$maxUploadBytes = $maxUploadMb * 1024 * 1024;
$uploadDirectory = 'uploads/knowledge';

function knowledge_original_basename(string $name): string
{
    $name = str_replace("\0", '', $name);
    $parts = preg_split('/[\/\\\\]+/', $name) ?: [];
    return trim((string)end($parts));
}

function knowledge_detect_mime(string $path): string
{
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mime = finfo_file($finfo, $path) ?: 'application/octet-stream';
            finfo_close($finfo);
            return $mime;
        }
    }

    return function_exists('mime_content_type') ? (mime_content_type($path) ?: 'application/octet-stream') : 'application/octet-stream';
}

function knowledge_validate_upload(array $file, array $allowedExtensions, array $unsafeExtensions, int $maxUploadBytes): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'فایل به درستی ارسال نشد.'];
    }

    if ((int)($file['size'] ?? 0) <= 0 || (int)$file['size'] > $maxUploadBytes) {
        return ['ok' => false, 'error' => 'حجم فایل مجاز نیست.'];
    }

    $originalName = knowledge_original_basename((string)($file['name'] ?? ''));
    $submittedName = trim((string)($file['name'] ?? ''));
    if ($originalName === '' || str_contains($submittedName, '..')) {
        return ['ok' => false, 'error' => 'نام فایل معتبر نیست.'];
    }
    if (str_contains($originalName, '..') || preg_match('/[\x00-\x1F\x7F]/', $originalName)) {
        return ['ok' => false, 'error' => 'نام فایل معتبر نیست.'];
    }

    $parts = explode('.', $originalName);
    $extension = strtolower((string)pathinfo($originalName, PATHINFO_EXTENSION));
    if (!in_array($extension, $allowedExtensions, true)) {
        return ['ok' => false, 'error' => 'پسوند فایل مجاز نیست.'];
    }

    $priorExtensions = array_map('strtolower', array_slice($parts, 0, -1));
    foreach ($priorExtensions as $priorExtension) {
        if (in_array($priorExtension, $unsafeExtensions, true)) {
            return ['ok' => false, 'error' => 'نام فایل شامل پسوند ناامن است.'];
        }
    }

    return [
        'ok' => true,
        'original_name' => $originalName,
        'extension' => $extension,
    ];
}

function knowledge_save_upload(array $file, array $validated, string $uploadDirectory): array
{
    $root = realpath(__DIR__ . '/..') ?: dirname(__DIR__);
    $targetDir = rtrim($root . '/' . trim($uploadDirectory, '/'), '/');
    if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true)) {
        return ['ok' => false, 'error' => 'امکان ساخت پوشه منابع دانش وجود ندارد.'];
    }

    $storedName = bin2hex(random_bytes(16)) . '.' . $validated['extension'];
    $path = $targetDir . '/' . $storedName;
    if (!move_uploaded_file($file['tmp_name'], $path)) {
        return ['ok' => false, 'error' => 'ذخیره فایل ناموفق بود.'];
    }

    @chmod($path, 0644);

    return [
        'ok' => true,
        'original_name' => $validated['original_name'],
        'stored_name' => $storedName,
        'file_path' => '/' . trim($uploadDirectory, '/') . '/' . $storedName,
        'extension' => $validated['extension'],
        'mime_type' => knowledge_detect_mime($path),
        'file_size' => (int)$file['size'],
    ];
}

if (isset($_GET['delete']) && Auth::verifyCsrf($_GET['csrf_token'] ?? '') && Auth::can('manage_knowledge', 'delete')) {
    $document = Database::fetch('SELECT * FROM knowledge_documents WHERE id = ?', [(int)$_GET['delete']]);
    if ($document) {
        $root = realpath(__DIR__ . '/..') ?: dirname(__DIR__);
        $knowledgeRoot = realpath($root . '/uploads/knowledge');
        $path = realpath($root . $document['file_path']);
        if ($path && $knowledgeRoot && strncmp($path, $knowledgeRoot, strlen($knowledgeRoot)) === 0 && is_file($path)) {
            @unlink($path);
        }
        Database::execute('DELETE FROM knowledge_documents WHERE id = ?', [$document['id']]);
        Auth::log((int)($user['id'] ?? 0), 'delete', 'knowledge', (int)$document['id']);
        flash('منبع دانش حذف شد.');
    }
    redirect('/admin/knowledge.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('درخواست نامعتبر است.', 'danger');
        redirect('/admin/knowledge.php');
    }

    $action = $_POST['action'] ?? '';
    if ($action === 'upload') {
        if (!Auth::can('manage_knowledge', 'create')) {
            flash('برای آپلود منبع دانش دسترسی ندارید.', 'danger');
            redirect('/admin/knowledge.php');
        }

        $validated = knowledge_validate_upload($_FILES['file'] ?? [], $allowedExtensions, $unsafeExtensions, $maxUploadBytes);
        if (!$validated['ok']) {
            flash($validated['error'], 'danger');
            redirect('/admin/knowledge.php');
        }

        $upload = knowledge_save_upload($_FILES['file'], $validated, $uploadDirectory);
        if (!$upload['ok']) {
            flash($upload['error'], 'danger');
            redirect('/admin/knowledge.php');
        }

        Database::execute(
            'INSERT INTO knowledge_documents (uploaded_by,original_name,stored_name,file_path,extension,mime_type,file_size,created_at,updated_at) VALUES (?,?,?,?,?,?,?,NOW(),NOW())',
            [(int)($user['id'] ?? 0), $upload['original_name'], $upload['stored_name'], $upload['file_path'], $upload['extension'], $upload['mime_type'], $upload['file_size']]
        );
        Auth::log((int)($user['id'] ?? 0), 'upload', 'knowledge', (int)Database::lastInsertId());
        flash('منبع دانش بارگذاری شد.');
        redirect('/admin/knowledge.php');
    }

    if ($action === 'rebuild_index') {
        if (!Auth::can('manage_knowledge', 'edit')) {
            flash('برای بازسازی ایندکس دسترسی ندارید.', 'danger');
            redirect('/admin/knowledge.php');
        }

        $client = new SobhanApiClient(null, null, 60);
        $result = $client->post('/kb/reindex');
        Auth::log((int)($user['id'] ?? 0), $result['ok'] ? 'rebuild_index' : 'rebuild_index_failed', 'knowledge');
        if ($result['ok'] && (bool)($result['data']['ok'] ?? true)) {
            flash('بازسازی ایندکس منابع دانش انجام شد.');
        } else {
            $message = 'امکان اجرای ایندکس از هاست فعال نیست. باید endpoint ایندکس روی Windows Server API اضافه شود.';
            $errorMessage = (string)($result['error']['message_fa'] ?? '');
            $technical = strtolower((string)($result['error']['technical'] ?? ''));
            if (!$result['ok']) {
                if ((int)($result['status'] ?? 0) === 404) {
                    $message = 'امکان اجرای ایندکس از هاست فعال نیست. باید endpoint ایندکس روی Windows Server API اضافه شود.';
                } elseif (str_contains($errorMessage, 'بیش از حد') || str_contains($technical, 'timed out')) {
                    $message = 'ایندکس دانش سازمانی بیش از حد طول کشید. وضعیت لاگ را بررسی کنید.';
                } elseif (str_contains($errorMessage, 'اتصال') || str_contains($technical, 'couldn')) {
                    $message = 'اتصال به سرویس ویندوز سرور برقرار نشد.';
                }
            } elseif (isset($result['data']['ok']) && !$result['data']['ok']) {
                $message = (string)($result['data']['message_fa'] ?? $result['data']['message'] ?? $message);
            }
            flash($message, 'danger');
        }
        redirect('/admin/knowledge.php');
    }
}

$documents = Database::fetchAll(
    'SELECT d.*, u.name uploaded_by_name
     FROM knowledge_documents d
     LEFT JOIN users u ON u.id = d.uploaded_by
     ORDER BY d.id DESC'
);
require __DIR__ . '/../views/partials/admin-header.php';
?>
<section class="card admin-form">
    <div class="section-heading-row">
        <div>
            <h2>منابع دانش هوش مصنوعی</h2>
            <p class="muted">فایل‌های متنی و اداری مجاز را بارگذاری کنید و سپس ایندکس دانش را بازسازی کنید.</p>
        </div>
        <?php if (Auth::can('manage_knowledge', 'edit')): ?>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= e(Auth::csrfToken()) ?>">
                <button class="btn" name="action" value="rebuild_index">بازسازی ایندکس</button>
            </form>
        <?php endif; ?>
    </div>

    <?php if (Auth::can('manage_knowledge', 'create')): ?>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= e(Auth::csrfToken()) ?>">
            <div class="grid grid-2">
                <label class="form-field">
                    <span>فایل منبع</span>
                    <input type="file" name="file" accept=".txt,.docx,.xlsx,.csv" required>
                    <small>پسوندهای مجاز: txt, docx, xlsx, csv. حداکثر حجم: <?= e((string)$maxUploadMb) ?> MB</small>
                </label>
            </div>
            <button class="btn btn-primary" name="action" value="upload">آپلود منبع</button>
        </form>
    <?php endif; ?>
</section>

<div class="table-wrap">
    <table>
        <thead><tr><th>نام فایل</th><th>نوع</th><th>حجم</th><th>بارگذاری‌کننده</th><th>تاریخ</th><th>عملیات</th></tr></thead>
        <tbody>
        <?php foreach ($documents as $document): ?>
            <tr>
                <td><?= e($document['original_name']) ?></td>
                <td><?= e($document['extension']) ?></td>
                <td><?= e(number_format((int)$document['file_size'] / 1024, 1)) ?> KB</td>
                <td><?= e($document['uploaded_by_name'] ?: '-') ?></td>
                <td><?= e(format_jalali_datetime($document['created_at'])) ?></td>
                <td>
                    <?php if (Auth::can('manage_knowledge', 'delete')): ?>
                        <a class="btn btn-small btn-danger" onclick="return confirm('حذف شود؟')" href="?delete=<?= e($document['id']) ?>&csrf_token=<?= e(Auth::csrfToken()) ?>">حذف</a>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$documents): ?><tr><td colspan="6">هنوز منبعی بارگذاری نشده است.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
</section></main></div><script src="/assets/js/app.js"></script></body></html>
