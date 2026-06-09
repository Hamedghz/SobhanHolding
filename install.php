<?php
require_once __DIR__ . '/core/Installer.php';
$lock = __DIR__ . '/install.lock';
$errors = [];$success = false;
if (file_exists($lock)) { http_response_code(403); echo '<!doctype html><html lang="fa" dir="rtl"><meta charset="utf-8"><body style="font-family:Tahoma;padding:40px;background:#f8fafc"><h2>نصب قبلاً انجام شده است.</h2><p>برای نصب مجدد، ابتدا فایل install.lock را حذف کنید.</p></body></html>'; exit; }
$requirements = Installer::requirements();
function h($v){return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (['db_host','db_name','db_user','app_url','admin_name','admin_email','admin_username','admin_password'] as $field) {
        if (trim($_POST[$field] ?? '') === '') $errors[] = 'فیلدهای ضروری را کامل کنید.';
    }
    if (!filter_var($_POST['admin_email'] ?? '', FILTER_VALIDATE_EMAIL)) $errors[] = 'ایمیل مدیر معتبر نیست.';
    if (strlen($_POST['admin_password'] ?? '') < 8) $errors[] = 'رمز مدیر باید حداقل ۸ کاراکتر باشد.';
    if (in_array(false, $requirements, true)) $errors[] = 'همه پیش‌نیازهای نصب برقرار نیست.';
    if (!$errors) {
        try {
            $pdo = new PDO('mysql:host=' . $_POST['db_host'] . ';charset=utf8mb4', $_POST['db_user'], $_POST['db_pass'] ?? '', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
            $dbName = preg_replace('/[^a-zA-Z0-9_]/', '', $_POST['db_name']);
            $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . $dbName . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $pdo->exec('USE `' . $dbName . '`');
            $schema = file_get_contents(__DIR__ . '/database/schema.sql');
            foreach (array_filter(array_map('trim', explode(';', $schema))) as $statement) $pdo->exec($statement);
            $pdo->beginTransaction();
            $seed = file_get_contents(__DIR__ . '/database/seed.sql');
            foreach (array_filter(array_map('trim', explode(';', $seed))) as $statement) $pdo->exec($statement);
            $stmt = $pdo->prepare('INSERT INTO users (name,email,username,password_hash,role,status,created_at,updated_at) VALUES (?,?,?,?,"admin","active",NOW(),NOW())');
            $stmt->execute([$_POST['admin_name'], $_POST['admin_email'], $_POST['admin_username'], password_hash($_POST['admin_password'], PASSWORD_DEFAULT)]);
            $pdo->commit();
            $config = "<?php\nreturn [\n    'db' => [\n        'host' => " . var_export($_POST['db_host'], true) . ",\n        'name' => " . var_export($dbName, true) . ",\n        'user' => " . var_export($_POST['db_user'], true) . ",\n        'pass' => " . var_export($_POST['db_pass'] ?? '', true) . ",\n        'charset' => 'utf8mb4',\n    ],\n    'app' => [\n        'url' => " . var_export(rtrim($_POST['app_url'], '/'), true) . ",\n        'name' => 'شرکت پخش سبحان',\n        'debug' => false,\n    ],\n];\n";
            file_put_contents(__DIR__ . '/config/config.php', $config, LOCK_EX);
            file_put_contents($lock, date('c'), LOCK_EX);
            $success = true;
        } catch (Throwable $e) {
            if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
            $errors[] = 'خطا در نصب: ' . h($e->getMessage());
        }
    }
}
?>
<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>نصب شرکت پخش سبحان</title><link rel="stylesheet" href="/assets/css/app.css"></head><body class="login-page"><main class="card login-card"><h1>نصب سامانه</h1><?php if($success): ?><div class="alert alert-success">نصب با موفقیت انجام شد. اکنون می‌توانید وارد سامانه شوید.</div><a class="btn btn-primary" href="/login.php">ورود</a><?php else: ?><h3>پیش‌نیازها</h3><?php foreach($requirements as $label=>$ok): ?><p><?= $ok?'✅':'❌' ?> <?= h($label) ?></p><?php endforeach; ?><?php foreach(array_unique($errors) as $error): ?><div class="alert alert-danger"><?= $error ?></div><?php endforeach; ?><form method="post"><div class="grid grid-2"><label class="form-field"><span>هاست دیتابیس</span><input name="db_host" value="<?= h($_POST['db_host'] ?? 'localhost') ?>" required></label><label class="form-field"><span>نام دیتابیس</span><input name="db_name" value="<?= h($_POST['db_name'] ?? '') ?>" required></label><label class="form-field"><span>نام کاربری دیتابیس</span><input name="db_user" value="<?= h($_POST['db_user'] ?? '') ?>" required></label><label class="form-field"><span>رمز دیتابیس</span><input type="password" name="db_pass"></label><label class="form-field"><span>آدرس برنامه</span><input name="app_url" value="<?= h($_POST['app_url'] ?? '') ?>" required></label><label class="form-field"><span>نام مدیر</span><input name="admin_name" value="<?= h($_POST['admin_name'] ?? '') ?>" required></label><label class="form-field"><span>ایمیل مدیر</span><input type="email" name="admin_email" value="<?= h($_POST['admin_email'] ?? '') ?>" required></label><label class="form-field"><span>نام کاربری مدیر</span><input name="admin_username" value="<?= h($_POST['admin_username'] ?? '') ?>" required></label><label class="form-field"><span>رمز مدیر</span><input type="password" name="admin_password" required></label></div><button class="btn btn-primary" type="submit">شروع نصب</button></form><?php endif; ?></main></body></html>
