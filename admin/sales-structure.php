<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/SalesStructureModule.php';

Auth::requirePermission('sales_structure', 'view');
$pageTitle = 'ساختار فروش، لاین و مناطق';
$currentUser = Auth::user();
$canManage = Auth::can('sales_structure', 'edit') || Auth::can('sales_structure', 'create');

$pdo = Database::connection();
SalesStructureModule::repair($pdo);

function sales_structure_code(string $value): string
{
    $value = strtoupper(trim($value));
    return preg_replace('/[^A-Z0-9_\-]/', '', $value) ?: '';
}

function sales_structure_int(?string $value): ?int
{
    $id = (int)($value ?? 0);
    return $id > 0 ? $id : null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('درخواست نامعتبر است.', 'danger');
        redirect('/admin/sales-structure.php');
    }
    if (!$canManage) {
        flash('برای تغییر ساختار فروش دسترسی ندارید.', 'danger');
        redirect('/admin/sales-structure.php');
    }

    $action = (string)($_POST['action'] ?? '');
    $pdo->beginTransaction();
    try {
        if ($action === 'save_line') {
            $id = (int)($_POST['id'] ?? 0);
            $code = sales_structure_code((string)($_POST['code'] ?? ''));
            $title = trim((string)($_POST['title'] ?? ''));
            $managerId = sales_structure_int($_POST['manager_user_id'] ?? null);
            $supervisorId = sales_structure_int($_POST['supervisor_user_id'] ?? null);
            $active = isset($_POST['active']) ? 1 : 0;
            $sortOrder = max(0, (int)($_POST['sort_order'] ?? 0));
            $description = trim((string)($_POST['description'] ?? ''));

            if ($code === '' || $title === '') throw new InvalidArgumentException('کد و عنوان لاین الزامی است.');
            if ($managerId && !Database::fetch("SELECT u.id FROM users u LEFT JOIN org_roles r ON r.id=u.org_role_id WHERE u.id=? AND u.status='active' AND (r.code='SALES_MANAGER' OR u.role_key='SALES_MANAGER')", [$managerId])) {
                throw new InvalidArgumentException('مدیر فروش انتخاب‌شده معتبر نیست.');
            }
            if ($supervisorId && !Database::fetch("SELECT u.id FROM users u LEFT JOIN org_roles r ON r.id=u.org_role_id WHERE u.id=? AND u.status='active' AND (r.code='SALES_SUPERVISOR' OR u.role_key='SALES_SUPERVISOR')", [$supervisorId])) {
                throw new InvalidArgumentException('سرپرست فروش انتخاب‌شده معتبر نیست.');
            }
            if ($supervisorId) {
                $duplicateSupervisor = Database::fetch('SELECT id,title FROM sales_lines WHERE supervisor_user_id=? AND id<>? AND active=1 LIMIT 1', [$supervisorId, $id]);
                if ($duplicateSupervisor) throw new InvalidArgumentException('این سرپرست قبلاً مسئول یک لاین فعال دیگر است.');
            }

            if ($id) {
                Database::execute('UPDATE sales_lines SET code=?,title=?,manager_user_id=?,supervisor_user_id=?,active=?,sort_order=?,description=?,updated_at=NOW() WHERE id=?', [$code,$title,$managerId,$supervisorId,$active,$sortOrder,$description,$id]);
            } else {
                Database::execute('INSERT INTO sales_lines(code,title,manager_user_id,supervisor_user_id,active,sort_order,description,created_at,updated_at) VALUES (?,?,?,?,?,?,?,NOW(),NOW())', [$code,$title,$managerId,$supervisorId,$active,$sortOrder,$description]);
                $id = (int)Database::lastInsertId();
            }

            if ($supervisorId) {
                Database::execute('UPDATE users SET sales_line=?,parent_user_id=?,organization_manager_id=?,updated_at=NOW() WHERE id=?', [$code,$managerId,$managerId,$supervisorId]);
                if ($managerId) Database::execute('INSERT IGNORE INTO manager_employees(manager_id,employee_id,assigned_by,created_at) VALUES (?,?,?,NOW())', [$managerId,$supervisorId,(int)$currentUser['id']]);
            }

            SalesStructureModule::log('save_line', 'sales_line', $id, (int)$currentUser['id'], $_POST);
            flash('لاین فروش ذخیره شد.');
        } elseif ($action === 'save_brand') {
            $id = (int)($_POST['id'] ?? 0);
            $lineId = (int)($_POST['line_id'] ?? 0);
            $brandName = trim((string)($_POST['brand_name'] ?? ''));
            $brandCode = sales_structure_code((string)($_POST['brand_code'] ?? '')) ?: null;
            $active = isset($_POST['active']) ? 1 : 0;
            $sortOrder = max(0, (int)($_POST['sort_order'] ?? 0));
            if (!$lineId || !Database::fetch('SELECT id FROM sales_lines WHERE id=?', [$lineId])) throw new InvalidArgumentException('لاین معتبر نیست.');
            if ($brandName === '') throw new InvalidArgumentException('نام برند الزامی است.');
            if ($id) {
                Database::execute('UPDATE sales_line_brands SET line_id=?,brand_code=?,brand_name=?,active=?,sort_order=?,updated_at=NOW() WHERE id=?', [$lineId,$brandCode,$brandName,$active,$sortOrder,$id]);
            } else {
                Database::execute('INSERT INTO sales_line_brands(line_id,brand_code,brand_name,active,sort_order,created_at,updated_at) VALUES (?,?,?,?,?,NOW(),NOW())', [$lineId,$brandCode,$brandName,$active,$sortOrder]);
                $id = (int)Database::lastInsertId();
            }
            SalesStructureModule::log('save_brand', 'sales_line_brand', $id, (int)$currentUser['id'], $_POST);
            flash('برند لاین ذخیره شد.');
        } elseif ($action === 'save_geography') {
            $id = (int)($_POST['id'] ?? 0);
            $type = ($_POST['type'] ?? '') === 'region' ? 'region' : 'city';
            $parentId = sales_structure_int($_POST['parent_id'] ?? null);
            $code = sales_structure_code((string)($_POST['code'] ?? ''));
            $title = trim((string)($_POST['title'] ?? ''));
            $active = isset($_POST['active']) ? 1 : 0;
            $sortOrder = max(0, (int)($_POST['sort_order'] ?? 0));
            if ($code === '' || $title === '') throw new InvalidArgumentException('کد و عنوان منطقه الزامی است.');
            if ($type === 'city') $parentId = null;
            if ($type === 'region') {
                if (!$parentId) throw new InvalidArgumentException('برای منطقه، انتخاب شهر والد الزامی است.');
                if ($parentId === $id) throw new InvalidArgumentException('منطقه نمی‌تواند والد خودش باشد.');
                if (!Database::fetch("SELECT id FROM sales_geographies WHERE id=? AND type='city'", [$parentId])) throw new InvalidArgumentException('شهر والد معتبر نیست.');
            }
            if ($id) {
                Database::execute('UPDATE sales_geographies SET parent_id=?,type=?,code=?,title=?,active=?,sort_order=?,updated_at=NOW() WHERE id=?', [$parentId,$type,$code,$title,$active,$sortOrder,$id]);
            } else {
                Database::execute('INSERT INTO sales_geographies(parent_id,type,code,title,active,sort_order,created_at,updated_at) VALUES (?,?,?,?,?,?,NOW(),NOW())', [$parentId,$type,$code,$title,$active,$sortOrder]);
                $id = (int)Database::lastInsertId();
            }
            SalesStructureModule::log('save_geography', 'sales_geography', $id, (int)$currentUser['id'], $_POST);
            flash('شهر / منطقه ذخیره شد.');
        } elseif ($action === 'assign_territories') {
            $visitorId = (int)($_POST['visitor_user_id'] ?? 0);
            $lineId = (int)($_POST['line_id'] ?? 0);
            $primaryGeoId = (int)($_POST['primary_geography_id'] ?? 0);
            $notes = trim((string)($_POST['notes'] ?? ''));
            $geoIds = array_values(array_unique(array_map('intval', $_POST['geography_ids'] ?? [])));
            $geoIds = array_values(array_filter($geoIds, static fn($id) => $id > 0));

            $visitor = Database::fetch("SELECT u.id,u.name FROM users u LEFT JOIN org_roles r ON r.id=u.org_role_id WHERE u.id=? AND u.status='active' AND (r.code='VISITOR' OR u.role_key='VISITOR')", [$visitorId]);
            $line = Database::fetch('SELECT id,code,manager_user_id,supervisor_user_id FROM sales_lines WHERE id=? AND active=1', [$lineId]);
            if (!$visitor) throw new InvalidArgumentException('ویزیتور انتخاب‌شده معتبر نیست.');
            if (!$line) throw new InvalidArgumentException('لاین انتخاب‌شده معتبر نیست.');
            if (!$geoIds) throw new InvalidArgumentException('حداقل یک شهر یا منطقه باید انتخاب شود.');
            if ($primaryGeoId && !in_array($primaryGeoId, $geoIds, true)) $primaryGeoId = $geoIds[0];
            if (!$primaryGeoId) $primaryGeoId = $geoIds[0];

            Database::execute('UPDATE sales_visitor_territories SET active=0,is_primary=0,updated_at=NOW() WHERE visitor_user_id=? AND line_id=?', [$visitorId,$lineId]);
            foreach ($geoIds as $geoId) {
                if (!Database::fetch('SELECT id FROM sales_geographies WHERE id=? AND active=1', [$geoId])) continue;
                Database::execute(
                    'INSERT INTO sales_visitor_territories(visitor_user_id,line_id,geography_id,is_primary,active,notes,created_at,updated_at) VALUES (?,?,?,?,1,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE line_id=VALUES(line_id),is_primary=VALUES(is_primary),active=1,notes=VALUES(notes),updated_at=NOW()',
                    [$visitorId,$lineId,$geoId,$geoId === $primaryGeoId ? 1 : 0,$notes]
                );
            }

            Database::execute('UPDATE users SET sales_line=?,supervisor_id=?,parent_user_id=?,organization_manager_id=?,updated_at=NOW() WHERE id=?', [$line['code'],$line['supervisor_user_id'],$line['supervisor_user_id'],$line['manager_user_id'],$visitorId]);
            if (!empty($line['supervisor_user_id'])) Database::execute('INSERT IGNORE INTO manager_employees(manager_id,employee_id,assigned_by,created_at) VALUES (?,?,?,NOW())', [(int)$line['supervisor_user_id'],$visitorId,(int)$currentUser['id']]);
            SalesStructureModule::log('assign_territories', 'visitor', $visitorId, (int)$currentUser['id'], $_POST);
            flash('محدوده فروش ویزیتور ذخیره شد.');
        }
        $pdo->commit();
    } catch (InvalidArgumentException $e) {
        $pdo->rollBack();
        flash($e->getMessage(), 'danger');
    } catch (Throwable $e) {
        $pdo->rollBack();
        error_log('sales structure: ' . $e->getMessage());
        flash('ذخیره ساختار فروش انجام نشد. لطفاً اطلاعات را بررسی کنید.', 'danger');
    }
    redirect('/admin/sales-structure.php');
}

