<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/OrgModule.php';
require_once __DIR__ . '/../lib/OrgAccess.php';

Auth::requirePermission('hr_settings', 'view');
$pageTitle = 'تنظیمات منابع انسانی';
$canManage = Auth::can('hr_settings', 'edit') || Auth::can('users', 'edit');

function hr_setting_code(string $value): string
{
    return trim(preg_replace('/[^A-Z0-9_\-]/', '', strtoupper($value)) ?? '');
}

function hr_user_tree(array $children, int $parentId = 0, array $seen = []): void
{
    foreach ($children[$parentId] ?? [] as $user) {
        $id = (int)$user['id'];
        if (isset($seen[$id])) continue;
        $nextSeen = $seen;
        $nextSeen[$id] = true;
        echo '<li><div><strong>' . e($user['name']) . '</strong><span>' . e($user['org_role_title'] ?: ($user['role_key'] ?: 'بدون نقش')) . '</span><a class="btn btn-small" href="/admin/users.php?edit=' . e((string)$id) . '">اصلاح ارتباط</a></div>';
        if (!empty($children[$id])) {
            echo '<ul>';
            hr_user_tree($children, $id, $nextSeen);
            echo '</ul>';
        }
        echo '</li>';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('درخواست نامعتبر است.', 'danger');
        redirect('/admin/hr-settings.php');
    }
    if (!$canManage) {
        flash('برای این عملیات دسترسی ندارید.', 'danger');
        redirect('/admin/hr-settings.php');
    }
    $action = (string)($_POST['action'] ?? '');
    try {
        if ($action === 'save_unit') {
            $id = (int)($_POST['id'] ?? 0);
            $parentId = (int)($_POST['parent_id'] ?? 0) ?: null;
            $title = trim((string)($_POST['title'] ?? ''));
            $code = hr_setting_code((string)($_POST['code'] ?? ''));
            if ($title === '' || $code === '') throw new InvalidArgumentException('عنوان و کد واحد الزامی است.');
            if ($id && $parentId === $id) throw new InvalidArgumentException('یک واحد نمی‌تواند والد خودش باشد.');
            if ($parentId) {
                $parent = Database::fetch('SELECT id FROM org_units WHERE id=?', [$parentId]);
                if (!$parent) throw new InvalidArgumentException('واحد والد معتبر نیست.');
                $cursor = $parentId;
                $seenUnits = [];
                while ($cursor && !isset($seenUnits[$cursor])) {
                    if ($id && $cursor === $id) throw new InvalidArgumentException('انتخاب این والد باعث ایجاد حلقه در ساختار واحدها می‌شود.');
                    $seenUnits[$cursor] = true;
                    $row = Database::fetch('SELECT parent_id FROM org_units WHERE id=?', [$cursor]);
                    $cursor = (int)($row['parent_id'] ?? 0);
                }
                $maxDepth = OrgModule::salesBranch($parentId) ? 3 : 2;
                if (OrgModule::unitDepth($parentId) >= $maxDepth) throw new InvalidArgumentException($maxDepth === 3 ? 'عمق واحد فروش بیشتر از سه سطح مجاز نیست.' : 'عمق واحدهای غیر فروش بیشتر از دو سطح مجاز نیست.');
            }
            $data = [$parentId,$title,$code,($_POST['unit_type'] ?? '') === 'sales' ? 'sales' : 'general',isset($_POST['active']) ? 1 : 0,max(0,(int)($_POST['sort_order'] ?? 0)),trim((string)($_POST['description'] ?? ''))];
            if ($id) Database::execute('UPDATE org_units SET parent_id=?,title=?,code=?,unit_type=?,active=?,sort_order=?,description=?,updated_at=NOW() WHERE id=?', [...$data,$id]);
            else Database::execute('INSERT INTO org_units(parent_id,title,code,unit_type,active,sort_order,description,created_at,updated_at) VALUES (?,?,?,?,?,?,?,NOW(),NOW())', $data);
            flash('واحد سازمانی ذخیره شد.');
        } elseif ($action === 'save_role') {
            $id = (int)($_POST['id'] ?? 0);
            $unitId = (int)($_POST['org_unit_id'] ?? 0) ?: null;
            $parentRoleId = (int)($_POST['parent_role_id'] ?? 0) ?: null;
            $title = trim((string)($_POST['title'] ?? ''));
            $code = hr_setting_code((string)($_POST['code'] ?? ''));
            if ($title === '' || $code === '') throw new InvalidArgumentException('عنوان و کد نقش الزامی است.');
            if ($id && $parentRoleId === $id) throw new InvalidArgumentException('یک نقش نمی‌تواند والد خودش باشد.');
            if ($unitId && !Database::fetch('SELECT id FROM org_units WHERE id=?', [$unitId])) throw new InvalidArgumentException('واحد انتخاب‌شده معتبر نیست.');
            if ($parentRoleId) {
                $cursor = $parentRoleId;
                $seen = [];
                while ($cursor && !isset($seen[$cursor])) {
                    if ($id && $cursor === $id) throw new InvalidArgumentException('انتخاب این نقش والد باعث ایجاد حلقه می‌شود.');
                    $seen[$cursor] = true;
                    $parentRole = Database::fetch('SELECT parent_role_id FROM org_roles WHERE id=?', [$cursor]);
                    if (!$parentRole) throw new InvalidArgumentException('نقش والد معتبر نیست.');
                    $cursor = (int)($parentRole['parent_role_id'] ?? 0);
                }
            }
            $roleType = in_array($_POST['role_type'] ?? '', ['executive','manager','supervisor','staff'], true) ? $_POST['role_type'] : 'staff';
            $data = [$title,$code,$unitId,$parentRoleId,$roleType,isset($_POST['is_sales_role']) ? 1 : 0,max(0,min(3,(int)($_POST['hierarchy_level'] ?? 0))),isset($_POST['active']) ? 1 : 0,max(0,(int)($_POST['sort_order'] ?? 0)),trim((string)($_POST['description'] ?? ''))];
            if ($id) Database::execute('UPDATE org_roles SET title=?,code=?,org_unit_id=?,parent_role_id=?,role_type=?,is_sales_role=?,hierarchy_level=?,active=?,sort_order=?,description=?,updated_at=NOW() WHERE id=?', [...$data,$id]);
            else Database::execute('INSERT INTO org_roles(title,code,org_unit_id,parent_role_id,role_type,is_sales_role,hierarchy_level,active,sort_order,description,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,NOW(),NOW())', $data);
            flash('نقش سازمانی ذخیره شد.');
        } elseif ($action === 'assign_user') {
            $userId = (int)($_POST['user_id'] ?? 0);
            $unitId = (int)($_POST['org_unit_id'] ?? 0) ?: null;
            $roleId = (int)($_POST['org_role_id'] ?? 0) ?: null;
            $parentId = (int)($_POST['parent_user_id'] ?? 0) ?: null;
            $target = Database::fetch('SELECT id FROM users WHERE id=?', [$userId]);
            if (!OrgAccess::canAccessUser(Auth::user(),$userId)) throw new InvalidArgumentException('برای ویرایش این کاربر دسترسی ندارید.');
            if (!$target) throw new InvalidArgumentException('کاربر انتخاب‌شده معتبر نیست.');
            $organization = OrgModule::normalizeUserOrganization([
                'org_unit_id' => $unitId,
                'org_role_id' => $roleId,
                'parent_user_id' => $parentId,
                'supervisor_id' => $parentId,
                'organization_manager_id' => $parentId,
                'sales_line' => $_POST['sales_line'] ?? '',
            ], $userId);
            if ($organization['errors']) throw new InvalidArgumentException(implode(' ', $organization['errors']));
            $unit = $organization['org_unit'];
            $role = $organization['org_role'];
            $parentId = $organization['parent_user_id'];
            $parent = $parentId ? OrgAccess::userContext($parentId) : null;
            if ($parentId && (!$parent || !OrgAccess::canAccessUser(Auth::user(), $parentId))) throw new InvalidArgumentException('مدیر مستقیم معتبر نیست.');
            if ($role && !(int)$role['is_sales_role'] && $parent && (int)($parent['parent_user_id'] ?? 0) > 0) throw new InvalidArgumentException('عمق ارتباط مستقیم در واحدهای غیر فروش بیشتر از دو سطح مجاز نیست.');
            $scope = in_array($_POST['access_scope'] ?? '', ['self','team','unit','all'], true) ? $_POST['access_scope'] : 'self';
            if ($scope === 'all' && !Auth::isAdmin()) $scope = 'self';
            $salesLine = $organization['sales_line'];
            $supervisorId = $organization['supervisor_id'];
            $managerId = $organization['organization_manager_id'];
            Database::execute('UPDATE users SET org_unit_id=?,org_role_id=?,parent_user_id=?,department=?,role_key=?,sales_line=?,supervisor_id=?,organization_manager_id=?,access_scope=?,employee_panel_enabled=?,admin_panel_enabled=?,display_order=?,description=?,updated_at=NOW() WHERE id=?', [$unitId,$roleId,$parentId,$unit['title'] ?? '',$role['code'] ?? '',$salesLine,$supervisorId,$managerId,$scope,isset($_POST['employee_panel_enabled'])?1:0,isset($_POST['admin_panel_enabled'])?1:0,max(0,(int)($_POST['display_order'] ?? 0)),trim((string)($_POST['description'] ?? '')),$userId]);
            Database::execute('DELETE FROM manager_employees WHERE employee_id=?', [$userId]);
            if ($parentId) Database::execute('INSERT IGNORE INTO manager_employees(manager_id,employee_id,assigned_by,created_at) VALUES (?,?,?,NOW())', [$parentId,$userId,(int)Auth::user()['id']]);
            flash('ارتباط سازمانی کاربر ذخیره شد.');
        }
    } catch (InvalidArgumentException $e) {
        flash($e->getMessage(), 'danger');
    } catch (Throwable $e) {
        error_log('HR settings: ' . $e->getMessage());
        flash('ذخیره اطلاعات انجام نشد. لطفاً دوباره تلاش کنید.', 'danger');
    }
    redirect('/admin/hr-settings.php');
}

