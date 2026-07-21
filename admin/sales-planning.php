<?php

require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../services/SalesPlanningService.php';

Auth::requireLogin();
$actor = Auth::user();
$canView = Auth::isAdmin() || Auth::can('sales_planning.view') || Auth::can('sales_planning.manage') || Auth::can('sales_planning.reports') || Auth::can('sales_data_view');
$canManage = Auth::isAdmin() || Auth::can('sales_planning.manage', 'edit') || Auth::can('sales_data_manage_formulas', 'edit');
$canReport = Auth::isAdmin() || Auth::can('sales_planning.reports') || Auth::can('sales_data_view_reports');
if (!$canView && !$canReport) {
    http_response_code(403);
    exit('دسترسی غیرمجاز');
}

$download = (string)($_GET['download'] ?? '');
if ($download !== '') {
    if (!$canManage) {
        http_response_code(403);
        exit('دسترسی غیرمجاز');
    }
    $templates = [
        'coefficients' => [
            'guild-coefficients-template.csv',
            ['شناسه دوره','کد صنف','نام صنف','ضریب','تاریخ شروع','تاریخ پایان'],
            ['','PHARMACY','داروخانه','1.15','',''],
        ],
        'priorities' => [
            'product-priorities-template.csv',
            ['شناسه دوره','کد کالا','نام کالا','کد برند','نام برند','موجودی','ارزش موجودی','اولویت','وضعیت'],
            ['','10001','نمونه کالا','BR01','نمونه برند','120','450000000','P1','فعال'],
        ],
        'targets' => [
            'sales-targets-template.csv',
            ['شناسه دوره','کد لاین','کد کالا','نام کالا','کد فروشنده','تارگت تعداد','تارگت مبلغ','درصد تخصیص'],
            ['','A','10001','نمونه کالا','V001','100','500000000','25'],
        ],
    ];
    if (!isset($templates[$download])) {
        http_response_code(404);
        exit('قالب پیدا نشد.');
    }
    [$fileName,$headers,$sample] = $templates[$download];
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    echo "\xEF\xBB\xBF";
    $stream = fopen('php://output', 'wb');
    fputcsv($stream, $headers);
    fputcsv($stream, $sample);
    fclose($stream);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
            throw new DomainException('اعتبار فرم منقضی شده است.');
        }
        if (!$canManage) throw new DomainException('مجوز مدیریت برنامه فروش را ندارید.');
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'save_coefficient') {
            $id = SalesPlanningService::saveCoefficient($_POST, (int)$actor['id']);
            Auth::log((int)$actor['id'], 'sales_coefficient_version_created', 'sales_customer_class_coefficients', $id);
            flash('نسخه جدید ضریب صنف ثبت شد.');
        } elseif ($action === 'toggle_coefficient') {
            $id = (int)($_POST['id'] ?? 0);
            SalesPlanningService::setCoefficientActive($id, !empty($_POST['active']), (int)$actor['id']);
            Auth::log((int)$actor['id'], 'sales_coefficient_status_changed', 'sales_customer_class_coefficients', $id);
            flash('وضعیت نسخه ضریب بروزرسانی شد.');
        } elseif ($action === 'save_target') {
            $id = SalesPlanningService::saveTarget($_POST, $actor);
            Auth::log((int)$actor['id'], 'sales_target_saved', 'sales_targets', $id);
            flash('هدف ویزیتور برای کالا ثبت شد؛ جمع لاین به‌صورت خودکار محاسبه می‌شود.');
        } elseif ($action === 'toggle_target') {
            $id = (int)($_POST['id'] ?? 0);
            SalesPlanningService::setTargetActive($id, !empty($_POST['active']), $actor);
            Auth::log((int)$actor['id'], 'sales_target_status_changed', 'sales_targets', $id);
            flash('وضعیت هدف بروزرسانی شد.');
        } else {
            throw new InvalidArgumentException('عملیات درخواست‌شده معتبر نیست.');
        }
    } catch (InvalidArgumentException|DomainException $e) {
        flash($e->getMessage(), 'danger');
    } catch (Throwable $e) {
        error_log('Sales planning: ' . $e->getMessage());
        flash('عملیات انجام نشد. جزئیات فنی در لاگ ثبت شد.', 'danger');
    }
    $tab = preg_replace('/[^a-z_]/', '', (string)($_POST['return_tab'] ?? 'coefficients'));
    $period = max(0, (int)($_POST['return_period_id'] ?? $_POST['period_id'] ?? 0));
    redirect('/admin/sales-planning.php?tab=' . $tab . ($period ? '&period_id=' . $period : ''));
}

