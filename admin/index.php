<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../lib/admin_menu.php';
Auth::requirePermission('dashboard','view');
$pageTitle='داشبورد';
$icons=['dashboards'=>'▦','performance'=>'↗','hr'=>'◎','finance'=>'﷼','ai'=>'AI','content'=>'□','system'=>'⚙'];
$groups=[];foreach(admin_menu_registry() as $key=>$group){$items=array_values(array_filter($group['items'],'admin_menu_allowed'));if($items)$groups[$key]=['title'=>$group['title'],'items'=>$items];}
$cache=Database::fetchAll('SELECT dashboard_key,source,updated_at FROM dashboard_data_cache ORDER BY updated_at DESC');$cacheByKey=array_column($cache,null,'dashboard_key');
require __DIR__ . '/../views/partials/admin-header.php';
?>
<div class="section-heading-row"><div><h1>داشبورد دسترسی‌ها</h1><p class="muted">مسیرهای قابل استفاده بر اساس نقش و مجوزهای حساب شما</p></div></div>
<?php if(!$groups):?><section class="card"><p>برای حساب کاربری شما هنوز دسترسی فعالی تعریف نشده است.</p></section><?php endif?>
<?php foreach($groups as $key=>$group):?><section class="card"><h2><?=e($group['title'])?></h2><div class="dashboard-access-grid"><?php foreach($group['items'] as $item):?><a class="dashboard-access-card" href="<?=e($item['url'])?>"><span class="dashboard-access-icon"><?=e($icons[$key]??'□')?></span><strong><?=e($item['title'])?></strong><small>ورود به بخش</small></a><?php endforeach?></div></section><?php endforeach?>
<?php if($cache):?><section class="card"><h2>آخرین بروزرسانی داشبوردها</h2><div class="table-wrap"><table><thead><tr><th>داشبورد</th><th>منبع</th><th>زمان</th></tr></thead><tbody><?php foreach($cacheByKey as $row):?><tr><td><?=e($row['dashboard_key'])?></td><td><?=e($row['source'])?></td><td><?=e($row['updated_at'])?></td></tr><?php endforeach?></tbody></table></div></section><?php endif?>
</section></main></div><script src="/assets/js/app.js"></script><script src="/assets/js/personal-planner.js"></script></body></html>
