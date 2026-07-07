<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../lib/NotificationService.php';
require_once __DIR__ . '/../lib/PushNotificationService.php';
require_once __DIR__ . '/../services/NotificationHubService.php';

Auth::requireLogin();
$user = Auth::user();
$pageTitle = 'تنظیمات اعلان‌ها';
$userId = (int)$user['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('درخواست نامعتبر است.', 'danger');
        redirect('/admin/notification-settings.php');
    }

    $action = $_POST['action'] ?? 'settings';
    try {
        if ($action === 'mark_all_read') {
            NotificationService::markAllAsRead($userId);
            flash('همه اعلان‌ها خوانده شد.');
        } elseif ($action === 'hub_modules') {
            NotificationHubService::saveModuleSettings($userId, (array)($_POST['modules'] ?? []));
            flash('تنظیمات اعلان ویندوز ذخیره شد.');
        } else {
            NotificationService::saveSettings($userId, $_POST);
            flash('تنظیمات اعلان‌ها ذخیره شد.');
        }
    } catch (Throwable $e) {
        error_log('notification-settings: ' . $e->getMessage());
        flash('ذخیره تنظیمات اعلان‌ها انجام نشد.', 'danger');
    }
    redirect('/admin/notification-settings.php');
}

$settings = NotificationService::settings($userId);
$events = NotificationService::eventLabels();
$subscriptions = NotificationService::activeSubscriptions($userId);
$notifications = NotificationService::listForUser($userId, 20);
$pushSupported = PushNotificationService::hasSupport();
$hubModuleSettings = NotificationHubService::moduleSettings($userId);
$hubModuleLabels=['cartable'=>'کارتابل','ticketing'=>'تیکتینگ و SLA','approval'=>'درخواست تأیید','hr'=>'منابع انسانی','sales'=>'فروش','management'=>'مدیریتی و مصوبات','system'=>'سیستمی'];

require __DIR__ . '/../views/partials/admin-header.php';
?>
<div class="section-heading-row">
    <div>
        <h1>تنظیمات اعلان‌ها</h1>
        <p class="muted">کانال‌ها و رویدادهای اعلان برای حساب شما مدیریت می‌شود.</p>
    </div>
    <div class="actions">
        <button class="btn btn-primary" type="button" data-enable-push>فعال‌سازی اعلان روی این دستگاه</button>
        <form method="post" class="inline-form">
            <input type="hidden" name="csrf_token" value="<?= e(Auth::csrfToken()) ?>">
            <input type="hidden" name="action" value="mark_all_read">
            <button class="btn" type="submit">خواندن همه اعلان‌ها</button>
        </form>
    </div>
</div>

<section class="card">
    <h2>تنظیمات Sobhan Notification Hub ویندوز</h2>
    <p class="muted">این تنظیمات توسط برنامه ویندوز دریافت می‌شود. متن HR و مدیریت به‌صورت پیش‌فرض مخفی است.</p>
    <form method="post"><input type="hidden" name="csrf_token" value="<?=e(Auth::csrfToken())?>"><input type="hidden" name="action" value="hub_modules">
    <div class="table-wrap"><table><thead><tr><th>ماژول</th><th>فعال</th><th>Desktop</th><th>نمایش متن</th><th>صدا</th><th>اولویت</th><th>پاسخ/اکشن مستقیم</th></tr></thead><tbody>
    <?php foreach($hubModuleLabels as $key=>$label):$row=$hubModuleSettings[$key]??[];$defaultShow=!in_array($key,['hr','management'],true);?>
    <tr><td><strong><?=e($label)?></strong></td><td><input type="checkbox" name="modules[<?=e($key)?>][enabled]" value="1" <?=($row?(int)$row['enabled']:1)?'checked':''?>></td><td><input type="checkbox" name="modules[<?=e($key)?>][desktop_enabled]" value="1" <?=($row?(int)$row['desktop_enabled']:1)?'checked':''?>></td><td><input type="checkbox" name="modules[<?=e($key)?>][show_body]" value="1" <?=($row?(int)$row['show_body']:$defaultShow)?'checked':''?>></td><td><select name="modules[<?=e($key)?>][sound]"><?php foreach(['default'=>'پیش‌فرض','important'=>'مهم','message'=>'پیام','silent'=>'بی‌صدا'] as $v=>$t):?><option value="<?=$v?>" <?=($row['sound']??'default')===$v?'selected':''?>><?=$t?></option><?php endforeach?></select></td><td><select name="modules[<?=e($key)?>][priority]"><?php foreach(['low'=>'کم','normal'=>'عادی','important'=>'مهم'] as $v=>$t):?><option value="<?=$v?>" <?=($row['priority']??'normal')===$v?'selected':''?>><?=$t?></option><?php endforeach?></select></td><td><?php if($key==='approval'):?><label><input type="checkbox" name="modules[<?=$key?>][direct_action_enabled]" value="1" <?=!empty($row['direct_action_enabled'])?'checked':''?>> تأیید/رد مستقیم</label><?php else:?>—<?php endif?></td></tr>
    <?php endforeach?></tbody></table></div><button class="btn btn-primary">ذخیره تنظیمات ویندوز</button></form>