$unitEdit = isset($_GET['unit_edit']) ? Database::fetch('SELECT * FROM org_units WHERE id=?', [(int)$_GET['unit_edit']]) : null;
$roleEdit = isset($_GET['role_edit']) ? Database::fetch('SELECT * FROM org_roles WHERE id=?', [(int)$_GET['role_edit']]) : null;
$units = Database::fetchAll('SELECT u.*,p.title parent_title,(SELECT COUNT(*) FROM users x WHERE x.org_unit_id=u.id) user_count FROM org_units u LEFT JOIN org_units p ON p.id=u.parent_id ORDER BY u.sort_order,u.title');
$roles = Database::fetchAll('SELECT r.*,ou.title org_unit_title,pr.title parent_role_title,(SELECT COUNT(*) FROM users x WHERE x.org_role_id=r.id) user_count FROM org_roles r LEFT JOIN org_units ou ON ou.id=r.org_unit_id LEFT JOIN org_roles pr ON pr.id=r.parent_role_id ORDER BY r.sort_order,r.title');
$allowedIds=OrgAccess::accessibleUserIds(Auth::user());if(!$allowedIds)$allowedIds=[-1];$users = Database::fetchAll('SELECT u.*,ou.title org_unit_title,orr.title org_role_title,orr.code org_role_code,p.name parent_name FROM users u LEFT JOIN org_units ou ON ou.id=u.org_unit_id LEFT JOIN org_roles orr ON orr.id=u.org_role_id LEFT JOIN users p ON p.id=u.parent_user_id WHERE u.id IN ('.implode(',',array_fill(0,count($allowedIds),'?')).') ORDER BY u.display_order,u.name',$allowedIds);
$children = [];
foreach ($users as $item) $children[(int)($item['parent_user_id'] ?? 0)][] = $item;
$withoutUnit = array_filter($users, static fn($item) => !(int)($item['org_unit_id'] ?? 0));
$withoutRole = array_filter($users, static fn($item) => !(int)($item['org_role_id'] ?? 0));
$withoutManager = array_filter($users, static fn($item) => !(int)($item['parent_user_id'] ?? 0) && !in_array($item['org_role_code'] ?? '', ['CEO','SALES_MANAGER'], true));
require __DIR__ . '/../views/partials/admin-header.php';
?>
<div class="section-heading-row"><div><h1>تنظیمات منابع انسانی</h1><p class="muted">ساختار مرکزی واحد، نقش و مدیر مستقیم با حفظ سازگاری داده‌های قبلی</p></div><a class="btn" href="/admin/users.php">مدیریت کاربران</a></div>
<div class="stats"><div class="stat-card"><span>واحدها</span><strong><?=e((string)count($units))?></strong></div><div class="stat-card"><span>نقش‌ها</span><strong><?=e((string)count($roles))?></strong></div><div class="stat-card"><span>بدون واحد</span><strong><?=e((string)count($withoutUnit))?></strong></div><div class="stat-card"><span>بدون نقش</span><strong><?=e((string)count($withoutRole))?></strong></div><div class="stat-card"><span>بدون مدیر مستقیم</span><strong><?=e((string)count($withoutManager))?></strong></div></div>
<?php if($canManage):?><div class="grid grid-2"><section class="card admin-form"><h2>تعریف واحد سازمانی</h2><form method="post"><input type="hidden" name="csrf_token" value="<?=e(Auth::csrfToken())?>"><input type="hidden" name="action" value="save_unit"><input type="hidden" name="id" value="<?=e($unitEdit['id']??'')?>"><div class="grid grid-2"><label class="form-field"><span>عنوان</span><input name="title" value="<?=e($unitEdit['title']??'')?>" required></label><label class="form-field"><span>کد</span><input dir="ltr" name="code" value="<?=e($unitEdit['code']??'')?>" required></label><label class="form-field"><span>والد</span><select name="parent_id"><option value="">سطح اول</option><?php foreach($units as $unit):?><option value="<?=$unit['id']?>" <?=(int)($unitEdit['parent_id']??0)===(int)$unit['id']?'selected':''?>><?=e($unit['title'])?></option><?php endforeach?></select></label><label class="form-field"><span>نوع</span><select name="unit_type"><option value="general">عمومی</option><option value="sales" <?=($unitEdit['unit_type']??'')==='sales'?'selected':''?>>فروش</option></select></label><label class="form-field"><span>ترتیب</span><input type="number" min="0" name="sort_order" value="<?=e($unitEdit['sort_order']??0)?>"></label><label class="checkbox-item"><input type="checkbox" name="active" <?=!isset($unitEdit['active'])||(int)$unitEdit['active']?'checked':''?>> فعال</label></div><label class="form-field"><span>توضیحات</span><textarea name="description"><?=e($unitEdit['description']??'')?></textarea></label><button class="btn btn-primary">ذخیره واحد</button></form></section>
<section class="card admin-form"><h2>تعریف نقش سازمانی</h2><form method="post"><input type="hidden" name="csrf_token" value="<?=e(Auth::csrfToken())?>"><input type="hidden" name="action" value="save_role"><input type="hidden" name="id" value="<?=e($roleEdit['id']??'')?>"><div class="grid grid-2"><label class="form-field"><span>عنوان</span><input name="title" value="<?=e($roleEdit['title']??'')?>" required></label><label class="form-field"><span>کد</span><input dir="ltr" name="code" value="<?=e($roleEdit['code']??'')?>" required></label><label class="form-field"><span>واحد مرتبط</span><select name="org_unit_id"><option value="">همه واحدها</option><?php foreach($units as $unit):if(!$unit['active'] && (int)($roleEdit['org_unit_id']??0)!==(int)$unit['id'])continue?><option value="<?=$unit['id']?>" <?=(int)($roleEdit['org_unit_id']??0)===(int)$unit['id']?'selected':''?>><?=e($unit['title'])?></option><?php endforeach?></select></label><label class="form-field"><span>نقش والد</span><select name="parent_role_id"><option value="">بدون والد</option><?php foreach($roles as $role):if((int)$role['id']===(int)($roleEdit['id']??0)||(!$role['active']&&(int)($roleEdit['parent_role_id']??0)!==(int)$role['id']))continue?><option value="<?=$role['id']?>" <?=(int)($roleEdit['parent_role_id']??0)===(int)$role['id']?'selected':''?>><?=e($role['title'])?></option><?php endforeach?></select></label><label class="form-field"><span>نوع نقش</span><select name="role_type"><?php foreach(['executive'=>'مدیریت ارشد','manager'=>'مدیر','supervisor'=>'سرپرست','staff'=>'کارشناس/کارمند'] as $value=>$label):?><option value="<?=$value?>" <?=($roleEdit['role_type']??'staff')===$value?'selected':''?>><?=$label?></option><?php endforeach?></select></label><label class="form-field"><span>سطح سلسله‌مراتب</span><input type="number" min="0" max="3" name="hierarchy_level" value="<?=e($roleEdit['hierarchy_level']??0)?>"></label><label class="checkbox-item"><input type="checkbox" name="is_sales_role" <?=(int)($roleEdit['is_sales_role']??0)?'checked':''?>> نقش فروش</label><label class="checkbox-item"><input type="checkbox" name="active" <?=!isset($roleEdit['active'])||(int)$roleEdit['active']?'checked':''?>> فعال</label></div><label class="form-field"><span>توضیحات</span><textarea name="description"><?=e($roleEdit['description']??'')?></textarea></label><button class="btn btn-primary">ذخیره نقش</button></form></section></div><?php endif?>
<section class="card"><h2>واحدها و نقش‌های سازمانی</h2><div class="grid grid-2"><div class="table-wrap"><table><thead><tr><th>واحد</th><th>والد</th><th>کاربر</th><th>وضعیت</th><th></th></tr></thead><tbody><?php foreach($units as $unit):?><tr><td><?=e($unit['title'])?><small><?=e($unit['code'])?></small></td><td><?=e($unit['parent_title']?:'-')?></td><td><?=e($unit['user_count'])?></td><td><?=($unit['active']?'فعال':'غیرفعال')?></td><td><a class="btn btn-small" href="?unit_edit=<?=$unit['id']?>">ویرایش</a></td></tr><?php endforeach?></tbody></table></div><div class="table-wrap"><table><thead><tr><th>نقش</th><th>واحد / والد</th><th>کاربر</th><th>وضعیت</th><th></th></tr></thead><tbody><?php foreach($roles as $role):?><tr><td><?=e($role['title'])?><small><?=e($role['code'])?></small></td><td><?=e(($role['org_unit_title']?:'همه واحدها').' / '.($role['parent_role_title']?:'بدون والد'))?></td><td><?=e($role['user_count'])?></td><td><?=($role['active']?'فعال':'غیرفعال')?></td><td><a class="btn btn-small" href="?role_edit=<?=$role['id']?>">ویرایش</a></td></tr><?php endforeach?></tbody></table></div></div></section>
<?php if($canManage):?><section class="card admin-form"><h2>ارتباط کاربر با ساختار سازمانی</h2><form method="post"><input type="hidden" name="csrf_token" value="<?=e(Auth::csrfToken())?>"><input type="hidden" name="action" value="assign_user"><div class="grid grid-3"><label class="form-field"><span>کاربر</span><select name="user_id" required><?php foreach($users as $item):?><option value="<?=$item['id']?>"><?=e($item['name'])?></option><?php endforeach?></select></label><label class="form-field"><span>واحد</span><select name="org_unit_id"><option value="">بدون واحد</option><?php foreach($units as $unit):if(!$unit['active'])continue?><option value="<?=$unit['id']?>"><?=e($unit['title'])?></option><?php endforeach?></select></label><label class="form-field"><span>نقش سازمانی</span><select name="org_role_id"><option value="">بدون نقش</option><?php foreach($roles as $role):if(!$role['active'])continue?><option value="<?=$role['id']?>"><?=e($role['title'])?></option><?php endforeach?></select></label><label class="form-field"><span>مدیر مستقیم</span><select name="parent_user_id"><option value="">بدون مدیر</option><?php foreach($users as $item):?><option value="<?=$item['id']?>"><?=e($item['name'].' - '.($item['org_role_title']?:$item['role_key']))?></option><?php endforeach?></select></label><label class="form-field"><span>لاین فروش</span><input name="sales_line"></label><label class="form-field"><span>سطح مشاهده</span><select name="access_scope"><option value="self">فقط خود</option><option value="team">تیم مستقیم</option><option value="unit">واحد سازمانی</option><option value="all">همه اطلاعات</option></select></label><label class="form-field"><span>ترتیب</span><input type="number" min="0" name="display_order"></label><label class="checkbox-item"><input type="checkbox" name="employee_panel_enabled" checked> دسترسی پنل کارمند</label><label class="checkbox-item"><input type="checkbox" name="admin_panel_enabled"> دسترسی پنل ادمین</label><label class="form-field grid-span-2"><span>توضیحات</span><input name="description"></label></div><button class="btn btn-primary">ذخیره ارتباط</button></form></section><?php endif?>
<section class="card org-tree-card"><h2>ساختار سازمانی</h2><p class="muted">واحد → نقش → کاربر → زیرمجموعه</p><div class="org-unit-tree"><?php foreach($units as $unit):?><details open><summary><strong><?=e($unit['title'])?></strong><span><?=e($unit['user_count'])?> کاربر</span></summary><?php $unitUsers=array_values(array_filter($users,fn($item)=>(int)$item['org_unit_id']===(int)$unit['id']));$unitIds=array_flip(array_map(fn($item)=>(int)$item['id'],$unitUsers));$unitChildren=[];foreach($unitUsers as $item){$parent=(int)($item['parent_user_id']??0);$unitChildren[isset($unitIds[$parent])?$parent:0][]=$item;}?><ul><?php hr_user_tree($unitChildren);if(!$unitUsers):?><li class="muted">کاربری در این واحد ثبت نشده است.</li><?php endif?></ul></details><?php endforeach?></div></section>
<?php require __DIR__ . '/../views/partials/admin-footer.php'; ?>
