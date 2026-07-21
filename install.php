<?php
require_once __DIR__ . '/core/Installer.php';

$lock = __DIR__ . '/install.lock';
$errors = [];
$success = false;
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (empty($_SESSION['installer_csrf'])) {
    $_SESSION['installer_csrf'] = bin2hex(random_bytes(32));
}

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function safeError(Throwable $exception): string
{
    $code = preg_replace('/[^A-Za-z0-9_.-]/', '', (string)$exception->getCode()) ?: 'unknown';
    error_log('Installer failure: type=' . get_class($exception) . ' code=' . $code);
    return 'اتصال به دیتابیس یا ایجاد ساختار انجام نشد. اطلاعات اتصال و سطح دسترسی کاربر دیتابیس را بررسی کنید.';
}

if (file_exists($lock)) {
    http_response_code(403);
    echo '<!doctype html><html lang="fa" dir="rtl"><meta charset="utf-8"><body style="font-family:Tahoma;padding:40px;background:#f8fafc"><h2>نصب قبلاً انجام شده است.</h2><p>برای نصب مجدد، ابتدا فایل install.lock را حذف کنید.</p></body></html>';
    exit;
}

$requirements = Installer::requirements();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals((string)$_SESSION['installer_csrf'], (string)($_POST['csrf_token'] ?? ''))) {
        $errors[] = 'اعتبار فرم نصب منقضی شده است. صفحه را تازه‌سازی و دوباره تلاش کنید.';
    }
    $dbHost = trim($_POST['db_host'] ?? '');
    $dbPort = (int)($_POST['db_port'] ?? 3306);
    $dbNameRaw = trim($_POST['db_name'] ?? '');
    $dbName = Installer::cleanDatabaseName($dbNameRaw);
    $dbUser = trim($_POST['db_user'] ?? '');
    $dbPass = (string)($_POST['db_pass'] ?? '');
    $appUrl = rtrim(trim($_POST['app_url'] ?? ''), '/');
    $adminName = trim($_POST['admin_name'] ?? '');
    $adminEmail = trim($_POST['admin_email'] ?? '');
    $adminUsername = trim($_POST['admin_username'] ?? '');
    $adminPassword = (string)($_POST['admin_password'] ?? '');

    foreach (['db_host', 'db_port', 'db_name', 'db_user', 'app_url', 'admin_name', 'admin_email', 'admin_username', 'admin_password'] as $field) {
        if (trim((string)($_POST[$field] ?? '')) === '') {
            $errors[] = 'فیلدهای ضروری را کامل کنید.';
            break;
        }
    }

    if ($dbName === '' || $dbName !== $dbNameRaw) {
        $errors[] = 'نام دیتابیس فقط می‌تواند شامل حروف انگلیسی، عدد و خط زیر باشد.';
    }

    if ($dbPort < 1 || $dbPort > 65535) {
        $errors[] = 'پورت دیتابیس باید عددی بین ۱ تا ۶۵۵۳۵ باشد.';
    }

    if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'ایمیل مدیر معتبر نیست.';
    }

    if (strlen($adminPassword) < 8) {
        $errors[] = 'رمز مدیر باید حداقل ۸ کاراکتر باشد.';
    }

    if (in_array(false, $requirements, true)) {
        $errors[] = 'همه پیش‌نیازهای نصب برقرار نیست.';
    }

    if (!$errors) {
        try {
            $serverDsn = 'mysql:host=' . $dbHost . ';port=' . $dbPort . ';charset=utf8mb4';
            $dbDsn = 'mysql:host=' . $dbHost . ';port=' . $dbPort . ';dbname=' . $dbName . ';charset=utf8mb4';
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];

            $serverPdo = new PDO($serverDsn, $dbUser, $dbPass, $options);
            try {
                $serverPdo->exec('CREATE DATABASE IF NOT EXISTS `' . $dbName . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            } catch (Throwable $ignored) {
                // On many cPanel/DirectAdmin hosts the database is created in the panel first.
                // If CREATE DATABASE is not allowed, we still try to connect to the provided DB below.
            }

            $pdo = new PDO($dbDsn, $dbUser, $dbPass, $options);

            // Fresh install only: runtime repairs/defaults are handled by core/Database.php::migrate().
            $schema = file_get_contents(__DIR__ . '/database/schema.sql');
            // Comments may legitimately contain semicolons. Remove comment-only lines before
            // splitting statements so their text can never be executed as SQL.
            $schema = preg_replace('/^\s*--.*$/m', '', $schema) ?? $schema;
            foreach (array_filter(array_map('trim', explode(';', $schema))) as $statement) {
                $pdo->exec($statement);
            }

            $pdo->beginTransaction();
            $stmt = $pdo->prepare('INSERT INTO users (name, email, username, password_hash, role, status, created_at, updated_at) VALUES (?, ?, ?, ?, "admin", "active", NOW(), NOW())');
            $stmt->execute([$adminName, $adminEmail, $adminUsername, password_hash($adminPassword, PASSWORD_DEFAULT)]);
            $adminUserId = (int)$pdo->lastInsertId();
            $pdo->commit();

            // Populate only safe system/reference data. Operational sales,
            // attendance, finance and user-entered records are never fabricated.
            Installer::seedFreshDatabase($pdo, $adminUserId);

            Installer::writeConfig([
                'installed' => true,
                'db' => [
                    'host' => $dbHost,
                    'port' => $dbPort,
                    'name' => $dbName,
                    'user' => $dbUser,
                    'pass' => $dbPass,
                    'charset' => 'utf8mb4',
                ],
                'app' => [
                    'url' => $appUrl,
                    'name' => 'شرکت پخش سبحان',
                    'debug' => false,
                ],
            ]);

            file_put_contents($lock, date('c'), LOCK_EX);
            $success = true;
        } catch (Throwable $exception) {
            if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = safeError($exception);
        }
    }
}
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>نصب شرکت پخش سبحان</title>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="login-page">
<main class="card login-card">
    <h1>نصب سامانه</h1>

    <?php if ($success): ?>
        <div class="alert alert-success">ساختار دیتابیس، حساب مدیر و داده‌های پایه امن ایجاد شد. سامانه آماده ورود است.</div>
        <a class="btn btn-primary" href="/login.php">ورود</a>
    <?php else: ?>
        <h3>پیش‌نیازها</h3>
        <?php foreach ($requirements as $label => $ok): ?>
            <p><?= $ok ? '✅' : '❌' ?> <?= h($label) ?></p>
        <?php endforeach; ?>

        <?php foreach (array_unique($errors) as $error): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endforeach; ?>

        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= h($_SESSION['installer_csrf']) ?>">
            <div class="grid grid-2">
                <label class="form-field"><span>هاست دیتابیس</span><input name="db_host" value="<?= h($_POST['db_host'] ?? 'localhost') ?>" required></label>
                <label class="form-field"><span>پورت دیتابیس</span><input type="number" min="1" max="65535" name="db_port" value="<?= h($_POST['db_port'] ?? '3306') ?>" required></label>
                <label class="form-field"><span>نام دیتابیس</span><input name="db_name" value="<?= h($_POST['db_name'] ?? '') ?>" required></label>
                <label class="form-field"><span>نام کاربری دیتابیس</span><input name="db_user" value="<?= h($_POST['db_user'] ?? '') ?>" required></label>
                <label class="form-field"><span>رمز دیتابیس</span><input type="password" name="db_pass" autocomplete="off"></label>
                <label class="form-field"><span>آدرس برنامه</span><input name="app_url" value="<?= h($_POST['app_url'] ?? '') ?>" required></label>
                <label class="form-field"><span>نام مدیر</span><input name="admin_name" value="<?= h($_POST['admin_name'] ?? '') ?>" required></label>
                <label class="form-field"><span>ایمیل مدیر</span><input type="email" name="admin_email" value="<?= h($_POST['admin_email'] ?? '') ?>" required></label>
                <label class="form-field"><span>نام کاربری مدیر</span><input name="admin_username" value="<?= h($_POST['admin_username'] ?? '') ?>" required></label>
                <label class="form-field"><span>رمز مدیر</span><input type="password" name="admin_password" required></label>
            </div>
            <button class="btn btn-primary" type="submit">اتصال به دیتابیس و شروع نصب</button>
        </form>
    <?php endif; ?>
</main>
</body>
</html>
