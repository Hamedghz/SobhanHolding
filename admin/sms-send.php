<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/SmsGatewayService.php';

Auth::requirePermission('sms_send', 'create');
$pageTitle = 'ارسال پیامک';
$templates = Database::fetchAll('SELECT id,title,body FROM sms_templates WHERE is_active=1 ORDER BY title');
Auth::start();
if (empty($_SESSION['sms_manual_request_key'])) $_SESSION['sms_manual_request_key'] = bin2hex(random_bytes(24));
$smsRequestKey = (string)$_SESSION['sms_manual_request_key'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('درخواست نامعتبر است.', 'danger');
        redirect('/admin/sms-send.php');
    }
    try {
        $input = preg_split('/[\s,;،]+/u', (string)($_POST['mobiles'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $message = trim((string)($_POST['message'] ?? ''));
        $postedKey = (string)($_POST['request_key'] ?? '');
        if ($postedKey === '' || !hash_equals($smsRequestKey, $postedKey)) throw new InvalidArgumentException('شناسه درخواست ارسال معتبر نیست. صفحه را تازه‌سازی کنید.');
        $service = SmsGatewayService::active();
        $result = $service->sendSimpleSms($input, $message, trim((string)($_POST['sender'] ?? '')) ?: null, (int)Auth::user()['id'], 'manual', null, $postedKey);
        unset($_SESSION['sms_manual_request_key']);
        $success = count(array_filter($result['batches'], static fn($batch) => !empty($batch['success'])));
        $failed = count($result['batches']) - $success;
        $codes = array_values(array_filter(array_column($result['batches'], 'bulk_code')));
        $text = !empty($result['duplicate']) ? $result['message'] : "گیرندگان معتبر: {$result['valid_count']}، نامعتبر/تکراری: {$result['invalid_count']}، بسته موفق: {$success}، ناموفق: {$failed}" . ($codes ? '، کدها: ' . implode('، ', $codes) : '');
        flash($text, $failed || !$result['success'] ? 'danger' : 'success');
        Auth::log((int)Auth::user()['id'], !empty($result['duplicate']) ? 'sms_duplicate_prevented' : 'sms_manual_send', 'sms_messages');
    } catch (InvalidArgumentException $error) {
        flash($error->getMessage(), 'danger');
    } catch (Throwable $error) {
        error_log('SMS manual page [' . get_class($error) . ']');
        flash('ارسال پیامک انجام نشد. تنظیمات و دسترسی سرویس را بررسی کنید.', 'danger');
    }
    redirect('/admin/sms-send.php');
}

$setting = Database::fetch('SELECT default_sender FROM sms_settings WHERE is_active=1 ORDER BY id DESC LIMIT 1');
require __DIR__ . '/../views/partials/admin-header.php';
?>
<form class="card admin-form" method="post" data-sms-send-form>
    <input type="hidden" name="csrf_token" value="<?= e(Auth::csrfToken()) ?>">
    <input type="hidden" name="request_key" value="<?= e($smsRequestKey) ?>">
    <h2>ارسال دستی</h2>
    <p class="muted">شماره‌ها را با فاصله، ویرگول، سمی‌کالن یا خط جدید جدا کنید؛ موارد تکراری حذف و ارسال در بسته‌های ۹۰تایی انجام می‌شود.</p>
    <div class="grid grid-2"><label class="form-field"><span>قالب اختیاری</span><select id="smsTemplate"><option value="">بدون قالب</option><?php foreach ($templates as $template): ?><option value="<?= e((string)$template['id']) ?>" data-body="<?= e($template['body']) ?>"><?= e($template['title']) ?></option><?php endforeach; ?></select></label><label class="form-field"><span>خط ارسال</span><input dir="ltr" name="sender" value="<?= e($setting['default_sender'] ?? '') ?>"></label></div>
    <label class="form-field"><span>گیرندگان</span><textarea dir="ltr" name="mobiles" rows="6" required></textarea></label>
    <label class="form-field"><span>متن پیام</span><textarea id="smsBody" name="message" rows="6" maxlength="2000" required></textarea><small data-sms-counter aria-live="polite"></small></label>
    <div class="form-actions"><button class="btn btn-primary">ارسال پیامک</button><a class="btn" href="/admin/sms-messages.php">گزارش ارسال</a></div>
</form>
<script>
(() => {
    const form = document.querySelector('[data-sms-send-form]');
    const body = document.getElementById('smsBody');
    const counter = document.querySelector('[data-sms-counter]');
    const updateCounter = () => {
        const length = [...body.value].length;
        const unicode = /[^\x00-\x7F]/.test(body.value);
        const single = unicode ? 70 : 160;
        const multiple = unicode ? 67 : 153;
        const segments = length === 0 ? 0 : (length <= single ? 1 : Math.ceil(length / multiple));
        counter.textContent = `${length} نویسه، ${segments} بخش پیامک`;
    };
    document.getElementById('smsTemplate')?.addEventListener('change', function () {
        const option = this.options[this.selectedIndex];
        if (option?.dataset.body) { body.value = option.dataset.body; updateCounter(); }
    });
    body?.addEventListener('input', updateCounter);
    updateCounter();
    form?.addEventListener('submit', () => {
        const button = form.querySelector('button');
        if (button) { button.disabled = true; button.textContent = 'در حال ارسال...'; }
    });
})();
</script>
<?php require __DIR__ . '/../views/partials/admin-footer.php'; ?>
