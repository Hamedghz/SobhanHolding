<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/ManagerDashboard.php';

Auth::requireLogin();
if (!Auth::can('manager_dashboard.image_export')) { http_response_code(403); exit('شما اجازه دریافت خروجی تصویری را ندارید.'); }
if (ManagerDashboard::setting('image_export_enabled') !== '1') { http_response_code(403); exit('خروجی تصویری غیرفعال است.'); }
$widgetKey = trim($_GET['widget_key'] ?? '');
$definition = ManagerDashboard::definitions()[$widgetKey] ?? null;
$widget = Database::fetch('SELECT * FROM manager_dashboard_widget_settings WHERE widget_key=? AND is_enabled=1 AND show_in_dashboard=1', [$widgetKey]);
if (!$definition || !$widget || !(int)($widget['allow_image_export'] ?? 0)) { http_response_code(404); exit('ویجت معتبر یا فعال نیست.'); }
$report = ManagerDashboard::latestReport((int)($_GET['report_id'] ?? 0));
if (!$report) { http_response_code(404); exit('گزارش پیدا نشد.'); }
$format = strtolower(trim($_GET['format'] ?? ManagerDashboard::setting('image_export_format')));
if (!in_array($format, ['png','jpg'], true)) $format = 'png';
$page = max(1, (int)($_GET['page'] ?? 1));
$filters = ['search'=>trim($_GET['search']??''),'line_code'=>trim($_GET['line_code']??''),'visitor'=>trim($_GET['visitor']??''),'supervisor'=>trim($_GET['supervisor']??'')];
$rows = ManagerDashboard::filteredRows($widgetKey, (int)$report['id'], $filters, 25, ($page-1)*25);
$dateLabel = ManagerDashboard::setting('date_format') === 'gregorian' ? $report['report_date'] : format_jalali_date($report['report_date']);
$fileDate = str_replace(['/','\\'], '-', format_jalali_date($report['report_date']));
$fileName = 'manager-dashboard-'.str_replace('_','-',$widgetKey).'-'.$fileDate.'.'.$format;

