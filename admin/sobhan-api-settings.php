<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/SobhanApiClient.php';
require_once __DIR__ . '/../core/AiUpdateService.php';

Auth::requireLogin();
if (!Auth::can('view_sobhan_api_settings') && !Auth::can('manage_sobhan_api_settings') && !Auth::can('view_data_source_settings') && !Auth::can('manage_data_source_settings')) {
    http_response_code(403);
    echo 'دسترسی غیرمجاز';
    exit;
}
$pageTitle = 'تنظیمات API سبحان';

function sobhan_save_setting(string $key, string $value, string $type): void
{
    Database::execute(
        'INSERT INTO site_settings (setting_key,setting_value,setting_type,updated_at) VALUES (?,?,?,NOW()) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value), setting_type=VALUES(setting_type), updated_at=NOW()',
        [$key, $value, $type]
    );
}

$testResult = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? 'save');
    if (in_array($action, ['save', 'test'], true) && !Auth::can('manage_sobhan_api_settings', 'edit')) {
        flash('برای ویرایش تنظیمات API دسترسی ندارید.', 'danger');
        redirect('/admin/sobhan-api-settings.php');
    }
    if ($action === 'save_data_source' && !Auth::can('manage_data_source_settings', 'edit')) {
        flash('برای تغییر منبع داده دسترسی ندارید.', 'danger');
        redirect('/admin/sobhan-api-settings.php#data-source-settings');
    }
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('درخواست نامعتبر است.', 'danger');
        redirect('/admin/sobhan-api-settings.php');
    }

    if ($action === 'save_data_source') {
        $mode = in_array($_POST['sobhan_distribution_data_mode'] ?? '', ['import_file', 'ai_api'], true) ? $_POST['sobhan_distribution_data_mode'] : 'import_file';
        $autofill = !empty($_POST['sobhan_ai_autofill_enabled']) ? '1' : '0';
        $overwrite = !empty($_POST['sobhan_ai_overwrite_manual_data']) ? '1' : '0';

        if ($autofill === '1' && !Auth::can('toggle_ai_autofill', 'edit')) {
            flash('برای فعال‌سازی تکمیل خودکار با هوش مصنوعی دسترسی ندارید.', 'danger');
            redirect('/admin/sobhan-api-settings.php#data-source-settings');
        }
        if ($overwrite === '1' && !Auth::can('allow_ai_overwrite_manual_data', 'edit')) {
            flash('برای اجازه بازنویسی داده‌های دستی دسترسی جداگانه لازم است.', 'danger');
            redirect('/admin/sobhan-api-settings.php#data-source-settings');
        }

        sobhan_save_setting('sobhan_distribution_data_mode', $mode, 'select');
        sobhan_save_setting('sobhan_ai_autofill_enabled', $autofill, 'boolean');
        sobhan_save_setting('sobhan_ai_overwrite_manual_data', $overwrite, 'boolean');
        sobhan_save_setting('sobhan_static_pharmacy_mode', '1', 'boolean');
        flash('تنظیمات منبع داده ذخیره شد.');
        redirect('/admin/sobhan-api-settings.php#data-source-settings');
    }

    $baseUrl = rtrim(trim((string)($_POST['sobhan_windows_api_url'] ?? $_POST['sobhan_api_base_url'] ?? '')), '/');
    $reportingUrl = rtrim(trim((string)($_POST['sobhan_reporting_api_url'] ?? '')), '/');
    $aiModelUrl = rtrim(trim((string)($_POST['sobhan_ai_model_api_url'] ?? '')), '/');
    $timeout = (string)max(1, min(60, (int)($_POST['sobhan_api_timeout'] ?? 10)));
    $model = trim((string)($_POST['sobhan_api_model'] ?? 'qwen2.5:1.5b'));
    $enabled = !empty($_POST['sobhan_api_enabled']) ? '1' : '0';
    $reportingEnabled = !empty($_POST['sobhan_reporting_api_enabled']) ? '1' : '0';
    $aiModelEnabled = !empty($_POST['sobhan_ai_model_api_enabled']) ? '1' : '0';
    $retryCount = (string)max(0,min(5,(int)($_POST['sobhan_api_retry_count']??1)));
    $newKey = trim((string)($_POST['sobhan_api_key'] ?? ''));

    foreach ([['Windows Server API',$baseUrl],['Reporting API',$reportingUrl],['AI Model API',$aiModelUrl]] as [$label,$url]) if ($url !== '' && !filter_var($url, FILTER_VALIDATE_URL)) {
        flash('آدرس '.$label.' معتبر نیست.', 'danger');
        redirect('/admin/sobhan-api-settings.php');
    }

    sobhan_save_setting('sobhan_api_base_url', $baseUrl, 'text');
    sobhan_save_setting('sobhan_api_timeout', $timeout, 'number');
    sobhan_save_setting('sobhan_api_retry_count', $retryCount, 'number');
    sobhan_save_setting('sobhan_api_model', $model, 'text');
    sobhan_save_setting('sobhan_ai_model', $model, 'text');
    sobhan_save_setting('sobhan_api_enabled', $enabled, 'boolean');
    sobhan_save_setting('sobhan_windows_api_url', $baseUrl, 'text');
    sobhan_save_setting('sobhan_reporting_api_url', $reportingUrl, 'text');
    sobhan_save_setting('sobhan_ai_model_api_url', $aiModelUrl, 'text');
    sobhan_save_setting('sobhan_windows_api_enabled', $enabled, 'boolean');
    sobhan_save_setting('sobhan_reporting_api_enabled', $reportingEnabled, 'boolean');
    sobhan_save_setting('sobhan_ai_model_api_enabled', $aiModelEnabled, 'boolean');
    if ($newKey !== '') {
        sobhan_save_setting('sobhan_api_key', $newKey, 'password');
    }

    if ($action === 'test') {
        $testJob = AiUpdateService::createAndRun('test_all',(int)Auth::user()['id']);
        flash($testJob['message']??'تست اتصال انجام شد.',$testJob['status']==='completed'?'success':'danger');
    } else {
        flash('تنظیمات API سبحان ذخیره شد.');
    }
    redirect('/admin/sobhan-api-settings.php');
}

