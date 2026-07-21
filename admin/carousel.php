<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Upload.php';
require_once __DIR__ . '/../core/CarouselModule.php';

Auth::requirePermission('carousel', 'view');
$pageTitle = 'مدیریت اسلایدر و بنرها';
$edit = null;

if (isset($_GET['edit'])) {
    $edit = Database::fetch('SELECT * FROM carousel_items WHERE id=?', [(int)$_GET['edit']]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('اعتبار درخواست منقضی شده است.', 'danger');
        redirect('/admin/carousel.php');
    }
    $id = max(0, (int)($_POST['id'] ?? 0));
    $action = (string)($_POST['action'] ?? 'save');
    if (!Auth::can('carousel', $action === 'save' ? ($id ? 'edit' : 'create') : 'edit')) {
        flash('دسترسی کافی برای این عملیات ندارید.', 'danger');
        redirect('/admin/carousel.php');
    }

    try {
        if (in_array($action, ['disable', 'enable'], true)) {
            Database::execute('UPDATE carousel_items SET status=?,updated_at=NOW() WHERE id=?', [$action === 'enable' ? 'active' : 'disabled', $id]);
            Auth::log((int)Auth::user()['id'], 'carousel_' . $action, 'carousel_items', $id);
            flash($action === 'enable' ? 'اسلاید فعال شد.' : 'اسلاید بدون حذف داده غیرفعال شد.');
            redirect('/admin/carousel.php');
        }

        $title = trim((string)($_POST['title'] ?? ''));
        if ($title === '') throw new InvalidArgumentException('عنوان اسلاید الزامی است.');
        $current = $id ? Database::fetch('SELECT * FROM carousel_items WHERE id=?', [$id]) : null;
        if ($id && !$current) throw new InvalidArgumentException('اسلاید موردنظر پیدا نشد.');
        $image = (string)($current['image_path'] ?? '');
        $mobileImage = (string)($current['mobile_image_path'] ?? '');
        foreach (['image' => &$image, 'mobile_image' => &$mobileImage] as $field => &$target) {
            if (!empty($_FILES[$field]['name'])) {
                $upload = Upload::save($_FILES[$field], 'uploads/carousel', Upload::IMAGE_EXTENSIONS, 8 * 1024 * 1024);
                if (!$upload['ok']) throw new InvalidArgumentException($upload['error']);
                $target = $upload['file_path'];
            }
        }
        unset($target);
        if ($image === '' && $mobileImage === '') throw new InvalidArgumentException('حداقل یک تصویر دسکتاپ یا موبایل لازم است.');

        $link = CarouselModule::safeLink((string)($_POST['button_link'] ?? ''));
        if (trim((string)($_POST['button_link'] ?? '')) !== '' && $link === '') throw new InvalidArgumentException('لینک اسلاید معتبر یا امن نیست.');
        $startsAt = app_datetime_to_iso($_POST['starts_at'] ?? '') ?: null;
        $endsAt = app_datetime_to_iso($_POST['ends_at'] ?? '') ?: null;
        if ($startsAt && $endsAt && $endsAt < $startsAt) throw new InvalidArgumentException('زمان پایان باید بعد از زمان شروع باشد.');
        $data = [
            $title,
            trim((string)($_POST['description'] ?? '')),
            $image,
            $mobileImage,
            trim((string)($_POST['alt_text'] ?? '')) ?: $title,
            trim((string)($_POST['button_text'] ?? '')),
            $link,
            ($_POST['link_target'] ?? '') === '_blank' ? '_blank' : '_self',
            in_array($_POST['placement'] ?? '', ['homepage'], true) ? $_POST['placement'] : 'homepage',
            in_array($_POST['item_type'] ?? '', ['slider', 'banner'], true) ? $_POST['item_type'] : 'slider',
            $startsAt,
            $endsAt,
            max(0, (int)($_POST['sort_order'] ?? 0)),
            ($_POST['status'] ?? '') === 'disabled' ? 'disabled' : 'active',
        ];
        if ($id) {
            Database::execute('UPDATE carousel_items SET title=?,description=?,image_path=?,mobile_image_path=?,alt_text=?,button_text=?,button_link=?,link_target=?,placement=?,item_type=?,starts_at=?,ends_at=?,sort_order=?,status=?,updated_at=NOW() WHERE id=?', [...$data, $id]);
        } else {
            Database::execute('INSERT INTO carousel_items(title,description,image_path,mobile_image_path,alt_text,button_text,button_link,link_target,placement,item_type,starts_at,ends_at,sort_order,status,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())', $data);
            $id = (int)Database::lastInsertId();
        }
        Auth::log((int)Auth::user()['id'], 'carousel_saved', 'carousel_items', $id);
        flash('اسلاید با موفقیت ذخیره شد.');
    } catch (InvalidArgumentException $error) {
        flash($error->getMessage(), 'danger');
    } catch (Throwable $error) {
        error_log('Carousel save [' . get_class($error) . ']');
        flash('ذخیره اسلاید انجام نشد. لطفاً دوباره تلاش کنید.', 'danger');
    }
    redirect('/admin/carousel.php' . ($id ? '?edit=' . $id : ''));
}