function manager_image_value($value, string $type): string {
    if (in_array($type, ['money','signed_money'], true)) return (ManagerDashboard::setting('number_format')==='plain'?(string)(float)$value:number_format((float)$value)).' '.ManagerDashboard::setting('currency_label');
    if ($type === 'percent') return number_format((float)$value,1).'٪';
    if ($type === 'date') return ManagerDashboard::setting('date_format')==='gregorian'?(string)$value:format_jalali_date((string)$value);
    if ($type === 'entity') return ['visitor'=>'ویزیتور','supervisor'=>'سرپرست','manager'=>'مدیر فروش'][$value]??$value;
    if ($type === 'status') return ['ok'=>'واجد شرایط'][$value]??$value;
    return (string)$value;
}
Auth::log((int)(Auth::user()['id']??0), 'image_export', 'manager_dashboard', (int)$report['id']);
?>
<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=e($widget['widget_title_fa'])?></title><style>
*{box-sizing:border-box}body{margin:0;padding:24px;background:#eef2f7;color:#0f172a;font-family:Tahoma,Arial,sans-serif}.toolbar{display:flex;gap:8px;justify-content:center;margin-bottom:18px}.toolbar button{padding:10px 18px;border:0;border-radius:9px;background:#0f766e;color:#fff;font:inherit;font-weight:800;cursor:pointer}.toolbar button+button{background:#475569}.capture{position:relative;width:max-content;min-width:100%;padding:28px;background:#fff;border-radius:16px}.capture header{display:flex;align-items:flex-end;justify-content:space-between;gap:30px;margin-bottom:20px;padding-bottom:16px;border-bottom:3px solid #0f766e}.capture h1{margin:0;font-size:24px}.capture header p{margin:6px 0 0;color:#64748b}.meta{text-align:left;color:#334155;font-size:13px;line-height:1.9}.table-wrap{overflow:visible}table{width:100%;border-collapse:collapse;font-size:12px}th,td{padding:10px 12px;border:1px solid #cbd5e1;text-align:right;white-space:nowrap}th{background:#0f766e;color:#fff;font-weight:900}tbody tr:nth-child(even){background:#f8fafc}.empty{text-align:center;color:#64748b}.watermark{position:absolute;left:28px;bottom:8px;color:rgba(15,118,110,.22);font-size:16px;font-weight:900}.footer{margin-top:14px;color:#64748b;font-size:11px}@media print{body{padding:0;background:#fff}.toolbar{display:none}.capture{border-radius:0}.watermark{color:#94a3b8}}
</style></head><body><div class="toolbar"><button type="button" id="downloadButton">دریافت <?=strtoupper($format)?></button><button type="button" onclick="window.print()">چاپ</button></div><main class="capture" id="capture"><header><div><?php if(ManagerDashboard::setting('image_export_include_title')==='1'):?><h1><?=e($widget['widget_title_fa'])?></h1><?php endif?><p><?=e($report['report_title'])?></p></div><div class="meta"><?php if(ManagerDashboard::setting('image_export_include_company_name')==='1'):?><strong><?=e(setting('company_name','شرکت پخش سبحان'))?></strong><br><?php endif?><?php if(ManagerDashboard::setting('image_export_include_report_date')==='1'):?>تاریخ گزارش: <?=e($dateLabel)?><?php endif?></div></header><div class="table-wrap"><table><thead><tr><?php foreach($definition['fields'] as $field):?><th><?=e($field[1])?></th><?php endforeach?></tr></thead><tbody><?php foreach($rows as $row):?><tr><?php foreach($definition['fields'] as [$key,$label,$type]):?><td><?=e(manager_image_value($row[$key],$type))?></td><?php endforeach?></tr><?php endforeach?><?php if(!$rows):?><tr><td class="empty" colspan="<?=count($definition['fields'])?>">داده‌ای مطابق فیلترهای انتخابی وجود ندارد.</td></tr><?php endif?></tbody></table></div><div class="footer">خروجی صفحه <?=e((string)$page)?></div><?php if(ManagerDashboard::setting('image_export_watermark_enabled')==='1'):?><div class="watermark"><?=e(ManagerDashboard::setting('image_export_watermark_text'))?></div><?php endif?></main><script>
(function(){
 const target=document.getElementById('capture'),button=document.getElementById('downloadButton');
 async function downloadImage(){
  if(document.fonts&&document.fonts.ready)await document.fonts.ready;
  const width=Math.ceil(target.scrollWidth),height=Math.ceil(target.scrollHeight),scale=Math.min(2,10000/Math.max(width,height));
  const clone=target.cloneNode(true);clone.setAttribute('xmlns','http://www.w3.org/1999/xhtml');
  const styles=document.querySelector('style').textContent;
  const markup=new XMLSerializer().serializeToString(clone);
  const svg='<svg xmlns="http://www.w3.org/2000/svg" width="'+width+'" height="'+height+'"><foreignObject width="100%" height="100%"><div xmlns="http://www.w3.org/1999/xhtml"><style>'+styles+'</style>'+markup+'</div></foreignObject></svg>';
  const image=new Image();image.onload=function(){const canvas=document.createElement('canvas');canvas.width=Math.round(width*scale);canvas.height=Math.round(height*scale);const context=canvas.getContext('2d');context.scale(scale,scale);context.fillStyle='#ffffff';context.fillRect(0,0,width,height);context.drawImage(image,0,0);const link=document.createElement('a');link.download=<?=json_encode($fileName,JSON_UNESCAPED_UNICODE)?>;link.href=canvas.toDataURL(<?=$format==='jpg'?'"image/jpeg",0.94':'"image/png"'?>);link.click();URL.revokeObjectURL(image.src)};image.onerror=function(){alert('ساخت خروجی تصویری ناموفق بود.')};image.src=URL.createObjectURL(new Blob([svg],{type:'image/svg+xml;charset=utf-8'}));
 }
 button.addEventListener('click',downloadImage);
 <?php if(isset($_GET['print'])):?>window.addEventListener('load',()=>window.print());<?php else:?>window.addEventListener('load',downloadImage);<?php endif?>
})();
</script></body></html>
