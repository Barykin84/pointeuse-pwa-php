// /service-worker.js
const CACHE_NAME = 'pointeuse-v2'; // <- change la version pour forcer la MAJ
const STATIC_ASSETS = new Set([
  '/assets/styles.css',
  '/offline.html',
  // CDN bootstrap rest precached via browser cache; on peut l'ajouter si tu veux
]);

self.addEventListener('install', (event) => {
  event.waitUntil((async () => {
    const cache = await caches.open(CACHE_NAME);
    await cache.addAll([...STATIC_ASSETS]);
  })());
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil((async () => {
    const keys = await caches.keys();
    await Promise.all(keys.map(k => (k !== CACHE_NAME) ? caches.delete(k) : null));
    await self.clients.claim();
  })());
});

// Stratégie :
// - Navigations (HTML/PHP) : réseau d'abord; si offline => offline.html
// - /api/* : jamais mis en cache (réseau direct)
// - Assets statiques listés dans STATIC_ASSETS : cache-first puis réseau
// - Tout le reste : passe au réseau (pas de cache)
self.addEventListener('fetch', (event) => {
  const req = event.request;

  // Laisse passer POST/PUT/etc.
  if (req.method !== 'GET') return;

  const url = new URL(req.url);
  const sameOrigin = url.origin === self.location.origin;

  // 1) Navigations (documents HTML/PHP)
  if (req.mode === 'navigate' || (req.destination === 'document')) {
    event.respondWith((async () => {
      try {
        // Réseau d'abord : autorise les redirections serveur (ex: vers /login.php)
        const res = await fetch(req);
        return res;
      } catch (err) {
        // Hors-ligne : fallback
        const cached = await caches.match('/offline.html');
        return cached || new Response('Hors-ligne', { status: 503 });
      }
    })());
    return;
  }

  // 2) API : jamais cachée
  if (sameOrigin && url.pathname.startsWith('/api/')) {
    return; // réseau direct, pas d'interception
  }

  // 3) Assets statiques : cache-first
  if (sameOrigin && STATIC_ASSETS.has(url.pathname)) {
    event.respondWith((async () => {
      const cached = await caches.match(req);
      if (cached) return cached;
      const res = await fetch(req);
      // ne met pas en cache les réponses redirigées/erreurs
      if (res && res.ok && !res.redirected) {
        const clone = res.clone();
        const cache = await caches.open(CACHE_NAME);
        cache.put(req, clone);
      }
      return res;
    })());
    return;
  }

  // 4) Par défaut : réseau (pas de cache)
  // (évite d'introduire des réponses redirigées dans le cache)
});
