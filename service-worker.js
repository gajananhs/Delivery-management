// service-worker.js — Workbox-powered service worker for Direct Dispatch & Fleet Controller
//
// Bump CACHE_VERSION whenever you deploy changed static files. It changes the
// underlying Workbox cache names, so old entries are dropped and clients pick
// up fresh files automatically (see "activate" cleanup below).
//
// IMPORTANT: this was left at 'v1' since the very first deploy, which is why
// updates stopped reaching installed/cached copies of the app — the service
// worker file never changed byte-for-byte, so browsers never even detected
// there was a new version to install. Bump this on every future deploy
// (v2 -> v3 -> v4 ...), otherwise this exact problem comes back.
const CACHE_VERSION = 'v2';

importScripts('https://storage.googleapis.com/workbox-cdn/releases/7.1.0/workbox-sw.js');

if (workbox) {
  workbox.setConfig({ debug: false });
  workbox.core.setCacheNameDetails({
    prefix: 'dispatch-app',
    suffix: CACHE_VERSION
  });

  const { registerRoute, setDefaultHandler, setCatchHandler } = workbox.routing;
  const { NetworkFirst, StaleWhileRevalidate, CacheFirst, NetworkOnly } = workbox.strategies;
  const { ExpirationPlugin } = workbox.expiration;
  const { precacheAndRoute, cleanupOutdatedCaches } = workbox.precaching;

  // ---- App shell: pre-cached at install time -------------------------------
  precacheAndRoute([
    { url: './index.html', revision: CACHE_VERSION },
    { url: './track_driver.html', revision: CACHE_VERSION },
    { url: './import_suppliers.html', revision: CACHE_VERSION },
    { url: './offline.html', revision: CACHE_VERSION },
    { url: './manifest.json', revision: CACHE_VERSION },
    { url: './assets/frontend_application_controller.js', revision: CACHE_VERSION },
    { url: './assets/pwa-controller.js', revision: CACHE_VERSION },
    { url: './icons/icon-72.png', revision: CACHE_VERSION },
    { url: './icons/icon-96.png', revision: CACHE_VERSION },
    { url: './icons/icon-128.png', revision: CACHE_VERSION },
    { url: './icons/icon-144.png', revision: CACHE_VERSION },
    { url: './icons/icon-152.png', revision: CACHE_VERSION },
    { url: './icons/icon-180.png', revision: CACHE_VERSION },
    { url: './icons/icon-192.png', revision: CACHE_VERSION },
    { url: './icons/icon-384.png', revision: CACHE_VERSION },
    { url: './icons/icon-512.png', revision: CACHE_VERSION },
    { url: './icons/icon-maskable-192.png', revision: CACHE_VERSION },
    { url: './icons/icon-maskable-512.png', revision: CACHE_VERSION }
  ]);

  cleanupOutdatedCaches();

  // ---- CRITICAL: never cache API calls --------------------------------------
  // This app runs live dispatch/task/driver-location data via api/*.php.
  // Caching these would show stale data, so they always go straight to the
  // network and are never stored.
  registerRoute(
    ({ url }) => url.pathname.includes('/api/'),
    new NetworkOnly()
  );

  // ---- HTML navigations: NetworkFirst with offline fallback -----------------
  // Always try the network first (so users get the latest page + PHP-rendered
  // state), fall back to the cached copy, and finally to offline.html if
  // neither is available.
  registerRoute(
    ({ request }) => request.mode === 'navigate',
    new NetworkFirst({
      cacheName: `dispatch-app-pages-${CACHE_VERSION}`,
      networkTimeoutSeconds: 8,
      plugins: [new ExpirationPlugin({ maxEntries: 20 })]
    })
  );

  // ---- Tailwind CDN script: CacheFirst ---------------------------------------
  // The app styles itself via the Tailwind CDN's runtime JIT script. Caching
  // it means styling still works even when the app is opened offline.
  registerRoute(
    ({ url }) => url.origin === 'https://cdn.tailwindcss.com',
    new CacheFirst({
      cacheName: `dispatch-app-cdn-${CACHE_VERSION}`,
      plugins: [new ExpirationPlugin({ maxEntries: 5, maxAgeSeconds: 30 * 24 * 60 * 60 })]
    })
  );

  // ---- Local JS: StaleWhileRevalidate ----------------------------------------
  registerRoute(
    ({ request, url }) => request.destination === 'script' && url.origin === self.location.origin,
    new StaleWhileRevalidate({ cacheName: `dispatch-app-scripts-${CACHE_VERSION}` })
  );

  // ---- Images/icons: CacheFirst -----------------------------------------------
  registerRoute(
    ({ request }) => request.destination === 'image',
    new CacheFirst({
      cacheName: `dispatch-app-images-${CACHE_VERSION}`,
      plugins: [new ExpirationPlugin({ maxEntries: 60, maxAgeSeconds: 60 * 24 * 60 * 60 })]
    })
  );

  // ---- Fallback for failed navigations (fully offline, nothing cached) -------
  setCatchHandler(async ({ event }) => {
    if (event.request.mode === 'navigate') {
      return caches.match('./offline.html');
    }
    return Response.error();
  });

  // ---- Immediate activation on every deploy -----------------------------------
  self.addEventListener('install', () => {
    self.skipWaiting();
  });
  self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
  });

  // Let the page force an update check / activation on demand
  self.addEventListener('message', (event) => {
    if (event.data && event.data.type === 'SKIP_WAITING') {
      self.skipWaiting();
    }
  });
} else {
  console.error('Workbox failed to load — service worker running without caching.');
}
