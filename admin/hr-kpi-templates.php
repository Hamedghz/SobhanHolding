<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Response.php';

Auth::requirePermission('hr_kpi.manage');
$pageTitle = 'قالب‌های ارزیابی KPI';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('درخواست نامعتبر است.', 'danger');
        redirect('/admin/hr-kpi-templates.php');
    }
    try {
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'template') {
            $id = (int)($_POST['id'] ?? 0);
            $title = trim((string)($_POST['title'] ?? ''));
            $code = strtoupper(preg_replace('/[^A-Z0-9_]/', '', (string)($_POST['code'] ?? '')) ?? '');
            $unitId = (int)($_POST['org_unit_id'] ?? 0) ?: null;
            $salesLine = mb_substr(trim((string)($_POST['sales_line'] ?? '')),0,50);
            $roleIds = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['role_ids'] ?? [])))));
            $defaultRoleId = (int)($_POST['default_role_id'] ?? 0) ?: null;
            if ($title === '' || $code === '') throw new InvalidArgumentException('عنوان و کد قالب الزامی است.');
            if ($unitId && !Database::fetch('SELECT id FROM org_units WHERE id=?', [$unitId])) throw new InvalidArgumentException('واحد انتخاب‌شده معتبر نیست.');
            if ($defaultRoleId && !in_array($defaultRoleId, $roleIds, true)) throw new InvalidArgumentException('نقش پیش‌فرض باید در میان نقش‌های متصل انتخاب شود.');
            foreach ($roleIds as $roleId) if (!Database::fetch('SELECT id FROM org_roles WHERE id=?', [$roleId])) throw new InvalidArgumentException('یکی از نقش‌های انتخاب‌شده معتبر نیست.');

            $pdo = Database::connection();
            $pdo->beginTransaction();
            try {
                $data = [$title,trim((string)($_POST['category'] ?? '')),$unitId,$salesLine?:null,trim((string)($_POST['description'] ?? '')),isset($_POST['active'])?1:0,max(0,(int)($_POST['sort_order'] ?? 0))];
                if ($id) Database::execute('UPDATE hr_kpi_templates SET title=?,category=?,org_unit_id=?,sales_line=?,description=?,active=?,sort_order=?,updated_at=NOW() WHERE id=?', [...$data,$id]);
                else {
                    Database::execute('INSERT INTO hr_kpi_templates(title,code,category,org_unit_id,sales_line,description,active,sort_order,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,NOW(),NOW())', [$title,$code,...array_slice($data,1)]);
                    $id = (int)Database::lastInsertId();
                }
                if ($id < 1) throw new RuntimeException('Template was not saved.');
                if ($roleIds) {
                    $placeholders = implode(',', array_fill(0, count($roleIds), '?'));
                    Database::execute("UPDATE hr_kpi_template_roles SET active=0,updated_at=NOW() WHERE template_id=? AND role_id NOT IN ({$placeholders})", [$id,...$roleIds]);
                } else Database::execute('UPDATE hr_kpi_template_roles SET active=0,updated_at=NOW() WHERE template_id=?', [$id]);
                foreach ($roleIds as $roleId) Database::execute('INSERT INTO hr_kpi_template_roles(template_id,role_id,is_default,active,created_at,updated_at) VALUES (?,?,?,1,NOW(),NOW()) ON DUPLICATE KEY UPDATE is_default=VALUES(is_default),active=1,updated_at=NOW()', [$id,$roleId,$defaultRoleId===$roleId?1:0]);
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $e;
            }
            flash('قالب KPI ذخیره شد.');
        } elseif ($action === 'criterion') {
            $id = (int)($_POST['id'] ?? 0);
            $templateId = (int)($_POST['template_id'] ?? 0);
            $text = trim((string)($_POST['criteria_text'] ?? ''));
            if (!$templateId || $text === '' || !Database::fetch('SELECT id FROM hr_kpi_templates WHERE id=?', [$templateId])) throw new InvalidArgumentException('قالب و متن معیار الزامی است.');
            $data = [$text,hash('sha256',mb_strtolower($text,'UTF-8')),trim((string)($_POST['category'] ?? '')),max(.01,(float)($_POST['weight'] ?? 1)),max(.01,(float)($_POST['max_score'] ?? 10)),max(0,(int)($_POST['sort_order'] ?? 0)),isset($_POST['active'])?1:0];
            if ($id) Database::execute('UPDATE hr_kpi_criteria SET criteria_text=?,criteria_hash=?,category=?,weight=?,max_score=?,sort_order=?,active=?,updated_at=NOW() WHERE id=? AND template_id=?', [...$data,$id,$templateId]);
            else Database::execute('INSERT INTO hr_kpi_criteria(template_id,criteria_text,criteria_hash,category,weight,max_score,sort_order,active,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,NOW(),NOW())', [$templateId,...$data]);
            flash('معیار KPI ذخیره شد.');
        }
    } catch (InvalidArgumentException $e) {
        flash($e->getMessage(), 'danger');
    } catch (Throwable $e) {
        error_log('HR KPI template save: ' . $e->getMessage());
        flash('ذخیره اطلاعات انجام نشد. کد قالب یا متن معیار ممکن است تکراری باشد.', 'danger');
    }
    redirect('/admin/hr-kpi-templates.php');
}