$editLine = isset($_GET['edit_line']) ? Database::fetch('SELECT * FROM sales_lines WHERE id=?', [(int)$_GET['edit_line']]) : null;
$editBrand = isset($_GET['edit_brand']) ? Database::fetch('SELECT * FROM sales_line_brands WHERE id=?', [(int)$_GET['edit_brand']]) : null;
$editGeo = isset($_GET['edit_geo']) ? Database::fetch('SELECT * FROM sales_geographies WHERE id=?', [(int)$_GET['edit_geo']]) : null;

$managers = SalesStructureModule::managers();
$supervisors = SalesStructureModule::supervisors();
$visitors = SalesStructureModule::visitors();
$lines = Database::fetchAll("SELECT sl.*,mu.name manager_name,su.name supervisor_name,(SELECT COUNT(*) FROM sales_line_brands b WHERE b.line_id=sl.id AND b.active=1) brand_count,(SELECT COUNT(DISTINCT vt.visitor_user_id) FROM sales_visitor_territories vt WHERE vt.line_id=sl.id AND vt.active=1) visitor_count,(SELECT COUNT(*) FROM sales_visitor_territories vt WHERE vt.line_id=sl.id AND vt.active=1) territory_count FROM sales_lines sl LEFT JOIN users mu ON mu.id=sl.manager_user_id LEFT JOIN users su ON su.id=sl.supervisor_user_id ORDER BY sl.sort_order,sl.code");
$brands = Database::fetchAll('SELECT b.*,sl.title line_title,sl.code line_code FROM sales_line_brands b JOIN sales_lines sl ON sl.id=b.line_id ORDER BY sl.sort_order,b.sort_order,b.brand_name');
$geographies = Database::fetchAll('SELECT g.*,p.title parent_title FROM sales_geographies g LEFT JOIN sales_geographies p ON p.id=g.parent_id ORDER BY COALESCE(p.sort_order,g.sort_order),g.parent_id IS NOT NULL,g.sort_order,g.title');
$cities = array_values(array_filter($geographies, static fn($g) => ($g['type'] ?? '') === 'city'));
$territories = Database::fetchAll("SELECT vt.*,u.name visitor_name,sl.code line_code,sl.title line_title,g.title geography_title,g.type geography_type,p.title parent_title FROM sales_visitor_territories vt JOIN users u ON u.id=vt.visitor_user_id JOIN sales_lines sl ON sl.id=vt.line_id JOIN sales_geographies g ON g.id=vt.geography_id LEFT JOIN sales_geographies p ON p.id=g.parent_id WHERE vt.active=1 ORDER BY sl.sort_order,u.name,vt.is_primary DESC,g.sort_order");
$visitorSummary = Database::fetchAll("SELECT u.id,u.name,u.sales_line,su.name supervisor_name,GROUP_CONCAT(CONCAT(CASE WHEN g.type='region' AND p.title IS NOT NULL THEN CONCAT(p.title,' / ') ELSE '' END,g.title) ORDER BY vt.is_primary DESC,g.sort_order SEPARATOR '، ') territories FROM users u LEFT JOIN users su ON su.id=u.supervisor_id LEFT JOIN sales_visitor_territories vt ON vt.visitor_user_id=u.id AND vt.active=1 LEFT JOIN sales_geographies g ON g.id=vt.geography_id LEFT JOIN sales_geographies p ON p.id=g.parent_id LEFT JOIN org_roles r ON r.id=u.org_role_id WHERE u.status='active' AND (r.code='VISITOR' OR u.role_key='VISITOR') GROUP BY u.id,u.name,u.sales_line,su.name ORDER BY u.display_order,u.name");

