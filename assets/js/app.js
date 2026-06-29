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

document.querySelectorAll('[data-dashboard-refresh]').forEach(form => {
  form.addEventListener('submit', async event => {
    event.preventDefault();
    const button = form.querySelector('button');
    if (button) button.disabled = true;
    try {
      const response = await fetch('/admin/actions/dashboard-refresh.php', {method: 'POST', body: new FormData(form), credentials: 'same-origin'});
      const data = await response.json();
      alert(data.ok ? (data.job?.message || 'بروزرسانی ثبت شد.') : (data.message || 'بروزرسانی داشبورد ناموفق بود.'));
      if (data.ok && data.job?.status === 'completed') location.reload();
    } catch (error) {
      alert('اتصال به سرویس بروزرسانی برقرار نشد.');
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
    alert('کلید اعلان مرورگر هنوز آماده نیست.');
    return;
  }
  if (!sobhanSecurePushContext()) {
    alert('اعلان مرورگر در محیط عملیاتی فقط با HTTPS فعال می‌شود.');
    return;
  }
  if (!('Notification' in window) || !('PushManager' in window)) {
    alert('مرورگر این دستگاه از Push Notification پشتیبانی نمی‌کند.');
    return;
  }

  button.disabled = true;
  try {
    const permission = await Notification.requestPermission();
    if (permission !== 'granted') {
      alert('مجوز اعلان برای این مرورگر صادر نشد.');
      return;
    }

    const registration = await sobhanRegisterServiceWorker();
    if (!registration) {
      alert('Service Worker فعال نشد.');
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
    alert(data.message || (data.ok ? 'اعلان فعال شد.' : 'فعال‌سازی اعلان انجام نشد.'));
  } catch (error) {
    alert('فعال‌سازی اعلان روی این دستگاه انجام نشد.');
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