$tabs = ['coefficients'=>'ضرایب صنف','priorities'=>'اولویت محصولات','targets'=>'تخصیص هدف','reports'=>'گزارش تحقق'];
$tab = (string)($_GET['tab'] ?? 'coefficients');
if (!isset($tabs[$tab])) $tab = 'coefficients';
$periods = SalesPlanningService::periods();
$defaultPeriodId = (int)($periods[0]['id'] ?? 0);
$periodId = max(0, (int)($_GET['period_id'] ?? $defaultPeriodId));
$lines = SalesPlanningService::linesForActor($actor);
$visitors = SalesPlanningService::visitorsForActor($actor);
$coefficients = $tab === 'coefficients' ? SalesPlanningService::coefficients($periodId ?: null) : [];
$priorities = $tab === 'priorities' ? SalesPlanningService::priorities($periodId ?: null) : [];
$targets = $tab === 'targets' ? SalesPlanningService::targets($actor, $periodId ?: null) : [];
$grain = (string)($_GET['grain'] ?? 'visitor_product');
$grainLabels = [
    'visitor_product'=>'هدف کالایی ویزیتور','visitor_total'=>'جمع هدف ویزیتور',
    'line_product'=>'هدف کالایی لاین','line_total'=>'جمع هدف لاین','brand'=>'هدف برند',
];
if (!isset($grainLabels[$grain])) $grain = 'visitor_product';
$achievement = $tab === 'reports' && $periodId && $canReport
    ? SalesPlanningService::achievement($actor, $periodId, $grain)
    : [];
$pageTitle = 'ضرایب، اولویت‌ها و اهداف فروش';
$adminExtraStylesheets = ['/assets/css/sales-planning.css'];
$adminExtraScripts = ['/assets/js/sales-planning.js'];
require __DIR__ . '/../views/partials/admin-header.php';

$number = static fn(mixed $value, int $decimals = 0): string =>
    $value === null || $value === '' ? '—' : number_format((float)$value, $decimals);
?>

