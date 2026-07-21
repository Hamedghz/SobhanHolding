<?php
require_once __DIR__ . '/includes/letters.php';
LetterModule::requireCapability('settings');
$id = (int)($_GET['id'] ?? 0);
$head = $id > 0 ? Database::fetch('SELECT * FROM letter_letterheads WHERE id=? LIMIT 1', [$id]) : null;
if (!$head) {
    http_response_code(404);
    exit('سربرگ در دسترس نیست.');
}
$letter = array_merge($head, [
    'id' => 0,
    'letterhead_id' => $id,
    'paper_size' => 'A4',
    'orientation' => 'portrait',
    'letter_number' => 'SH-00001',
    'letter_date' => date('Y-m-d'),
    'subject' => 'پیش‌نمایش چاپ سربرگ سازمانی',
    'recipient_name' => 'نام گیرنده',
    'recipient_title' => 'سمت گیرنده',
    'recipient_organization' => 'سازمان گیرنده',
    'sender_unit' => 'دبیرخانه',
    'body_html' => '<p>این متن نمونه برای بررسی جای‌گذاری هدر، فوتر، حاشیه‌ها و کیفیت سربرگ در چاپ و خروجی PDF است.</p><p>نسخه نهایی نامه با متن واقعی و متغیرهای سازمانی تولید می‌شود.</p>',
    'body_delta_json' => null,
    'final_html' => null,
    'status' => 'draft',
    'attachment_count' => 0,
    'signature_id' => null,
    'signature_path' => null,
    'stamp_path' => null,
    'signer_name' => 'نام امضاکننده',
    'signer_title' => 'عنوان سازمانی',
    'creator_name' => (string)(Auth::user()['name'] ?? ''),
]);
echo letter_document_page($letter, false, false);