$stats = [
    'lines' => count(array_filter($lines, static fn($line) => (int)$line['active'] === 1)),
    'brands' => count(array_filter($brands, static fn($brand) => (int)$brand['active'] === 1)),
    'geographies' => count(array_filter($geographies, static fn($geo) => (int)$geo['active'] === 1)),
    'visitors_with_territory' => count(array_filter($visitorSummary, static fn($row) => trim((string)($row['territories'] ?? '')) !== '')),
];

require __DIR__ . '/../views/partials/admin-header.php';
?>
<div class="section-heading-row">
    <div>
        <h1>ساختار فروش، لاین و مناطق</h1>
        <p class="muted">تعریف رابطه مدیر فروش ← سرپرست ← لاین ← ویزیتور، برندهای هر لاین و محدوده جغرافیایی فروش</p>
    </div>
    <a class="btn" href="/admin/users.php">مدیریت کاربران</a>
</div>

<div class="stats">
    <div class="stat-card"><span>لاین فعال</span><strong><?=e((string)$stats['lines'])?></strong></div>
    <div class="stat-card"><span>برندهای ثبت‌شده</span><strong><?=e((string)$stats['brands'])?></strong></div>
    <div class="stat-card"><span>شهر / منطقه</span><strong><?=e((string)$stats['geographies'])?></strong></div>
    <div class="stat-card"><span>ویزیتور دارای محدوده</span><strong><?=e((string)$stats['visitors_with_territory'])?></strong></div>
