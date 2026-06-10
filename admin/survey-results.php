<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Response.php';

Auth::requirePermission('survey_results', 'view');
$pageTitle = 'نتایج ارزیابی';
$user = Auth::user();

function placeholders(array $values): string
{
    return implode(',', array_fill(0, count($values), '?'));
}

if (Auth::isAdmin()) {
    $availableEmployees = Database::fetchAll('SELECT id,name FROM users WHERE role = "employee" AND status = "active" ORDER BY name');
} elseif (Auth::isManager()) {
    $availableEmployees = Database::fetchAll('SELECT u.id,u.name FROM users u JOIN manager_employees me ON me.employee_id = u.id WHERE me.manager_id = ? AND u.status = "active" ORDER BY u.name', [$user['id']]);
} else {
    $availableEmployees = Auth::isEmployee() ? [Database::fetch('SELECT id,name FROM users WHERE id = ?', [$user['id']])] : [];
    $availableEmployees = array_filter($availableEmployees);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('درخواست نامعتبر است.', 'danger');
        redirect('/admin/survey-results.php');
    }
    if (!Auth::can('survey_results', 'create')) {
        flash('برای ثبت نتیجه دسترسی ندارید.', 'danger');
        redirect('/admin/survey-results.php');
    }

    $surveyId = (int)($_POST['survey_id'] ?? 0);
    $employeeId = (int)($_POST['employee_id'] ?? 0);
    $surveyAllowed = Auth::isAdmin() || Database::fetch('SELECT id FROM survey_assignments WHERE survey_id = ? AND user_id = ?', [$surveyId, $user['id']]);
    if (!$surveyAllowed || !$employeeId || !Auth::canAccessEmployee($employeeId)) {
        flash('دسترسی غیرمجاز.', 'danger');
        redirect('/admin/survey-results.php');
    }

    $employee = Database::fetch('SELECT id,name FROM users WHERE id = ? AND role = "employee"', [$employeeId]);
    if (!$employee) {
        flash('کارمند انتخاب‌شده معتبر نیست.', 'danger');
        redirect('/admin/survey-results.php');
    }

    $kpis = Database::fetchAll('SELECT sk.kpi_id, sk.weight FROM survey_kpis sk WHERE sk.survey_id = ?', [$surveyId]);
    $sumW = 0;
    $sum = 0;
    foreach ($kpis as $k) {
        $score = max(0, min(100, (float)($_POST['score'][$k['kpi_id']] ?? 0)));
        $sumW += (float)$k['weight'];
        $sum += $score * (float)$k['weight'];
    }
    $final = $sumW > 0 ? $sum / $sumW : 0;

    $pdo = Database::connection();
    $pdo->beginTransaction();
    try {
        Database::execute('INSERT INTO survey_results (survey_id,user_id,employee_id,employee_name,final_score,created_at,updated_at) VALUES (?,?,?,?,?,NOW(),NOW())', [$surveyId, $user['id'], $employeeId, $employee['name'], $final]);
        $resultId = (int)Database::lastInsertId();
        foreach ($kpis as $k) {
            $score = max(0, min(100, (float)($_POST['score'][$k['kpi_id']] ?? 0)));
            Database::execute('INSERT INTO survey_result_items (result_id,kpi_id,score,weighted_score,created_at) VALUES (?,?,?,?,NOW())', [$resultId, $k['kpi_id'], $score, $score * (float)$k['weight']]);
        }
        Database::execute('UPDATE survey_assignments SET status = "completed" WHERE survey_id = ? AND user_id = ?', [$surveyId, $user['id']]);
        $pdo->commit();
        flash('نتیجه ثبت شد.');
    } catch (Throwable $e) {
        $pdo->rollBack();
        flash('خطا در ثبت نتیجه.', 'danger');
    }
    redirect('/admin/survey-results.php');
}

$assigned = Auth::isAdmin()
    ? Database::fetchAll('SELECT * FROM surveys WHERE status = "active" ORDER BY id DESC')
    : Database::fetchAll('SELECT s.* FROM surveys s JOIN survey_assignments sa ON sa.survey_id = s.id WHERE sa.user_id = ? AND s.status = "active" ORDER BY s.id DESC', [$user['id']]);
$allSurveys = Database::fetchAll('SELECT id,title FROM surveys ORDER BY title');
$managers = Auth::isAdmin() ? Database::fetchAll('SELECT id,name FROM users WHERE role = "manager" AND status = "active" ORDER BY name') : [];

