(() => {
  const KEY = 'papi_rastro_msg_cursor';
  let lastCursor = Number(localStorage.getItem(KEY) || 0);
  let hasCursor = localStorage.getItem(KEY) !== null;

  function updateBadges(count) {
    document.querySelectorAll('[data-msg-badge]').forEach((el) => {
      const n = Number(count) || 0;
      if (n > 0) {
        el.textContent = String(n);
        el.hidden = false;
      } else {
        el.textContent = '';
        el.hidden = true;
      }
    });
  }

  // Só notifica se a aba estiver em segundo plano e a permissão já existir
  // (não pede permissão sozinho — evita popups no Firefox/desktop)
  function notify(items) {
    if (!items.length) return;
    if (document.visibilityState === 'visible') return;
    if (!('Notification' in window) || Notification.permission !== 'granted') return;

    items.forEach((m) => {
      const de = m.de_nome || m.de_usuario || 'Alguém';
      const body = m.tipo === 'audio' ? 'Enviou um áudio' : String(m.corpo || '').slice(0, 120);
      try {
        const n = new Notification('PAPI Rastro — ' + de, {
          body,
          tag: 'msg-' + m.id,
          icon: 'assets/icons/icon-192.png',
        });
        n.onclick = () => {
          window.focus();
          location.href = 'mensagens.php?com=' + encodeURIComponent(m.de_usuario_id);
          n.close();
        };
      } catch (_) {}
    });
  }

  async function poll() {
    try {
      const url = 'api/mensagens_nao_lidas.php?after_id=' + encodeURIComponent(String(lastCursor));
      const resp = await fetch(url, { credentials: 'include' });
      if (resp.status === 401) return;
      if (!resp.ok) return;
      const data = await resp.json();
      updateBadges(data.nao_lidas || 0);
      const novas = Array.isArray(data.novas) ? data.novas : [];
      if (hasCursor && novas.length) {
        notify(novas);
      }
      if (typeof data.cursor === 'number') {
        lastCursor = Math.max(lastCursor, data.cursor);
        localStorage.setItem(KEY, String(lastCursor));
        hasCursor = true;
      }
    } catch (_) {
      // silencioso
    }
  }

  window.addEventListener('papi:mensagens-refresh', poll);

  poll();
  setInterval(poll, 15000);
})();
