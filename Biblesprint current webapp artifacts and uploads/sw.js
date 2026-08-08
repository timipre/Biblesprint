// Bible Sprint — Service Worker
// Caches the app shell + static images for offline use.
// Network-first for HTML so users see fresh content when online but the app still loads when offline.
// Cache-first for images (they rarely change).
// Firebase / Firestore / Bible Brain requests pass straight through — those are dynamic.
// Bump CACHE_VERSION when you ship a meaningful change to force everyone to refresh their cache.

const CACHE_VERSION = 'biblesprint-v1';
const CORE_ASSETS = [
  '/',
  '/index.html',
  '/home.html',
  '/app.html',
  '/about.html',
  '/contact.html',
  '/privacy.html',
  '/blog/',
  '/blog/index.html',
  '/blog/why-i-read-the-whole-bible-in-43-days.html',
  '/offline.html',
  '/manifest.json',
  '/cross-hero.webp',
  '/cross-hero-mobile.webp',
  '/icon-192.png',
  '/icon-512.png',
  '/icon-maskable-512.png',
  '/icon-180.png',
];

// Install: warm the cache with the core app shell.
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_VERSION).then((cache) => {
      // addAll fails the whole install if any single asset 404s — use individual put calls so a missing optional file doesn't block install
      return Promise.allSettled(
        CORE_ASSETS.map((url) =>
          fetch(url, { cache: 'reload' })
            .then((response) => response.ok ? cache.put(url, response) : null)
            .catch(() => null)
        )
      );
    }).then(() => self.skipWaiting())
  );
});

// Activate: clean up any old caches from a previous CACHE_VERSION.
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.filter((k) => k !== CACHE_VERSION).map((k) => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

// Fetch handler — different strategies for different asset types.
self.addEventListener('fetch', (event) => {
  const req = event.request;
  // Only handle GET requests
  if (req.method !== 'GET') return;

  const url = new URL(req.url);

  // Skip cross-origin requests entirely — Firebase, FCBH, Bible.is, Formspree, fonts, all of it.
  // The browser handles them normally and they bypass our cache.
  if (url.origin !== self.location.origin) return;

  // Skip Firebase config / API routes that might be served from our own origin in future
  if (url.pathname.startsWith('/api/')) return;

  // HTML documents → network-first, fall back to cache, fall back to /offline.html
  if (req.mode === 'navigate' || req.headers.get('accept')?.includes('text/html')) {
    event.respondWith(
      fetch(req)
        .then((response) => {
          // Update cache in the background
          const copy = response.clone();
          caches.open(CACHE_VERSION).then((cache) => cache.put(req, copy));
          return response;
        })
        .catch(() =>
          caches.match(req).then((cached) => cached || caches.match('/offline.html'))
        )
    );
    return;
  }

  // Static assets (images, fonts) → cache-first, network fallback
  if (
    /\.(png|jpg|jpeg|webp|svg|gif|ico|woff|woff2|ttf|otf)$/i.test(url.pathname) ||
    url.pathname === '/manifest.json'
  ) {
    event.respondWith(
      caches.match(req).then((cached) => {
        if (cached) return cached;
        return fetch(req).then((response) => {
          if (response.ok) {
            const copy = response.clone();
            caches.open(CACHE_VERSION).then((cache) => cache.put(req, copy));
          }
          return response;
        });
      })
    );
    return;
  }

  // Anything else (xml, txt, .md, etc.) → try network, then cache
  event.respondWith(
    fetch(req).catch(() => caches.match(req))
  );
});