$maskedKey = SobhanApiClient::maskKey(setting('sobhan_api_key', ''));
$distributionMode = setting('sobhan_distribution_data_mode', 'import_file');
$aiAutofillEnabled = setting('sobhan_ai_autofill_enabled', '0') === '1';
$aiOverwriteManual = setting('sobhan_ai_overwrite_manual_data', '0') === '1';
$canManageApi = Auth::can('manage_sobhan_api_settings', 'edit');
$aiJobs = Database::fetchAll('SELECT j.*,u.name requested_by_name FROM ai_update_jobs j LEFT JOIN users u ON u.id=j.requested_by ORDER BY j.id DESC LIMIT 30');
$windowsEnabled=setting('sobhan_windows_api_enabled',setting('sobhan_api_enabled','0'))==='1';$reportingEnabledValue=setting('sobhan_reporting_api_enabled',setting('sobhan_api_enabled','0'))==='1';$aiModelEnabledValue=setting('sobhan_ai_model_api_enabled','0')==='1';
require __DIR__ . '/../views/partials/admin-header.php';
?>
<form class="card admin-form" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= e(Auth::csrfToken()) ?>">
    <h2>تنظیمات اتصال سرویس‌ها</h2>
    <div class="grid grid-2">
        <label class="form-field">
            <span>آدرس Windows Server API</span>
            <input dir="ltr" name="sobhan_windows_api_url" value="<?= e(setting('sobhan_windows_api_url',setting('sobhan_api_base_url',''))) ?>" placeholder="https://windows-api.example.com" <?= $canManageApi ? '' : 'disabled' ?>>
        </label>
        <label class="form-field"><span>آدرس Reporting API</span><input dir="ltr" name="sobhan_reporting_api_url" value="<?=e(setting('sobhan_reporting_api_url',setting('sobhan_api_base_url','')))?>" placeholder="https://reporting-api.example.com" <?=$canManageApi?'':'disabled'?>></label>
        <label class="form-field"><span>آدرس AI Model API</span><input dir="ltr" name="sobhan_ai_model_api_url" value="<?=e(setting('sobhan_ai_model_api_url',''))?>" placeholder="https://ai-api.example.com" <?=$canManageApi?'':'disabled'?>></label>
        <label class="form-field">
            <span>SOBHAN_API_TIMEOUT</span>
            <input dir="ltr" type="number" min="1" max="60" name="sobhan_api_timeout" value="<?= e(setting('sobhan_api_timeout', '10')) ?>" <?= $canManageApi ? '' : 'disabled' ?>>
        </label>
        <label class="form-field"><span>تعداد تلاش مجدد</span><input type="number" min="0" max="5" name="sobhan_api_retry_count" value="<?=e(setting('sobhan_api_retry_count','1'))?>" <?=$canManageApi?'':'disabled'?>></label>
        <label class="form-field">
            <span>SOBHAN_API_KEY</span>
            <input dir="ltr" type="password" name="sobhan_api_key" value="" placeholder="<?= e($maskedKey ?: 'برای تغییر، کلید جدید را وارد کنید') ?>" autocomplete="new-password" <?= $canManageApi ? '' : 'disabled' ?>>
            <?php if ($maskedKey): ?><small>کلید ذخیره‌شده: <?= e($maskedKey) ?></small><?php endif; ?>
        </label>
        <label class="checkbox-item sobhan-toggle">
            <input type="checkbox" name="sobhan_api_enabled" value="1" <?= setting('sobhan_api_enabled', '0') === '1' ? 'checked' : '' ?> <?= $canManageApi ? '' : 'disabled' ?>>
            <span>SOBHAN_API_ENABLED</span>
        </label>
        <label class="checkbox-item sobhan-toggle"><input type="checkbox" name="sobhan_reporting_api_enabled" value="1" <?=$reportingEnabledValue?'checked':''?> <?=$canManageApi?'':'disabled'?>> <span>Reporting API فعال</span></label>
        <label class="checkbox-item sobhan-toggle"><input type="checkbox" name="sobhan_ai_model_api_enabled" value="1" <?=$aiModelEnabledValue?'checked':''?> <?=$canManageApi?'':'disabled'?>> <span>AI Model API فعال</span></label>
        <label class="form-field"><span>مدل AI</span><input dir="ltr" name="sobhan_api_model" value="<?=e(setting('sobhan_api_model','qwen2.5:1.5b'))?>" <?=$canManageApi?'':'disabled'?>></label>
    </div>
    <div class="form-actions">
        <?php if ($canManageApi): ?>
            <button class="btn btn-primary" name="action" value="save">ذخیره تنظیمات</button>
            <button class="btn" name="action" value="test">Test API Connection</button>
        <?php else: ?>
            <p class="muted">شما فقط می‌توانید تنظیمات را مشاهده کنید.</p>
        <?php endif; ?>
    </div>
