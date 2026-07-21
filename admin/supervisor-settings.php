<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/SalesStructureModule.php';
require_once __DIR__ . '/../services/SalesOperationsService.php';
SalesOperationsService::boot(); Auth::requirePermission('admin.supervisor_settings.manage');
SalesStructureModule::repair(Database::connection());
$assignments=Database::fetchAll(
    "SELECT v.id visitor_id,v.name visitor_name,v.kara_system_code,v.sales_line,
            s.name supervisor_name,m.name manager_name,sl.title line_title,
            (SELECT CONCAT(CASE WHEN g.type='region' AND p.title IS NOT NULL THEN CONCAT(p.title,' / ') ELSE '' END,g.title)
             FROM sales_visitor_territories vt
             JOIN sales_geographies g ON g.id=vt.geography_id
             LEFT JOIN sales_geographies p ON p.id=g.parent_id
             WHERE vt.visitor_user_id=v.id AND vt.active=1
             ORDER BY vt.is_primary DESC,vt.id LIMIT 1) territory_title,
            v.updated_at
     FROM users v
     LEFT JOIN org_roles r ON r.id=v.org_role_id
     LEFT JOIN users s ON s.id=v.supervisor_id
     LEFT JOIN users m ON m.id=v.organization_manager_id
     LEFT JOIN sales_lines sl ON sl.id=v.sales_line_id
     WHERE v.status='active' AND (r.code='VISITOR' OR v.role_key='VISITOR')
     ORDER BY sl.sort_order,v.display_order,v.name
     LIMIT 1000"
);
$pageTitle='تنظیمات پنل سرپرستان';require __DIR__ . '/../views/partials/admin-header.php';
?>
<div class="section-heading-row"><div><h1>ساختار تیم سرپرستان فروش</h1><p class="muted">این صفحه فقط نمای عملیاتی است؛ منبع اصلی رابطه‌ها، کاربران و لاین‌های مرکزی هستند.</p></div><div class="actions"><a class="btn btn-primary" href="/admin/users.php">مدیریت کاربران</a><a class="btn" href="/admin/sales-structure.php">ساختار فروش</a><a class="btn" href="/admin/sales-script-fields.php">قالب فیلدها</a></div></div>
<div class="alert alert-info">ثبت دستی و موازی تیم فروش غیرفعال شده است. هر تغییر از مسیر کاربر مرکزی یا صفحه ساختار فروش انجام می‌شود و جدول سازگاری قدیمی فقط برای ماژول‌های قبلی همگام می‌ماند.</div>
<section class="card"><h2>تیم‌های فعال مشتق‌شده از کاربران</h2><div class="table-wrap"><table><thead><tr><th>سرپرست</th><th>ویزیتور</th><th>کد کارا</th><th>مدیر فروش</th><th>لاین</th><th>منطقه اصلی</th><th>آخرین بروزرسانی</th></tr></thead><tbody><?php foreach($assignments as $a):?><tr><td><?=e($a['supervisor_name']??'-')?></td><td><?=e($a['visitor_name']??'-')?></td><td dir="ltr"><?=e($a['kara_system_code']??'-')?></td><td><?=e($a['manager_name']??'-')?></td><td><?=e($a['line_title']?:($a['sales_line']??'-'))?></td><td><?=e($a['territory_title']??'-')?></td><td><?=e($a['updated_at'])?></td></tr><?php endforeach;?><?php if(!$assignments):?><tr><td colspan="7">ویزیتور فعالی در ساختار مرکزی ثبت نشده است.</td></tr><?php endif;?></tbody></table></div></section>
<?php require __DIR__ . '/../views/partials/admin-footer.php'; ?>
