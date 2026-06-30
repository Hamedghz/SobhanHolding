<nav class="mg-tabs"><a href="/admin/management-meetings.php">صورتجلسات</a><a href="/admin/management-decisions.php">مصوبات و پیگیری‌ها</a><a href="/admin/management-rules.php">قوانین مصوب</a></nav>
<script>
document.addEventListener('DOMContentLoaded',()=>{
  const advancedNames=new Set(['meeting_type','location','start_time','end_time','secretary_user_id','absent_users[]','decision_type','category','responsible_unit_id','is_rule','rule_effective_date','rule_expire_date','latest_followup_note']);
  document.querySelectorAll('.mg-form-grid').forEach(form=>{
    const labels=[...form.querySelectorAll(':scope > label')].filter(label=>[...label.querySelectorAll('input,select,textarea')].some(field=>advancedNames.has(field.name)));
    if(!labels.length)return;const details=document.createElement('details');details.className='wide mg-advanced';const summary=document.createElement('summary');summary.textContent='تنظیمات پیشرفته';details.append(summary);labels.forEach(label=>details.append(label));const action=[...form.children].find(el=>el.classList?.contains('wide')&&el.querySelector('button'));form.insertBefore(details,action||null);
  });
  document.querySelectorAll('select[name="followup_status"] option').forEach(option=>{if(['not_started','assigned','needs_revision'].includes(option.value))option.remove();});
});
</script>
<?php if(isset($analytics)&&is_array($analytics)):$topResponsible=array_key_first($analytics['open_by_responsible']??[]);$topUnit=array_key_first($analytics['overdue_by_unit']??[]);?>
<section class="mg-kpis" aria-label="خلاصه مدیریتی"><article class="mg-kpi"><span>میانگین زمان انجام</span><strong><?=e((string)$analytics['average_days'])?> روز</strong></article><article class="mg-kpi"><span>تحقق ماه جاری</span><strong><?=format_number($analytics['month_percent'])?>٪</strong></article><article class="mg-kpi"><span>قوانین فعال / منقضی</span><strong><?=format_number($analytics['active_rules'])?> / <?=format_number($analytics['expired_rules'])?></strong></article><article class="mg-kpi"><span>بیشترین تعهد باز</span><strong><?=e($topResponsible?:'—')?></strong></article><article class="mg-kpi"><span>بیشترین تأخیر واحد</span><strong><?=e($topUnit?:'—')?></strong></article></section>
<?php endif?>