$filterSurvey = (int)($_GET['survey_id'] ?? 0);
$filterManager = Auth::isAdmin() ? (int)($_GET['manager_id'] ?? 0) : 0;
$filterEmployee = (int)($_GET['employee_id'] ?? 0);
$fromDate = trim($_GET['from_date'] ?? '');
$toDate = trim($_GET['to_date'] ?? '');

$employeeFilterOptions = $availableEmployees;
if (Auth::isAdmin()) {
    if ($filterManager) {
        $employeeFilterOptions = Database::fetchAll('SELECT u.id,u.name FROM users u JOIN manager_employees me ON me.employee_id = u.id WHERE me.manager_id = ? ORDER BY u.name', [$filterManager]);
    } else {
        $employeeFilterOptions = Database::fetchAll('SELECT id,name FROM users WHERE role = "employee" ORDER BY name');
    }
}

$where = [];
$params = [];
if (!Auth::isAdmin()) {
    if (Auth::isManager()) {
        $ids = Auth::assignedEmployeeIds((int)$user['id']);
        $ids[] = -1;
        $where[] = '(r.employee_id IN (' . placeholders($ids) . ') OR r.user_id = ?)';
        $params = array_merge($params, $ids, [(int)$user['id']]);
    } else {
        $where[] = '(r.employee_id = ? OR (r.employee_id IS NULL AND r.user_id = ?))';
        $params[] = (int)$user['id'];
        $params[] = (int)$user['id'];
    }
}
if ($filterSurvey) {
    $where[] = 'r.survey_id = ?';
    $params[] = $filterSurvey;
}
if ($filterManager) {
    $where[] = 'EXISTS (SELECT 1 FROM manager_employees mef WHERE mef.employee_id = r.employee_id AND mef.manager_id = ?)';
    $params[] = $filterManager;
}
if ($filterEmployee && Auth::canAccessEmployee($filterEmployee)) {
    $where[] = 'r.employee_id = ?';
    $params[] = $filterEmployee;
}
if ($fromDate !== '') {
    $where[] = 'DATE(r.created_at) >= ?';
    $params[] = $fromDate;
}
if ($toDate !== '') {
    $where[] = 'DATE(r.created_at) <= ?';
    $params[] = $toDate;
}

$sql = 'SELECT r.*, s.title survey_title, u.name user_name, e.name employee_real_name,
        (SELECT GROUP_CONCAT(m.name SEPARATOR "، ") FROM manager_employees me JOIN users m ON m.id = me.manager_id WHERE me.employee_id = r.employee_id) manager_names
        FROM survey_results r
        JOIN surveys s ON s.id = r.survey_id
        JOIN users u ON u.id = r.user_id
        LEFT JOIN users e ON e.id = r.employee_id';
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY r.id DESC';
$results = Database::fetchAll($sql, $params);

require __DIR__ . '/../views/partials/admin-header.php';
?>
<div class="card admin-form">
    <h2>ثبت ارزیابی جدید</h2>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= e(Auth::csrfToken()) ?>">
        <div class="grid grid-2">
            <label class="form-field"><span>نظرسنجی</span><select name="survey_id" id="surveySelect" required><option value="">انتخاب کنید</option><?php foreach ($assigned as $s): ?><option value="<?= e($s['id']) ?>"><?= e($s['title']) ?></option><?php endforeach; ?></select></label>
            <label class="form-field"><span>کارمند ارزیابی‌شونده</span><select name="employee_id" required><option value="">انتخاب کنید</option><?php foreach ($availableEmployees as $employee): ?><option value="<?= e($employee['id']) ?>"><?= e($employee['name']) ?></option><?php endforeach; ?></select></label>
        </div>
        <?php foreach ($assigned as $s): $ks = Database::fetchAll('SELECT k.id,k.title,sk.weight FROM survey_kpis sk JOIN kpis k ON k.id = sk.kpi_id WHERE sk.survey_id = ?', [$s['id']]); ?>
            <div class="survey-kpis" data-survey="<?= e($s['id']) ?>" style="display:none">
                <h3><?= e($s['title']) ?></h3>
                <?php foreach ($ks as $k): ?><label class="form-field"><span><?= e($k['title']) ?> - وزن <?= e($k['weight']) ?></span><input type="number" min="0" max="100" step="0.01" name="score[<?= e($k['id']) ?>]" value="0"></label><?php endforeach; ?>
            </div>
        <?php endforeach; ?>
        <button class="btn btn-primary">ثبت نتیجه</button>
    </form>
