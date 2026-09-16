# Direct Dispatch & Fleet Controller — PWA (production build)

## Stack (unchanged)
- Frontend: plain HTML + vanilla JavaScript + Tailwind CDN (JIT, runtime)
- Backend: PHP + PDO, MySQL — untouched
- No React/Next.js/Node/build step was introduced. All PWA functionality
  below is added as static files + a service worker, loaded directly by
  the browser, exactly like the existing `assets/frontend_application_controller.js`.

## What was added (nothing existing was removed or rewritten)
| File | Purpose |
|---|---|
| `manifest.json` | App identity, 11 icon sizes (incl. maskable), shortcuts, display mode |
| `service-worker.js` | Workbox-based caching (loaded via CDN `importScripts`, no build step) |
| `assets/pwa-controller.js` | SW registration, auto-update banner, online/offline banner — included on all 3 pages |
| `offline.html` | Fallback page shown when fully offline and nothing cached |
| `icons/` | 9 regular icon sizes (72–512px) + 2 maskable icons |
| `splash/` | 9 iOS splash screens for common iPhone/iPad viewports |
| `.htaccess` | HTTPS redirect, correct manifest MIME type, cache-control (critical: `service-worker.js` is never browser-cached), gzip |
| Head tags in `index.html` / `track_driver.html` / `import_suppliers.html` | manifest link, theme-color, Apple/Windows PWA meta tags, icon links, splash-screen links |

## Caching strategy (in `service-worker.js`)
- **`/api/*.php` → NetworkOnly, never cached.** Live dispatch/task/location
  data must always come from the network.
- **HTML pages → NetworkFirst** (8s timeout), falls back to cache, then to
  `offline.html`. Always shows the latest page when online.
- **Tailwind CDN script → CacheFirst.** So the app still renders its styling
  when opened offline (it's cached after first load).
- **Local JS → StaleWhileRevalidate.** Fast load, refreshes in background.
- **Icons/images → CacheFirst**, capped and expired automatically.

## Auto-updates
New deploys take effect automatically (`skipWaiting` + `clients.claim()` in
the service worker) — no manual "update" action is required from the user.
For anyone with the app already open in a tab, a small "App updated —
Refresh" banner appears once the new version has taken over, rather than
force-reloading mid-task (this is a live ops app; an unannounced reload
could interrupt an in-progress dispatch or delivery update).

## Online/offline detection
`assets/pwa-controller.js` shows a small banner at the bottom of the screen
when the connection drops ("You are offline — showing cached data...") and
briefly on reconnect ("Back online"). This is independent of Tailwind, so it
still displays even if the CDN script hasn't loaded.

## Installability
With HTTPS + the manifest + the service worker in place, Chrome/Edge/Android
will show an automatic "Install app" prompt, and iOS Safari supports
"Add to Home Screen" from the Share menu (the splash screens and
`apple-mobile-web-app-*` meta tags make that install look native on iOS,
since iOS doesn't yet auto-apply `manifest.json`'s icons/splash the way
Android does).

## Deployment (production)
1. Upload the **entire contents of this folder** to your Hostinger
   `public_html/delivery/` folder (same as before), overwriting the existing
   files. `database_configuration.php` and everything in `api/` are
   unchanged — no database or backend redeployment needed.
2. Confirm the site is served over **HTTPS** (already true on
   canaresonline.com) — service workers refuse to register on plain HTTP.
3. Visit `https://canaresonline.com/delivery/index.html`, open DevTools →
   Application tab, and confirm:
   - **Manifest** section shows the app name/icons with no errors
   - **Service Workers** section shows it as "activated and running"
4. Run a Lighthouse audit (DevTools → Lighthouse → PWA category) to confirm
   installability and get a performance score.
5. On future deploys: bump `CACHE_VERSION` at the top of `service-worker.js`
   (e.g. `v1` → `v2`) before re-uploading, so returning users' browsers pick
   up the new cache instead of reusing stale precached files.

## Known limitation
Tailwind is loaded via its CDN JIT script rather than a compiled stylesheet
(this was true before this change too). It's cached after first load for
offline use, but a brand-new install with zero connectivity on its very
first visit won't have it cached yet. This is inherent to using the Tailwind
CDN approach and wasn't introduced by the PWA conversion; switching to a
compiled Tailwind build would need a Node build step, which this task
intentionally avoided to keep the existing stack unchanged.
