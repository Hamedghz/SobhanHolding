(() => {
  'use strict';
  const form = document.querySelector('[data-kpi-selector]');
  if (!form) return;
  const employee = form.querySelector('[data-kpi-employee]');
  const template = form.querySelector('[data-kpi-template]');
  const period = form.querySelector('[data-kpi-period]');
  const status = form.querySelector('[data-kpi-template-status]');
  const preview = form.querySelector('[data-kpi-template-preview]');
  let request = null;

  const text = value => String(value ?? '');
  const clearPreview = message => {
    preview.replaceChildren();
    if (message) {
      const paragraph = document.createElement('p');
      paragraph.className = 'muted';
      paragraph.textContent = message;
      preview.appendChild(paragraph);
    }
  };
  const load = async includeCriteria => {
    request?.abort();
    request = new AbortController();
    const employeeId = Number(employee.value || 0);
    template.disabled = !employeeId;
    if (!employeeId) {
      template.replaceChildren(new Option('ابتدا پرسنل را انتخاب کنید', ''));
      status.textContent = 'قالب‌ها پس از انتخاب پرسنل بارگذاری می‌شوند.';
      clearPreview('');
      return;
    }
    status.textContent = 'در حال بارگذاری قالب‌های مجاز...';
    const params = new URLSearchParams({employee_id: String(employeeId)});
    if (includeCriteria && template.value) params.set('template_id', template.value);
    if (period.value) params.set('period_id', period.value);
    try {
      const response = await fetch(`${form.dataset.templateEndpoint}?${params}`, {
        credentials: 'same-origin',
        cache: 'no-store',
        headers: {'X-CSRF-Token': form.dataset.csrfToken || ''},
        signal: request.signal
      });
      const payload = await response.json();
      if (!response.ok || !payload.success) throw new Error(payload.message || 'بارگذاری قالب انجام نشد.');
      if (!includeCriteria) {
        const selected = template.value;
        template.replaceChildren(new Option('انتخاب کنید', ''));
        (payload.data?.templates || []).forEach(item => template.appendChild(new Option(text(item.title), text(item.id))));
        if ([...template.options].some(option => option.value === selected)) template.value = selected;
        status.textContent = payload.message;
        clearPreview(payload.data?.templates?.length ? 'قالب را انتخاب کنید تا مشخصات آن نمایش داده شود.' : payload.message);
        return;
      }
      const item = payload.data?.template;
      clearPreview('');
      if (item) {
        const heading = document.createElement('strong');
        heading.textContent = text(item.title);
        const paragraph = document.createElement('p');
        paragraph.textContent = text(item.description || `${payload.data?.criteria?.length || 0} معیار فعال آماده بارگذاری است.`);
        preview.append(heading, paragraph);
      }
      status.textContent = payload.message;
    } catch (error) {
      if (error.name === 'AbortError') return;
      status.textContent = error.message || 'بارگذاری قالب انجام نشد.';
      clearPreview(status.textContent);
    }
  };
  employee.addEventListener('change', () => load(false));
  template.addEventListener('change', () => load(true));
})();
