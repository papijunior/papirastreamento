(() => {
  const cfg = window.PAPI_RASTRO || {};
  const statusEl = document.getElementById('geo-status');
  const legendEl = document.getElementById('legend-content');
  const btnGeo = document.getElementById('btn-geo');
  const btnPause = document.getElementById('btn-pause');
  const installBanner = document.getElementById('install-banner');
  const btnInstall = document.getElementById('btn-install');
  const btnInstallDismiss = document.getElementById('btn-install-dismiss');
  const geoGate = document.getElementById('geo-gate');
  const btnGeoGate = document.getElementById('btn-geo-gate');
  const btnGeoGateLater = document.getElementById('btn-geo-gate-later');
  const geoGateSecure = document.getElementById('geo-gate-secure');
  const sheet = document.getElementById('person-sheet');
  const sheetClose = document.getElementById('sheet-close');
  const sheetForm = document.getElementById('sheet-msg-form');
  const sheetPara = document.getElementById('sheet-para-id');
  const sheetTexto = document.getElementById('sheet-texto');
  const sheetRec = document.getElementById('sheet-rec');
  const sheetStatus = document.getElementById('sheet-msg-status');
  const sheetPreview = document.getElementById('sheet-audio-preview');
  let sheetRecorder = null;
  let sheetChunks = [];
  let sheetAudioBlob = null;
  let sheetPersonId = null;

  let compartilhando = !!cfg.compartilhando;
  const filtroGrupo = document.getElementById('filtro-grupo');
  let grupoFiltro = cfg.grupoIdInicial != null ? String(cfg.grupoIdInicial) : '';
  if (filtroGrupo) {
    filtroGrupo.value = grupoFiltro;
  }
  let wakeLock = null;

  // Cores bem distintas e estáveis por usuário (sem foto)
  const colors = [
    '#1a4f9c', '#0f6b3c', '#c2410c', '#9b1c1c', '#6d28d9',
    '#0e7490', '#be185d', '#a16207', '#15803d', '#1d4ed8',
    '#b91c1c', '#7c3aed', '#0891b2', '#c026d3',
  ];
  const colorByUser = new Map();
  let deferredPrompt = null;
  let watchId = null;
  let lastSaveAt = 0;
  let selfMarker = null;
  let selfCircle = null;
  let selfTrueLatLng = null;
  let geoAtivo = false;

  function colorFor(userId) {
    const id = Number(userId) || 0;
    if (!colorByUser.has(id)) {
      // hash estável: mesmo usuário = mesma cor em qualquer dispositivo
      let h = id * 2654435761;
      h = Math.abs(h) % colors.length;
      colorByUser.set(id, colors[h]);
    }
    return colorByUser.get(id);
  }

  function myColor() {
    return colorFor(cfg.usuarioId);
  }

  function myDisplayName() {
    return String(cfg.nome || cfg.usuario || 'Você');
  }

  function setStatus(text, isError = false) {
    if (!statusEl) return;
    statusEl.textContent = text;
    statusEl.classList.toggle('is-error', isError);
  }

  function escapeHtml(value) {
    return String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function photoUrl(path) {
    if (!path) return null;
    return String(path);
  }

  function initials(label) {
    const t = String(label || '?').trim();
    return (t.charAt(0) || '?').toUpperCase();
  }

  function photoIcon(foto, label, color) {
    const url = photoUrl(foto);
    const html = url
      ? `<div class="map-avatar" style="color:${color};border-color:${color}"><img src="${escapeHtml(url)}" alt=""></div>`
      : `<div class="map-avatar map-avatar-fallback" style="color:${color};border-color:${color};background:${color}">${escapeHtml(initials(label))}</div>`;

    return L.divIcon({
      className: 'map-avatar-icon',
      html,
      iconSize: [44, 44],
      iconAnchor: [22, 22],
      popupAnchor: [0, -22],
    });
  }

  function metersToLat(m) {
    return m / 111320;
  }

  function metersToLon(m, lat) {
    return m / (111320 * Math.max(0.2, Math.cos((lat * Math.PI) / 180)));
  }

  function distanceMeters(a, b) {
    const toRad = (d) => (d * Math.PI) / 180;
    const R = 6371000;
    const dLat = toRad(b.lat - a.lat);
    const dLon = toRad(b.lon - a.lon);
    const lat1 = toRad(a.lat);
    const lat2 = toRad(b.lat);
    const x = Math.sin(dLat / 2) ** 2
      + Math.cos(lat1) * Math.cos(lat2) * Math.sin(dLon / 2) ** 2;
    return 2 * R * Math.asin(Math.min(1, Math.sqrt(x)));
  }

  /** Separa avatares no mesmo ponto em leque, para ninguém ficar escondido. */
  function fanOutPositions(entries, proximityM = 28, fanRadiusM = 20) {
    const result = new Map();
    const used = new Set();

    for (let i = 0; i < entries.length; i += 1) {
      if (used.has(i)) continue;
      const cluster = [i];
      for (let j = i + 1; j < entries.length; j += 1) {
        if (used.has(j)) continue;
        if (distanceMeters(entries[i], entries[j]) <= proximityM) {
          cluster.push(j);
        }
      }
      cluster.forEach((idx) => used.add(idx));

      if (cluster.length === 1) {
        const e = entries[cluster[0]];
        result.set(e.key, { lat: e.lat, lon: e.lon });
        continue;
      }

      let clat = 0;
      let clon = 0;
      cluster.forEach((idx) => {
        clat += entries[idx].lat;
        clon += entries[idx].lon;
      });
      clat /= cluster.length;
      clon /= cluster.length;

      const n = cluster.length;
      const radius = fanRadiusM + Math.max(0, n - 3) * 4;
      cluster.forEach((idx, k) => {
        const angle = ((2 * Math.PI) * k) / n - Math.PI / 2;
        result.set(entries[idx].key, {
          lat: clat + metersToLat(radius) * Math.cos(angle),
          lon: clon + metersToLon(radius, clat) * Math.sin(angle),
        });
      });
    }

    return result;
  }

  function applyFanOutToMapMarkers() {
    const entries = [];
    markersByUser.forEach((marker, uid) => {
      const trueLl = marker._papiTrueLatLng || marker.getLatLng();
      entries.push({
        key: 'u:' + uid,
        lat: trueLl.lat,
        lon: trueLl.lng,
        marker,
      });
    });
    if (selfMarker) {
      const trueLl = selfTrueLatLng || selfMarker.getLatLng();
      // Evita duplicar o próprio usuário se já veio na API
      if (!markersByUser.has(Number(cfg.usuarioId))) {
        entries.push({
          key: 'self',
          lat: trueLl.lat,
          lon: trueLl.lng,
          marker: selfMarker,
        });
      }
    }
    if (entries.length < 2) {
      if (selfMarker && selfTrueLatLng) {
        selfMarker.setLatLng(selfTrueLatLng);
      }
      return;
    }

    const positions = fanOutPositions(
      entries.map((e) => ({ key: e.key, lat: e.lat, lon: e.lon }))
    );
    entries.forEach((e) => {
      const pos = positions.get(e.key);
      if (pos) e.marker.setLatLng([pos.lat, pos.lon]);
    });
  }
  const map = L.map('map').setView([-22.7291, -47.6493], 12);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap',
  }).addTo(map);

  const markersLayer = L.layerGroup().addTo(map);
  const markersByUser = new Map();
  const peopleByUser = new Map();

  async function reverseGeocode(lat, lon) {
    const fallback = `Lat ${lat.toFixed(5)}, Lon ${lon.toFixed(5)}`;
    try {
      const ctrl = new AbortController();
      const timer = setTimeout(() => ctrl.abort(), 2500);
      const resp = await fetch(
        `https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${lat}&lon=${lon}&zoom=18&addressdetails=1`,
        {
          headers: { 'User-Agent': 'PapiRastro/1.0 (paulo@papijunior.com.br)' },
          signal: ctrl.signal,
        }
      );
      clearTimeout(timer);
      if (!resp.ok) return fallback;
      const data = await resp.json();
      return data.display_name || fallback;
    } catch (_) {
      return fallback;
    }
  }

  async function salvarLocalizacao(latitude, longitude, enderecoPrevio) {
    const agora = Date.now();
    if (agora - lastSaveAt < 8000) return false;
    lastSaveAt = agora;

    // Não bloqueia o save se o Nominatim estiver lento/bloqueado no celular
    let endereco = enderecoPrevio;
    if (!endereco) {
      endereco = await Promise.race([
        reverseGeocode(latitude, longitude),
        new Promise((resolve) => setTimeout(() => resolve(`Lat ${latitude.toFixed(5)}, Lon ${longitude.toFixed(5)}`), 2000)),
      ]);
    }
    const dispositivo = (navigator.userAgent || '').slice(0, 100);

    const resp = await fetch('api/salvar_localizacao.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify({ latitude, longitude, endereco, dispositivo }),
    });
    const data = await resp.json().catch(() => ({}));
    if (resp.status === 401) {
      setStatus('Sessão expirada. Faça login de novo com papijunior.cell.', true);
      window.location.href = 'login.php';
      return false;
    }
    if (resp.status === 403) {
      throw new Error('Servidor bloqueou o envio (403). Atualize a página e tente de novo.');
    }
    if (!resp.ok) {
      throw new Error(data.mensagem || 'Falha ao salvar localização');
    }
    if (data.status === 'ignorado') {
      setStatus(data.mensagem || 'Localização não registrada neste momento.');
      if (data.participa_escala && data.em_escala === false) {
        // fora da escala
      }
      return false;
    }
    return true;
  }

  function resetSheetAudio() {
    sheetAudioBlob = null;
    sheetChunks = [];
    if (sheetPreview) {
      sheetPreview.hidden = true;
      sheetPreview.removeAttribute('src');
    }
    sheetRec?.classList.remove('is-recording');
    sheetRec?.setAttribute('aria-pressed', 'false');
    if (sheetStatus) sheetStatus.hidden = true;
    if (sheetTexto) sheetTexto.value = '';
  }

  function openPersonSheet(person, opts = {}) {
    if (!sheet || !person) return;
    const label = person.nome || person.usuario || 'Pessoa';
    const foto = photoUrl(person.foto);
    const fotoEl = document.getElementById('sheet-foto');
    const fotoFb = document.getElementById('sheet-foto-fallback');
    const titleEl = document.getElementById('sheet-title');
    const whenEl = document.getElementById('sheet-quando');
    const addrEl = document.getElementById('sheet-endereco');
    const mapsEl = document.getElementById('sheet-maps');
    const hintEl = document.getElementById('sheet-msg-hint');
    const msgBlock = document.getElementById('sheet-msg-block');
    const msgHeading = document.getElementById('sheet-msg-heading');

    titleEl.textContent = label;
    whenEl.textContent = person.criado_em
      ? 'Atualizado em ' + new Date(person.criado_em).toLocaleString('pt-BR')
      : '';
    addrEl.textContent = person.endereco || 'Endereço ainda não disponível.';

    if (foto) {
      fotoEl.src = foto;
      fotoEl.hidden = false;
      if (fotoFb) fotoFb.hidden = true;
    } else {
      fotoEl.removeAttribute('src');
      fotoEl.hidden = true;
      if (fotoFb) {
        const color = colorFor(person.usuario_id || 0);
        fotoFb.hidden = false;
        fotoFb.textContent = initials(label);
        fotoFb.style.background = color;
        fotoFb.style.borderColor = color;
      }
    }

    mapsEl.href = `https://www.google.com/maps?q=${person.latitude},${person.longitude}`;

    sheetPersonId = Number(person.usuario_id || 0);
    resetSheetAudio();
    const isSelf = sheetPersonId > 0 && sheetPersonId === Number(cfg.usuarioId);
    if (msgBlock) msgBlock.hidden = isSelf;
    if (sheetForm) {
      sheetForm.hidden = isSelf;
      if (sheetPara) sheetPara.value = isSelf ? '' : String(sheetPersonId);
    }
    if (msgHeading) {
      msgHeading.textContent = isSelf ? 'Mensagem' : ('Enviar mensagem para ' + label);
    }
    if (hintEl) hintEl.hidden = !isSelf;

    sheet.hidden = false;

    if (opts.focusMessage && !isSelf && sheetTexto) {
      setTimeout(() => {
        sheetTexto.focus();
        msgBlock?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      }, 50);
    }
  }

  function closePersonSheet() {
    if (sheetRecorder && sheetRecorder.state === 'recording') {
      try { sheetRecorder.stop(); } catch (_) {}
    }
    if (sheet) sheet.hidden = true;
  }

  sheetClose?.addEventListener('click', closePersonSheet);
  sheet?.addEventListener('click', (ev) => {
    if (ev.target === sheet) closePersonSheet();
  });

  sheetRec?.addEventListener('click', async () => {
    try {
      if (sheetRecorder && sheetRecorder.state === 'recording') {
        sheetRecorder.stop();
        return;
      }
      if (!navigator.mediaDevices?.getUserMedia) {
        alert('Gravação de áudio não suportada neste navegador.');
        return;
      }
      const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
      sheetChunks = [];
      sheetAudioBlob = null;
      const mime = MediaRecorder.isTypeSupported('audio/webm;codecs=opus')
        ? 'audio/webm;codecs=opus'
        : 'audio/webm';
      sheetRecorder = new MediaRecorder(stream);
      sheetRecorder.ondataavailable = (ev) => {
        if (ev.data.size > 0) sheetChunks.push(ev.data);
      };
      sheetRecorder.onstop = () => {
        stream.getTracks().forEach((t) => t.stop());
        sheetAudioBlob = new Blob(sheetChunks, { type: mime });
        if (sheetPreview) {
          sheetPreview.src = URL.createObjectURL(sheetAudioBlob);
          sheetPreview.hidden = false;
        }
        sheetRec?.classList.remove('is-recording');
        sheetRec?.setAttribute('aria-pressed', 'false');
        if (sheetStatus) {
          sheetStatus.hidden = false;
          sheetStatus.textContent = 'Áudio pronto. Toque em Enviar.';
        }
      };
      sheetRecorder.start();
      sheetRec.classList.add('is-recording');
      sheetRec.setAttribute('aria-pressed', 'true');
      if (sheetStatus) {
        sheetStatus.hidden = false;
        sheetStatus.textContent = 'Gravando… toque em Áudio de novo para parar.';
      }
    } catch (err) {
      alert(err.message || 'Não foi possível gravar.');
    }
  });

  sheetForm?.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const para = Number(sheetPara?.value || 0);
    const texto = String(sheetTexto?.value || '').trim();
    if (!para) return;
    if (!texto && !sheetAudioBlob) {
      alert('Digite um texto ou grave um áudio.');
      return;
    }

    const fd = new FormData();
    fd.append('para_usuario_id', String(para));
    if (texto) fd.append('texto', texto);
    if (sheetAudioBlob) fd.append('audio', sheetAudioBlob, 'audio.webm');

    const btn = document.getElementById('sheet-send');
    if (btn) btn.disabled = true;
    if (sheetStatus) {
      sheetStatus.hidden = false;
      sheetStatus.textContent = 'Enviando…';
    }
    try {
      const resp = await fetch('api/mensagens_enviar.php', {
        method: 'POST',
        body: fd,
        credentials: 'same-origin',
      });
      const data = await resp.json();
      if (!resp.ok) throw new Error(data.mensagem || 'Falha ao enviar');

      const openUrl = data.whatsapp?.abrir_url;
      if (openUrl && !data.whatsapp?.enviado_api) {
        window.open(openUrl, '_blank', 'noopener');
      }

      if (sheetStatus) {
        sheetStatus.hidden = false;
        sheetStatus.textContent = data.whatsapp?.enviado_api
          ? 'Enviado no app e no WhatsApp.'
          : (openUrl
            ? 'Salvo no app. Abrindo WhatsApp para confirmar o envio…'
            : 'Mensagem salva no app. Cadastre o telefone para também enviar no WhatsApp.');
      }
      resetSheetAudio();
      window.dispatchEvent(new CustomEvent('papi:mensagens-refresh'));
    } catch (err) {
      if (sheetStatus) {
        sheetStatus.hidden = false;
        sheetStatus.textContent = err.message || 'Erro ao enviar';
      }
    } finally {
      if (btn) btn.disabled = false;
    }
  });
  function hideGeoGate() {
    if (geoGate) geoGate.hidden = true;
  }

  function showGeoGate(extraMsg) {
    if (!geoGate) return;
    if (extraMsg && geoGateSecure) {
      geoGateSecure.hidden = false;
      geoGateSecure.textContent = extraMsg;
    }
    geoGate.hidden = false;
  }

  function upsertSelfMarker(lat, lon, accuracy, endereco) {
    const label = myDisplayName();
    const color = myColor();
    const icon = photoIcon(cfg.foto, label, color);
    selfTrueLatLng = L.latLng(lat, lon);

    if (selfMarker) {
      selfMarker.setLatLng([lat, lon]);
      selfMarker.setIcon(icon);
    } else {
      selfMarker = L.marker([lat, lon], { icon, zIndexOffset: 1000 }).addTo(map);
      selfMarker.on('click', () => {
        const trueLl = selfTrueLatLng || selfMarker.getLatLng();
        openPersonSheet({
          usuario_id: cfg.usuarioId,
          usuario: cfg.usuario,
          nome: myDisplayName() + ' (você)',
          foto: cfg.foto,
          telefone: null,
          latitude: trueLl.lat,
          longitude: trueLl.lng,
          endereco,
          criado_em: new Date().toISOString(),
        });
      });
    }

    if (selfCircle) {
      selfCircle.setLatLng([lat, lon]).setRadius(Math.min(accuracy, 80));
      selfCircle.setStyle({ color, fillColor: color });
    } else {
      selfCircle = L.circle([lat, lon], {
        radius: Math.min(accuracy, 80),
        color,
        fillColor: color,
        fillOpacity: 0.12,
        weight: 1,
      }).addTo(map);
    }

    applyFanOutToMapMarkers();
  }

  async function onGeoSuccess(pos) {
    geoAtivo = true;
    hideGeoGate();
    if (btnGeo) {
      btnGeo.disabled = false;
      btnGeo.classList.add('is-active');
      btnGeo.textContent = 'Localização ativa';
    }

    const { latitude, longitude, accuracy } = pos.coords;
    map.setView([latitude, longitude], Math.max(map.getZoom(), 15));

    // Mostra no mapa imediatamente; não espera Nominatim (trava no celular)
    const enderecoRapido = `Lat ${latitude.toFixed(5)}, Lon ${longitude.toFixed(5)}`;
    upsertSelfMarker(latitude, longitude, accuracy || 20, enderecoRapido);

    if (!compartilhando) {
      setStatus('Localização obtida, mas o compartilhamento está pausado.');
      return;
    }

    setStatus('Enviando sua localização…');
    try {
      const salvou = await salvarLocalizacao(latitude, longitude, enderecoRapido);
      reverseGeocode(latitude, longitude).then((addr) => {
        if (addr) upsertSelfMarker(latitude, longitude, accuracy || 20, addr);
      }).catch(() => {});

      await requestWakeLock();
      if (salvou) {
        setStatus('Você está no mapa ao vivo. Deixe esta tela aberta (minimizar ok; fechar some do mapa).');
        carregarLocalizacoes();
      }
    } catch (err) {
      setStatus('Não foi possível salvar a localização: ' + err.message, true);
    }
  }

  function onGeoError(err) {
    if (btnGeo) {
      btnGeo.disabled = false;
      btnGeo.classList.remove('is-active');
      btnGeo.textContent = 'Ativar localização';
    }
    let msg = 'Não foi possível obter sua localização. ';
    if (err.code === err.PERMISSION_DENIED) {
      msg += 'Permissão negada — nas configurações do celular, permita a localização para este site/app e toque de novo.';
    } else if (err.code === err.POSITION_UNAVAILABLE) {
      msg += 'Localização indisponível. Ative o GPS do celular.';
    } else if (err.code === err.TIMEOUT) {
      msg += 'Tempo esgotado. Tente de novo perto de uma janela ou com Wi‑Fi.';
    } else {
      msg += err.message || 'Erro desconhecido.';
    }
    setStatus(msg, true);
    showGeoGate(msg);
  }

  function iniciarGeolocalizacao() {
    if (!navigator.geolocation) {
      setStatus('Geolocalização não suportada neste navegador.', true);
      showGeoGate('Este navegador não oferece geolocalização.');
      return;
    }

    if (!window.isSecureContext) {
      const aviso = 'Atenção: o navegador só libera GPS em HTTPS (ou localhost). Abra o app por um endereço seguro.';
      setStatus(aviso, true);
      showGeoGate(aviso);
      // ainda tenta — em alguns Androids na rede local funciona
    }

    if (watchId !== null) {
      navigator.geolocation.clearWatch(watchId);
      watchId = null;
    }

    setStatus('Solicitando permissão de localização…');
    if (btnGeo) btnGeo.disabled = true;

    const opts = { enableHighAccuracy: true, timeout: 30000, maximumAge: 3000 };

    // 1ª leitura rápida (dispara o prompt no celular)
    navigator.geolocation.getCurrentPosition(onGeoSuccess, onGeoError, opts);

    // continua atualizando enquanto a tela estiver aberta
    watchId = navigator.geolocation.watchPosition(onGeoSuccess, () => {}, opts);
  }

  btnGeo?.addEventListener('click', iniciarGeolocalizacao);
  btnGeoGate?.addEventListener('click', () => {
    localStorage.removeItem('papi_rastro_geo_later');
    iniciarGeolocalizacao();
  });

  function syncPauseButton() {
    if (!btnPause) return;
    btnPause.textContent = compartilhando ? 'Pausar compartilhamento' : 'Retomar compartilhamento';
    btnPause.classList.toggle('is-paused', !compartilhando);
  }

  btnPause?.addEventListener('click', async () => {
    try {
      btnPause.disabled = true;
      const resp = await fetch('api/toggle_compartilhar.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ compartilhando: !compartilhando }),
      });
      const data = await resp.json();
      if (!resp.ok) throw new Error(data.mensagem || 'Falha ao atualizar');
      compartilhando = !!data.compartilhando;
      syncPauseButton();
      setStatus(data.mensagem || (compartilhando ? 'Compartilhamento ativo.' : 'Compartilhamento pausado.'));
      carregarLocalizacoes();
    } catch (err) {
      setStatus(err.message || 'Erro ao pausar/retomar', true);
    } finally {
      btnPause.disabled = false;
    }
  });

  syncPauseButton();

  async function requestWakeLock() {
    if (!('wakeLock' in navigator)) return;
    try {
      if (wakeLock) {
        try { await wakeLock.release(); } catch (_) {}
        wakeLock = null;
      }
      wakeLock = await navigator.wakeLock.request('screen');
      wakeLock.addEventListener('release', () => { wakeLock = null; });
    } catch (_) {
      // navegador pode negar
    }
  }

  async function killSharingNotifications() {
    try {
      if ('serviceWorker' in navigator) {
        const regs = await navigator.serviceWorker.getRegistrations();
        for (const reg of regs) {
          reg.active?.postMessage({ type: 'close-notifications' });
          if (reg.getNotifications) {
            const list = await reg.getNotifications();
            list.forEach((n) => n.close());
          }
        }
      }
      if ('Notification' in window && navigator.serviceWorker) {
        const reg = await navigator.serviceWorker.ready.catch(() => null);
        if (reg?.getNotifications) {
          (await reg.getNotifications()).forEach((n) => n.close());
        }
      }
    } catch (_) {}
  }

  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') {
      killSharingNotifications();
      requestWakeLock();
      carregarLocalizacoes();
      if (watchId === null && compartilhando) {
        if (navigator.permissions?.query) {
          navigator.permissions.query({ name: 'geolocation' }).then((result) => {
            if (result.state === 'granted') iniciarGeolocalizacao();
          }).catch(() => {});
        } else if (geoAtivo) {
          iniciarGeolocalizacao();
        }
      } else if (watchId !== null) {
        requestWakeLock();
      }
    } else if (geoAtivo && compartilhando) {
      setStatus('App minimizado — GPS continua enquanto o sistema permitir. Fechar a aba encerra o mapa ao vivo.');
    }
  });
  async function carregarLocalizacoes() {
    try {
      const params = new URLSearchParams();
      params.set('online_minutos', String(cfg.onlineMinutos || 3));
      const gid = filtroGrupo?.value || grupoFiltro || '';
      if (gid) params.set('grupo_id', gid);

      const resp = await fetch('api/get_localizacoes.php?' + params.toString(), { credentials: 'include' });
      if (resp.status === 401) {
        window.location.href = 'login.php';
        return;
      }
      if (resp.status === 403) {
        setStatus('Servidor bloqueou a leitura do mapa (403). Atualize a página.', true);
        return;
      }
      if (!resp.ok) throw new Error('Falha ao buscar localizações.');
      const data = await resp.json();
      const locs = Array.isArray(data.localizacoes) ? data.localizacoes : [];
      const legendTitle = document.getElementById('legend-title');
      const legendHint = document.getElementById('legend-hint');
      const selectedOpt = filtroGrupo?.selectedOptions?.[0];
      const grupoNome = selectedOpt && filtroGrupo.value
        ? selectedOpt.textContent.replace(/\s+/g, ' ').trim().split('—')[0].trim()
        : null;
      if (legendTitle) {
        legendTitle.textContent = grupoNome
          ? `Ativos em “${grupoNome}”`
          : 'Online agora';
      }
      if (legendHint) {
        legendHint.textContent = grupoNome
          ? 'Toque no nome ou em Mensagem para falar com a pessoa.'
          : 'Selecione um grupo acima para filtrar. Toque para abrir e mandar mensagem.';
      }

      markersLayer.clearLayers();
      markersByUser.clear();
      peopleByUser.clear();
      legendEl.innerHTML = '';

      if (locs.length === 0) {
        legendEl.innerHTML = '<p class="legend-empty">Ninguém ativo neste filtro agora.</p>';
        applyFanOutToMapMarkers();
        return;
      }

      locs.forEach((loc) => {
        const uid = Number(loc.usuario_id);
        const lat = parseFloat(loc.latitude);
        const lon = parseFloat(loc.longitude);
        if (!Number.isFinite(lat) || !Number.isFinite(lon)) return;

        const color = colorFor(uid);
        const label = loc.nome || loc.usuario;
        const isSelf = uid === Number(cfg.usuarioId);
        const person = {
          ...loc,
          latitude: lat,
          longitude: lon,
        };
        peopleByUser.set(uid, person);

        let marker = null;

        // Evita avatar duplicado: o GPS ao vivo já desenha o "eu"
        if (isSelf && selfMarker) {
          selfTrueLatLng = L.latLng(lat, lon);
          selfMarker.setIcon(photoIcon(loc.foto, label, color));
          marker = selfMarker;
        } else {
          marker = L.marker([lat, lon], {
            icon: photoIcon(loc.foto, label, color),
            zIndexOffset: isSelf ? 500 : 0,
          });
          marker._papiTrueLatLng = L.latLng(lat, lon);
          marker.on('click', () => openPersonSheet(person, { focusMessage: !isSelf }));
          markersLayer.addLayer(marker);
          markersByUser.set(uid, marker);
        }

        const row = document.createElement('div');
        row.className = 'legend-row';

        const item = document.createElement('button');
        item.type = 'button';
        item.className = 'legend-item';
        const thumb = photoUrl(loc.foto)
          ? `<img class="legend-foto" src="${escapeHtml(photoUrl(loc.foto))}" alt="">`
          : `<span class="legend-dot" style="background:${color}"></span>`;
        item.innerHTML = `${thumb}<span class="legend-name">${escapeHtml(label)}${isSelf ? ' (você)' : ''}</span>`;
        item.addEventListener('click', () => {
          map.setView(marker.getLatLng(), 15);
          openPersonSheet(person, { focusMessage: !isSelf });
        });
        row.appendChild(item);

        if (!isSelf) {
          const msgBtn = document.createElement('button');
          msgBtn.type = 'button';
          msgBtn.className = 'legend-msg';
          msgBtn.textContent = 'Msg';
          msgBtn.title = 'Enviar mensagem para ' + label;
          msgBtn.addEventListener('click', (ev) => {
            ev.stopPropagation();
            map.setView(marker.getLatLng(), 15);
            openPersonSheet(person, { focusMessage: true });
          });
          row.appendChild(msgBtn);
        }

        legendEl.appendChild(row);
      });

      applyFanOutToMapMarkers();
    } catch (err) {
      console.error(err);
    }
  }

  filtroGrupo?.addEventListener('change', () => {
    grupoFiltro = filtroGrupo.value;
    syncGroupChips();
    carregarLocalizacoes();
  });

  function syncGroupChips() {
    const val = String(filtroGrupo?.value || '');
    document.querySelectorAll('.group-chip').forEach((chip) => {
      chip.classList.toggle('is-active', String(chip.dataset.grupo || '') === val);
    });
  }

  document.getElementById('group-chips')?.addEventListener('click', (ev) => {
    const chip = ev.target.closest('.group-chip');
    if (!chip || !filtroGrupo) return;
    filtroGrupo.value = chip.dataset.grupo || '';
    grupoFiltro = filtroGrupo.value;
    syncGroupChips();
    carregarLocalizacoes();
  });

  syncGroupChips();

  function scrollToSidePanel() {
    const panel = document.getElementById('side-panel');
    if (!panel) return;
    panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
    // Leaflet precisa recalcular tamanho após o layout mudar
    setTimeout(() => {
      try { map.invalidateSize(); } catch (_) {}
    }, 350);
  }

  document.getElementById('map-scroll-cue')?.addEventListener('click', scrollToSidePanel);
  document.getElementById('panel-scroll-cue')?.addEventListener('click', () => {
    document.getElementById('legend')?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  });

  // PWA install — "Agora não" só vale nesta sessão do navegador
  const INSTALL_DISMISS_KEY = 'papi_rastro_install_dismissed';
  try {
    localStorage.removeItem(INSTALL_DISMISS_KEY); // migra: não guardar "nunca mais"
  } catch (_) {}

  function installWasDismissedThisSession() {
    try {
      return sessionStorage.getItem(INSTALL_DISMISS_KEY) === '1';
    } catch (_) {
      return false;
    }
  }

  function dismissInstallThisSession() {
    try {
      sessionStorage.setItem(INSTALL_DISMISS_KEY, '1');
    } catch (_) {}
  }

  const isStandalone = window.matchMedia('(display-mode: standalone)').matches
    || window.navigator.standalone === true;

  window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    deferredPrompt = e;
    if (isStandalone || installWasDismissedThisSession()) return;
    if (installBanner) installBanner.hidden = false;
  });

  btnInstall?.addEventListener('click', async () => {
    if (!deferredPrompt) return;
    deferredPrompt.prompt();
    await deferredPrompt.userChoice;
    deferredPrompt = null;
    if (installBanner) installBanner.hidden = true;
  });

  btnInstallDismiss?.addEventListener('click', () => {
    dismissInstallThisSession();
    if (installBanner) installBanner.hidden = true;
  });

  // iOS tip when not installable via beforeinstallprompt
  const isIos = /iphone|ipad|ipod/i.test(navigator.userAgent);
  if (isIos && !isStandalone && installBanner && !installWasDismissedThisSession()) {
    installBanner.hidden = false;
    const p = installBanner.querySelector('p');
    if (p) {
      p.textContent = 'No iPhone: toque em Compartilhar e depois em “Adicionar à Tela de Início”. Em seguida ative a localização.';
    }
    if (btnInstall) btnInstall.hidden = true;
  }

  if ('serviceWorker' in navigator) {
    // Força SW novo (sem notificação de GPS) e fecha as notificações antigas
    navigator.serviceWorker.getRegistrations().then(async (regs) => {
      for (const reg of regs) {
        try {
          if (reg.getNotifications) {
            (await reg.getNotifications()).forEach((n) => n.close());
          }
          reg.active?.postMessage({ type: 'close-notifications' });
        } catch (_) {}
      }
      await navigator.serviceWorker.register('sw.js?v=20260722o');
      killSharingNotifications();
    }).catch(() => {});
  }

  killSharingNotifications();
  setInterval(killSharingNotifications, 5000);

  carregarLocalizacoes();
  setInterval(carregarLocalizacoes, 10000);

  // Abre o pedido de GPS se ainda não estiver ativo (celular precisa do toque do usuário)
  function maybeShowGeoGate() {
    if (geoAtivo || localStorage.getItem('papi_rastro_geo_later') === '1') {
      hideGeoGate();
      return;
    }
    if (!window.isSecureContext && geoGateSecure) {
      geoGateSecure.hidden = false;
      geoGateSecure.textContent = 'Se o GPS não abrir: use HTTPS ou o IP local no Chrome Android. No iPhone, HTTPS é obrigatório.';
    }
    showGeoGate();
  }

  btnGeoGateLater?.addEventListener('click', () => {
    localStorage.setItem('papi_rastro_geo_later', '1');
    hideGeoGate();
  });

  // Se já tinha permissão, inicia sozinho; senão mostra o card
  if (navigator.permissions?.query) {
    navigator.permissions.query({ name: 'geolocation' }).then((result) => {
      if (result.state === 'granted') {
        iniciarGeolocalizacao();
      } else {
        maybeShowGeoGate();
      }
      result.onchange = () => {
        if (result.state === 'granted') iniciarGeolocalizacao();
      };
    }).catch(() => maybeShowGeoGate());
  } else {
    // iOS Safari: permissions API limitada
    maybeShowGeoGate();
  }
})();
