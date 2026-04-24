// Cache-retirement worker.
//
// This file intentionally clears old caches and unregisters itself so stale
// normal-tab state does not survive a deployment. If offline support becomes a
// requirement later, replace this with a versioned caching strategy that does
// not cache navigations or dynamic API responses.

self.addEventListener('install', event => {
  self.skipWaiting();
});

self.addEventListener('activate', event => {
  event.waitUntil((async () => {
    const keys = await caches.keys();
    await Promise.all(keys.map(key => caches.delete(key)));
    await self.registration.unregister();
    await self.clients.claim();
  })());
});
