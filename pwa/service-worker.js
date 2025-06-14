const CACHE_NAME = "rastreamento-v1";
const urlsToCache = [
  "index.php",
  "manifest.json",
  "icon/logoPJ2.png",
  "icon/logoPJ1.png"
];

// Evento de instalação
self.addEventListener("install", (event) => {
  console.log("[ServiceWorker] Instalando...");
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      console.log("[ServiceWorker] Arquivos em cache");
      return cache.addAll(urlsToCache);
    })
  );
  self.skipWaiting();
});

// Evento de ativação
self.addEventListener("activate", (event) => {
  console.log("[ServiceWorker] Ativado");
  event.waitUntil(
    caches.keys().then((cacheNames) =>
      Promise.all(
        cacheNames.map((cacheName) => {
          if (cacheName !== CACHE_NAME) {
            console.log("[ServiceWorker] Limpando cache antigo:", cacheName);
            return caches.delete(cacheName);
          }
        })
      )
    )
  );
});

// Interceptar requisições
self.addEventListener("fetch", (event) => {
  event.respondWith(
    caches.match(event.request).then((response) => {
      return response || fetch(event.request);
    })
  );
});