$items = Database::fetchAll('SELECT * FROM carousel_items ORDER BY sort_order ASC,id DESC');
require __DIR__ . '/../views/partials/admin-header.php';
?>
<div class="section-heading-row"><div><h1>اسلایدر و بنرهای صفحه اصلی</h1><p class="muted">انتشار زمان‌بندی‌شده، تصویر موبایل و لینک امن از همین منبع به صفحه اصلی متصل است.</p></div><a class="btn" href="/">مشاهده صفحه اصلی</a></div>
<form class="card admin-form" method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= e(Auth::csrfToken()) ?>">
    <input type="hidden" name="id" value="<?= e((string)($edit['id'] ?? '')) ?>">
    <input type="hidden" name="action" value="save">
    <div class="grid grid-3">
        <label class="form-field"><span>عنوان</span><input name="title" value="<?= e($edit['title'] ?? '') ?>" required></label>
        <label class="form-field"><span>تصویر دسکتاپ</span><input type="file" name="image" accept="image/png,image/jpeg,image/webp"><?php if (!empty($edit['image_path'])): ?><small><?= e($edit['image_path']) ?></small><?php endif; ?></label>
        <label class="form-field"><span>تصویر موبایل</span><input type="file" name="mobile_image" accept="image/png,image/jpeg,image/webp"><?php if (!empty($edit['mobile_image_path'])): ?><small><?= e($edit['mobile_image_path']) ?></small><?php endif; ?></label>
        <label class="form-field"><span>متن جایگزین تصویر</span><input name="alt_text" value="<?= e($edit['alt_text'] ?? '') ?>"></label>
        <label class="form-field"><span>متن دکمه</span><input name="button_text" value="<?= e($edit['button_text'] ?? '') ?>"></label>
        <label class="form-field"><span>لینک دکمه</span><input dir="ltr" name="button_link" value="<?= e($edit['button_link'] ?? '') ?>" placeholder="/login.php یا https://..."></label>
        <label class="form-field"><span>نحوه بازشدن لینک</span><select name="link_target"><option value="_self">همین صفحه</option><option value="_blank" <?= ($edit['link_target'] ?? '') === '_blank' ? 'selected' : '' ?>>صفحه جدید</option></select></label>
        <label class="form-field"><span>نوع آیتم</span><select name="item_type"><option value="slider">اسلایدر</option><option value="banner" <?= ($edit['item_type'] ?? '') === 'banner' ? 'selected' : '' ?>>بنر</option></select></label>
        <label class="form-field"><span>موقعیت</span><select name="placement"><option value="homepage">صفحه اصلی</option></select></label>
        <label class="form-field"><span>شروع انتشار</span><?= app_date_input('starts_at', $edit['starts_at'] ?? null, ['datetime' => true]) ?></label>
        <label class="form-field"><span>پایان انتشار</span><?= app_date_input('ends_at', $edit['ends_at'] ?? null, ['datetime' => true]) ?></label>
        <label class="form-field"><span>ترتیب</span><input type="number" min="0" name="sort_order" value="<?= e((string)($edit['sort_order'] ?? 0)) ?>"></label>
        <label class="form-field"><span>وضعیت</span><select name="status"><option value="active">فعال</option><option value="disabled" <?= ($edit['status'] ?? '') === 'disabled' ? 'selected' : '' ?>>غیرفعال</option></select></label>
        <label class="form-field grid-span-2"><span>زیرعنوان / توضیحات</span><textarea name="description"><?= e($edit['description'] ?? '') ?></textarea></label>
    </div>
    <div class="form-actions"><button class="btn btn-primary">ذخیره</button><a class="btn" href="/admin/carousel.php">آیتم جدید</a></div>
</form>
<section class="card"><h2>آیتم‌های ثبت‌شده</h2><div class="table-wrap"><table><thead><tr><th>پیش‌نمایش</th><th>عنوان</th><th>نوع / زمان‌بندی</th><th>ترتیب</th><th>وضعیت</th><th>عملیات</th></tr></thead><tbody>
<?php foreach ($items as $item): ?><tr><td><?php $preview = CarouselModule::safeImagePath((string)($item['image_path'] ?: $item['mobile_image_path'])); ?><?php if ($preview): ?><img src="<?= e($preview) ?>" alt="" loading="lazy" style="width:96px;height:54px;object-fit:cover;border-radius:8px"><?php else: ?>بدون تصویر معتبر<?php endif; ?></td><td><strong><?= e($item['title']) ?></strong><small><?= e($item['alt_text'] ?: 'بدون متن جایگزین') ?></small></td><td><?= e($item['item_type'] === 'banner' ? 'بنر' : 'اسلایدر') ?><small><?= e(($item['starts_at'] ?: 'بدون شروع') . ' تا ' . ($item['ends_at'] ?: 'بدون پایان')) ?></small></td><td><?= e((string)$item['sort_order']) ?></td><td><span class="badge"><?= $item['status'] === 'active' ? 'فعال' : 'غیرفعال' ?></span></td><td><div class="row-actions"><a class="btn btn-small" href="?edit=<?= (int)$item['id'] ?>">ویرایش</a><form method="post"><input type="hidden" name="csrf_token" value="<?= e(Auth::csrfToken()) ?>"><input type="hidden" name="id" value="<?= (int)$item['id'] ?>"><button class="btn btn-small" name="action" value="<?= $item['status'] === 'active' ? 'disable' : 'enable' ?>"><?= $item['status'] === 'active' ? 'غیرفعال‌سازی' : 'فعال‌سازی' ?></button></form></div></td></tr><?php endforeach; ?>
<?php if (!$items): ?><tr><td colspan="6">هنوز اسلایدی ثبت نشده است.</td></tr><?php endif; ?></tbody></table></div></section>
<?php require __DIR__ . '/../views/partials/admin-footer.php'; ?>
