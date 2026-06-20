<?php require_once __DIR__.'/../../lib/admin_menu.php';$menuPath=(string)($_SERVER['PHP_SELF']??'');?>
<div class="admin-sidebar-overlay" data-sidebar-overlay hidden></div>
<aside class="admin-sidebar" id="adminSidebar" aria-hidden="true">
<a class="admin-logo" href="/admin/"><?=e(setting('company_name','شرکت پخش سبحان'))?></a><nav>
<?php foreach(admin_menu_registry() as $group):$visible=array_values(array_filter($group['items'],'admin_menu_allowed'));if(!$visible)continue;$open=false;foreach($visible as $item)if(admin_menu_is_active($item,$menuPath))$open=true;?>
<details class="sidebar-group" <?=$open?'open':''?>><summary><?=e($group['title'])?></summary><?php foreach($visible as $item):?><a class="<?=admin_menu_is_active($item,$menuPath)?'active':''?>" href="<?=e($item['url'])?>"><?=e($item['title'])?></a><?php endforeach?></details>
<?php endforeach?></nav></aside>
