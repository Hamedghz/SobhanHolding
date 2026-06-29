self.addEventListener('push', event => {
  event.waitUntil((async () => {
    let notification = {
      title: 'پنل سبحان',
      body: 'یک اعلان جدید دارید.',
      action_url: '/admin/notification-settings.php',
      id: null
    };

    try {
      const response = await fetch('/api/notifications.php?limit=1', {credentials: 'include', cache: 'no-store'});
      const data = await response.json();
      if (data && data.ok && data.items && data.items[0]) {
        notification = data.items[0];
      }
    } catch (error) {
      // A logged-out or expired browser session still gets a safe generic notification.
    }

    const title = notification.title || 'پنل سبحان';
    const body = notification.body ? 'برای مشاهده جزئیات وارد پنل شوید.' : 'یک اعلان جدید دارید.';
    await self.registration.showNotification(title, {
      body,
      dir: 'rtl',
      lang: 'fa',
      tag: notification.id ? 'sobhan-' + notification.id : 'sobhan-notification',
      renotify: true,
      data: {
        id: notification.id || null,
        action_url: notification.action_url || '/admin/notification-settings.php'
      }
    });
  })());
});

self.addEventListener('notificationclick', event => {
  event.notification.close();
  const target = new URL(event.notification.data?.action_url || '/admin/notification-settings.php', self.location.origin);
  const targetUrl = target.origin === self.location.origin ? target.href : (self.location.origin + '/admin/notification-settings.php');

  event.waitUntil((async () => {
    const windows = await clients.matchAll({type: 'window', includeUncontrolled: true});
    for (const client of windows) {
      if ('focus' in client && client.url.startsWith(self.location.origin)) {
        await client.focus();
        if ('navigate' in client) return client.navigate(targetUrl);
        return;
      }
    }
    return clients.openWindow(targetUrl);
  })());
});
