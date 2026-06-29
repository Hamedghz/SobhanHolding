document.addEventListener('DOMContentLoaded',()=>{
  const period=document.querySelector('[data-report-period]');
  const syncPeriod=()=>{if(!period)return;const option=period.selectedOptions[0];for(const key of ['title','start','end']){const input=document.querySelector(`[data-period-${key}]`);if(input)input.value=option?.dataset[key]||''}};
  period?.addEventListener('change',syncPeriod);syncPeriod();
  document.querySelectorAll('[data-repeater]').forEach(root=>{
    const hidden=root.querySelector('input[type=hidden]'),body=root.querySelector('tbody');let columns=[];try{columns=JSON.parse(root.dataset.columns||'[]')}catch(e){}if(!columns.length)columns=[{key:'value',label:'مقدار'}];
    const serialize=()=>{hidden.value=JSON.stringify([...body.querySelectorAll('tr')].map(row=>Object.fromEntries(columns.map(c=>[c.key,row.querySelector(`[data-key="${c.key}"]`)?.value||'']))))};
    const add=(item={})=>{const row=document.createElement('tr');columns.forEach(column=>{const cell=document.createElement('td'),input=document.createElement('input');input.type='text';input.dataset.key=column.key;input.placeholder=column.label||column.key;input.value=item[column.key]||'';input.addEventListener('input',serialize);cell.append(input);row.append(cell)});const action=document.createElement('td'),remove=document.createElement('button');remove.type='button';remove.className='btn btn-small';remove.textContent='حذف ردیف';remove.addEventListener('click',()=>{row.remove();serialize()});action.append(remove);row.append(action);body.append(row)};
    let initial=[];try{initial=JSON.parse(hidden.value||'[]')}catch(e){};(initial.length?initial:[{}]).forEach(add);root.querySelector('[data-add-row]')?.addEventListener('click',()=>{add();serialize()});root.closest('form')?.addEventListener('submit',serialize);
  });
});
