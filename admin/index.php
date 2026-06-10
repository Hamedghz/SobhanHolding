<?php
require_once __DIR__ . '/../core/Auth.php'; require_once __DIR__ . '/../core/Database.php'; require_once __DIR__ . '/../core/Response.php';
Auth::requirePermission('dashboard','view'); $pageTitle='داشبورد';
$counts=[]; foreach(['users'=>'کاربران','kpis'=>'شاخص‌ها','surveys'=>'نظرسنجی‌ها','user_files'=>'فایل‌ها'] as $table=>$label){ $counts[$label]=(int)(Database::fetch("SELECT COUNT(*) c FROM $table")['c'] ?? 0); }
require __DIR__ . '/../views/partials/admin-header.php';
?>
<div class="stats"><?php foreach($counts as $label=>$count): ?><div class="stat-card"><span class="muted"><?= e($label) ?></span><strong><?= e((string)$count) ?></strong></div><?php endforeach; ?></div>
<div class="card"><h2>دسترسی سریع</h2><div class="actions"><?php if(Auth::can('survey_results')):?><a class="btn" href="/admin/survey-results.php">نتایج ارزیابی</a><?php endif;?><?php if(Auth::can('files')):?><a class="btn" href="/admin/files.php">فایل‌ها</a><?php endif;?><?php if(Auth::can('users')):?><a class="btn" href="/admin/users.php">مدیریت کاربران</a><?php endif;?><?php if(Auth::can('carousel')):?><a class="btn" href="/admin/carousel.php">مدیریت اسلایدر</a><?php endif;?></div></div>
</section></main></div><script src="/assets/js/app.js"></script></body></html>
