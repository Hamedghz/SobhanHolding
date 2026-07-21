(function () {
  'use strict';

  const reduceMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
  const motion = !reduceMotion && window.Motion?.animate ? window.Motion : null;

  function setFieldState(element, visible) {
    if (!element) return;
    element.hidden = !visible;
    element.querySelectorAll?.('input,select,textarea').forEach(field => {
      field.disabled = !visible;
    });
    if (element.matches?.('input,select,textarea')) element.disabled = !visible;
  }

  function syncRow(row, animateChange) {
    const status = row.querySelector('[data-attendance-status]')?.value || 'present';
    const timeFields = row.querySelector('[data-attendance-time-fields]');
    const leaveField = row.querySelector('[data-attendance-leave-field]');
    const missionField = row.querySelector('[data-attendance-mission-field]');
    const hasTimes = status === 'present' || status === 'holiday_work';

    setFieldState(timeFields, hasTimes);
    setFieldState(leaveField, status === 'leave');
    setFieldState(missionField, status === 'mission');
    if (leaveField) leaveField.required = status === 'leave';
    if (missionField) missionField.required = status === 'mission';
    timeFields?.querySelectorAll('input[type="time"]').forEach(field => {
      field.required = hasTimes;
    });
    row.dataset.attendanceState = status;

    if (animateChange && motion) {
      const target = status === 'leave' ? leaveField : status === 'mission' ? missionField : timeFields;
      if (target && !target.hidden) {
        motion.animate(
          target,
          {opacity: [0, 1], transform: ['translateY(-4px)', 'translateY(0)']},
          {duration: .18, ease: 'easeOut'}
        );
      }
    }
  }

  function validateTimePair(row) {
    const status = row.querySelector('[data-attendance-status]')?.value || '';
    if (status !== 'present' && status !== 'holiday_work') return;
    const fields = Array.from(row.querySelectorAll('[data-attendance-time-fields] input[type="time"]'));
    if (fields.length !== 2) return;
    const partial = Boolean(fields[0].value) !== Boolean(fields[1].value);
    fields.forEach(field => field.classList.toggle('is-invalid', partial));
  }

  document.querySelectorAll('[data-attendance-row]').forEach(row => {
    syncRow(row, false);
    const status = row.querySelector('[data-attendance-status]');
    const checkbox = row.querySelector('input[type="checkbox"][name*="[selected]"]');
    status?.addEventListener('change', () => {
      syncRow(row, true);
      validateTimePair(row);
    });
    row.querySelectorAll('[data-attendance-time-fields] input').forEach(field => {
      field.addEventListener('blur', () => validateTimePair(row));
    });
    checkbox?.addEventListener('change', () => {
      row.classList.toggle('is-selected', checkbox.checked);
      if (checkbox.checked && motion) {
        motion.animate(row, {backgroundColor: ['rgba(20,125,124,0)', 'rgba(20,125,124,.06)']}, {duration: .2, ease: 'easeOut'});
      }
    });
  });

  const revealItems = Array.from(document.querySelectorAll('[data-attendance-reveal]'));
  if (motion && revealItems.length) {
    revealItems.slice(0, 12).forEach((element, index) => {
      motion.animate(
        element,
        {opacity: [0, 1], transform: ['translateY(7px)', 'translateY(0)']},
        {duration: .24, delay: index * .035, ease: 'easeOut'}
      );
    });
  }
})();
