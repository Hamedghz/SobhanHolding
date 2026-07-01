<?php
require_once __DIR__.'/../core/Auth.php';
require_once __DIR__.'/../core/Response.php';

Auth::requireLogin();
$pageTitle='برنامه کاری من';
$personalPlannerFullPage=true;
$adminExtraStylesheets=['/assets/css/personal-planner.css'];
require __DIR__.'/../views/partials/admin-header.php';
?>
<div class="section-heading-row">
    <div>
        <h1>برنامه کاری من</h1>
        <p class="muted">برنامه شخصی، سریع و خصوصی شما با افزودن سریع، ویرایش، اولویت، انتقال و یادداشت روزانه</p>
    </div>
    <a class="btn" href="/admin/index.php">بازگشت به داشبورد</a>
</div>
<?php require __DIR__.'/includes/personal-planner-widget.php'; ?>
<?php require __DIR__.'/../views/partials/admin-footer.php'; ?>