</section>

<?php if (!$pushSupported): ?>
    <div class="alert alert-warning">Push Notification روی این سرور کامل فعال نیست؛ اعلان‌های داخل پنل همچنان کار می‌کنند.</div>
<?php endif; ?>

<div class="notification-settings-grid">
    <form class="card admin-form" method="post">
        <input type="hidden" name="csrf_token" value="<?= e(Auth::csrfToken()) ?>">
        <input type="hidden" name="action" value="settings">
        <h2>کانال‌های اعلان</h2>
        <div class="checkbox-grid">
            <label class="checkbox-item"><input type="checkbox" name="in_app_enabled" value="1" <?= $settings['in_app_enabled'] ? 'checked' : '' ?>> اعلان داخل پنل</label>
            <label class="checkbox-item"><input type="checkbox" name="push_enabled" value="1" <?= $settings['push_enabled'] ? 'checked' : '' ?>> Browser Push / PWA</label>
            <label class="checkbox-item"><input type="checkbox" name="email_enabled" value="1" <?= $settings['email_enabled'] ? 'checked' : '' ?>> ایمیل اختیاری</label>
            <label class="checkbox-item"><input type="checkbox" name="sms_enabled" value="1" <?= $settings['sms_enabled'] ? 'checked' : '' ?>> پیامک/پیام‌رسان آینده</label>
        </div>
        <div class="grid grid-2">
            <label class="form-field"><span>شروع سکوت اعلان Push</span><input type="time" name="quiet_hours_start" value="<?= e(substr((string)($settings['quiet_hours_start'] ?? ''), 0, 5)) ?>"></label>
            <label class="form-field"><span>پایان سکوت اعلان Push</span><input type="time" name="quiet_hours_end" value="<?= e(substr((string)($settings['quiet_hours_end'] ?? ''), 0, 5)) ?>"></label>
        </div>

        <h2 class="section-title">رویدادها</h2>
        <div class="table-wrap notification-event-table">
            <table>
                <thead><tr><th>رویداد</th><th>داخل پنل</th><th>Push</th><th>ایمیل</th><th>پیام‌رسان</th></tr></thead>
                <tbody>
                <?php foreach ($events as $eventKey => $eventLabel): $eventSetting = $settings['events'][$eventKey] ?? []; ?>
                    <tr>
                        <td><strong><?= e($eventLabel) ?></strong><small><code><?= e($eventKey) ?></code></small></td>
                        <?php foreach (['in_app' => 'داخل پنل', 'push' => 'Push', 'email' => 'ایمیل', 'sms' => 'پیام‌رسان'] as $channel => $label): ?>
                            <td><input type="checkbox" name="events[<?= e($eventKey) ?>][<?= e($channel) ?>]" value="1" <?= !empty($eventSetting[$channel]) ? 'checked' : '' ?> aria-label="<?= e($label . ' ' . $eventLabel) ?>"></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="form-actions"><button class="btn btn-primary">ذخیره تنظیمات</button></div>
    </form>

    <aside class="card">
        <h2>دستگاه‌های Push</h2>
        <div class="notification-device-list">
            <?php foreach ($subscriptions as $subscription): ?>
                <article>
                    <strong><?= (int)$subscription['active'] ? 'فعال' : 'غیرفعال' ?></strong>
                    <small><?= e(parse_url($subscription['endpoint'], PHP_URL_HOST) ?: $subscription['endpoint']) ?></small>
                    <small>آخرین موفقیت: <?= e($subscription['last_success_at'] ? format_jalali_datetime($subscription['last_success_at']) : '-') ?></small>
                    <?php if ($subscription['last_error']): ?><small>خطا: <?= e($subscription['last_error']) ?></small><?php endif; ?>
                </article>
            <?php endforeach; ?>
            <?php if (!$subscriptions): ?><p class="muted">هنوز دستگاهی برای Push ثبت نشده است.</p><?php endif; ?>
        </div>
    </aside>
</div>

<section class="card">
    <h2>آخرین اعلان‌های من</h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th>عنوان</th><th>رویداد</th><th>وضعیت</th><th>زمان</th><th>لینک</th></tr></thead>
            <tbody>
            <?php foreach ($notifications as $notification): ?>
                <tr>
                    <td><strong><?= e($notification['title']) ?></strong><br><small><?= e($notification['body']) ?></small></td>
                    <td><?= e($events[$notification['event_type']] ?? $notification['event_type']) ?></td>
                    <td><?= $notification['status'] === 'unread' ? 'خوانده‌نشده' : 'خوانده‌شده' ?></td>
                    <td><?= e(format_jalali_datetime($notification['created_at'])) ?></td>
                    <td><?php if ($notification['action_url']): ?><a class="btn btn-small" href="<?= e($notification['action_url']) ?>">مشاهده</a><?php else: ?>-<?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$notifications): ?><tr><td colspan="5">اعلانی ثبت نشده است.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require __DIR__ . '/../views/partials/admin-footer.php'; ?>
