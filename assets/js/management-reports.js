document.addEventListener('DOMContentLoaded',()=>{
  const reduceMotion=window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
  const motion=!reduceMotion&&window.Motion?.animate?window.Motion:null;
  const period=document.querySelector('[data-report-period]');
  const syncPeriod=()=>{if(!period)return;const option=period.selectedOptions[0];for(const key of ['title','start','end']){const input=document.querySelector(`[data-period-${key}]`);if(input)input.value=option?.dataset[key]||''}};
  period?.addEventListener('change',syncPeriod);syncPeriod();
  document.querySelectorAll('[data-repeater]').forEach(root=>{
    const hidden=root.querySelector('input[type=hidden]'),body=root.querySelector('tbody');let columns=[];try{columns=JSON.parse(root.dataset.columns||'[]')}catch(e){}if(!columns.length)columns=[{key:'value',label:'مقدار'}];
    const serialize=()=>{hidden.value=JSON.stringify([...body.querySelectorAll('tr')].map(row=>Object.fromEntries(columns.map(c=>[c.key,row.querySelector(`[data-key="${c.key}"]`)?.value||'']))))};
    const add=(item={})=>{const row=document.createElement('tr');columns.forEach(column=>{const cell=document.createElement('td'),input=document.createElement('input');input.type='text';input.dataset.key=column.key;input.placeholder=column.label||column.key;input.value=item[column.key]||'';input.addEventListener('input',serialize);cell.append(input);row.append(cell)});const action=document.createElement('td'),remove=document.createElement('button');remove.type='button';remove.className='btn btn-small';remove.textContent='حذف ردیف';remove.addEventListener('click',()=>{row.remove();serialize()});action.append(remove);row.append(action);body.append(row)};
    let initial=[];try{initial=JSON.parse(hidden.value||'[]')}catch(e){};(initial.length?initial:[{}]).forEach(add);root.querySelector('[data-add-row]')?.addEventListener('click',()=>{add();serialize()});root.closest('form')?.addEventListener('submit',serialize);
  });

  document.querySelectorAll('[data-field-builder]').forEach(builder=>{
    const type=builder.querySelector('[data-field-type]');
    const label=builder.querySelector('[data-field-label]');
    const preview=builder.querySelector('[data-field-preview]');
    const options=builder.querySelector('[data-options-panel]');
    const labels=Object.fromEntries([...type?.options||[]].map(option=>[option.value,option.textContent.trim()]));
    const sync=()=>{
      const selected=type?.value||'text';
      const showOptions=['select','table','repeater'].includes(selected);
      if(options){
        options.hidden=!showOptions;
        options.querySelector('textarea')?.toggleAttribute('disabled',!showOptions);
      }
      if(preview){
        preview.querySelector('strong').textContent=label?.value.trim()||'فیلد جدید';
        preview.querySelector('small').textContent=labels[selected]||selected;
        if(motion)motion.animate(preview,{opacity:[.55,1],transform:['translateY(4px) scale(.99)','translateY(0) scale(1)']},{duration:.18,ease:'easeOut'});
      }
    };
    type?.addEventListener('change',sync);
    label?.addEventListener('input',sync);
    sync();
  });

  document.querySelectorAll('[data-action-converter], .mr-builder').forEach((element,index)=>{
    if(motion)motion.animate(element,{opacity:[0,1],transform:['translateY(10px)','translateY(0)']},{duration:.24,delay:index*.04,ease:'easeOut'});
  });
  document.querySelectorAll('.mr-builder button,.mr-action-converter button').forEach(button=>{
    button.addEventListener('pointerdown',()=>{if(motion)motion.animate(button,{transform:['scale(1)','scale(.975)']},{duration:.09})});
    button.addEventListener('pointerup',()=>{if(motion)motion.animate(button,{transform:['scale(.975)','scale(1)']},{duration:.12})});
  });
});
