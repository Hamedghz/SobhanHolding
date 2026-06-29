<?php
require_once __DIR__ . '/includes/letters.php';
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
LetterModule::requireCapability($id > 0 ? 'edit' : 'create');
$letter = $id ? LetterModule::load($id) : null;
if ($id && (!$letter || !LetterModule::canViewLetter($letter) || $letter['status'] !== 'draft')) { flash('فقط پیش‌نویس‌های در دسترس قابل ویرایش هستند.', 'danger'); redirect('/admin/letters.php'); }
$pageTitle = $letter ? 'ویرایش پیش‌نویس نامه' : 'ایجاد نامه سازمانی';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) { flash('اعتبار درخواست منقضی شده است.', 'danger'); redirect($id ? '/admin/letter-create.php?id='.$id : '/admin/letter-create.php'); }
    try {
        $subject = trim((string)($_POST['subject'] ?? ''));
        $recipient = trim((string)($_POST['recipient_name'] ?? ''));
        $date = trim((string)($_POST['letter_date'] ?? ''));
        $body = LetterModule::sanitizeHtml($_POST['body_html'] ?? '');
        if ($subject === '' || mb_strlen($subject) > 255) throw new InvalidArgumentException('موضوع نامه الزامی و حداکثر ۲۵۵ نویسه است.');
        if ($recipient === '' || mb_strlen($recipient) > 190) throw new InvalidArgumentException('نام گیرنده الزامی است.');
        $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $date);
        if (!$parsed || $parsed->format('Y-m-d') !== $date) throw new InvalidArgumentException('تاریخ نامه معتبر نیست.');
        if ($body === '') throw new InvalidArgumentException('متن نامه را وارد کنید.');
        $paper = in_array($_POST['paper_size'] ?? '', ['A4','A5'], true) ? $_POST['paper_size'] : 'A4';
        $orientation = in_array($_POST['orientation'] ?? '', ['portrait','landscape'], true) ? $_POST['orientation'] : 'portrait';
        $importance = array_key_exists($_POST['importance'] ?? '', LetterModule::IMPORTANCE) ? $_POST['importance'] : 'normal';
        $confidentiality = array_key_exists($_POST['confidentiality'] ?? '', LetterModule::CONFIDENTIALITY) ? $_POST['confidentiality'] : 'normal';
        if ($confidentiality !== 'normal' && !LetterModule::can('confidential') && !Auth::isAdmin()) throw new InvalidArgumentException('اجازه ایجاد نامه محرمانه را ندارید.');
        $letterNumber = trim((string)($_POST['letter_number'] ?? '')) ?: null;
        if ($letterNumber !== null && !LetterModule::can('issue')) throw new InvalidArgumentException('ثبت یا تغییر شماره نامه فقط برای دبیرخانه مجاز است.');
        if ($letterNumber && Database::fetch('SELECT id FROM organizational_letters WHERE letter_number=? AND id<>? LIMIT 1', [$letterNumber, $id])) throw new InvalidArgumentException('این شماره نامه قبلاً استفاده شده است.');
        $values = [$letterNumber,$date,$subject,$recipient,trim((string)($_POST['recipient_title']??'')),trim((string)($_POST['recipient_organization']??'')),trim((string)($_POST['sender_unit']??'')),(int)($_POST['template_id']??0)?:null,(int)($_POST['letterhead_id']??0)?:null,(int)($_POST['signature_id']??0)?:null,$body,$paper,$orientation,$importance,$confidentiality];
        if ($id) {
            Database::execute('UPDATE organizational_letters SET letter_number=?,letter_date=?,subject=?,recipient_name=?,recipient_title=?,recipient_organization=?,sender_unit=?,template_id=?,letterhead_id=?,signature_id=?,body_html=?,paper_size=?,orientation=?,importance=?,confidentiality=?,final_html=NULL,updated_at=NOW() WHERE id=? AND status="draft"', [...$values,$id]);
            LetterModule::log($id, 'updated', 'draft', 'draft');
        } else {
            Database::execute('INSERT INTO organizational_letters(letter_number,letter_date,subject,recipient_name,recipient_title,recipient_organization,sender_unit,template_id,letterhead_id,signature_id,body_html,paper_size,orientation,importance,confidentiality,status,created_by,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,"draft",?,NOW(),NOW())', [...$values,(int)Auth::user()['id']]);
            $id = (int)Database::lastInsertId();
            LetterModule::log($id, 'created', null, 'draft');
        }
        if (!empty($_FILES['attachment'])) letter_upload_attachment($id, $_FILES['attachment']);
        if (($_POST['submit_action'] ?? '') === 'request_signature') letter_transition($id, 'request_signature');
        flash(($_POST['submit_action'] ?? '') === 'request_signature' ? 'نامه ذخیره و برای امضا ارسال شد.' : 'پیش‌نویس نامه ذخیره شد.');
        redirect('/admin/letter-view.php?id=' . $id);
    } catch (InvalidArgumentException $e) { flash($e->getMessage(), 'danger'); }
    catch (Throwable $e) { error_log('Letter save: ' . $e->getMessage()); flash('ذخیره نامه انجام نشد. لطفاً اطلاعات را بررسی و دوباره تلاش کنید.', 'danger'); }
    redirect($id ? '/admin/letter-create.php?id=' . $id : '/admin/letter-create.php');
}

