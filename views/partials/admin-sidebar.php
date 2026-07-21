<?php
require_once __DIR__ . '/../../lib/admin_menu.php';
$menuPath = (string)($_SERVER['REQUEST_URI'] ?? $_SERVER['PHP_SELF'] ?? '');
$visibleAdminMenu = admin_menu_visible_registry();
$menuSearchItems = admin_menu_search_index();
?>
<div class="admin-sidebar-overlay" data-sidebar-overlay hidden></div>
<aside class="admin-sidebar" id="adminSidebar" aria-hidden="false">
    <a class="admin-logo" href="/admin/"><span><?= e(setting('company_name', 'شرکت پخش سبحان')) ?></span><small>پنل مدیریت</small></a>
    <div class="admin-menu-search" data-menu-search>
        <label for="adminMenuSearch"><span class="sr-only">جست‌وجوی منوی پنل</span><input id="adminMenuSearch" type="search" autocomplete="off" placeholder="جست‌وجوی منو..." aria-controls="adminMenuSearchResults" aria-expanded="false" data-menu-search-input><kbd>Ctrl K</kbd></label>
        <div class="admin-menu-search-results" id="adminMenuSearchResults" role="listbox" aria-label="نتایج جست‌وجوی منو" data-menu-search-results hidden>
            <?php foreach ($menuSearchItems as $searchItem): ?>
                <a role="option" tabindex="-1" href="<?= e($searchItem['url']) ?>" data-menu-search-result data-search-text="<?= e($searchItem['group'] . ' ' . $searchItem['title']) ?>"><span class="admin-menu-search-icon" aria-hidden="true"></span><span><strong><?= e($searchItem['title']) ?></strong><small><?= e($searchItem['group'] . ' ← ' . $searchItem['title']) ?></small></span></a>
            <?php endforeach; ?>
            <p class="muted" data-menu-search-empty hidden>نتیجه مجازی پیدا نشد.</p>
        </div>
    </div>
    <nav class="admin-menu" aria-label="منوی مدیریت">
        <?php foreach ($visibleAdminMenu as $groupKey => $group): ?>
            <?php $hasActiveChild = false;foreach ($group['items'] as $item) if (admin_menu_is_active($item, $menuPath)) {$hasActiveChild = true;break;}$submenuId = 'admin-menu-' . preg_replace('/[^a-z0-9_-]/i', '-', (string)$groupKey); ?>
            <details class="admin-menu-section<?= $hasActiveChild ? ' has-active-child' : '' ?>" data-menu-section="<?= e((string)$groupKey) ?>" <?= $hasActiveChild ? 'open' : '' ?>>
                <summary class="admin-menu-toggle<?= $hasActiveChild ? ' has-active-child' : '' ?>" aria-controls="<?= e($submenuId) ?>"><span><?= e($group['title']) ?></span></summary>
                <div class="admin-submenu" id="<?= e($submenuId) ?>">
                    <?php foreach ($group['items'] as $item): $isActive = admin_menu_is_active($item, $menuPath); ?><a class="admin-menu-link<?= $isActive ? ' is-active' : '' ?>" href="<?= e($item['url']) ?>" <?= $isActive ? 'aria-current="page"' : '' ?>><span><?= e($item['title']) ?></span></a><?php endforeach; ?>
                </div>
            </details>
        <?php endforeach; ?>
    </nav>
</aside>
