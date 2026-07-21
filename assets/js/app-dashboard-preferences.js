(function () {
  'use strict';

  const widgets = Array.from(document.querySelectorAll('[data-dashboard-refresh-seconds]'));
  const refreshValues = widgets
    .map(widget => Number.parseInt(widget.dataset.dashboardRefreshSeconds || '0', 10))
    .filter(seconds => Number.isFinite(seconds) && seconds >= 60 && seconds <= 3600);

  if (!refreshValues.length) return;

  const refreshAfter = Math.min(...refreshValues) * 1000;
  const dueAt = Date.now() + refreshAfter;

  function refreshWhenSafe() {
    if (document.visibilityState !== 'visible') return;
    if (document.querySelector('dialog[open], form[data-prevent-dashboard-refresh="1"]')) {
      window.setTimeout(refreshWhenSafe, 30000);
      return;
    }
    window.location.reload();
  }

  window.setTimeout(refreshWhenSafe, refreshAfter);
  document.addEventListener('visibilitychange', () => {
    if (Date.now() >= dueAt) refreshWhenSafe();
  });
})();