</div>

<form class="card admin-form" method="get">
    <h2>فیلتر نتایج</h2>
    <div class="grid grid-3">
        <label class="form-field"><span>نظرسنجی</span><select name="survey_id"><option value="">همه</option><?php foreach ($allSurveys as $s): ?><option value="<?= e($s['id']) ?>" <?= $filterSurvey === (int)$s['id'] ? 'selected' : '' ?>><?= e($s['title']) ?></option><?php endforeach; ?></select></label>
        <?php if (Auth::isAdmin()): ?><label class="form-field"><span>مدیر</span><select name="manager_id"><option value="">همه</option><?php foreach ($managers as $m): ?><option value="<?= e($m['id']) ?>" <?= $filterManager === (int)$m['id'] ? 'selected' : '' ?>><?= e($m['name']) ?></option><?php endforeach; ?></select></label><?php endif; ?>
        <label class="form-field"><span>کارمند</span><select name="employee_id"><option value="">همه</option><?php foreach ($employeeFilterOptions as $employee): ?><option value="<?= e($employee['id']) ?>" <?= $filterEmployee === (int)$employee['id'] ? 'selected' : '' ?>><?= e($employee['name']) ?></option><?php endforeach; ?></select></label>
        <label class="form-field"><span>از تاریخ</span><input type="date" name="from_date" value="<?= e($fromDate) ?>"></label>
        <label class="form-field"><span>تا تاریخ</span><input type="date" name="to_date" value="<?= e($toDate) ?>"></label>
    </div>
    <div class="form-actions"><button class="btn btn-primary">اعمال فیلتر</button><a class="btn" href="/admin/survey-results.php">پاکسازی</a></div>
</form>

<h2 class="section-title">نمودار امتیاز نهایی کارمندان</h2>
<div class="score-list">
    <?php foreach ($results as $r): $score = min(100, max(0, (float)$r['final_score'])); ?>
        <div class="card score-row">
            <div>
                <strong><?= e($r['employee_real_name'] ?: $r['employee_name']) ?></strong>
                <span class="muted"><?= e($r['manager_names'] ?: 'بدون مدیر') ?> | <?= e($r['survey_title']) ?> | <?= e($r['created_at']) ?></span>
            </div>
            <div class="score-meter"><div class="progress"><span style="width:<?= e((string)$score) ?>%"></span></div><b><?= e(number_format((float)$r['final_score'], 2)) ?></b></div>
        </div>
    <?php endforeach; ?>
</div>

<h2 class="section-title">نتایج ثبت‌شده</h2>
<?php foreach ($results as $r): $items = Database::fetchAll('SELECT i.*,k.title,k.weight FROM survey_result_items i JOIN kpis k ON k.id = i.kpi_id WHERE i.result_id = ?', [$r['id']]); ?>
    <div class="card admin-form">
        <h3><?= e(($r['employee_real_name'] ?: $r['employee_name']) . ' - ' . $r['survey_title']) ?></h3>
        <p class="muted">ثبت‌کننده: <?= e($r['user_name']) ?> | مدیر: <?= e($r['manager_names'] ?: 'بدون مدیر') ?> | امتیاز نهایی: <?= e(number_format((float)$r['final_score'], 2)) ?></p>
        <div class="progress"><span style="width:<?= e((string)min(100, (float)$r['final_score'])) ?>%"></span></div>
        <div class="table-wrap" style="margin-top:12px"><table><thead><tr><th>KPI</th><th>امتیاز</th><th>وزن</th><th>امتیاز وزنی</th></tr></thead><tbody><?php foreach ($items as $it): ?><tr><td><?= e($it['title']) ?></td><td><?= e($it['score']) ?></td><td><?= e($it['weight']) ?></td><td><?= e($it['weighted_score']) ?></td></tr><?php endforeach; ?></tbody></table></div>
    </div>
<?php endforeach; ?>
<script>
document.getElementById('surveySelect')?.addEventListener('change', e => {
    document.querySelectorAll('.survey-kpis').forEach(x => x.style.display = x.dataset.survey === e.target.value ? 'block' : 'none');
});
</script>
</section></main></div><script src="/assets/js/app.js"></script></body></html>