$templates = Database::fetchAll('SELECT * FROM letter_templates WHERE is_active=1' . ($letter && $letter['template_id'] ? ' OR id='.(int)$letter['template_id'] : '') . ' ORDER BY title');
$letterheads = Database::fetchAll('SELECT id,title,company_name FROM letter_letterheads WHERE is_active=1' . ($letter && $letter['letterhead_id'] ? ' OR id='.(int)$letter['letterhead_id'] : '') . ' ORDER BY title');
$signatures = Database::fetchAll('SELECT id,signer_name,signer_title FROM letter_signatures WHERE is_active=1' . ($letter && $letter['signature_id'] ? ' OR id='.(int)$letter['signature_id'] : '') . ' ORDER BY signer_name');
$defaults = [
    'letter_date'=>date('Y-m-d'),'paper_size'=>letter_setting('default_paper_size','A4'),'orientation'=>letter_setting('default_orientation','portrait'),
    'importance'=>'normal','confidentiality'=>'normal','sender_unit'=>(string)(Auth::user()['department']??''),'body_html'=>'<p>متن نامه را اینجا بنویسید.</p>',
];
$form = array_merge($defaults, $letter ?: []);
$form['body_html'] = LetterModule::sanitizeHtml($form['body_html']);
$templatePayload = [];
foreach ($templates as $template) $templatePayload[(string)$template['id']] = ['subject'=>$template['default_subject'],'body'=>$template['default_body'],'letterhead_id'=>$template['letterhead_id'],'signature_id'=>$template['signature_id'],'paper_size'=>$template['paper_size'],'orientation'=>$template['orientation']];
require __DIR__ . '/../views/partials/admin-header.php'; ?>
<div class="letters-page-head"><div><h1><?= e($pageTitle) ?></h1><p>اطلاعات نامه و ظاهر نسخه رسمی را در یک مرحله تنظیم کنید.</p></div><a class="btn" href="/admin/letters.php">بازگشت به نامه‌ها</a></div>
<form method="post" enctype="multipart/form-data" class="letter-compose" data-letter-form>
<input type="hidden" name="csrf_token" value="<?= e(Auth::csrfToken()) ?>"><input type="hidden" name="id" value="<?= e((string)$id) ?>"><textarea name="body_html" data-editor-input hidden><?= e($form['body_html']) ?></textarea>
<section class="card letter-form-section"><header><div><span class="letter-step">۱</span><h2>ساختار و قالب</h2></div></header><div class="grid grid-3">
    <label class="form-field"><span>قالب نامه</span><select name="template_id" data-template-select><option value="">بدون قالب</option><?php foreach($templates as $t):?><option value="<?=e((string)$t['id'])?>" <?=(int)($form['template_id']??0)===(int)$t['id']?'selected':''?>><?=e($t['title'])?></option><?php endforeach;?></select></label>
    <label class="form-field"><span>سربرگ</span><select name="letterhead_id" data-letterhead-select><option value="">بدون سربرگ</option><?php foreach($letterheads as $h):?><option value="<?=e((string)$h['id'])?>" <?=(int)($form['letterhead_id']??0)===(int)$h['id']?'selected':''?>><?=e($h['title'])?></option><?php endforeach;?></select></label>
    <label class="form-field"><span>امضاکننده</span><select name="signature_id" data-signature-select><option value="">انتخاب نشده</option><?php foreach($signatures as $s):?><option value="<?=e((string)$s['id'])?>" <?=(int)($form['signature_id']??0)===(int)$s['id']?'selected':''?>><?=e($s['signer_name'].' — '.$s['signer_title'])?></option><?php endforeach;?></select></label>
