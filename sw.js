const CACHE = 'papi-rastro-v5-group-filter';
const ASSETS = [
  './manifest.webmanifest',
  './assets/favicon.ico',
  './assets/favicon-32.png',
  './assets/apple-touch-icon.png',
  './assets/papijunior-logo.png',
  './assets/papilab-logo.png',
  './assets/icons/icon-192.png',
  './assets/icons/icon-512.png',
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE).then((cache) => cache.addAll(ASSETS)).then(() => self.skipWaiting())
  );
});

async function closeAllNotifications() {
  const list = await self.registration.getNotifications();
  list.forEach((n) => n.close());
}

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))))
      .then(() => closeAllNotifications())
      .then(() => self.clients.claim())
  );
});

self.addEventListener('message', (event) => {
  const data = event.data || {};
  if (data.type === 'close-notifications' || data.type === 'sharing-on' || data.type === 'sharing-off') {
    event.waitUntil(closeAllNotifications());
  }
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
});

self.addEventListener('fetch', (event) => {
  const url = new URL(event.request.url);

  // Nunca cachear API / JS / CSS / PHP — evita JS antigo com notificação de GPS
  if (
    url.pathname.includes('/api/') ||
    url.pathname.endsWith('.js') ||
    url.pathname.endsWith('.css') ||
    url.pathname.endsWith('.php') ||
    url.hostname === 'nominatim.openstreetmap.org' ||
    url.hostname.includes('tile.openstreetmap.org')
  ) {
    event.respondWith(
      fetch(event.request).catch(() => {
        if (url.pathname.includes('/api/')) {
          return new Response(JSON.stringify({ status: 'erro', mensagem: 'Offline' }), {
            status: 503,
            headers: { 'Content-Type': 'application/json' },
          });
        }
        return caches.match(event.request);
      })
    );
    return;
  }

  event.respondWith(
    caches.match(event.request).then((cached) => {
      const network = fetch(event.request)
        .then((response) => {
          if (response && response.status === 200 && response.type === 'basic') {
            const clone = response.clone();
            caches.open(CACHE).then((cache) => cache.put(event.request, clone));
          }
          return response;
        })
        .catch(() => cached);
      return network || cached;
    })
  );
});