</div>

<div class="grid two-columns">
    <div class="card">
        <h2><?= $editLine ? 'ویرایش لاین فروش' : 'تعریف لاین فروش' ?></h2>
        <form method="post" class="form-grid">
            <input type="hidden" name="csrf_token" value="<?=e(Auth::csrfToken())?>">
            <input type="hidden" name="action" value="save_line">
            <input type="hidden" name="id" value="<?=e((string)($editLine['id'] ?? 0))?>">
            <label>کد لاین<input name="code" value="<?=e($editLine['code'] ?? '')?>" placeholder="A"></label>
            <label>عنوان لاین<input name="title" value="<?=e($editLine['title'] ?? '')?>" placeholder="لاین A"></label>
            <label>مدیر فروش<select name="manager_user_id"><option value="">بدون مدیر</option><?php foreach ($managers as $manager): ?><option value="<?=e((string)$manager['id'])?>" <?=((int)($editLine['manager_user_id'] ?? 0)===(int)$manager['id'])?'selected':''?>><?=e($manager['name'])?></option><?php endforeach; ?></select></label>
            <label>سرپرست مسئول لاین<select name="supervisor_user_id"><option value="">بدون سرپرست</option><?php foreach ($supervisors as $supervisor): ?><option value="<?=e((string)$supervisor['id'])?>" <?=((int)($editLine['supervisor_user_id'] ?? 0)===(int)$supervisor['id'])?'selected':''?>><?=e($supervisor['name'])?></option><?php endforeach; ?></select></label>
            <label>ترتیب<input type="number" name="sort_order" value="<?=e((string)($editLine['sort_order'] ?? 0))?>"></label>
            <label class="checkbox"><input type="checkbox" name="active" <?=((int)($editLine['active'] ?? 1)===1)?'checked':''?>> فعال</label>
            <label class="full">توضیحات<textarea name="description" rows="2"><?=e($editLine['description'] ?? '')?></textarea></label>
            <button class="btn" type="submit" <?= $canManage ? '' : 'disabled' ?>>ذخیره لاین</button>
        </form>
    </div>

    <div class="card">
        <h2><?= $editBrand ? 'ویرایش برند لاین' : 'افزودن برند به لاین' ?></h2>
        <form method="post" class="form-grid">
            <input type="hidden" name="csrf_token" value="<?=e(Auth::csrfToken())?>">
            <input type="hidden" name="action" value="save_brand">
            <input type="hidden" name="id" value="<?=e((string)($editBrand['id'] ?? 0))?>">
            <label>لاین<select name="line_id"><?php foreach ($lines as $line): ?><option value="<?=e((string)$line['id'])?>" <?=((int)($editBrand['line_id'] ?? 0)===(int)$line['id'])?'selected':''?>><?=e($line['title'])?></option><?php endforeach; ?></select></label>
            <label>کد برند<input name="brand_code" value="<?=e($editBrand['brand_code'] ?? '')?>" placeholder="اختیاری"></label>
            <label>نام برند<input name="brand_name" value="<?=e($editBrand['brand_name'] ?? '')?>"></label>
            <label>ترتیب<input type="number" name="sort_order" value="<?=e((string)($editBrand['sort_order'] ?? 0))?>"></label>
            <label class="checkbox"><input type="checkbox" name="active" <?=((int)($editBrand['active'] ?? 1)===1)?'checked':''?>> فعال</label>
            <button class="btn" type="submit" <?= $canManage ? '' : 'disabled' ?>>ذخیره برند</button>
        </form>
    </div>