</form>
<form class="card admin-form" method="post" id="data-source-settings">
    <input type="hidden" name="csrf_token" value="<?= e(Auth::csrfToken()) ?>">
    <h2>منبع داده داشبورد و اطلاعات شرکت پخش</h2>
    <section class="settings-section">
        <h3>تنظیمات منبع داده</h3>
        <p class="muted">برای اطلاعات شرکت پخش می‌توانید مشخص کنید داده‌ها از فایل ایمپورت‌شده خوانده شوند یا از API/هوش مصنوعی سبحان تکمیل گردند. اطلاعات داروخانه‌ها همیشه از فایل استاتیک خوانده می‌شود و توسط هوش مصنوعی بازنویسی نمی‌شود.</p>
        <div class="data-source-options">
            <label class="checkbox-item">
                <input type="radio" name="sobhan_distribution_data_mode" value="import_file" <?= $distributionMode === 'import_file' ? 'checked' : '' ?> <?= Auth::can('manage_data_source_settings', 'edit') ? '' : 'disabled' ?>>
                <strong>فایل ایمپورت‌شده</strong>
                <small>خواندن از فایل ایمپورت‌شده</small>
            </label>
            <label class="checkbox-item">
                <input type="radio" name="sobhan_distribution_data_mode" value="ai_api" <?= $distributionMode === 'ai_api' ? 'checked' : '' ?> <?= Auth::can('manage_data_source_settings', 'edit') ? '' : 'disabled' ?>>
                <strong>API/هوش مصنوعی سبحان</strong>
                <small>تکمیل و تحلیل با هوش مصنوعی/API سبحان</small>
            </label>
        </div>
        <div class="grid grid-2">
            <label class="checkbox-item sobhan-toggle">
                <input type="checkbox" name="sobhan_ai_autofill_enabled" value="1" <?= $aiAutofillEnabled ? 'checked' : '' ?> <?= Auth::can('toggle_ai_autofill', 'edit') ? '' : 'disabled' ?>>
                <span>تکمیل خودکار با هوش مصنوعی</span>
            </label>
            <label class="checkbox-item sobhan-toggle">
                <input type="checkbox" name="sobhan_ai_overwrite_manual_data" value="1" <?= $aiOverwriteManual ? 'checked' : '' ?> <?= Auth::can('allow_ai_overwrite_manual_data', 'edit') ? '' : 'disabled' ?> data-overwrite-toggle>
                <span>اجازه بازنویسی داده‌های دستی/ایمپورت‌شده</span>
            </label>
        </div>
        <div class="alert alert-error" data-overwrite-warning <?= $aiOverwriteManual ? '' : 'hidden' ?>>با فعال‌سازی این گزینه، داده‌های دستی یا ایمپورت‌شده ممکن است با داده‌های API/هوش مصنوعی جایگزین شوند.</div>
        <p><span class="badge">داروخانه‌ها: همیشه از فایل استاتیک خوانده می‌شود</span></p>
    </section>
    <?php if (Auth::can('manage_data_source_settings', 'edit')): ?>
        <button class="btn btn-primary" name="action" value="save_data_source">ذخیره تنظیمات منبع داده</button>
    <?php else: ?>
        <p class="muted">شما فقط می‌توانید وضعیت منبع داده را مشاهده کنید.</p>
    <?php endif; ?>
