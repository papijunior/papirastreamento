const CACHE_NAME = "rastreamento-v6"; // *** INCREMENTE A VERSÃO DO CACHE NOVAMENTE ***
const urlsToCache = [
  "./", 
  "./pwa.html", 
  "./manifest.json",
  "./icon/logoPJ1.png", 
  "./icon/logoPJ2.png",   
  // Não precisamos cachear salvar_localizacao.php e get_localizacoes.php aqui, 
  // pois eles sempre devem ir para a rede e a estratégia de fetch abaixo garante isso.
  // Se eles fossem HTML, CSS ou JS estáticos, então sim.
  
  // Recursos externos (CDNs) - cruciais para o funcionamento offline
  "https://unpkg.com/leaflet/dist/leaflet.css",
  "https://unpkg.com/leaflet/dist/leaflet.js",
  "https://cdnjs.cloudflare.com/ajax/libs/Leaflet.awesome-markers/2.0.2/leaflet.awesome-markers.css",
  "https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css", 
  "https://cdnjs.cloudflare.com/ajax/libs/Leaflet.awesome-markers/2.0.2/leaflet.awesome-markers.min.js"
];

self.addEventListener("install", (event) => {
  console.log("[ServiceWorker] Instalando...");
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => {
        console.log("[ServiceWorker] Adicionando arquivos ao cache...");
        return cache.addAll(urlsToCache)
          .then(() => console.log("[ServiceWorker] URLs adicionadas ao cache com sucesso."))
          .catch((error) => console.error("[ServiceWorker] Falha ao adicionar URLs ao cache durante a instalação:", error));
      })
      .catch((error) => console.error("[ServiceWorker] Erro ao abrir cache durante a instalação:", error))
  );
  self.skipWaiting(); 
});

self.addEventListener("activate", (event) => {
  console.log("[ServiceWorker] Ativado");
  const cacheWhitelist = [CACHE_NAME]; 
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames.map((cacheName) => {
          if (cacheWhitelist.indexOf(cacheName) === -1) {
            console.log("[ServiceWorker] Limpando cache antigo:", cacheName);
            return caches.delete(cacheName);
          }
        })
      );
    })
  );
  return self.clients.claim(); 
});

self.addEventListener("fetch", (event) => {
  const requestUrl = new URL(event.request.url);

  // Estratégia "Network-First" com fallback para um "erro" para dados dinâmicos e APIs externas.
  if (
    requestUrl.origin === "https://nominatim.openstreetmap.org" ||
    requestUrl.pathname.includes("salvar_localizacao.php") ||
    requestUrl.pathname.includes("get_localizacoes.php")
  ) {
    event.respondWith(
      fetch(event.request)
        .then(response => {
          // Se a resposta for OK, retorna ela.
          return response;
        })
        .catch(error => {
          console.warn(`[ServiceWorker] Rede falhou para ${requestUrl.pathname}:`, error);
          // *** IMPORTANTE: Retorna uma Response de erro para o navegador. ***
          // Isso evita o 'Failed to convert value to Response' e permite o tratamento no frontend.
          // Definimos o status para 503 (Service Unavailable) e um corpo JSON de erro.
          return new Response(JSON.stringify({ 
            status: 'erro_sw', 
            message: `Falha na conexão ou API indisponível para ${requestUrl.pathname}.`,
            error_details: error.message // Inclui a mensagem de erro original
          }), { 
            status: 503, // Service Unavailable
            headers: { 'Content-Type': 'application/json' }
          });
        })
    );
    return; 
  }

  // Estratégia "Cache-First" para recursos estáticos.
  event.respondWith(
    caches.match(event.request).then((response) => {
      if (response) {
        return response;
      }

      return fetch(event.request).then((networkResponse) => {
        if (!networkResponse || networkResponse.status !== 200 || networkResponse.type !== 'basic') {
          return networkResponse;
        }

        const responseToCache = networkResponse.clone();
        caches.open(CACHE_NAME).then((cache) => {
          cache.put(event.request, responseToCache);
        });
        return networkResponse;
      }).catch(error => {
        console.error(`[ServiceWorker] Erro de rede ou cache para ${requestUrl.pathname}:`, error);
        // Em caso de falha para recursos estáticos que não estão no cache,
        // você pode retornar uma resposta de fallback (ex: página offline, imagem padrão).
        // Por agora, vamos retornar um Response de erro genérico se for um tipo que não é um HTML ou imagem
        // ou você pode lançar o erro se o frontend puder lidar com a ausência do recurso.
        return new Response('Recurso não disponível offline.', { status: 503 });
      });
    })
  );
});