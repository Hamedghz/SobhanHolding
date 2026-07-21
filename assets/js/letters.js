(()=>{
    'use strict';
    const q=(selector,root=document)=>root.querySelector(selector);
    const qa=(selector,root=document)=>[...root.querySelectorAll(selector)];
    const instances=new Map();

    qa('[data-rich-editor]').forEach(editor=>{
        const surface=q('[data-editor-surface]',editor);
        const toolbar=q('[data-quill-toolbar]',editor);
        const form=editor.closest('form');
        const htmlInput=form&&q('[data-editor-input]',form);
        const deltaInput=form&&q('[data-editor-delta]',form);
        if(!surface||!toolbar||typeof window.Quill!=='function')return;
        const quill=new window.Quill(surface,{
            theme:'snow',
            modules:{
                toolbar:{
                    container:toolbar,
                    handlers:{
                        undo(){this.quill.history.undo()},
                        redo(){this.quill.history.redo()}
                    }
                },
                history:{delay:700,maxStack:120,userOnly:true}
            },
            formats:['font','size','bold','italic','underline','color','background','direction','align','list','indent','link']
        });
        quill.root.setAttribute('dir','rtl');
        quill.root.setAttribute('lang','fa');
        const sync=()=>{
            if(htmlInput)htmlInput.value=quill.root.innerHTML;
            if(deltaInput)deltaInput.value=JSON.stringify(quill.getContents());
        };
        if(deltaInput?.value){
            try{const delta=JSON.parse(deltaInput.value);if(delta?.ops)quill.setContents(delta,'silent')}catch{}
        }
        sync();
        quill.on('text-change',sync);
        form?.addEventListener('submit',sync);
        qa('[data-insert-variable]',form||document).forEach(button=>button.addEventListener('click',()=>{
            const selection=quill.getSelection(true);
            quill.insertText(selection?.index??quill.getLength()-1,button.dataset.insertVariable||'','user');
            quill.focus();
        }));
        instances.set(editor,quill);
    });

    const templateSelect=q('[data-template-select]');
    const payload=q('#letterTemplateData');
    if(templateSelect&&payload){
        let data={};
        try{data=JSON.parse(payload.textContent)}catch{}
        templateSelect.addEventListener('change',()=>{
            const template=data[templateSelect.value];
            if(!template||!confirm('مقادیر پیش‌فرض قالب روی فرم اعمال شود؟'))return;
            const set=(selector,value)=>{const element=q(selector);if(element&&value!==null&&value!=='')element.value=value};
            set('[data-subject-input]',template.subject);
            set('[data-letterhead-select]',template.letterhead_id);
            set('[data-signature-select]',template.signature_id);
            set('[data-paper-select]',template.paper_size);
            set('[data-orientation-select]',template.orientation);
            const editor=q('[data-rich-editor]');
            const quill=editor&&instances.get(editor);
            if(quill){
                let applied=false;
                if(template.delta){try{const delta=typeof template.delta==='string'?JSON.parse(template.delta):template.delta;if(delta?.ops){quill.setContents(delta,'user');applied=true}}catch{}}
                if(!applied&&template.body)quill.clipboard.dangerouslyPasteHTML(template.body,'user');
            }
        });
    }

    const dialog=q('[data-preview-dialog]');
    q('[data-preview-toggle]')?.addEventListener('click',()=>{
        const content=q('[data-preview-content]');
        const editor=q('[data-rich-editor]');
        const quill=editor&&instances.get(editor);
        if(content&&quill)content.innerHTML=quill.root.innerHTML;
        dialog?.showModal();
    });
    q('[data-preview-close]')?.addEventListener('click',()=>dialog?.close());
})();