</div>

<div class="grid two-columns">
    <div class="card">
        <h2><?= $editGeo ? 'ویرایش شهر / منطقه' : 'تعریف شهر و منطقه' ?></h2>
        <form method="post" class="form-grid">
            <input type="hidden" name="csrf_token" value="<?=e(Auth::csrfToken())?>">
            <input type="hidden" name="action" value="save_geography">
            <input type="hidden" name="id" value="<?=e((string)($editGeo['id'] ?? 0))?>">
            <label>نوع<select name="type"><option value="city" <?=($editGeo['type'] ?? '')==='city'?'selected':''?>>شهرستان</option><option value="region" <?=($editGeo['type'] ?? '')==='region'?'selected':''?>>منطقه داخل شهر</option></select></label>
            <label>شهر والد<select name="parent_id"><option value="">بدون والد</option><?php foreach ($cities as $city): ?><option value="<?=e((string)$city['id'])?>" <?=((int)($editGeo['parent_id'] ?? 0)===(int)$city['id'])?'selected':''?>><?=e($city['title'])?></option><?php endforeach; ?></select></label>
            <label>کد<input name="code" value="<?=e($editGeo['code'] ?? '')?>" placeholder="ZAHEDAN_R1"></label>
            <label>عنوان<input name="title" value="<?=e($editGeo['title'] ?? '')?>" placeholder="زاهدان منطقه ۱"></label>
            <label>ترتیب<input type="number" name="sort_order" value="<?=e((string)($editGeo['sort_order'] ?? 0))?>"></label>
            <label class="checkbox"><input type="checkbox" name="active" <?=((int)($editGeo['active'] ?? 1)===1)?'checked':''?>> فعال</label>
            <button class="btn" type="submit" <?= $canManage ? '' : 'disabled' ?>>ذخیره شهر / منطقه</button>
        </form>
    </div>

    <div class="card">
        <h2>تخصیص محدوده به ویزیتور</h2>
        <form method="post" class="form-grid">
            <input type="hidden" name="csrf_token" value="<?=e(Auth::csrfToken())?>">
            <input type="hidden" name="action" value="assign_territories">
            <label>ویزیتور<select name="visitor_user_id"><?php foreach ($visitors as $visitor): ?><option value="<?=e((string)$visitor['id'])?>"><?=e($visitor['name'])?><?= $visitor['sales_line'] ? ' - ' . e($visitor['sales_line']) : '' ?></option><?php endforeach; ?></select></label>
            <label>لاین<select name="line_id"><?php foreach ($lines as $line): if (!(int)$line['active']) continue; ?><option value="<?=e((string)$line['id'])?>"><?=e($line['title'])?></option><?php endforeach; ?></select></label>
            <label class="full">شهرها / مناطق<select name="geography_ids[]" multiple size="8"><?php foreach ($geographies as $geo): if (!(int)$geo['active']) continue; ?><option value="<?=e((string)$geo['id'])?>"><?=e(($geo['parent_title'] ? $geo['parent_title'] . ' / ' : '') . $geo['title'])?></option><?php endforeach; ?></select><small class="muted">برای انتخاب چند مورد، Ctrl را نگه دارید.</small></label>
            <label>محدوده اصلی<select name="primary_geography_id"><option value="">اولین مورد انتخاب‌شده</option><?php foreach ($geographies as $geo): if (!(int)$geo['active']) continue; ?><option value="<?=e((string)$geo['id'])?>"><?=e(($geo['parent_title'] ? $geo['parent_title'] . ' / ' : '') . $geo['title'])?></option><?php endforeach; ?></select></label>
            <label class="full">توضیح<textarea name="notes" rows="2"></textarea></label>
            <button class="btn" type="submit" <?= $canManage ? '' : 'disabled' ?>>ذخیره محدوده ویزیتور</button>
        </form>
    </div>
