(function () {
  'use strict';

  const form = document.querySelector('[data-formula-builder]');
  if (!form) return;

  const sources = window.SOBHAN_FORMULA_SOURCES || {};
  const sourceSelect = form.querySelector('[data-formula-source]');
  const metricSelect = form.querySelector('[data-formula-metric]');
  const comparisonSelect = form.querySelector('[data-formula-comparison]');
  const aggregationSelect = form.querySelector('[data-formula-aggregation]');
  const comparisonField = form.querySelector('[data-comparison-field]');
  const filterList = form.querySelector('[data-formula-filters]');
  const reduceMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

  function optionMarkup(items, selected, includeEmpty) {
    let html = includeEmpty ? '<option value="">انتخاب کنید</option>' : '';
    Object.entries(items || {}).forEach(([key, label]) => {
      const isSelected = key === selected ? ' selected' : '';
      html += `<option value="${escapeHtml(key)}"${isSelected}>${escapeHtml(label)}</option>`;
    });
    return html;
  }

  function escapeHtml(value) {
    return String(value)
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');
  }

  function operatorMarkup(selected = '=') {
    const operators = {
      '=': 'برابر',
      '!=': 'نابرابر',
      '>': 'بزرگ‌تر',
      '>=': 'بزرگ‌تر یا برابر',
      '<': 'کوچک‌تر',
      '<=': 'کوچک‌تر یا برابر',
      BETWEEN: 'بین',
      IN: 'در فهرست',
      NOT_IN: 'خارج از فهرست'
    };
    return optionMarkup(operators, selected, false);
  }

  function refreshSourceControls(preserve = true) {
    const source = sources[sourceSelect.value] || {metrics: {}, filters: {}};
    const currentMetric = preserve ? (metricSelect.value || metricSelect.dataset.selected) : '';
    const currentComparison = preserve ? (comparisonSelect.value || comparisonSelect.dataset.selected) : '';
    metricSelect.innerHTML = optionMarkup(source.metrics, currentMetric, false);
    comparisonSelect.innerHTML = optionMarkup(source.metrics, currentComparison, true);
    filterList.querySelectorAll('[data-filter-field]').forEach(select => {
      select.innerHTML = optionMarkup(source.filters, preserve ? (select.value || select.dataset.selected) : '', false);
    });
    refreshComparisonVisibility();
  }

  function refreshComparisonVisibility() {
    const visible = aggregationSelect.value === 'PERCENT' || aggregationSelect.value === 'RATIO';
    comparisonField.hidden = !visible;
    comparisonSelect.required = visible;
  }

  function addFilter() {
    const source = sources[sourceSelect.value] || {filters: {}};
    if (!Object.keys(source.filters || {}).length) return;
    const row = document.createElement('div');
    row.className = 'formula-filter-row';
    row.innerHTML = `
      <label><span>فیلد</span><select name="filter_field[]" data-filter-field>${optionMarkup(source.filters, '', false)}</select></label>
      <label><span>عملگر</span><select name="filter_operator[]">${operatorMarkup()}</select></label>
      <label><span>مقدار</span><input name="filter_value[]" placeholder="برای فهرست، با ویرگول جدا کنید"></label>
      <button class="formula-remove" type="button" data-remove-formula-filter aria-label="حذف فیلتر">×</button>`;
    filterList.append(row);
    if (!reduceMotion && window.Motion?.animate) {
      window.Motion.animate(
        row,
        {opacity: [0, 1], transform: ['translateY(-6px)', 'translateY(0)']},
        {duration: .18, ease: 'easeOut'}
      );
    }
  }

  sourceSelect.addEventListener('change', () => refreshSourceControls(false));
  aggregationSelect.addEventListener('change', refreshComparisonVisibility);
  form.querySelector('[data-add-formula-filter]')?.addEventListener('click', addFilter);
  filterList.addEventListener('click', event => {
    const button = event.target.closest('[data-remove-formula-filter]');
    if (!button) return;
    const row = button.closest('.formula-filter-row');
    if (!row) return;
    if (!reduceMotion && window.Motion?.animate) {
      const animation = window.Motion.animate(
        row,
        {opacity: [1, 0], transform: ['translateY(0)', 'translateY(-5px)']},
        {duration: .14, ease: 'easeIn'}
      );
      if (animation && typeof animation.then === 'function') {
        animation.then(() => row.remove());
      } else if (animation?.finished && typeof animation.finished.then === 'function') {
        animation.finished.then(() => row.remove());
      } else {
        window.setTimeout(() => row.remove(), 180);
      }
    } else {
      row.remove();
    }
  });

  refreshSourceControls(true);
})();