</form>
<?php if(Auth::isAdmin()||Auth::can('ai_updates')):?><section class="card" id="ai-update-runner"><div class="section-heading-row"><div><h2>تست و بروزرسانی سرویس‌ها</h2><p class="muted">همه درخواست‌ها در PHP سرور اجرا و در دیتابیس ثبت می‌شوند.</p></div></div><input type="hidden" id="aiJobCsrf" value="<?=e(Auth::csrfToken())?>"><div class="actions"><button type="button" class="btn" data-ai-job="test_windows">تست Windows Server API</button><button type="button" class="btn" data-ai-job="test_reporting">تست Reporting API</button><button type="button" class="btn" data-ai-job="test_ai">تست AI Model API</button><button type="button" class="btn" data-ai-job="test_all">تست کامل همه سرویس‌ها</button><button type="button" class="btn btn-primary" data-ai-job="full_update" <?=!$aiModelEnabledValue?'disabled title="AI Model API غیرفعال است"':''?>>بروزرسانی کامل</button><button type="button" class="btn" data-ai-job="dashboard_ceo_update" <?=!$reportingEnabledValue?'disabled':''?>>بروزرسانی داشبورد مدیرعامل</button><button type="button" class="btn" data-ai-job="dashboard_manager_update" <?=!$reportingEnabledValue?'disabled':''?>>بروزرسانی داشبورد مدیران</button><button type="button" class="btn" data-ai-job="hr_kpi_update" <?=!$reportingEnabledValue?'disabled':''?>>بروزرسانی KPI منابع انسانی</button></div><div class="ai-job-status" id="aiJobStatus" hidden><strong id="aiJobMessage">در حال اجرا</strong><div class="progress"><span id="aiJobProgress" style="width:0"></span></div><small id="aiJobPercent">۰٪</small></div><details class="manager-history" open><summary>مشاهده لاگ اجرا</summary><div class="table-wrap"><table><thead><tr><th>#</th><th>نوع عملیات</th><th>وضعیت</th><th>پیشرفت</th><th>پیام</th><th>endpoint</th><th>مدت</th><th>کاربر</th><th>شروع</th><th>پایان</th><?php if(Auth::isSuperAdmin()):?><th>جزئیات فنی</th><?php endif?></tr></thead><tbody><?php foreach($aiJobs as $job):?><tr><td><?=e($job['id'])?></td><td><?=e($job['job_type'])?></td><td><?=e($job['status'])?></td><td><?=e($job['progress'])?>٪</td><td><?=e($job['message'])?></td><td dir="ltr"><?=e($job['endpoint']?:'-')?></td><td><?=e($job['duration_ms']!==null?$job['duration_ms'].' ms':'-')?></td><td><?=e($job['requested_by_name']?:'-')?></td><td><?=e($job['started_at']?:$job['created_at'])?></td><td><?=e($job['finished_at']?:'-')?></td><?php if(Auth::isSuperAdmin()):?><td><details><summary>نمایش</summary><?=e($job['technical_details']?:'-')?></details></td><?php endif?></tr><?php endforeach?></tbody></table></div></details></section><?php endif?>
<section class="card">
    <h2>نکات امنیتی</h2>
    <p class="muted">کلید API پس از ذخیره نمایش داده نمی‌شود و فقط درخواست‌های PHP سرور از آن استفاده می‌کنند.</p>
