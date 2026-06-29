<?php
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Response.php';
require_once __DIR__ . '/../../core/Upload.php';
require_once __DIR__ . '/../../core/LetterModule.php';
$adminExtraStylesheets = array_values(array_unique(array_merge($adminExtraStylesheets ?? [], ['/assets/css/letters.css'])));
$adminExtraScripts = array_values(array_unique(array_merge($adminExtraScripts ?? [], ['/assets/js/letters.js'])));

function letter_setting(string $key, string $default = ''): string
{
    return setting('letter_' . $key, $default);
}

function letter_asset_url(string $type, int $id, string $field, int $letterId = 0): string
{
    return '/admin/letter-asset.php?type=' . rawurlencode($type) . '&id=' . $id . '&field=' . rawurlencode($field) . ($letterId > 0 ? '&letter_id=' . $letterId : '');
}

function letter_private_upload(array $file, string $category, array $extensions, int $maxBytes): array
{
    $result = Upload::save($file, 'uploads/letters/' . $category, $extensions, $maxBytes, false);
    if (!$result['ok']) return $result;
    $path = dirname(__DIR__, 2) . str_replace('/', DIRECTORY_SEPARATOR, $result['file_path']);
    $mime = (string)($result['mime_type'] ?? '');
    $allowedMimes = [
        'image/jpeg', 'image/png', 'image/webp', 'application/pdf', 'text/plain',
        'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];
    if (!in_array($mime, $allowedMimes, true)) {
        if (is_file($path)) @unlink($path);
        return ['ok' => false, 'error' => 'نوع واقعی فایل مجاز نیست.'];
    }
    try {require_once __DIR__ . '/../../lib/FileBackupService.php';$result['backup_id']=FileBackupService::registerSavedFile($result['file_path'],$result['original_name'],$result['mime_type'],$result['file_size']);}
    catch(Throwable $e){error_log('Letter backup registration: '.$e->getMessage());$result['backup_id']=null;}
    return $result;
}

function letter_embedded_asset(?string $path): string
{
    if (!$path) return '';
    $root = realpath(dirname(__DIR__, 2));
    $allowed = $root ? realpath($root . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'letters') : false;
    $full = $root ? realpath($root . str_replace('/', DIRECTORY_SEPARATOR, $path)) : false;
    if (!$full || !$allowed || !str_starts_with(strtolower($full), strtolower($allowed . DIRECTORY_SEPARATOR)) || !is_file($full)) return '';
    $mime = function_exists('mime_content_type') ? (mime_content_type($full) ?: '') : '';
    if (!in_array($mime, ['image/png','image/jpeg','image/webp'], true)) return '';
    return 'data:' . $mime . ';base64,' . base64_encode((string)file_get_contents($full));
}

function letter_document_fragment(array $letter, bool $embedAssets = false): string
{
    if (!empty($letter['final_html']) && in_array($letter['status'] ?? '', ['signed','issued','archived'], true)) {
        $snapshot = (string)$letter['final_html'];
        if ($embedAssets) {
            $assets = [
                ['letterhead','logo_path'], ['letterhead','background_path'], ['signature','signature_path'], ['signature','stamp_path'],
            ];
            foreach ($assets as [$type,$field]) {
                if (empty($letter[$field])) continue;
                $entityId = (int)$letter[$type === 'letterhead' ? 'letterhead_id' : 'signature_id'];
                $url = letter_asset_url($type, $entityId, $field, (int)($letter['id'] ?? 0));
                $snapshot = str_replace([$url, e($url)], letter_embedded_asset($letter[$field]), $snapshot);
            }
        }
        return $snapshot;
    }
    $paper = in_array($letter['paper_size'] ?? '', ['A4', 'A5'], true) ? $letter['paper_size'] : 'A4';
    $orientation = ($letter['orientation'] ?? '') === 'landscape' ? 'landscape' : 'portrait';
    $logo = !empty($letter['logo_path']) ? ($embedAssets ? letter_embedded_asset($letter['logo_path']) : letter_asset_url('letterhead', (int)$letter['letterhead_id'], 'logo_path', (int)($letter['id'] ?? 0))) : '';
    $background = !empty($letter['background_path']) ? ($embedAssets ? letter_embedded_asset($letter['background_path']) : letter_asset_url('letterhead', (int)$letter['letterhead_id'], 'background_path', (int)($letter['id'] ?? 0))) : '';
    $signature = !empty($letter['signature_path']) ? ($embedAssets ? letter_embedded_asset($letter['signature_path']) : letter_asset_url('signature', (int)$letter['signature_id'], 'signature_path', (int)($letter['id'] ?? 0))) : '';
    $stamp = !empty($letter['stamp_path']) ? ($embedAssets ? letter_embedded_asset($letter['stamp_path']) : letter_asset_url('signature', (int)$letter['signature_id'], 'stamp_path', (int)($letter['id'] ?? 0))) : '';
    $header = LetterModule::sanitizeHtml((string)($letter['header_html'] ?? ''));
    $footer = LetterModule::sanitizeHtml((string)($letter['footer_html'] ?? ''));
    $body = LetterModule::renderBody($letter);
    $company = (string)($letter['company_name'] ?: setting('company_name', 'شرکت پخش سبحان'));
    ob_start(); ?>
    <article class="official-letter paper-<?= e(strtolower($paper)) ?> orientation-<?= e($orientation) ?>" data-paper="<?= e($paper) ?>" data-orientation="<?= e($orientation) ?>"<?= $background ? ' style="background-image:url(\'' . e($background) . '\')"' : '' ?>>
        <?php if (!empty($letter['watermark_text'])): ?><div class="letter-watermark"><?= e($letter['watermark_text']) ?></div><?php endif; ?>
        <header class="letter-document-header">
            <?php if ($header !== ''): ?><?= $header ?><?php else: ?>
                <div class="letter-brand"><?php if ($logo): ?><img src="<?= e($logo) ?>" alt="نشان سازمان"><?php endif; ?><div><strong><?= e($company) ?></strong><small><?= nl2br(e((string)($letter['contact_info'] ?? ''))) ?></small></div></div>
            <?php endif; ?>
            <dl class="letter-meta"><div><dt>شماره:</dt><dd><?= e($letter['letter_number'] ?: 'پس از صدور') ?></dd></div><div><dt>تاریخ:</dt><dd><?= e(format_jalali_date((string)$letter['letter_date'])) ?></dd></div><div><dt>پیوست:</dt><dd><?= !empty($letter['attachment_count']) ? e((string)$letter['attachment_count']) : 'ندارد' ?></dd></div></dl>
        </header>
        <section class="letter-address">
            <p>موضوع: <strong><?= e($letter['subject']) ?></strong></p>
            <p><?= e(trim((string)($letter['recipient_title'] ?? '') . ' ' . (string)$letter['recipient_name'])) ?></p>
            <?php if (!empty($letter['recipient_organization'])): ?><p><?= e($letter['recipient_organization']) ?></p><?php endif; ?>
            <p>با سلام و احترام،</p>
        </section>
        <section class="letter-body-content"><?= $body ?></section>
        <section class="letter-signature-block">
            <div><strong><?= e((string)($letter['signer_name'] ?? '')) ?></strong><span><?= e((string)($letter['signer_title'] ?? '')) ?></span></div>
            <?php if ($signature): ?><img class="signature-image" src="<?= e($signature) ?>" alt="امضا"><?php endif; ?>
            <?php if ($stamp): ?><img class="stamp-image" src="<?= e($stamp) ?>" alt="مهر"><?php endif; ?>
        </section>
        <footer class="letter-document-footer"><?php if ($footer !== ''): ?><?= $footer ?><?php else: ?><small><?= e((string)($letter['contact_info'] ?? '')) ?></small><?php endif; ?></footer>
    </article>
    <?php return (string)ob_get_clean();
}

function letter_document_page(array $letter, bool $autoPrint = false, bool $embedAssets = false): string
{
    $paper = in_array($letter['paper_size'] ?? '', ['A4', 'A5'], true) ? $letter['paper_size'] : 'A4';
    $orientation = ($letter['orientation'] ?? '') === 'landscape' ? 'landscape' : 'portrait';
    $margins = [
        max(5, min(40, (int)letter_setting('margin_top', '18'))), max(5, min(40, (int)letter_setting('margin_right', '18'))),
        max(5, min(40, (int)letter_setting('margin_bottom', '18'))), max(5, min(40, (int)letter_setting('margin_left', '18'))),
    ];
    $font = preg_replace('/[^\p{L}\p{N}\s,_-]/u', '', letter_setting('default_font', 'Tahoma, sans-serif')) ?: 'Tahoma, sans-serif';
    $fontSize = max(10, min(24, (int)letter_setting('default_font_size', '14')));
    $fragment = letter_document_fragment($letter, $embedAssets);
    if ($embedAssets) $fragment = preg_replace('/(<img\b[^>]*\bsrc\s*=\s*["\'])https?:\/\/[^"\']*(["\'])/iu', '$1$2', $fragment) ?? $fragment;
    $printScript = $autoPrint ? '<script>addEventListener("load",()=>setTimeout(()=>window.print(),250));</script>' : '';
    $baseStyles = $embedAssets ? (string)@file_get_contents(dirname(__DIR__, 2) . '/assets/css/letters.css') : '';
    $stylesheet = $embedAssets ? '<style>' . $baseStyles . '</style>' : '<link rel="stylesheet" href="/assets/css/letters.css">';
    return '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . e((string)$letter['subject']) . '</title>' . $stylesheet . '<style>@page{size:' . e($paper) . ' ' . e($orientation) . ';margin:' . implode('mm ', $margins) . 'mm}.official-letter{font-family:' . e($font) . ';font-size:' . $fontSize . 'px}</style></head><body class="letter-output-page">' . $fragment . $printScript . '</body></html>';
}

function letter_upload_attachment(int $letterId, array $file): void
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return;
    $allowed = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'webp', 'txt'];
    $upload = letter_private_upload($file, 'attachments', $allowed, 10 * 1024 * 1024);
    if (!$upload['ok']) throw new InvalidArgumentException($upload['error']);
    Database::execute('INSERT INTO organizational_letter_attachments(letter_id,original_name,stored_name,file_path,mime_type,file_size,uploaded_by,created_at) VALUES(?,?,?,?,?,?,?,NOW())', [
        $letterId, $upload['original_name'], $upload['stored_name'], $upload['file_path'], $upload['mime_type'], $upload['file_size'], (int)Auth::user()['id'],
    ]);
    LetterModule::log($letterId, 'attachment_added', null, null, 'پیوست افزوده شد: ' . mb_substr((string)$upload['original_name'], 0, 180));
}

