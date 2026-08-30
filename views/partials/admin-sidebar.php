<?php
require_once __DIR__ . '/../../lib/admin_menu.php';
$menuPath = (string)($_SERVER['PHP_SELF'] ?? '');
?>
<div class="admin-sidebar-overlay" data-sidebar-overlay hidden></div>
<aside class="admin-sidebar" id="adminSidebar" aria-hidden="false">
    <a class="admin-logo" href="/admin/">
        <span><?= e(setting('company_name', 'شرکت پخش سبحان')) ?></span>
        <small>پنل مدیریت</small>
    </a>
    <label class="admin-menu-search">
        <span class="sr-only">جستجو در منو</span>
        <input type="search" data-admin-menu-search autocomplete="off" placeholder="جستجو در منو…">
        <b aria-hidden="true">⌕</b>
    </label>
    <p class="admin-menu-empty" data-admin-menu-empty hidden>موردی در منو پیدا نشد.</p>
    <nav class="admin-menu" aria-label="منوی مدیریت">
        <?php foreach (admin_menu_registry() as $groupKey => $group): ?>
            <?php
            $visibleItems = array_values(array_filter($group['items'], 'admin_menu_allowed'));
            if (!$visibleItems) continue;
            $hasActiveChild = false;
            foreach ($visibleItems as $item) {
                if (admin_menu_is_active($item, $menuPath)) {
                    $hasActiveChild = true;
                    break;
                }
            }
            $submenuId = 'admin-menu-' . preg_replace('/[^a-z0-9_-]/i', '-', (string)$groupKey);
            ?>
            <details class="admin-menu-section<?= $hasActiveChild ? ' has-active-child' : '' ?>" data-menu-section="<?= e((string)$groupKey) ?>" <?= $hasActiveChild ? 'open' : '' ?>>
                <summary class="admin-menu-toggle<?= $hasActiveChild ? ' has-active-child' : '' ?>" aria-controls="<?= e($submenuId) ?>">
                    <span><?= e($group['title']) ?></span>
                </summary>
                <div class="admin-submenu" id="<?= e($submenuId) ?>">
                    <?php foreach ($visibleItems as $item): ?>
                        <?php $isActive = admin_menu_is_active($item, $menuPath); ?>
                        <a class="admin-menu-link<?= $isActive ? ' is-active' : '' ?>" href="<?= e($item['url']) ?>" <?= $isActive ? 'aria-current="page"' : '' ?>>
                            <span><?= e($item['title']) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </details>
        <?php endforeach; ?>
    </nav>
</aside>