<div class="sales-planning-page">
    <header class="planning-hero" data-planning-reveal>
        <div>
            <span class="planning-kicker">Sales Planning Control Room</span>
            <h1><?= e($pageTitle) ?></h1>
            <p>ضرایب نسخه‌دار، اولویت کنترل‌شده و هدف در دانه‌بندی «دوره + ویزیتور + لاین + کالا»؛ جمع‌ها بدون ورود دستی دوباره محاسبه می‌شوند.</p>
        </div>
        <div class="planning-flow" aria-label="جریان برنامه فروش">
            <span>مرجع</span><i></i><span>تخصیص</span><i></i><span>فروش واقعی</span><i></i><span>تحقق</span>
        </div>
    </header>

    <nav class="planning-tabs" aria-label="بخش برنامه فروش">
        <?php foreach ($tabs as $key=>$label): ?>
            <a class="<?= $tab===$key?'is-active':'' ?>" href="/admin/sales-planning.php?tab=<?=e($key)?><?= $periodId?'&period_id='.$periodId:'' ?>"><?=e($label)?></a>
        <?php endforeach; ?>
    </nav>

    <section class="card planning-filter-card">
        <form method="get" class="planning-filter">
            <input type="hidden" name="tab" value="<?=e($tab)?>">
            <?php if ($tab === 'reports'): ?><input type="hidden" name="grain" value="<?=e($grain)?>"><?php endif; ?>
            <label><span>دوره مؤثر</span><select name="period_id" required><?php foreach($periods as $period): ?><option value="<?=(int)$period['id']?>" <?=$periodId===(int)$period['id']?'selected':''?>><?=e($period['title'])?></option><?php endforeach; ?></select></label>
            <button class="btn btn-light">اعمال دوره</button>
        </form>
        <small>دوره از مرجع مرکزی تاریخ انتخاب می‌شود و تاریخ شروع/پایان در محاسبه تحقق استفاده می‌شود.</small>
    </section>

    <?php if ($tab === 'coefficients'): ?>
        <?php if ($canManage): ?>
            <section class="card planning-editor" data-planning-reveal>
                <div class="planning-section-title"><div><span>Interactive grid</span><h2>ثبت نسخه ضریب صنف</h2></div><a class="btn btn-light" href="/admin/sales-planning.php?download=coefficients">دانلود قالب Excel/CSV</a></div>
                <form method="post" class="coefficient-grid">
                    <input type="hidden" name="csrf_token" value="<?=e(Auth::csrfToken())?>">
                    <input type="hidden" name="action" value="save_coefficient">
                    <input type="hidden" name="return_tab" value="coefficients">
                    <input type="hidden" name="return_period_id" value="<?=$periodId?>">
                    <label><span>دوره</span><select name="period_id" required><?php foreach($periods as $period): ?><option value="<?=(int)$period['id']?>" <?=$periodId===(int)$period['id']?'selected':''?>><?=e($period['title'])?></option><?php endforeach; ?></select></label>
                    <label><span>کد صنف</span><input name="guild_code" maxlength="100" placeholder="مثلاً PHARMACY"></label>
                    <label><span>نام صنف</span><input name="guild_name" maxlength="255" placeholder="مثلاً داروخانه"></label>
                    <label><span>ضریب</span><input name="coefficient" inputmode="decimal" required placeholder="1.15"></label>
                    <label class="planning-check"><input type="checkbox" name="active" value="1" checked><span>نسخه فعال</span></label>
                    <button class="btn btn-primary">ثبت نسخه جدید</button>
                </form>
                <p class="muted">کلید یکتا از کد صنف و در نبود آن از نام نرمال‌شده + دوره ساخته می‌شود. ثبت جدید، تاریخچه نسخه قبلی را نگه می‌دارد.</p>
            </section>
        <?php endif; ?>
        <section class="card" data-planning-reveal>
            <div class="planning-section-title"><div><span>Version history</span><h2>نسخه‌های ضرایب صنف</h2></div><a class="btn btn-light" href="/admin/import-center.php?source=customer_coefficients">ورود از Excel</a></div>
            <div class="table-responsive"><table><thead><tr><th>صنف</th><th>دوره</th><th>ضریب</th><th>نسخه</th><th>منبع</th><th>وضعیت</th><th>عملیات</th></tr></thead><tbody>
            <?php if (!$coefficients): ?><tr><td colspan="7" class="empty-state">هنوز ضریب صنفی برای این دوره ثبت نشده است.</td></tr><?php endif; ?>
            <?php foreach($coefficients as $row): ?><tr>
                <td><strong><?=e((string)($row['customer_class_title'] ?: $row['customer_class_code']))?></strong><small><?=e((string)$row['guild_identity_key'])?></small></td>
                <td><?=e((string)($row['period_title'] ?: 'دوره قدیمی'))?></td>
                <td><?=$number($row['coefficient'],4)?></td><td><?=e((string)$row['version_no'])?></td>
                <td><?=($row['import_batch_id']?'Excel / Batch #'.(int)$row['import_batch_id']:'دستی')?></td>
                <td><span class="planning-status <?=$row['active']?'is-active':'is-inactive'?>"><?=$row['active']?'فعال':'غیرفعال'?></span></td>
                <td><?php if($canManage && !$row['import_batch_id']): ?><form method="post"><input type="hidden" name="csrf_token" value="<?=e(Auth::csrfToken())?>"><input type="hidden" name="action" value="toggle_coefficient"><input type="hidden" name="id" value="<?=(int)$row['id']?>"><input type="hidden" name="return_tab" value="coefficients"><input type="hidden" name="return_period_id" value="<?=$periodId?>"><input type="hidden" name="active" value="<?=$row['active']?0:1?>"><button class="btn btn-light btn-sm"><?=$row['active']?'غیرفعال‌سازی':'فعال‌سازی'?></button></form><?php else: ?>—<?php endif; ?></td>
            </tr><?php endforeach; ?></tbody></table></div>
        </section>
    <?php elseif ($tab === 'priorities'): ?>
        <section class="planning-template-grid">
            <article class="card planning-action-card" data-planning-reveal><span>P1 → P4</span><h2>مقادیر اولویت کنترل‌شده</h2><p>فوری/خیلی بالا، بالا، عادی و پایین هنگام ورود به کدهای P1 تا P4 نرمال می‌شوند.</p></article>
            <article class="card planning-action-card" data-planning-reveal><span>Excel import</span><h2>بروزرسانی اولویت محصولات</h2><div class="actions"><a class="btn btn-primary" href="/admin/import-center.php?source=product_priorities">ورود فایل</a><a class="btn btn-light" href="/admin/sales-planning.php?download=priorities">دانلود قالب</a></div></article>
        </section>
        <section class="card" data-planning-reveal>
            <div class="planning-section-title"><div><span>Active reference</span><h2>اولویت فعال محصولات</h2></div><span class="planning-count"><?=count($priorities)?> ردیف</span></div>
            <div class="table-responsive"><table><thead><tr><th>کالا</th><th>برند</th><th>موجودی</th><th>ارزش موجودی</th><th>اولویت</th><th>وضعیت</th><th>Batch</th></tr></thead><tbody>
            <?php if(!$priorities): ?><tr><td colspan="7" class="empty-state">داده اولویت فعال برای این دوره وجود ندارد؛ فایل مرجع را وارد کنید.</td></tr><?php endif; ?>
            <?php foreach($priorities as $row): ?><tr><td><strong><?=e((string)$row['product_name'])?></strong><small><?=e((string)$row['product_code'])?></small></td><td><?=e((string)($row['brand_name']?:$row['brand_code']))?></td><td><?=$number($row['inventory_quantity'],2)?></td><td><?=$number($row['inventory_value'])?></td><td><span class="priority-badge priority-<?=e(strtolower((string)$row['priority_code']))?>"><?=e(SalesPlanningService::PRIORITIES[$row['priority_code']]??(string)$row['priority_code'])?></span></td><td><?=e((string)$row['status'])?></td><td>#<?=(int)$row['import_batch_id']?></td></tr><?php endforeach; ?>
            </tbody></table></div>
        </section>
    <?php elseif ($tab === 'targets'): ?>
        <?php if ($canManage): ?>
            <section class="card planning-editor" data-planning-reveal>
                <div class="planning-section-title"><div><span>Canonical allocation</span><h2>تخصیص هدف کالایی ویزیتور</h2></div><div class="actions"><a class="btn btn-light" href="/admin/sales-planning.php?download=targets">دانلود قالب</a><a class="btn btn-light" href="/admin/import-center.php?source=sales_targets">ورود Excel</a></div></div>
                <form method="post" class="target-grid" data-target-form>
                    <input type="hidden" name="csrf_token" value="<?=e(Auth::csrfToken())?>"><input type="hidden" name="action" value="save_target"><input type="hidden" name="return_tab" value="targets"><input type="hidden" name="return_period_id" value="<?=$periodId?>">
                    <label><span>دوره</span><select name="period_id" required><?php foreach($periods as $period): ?><option value="<?=(int)$period['id']?>" <?=$periodId===(int)$period['id']?'selected':''?>><?=e($period['title'])?></option><?php endforeach; ?></select></label>
                    <label><span>لاین</span><select name="line_code" required data-target-line><option value="">انتخاب لاین</option><?php foreach($lines as $line): ?><option value="<?=e($line['code'])?>"><?=e($line['title'].' · '.$line['code'])?></option><?php endforeach; ?></select></label>
                    <label><span>ویزیتور</span><select name="visitor_code" required data-target-visitor><option value="">انتخاب ویزیتور</option><?php foreach($visitors as $visitor): ?><option value="<?=e((string)($visitor['employee_no']?:$visitor['kara_system_code']))?>" data-line="<?=e((string)$visitor['line_code'])?>"><?=e($visitor['name'].' · '.$visitor['line_title'])?></option><?php endforeach; ?></select></label>
                    <label><span>کد کالا</span><input name="product_code" maxlength="100" required></label>
                    <label><span>هدف تعداد</span><input name="target_quantity" inputmode="decimal"></label>
                    <label><span>هدف مبلغ</span><input name="target_amount" inputmode="decimal"></label>
                    <label><span>درصد تخصیص</span><input name="allocation_percent" inputmode="decimal" placeholder="0 تا 100"></label>
                    <button class="btn btn-primary">ثبت / بروزرسانی هدف</button>
                </form>
            </section>
        <?php endif; ?>
        <section class="card" data-planning-reveal>
            <div class="planning-section-title"><div><span>Single source of truth</span><h2>اهداف فعال ویزیتورها</h2></div><span class="planning-count"><?=count($targets)?> ردیف</span></div>
            <div class="table-responsive"><table><thead><tr><th>ویزیتور</th><th>لاین</th><th>کالا</th><th>هدف تعداد</th><th>هدف مبلغ</th><th>تخصیص</th><th>منبع</th><th>عملیات</th></tr></thead><tbody>
            <?php if(!$targets): ?><tr><td colspan="8" class="empty-state">برای این دوره هدف فعالی ثبت نشده است.</td></tr><?php endif; ?>
            <?php foreach($targets as $row): ?><tr><td><?=e((string)$row['visitor_name'])?></td><td><?=e((string)$row['line_title'])?></td><td><strong><?=e((string)($row['product_name']?:$row['product_code']))?></strong><small><?=e((string)$row['product_code'])?></small></td><td><?=$number($row['target_quantity'],2)?></td><td><?=$number($row['target_amount'])?></td><td><?=$number($row['allocation_percent'],2)?>%</td><td><?=$row['import_batch_id']?'Batch #'.(int)$row['import_batch_id']:'دستی'?></td><td><?php if($canManage && !$row['import_batch_id']): ?><form method="post"><input type="hidden" name="csrf_token" value="<?=e(Auth::csrfToken())?>"><input type="hidden" name="action" value="toggle_target"><input type="hidden" name="id" value="<?=(int)$row['id']?>"><input type="hidden" name="return_tab" value="targets"><input type="hidden" name="return_period_id" value="<?=$periodId?>"><input type="hidden" name="active" value="0"><button class="btn btn-light btn-sm">غیرفعال</button></form><?php else: ?>—<?php endif; ?></td></tr><?php endforeach; ?>
            </tbody></table></div>
        </section>
    <?php else: ?>
        <nav class="report-grains" aria-label="نوع گزارش"><?php foreach($grainLabels as $key=>$label): ?><a class="<?=$grain===$key?'is-active':''?>" href="/admin/sales-planning.php?tab=reports&period_id=<?=$periodId?>&grain=<?=e($key)?>"><?=e($label)?></a><?php endforeach; ?></nav>
        <section class="card" data-planning-reveal>
            <div class="planning-section-title"><div><span>Achievement report</span><h2><?=e($grainLabels[$grain])?></h2></div><span class="planning-count"><?=count($achievement)?> ردیف</span></div>
            <div class="table-responsive"><table><thead><tr><th>شرح</th><th>هدف تعداد</th><th>عملکرد تعداد</th><th>تحقق تعداد</th><th>هدف مبلغ</th><th>عملکرد مبلغ</th><th>تحقق مبلغ</th></tr></thead><tbody>
            <?php if(!$achievement): ?><tr><td colspan="7" class="empty-state">برای دوره و دامنه دسترسی شما داده قابل گزارش وجود ندارد.</td></tr><?php endif; ?>
            <?php foreach($achievement as $row):
                $label = match($grain) {
                    'visitor_product' => ($row['visitor_name']??'').' · '.($row['product_name']?:$row['product_code']),
                    'visitor_total' => (string)($row['visitor_name']??''),
                    'line_product' => ($row['line_title']??'').' · '.($row['product_name']?:$row['product_code']),
                    'line_total' => (string)($row['line_title']??''),
                    'brand' => (string)($row['brand_name']?:'بدون برند'),
                    default => '',
                };
                $qTarget=(float)($row['target_quantity']??0);$qActual=(float)($row['achievement_quantity']??0);
                $aTarget=(float)($row['target_amount']??0);$aActual=(float)($row['achievement_amount']??0);
                $qPercent=$qTarget>0?($qActual/$qTarget*100):null;$aPercent=$aTarget>0?($aActual/$aTarget*100):null;
            ?><tr><td><strong><?=e($label)?></strong></td><td><?=$number($qTarget,2)?></td><td><?=$number($qActual,2)?></td><td><span class="achievement-pill"><?= $qPercent===null?'—':$number($qPercent,1).'%' ?></span></td><td><?=$number($aTarget)?></td><td><?=$number($aActual)?></td><td><span class="achievement-pill"><?= $aPercent===null?'—':$number($aPercent,1).'%' ?></span></td></tr><?php endforeach; ?>
            </tbody></table></div>
            <p class="muted planning-formula-note">عملکرد تعداد = تعداد کل منهای مرجوعی؛ عملکرد مبلغ = مبلغ خالص منهای مبلغ مرجوعی، فقط در بازه دوره و بر اساس ویزیتور + لاین + کالا.</p>
        </section>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../views/partials/admin-footer.php'; ?>
