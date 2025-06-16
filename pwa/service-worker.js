const CACHE_NAME = "rastreamento-v3"; // Aumente a versão do cache a cada mudança significativa
const urlsToCache = [
  "./", // Caches a URL raiz
  "./index.php", // Seu arquivo principal
  "./manifest.json",
  "./icon/logoPJ1.png", // Seu ícone 192x192
  "./icon/logoPJ2.png", // Seu ícone 512x512
  "./salvar_localizacao.php", // Cacheia o script, mas a estratégia de fetch abaixo evita uso indevido
  "./get_localizacoes.php",   // Cacheia o script, mas a estratégia de fetch abaixo evita uso indevido
  
  // Recursos do Leaflet e Awesome Markers (CSS e JS)
  "https://unpkg.com/leaflet/dist/leaflet.css",
  "https://unpkg.com/leaflet/dist/leaflet.js",
  "https://cdnjs.cloudflare.com/ajax/libs/Leaflet.awesome-markers/2.0.2/leaflet.awesome-markers.css",
  "https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css", 
  "https://cdnjs.cloudflare.com/ajax/libs/Leaflet.awesome-markers/2.0.2/leaflet.awesome-markers.min.js"
];

// Evento de instalação: abre o cache e adiciona todos os recursos listados
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
  self.skipWaiting(); // Ativa o Service Worker imediatamente após a instalação
});

// Evento de ativação: limpa caches antigos para liberar espaço
self.addEventListener("activate", (event) => {
  console.log("[ServiceWorker] Ativado");
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames.map((cacheName) => {
          if (cacheName !== CACHE_NAME) {
            console.log("[ServiceWorker] Limpando cache antigo:", cacheName);
            return caches.delete(cacheName);
          }
        })
      );
    })
  );
  // Garante que o Service Worker assume o controle da página imediatamente após a ativação
  return self.clients.claim(); 
});

// Intercepta todas as requisições de rede
self.addEventListener("fetch", (event) => {
  const requestUrl = new URL(event.request.url);

  // Regra 1: Para APIs externas (Nominatim) e scripts PHP dinâmicos (salvar/obter localizações)
  // Sempre tente a rede primeiro para garantir dados mais recentes.
  if (
    requestUrl.origin === "https://nominatim.openstreetmap.org" ||
    requestUrl.pathname.includes("salvar_localizacao.php") ||
    requestUrl.pathname.includes("get_localizacoes.php")
  ) {
    event.respondWith(
      fetch(event.request).catch(error => {
        console.warn(`[ServiceWorker] Rede falhou para ${requestUrl.pathname}:`, error);
        // Opcional: Adicione um fallback para UX em caso de offline para dados dinâmicos,
        // por exemplo, retornando uma mensagem JSON de erro ou um status de offline.
        // throw error; // Relançar o erro para que o fetch no app ainda falhe.
      })
    );
    return; // Não processa mais esta requisição com outras regras de cache
  }

  // Regra 2: Para outros recursos (estáticos: HTML, CSS, JS, imagens, etc.)
  // Use a estratégia Cache-First: Tente o cache primeiro, se não encontrar, vá para a rede.
  event.respondWith(
    caches.match(event.request).then((response) => {
      // Se a resposta estiver no cache, retorna ela
      if (response) {
        return response;
      }

      // Se não estiver no cache, busca na rede
      return fetch(event.request).then((networkResponse) => {
        // Verifica se a resposta da rede é válida antes de cachear
        // (status 200 OK e tipo 'basic' para requisições do mesmo domínio)
        if (!networkResponse || networkResponse.status !== 200 || networkResponse.type !== 'basic') {
          return networkResponse;
        }

        // Clona a resposta para que ela possa ser consumida tanto pelo navegador quanto pelo cache
        const responseToCache = networkResponse.clone();
        caches.open(CACHE_NAME).then((cache) => {
          cache.put(event.request, responseToCache);
        });
        return networkResponse;
      });
    }).catch(error => {
      console.error(`[ServiceWorker] Erro de rede ou cache para ${requestUrl.pathname}:`, error);
      // Opcional: Aqui você pode adicionar um fallback para recursos estáticos em caso de offline
      // Por exemplo, retornar uma imagem de "offline" para imagens que falharam, ou uma página offline.
      // return caches.match('/offline.html');
    })
  );
});