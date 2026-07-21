(() => {
  'use strict';

  const data = window.SobhanDailyReport || {};
  const sourceSelect = document.querySelector('[data-daily-source]');
  const sourceKeySelect = document.querySelector('[data-daily-source-key]');
  const formulaField = document.querySelector('[data-daily-formula]');
  const syncSource = () => {
    if (!sourceSelect || !sourceKeySelect) return;
    const source = sourceSelect.value;
    const current = sourceKeySelect.dataset.current || sourceKeySelect.value;
    sourceKeySelect.replaceChildren(new Option(source === 'manual' || source === 'calculated' ? 'برای این منبع لازم نیست' : 'انتخاب کنید', ''));
    Object.entries((data.sourceKeys || {})[source] || {}).forEach(([value, label]) => {
      const option = new Option(label, value);
      option.selected = value === current;
      sourceKeySelect.append(option);
    });
    sourceKeySelect.disabled = source === 'manual' || source === 'calculated';
    if (formulaField) formulaField.hidden = source !== 'calculated';
  };
  sourceSelect?.addEventListener('change', () => {
    if (sourceKeySelect) sourceKeySelect.dataset.current = '';
    syncSource();
  });
  syncSource();

  const scopeSelect = document.querySelector('[data-daily-scope]');
  const scopeValue = document.querySelector('[data-daily-scope-value]');
  const syncScope = () => {
    if (!scopeSelect || !scopeValue) return;
    const items = (data.scopeOptions || {})[scopeSelect.value] || [];
    scopeValue.replaceChildren();
    items.forEach(item => scopeValue.append(new Option(item.title, item.id)));
    scopeValue.disabled = scopeSelect.value === 'company';
  };
  scopeSelect?.addEventListener('change', syncScope);
  syncScope();

  if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches && window.Motion?.animate) {
    document.querySelectorAll('[data-daily-reveal]').forEach((element, index) => {
      window.Motion.animate(
        element,
        {opacity:[0,1],transform:['translateY(7px)','translateY(0)']},
        {duration:.23,delay:Math.min(index,8)*.025,ease:'easeOut'}
      );
    });
  }
})();
