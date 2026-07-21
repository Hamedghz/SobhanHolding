(function () {
  'use strict';

  const digitMap = {
    '۰': '0', '۱': '1', '۲': '2', '۳': '3', '۴': '4',
    '۵': '5', '۶': '6', '۷': '7', '۸': '8', '۹': '9',
    '٠': '0', '١': '1', '٢': '2', '٣': '3', '٤': '4',
    '٥': '5', '٦': '6', '٧': '7', '٨': '8', '٩': '9'
  };

  function normalizeDigits(value) {
    return String(value || '').replace(/[۰-۹٠-٩]/g, char => digitMap[char] || char);
  }

  function normalizeDateInput(input) {
    if (input.dataset.jalaliMode === 'range' || input.dataset.jdpMode === 'range') {
      input.value = normalizeDigits(input.value)
        .replace(/[^\d/.,\-: ]/g, '')
        .replace(/[.]/g, '/')
        .replace(/\s+/g, ' ')
        .trimStart();
      return;
    }
    const dateTime = input.hasAttribute('data-jalali-datetime');
    const monthOnly = input.hasAttribute('data-jalali-month');
    let value = normalizeDigits(input.value)
      .replace(/[^\d/.\-: ]/g, '')
      .replace(/[.\-]/g, '/')
      .replace(/\s+/g, ' ')
      .trimStart();
    const parts = value.split(' ', 2);
    const digits = (parts[0] || '').replace(/\D/g, '').slice(0, 8);
    let date = digits;
    if (digits.length > 4 && digits.length <= 6) date = digits.slice(0, 4) + '/' + digits.slice(4);
    if (digits.length > 6) date = digits.slice(0, 4) + '/' + digits.slice(4, 6) + '/' + digits.slice(6);
    if (monthOnly && date.length > 7) date = date.slice(0, 7);
    if (dateTime && parts[1]) {
      const time = parts[1].replace(/[^\d:]/g, '').slice(0, 8);
      value = date + (time ? ' ' + time : '');
    } else {
      value = date;
    }
    input.value = value;
  }

  function parseDisabledDates(input) {
    const raw = normalizeDigits(input.getAttribute('data-jalali-disabled-dates') || '');
    if (!raw) return new Set();
    let values = [];
    try {
      const decoded = JSON.parse(raw);
      values = Array.isArray(decoded) ? decoded : [];
    } catch (error) {
      values = raw.split(',');
    }
    return new Set(values.map(value => String(value).trim().replace(/[.\-]/g, '/')).filter(Boolean));
  }

  function configureInput(input) {
    if (input.dataset.appDateReady === '1') return;
    input.dataset.appDateReady = '1';
    input.setAttribute('data-jdp', '');
    input.setAttribute('dir', 'ltr');
    input.setAttribute('inputmode', input.hasAttribute('data-jalali-datetime') ? 'text' : 'numeric');
    input.setAttribute('autocomplete', 'off');
    input.classList.add('app-jalali-date-input');
    if (!input.hasAttribute('data-jalali-datetime')) input.setAttribute('data-jdp-only-date', '');
    if (!input.hasAttribute('data-jdp-has-second')) input.setAttribute('data-jdp-has-second', 'false');
    if (input.dataset.jalaliMode && !input.dataset.jdpMode) input.dataset.jdpMode = input.dataset.jalaliMode;
    if (input.dataset.jalaliMin && !input.dataset.jdpMinDate) input.dataset.jdpMinDate = input.dataset.jalaliMin;
    if (input.dataset.jalaliMax && !input.dataset.jdpMaxDate) input.dataset.jdpMaxDate = input.dataset.jalaliMax;
    if (input.dataset.isoTarget && !input.dataset.jdpTargetValueInput) {
      input.dataset.jdpTargetValueInput = input.dataset.isoTarget;
      input.dataset.jdpTargetValueType = 'gregorian';
    }
    input.addEventListener('input', () => normalizeDateInput(input));
    input.addEventListener('blur', () => input.classList.toggle('is-invalid', input.required && input.value.trim() === ''));
    input.addEventListener('jdp:change', () => {
      if (input.hasAttribute('data-jalali-month')) input.value = normalizeDigits(input.value).slice(0, 7);
      input.dispatchEvent(new CustomEvent('appdate:change', {
        bubbles: true,
        detail: {
          jalali: input.value,
          iso: input.dataset.isoTarget ? document.querySelector(input.dataset.isoTarget)?.value || '' : ''
        }
      }));
    });
  }

  function initPeriodSelectors() {
    document.querySelectorAll('[data-period-selector]').forEach(select => {
      if (select.dataset.appPeriodReady === '1') return;
      select.dataset.appPeriodReady = '1';
      const targetSelector = select.getAttribute('data-custom-period-target');
      const target = targetSelector ? document.querySelector(targetSelector) : null;
      const sync = () => {
        const custom = select.value === 'custom';
        if (target) {
          target.hidden = !custom;
          target.querySelectorAll('input,select,textarea').forEach(field => {
            field.disabled = !custom;
          });
        }
        select.closest('form')?.classList.toggle('has-custom-period', custom);
      };
      select.addEventListener('change', sync);
      sync();
    });
  }

  function init() {
    const inputs = document.querySelectorAll('input[data-jalali-date], input.jalali-date-input');
    inputs.forEach(configureInput);

    if (window.jalaliDatepicker && inputs.length) {
      window.jalaliDatepicker.startWatch({
        selector: 'input[data-jalali-date], input.jalali-date-input',
        date: true,
        time: true,
        hasSecond: 'attr',
        mode: 'attr',
        minDate: 'attr',
        maxDate: 'attr',
        targetValueInput: 'attr',
        targetValueType: 'attr',
        position: 'right',
        autoReadOnlyInput: false,
        hideAfterChange: true,
        hideAfterChangeWithTime: false,
        showTodayBtn: true,
        showEmptyBtn: true,
        showCloseBtn: true,
        zIndex: 10050,
        dayRendering(day, input) {
          const disabled = parseDisabledDates(input);
          const key = [
            String(day.year).padStart(4, '0'),
            String(day.month).padStart(2, '0'),
            String(day.day).padStart(2, '0')
          ].join('/');
          return disabled.has(key) ? {...day, isValid: false, className: 'app-date-disabled'} : day;
        }
      });
    }
    initPeriodSelectors();
  }

  function watchDynamicFields() {
    if (!document.body || !window.MutationObserver) return;
    const observer = new MutationObserver(records => {
      records.forEach(record => record.addedNodes.forEach(node => {
        if (!(node instanceof Element)) return;
        if (node.matches('input[data-jalali-date], input.jalali-date-input')) configureInput(node);
        node.querySelectorAll?.('input[data-jalali-date], input.jalali-date-input').forEach(configureInput);
        if (node.matches('[data-period-selector]') || node.querySelector?.('[data-period-selector]')) initPeriodSelectors();
      }));
    });
    observer.observe(document.body, {childList: true, subtree: true});
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init, {once: true});
  } else {
    init();
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', watchDynamicFields, {once: true});
  } else {
    watchDynamicFields();
  }

  window.SobhanAppDate = {
    init,
    normalizeDigits,
    show(input) {
      if (window.jalaliDatepicker && input instanceof HTMLInputElement) window.jalaliDatepicker.show(input);
    },
    hide() {
      window.jalaliDatepicker?.hide();
    }
  };
})();
