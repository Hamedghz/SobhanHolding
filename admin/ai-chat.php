<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/SobhanApiClient.php';

Auth::requireLogin();
if (!Auth::can('view_ai_chat') || !Auth::can('use_ai_assistant')) {
    http_response_code(403);
    echo 'دسترسی غیرمجاز';
    exit;
}

$pageTitle = 'هوش مصنوعی';
$client = new SobhanApiClient();
$statusResult = $client->get('/ai/status');
$aiAvailable = $statusResult['ok'];
$statusMessage = $aiAvailable ? 'دستیار فعال است.' : 'دستیار هوش مصنوعی در حال حاضر در دسترس نیست.';
Auth::start();
$_SESSION['sobhan_ai_chat'] ??= [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('درخواست نامعتبر است.', 'danger');
        redirect('/admin/ai-chat.php');
    }

    if (($_POST['action'] ?? '') === 'clear') {
        $_SESSION['sobhan_ai_chat'] = [];
        redirect('/admin/ai-chat.php');
    }

    $question = trim((string)($_POST['question'] ?? ''));
    if ($question === '' || (function_exists('mb_strlen') ? mb_strlen($question, 'UTF-8') : strlen($question)) > 1000) {
        flash('متن پرسش باید بین ۱ تا ۱۰۰۰ کاراکتر باشد.', 'danger');
        redirect('/admin/ai-chat.php');
    }

    $now = time();
    $lastAskAt = (int)($_SESSION['sobhan_ai_last_ask_at'] ?? 0);
    if ($now - $lastAskAt < 2) {
        flash('لطفاً چند لحظه بعد دوباره تلاش کنید.', 'danger');
        redirect('/admin/ai-chat.php');
    }
    $_SESSION['sobhan_ai_last_ask_at'] = $now;

    $answer = 'دستیار هوش مصنوعی در حال حاضر در دسترس نیست.';
    if ($client->isEnabled()) {
        $askResult = $client->post('/ai/ask', ['question' => $question]);
        if ($askResult['ok']) {
            $data = is_array($askResult['data']) ? $askResult['data'] : [];
            $answer = (string)($data['answer'] ?? $data['message'] ?? $data['result'] ?? json_encode($data, JSON_UNESCAPED_UNICODE));
        } else {
            $answer = $askResult['error']['message_fa'] ?? $answer;
        }
    }

    $_SESSION['sobhan_ai_chat'][] = [
        'question' => $question,
        'answer' => $answer,
        'at' => date('Y-m-d H:i:s'),
    ];
    $_SESSION['sobhan_ai_chat'] = array_slice($_SESSION['sobhan_ai_chat'], -20);
    redirect('/admin/ai-chat.php');
}

$history = $_SESSION['sobhan_ai_chat'];
$prompts = [
    'فروش کل و تعداد فاکتورها را خلاصه کن',
    'سه ویزیتور برتر را تحلیل کن',
    'پرفروش‌ترین کالاها را معرفی کن',
    'یک گزارش مدیریتی کوتاه بده',
];

require __DIR__ . '/../views/partials/admin-header.php';
?>
<section class="card ai-chat-shell">
    <div class="section-heading-row">
        <div>
            <h2>گفتگوی مدیریتی با هوش مصنوعی</h2>
            <p class="muted"><?= e($statusMessage) ?></p>
        </div>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(Auth::csrfToken()) ?>">
            <button class="btn" name="action" value="clear">پاک کردن گفتگو</button>
        </form>
    </div>

    <?php if (!$client->isEnabled()): ?>
        <div class="alert alert-error">سرویس گزارش‌گیری سبحان غیرفعال است.</div>
    <?php elseif (!$aiAvailable): ?>
        <div class="alert alert-error">دستیار هوش مصنوعی در حال حاضر در دسترس نیست.</div>
    <?php endif; ?>

    <div class="suggested-prompts">
        <?php foreach ($prompts as $prompt): ?>
            <button type="button" class="btn btn-small" data-prompt="<?= e($prompt) ?>"><?= e($prompt) ?></button>
        <?php endforeach; ?>
    </div>

    <div class="ai-chat-history">
        <?php if (!$history): ?>
            <p class="muted">هنوز گفتگویی ثبت نشده است.</p>
        <?php endif; ?>
        <?php foreach ($history as $item): ?>
            <div class="chat-bubble chat-user"><?= e($item['question']) ?></div>
            <div class="chat-bubble chat-ai"><?= nl2br(e($item['answer'])) ?></div>
        <?php endforeach; ?>
    </div>

    <form class="ai-chat-form" method="post" data-loading-text="در حال تحلیل...">
        <input type="hidden" name="csrf_token" value="<?= e(Auth::csrfToken()) ?>">
        <label class="form-field">
            <span>پرسش شما</span>
            <textarea name="question" maxlength="1000" rows="4" required></textarea>
        </label>
        <button class="btn btn-primary" name="action" value="ask">تحلیل کن</button>
    </form>
</section>
<script>
document.querySelectorAll('[data-prompt]').forEach(button => {
    button.addEventListener('click', () => {
        const textarea = document.querySelector('.ai-chat-form textarea[name="question"]');
        if (textarea) textarea.value = button.dataset.prompt || '';
    });
});
document.querySelector('.ai-chat-form')?.addEventListener('submit', event => {
    const button = event.currentTarget.querySelector('button[type="submit"], button[name="action"]');
    if (button) {
        button.disabled = true;
        button.textContent = event.currentTarget.dataset.loadingText || button.textContent;
    }
});
</script>
</section></main></div><script src="/assets/js/app.js"></script></body></html>