function letter_transition(int $letterId, string $action): void
{
    $letter = LetterModule::load($letterId);
    if (!$letter || !LetterModule::canViewLetter($letter)) throw new InvalidArgumentException('نامه مورد نظر در دسترس نیست.');
    $from = $letter['status'];
    $to = $from;
    $fields = [];
    $values = [];
    if ($action === 'request_signature' && $from === 'draft' && LetterModule::can('edit')) {
        if (empty($letter['signature_id'])) throw new InvalidArgumentException('پیش از ارسال برای امضا، امضاکننده را انتخاب کنید.');
        $to = 'pending_signature';
    }
    elseif ($action === 'sign' && in_array($from, ['draft', 'pending_signature'], true) && LetterModule::can('sign')) {
        if (empty($letter['signature_id'])) throw new InvalidArgumentException('پیش از امضا، امضاکننده را برای نامه انتخاب کنید.');
        $to = 'signed'; $fields[] = 'approved_by=?'; $values[] = (int)Auth::user()['id'];
    }
    elseif ($action === 'issue' && $from === 'signed' && LetterModule::can('issue')) {
        $to = 'issued';
        if (empty($letter['letter_number'])) {
            if (letter_setting('auto_numbering', '1') !== '1') throw new InvalidArgumentException('شماره نامه پیش از صدور باید ثبت شود.');
            $number = LetterModule::nextNumber(Database::connection());
            $fields[] = 'letter_number=?';
            $values[] = $number;
            $letter['letter_number'] = $number;
        }
        $fields[] = 'issued_at=NOW()';
    } elseif ($action === 'archive' && $from === 'issued' && LetterModule::can('archive')) { $to = 'archived'; $fields[] = 'archived_at=NOW()'; }
    elseif ($action === 'cancel' && !in_array($from, ['archived', 'cancelled'], true) && (LetterModule::can('issue') || (int)$letter['created_by'] === (int)Auth::user()['id'])) $to = 'cancelled';
    else throw new InvalidArgumentException('این تغییر وضعیت برای نامه فعلی مجاز نیست.');
    $letter['status'] = $to;
    $letter['attachment_count'] = (int)(Database::fetch('SELECT COUNT(*) count FROM organizational_letter_attachments WHERE letter_id=?', [$letterId])['count'] ?? 0);
    $letter['approved_by'] = $action === 'sign' ? (int)Auth::user()['id'] : $letter['approved_by'];
    if ($action === 'issue') $letter['final_html'] = null;
    $final = in_array($to, ['signed', 'issued', 'archived'], true) ? letter_document_fragment($letter) : null;
    $fields[] = 'status=?'; $values[] = $to;
    if ($final !== null) { $fields[] = 'final_html=?'; $values[] = $final; }
    $values[] = $letterId;
    $values[] = $from;
    $stmt = Database::connection()->prepare('UPDATE organizational_letters SET ' . implode(',', $fields) . ',updated_at=NOW() WHERE id=? AND status=?');
    $stmt->execute($values);
    if ($stmt->rowCount() !== 1) throw new RuntimeException('letter_status_changed_concurrently');
    LetterModule::log($letterId, $action, $from, $to);
}
