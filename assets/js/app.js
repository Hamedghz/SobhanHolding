const adminSidebar = document.getElementById('adminSidebar');
const adminSidebarOverlay = document.querySelector('[data-sidebar-overlay]');
const adminSidebarToggle = document.querySelector('[data-sidebar-toggle]');

function setAdminSidebar(open) {
  if (!adminSidebar) return;
  adminSidebar.classList.toggle('open', open);
  adminSidebar.setAttribute('aria-hidden', open ? 'false' : 'true');
  document.body.classList.toggle('admin-sidebar-open', open);
  if (adminSidebarToggle) adminSidebarToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
  if (adminSidebarOverlay) adminSidebarOverlay.hidden = !open;
}

document.addEventListener('click', function (event) {
  const toggle = event.target.closest('[data-sidebar-toggle]');
  if (toggle) {
    event.preventDefault();
    setAdminSidebar(!adminSidebar?.classList.contains('open'));
    return;
  }
  if (event.target.closest('[data-sidebar-overlay]')) {
    setAdminSidebar(false);
    return;
  }
  if (event.target.closest('.admin-sidebar a')) setAdminSidebar(false);
});

document.addEventListener('keydown', function (event) {
  if (event.key === 'Escape') setAdminSidebar(false);
});

window.addEventListener('pageshow', function () {
  setAdminSidebar(false);
});

const persianDigits = {'۰':'0','۱':'1','۲':'2','۳':'3','۴':'4','۵':'5','۶':'6','۷':'7','۸':'8','۹':'9','٠':'0','١':'1','٢':'2','٣':'3','٤':'4','٥':'5','٦':'6','٧':'7','٨':'8','٩':'9'};
document.querySelectorAll('.jalali-date-input').forEach(input => {
  input.setAttribute('dir', 'ltr');
  input.addEventListener('input', () => {
    let value = input.value.replace(/[۰-۹٠-٩]/g, char => persianDigits[char] || char).replace(/[^\d/.-]/g, '').replace(/[.-]/g, '/');
    const digits = value.replace(/\D/g, '').slice(0, 8);
    if (digits.length > 4 && digits.length <= 6) value = digits.slice(0, 4) + '/' + digits.slice(4);
    else if (digits.length > 6) value = digits.slice(0, 4) + '/' + digits.slice(4, 6) + '/' + digits.slice(6);
    else value = digits;
    input.value = value;
  });
});