</div>

<div class="card">
    <h2>لاین‌ها و رابطه مدیریتی</h2>
    <div class="table-responsive"><table class="table"><thead><tr><th>لاین</th><th>مدیر فروش</th><th>سرپرست مسئول</th><th>برند</th><th>ویزیتور دارای محدوده</th><th>محدوده‌ها</th><th>وضعیت</th><th>عملیات</th></tr></thead><tbody>
    <?php foreach ($lines as $line): ?>
        <tr>
            <td><strong><?=e($line['title'])?></strong><br><small><?=e($line['code'])?></small></td>
            <td><?=e($line['manager_name'] ?: '—')?></td>
            <td><?=e($line['supervisor_name'] ?: '—')?></td>
            <td><?=e((string)$line['brand_count'])?></td>
            <td><?=e((string)$line['visitor_count'])?></td>
            <td><?=e((string)$line['territory_count'])?></td>
            <td><?=((int)$line['active']===1)?'<span class="badge success">فعال</span>':'<span class="badge muted">غیرفعال</span>'?></td>
            <td><a class="btn btn-small" href="/admin/sales-structure.php?edit_line=<?=e((string)$line['id'])?>">ویرایش</a></td>
        </tr>
    <?php endforeach; ?>
    </tbody></table></div>
</div>

