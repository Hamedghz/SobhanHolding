(() => {
  'use strict';

  const data = window.SobhanActionHub || {};
  const typeSelect = document.querySelector('[data-action-type]');
  const templateSelect = document.querySelector('[data-action-template]');
  const dynamicRoot = document.querySelector('[data-action-dynamic]');

  const fillSelect = (select, items, placeholder) => {
    if (!select || select.options.length > 1) return;
    select.append(new Option(placeholder, ''));
    (items || []).forEach(item => select.append(new Option(item.title, item.id)));
  };
  document.querySelectorAll('[data-action-users]').forEach(select => fillSelect(select, data.users, 'انتخاب کاربر'));
  document.querySelectorAll('[data-action-org-units]').forEach(select => fillSelect(select, data.orgUnits, 'انتخاب واحد'));
  document.querySelectorAll('[data-action-sales-lines]').forEach(select => fillSelect(select, data.salesLines, 'انتخاب لاین'));

  const syncTemplates = () => {
    if (!templateSelect) return;
    const type = typeSelect?.value || '';
    Array.from(templateSelect.options).forEach(option => {
      if (!option.value) return;
      option.hidden = type !== '' && option.dataset.type !== type;
      option.disabled = option.hidden;
    });
    if (templateSelect.selectedOptions[0]?.disabled) templateSelect.value = '';
    if (templateSelect.dataset.actionAutoTemplate === '1' && !templateSelect.value) {
      const firstCompatible = Array.from(templateSelect.options).find(option => option.value && !option.disabled);
      if (firstCompatible) templateSelect.value = firstCompatible.value;
    }
    syncFields();
  };

  const syncFields = () => {
    if (!dynamicRoot || !templateSelect) return;
    const selected = templateSelect.value;
    dynamicRoot.querySelectorAll('[data-template-fields]').forEach(section => {
      const active = section.dataset.templateFields === selected;
      section.hidden = !active;
      section.querySelectorAll('input,select,textarea').forEach(control => {
        control.disabled = !active;
      });
      if (active && window.Motion?.animate && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        window.Motion.animate(section, {opacity:[0,1],transform:['translateY(6px)','translateY(0)']}, {duration:.2,ease:'easeOut'});
      }
    });
  };

  typeSelect?.addEventListener('change', syncTemplates);
  templateSelect?.addEventListener('change', syncFields);
  syncTemplates();

  if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches && window.Motion?.animate) {
    document.querySelectorAll('[data-action-reveal]').forEach((element, index) => {
      window.Motion.animate(
        element,
        {opacity:[0,1],transform:['translateY(7px)','translateY(0)']},
        {duration:.23,delay:Math.min(index,7)*.025,ease:'easeOut'}
      );
    });
  }
})();
