(() => {
  const form = document.querySelector('[data-import-form]');
  if (!form) return;
  const source = form.querySelector('[data-source-select]');
  const templateLink = document.querySelector('[data-import-template-link]');
  const snapshot = form.querySelector('[data-snapshot-field]');
  const period = form.querySelector('[data-period-field]');
  const refresh = () => {
    const value = source?.value || '';
    if (templateLink) {
      templateLink.href = `/admin/import-template.php?source=${encodeURIComponent(value || 'all')}`;
      templateLink.textContent = value ? `دانلود قالب ${source.options[source.selectedIndex]?.text || ''}` : 'دانلود قالب همه منابع مجاز';
    }
    const snapshotRequired = ['inventory_aggregate', 'attendance'].includes(value);
    const periodRequired = ['inventory_aggregate', 'sales_targets', 'product_priorities', 'customer_coefficients'].includes(value);
    snapshot?.classList.toggle('is-required', snapshotRequired);
    period?.classList.toggle('is-required', periodRequired);
    const snapshotInput = snapshot?.querySelector('input');
    const periodInput = period?.querySelector('input');
    if (snapshotInput) snapshotInput.required = snapshotRequired;
    if (periodInput) periodInput.required = periodRequired;
  };
  source?.addEventListener('change', () => {
    refresh();
    const motion = window.SobhanMotion;
    if (motion?.animate && snapshot) motion.animate(snapshot, { opacity: [0.45, 1], y: [4, 0] }, { duration: 0.22 });
  });
  refresh();
  const dropzone = form.querySelector('.import-dropzone');
  const file = dropzone?.querySelector('input[type="file"]');
  file?.addEventListener('change', () => {
    const name = file.files?.[0]?.name;
    if (!name) return;
    dropzone.querySelector('strong').textContent = name;
    window.SobhanMotion?.animate?.(dropzone, { scale: [0.99, 1], borderColor: ['#2dd4bf', '#67b8ae'] }, { duration: 0.25 });
  });
})();
