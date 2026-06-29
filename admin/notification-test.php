<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../lib/NotificationService.php';
require_once __DIR__ . '/../lib/PushNotificationService.php';

Auth::requireAnyRole(['admin']);
$user = Auth::user();
$pageTitle = 'تست اعلان‌ها';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('درخواست نامعتبر است.', 'danger');
        redirect('/admin/notification-test.php');
    }

    try {
        NotificationService::create(
            (int)$user['id'],
            'test',
            'اعلان آزمایشی پنل سبحان',
            'این اعلان برای بررسی مرکز اعلان و Push دستگاه فعلی ارسال شده است.',
            '/admin/notification-test.php',
            ['actor_user' => $user, 'safe_push_body' => 'یک اعلان آزمایشی در پنل سبحان دارید.']
        );
        flash('اعلان آزمایشی ثبت شد. اگر Push روی دستگاه فعال باشد، اعلان مرورگر هم ارسال می‌شود.');
    } catch (Throwable $e) {
        error_log('notification-test: ' . $e->getMessage());
        flash('ارسال اعلان آزمایشی انجام نشد.', 'danger');
    }
    redirect('/admin/notification-test.php');
}

$subscriptions = NotificationService::activeSubscriptions((int)$user['id']);
$logs = Auth::isSuperAdmin() ? NotificationService::adminLogs(100) : [];
$events = NotificationService::eventLabels();
$pushSupported = PushNotificationService::hasSupport();

require __DIR__ . '/../views/partials/admin-header.php';
?>
<div class="section-heading-row">
    <div>
        <h1>تست اعلان‌ها</h1>
        <p class="muted">ادمین می‌تواند برای حساب خودش اعلان تست ارسال کند.</p>
    </div>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= e(Auth::csrfToken()) ?>">
        <button class="btn btn-primary">ارسال اعلان تست به خودم</button>
    </form>
</div>

<div class="stats">
    <div class="stat-card"><span>Push سرور</span><strong><?= $pushSupported ? 'فعال' : 'غیرفعال' ?></strong></div>
    <div class="stat-card"><span>اشتراک‌های من</span><strong><?= e((string)count($subscriptions)) ?></strong></div>
    <div class="stat-card"><span>خوانده‌نشده من</span><strong><?= e((string)NotificationService::unreadCount((int)$user['id'])) ?></strong></div>
    <div class="stat-card"><span>VAPID</span><strong><?= PushNotificationService::publicKey() !== '' ? 'آماده' : 'نیازمند بررسی' ?></strong></div>
</div>

<section class="card">
    <h2>اشتراک‌های Push حساب من</h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th>وضعیت</th><th>سرویس</th><th>آخرین موفقیت</th><th>آخرین خطا</th></tr></thead>
            <tbody>
            <?php foreach ($subscriptions as $subscription): ?>
                <tr>
                    <td><?= (int)$subscription['active'] ? 'فعال' : 'غیرفعال' ?></td>
                    <td><?= e(parse_url($subscription['endpoint'], PHP_URL_HOST) ?: $subscription['endpoint']) ?></td>
                    <td><?= e($subscription['last_success_at'] ? format_jalali_datetime($subscription['last_success_at']) : '-') ?></td>
                    <td><?= e($subscription['last_error'] ?: '-') ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$subscriptions): ?><tr><td colspan="4">اشتراک Push فعالی برای حساب شما ثبت نشده است.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php if (Auth::isSuperAdmin()): ?>
<section class="card">
    <h2>لاگ اعلان‌ها</h2>
    <div class="table-wrap notification-log-table">
        <table>
            <thead><tr><th>کاربر</th><th>رویداد</th><th>عنوان</th><th>Push</th><th>زمان</th></tr></thead>
            <tbody>
            <?php foreach ($logs as $log): ?>
                <tr>
                    <td><?= e($log['user_name']) ?></td>
                    <td><?= e($events[$log['event_type']] ?? $log['event_type']) ?></td>
                    <td><strong><?= e($log['title']) ?></strong><br><small><?= e($log['action_url'] ?: '-') ?></small></td>
                    <td><?= (int)$log['channel_push_sent'] ? 'ارسال شد' : ((int)$log['channel_push_requested'] ? 'درخواست شد' : '-') ?></td>
                    <td><?= e(format_jalali_datetime($log['created_at'])) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$logs): ?><tr><td colspan="5">لاگی ثبت نشده است.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>
<?php require __DIR__ . '/../views/partials/admin-footer.php'; ?>
