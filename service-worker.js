const CACHE_NAME = 'sobhan-pwa-v1';
try {
  importScripts('/public/sw.js');
} catch (error) {
  // Push notifications are optional; app caching still works if this import fails.
}

const CORE_ASSETS = [
  '/',
  '/assets/css/app.css',
  '/assets/js/app.js'
];

self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => cache.addAll(CORE_ASSETS))
      .catch(() => undefined)
  );
  self.skipWaiting();
});

self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys()
      .then(keys => Promise.all(keys.filter(key => key !== CACHE_NAME).map(key => caches.delete(key))))
      .catch(() => undefined)
  );
  self.clients.claim();
});

self.addEventListener('fetch', event => {
  const request = event.request;
  if (request.method !== 'GET') return;

  const url = new URL(request.url);
  if (url.origin !== self.location.origin) return;
  if (url.pathname.startsWith('/admin/') || url.pathname === '/login.php' || url.pathname === '/logout.php') return;
  if (url.pathname.endsWith('.php') && url.pathname !== '/manifest.php') return;

  event.respondWith(
    caches.match(request).then(cached => {
      const network = fetch(request).then(response => {
        if (response && response.ok) {
          const copy = response.clone();
          caches.open(CACHE_NAME).then(cache => cache.put(request, copy)).catch(() => undefined);
        }
        return response;
      });
      return cached || network;
    }).catch(() => fetch(request))
  );
});
