(function () {
  'use strict';

  const reduceMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
  if (reduceMotion || !window.Motion?.animate) return;

  const {animate, inView} = window.Motion;

  function revealDatepicker() {
    requestAnimationFrame(() => {
      const picker = document.querySelector('.jdp-container');
      if (!picker || picker.dataset.motionReady === '1') return;
      picker.dataset.motionReady = '1';
      animate(
        picker,
        {opacity: [0, 1], transform: ['translateY(8px) scale(.985)', 'translateY(0) scale(1)']},
        {duration: .2, ease: 'easeOut'}
      );
    });
  }

  document.addEventListener('focusin', event => {
    if (event.target instanceof HTMLInputElement &&
        event.target.matches('[data-jalali-date], .jalali-date-input')) {
      revealDatepicker();
    }
  });

  document.addEventListener('change', event => {
    const selector = event.target instanceof Element ? event.target.closest('[data-period-selector]') : null;
    if (!selector || selector.value !== 'custom') return;
    const target = document.querySelector(selector.getAttribute('data-custom-period-target') || '');
    if (!target) return;
    animate(
      target,
      {opacity: [0, 1], transform: ['translateY(-6px)', 'translateY(0)']},
      {duration: .18, ease: 'easeOut'}
    );
  });

  const cards = Array.from(document.querySelectorAll(
    '.admin-content > .card, .admin-content > section.card, .dashboard-widget-shell'
  )).slice(0, 16);
  if (cards.length && inView) {
    inView(cards, element => {
      animate(
        element,
        {opacity: [0, 1], transform: ['translateY(7px)', 'translateY(0)']},
        {duration: .22, ease: 'easeOut'}
      );
    }, {amount: .08});
  }
})();
