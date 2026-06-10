<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';
header('Content-Type: application/json; charset=utf-8');
Auth::requireLogin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Auth::verifyCsrf($_POST['csrf_token'] ?? '')) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'درخواست نامعتبر است'], JSON_UNESCAPED_UNICODE); exit; }
$user=Auth::user();$surveyId=(int)($_POST['survey_id']??0);
if (!Auth::can('survey_results','create')) { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'دسترسی غیرمجاز'], JSON_UNESCAPED_UNICODE); exit; }
if (!Auth::isAdmin() && !Database::fetch('SELECT id FROM survey_assignments WHERE survey_id=? AND user_id=?',[$surveyId,$user['id']])) { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'دسترسی غیرمجاز'], JSON_UNESCAPED_UNICODE); exit; }
$employeeId=(int)($_POST['employee_id']??0);
if(!$employeeId||!Auth::canAccessEmployee($employeeId)){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'دسترسی غیرمجاز'],JSON_UNESCAPED_UNICODE);exit;}
$employee=Database::fetch('SELECT id,name FROM users WHERE id=? AND role="employee"',[$employeeId]);
if(!$employee){http_response_code(422);echo json_encode(['ok'=>false,'error'=>'کارمند معتبر نیست'],JSON_UNESCAPED_UNICODE);exit;}
$kpis=Database::fetchAll('SELECT kpi_id,weight FROM survey_kpis WHERE survey_id=?',[$surveyId]);$sum=0;$sumW=0;foreach($kpis as $k){$score=max(0,min(100,(float)($_POST['score'][$k['kpi_id']]??0)));$sum+=$score*(float)$k['weight'];$sumW+=(float)$k['weight'];}$final=$sumW?$sum/$sumW:0;
$pdo=Database::connection();$pdo->beginTransaction();try{Database::execute('INSERT INTO survey_results (survey_id,user_id,employee_id,employee_name,final_score,created_at,updated_at) VALUES (?,?,?,?,?,NOW(),NOW())',[$surveyId,$user['id'],$employeeId,$employee['name'],$final]);$rid=(int)Database::lastInsertId();foreach($kpis as $k){$score=max(0,min(100,(float)($_POST['score'][$k['kpi_id']]??0)));Database::execute('INSERT INTO survey_result_items (result_id,kpi_id,score,weighted_score,created_at) VALUES (?,?,?,?,NOW())',[$rid,$k['kpi_id'],$score,$score*(float)$k['weight']]);}Database::execute('UPDATE survey_assignments SET status="completed" WHERE survey_id=? AND user_id=?',[$surveyId,$user['id']]);$pdo->commit();echo json_encode(['ok'=>true,'final_score'=>$final], JSON_UNESCAPED_UNICODE);}catch(Throwable $e){$pdo->rollBack();http_response_code(500);echo json_encode(['ok'=>false,'error'=>'خطا در ثبت نتیجه'], JSON_UNESCAPED_UNICODE);}
