(function () {
  'use strict';

  if (!document.body.classList.contains('app-compact-ui')) return;

  const reduceMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

  function decorateTables(root = document) {
    const tables = [
      ...(root.matches?.('.table-wrap > table:not([data-no-mobile-cards])') ? [root] : []),
      ...root.querySelectorAll('.table-wrap > table:not([data-no-mobile-cards])')
    ];
    tables.forEach(table => {
      if (table.dataset.compactTableReady === '1') return;
      const headers = Array.from(table.querySelectorAll('thead th')).map(header =>
        (header.textContent || '').trim()
      );
      if (headers.length < 2) return;

      table.dataset.compactTableReady = '1';
      table.classList.add('app-mobile-cards');
      table.querySelectorAll('tbody tr').forEach(row => {
        const cells = Array.from(row.children).filter(cell => cell instanceof HTMLTableCellElement);
        if (cells.length === 1 && cells[0].hasAttribute('colspan')) {
          cells[0].classList.add('app-empty-state');
          return;
        }
        cells.forEach((cell, index) => {
          if (!cell.hasAttribute('data-label')) {
            cell.setAttribute('data-label', headers[index] || 'اطلاعات');
          }
        });
      });
    });
  }

  function decorateAdvancedSections(root = document) {
    const sections = [
      ...(root.matches?.('details') ? [root] : []),
      ...root.querySelectorAll('details')
    ];
    sections.forEach(details => {
      if (details.closest('.notification-dropdown')) return;
      details.classList.add('app-advanced-section');
      if (details.dataset.compactMotionReady === '1') return;
      details.dataset.compactMotionReady = '1';
      details.addEventListener('toggle', () => {
        if (!details.open || reduceMotion || !window.Motion?.animate) return;
        const content = Array.from(details.children).filter(child => child.tagName !== 'SUMMARY');
        if (!content.length) return;
        window.Motion.animate(
          content,
          {opacity: [0, 1], transform: ['translateY(-5px)', 'translateY(0)']},
          {duration: .18, ease: 'easeOut'}
        );
      });
    });
  }

  function decorateActionBars(root = document) {
    const forms = [
      ...(root.matches?.('form') ? [root] : []),
      ...root.querySelectorAll('form')
    ];
    forms.forEach(form => {
      if (form.closest('td') || form.closest('.notification-dropdown')) return;
      const controls = form.querySelectorAll('input:not([type="hidden"]), select, textarea').length;
      if (controls < 4) return;

      const bars = Array.from(form.children).filter(child => child.classList?.contains('form-actions'));
      const bar = bars[bars.length - 1];
      if (bar) {
        bar.classList.add('app-sticky-actions');
        return;
      }

      const directSubmit = Array.from(form.children).reverse().find(child =>
        child instanceof HTMLButtonElement && (!child.type || child.type === 'submit')
      );
      directSubmit?.classList.add('app-sticky-submit');
    });
  }

  function initialize(root = document) {
    decorateTables(root);
    decorateAdvancedSections(root);
    decorateActionBars(root);
  }

  initialize();

  const observer = new MutationObserver(records => {
    records.forEach(record => {
      record.addedNodes.forEach(node => {
        if (node instanceof Element) initialize(node);
      });
    });
  });
  observer.observe(document.body, {childList: true, subtree: true});
})();
