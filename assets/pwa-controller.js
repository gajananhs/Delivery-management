/**
 * pwa-controller.js
 * Shared across index.html, track_driver.html, import_suppliers.html.
 * Handles: service worker registration, update detection/banner,
 * and online/offline status detection/banner.
 * Self-contained inline styling — does not depend on Tailwind CDN being
 * loaded, so it still works if the app is opened fully offline.
 */
(function () {
  'use strict';

  function makeBanner(id) {
    var existing = document.getElementById(id);
    if (existing) return existing;
    var el = document.createElement('div');
    el.id = id;
    el.style.cssText = [
      'position:fixed', 'left:50%', 'bottom:16px', 'transform:translateX(-50%)',
      'z-index:99999', 'max-width:92vw', 'padding:10px 16px', 'border-radius:10px',
      'font:600 13px/1.4 system-ui,-apple-system,Segoe UI,Roboto,sans-serif',
      'color:#fff', 'box-shadow:0 8px 24px rgba(0,0,0,.35)',
      'display:none', 'align-items:center', 'gap:10px', 'text-align:center'
    ].join(';');
    document.body.appendChild(el);
    return el;
  }

  function showBanner(el, html, bg, autoHideMs) {
    el.innerHTML = html;
    el.style.background = bg;
    el.style.display = 'flex';
    if (autoHideMs) {
      clearTimeout(el._hideTimer);
      el._hideTimer = setTimeout(function () { el.style.display = 'none'; }, autoHideMs);
    }
  }

  // ---------------- Online / offline detection ----------------
  var netBanner = makeBanner('pwa-net-banner');

  function renderNetStatus() {
    if (navigator.onLine) {
      showBanner(netBanner, '&#9679; Back online', '#16a34a', 3000);
    } else {
      showBanner(netBanner, '&#9679; You are offline — showing cached data, changes may not save', '#b91c1c');
    }
  }

  window.addEventListener('online', renderNetStatus);
  window.addEventListener('offline', renderNetStatus);
  if (!navigator.onLine) renderNetStatus();

  // ---------------- Service worker registration + auto-update ----------------
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
      navigator.serviceWorker.register('service-worker.js').then(function (registration) {

        // Check for a newer service-worker.js periodically (every 60 min)
        setInterval(function () { registration.update(); }, 60 * 60 * 1000);

        // Also check once whenever the tab regains focus/visibility
        document.addEventListener('visibilitychange', function () {
          if (document.visibilityState === 'visible') registration.update();
        });

      }).catch(function (err) {
        console.error('Service worker registration failed:', err);
      });

      // The new service worker activates automatically (skipWaiting +
      // clients.claim in service-worker.js). When it takes control, let the
      // user know a fresh version is in effect rather than force-reloading
      // mid-task (this is an active dispatch/ops app).
      var refreshed = false;
      navigator.serviceWorker.addEventListener('controllerchange', function () {
        if (refreshed) return;
        refreshed = true;
        var updateBanner = makeBanner('pwa-update-banner');
        showBanner(
          updateBanner,
          '&#8635; App updated. <button id="pwa-refresh-btn" style="margin-left:8px;padding:4px 10px;border:0;border-radius:6px;background:#fff;color:#111;font-weight:700;cursor:pointer;">Refresh</button>',
          '#4f46e5'
        );
        document.getElementById('pwa-refresh-btn').addEventListener('click', function () {
          window.location.reload();
        });
      });
    });
  }
})();
