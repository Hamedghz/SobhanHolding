<?php
require_once __DIR__ . '/core/Auth.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Response.php';
require_once __DIR__ . '/core/Upload.php';

$pageTitle = 'ثبت وصول حساب';
$message = '';
$status = '';
$roles = array_column(Database::fetchAll('SELECT title FROM accounting_roles WHERE status = "active" ORDER BY sort_order ASC, title ASC'), 'title');
$cities = array_column(Database::fetchAll('SELECT title FROM accounting_cities WHERE status = "active" ORDER BY sort_order ASC, title ASC'), 'title');
if (!$roles) $roles = ['موزع', 'تحصیلدار', 'ویزیتور'];
if (!$cities) $cities = ['تهران'];
$formData = [
    'collector_role' => '',
    'full_name' => '',
    'invoice_number' => '',
    'description' => '',
    'city' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
        $status = 'error';
        $message = 'درخواست نامعتبر است. لطفاً دوباره تلاش کنید.';
    } else {
        foreach ($formData as $key => $value) {
            $formData[$key] = trim((string)($_POST[$key] ?? ''));
        }

        if (!in_array($formData['collector_role'], $roles, true)) {
            $status = 'error';
            $message = 'لطفاً نقش را انتخاب کنید.';
        } elseif ($formData['full_name'] === '') {
            $status = 'error';
            $message = 'لطفاً نام را وارد کنید.';
        } elseif ($formData['invoice_number'] === '') {
            $status = 'error';
            $message = 'لطفاً شماره فاکتور را وارد کنید.';
        } elseif (!in_array($formData['city'], $cities, true)) {
            $status = 'error';
            $message = 'لطفاً شهرستان را انتخاب کنید.';
        } else {
            $upload = Upload::save($_FILES['image'] ?? [], 'uploads/accounting', Upload::IMAGE_EXTENSIONS);
            if (!$upload['ok']) {
                $status = 'error';
                $message = $upload['error'];
            } else {
                try {
                    Database::execute(
                        'INSERT INTO accounting_collections (collector_role,full_name,invoice_number,description,city,image_path,original_name,mime_type,file_size,status,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,"sent",NOW(),NOW())',
                        [
                            $formData['collector_role'],
                            $formData['full_name'],
                            $formData['invoice_number'],
                            $formData['description'],
                            $formData['city'],
                            $upload['file_path'],
                            $upload['original_name'],
                            $upload['mime_type'],
                            $upload['file_size'],
                        ]
                    );
                    $status = 'success';
                    $message = 'اطلاعات با موفقیت برای حسابداری ارسال شد.';
                    $formData = ['collector_role' => '', 'full_name' => '', 'invoice_number' => '', 'description' => '', 'city' => ''];
                } catch (Throwable $e) {
                    @unlink(__DIR__ . $upload['file_path']);
                    $status = 'error';
                    $message = 'خطا در ثبت اطلاعات. لطفاً بعداً دوباره تلاش کنید.';
                }
            }
        }
    }
}
?>
<!doctype html>
<html lang="fa-IR" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> - <?= e(setting('company_name', 'هلدینگ سبحان')) ?></title>
    <style>
        *{box-sizing:border-box}
        body{margin:0;min-height:100vh;display:grid;place-items:center;padding:20px;direction:rtl;font-family:Tahoma,Arial,sans-serif;background:linear-gradient(135deg,#06343d 0%,#071018 100%);color:#0f172a}
        .container{width:min(560px,100%);background:rgba(255,255,255,.96);border:1px solid rgba(255,255,255,.45);border-radius:18px;padding:34px;box-shadow:0 24px 70px rgba(0,0,0,.32)}
        .logo{text-align:center;margin-bottom:26px}
        .logo h1{margin:0 0 8px;color:#06343d;font-size:34px}
        .logo p{margin:0;color:#b9922d;font-weight:700}
        .message{padding:13px 16px;border-radius:10px;margin-bottom:20px;text-align:center;font-weight:700}
        .message.success{background:#ecfdf5;border:1px solid #86efac;color:#166534}
        .message.error{background:#fef2f2;border:1px solid #fecaca;color:#991b1b}
        .form-group{margin-bottom:18px}
        .form-label{display:block;margin-bottom:7px;color:#06343d;font-weight:700}
        .form-control{width:100%;border:2px solid #e2e8f0;border-radius:10px;padding:13px 14px;font:inherit;background:#fff;color:#0f172a}
        textarea.form-control{min-height:104px;resize:vertical}
        .form-control:focus{outline:0;border-color:#d4af37;box-shadow:0 0 0 4px rgba(212,175,55,.14)}
        .btn{width:100%;border:0;border-radius:10px;padding:15px 18px;background:#d4af37;color:#fff;font:inherit;font-size:17px;font-weight:800;cursor:pointer;transition:.2s}
        .btn:hover{background:#bd982d;transform:translateY(-1px);box-shadow:0 12px 24px rgba(212,175,55,.26)}
        .back-link{text-align:center;margin-top:18px}
        .back-link a{color:#06343d;font-weight:800;text-decoration:none}
        @media(max-width:520px){.container{padding:24px}.logo h1{font-size:28px}}
    </style>
</head>
<body>
<main class="container">
    <div class="logo">
        <h1>ثبت وصول حساب</h1>
        <p>فرم ارسال اطلاعات برای حسابداری</p>
    </div>

    <?php if ($message): ?>
        <div class="message <?= e($status) ?>"><?= e($message) ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= e(Auth::csrfToken()) ?>">

        <div class="form-group">
            <label class="form-label" for="collector_role">نقش *</label>
            <select class="form-control" id="collector_role" name="collector_role" required>
                <option value="">انتخاب کنید...</option>
                <?php foreach ($roles as $role): ?>
                    <option value="<?= e($role) ?>" <?= $formData['collector_role'] === $role ? 'selected' : '' ?>><?= e($role) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label" for="full_name">نام *</label>
            <input class="form-control" id="full_name" name="full_name" value="<?= e($formData['full_name']) ?>" placeholder="نام و نام خانوادگی" required>
        </div>

        <div class="form-group">
            <label class="form-label" for="invoice_number">شماره فاکتور *</label>
            <input class="form-control" id="invoice_number" name="invoice_number" value="<?= e($formData['invoice_number']) ?>" placeholder="مثلاً 12345" required>
        </div>

        <div class="form-group">
            <label class="form-label" for="city">شهرستان *</label>
            <select class="form-control" id="city" name="city" required>
                <option value="">انتخاب کنید...</option>
                <?php foreach ($cities as $city): ?>
                    <option value="<?= e($city) ?>" <?= $formData['city'] === $city ? 'selected' : '' ?>><?= e($city) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label" for="description">توضیحات</label>
            <textarea class="form-control" id="description" name="description" placeholder="توضیحات تکمیلی"><?= e($formData['description']) ?></textarea>
        </div>

        <div class="form-group">
            <label class="form-label" for="image">ارسال تصویر *</label>
            <input class="form-control" id="image" name="image" type="file" accept="image/png,image/jpeg" required>
        </div>

        <button class="btn" type="submit">ارسال برای حسابداری</button>
    </form>

    <div class="back-link"><a href="/">بازگشت به صفحه اصلی</a></div>
</main>
</body>
</html>
