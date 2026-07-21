(()=>{
    'use strict';
    const widget=document.querySelector('[data-dashboard-planner]');
    if(!widget)return;
    const endpoint=widget.dataset.endpoint;
    const errorBox=widget.querySelector('[data-planner-inline-error]');
    const setError=message=>{
        if(!errorBox)return;
        errorBox.textContent=message||'';
        errorBox.hidden=!message;
    };
    const key=()=>globalThis.crypto?.randomUUID?.()||`${Date.now()}-${Math.random().toString(16).slice(2)}`;
    async function submit(form){
        if(form.dataset.submitting==='1')return;
        setError('');
        form.dataset.submitting='1';
        const buttons=[...form.querySelectorAll('button')];
        buttons.forEach(button=>button.disabled=true);
        const body=new FormData(form);
        if(['quick_add','planner_quick_add'].includes(String(body.get('action')))){
            form.dataset.requestKey=form.dataset.requestKey||key();
            body.set('client_request_key',form.dataset.requestKey);
        }
        try{
            const response=await fetch(endpoint,{method:'POST',body,credentials:'same-origin',headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}});
            const type=response.headers.get('content-type')||'';
            if(!type.includes('application/json'))throw new Error('پاسخ پلنر معتبر نیست.');
            const result=await response.json();
            if(!response.ok||!result.success)throw new Error(result.message||'عملیات پلنر انجام نشد.');
            location.reload();
        }catch(error){
            setError(error?.message||'ارتباط با پلنر برقرار نشد.');
            form.dataset.submitting='0';
            buttons.forEach(button=>button.disabled=false);
        }
    }
    widget.addEventListener('submit',event=>{
        const form=event.target.closest('form[data-planner-ajax]');
        if(!form)return;
        event.preventDefault();
        submit(form);
    });
})();
