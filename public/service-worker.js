// Basic service worker for offline caching
self.addEventListener('install', event => {
  self.skipWaiting();
});

self.addEventListener('activate', event => {
  self.clients.claim();
});

self.addEventListener('fetch', event => {
  // Never intercept navigation requests (full page loads / refreshes).
  // Inertia.js pages are server-rendered by Laravel; caching them causes
  // stale CSRF tokens and breaks PHP-FPM passthrough on refresh.
  if (event.request.mode === 'navigate') return;

  event.respondWith(
    caches.open('woosoo-nexus-v1').then(cache => {
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
