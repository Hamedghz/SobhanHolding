(()=>{
    'use strict';
    const indicator=document.querySelector('[data-sobhan-ai-status]');
    if(!indicator)return;
    const dot=indicator.querySelector('[data-ai-status-dot]');
    const label=indicator.querySelector('[data-ai-status-label]');
    const format=value=>{
        if(!value)return 'هنوز اتصال موفقی ثبت نشده است';
        try{return `آخرین اتصال موفق: ${new Intl.DateTimeFormat('fa-IR',{dateStyle:'short',timeStyle:'short'}).format(new Date(value.replace(' ','T')))}`}catch{return 'آخرین اتصال موفق ثبت شده است'}
    };
    const render=data=>{
        const healthy=Boolean(data?.healthy);
        indicator.classList.toggle('is-healthy',healthy);
        indicator.classList.toggle('is-unavailable',!healthy);
        indicator.setAttribute('aria-label',healthy?'هوش مصنوعی سبحان متصل است':'هوش مصنوعی سبحان در دسترس نیست');
        indicator.title=format(data?.last_success_at);
        if(dot)dot.setAttribute('aria-hidden','true');
        if(label)label.textContent=healthy?'AI متصل':'AI قطع';
    };
    let refreshing=false;
    const refresh=()=>{
        if(refreshing||document.hidden)return;
        refreshing=true;
        const controller=new AbortController();
        const timeout=setTimeout(()=>controller.abort(),5000);
        fetch('/admin/ajax/sobhan-ai-status.php',{
            credentials:'same-origin',
            headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'},
            signal:controller.signal
        })
            .then(response=>response.headers.get('content-type')?.includes('application/json')?response.json():Promise.reject())
            .then(result=>render(result?.data))
            .catch(()=>render({healthy:false}))
            .finally(()=>{clearTimeout(timeout);refreshing=false});
    };
    refresh();
    setInterval(refresh,60000);
    document.addEventListener('visibilitychange',()=>{if(!document.hidden)refresh()});
})();
