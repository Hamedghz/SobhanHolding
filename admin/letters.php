<?php
require_once __DIR__ . '/includes/letters.php';
LetterModule::requireCapability('view');
$pageTitle = 'نامه‌های سازمانی';
$statusLabels = LetterModule::STATUSES;
$importanceLabels = LetterModule::IMPORTANCE;
$confidentialityLabels = LetterModule::CONFIDENTIALITY;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) { flash('اعتبار درخواست منقضی شده است.', 'danger'); redirect('/admin/letters.php'); }
    try {
        $id = (int)($_POST['id'] ?? 0);
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'copy') {
            if (!LetterModule::can('create')) throw new InvalidArgumentException('اجازه کپی نامه را ندارید.');
            $source = LetterModule::load($id);
            if (!$source || !LetterModule::canViewLetter($source)) throw new InvalidArgumentException('نامه در دسترس نیست.');
            Database::execute('INSERT INTO organizational_letters(letter_number,letter_date,subject,recipient_name,recipient_title,recipient_organization,sender_unit,template_id,letterhead_id,signature_id,body_html,paper_size,orientation,importance,confidentiality,status,created_by,created_at,updated_at) VALUES(NULL,CURDATE(),?,?,?,?,?,?,?,?,?,?,?,?,?,"draft",?,NOW(),NOW())', [
                'کپی - ' . $source['subject'], $source['recipient_name'], $source['recipient_title'], $source['recipient_organization'], $source['sender_unit'], $source['template_id'], $source['letterhead_id'], $source['signature_id'], $source['body_html'], $source['paper_size'], $source['orientation'], $source['importance'], $source['confidentiality'], (int)Auth::user()['id'],
            ]);
            $newId = (int)Database::lastInsertId();
            LetterModule::log($newId, 'copied', null, 'draft', 'کپی از نامه شماره ' . $id);
            flash('یک پیش‌نویس تازه از نامه ساخته شد.');
            redirect('/admin/letter-create.php?id=' . $newId);
        }
        letter_transition($id, $action);
        flash('وضعیت نامه با موفقیت به‌روزرسانی شد.');
    } catch (InvalidArgumentException $e) { flash($e->getMessage(), 'danger'); }
    catch (Throwable $e) { error_log('Letters list action: ' . $e->getMessage()); flash('انجام عملیات ممکن نشد. لطفاً دوباره تلاش کنید.', 'danger'); }
    redirect('/admin/letters.php');
}

$where = ['1=1']; $params = [];
$filters = [
    'letter_number' => 'l.letter_number LIKE ?', 'subject' => 'l.subject LIKE ?', 'recipient' => 'l.recipient_name LIKE ?',
    'organization' => 'l.recipient_organization LIKE ?', 'sender_unit' => 'l.sender_unit LIKE ?',
];
foreach ($filters as $key => $clause) if (trim((string)($_GET[$key] ?? '')) !== '') { $where[] = $clause; $params[] = '%' . trim((string)$_GET[$key]) . '%'; }
foreach (['status', 'importance', 'confidentiality'] as $key) if (trim((string)($_GET[$key] ?? '')) !== '') { $where[] = 'l.' . $key . '=?'; $params[] = $_GET[$key]; }
$dateFrom = app_date_to_iso($_GET['date_from'] ?? '');
$dateTo = app_date_to_iso($_GET['date_to'] ?? '');
if ($dateFrom) { $where[] = 'l.letter_date>=?'; $params[] = $dateFrom; }
if ($dateTo) { $where[] = 'l.letter_date<=?'; $params[] = $dateTo; }
if (!Auth::isAdmin() && !LetterModule::can('confidential')) { $where[] = '(l.confidentiality="normal" OR l.created_by=? OR l.approved_by=? OR s.user_id=?)'; $params[] = (int)Auth::user()['id']; $params[] = (int)Auth::user()['id']; $params[] = (int)Auth::user()['id']; }
$letters = Database::fetchAll('SELECT l.*,u.name creator_name,s.signer_name FROM organizational_letters l LEFT JOIN users u ON u.id=l.created_by LEFT JOIN letter_signatures s ON s.id=l.signature_id WHERE ' . implode(' AND ', $where) . ' ORDER BY l.letter_date DESC,l.id DESC LIMIT 500', $params);
require __DIR__ . '/../views/partials/admin-header.php'; ?>
<div class="letters-page-head"><div><h1>مکاتبات اداری</h1><p>صدور، امضا، چاپ و بایگانی امن نامه‌های سازمانی</p></div><?php if (LetterModule::can('create')): ?><a class="btn btn-primary" href="/admin/letter-create.php">ایجاد نامه جدید</a><?php endif; ?></div>
<nav class="letter-tabs"><a class="is-active" href="/admin/letters.php">نامه‌ها</a><?php if (LetterModule::can('settings')): ?><a href="/admin/letter-templates.php">قالب‌ها</a><a href="/admin/letter-letterheads.php">سربرگ‌ها</a><a href="/admin/letter-signatures.php">امضاها</a><a href="/admin/letter-settings.php">تنظیمات</a><?php endif; ?></nav>
<section class="card letter-filter-card"><form method="get" class="letter-filter-grid">
    <label><span>شماره نامه</span><input name="letter_number" value="<?= e($_GET['letter_number'] ?? '') ?>"></label><label><span>موضوع</span><input name="subject" value="<?= e($_GET['subject'] ?? '') ?>"></label><label><span>گیرنده</span><input name="recipient" value="<?= e($_GET['recipient'] ?? '') ?>"></label><label><span>سازمان</span><input name="organization" value="<?= e($_GET['organization'] ?? '') ?>"></label><label><span>واحد صادرکننده</span><input name="sender_unit" value="<?= e($_GET['sender_unit'] ?? '') ?>"></label>
    <label><span>از تاریخ</span><?=app_date_input('date_from',$_GET['date_from']??null)?></label><label><span>تا تاریخ</span><?=app_date_input('date_to',$_GET['date_to']??null)?></label>
    <label><span>وضعیت</span><select name="status"><option value="">همه</option><?php foreach ($statusLabels as $v=>$l): ?><option value="<?= e($v) ?>" <?= ($_GET['status'] ?? '')===$v?'selected':'' ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
    <label><span>محرمانگی</span><select name="confidentiality"><option value="">همه</option><?php foreach ($confidentialityLabels as $v=>$l): ?><option value="<?= e($v) ?>" <?= ($_GET['confidentiality'] ?? '')===$v?'selected':'' ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
    <label><span>اهمیت</span><select name="importance"><option value="">همه</option><?php foreach ($importanceLabels as $v=>$l): ?><option value="<?= e($v) ?>" <?= ($_GET['importance'] ?? '')===$v?'selected':'' ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
    <div class="form-actions"><button class="btn btn-primary">جستجو</button><a class="btn" href="/admin/letters.php">پاک‌کردن</a></div>
