<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/SalesDataRepository.php';
require_once __DIR__ . '/../lib/ImportSourceRegistry.php';

Auth::requirePermission('sales_data_manage_mapping');
if (!Auth::isSuperAdmin()) {
    http_response_code(403);
    exit('این ابزار فقط برای مدیر ارشد سامانه در دسترس است.');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) throw new DomainException('اعتبار فرم منقضی شده است.');
        $source = (string)($_POST['source_module'] ?? '');
        if (!isset(ImportSourceRegistry::all()[$source])) throw new InvalidArgumentException('منبع معتبر نیست.');
        $header = trim((string)($_POST['source_header'] ?? ''));
        $key = trim((string)($_POST['normalized_key'] ?? ''));
        $type = (string)($_POST['data_type'] ?? 'string');
        if ($header === '' || !preg_match('/^[a-z][a-z0-9_]{1,190}$/', $key)) throw new InvalidArgumentException('سرستون یا کلید استاندارد معتبر نیست.');
        if (!in_array($type, ['string','decimal','date'], true)) throw new InvalidArgumentException('نوع داده معتبر نیست.');
        Database::execute(
            'INSERT INTO sales_import_column_mappings(source_module,source_header,normalized_key,required,data_type,active,created_at,updated_at)
             VALUES (?,?,?,?,?,1,NOW(),NOW())
             ON DUPLICATE KEY UPDATE normalized_key=VALUES(normalized_key),required=VALUES(required),data_type=VALUES(data_type),active=1,updated_at=NOW()',
            [$source,mb_substr($header,0,255),$key,!empty($_POST['required'])?1:0,$type]
        );
        flash('نگاشت پیشرفته ذخیره شد.');
    } catch (InvalidArgumentException|DomainException $e) {
        flash($e->getMessage(), 'danger');
    } catch (Throwable $e) {
        error_log('Import mapping: '.$e->getMessage());
        flash('ذخیره نگاشت انجام نشد.', 'danger');
    }
    redirect('/admin/sales-data-mapping.php?source='.urlencode((string)($_POST['source_module']??'')));
}
$pageTitle = 'نگاشت پیشرفته ستون‌ها';
$rows = SalesDataRepository::mappings();
$selectedSource = (string)($_GET['source'] ?? 'sales_aggregate');
$labels = ImportSourceRegistry::labels();
if (!isset($labels[$selectedSource])) $selectedSource = 'sales_aggregate';
require __DIR__ . '/../views/partials/admin-header.php';
?>
<section class="card">
    <h1><?= e($pageTitle) ?></h1>
    <p class="muted">این ابزار فقط وقتی استفاده می‌شود که تطبیق خودکار سرستون‌های الزامی کافی نباشد. نگاشت‌های استاندارد در کد حفظ می‌شوند و این بخش override افزایشی ثبت می‌کند.</p>
    <form method="post" class="admin-form">
        <input type="hidden" name="csrf_token" value="<?=e(Auth::csrfToken())?>">
        <div class="grid grid-2">
            <label><span>منبع</span><select name="source_module"><?php foreach($labels as$key=>$label):?><option value="<?=e($key)?>" <?=$key===$selectedSource?'selected':''?>><?=e($label)?></option><?php endforeach;?></select></label>
            <label><span>سرستون فایل</span><input name="source_header" maxlength="255" required></label>
            <label><span>کلید استاندارد</span><input name="normalized_key" pattern="[a-z][a-z0-9_]{1,190}" required placeholder="product_code"></label>
            <label><span>نوع داده</span><select name="data_type"><option value="string">متن</option><option value="decimal">عدد</option><option value="date">تاریخ</option></select></label>
            <label><input type="checkbox" name="required" value="1"> این سرستون الزامی است</label>
        </div>
        <button class="btn btn-primary">ثبت override نگاشت</button>
    </form>
    <?php if (!$rows): ?>
        <div class="alert alert-info">هنوز نگاشت ستونی ثبت نشده است.</div>
    <?php else: ?>
        <div class="table-responsive"><table><thead><tr><th>منبع</th><th>سرستون ورودی</th><th>کلید استاندارد</th><th>نوع داده</th><th>الزامی</th><th>فعال</th></tr></thead><tbody>
        <?php foreach ($rows as $row): ?><tr>
            <td><?= e($row['source_module']) ?></td><td><?= e($row['source_header']) ?></td><td><?= e($row['normalized_key']) ?></td><td><?= e($row['data_type']) ?></td>
            <td><?= (int)$row['required'] === 1 ? 'بله' : 'خیر' ?></td><td><?= (int)$row['active'] === 1 ? 'بله' : 'خیر' ?></td>
        </tr><?php endforeach; ?>
        </tbody></table></div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/../views/partials/admin-footer.php'; ?>
