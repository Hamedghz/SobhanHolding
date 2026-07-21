(() => {
  'use strict';

  const form = document.querySelector('[data-target-form]');
  if (form) {
    const line = form.querySelector('[data-target-line]');
    const visitor = form.querySelector('[data-target-visitor]');
    const syncVisitors = () => {
      const selectedLine = line?.value || '';
      Array.from(visitor?.options || []).forEach(option => {
        if (!option.value) return;
        option.hidden = selectedLine !== '' && option.dataset.line !== selectedLine;
        option.disabled = option.hidden;
      });
      if (visitor?.selectedOptions?.[0]?.disabled) visitor.value = '';
      if (window.Motion?.animate && visitor) {
        window.Motion.animate(visitor, {opacity:[.55,1],transform:['translateY(3px)','translateY(0)']}, {duration:.18,ease:'easeOut'});
      }
    };
    line?.addEventListener('change', syncVisitors);
    syncVisitors();
  }

  const reduceMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
  if (reduceMotion || !window.Motion?.animate) return;
  document.querySelectorAll('[data-planning-reveal]').forEach((element, index) => {
    window.Motion.animate(
      element,
      {opacity:[0,1],transform:['translateY(7px)','translateY(0)']},
      {duration:.22,delay:Math.min(index,6)*.025,ease:'easeOut'}
    );
  });
})();