</form></section>
<section class="card"><div class="table-wrap"><table class="letters-table"><thead><tr><th>شماره / تاریخ</th><th>موضوع و گیرنده</th><th>واحد / سازمان</th><th>اهمیت</th><th>وضعیت</th><th>ایجادکننده</th><th>عملیات</th></tr></thead><tbody>
<?php foreach ($letters as $letter): ?><tr><td><strong><?= e($letter['letter_number'] ?: 'بدون شماره') ?></strong><small><?= e(format_jalali_date($letter['letter_date'])) ?></small></td><td><a class="letter-subject" href="/admin/letter-view.php?id=<?= e((string)$letter['id']) ?>"><?= e($letter['subject']) ?></a><small><?= e($letter['recipient_name']) ?></small></td><td><?= e($letter['sender_unit'] ?: '—') ?><small><?= e($letter['recipient_organization'] ?: '—') ?></small></td><td><span class="letter-badge importance-<?= e($letter['importance']) ?>"><?= e($importanceLabels[$letter['importance']] ?? $letter['importance']) ?></span><?php if ($letter['confidentiality'] !== 'normal'): ?><span class="letter-badge confidential"><?= e($confidentialityLabels[$letter['confidentiality']] ?? '') ?></span><?php endif; ?></td><td><span class="letter-badge status-<?= e($letter['status']) ?>"><?= e($statusLabels[$letter['status']] ?? $letter['status']) ?></span></td><td><?= e($letter['creator_name']) ?></td><td><div class="row-actions"><a class="btn btn-small" href="/admin/letter-view.php?id=<?= e((string)$letter['id']) ?>">مشاهده</a><?php if ($letter['status']==='draft' && LetterModule::can('edit')): ?><a class="btn btn-small" href="/admin/letter-create.php?id=<?= e((string)$letter['id']) ?>">ویرایش</a><?php endif; ?><a class="btn btn-small" target="_blank" href="/admin/letter-print.php?id=<?= e((string)$letter['id']) ?>">چاپ</a><a class="btn btn-small" href="/admin/letter-pdf.php?id=<?= e((string)$letter['id']) ?>">PDF</a><?php if(LetterModule::can('create')):?><form method="post"><input type="hidden" name="csrf_token" value="<?= e(Auth::csrfToken()) ?>"><input type="hidden" name="id" value="<?= e((string)$letter['id']) ?>"><button class="link-btn" name="action" value="copy">کپی</button></form><?php endif?><?php if ($letter['status']==='issued' && LetterModule::can('archive')): ?><form method="post" onsubmit="return confirm('نامه بایگانی شود؟')"><input type="hidden" name="csrf_token" value="<?= e(Auth::csrfToken()) ?>"><input type="hidden" name="id" value="<?= e((string)$letter['id']) ?>"><button class="link-btn" name="action" value="archive">بایگانی</button></form><?php endif; ?></div></td></tr><?php endforeach; ?>
<?php if (!$letters): ?><tr><td colspan="7"><div class="letter-empty"><strong>نامه‌ای پیدا نشد</strong><span>فیلترها را تغییر دهید یا یک نامه تازه بسازید.</span></div></td></tr><?php endif; ?></tbody></table></div></section>
<?php $adminExtraScripts = ['/assets/js/letters.js']; require __DIR__ . '/../views/partials/admin-footer.php'; ?>