<div class="grid two-columns">
    <div class="card">
        <h2>برندهای اختصاصی لاین‌ها</h2>
        <div class="table-responsive"><table class="table"><thead><tr><th>لاین</th><th>برند</th><th>کد</th><th>وضعیت</th><th>عملیات</th></tr></thead><tbody>
        <?php foreach ($brands as $brand): ?>
            <tr><td><?=e($brand['line_title'])?></td><td><?=e($brand['brand_name'])?></td><td><?=e($brand['brand_code'] ?: '—')?></td><td><?=((int)$brand['active']===1)?'فعال':'غیرفعال'?></td><td><a class="btn btn-small" href="/admin/sales-structure.php?edit_brand=<?=e((string)$brand['id'])?>">ویرایش</a></td></tr>
        <?php endforeach; if (!$brands): ?><tr><td colspan="5" class="muted">برندی ثبت نشده است.</td></tr><?php endif; ?>
        </tbody></table></div>
    </div>
    <div class="card">
        <h2>شهرها و مناطق</h2>
        <div class="table-responsive"><table class="table"><thead><tr><th>عنوان</th><th>نوع</th><th>والد</th><th>کد</th><th>عملیات</th></tr></thead><tbody>
        <?php foreach ($geographies as $geo): ?>
            <tr><td><?=e($geo['title'])?></td><td><?=($geo['type']==='region')?'منطقه':'شهرستان'?></td><td><?=e($geo['parent_title'] ?: '—')?></td><td><?=e($geo['code'])?></td><td><a class="btn btn-small" href="/admin/sales-structure.php?edit_geo=<?=e((string)$geo['id'])?>">ویرایش</a></td></tr>
        <?php endforeach; ?>
        </tbody></table></div>
    </div>
</div>

<div class="card">
    <h2>خلاصه محدوده ویزیتورها</h2>
    <div class="table-responsive"><table class="table"><thead><tr><th>ویزیتور</th><th>لاین</th><th>سرپرست</th><th>محدوده فروش</th></tr></thead><tbody>
    <?php foreach ($visitorSummary as $row): ?>
        <tr><td><?=e($row['name'])?></td><td><?=e($row['sales_line'] ?: '—')?></td><td><?=e($row['supervisor_name'] ?: '—')?></td><td><?=e($row['territories'] ?: 'ثبت نشده')?></td></tr>
    <?php endforeach; if (!$visitorSummary): ?><tr><td colspan="4" class="muted">ویزیتور فعالی ثبت نشده است.</td></tr><?php endif; ?>
    </tbody></table></div>
</div>

<div class="card">
    <h2>جزئیات تخصیص‌های فعال</h2>
    <div class="table-responsive"><table class="table"><thead><tr><th>ویزیتور</th><th>لاین</th><th>شهر / منطقه</th><th>اصلی</th><th>توضیح</th></tr></thead><tbody>
    <?php foreach ($territories as $territory): ?>
        <tr><td><?=e($territory['visitor_name'])?></td><td><?=e($territory['line_title'])?></td><td><?=e(($territory['parent_title'] ? $territory['parent_title'] . ' / ' : '') . $territory['geography_title'])?></td><td><?=((int)$territory['is_primary']===1)?'بله':'—'?></td><td><?=e($territory['notes'] ?: '')?></td></tr>
    <?php endforeach; if (!$territories): ?><tr><td colspan="5" class="muted">هنوز محدوده‌ای به ویزیتورها تخصیص داده نشده است.</td></tr><?php endif; ?>
    </tbody></table></div>
</div>
<?php require __DIR__ . '/../views/partials/admin-footer.php'; ?>