$units = Database::fetchAll('SELECT id,title,active FROM org_units ORDER BY sort_order,title');
$roles = Database::fetchAll('SELECT id,title,code,active FROM org_roles ORDER BY sort_order,title');
$templates = Database::fetchAll('SELECT t.*,u.title unit_title,(SELECT COUNT(*) FROM hr_kpi_criteria c WHERE c.template_id=t.id) criteria_count,GROUP_CONCAT(CASE WHEN tr.active=1 THEN r.title END ORDER BY tr.is_default DESC,r.title SEPARATOR "، ") role_titles FROM hr_kpi_templates t LEFT JOIN org_units u ON u.id=t.org_unit_id LEFT JOIN hr_kpi_template_roles tr ON tr.template_id=t.id LEFT JOIN org_roles r ON r.id=tr.role_id GROUP BY t.id ORDER BY t.sort_order,t.id');
$selected = (int)($_GET['template_id'] ?? ($templates[0]['id'] ?? 0));
$criteria = $selected ? Database::fetchAll('SELECT * FROM hr_kpi_criteria WHERE template_id=? ORDER BY sort_order,id', [$selected]) : [];
$editTemplate = isset($_GET['edit_template']) ? Database::fetch('SELECT * FROM hr_kpi_templates WHERE id=?', [(int)$_GET['edit_template']]) : null;
$editCriterion = isset($_GET['edit_criterion']) ? Database::fetch('SELECT * FROM hr_kpi_criteria WHERE id=? AND template_id=?', [(int)$_GET['edit_criterion'],$selected]) : null;
$selectedRoleRows = $editTemplate ? Database::fetchAll('SELECT role_id,is_default FROM hr_kpi_template_roles WHERE template_id=? AND active=1', [(int)$editTemplate['id']]) : [];
$selectedRoleIds = array_map('intval', array_column($selectedRoleRows, 'role_id'));
$defaultRoleId = 0; foreach ($selectedRoleRows as $row) if ((int)$row['is_default']) $defaultRoleId = (int)$row['role_id'];
require __DIR__ . '/../views/partials/admin-header.php';
?>
<section class="card admin-form"><h2><?=$editTemplate?'ویرایش قالب':'قالب جدید'?></h2><form method="post"><input type="hidden" name="csrf_token" value="<?=e(Auth::csrfToken())?>"><input type="hidden" name="action" value="template"><input type="hidden" name="id" value="<?=e($editTemplate['id']??'')?>"><div class="grid grid-3"><label class="form-field"><span>عنوان</span><input name="title" value="<?=e($editTemplate['title']??'')?>" required></label><label class="form-field"><span>کد</span><input dir="ltr" name="code" value="<?=e($editTemplate['code']??'')?>" required <?=$editTemplate?'readonly':''?>></label><label class="form-field"><span>واحد مرتبط</span><select name="org_unit_id"><option value="">همه واحدها</option><?php foreach($units as $unit):if(!$unit['active']&&(int)($editTemplate['org_unit_id']??0)!==(int)$unit['id'])continue?><option value="<?=$unit['id']?>" <?=(int)($editTemplate['org_unit_id']??0)===(int)$unit['id']?'selected':''?>><?=e($unit['title'])?></option><?php endforeach?></select></label><label class="form-field"><span>لاین فروش مرتبط</span><input name="sales_line" maxlength="50" value="<?=e($editTemplate['sales_line']??'')?>" placeholder="خالی یعنی همه لاین‌ها"></label><label class="form-field"><span>دسته</span><input name="category" value="<?=e($editTemplate['category']??'')?>"></label><label class="form-field"><span>ترتیب</span><input type="number" min="0" name="sort_order" value="<?=e($editTemplate['sort_order']??100)?>"></label><label class="checkbox-item"><input type="checkbox" name="active" <?=!$editTemplate||(int)$editTemplate['active']?'checked':''?>> فعال</label></div><label class="form-field"><span>نقش‌های مرتبط</span><select name="role_ids[]" id="kpiRoles" multiple size="5"><?php foreach($roles as $role):if(!$role['active']&&!in_array((int)$role['id'],$selectedRoleIds,true))continue?><option value="<?=$role['id']?>" <?=in_array((int)$role['id'],$selectedRoleIds,true)?'selected':''?>><?=e($role['title'].' ('.$role['code'].')')?></option><?php endforeach?></select></label><label class="form-field"><span>نقش پیش‌فرض</span><select name="default_role_id"><option value="">بدون نقش پیش‌فرض</option><?php foreach($roles as $role):if(!$role['active']&&!in_array((int)$role['id'],$selectedRoleIds,true))continue?><option value="<?=$role['id']?>" <?=$defaultRoleId===(int)$role['id']?'selected':''?>><?=e($role['title'])?></option><?php endforeach?></select></label><label class="form-field"><span>توضیحات</span><textarea name="description"><?=e($editTemplate['description']??'')?></textarea></label><div class="form-actions"><button class="btn btn-primary">ذخیره قالب</button><?php if($editTemplate):?><a class="btn" href="?template_id=<?=$selected?>">انصراف</a><?php endif?></div></form></section>
<div class="grid grid-2"><section class="card"><h2>قالب‌ها</h2><div class="table-wrap"><table><thead><tr><th>عنوان</th><th>واحد / نقش</th><th>معیار</th><th>وضعیت</th><th></th></tr></thead><tbody><?php foreach($templates as $t):?><tr><td><a href="?template_id=<?=$t['id']?>"><?=e($t['title'])?></a><small><?=e($t['code'])?></small></td><td><?=e(($t['unit_title']?:'همه واحدها').' / '.($t['role_titles']?:'همه نقش‌ها'))?></td><td><?=e($t['criteria_count'])?></td><td><?=$t['active']?'فعال':'غیرفعال'?></td><td><a class="btn btn-small" href="?template_id=<?=$t['id']?>&edit_template=<?=$t['id']?>">ویرایش</a></td></tr><?php endforeach?></tbody></table></div></section><section class="card admin-form"><h2><?=$editCriterion?'ویرایش معیار':'افزودن معیار'?></h2><?php if($selected):?><form method="post"><input type="hidden" name="csrf_token" value="<?=e(Auth::csrfToken())?>"><input type="hidden" name="action" value="criterion"><input type="hidden" name="id" value="<?=e($editCriterion['id']??'')?>"><input type="hidden" name="template_id" value="<?=$selected?>"><label class="form-field"><span>متن معیار</span><textarea name="criteria_text" required><?=e($editCriterion['criteria_text']??'')?></textarea></label><div class="grid grid-3"><label class="form-field"><span>وزن</span><input type="number" min=".01" step=".01" name="weight" value="<?=e($editCriterion['weight']??1)?>"></label><label class="form-field"><span>حداکثر امتیاز</span><input type="number" min=".01" step=".01" name="max_score" value="<?=e($editCriterion['max_score']??10)?>"></label><label class="form-field"><span>ترتیب</span><input type="number" min="0" name="sort_order" value="<?=e($editCriterion['sort_order']??100)?>"></label></div><label class="form-field"><span>دسته معیار</span><input name="category" value="<?=e($editCriterion['category']??'')?>"></label><label class="checkbox-item"><input type="checkbox" name="active" <?=!$editCriterion||(int)$editCriterion['active']?'checked':''?>> فعال</label><button class="btn btn-primary">ذخیره معیار</button></form><?php endif?></section></div>
<section class="card"><h2>معیارهای قالب انتخابی</h2><div class="table-wrap"><table><thead><tr><th>معیار</th><th>وزن</th><th>حداکثر</th><th>ترتیب</th><th>وضعیت</th><th></th></tr></thead><tbody><?php foreach($criteria as $c):?><tr><td><?=e($c['criteria_text'])?></td><td><?=e($c['weight'])?></td><td><?=e($c['max_score'])?></td><td><?=e($c['sort_order'])?></td><td><?=$c['active']?'فعال':'غیرفعال'?></td><td><a class="btn btn-small" href="?template_id=<?=$selected?>&edit_criterion=<?=$c['id']?>">ویرایش</a></td></tr><?php endforeach?><?php if(!$criteria):?><tr><td colspan="6">معیاری ثبت نشده است.</td></tr><?php endif?></tbody></table></div></section>
<?php require __DIR__ . '/../views/partials/admin-footer.php'; ?>
