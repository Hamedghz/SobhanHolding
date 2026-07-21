const adminSidebar = document.getElementById('adminSidebar');
const adminSidebarOverlay = document.querySelector('[data-sidebar-overlay]');
const adminSidebarToggle = document.querySelector('[data-sidebar-toggle]');

function setAdminSidebar(open) {
  if (!adminSidebar) return;
  const mobile = window.matchMedia('(max-width: 900px)').matches;
  open = mobile && open;
  adminSidebar.classList.toggle('open', open);
  adminSidebar.setAttribute('aria-hidden', mobile && !open ? 'true' : 'false');
  document.body.classList.toggle('admin-sidebar-open', open);
  if (adminSidebarToggle) adminSidebarToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
  if (adminSidebarOverlay) adminSidebarOverlay.hidden = !mobile || !open;
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

window.addEventListener('resize', function () {
  if (!window.matchMedia('(max-width: 900px)').matches) setAdminSidebar(false);
});

const adminMenuStorageKey = 'sobhan-admin-menu-open-sections';
const adminMenuSections = [...document.querySelectorAll('[data-menu-section]')];
let storedAdminMenuSections = {};
try {
  storedAdminMenuSections = JSON.parse(localStorage.getItem(adminMenuStorageKey) || '{}') || {};
} catch (error) {
  storedAdminMenuSections = {};
}

adminMenuSections.forEach(section => {
  const sectionKey = section.dataset.menuSection || '';
  const hasActiveChild = section.classList.contains('has-active-child');
  const sectionToggle = section.querySelector('.admin-menu-toggle');
  const syncAdminMenuSection = () => {
    section.classList.toggle('is-open', section.open);
    sectionToggle?.classList.toggle('is-open', section.open);
    sectionToggle?.setAttribute('aria-expanded', section.open ? 'true' : 'false');
  };
  if (hasActiveChild || storedAdminMenuSections[sectionKey] === true) section.open = true;
  syncAdminMenuSection();

  section.addEventListener('toggle', () => {
    if (hasActiveChild && !section.open) {
      section.open = true;
      return;
    }
    syncAdminMenuSection();
    storedAdminMenuSections[sectionKey] = section.open;
    try {
      localStorage.setItem(adminMenuStorageKey, JSON.stringify(storedAdminMenuSections));
    } catch (error) {
      // Storage can be unavailable in private or restricted browser contexts.
    }
  });
});

const activeAdminMenuLink = document.querySelector('.admin-menu-link.is-active');
if (activeAdminMenuLink) {
  requestAnimationFrame(() => activeAdminMenuLink.scrollIntoView({block: 'nearest'}));
}

const adminMenuSearch = document.querySelector('[data-menu-search]');
if (adminMenuSearch) {
  const input = adminMenuSearch.querySelector('[data-menu-search-input]');
  const results = adminMenuSearch.querySelector('[data-menu-search-results]');
  const items = [...adminMenuSearch.querySelectorAll('[data-menu-search-result]')];
  const empty = adminMenuSearch.querySelector('[data-menu-search-empty]');
  let focusedIndex = -1;
  const normalize = value => String(value || '').normalize('NFKD').replace(/[\u064B-\u065F\u0670]/g, '').replace(/[يى]/g, 'ی').replace(/ك/g, 'ک').replace(/[ةۀ]/g, 'ه').replace(/[\u200c\u200d]/g, ' ').replace(/\s+/g, ' ').trim().toLowerCase();
  const visibleItems = () => items.filter(item => !item.hidden);
  const focusItem = index => {
    const visible = visibleItems();
    items.forEach(item => item.classList.remove('is-focused'));
    if (!visible.length) { focusedIndex = -1; return; }
    focusedIndex = (index + visible.length) % visible.length;
    visible[focusedIndex].classList.add('is-focused');
    visible[focusedIndex].scrollIntoView({block: 'nearest'});
  };
  const search = () => {
    const term = normalize(input.value);
    let count = 0;
    items.forEach(item => { item.hidden = term === '' || !normalize(item.dataset.searchText).includes(term); if (!item.hidden) count++; });
    results.hidden = term === '';
    input.setAttribute('aria-expanded', results.hidden ? 'false' : 'true');
    empty.hidden = count > 0 || term === '';
    focusedIndex = -1;
  };
  input.addEventListener('input', search);
  input.addEventListener('keydown', event => {
    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') { event.preventDefault(); focusItem(focusedIndex + (event.key === 'ArrowDown' ? 1 : -1)); }
    if (event.key === 'Enter' && focusedIndex >= 0) { event.preventDefault(); visibleItems()[focusedIndex]?.click(); }
    if (event.key === 'Escape') { input.value = ''; search(); input.blur(); }
  });
  document.addEventListener('keydown', event => { if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') { event.preventDefault(); input.focus(); input.select(); } });
  document.addEventListener('click', event => { if (!event.target.closest('[data-menu-search]')) { results.hidden = true; input.setAttribute('aria-expanded', 'false'); } });
}

const sobhanToastKeys = new Map();
function sobhanNotify(message, type = 'info') {
  const region = document.querySelector('[data-toast-region]');
  if (!region || !message) return;
  const normalizedType = ['success', 'warning', 'danger', 'info'].includes(type) ? type : 'info';
  const key = `${normalizedType}:${message}`;
  if (sobhanToastKeys.has(key)) return;
  const notice = document.createElement('div');
  notice.className = `alert alert-${normalizedType}`;
  notice.setAttribute('role', normalizedType === 'danger' ? 'alert' : 'status');
  const label = document.createElement('span'); label.textContent = message;
  const close = document.createElement('button'); close.type = 'button'; close.setAttribute('aria-label', 'بستن پیام'); close.textContent = '×';
  const dismiss = () => { sobhanToastKeys.delete(key); notice.remove(); };
  close.addEventListener('click', dismiss); notice.append(label, close); region.appendChild(notice); sobhanToastKeys.set(key, notice); window.setTimeout(dismiss, normalizedType === 'danger' ? 9000 : 5500);
}
window.SobhanUI = {...(window.SobhanUI || {}), notify: sobhanNotify};
document.querySelectorAll('[data-app-notice]').forEach(notice => notice.querySelector('[data-notice-close]')?.addEventListener('click', () => notice.remove()));

document.querySelectorAll('[data-dashboard-refresh]').forEach(form => {
  form.addEventListener('submit', async event => {
    event.preventDefault();
    const button = form.querySelector('button');
    if (button) button.disabled = true;
    try {
      const response = await fetch('/admin/actions/dashboard-refresh.php', {method: 'POST', body: new FormData(form), credentials: 'same-origin'});
      const data = await response.json();
      sobhanNotify(data.ok ? (data.job?.message || 'بروزرسانی ثبت شد.') : (data.message || 'بروزرسانی داشبورد ناموفق بود.'), data.ok ? 'success' : 'danger');
      if (data.ok && data.job?.status === 'completed') location.reload();
    } catch (error) {
      sobhanNotify('اتصال به سرویس بروزرسانی برقرار نشد.', 'danger');
    } finally {
      if (button) button.disabled = false;
    }
  });
});

const sobhanNotificationConfig = window.SobhanNotifications || null;
const sobhanNotificationCenter = document.querySelector('[data-notification-center]');

function sobhanSecurePushContext() {
  return location.protocol === 'https:' || ['localhost', '127.0.0.1'].includes(location.hostname);
}

function sobhanBase64ToUint8Array(value) {
  const padding = '='.repeat((4 - value.length % 4) % 4);
  const base64 = (value + padding).replace(/-/g, '+').replace(/_/g, '/');
  const raw = atob(base64);
  const output = new Uint8Array(raw.length);
  for (let i = 0; i < raw.length; i++) output[i] = raw.charCodeAt(i);
  return output;
}

async function sobhanRegisterServiceWorker() {
  if (!sobhanNotificationConfig?.loggedIn || !('serviceWorker' in navigator) || !sobhanSecurePushContext()) return null;
  try {
    return await navigator.serviceWorker.register(sobhanNotificationConfig.serviceWorker || '/service-worker.js');
  } catch (error) {
    console.warn('Service worker registration failed:', error);
    return null;
  }
}

function sobhanNotificationHeaders() {
  return {
    'Content-Type': 'application/json',
    'X-CSRF-Token': sobhanNotificationConfig?.csrfToken || ''
  };
}

function sobhanUpdateNotificationCount(count) {
  const badge = document.querySelector('[data-notification-count]');
  if (!badge) return;
  const value = Number(count || 0);
  badge.textContent = value > 99 ? '99+' : String(value);
  badge.hidden = value <= 0;
}

function sobhanRenderNotifications(items) {
  const list = document.querySelector('[data-notification-list]');
  if (!list) return;
  list.innerHTML = '';
  if (!items.length) {
    const empty = document.createElement('p');
    empty.className = 'muted';
    empty.textContent = 'اعلان جدیدی ندارید.';
    list.appendChild(empty);
    return;
  }

  items.forEach(item => {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'notification-item' + (item.status === 'unread' ? ' is-unread' : '');
    button.dataset.notificationId = item.id;
    button.dataset.actionUrl = item.action_url || '/admin/notification-settings.php';

    const title = document.createElement('strong');
    title.textContent = item.title || 'اعلان';
    const meta = document.createElement('small');
    meta.textContent = item.created_at_fa || '';
    const body = document.createElement('span');
    body.textContent = item.body || '';

    button.append(title, meta, body);
    list.appendChild(button);
  });
}

async function sobhanLoadNotifications() {
  if (!sobhanNotificationCenter) return;
  try {
    const response = await fetch('/api/notifications.php?limit=8', {credentials: 'same-origin', cache: 'no-store'});
    const data = await response.json();
    if (!data.ok) return;
    sobhanUpdateNotificationCount(data.unread_count);
    sobhanRenderNotifications(data.items || []);
  } catch (error) {
    const list = document.querySelector('[data-notification-list]');
    if (list) list.innerHTML = '<p class="muted">دریافت اعلان‌ها ممکن نشد.</p>';
  }
}

async function sobhanMarkNotificationRead(payload) {
  const response = await fetch('/api/notifications-read.php', {
    method: 'POST',
    credentials: 'same-origin',
    headers: sobhanNotificationHeaders(),
    body: JSON.stringify(payload)
  });
  return response.json();
}

async function sobhanEnablePushOnDevice(button) {
  if (!sobhanNotificationConfig?.vapidPublicKey) {
    sobhanNotify('کلید اعلان مرورگر هنوز آماده نیست.', 'warning');
    return;
  }
  if (!sobhanSecurePushContext()) {
    sobhanNotify('اعلان مرورگر در محیط عملیاتی فقط با HTTPS فعال می‌شود.', 'warning');
    return;
  }
  if (!('Notification' in window) || !('PushManager' in window)) {
    sobhanNotify('مرورگر این دستگاه از Push Notification پشتیبانی نمی‌کند.', 'warning');
    return;
  }

  button.disabled = true;
  try {
    const permission = await Notification.requestPermission();
    if (permission !== 'granted') {
      sobhanNotify('مجوز اعلان برای این مرورگر صادر نشد.', 'warning');
      return;
    }

    const registration = await sobhanRegisterServiceWorker();
    if (!registration) {
      sobhanNotify('Service Worker فعال نشد.', 'danger');
      return;
    }

    const subscription = await registration.pushManager.subscribe({
      userVisibleOnly: true,
      applicationServerKey: sobhanBase64ToUint8Array(sobhanNotificationConfig.vapidPublicKey)
    });

    const contentEncoding = (PushManager.supportedContentEncodings || ['aes128gcm'])[0];
    const response = await fetch('/api/push-subscribe.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: sobhanNotificationHeaders(),
      body: JSON.stringify({...subscription.toJSON(), contentEncoding})
    });
    const data = await response.json();
    sobhanNotify(data.message || (data.ok ? 'اعلان فعال شد.' : 'فعال‌سازی اعلان انجام نشد.'), data.ok ? 'success' : 'danger');
  } catch (error) {
    sobhanNotify('فعال‌سازی اعلان روی این دستگاه انجام نشد.', 'danger');
  } finally {
    button.disabled = false;
  }
}

if (sobhanNotificationConfig?.loggedIn) {
  window.addEventListener('load', () => {
    sobhanRegisterServiceWorker();
    sobhanLoadNotifications();
  });
}

if (sobhanNotificationCenter) {
  const dropdown = sobhanNotificationCenter.querySelector('[data-notification-dropdown]');
  const toggle = sobhanNotificationCenter.querySelector('[data-notification-toggle]');

  toggle?.addEventListener('click', event => {
    event.preventDefault();
    const open = dropdown?.hidden;
    if (dropdown) dropdown.hidden = !open;
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    if (open) sobhanLoadNotifications();
  });

  document.addEventListener('click', event => {
    if (!event.target.closest('[data-notification-center]') && dropdown && !dropdown.hidden) {
      dropdown.hidden = true;
      toggle?.setAttribute('aria-expanded', 'false');
    }
  });

  sobhanNotificationCenter.addEventListener('click', async event => {
    const enableButton = event.target.closest('[data-enable-push]');
    if (enableButton) {
      event.preventDefault();
      await sobhanEnablePushOnDevice(enableButton);
      return;
    }

    const readAll = event.target.closest('[data-notification-read-all]');
    if (readAll) {
      event.preventDefault();
      await sobhanMarkNotificationRead({all: true});
      await sobhanLoadNotifications();
      return;
    }

    const item = event.target.closest('[data-notification-id]');
    if (item) {
      event.preventDefault();
      await sobhanMarkNotificationRead({id: Number(item.dataset.notificationId || 0)});
      location.href = item.dataset.actionUrl || '/admin/notification-settings.php';
    }
  });

  window.setInterval(sobhanLoadNotifications, 60000);
}

document.addEventListener('click', async event => {
  const enableButton = event.target.closest('[data-enable-push]');
  if (!enableButton || enableButton.closest('[data-notification-center]')) return;
  event.preventDefault();
  await sobhanEnablePushOnDevice(enableButton);
});
