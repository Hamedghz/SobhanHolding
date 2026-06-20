<?php
require_once __DIR__.'/../core/Auth.php';require_once __DIR__.'/../core/Database.php';require_once __DIR__.'/../core/ManagerDashboard.php';
Auth::requirePermission('manager_dashboard.export');
$template=isset($_GET['template']);$reportDate=trim($_GET['report_date']??'');
$report=$reportDate!==''?Database::fetch('SELECT * FROM manager_dashboard_reports WHERE report_date=? AND import_status="success" ORDER BY id DESC LIMIT 1',[JalaliDate::toGregorian($reportDate)?:$reportDate]):ManagerDashboard::latestReport((int)($_GET['report_id']??0)?:null);$widget=trim($_GET['widget']??'')?:null;
if(!$template&&!$report){http_response_code(404);exit('گزارشی برای خروجی وجود ندارد.');}
if($widget){$widgetSetting=Database::fetch('SELECT is_enabled,allow_export FROM manager_dashboard_widget_settings WHERE widget_key=?',[$widget]);if(!isset(ManagerDashboard::definitions()[$widget])||!$widgetSetting||(int)$widgetSetting['is_enabled']!==1){http_response_code(404);exit('جدول معتبر نیست.');}if((int)$widgetSetting['allow_export']!==1){http_response_code(403);exit('خروجی این جدول غیرفعال است.');}}
$sheets=ManagerDashboard::sheets($template?null:(int)$report['id'],$template,$widget);
Auth::log((int)(Auth::user()['id']??0),'export','manager_dashboard',$template?null:(int)$report['id']);
CeoDashboardExcel::output($sheets,($template?'manager-dashboard-template':'manager-dashboard-'.($report['report_date']??date('Y-m-d'))).'.xlsx');
