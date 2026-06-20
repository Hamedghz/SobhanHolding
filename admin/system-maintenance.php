<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/SeedManager.php';

Auth::requireLogin();
if (!Auth::isAdmin()) { http_response_code(403); exit('دسترسی غیرمجاز است.'); }
$pageTitle = 'بروزرسانی SQL و Seed';
$user = Auth::user();
$registry = SeedManager::registry();
$report = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) { http_response_code(419); exit('درخواست نامعتبر است.'); }
    $action = (string)($_POST['action'] ?? '');
    $mode = in_array($_POST['mode'] ?? '', ['safe','repair','force_update','dry_run'], true) ? $_POST['mode'] : 'safe';
    if ($mode === 'force_update' && trim((string)($_POST['force_confirmation'] ?? '')) !== 'تایید بروزرسانی اجباری') {
        flash('عبارت تایید بروزرسانی اجباری صحیح نیست.', 'danger');
        redirect('/admin/system-maintenance.php');
    }
    try {
        if ($action === 'run_seed') {
            $key = (string)($_POST['seed_key'] ?? '');
            $report = SeedManager::run($key, $mode, (int)$user['id']);
        } elseif ($action === 'run_all') {
            $report = SeedManager::runMany(array_keys($registry), $mode, (int)$user['id']);
        } elseif ($action === 'repair_schema') {
            $report = ['structure' => SystemMaintenance::repair(Database::connection())];
            HrModule::repair(Database::connection());
            Database::execute('INSERT INTO maintenance_logs(action_key,status,requested_by,message,result_json,created_at) VALUES ("repair_schema","completed",?,?,?,NOW())', [(int)$user['id'],'تعمیر امن ساختار انجام شد.',json_encode($report,JSON_UNESCAPED_UNICODE)]);
        } elseif ($action === 'check_schema') {
            $missing=[];foreach(SystemMaintenance::requiredTables() as $table)if(!Database::tableExists($table))$missing[]=$table;
            $report=['missing_tables'=>$missing,'status'=>$missing?'needs_repair':'complete'];
        } elseif ($action === 'clear_cache') {
            Database::execute('DELETE FROM dashboard_data_cache');
            $report=['cache'=>'cleared'];
        }
        if ($report !== null) flash('عملیات با موفقیت انجام شد.');
    } catch (Throwable $e) {
        error_log('System maintenance: '.$e->getMessage());
        flash('اجرای عملیات ناموفق بود. لطفاً لاگ را بررسی کنید.', 'danger');
    }
    if ($report !== null) $_SESSION['maintenance_report'] = $report;
    redirect('/admin/system-maintenance.php');
}

$report = $_SESSION['maintenance_report'] ?? null;unset($_SESSION['maintenance_report']);
$latest = array_column(SeedManager::latestRuns(), null, 'seed_group');
$logs = Database::fetchAll('SELECT * FROM seed_runs ORDER BY id DESC LIMIT 30');
require __DIR__ . '/../views/partials/admin-header.php';
?>
<section class="card admin-form"><div class="section-heading-row"><div><h1>بروزرسانی SQL و Seed</h1><p class="muted">اجرای امن و قابل تکرار، بدون DROP، TRUNCATE یا بازنویسی داده‌های عملیاتی.</p></div><form method="post"><input type="hidden" name="csrf_token" value="<?=e(Auth::csrfToken())?>"><button class="btn" name="action" value="check_schema">بررسی ساختار دیتابیس</button><button class="btn" name="action" value="repair_schema">تعمیر امن ساختار</button><button class="btn" name="action" value="clear_cache">پاکسازی کش داشبورد</button></form></div></section>
<?php if($report!==null):?><section class="card"><h2>گزارش آخرین اجرا</h2><pre class="maintenance-report"><?=e(json_encode($report,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT))?></pre></section><?php endif?>
<section class="card"><div class="section-heading-row"><h2>Seedهای موجود</h2><form method="post"><input type="hidden" name="csrf_token" value="<?=e(Auth::csrfToken())?>"><input type="hidden" name="mode" value="safe"><button class="btn btn-primary" name="action" value="run_all">اجرای همه Seedها</button></form></div><div class="table-wrap"><table><thead><tr><th>نام Seed</th><th>توضیح</th><th>آخرین اجرا</th><th>Inserted</th><th>Updated</th><th>Skipped</th><th>Errors</th><th>عملیات</th></tr></thead><tbody><?php foreach($registry as $key=>$seed):$last=$latest[$key]??null;?><tr><td><strong><?=e($seed['title'])?></strong><small><code><?=e($key)?></code></small></td><td><?=e($seed['description'])?></td><td><?=e($last['status']??'اجرا نشده')?></td><td><?=e($last['inserted_count']??0)?></td><td><?=e($last['updated_count']??0)?></td><td><?=e($last['skipped_count']??0)?></td><td><?=e($last['error_count']??0)?></td><td><form method="post" class="row-actions"><input type="hidden" name="csrf_token" value="<?=e(Auth::csrfToken())?>"><input type="hidden" name="seed_key" value="<?=e($key)?>"><button class="btn btn-small" name="action" value="run_seed" formaction="?mode=dry_run" onclick="this.form.elements.mode.value='dry_run'">بررسی بدون اجرا</button><button class="btn btn-small" name="action" value="run_seed">اجرای امن</button><button class="btn btn-small" name="action" value="run_seed" onclick="this.form.elements.mode.value='repair'">تعمیر</button><input type="hidden" name="mode" value="safe"></form></td></tr><?php endforeach?></tbody></table></div></section>
<section class="card admin-form"><h2>بروزرسانی اجباری رکوردهای سیستمی</h2><p class="alert alert-danger">این حالت فقط برای metadata سیستمی است و امتیازها، پاسخ‌ها، فروش، وصول و داده‌های دستی را تغییر نمی‌دهد.</p><form method="post" onsubmit="return confirm('بروزرسانی اجباری اجرا شود؟')"><input type="hidden" name="csrf_token" value="<?=e(Auth::csrfToken())?>"><input type="hidden" name="mode" value="force_update"><div class="grid grid-2"><label class="form-field"><span>Seed</span><select name="seed_key"><?php foreach($registry as $key=>$seed):?><option value="<?=e($key)?>"><?=e($seed['title'])?></option><?php endforeach?></select></label><label class="form-field"><span>عبارت تایید</span><input name="force_confirmation" required placeholder="تایید بروزرسانی اجباری"></label></div><button class="btn btn-danger" name="action" value="run_seed">بروزرسانی اجباری</button></form></section>
<section class="card"><h2>لاگ Seedها</h2><div class="table-wrap"><table><thead><tr><th>#</th><th>Seed</th><th>حالت</th><th>وضعیت</th><th>درج</th><th>ردشده</th><th>خطا</th><th>پیام</th></tr></thead><tbody><?php foreach($logs as $log):?><tr><td><?=e($log['id'])?></td><td><?=e($log['seed_group'])?></td><td><?=e($log['mode'])?></td><td><?=e($log['status'])?></td><td><?=e($log['inserted_count'])?></td><td><?=e($log['skipped_count'])?></td><td><?=e($log['error_count'])?></td><td><?=e($log['message'])?></td></tr><?php endforeach?></tbody></table></div></section>
</section></main></div><script src="/assets/js/app.js"></script></body></html>
