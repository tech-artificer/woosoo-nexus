const CACHE_NAME = 'woosoo-nexus-v2';

// Basic service worker for offline caching
self.addEventListener('install', event => {
  self.skipWaiting();
});

self.addEventListener('activate', event => {
  self.clients.claim();
});

self.addEventListener('fetch', event => {
  if (event.request.method !== 'GET') return;

  const requestUrl = new URL(event.request.url);

  // Ignore non-http(s) and cross-origin requests.
  if (!['http:', 'https:'].includes(requestUrl.protocol)) return;
  if (requestUrl.origin !== self.location.origin) return;

  // Never intercept Vite dev resources.
  if (requestUrl.pathname.startsWith('/@vite') || requestUrl.port === '5173') return;

  // Never intercept navigation requests (full page loads / refreshes).
  // Inertia.js pages are server-rendered by Laravel; caching them causes
  // stale CSRF tokens and breaks PHP-FPM passthrough on refresh.
  if (event.request.mode === 'navigate') return;

  event.respondWith(
    caches.open(CACHE_NAME).then(cache => {
      return cache.match(event.request).then(response => {
        return response || fetch(event.request).then(networkResponse => {
          if (event.request.method === 'GET' && networkResponse.ok) {
            cache.put(event.request, networkResponse.clone());
          }
          return networkResponse;
        });
      });
    })
  );
});