</section>
<script>
const overwriteToggle = document.querySelector('[data-overwrite-toggle]');
const overwriteWarning = document.querySelector('[data-overwrite-warning]');
overwriteToggle?.addEventListener('change', () => {
    if (overwriteWarning) overwriteWarning.hidden = !overwriteToggle.checked;
});
const jobStatus=document.getElementById('aiJobStatus'),jobMessage=document.getElementById('aiJobMessage'),jobProgress=document.getElementById('aiJobProgress'),jobPercent=document.getElementById('aiJobPercent');
async function pollAiJob(id){const response=await fetch('/admin/actions/ai-update-status.php?id='+encodeURIComponent(id),{credentials:'same-origin'});const data=await response.json();if(!data.ok)return;const job=data.job;jobStatus.hidden=false;jobMessage.textContent=job.message||'در حال اجرا';jobProgress.style.width=(job.progress||0)+'%';jobPercent.textContent=(job.progress||0)+'٪';if(job.status==='running'||job.status==='pending')setTimeout(()=>pollAiJob(id),2500);}
document.querySelectorAll('[data-ai-job]').forEach(button=>button.addEventListener('click',async()=>{jobStatus.hidden=false;jobMessage.textContent='در حال ثبت و اجرا';jobProgress.style.width='5%';jobPercent.textContent='۵٪';const body=new FormData();body.append('csrf_token',document.getElementById('aiJobCsrf').value);body.append('job_type',button.dataset.aiJob);button.disabled=true;try{const endpoint=button.dataset.aiJob==='test_connection'?'/admin/actions/ai-test-connection.php':'/admin/actions/ai-run-update.php';const response=await fetch(endpoint,{method:'POST',body,credentials:'same-origin'});const data=await response.json();if(!data.job)throw new Error(data.message||'اجرای بروزرسانی ناموفق بود.');pollAiJob(data.job.id);}catch(error){jobMessage.textContent=error.message||'اجرای بروزرسانی ناموفق بود.';jobProgress.style.width='100%';}finally{button.disabled=false;}}));
</script>
</section></main></div><script src="/assets/js/app.js"></script></body></html>