</div></section>
<section class="card letter-form-section"><header><div><span class="letter-step">۲</span><h2>اطلاعات نامه</h2></div></header><div class="grid grid-3">
    <label class="form-field"><span>شماره نامه</span><input name="letter_number" value="<?=e($form['letter_number']??'')?>" <?=LetterModule::can('issue')?'':'readonly'?> placeholder="هنگام صدور خودکار درج می‌شود"></label><label class="form-field"><span>تاریخ نامه</span><input type="date" name="letter_date" value="<?=e($form['letter_date'])?>" required></label><label class="form-field grid-span-2"><span>موضوع</span><input name="subject" value="<?=e($form['subject']??'')?>" maxlength="255" required data-subject-input></label>
    <label class="form-field"><span>نام گیرنده</span><input name="recipient_name" value="<?=e($form['recipient_name']??'')?>" required></label><label class="form-field"><span>سمت گیرنده</span><input name="recipient_title" value="<?=e($form['recipient_title']??'')?>"></label><label class="form-field"><span>سازمان گیرنده</span><input name="recipient_organization" value="<?=e($form['recipient_organization']??'')?>"></label><label class="form-field"><span>واحد صادرکننده</span><input name="sender_unit" value="<?=e($form['sender_unit']??'')?>"></label>
    <label class="form-field"><span>اهمیت</span><select name="importance"><?php foreach(LetterModule::IMPORTANCE as $v=>$l):?><option value="<?=e($v)?>" <?=($form['importance']??'normal')===$v?'selected':''?>><?=e($l)?></option><?php endforeach;?></select></label><label class="form-field"><span>محرمانگی</span><select name="confidentiality"><?php foreach(LetterModule::CONFIDENTIALITY as $v=>$l):?><option value="<?=e($v)?>" <?=($form['confidentiality']??'normal')===$v?'selected':''?>><?=e($l)?></option><?php endforeach;?></select></label>
</div></section>
<section class="card letter-form-section"><header><div><span class="letter-step">۳</span><h2>متن نامه</h2></div><button class="btn btn-small" type="button" data-preview-toggle>پیش‌نمایش</button></header>
    <div class="letter-editor" data-rich-editor><div class="editor-toolbar" role="toolbar" aria-label="ابزار ویرایش متن">
        <select data-editor-command="fontName" title="فونت"><option value="Tahoma">Tahoma</option><option value="Arial">Arial</option><option value="Vazirmatn">Vazirmatn</option><option value="IRANSans">IRANSans</option></select><select data-editor-size title="اندازه"><option value="2">کوچک</option><option value="3" selected>عادی</option><option value="4">بزرگ</option><option value="5">خیلی بزرگ</option></select>
        <button type="button" data-editor-command="bold"><b>B</b></button><button type="button" data-editor-command="italic"><i>I</i></button><button type="button" data-editor-command="underline"><u>U</u></button><input type="color" value="#111827" data-editor-color title="رنگ متن"><button type="button" data-editor-command="justifyRight">راست</button><button type="button" data-editor-command="justifyCenter">وسط</button><button type="button" data-editor-command="justifyLeft">چپ</button><button type="button" data-editor-command="insertOrderedList">۱. فهرست</button><button type="button" data-editor-command="insertUnorderedList">• فهرست</button><button type="button" data-editor-lineheight>فاصله خطوط</button><button type="button" data-editor-table>جدول</button><button type="button" data-editor-image>تصویر</button><input type="file" accept="image/png,image/jpeg,image/webp" data-editor-image-input hidden>
    </div><div class="editor-surface" contenteditable="true" dir="rtl" data-editor-surface><?= $form['body_html'] ?></div></div>
    <div class="letter-variable-hint"><strong>متغیرهای هوشمند:</strong><?php foreach(['{letter_number}','{letter_date}','{subject}','{recipient_name}','{recipient_title}','{recipient_organization}','{sender_unit}','{signer_name}','{signer_title}','{company_name}','{current_user_name}'] as $var):?><button type="button" data-insert-variable="<?=e($var)?>"><?=e($var)?></button><?php endforeach;?></div>
</section>
<section class="card letter-form-section"><header><div><span class="letter-step">۴</span><h2>چاپ و پیوست</h2></div></header><div class="grid grid-3"><label class="form-field"><span>اندازه کاغذ</span><select name="paper_size" data-paper-select><option value="A4" <?=$form['paper_size']==='A4'?'selected':''?>>A4</option><option value="A5" <?=$form['paper_size']==='A5'?'selected':''?>>A5</option></select></label><label class="form-field"><span>جهت</span><select name="orientation" data-orientation-select><option value="portrait" <?=$form['orientation']==='portrait'?'selected':''?>>عمودی</option><option value="landscape" <?=$form['orientation']==='landscape'?'selected':''?>>افقی</option></select></label><label class="form-field"><span>پیوست (حداکثر ۱۰ مگابایت)</span><input type="file" name="attachment" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.webp,.txt"></label></div></section>
<div class="letter-sticky-actions"><button class="btn" name="submit_action" value="draft">ذخیره پیش‌نویس</button><button class="btn btn-primary" name="submit_action" value="request_signature">ذخیره و ارسال برای امضا</button></div>
</form>
<dialog class="letter-preview-dialog" data-preview-dialog><header><strong>پیش‌نمایش متن</strong><button type="button" data-preview-close>×</button></header><div data-preview-content class="letter-body-content"></div></dialog>
<script type="application/json" id="letterTemplateData"><?=json_encode($templatePayload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP)?></script>
<?php require __DIR__ . '/../views/partials/admin-footer.php'; ?>
