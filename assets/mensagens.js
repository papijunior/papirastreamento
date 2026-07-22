(() => {
  const layout = document.querySelector('.msg-layout');
  if (!layout) return;

  const me = Number(layout.dataset.me || 0);
  let com = Number(layout.dataset.com || 0);
  const threadsEl = document.getElementById('msg-threads');
  const chatEl = document.getElementById('msg-chat');
  const emptyEl = document.getElementById('msg-empty');
  const listEl = document.getElementById('msg-list');
  const nameEl = document.getElementById('msg-chat-name');
  const form = document.getElementById('msg-compose');
  const paraInput = document.getElementById('msg-para');
  const textoEl = document.getElementById('msg-texto');
  const recBtn = document.getElementById('msg-rec');
  const recStatus = document.getElementById('msg-rec-status');
  const preview = document.getElementById('msg-preview');
  const backBtn = document.getElementById('msg-back');

  let mediaRecorder = null;
  let audioChunks = [];
  let audioBlob = null;

  function escapeHtml(v) {
    return String(v ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function labelOf(row) {
    return row.nome || row.usuario || 'Usuário';
  }

  function setChatVisible(on) {
    layout.classList.toggle('is-chat', on);
    if (chatEl) chatEl.hidden = !on;
    if (emptyEl) emptyEl.hidden = on;
  }

  async function loadThreads() {
    const resp = await fetch('api/mensagens_listar.php', { credentials: 'same-origin' });
    if (resp.status === 401) {
      location.href = 'login.php';
      return;
    }
    const data = await resp.json();
    const threads = data.threads || [];
    if (!threadsEl) return;

    if (threads.length === 0) {
      threadsEl.innerHTML = '<p class="hint">Nenhuma conversa ainda.</p>';
      return;
    }

    threadsEl.innerHTML = threads.map((t) => {
      const id = Number(t.outro_id);
      const nome = escapeHtml(labelOf(t));
      const previewTxt = t.tipo === 'audio' ? 'Áudio' : escapeHtml(String(t.corpo || '').slice(0, 60));
      const badge = Number(t.nao_lidas) > 0
        ? `<span class="msg-badge">${Number(t.nao_lidas)}</span>`
        : '';
      const foto = t.foto
        ? `<img class="msg-thread-foto" src="${escapeHtml(t.foto)}" alt="">`
        : `<span class="msg-thread-fallback">${escapeHtml((nome.charAt(0) || '?').toUpperCase())}</span>`;
      return `<button type="button" class="msg-thread${id === com ? ' is-active' : ''}" data-id="${id}">
        ${foto}
        <span class="msg-thread-body"><strong>${nome}</strong><span>${previewTxt}</span></span>
        ${badge}
      </button>`;
    }).join('');

    threadsEl.querySelectorAll('.msg-thread').forEach((btn) => {
      btn.addEventListener('click', () => openThread(Number(btn.dataset.id), btn.querySelector('strong')?.textContent || 'Conversa'));
    });
  }

  function renderMessages(mensagens) {
    if (!listEl) return;
    listEl.innerHTML = mensagens.map((m) => {
      const mine = Number(m.de_usuario_id) === me;
      const when = m.criado_em ? new Date(m.criado_em).toLocaleString('pt-BR') : '';
      let body = '';
      if (m.tipo === 'audio' && m.audio_path) {
        body = `<audio controls src="${escapeHtml(m.audio_path)}"></audio>`;
        if (m.corpo && m.corpo !== '[Áudio]') {
          body += `<div>${escapeHtml(m.corpo)}</div>`;
        }
      } else {
        body = `<div>${escapeHtml(m.corpo || '')}</div>`;
      }
      return `<article class="msg-bubble${mine ? ' is-mine' : ''}">${body}<time>${escapeHtml(when)}</time></article>`;
    }).join('');
    listEl.scrollTop = listEl.scrollHeight;
  }

  async function openThread(id, nome) {
    com = id;
    layout.dataset.com = String(id);
    if (paraInput) paraInput.value = String(id);
    if (nameEl) nameEl.textContent = nome;
    history.replaceState(null, '', 'mensagens.php?com=' + id);
    setChatVisible(true);

    const resp = await fetch('api/mensagens_listar.php?com=' + id, { credentials: 'same-origin' });
    const data = await resp.json();
    renderMessages(data.mensagens || []);
    loadThreads();
    window.dispatchEvent(new CustomEvent('papi:mensagens-refresh'));
  }

  backBtn?.addEventListener('click', () => {
    com = 0;
    layout.dataset.com = '0';
    history.replaceState(null, '', 'mensagens.php');
    setChatVisible(false);
  });

  async function startRecording() {
    if (!navigator.mediaDevices?.getUserMedia) {
      alert('Gravação de áudio não suportada neste navegador.');
      return;
    }
    const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
    audioChunks = [];
    audioBlob = null;
    const mime = MediaRecorder.isTypeSupported('audio/webm;codecs=opus')
      ? 'audio/webm;codecs=opus'
      : 'audio/webm';
    mediaRecorder = new MediaRecorder(stream);
    mediaRecorder.ondataavailable = (ev) => {
      if (ev.data.size > 0) audioChunks.push(ev.data);
    };
    mediaRecorder.onstop = () => {
      stream.getTracks().forEach((t) => t.stop());
      audioBlob = new Blob(audioChunks, { type: mime });
      if (preview) {
        preview.src = URL.createObjectURL(audioBlob);
        preview.hidden = false;
      }
      if (recStatus) {
        recStatus.hidden = false;
        recStatus.textContent = 'Áudio pronto. Toque em Enviar.';
      }
    };
    mediaRecorder.start();
    recBtn?.classList.add('is-recording');
    recBtn?.setAttribute('aria-pressed', 'true');
    if (recStatus) {
      recStatus.hidden = false;
      recStatus.textContent = 'Gravando… toque em Áudio novamente para parar.';
    }
  }

  function stopRecording() {
    if (mediaRecorder && mediaRecorder.state !== 'inactive') {
      mediaRecorder.stop();
    }
    recBtn?.classList.remove('is-recording');
    recBtn?.setAttribute('aria-pressed', 'false');
  }

  recBtn?.addEventListener('click', async () => {
    try {
      if (mediaRecorder && mediaRecorder.state === 'recording') {
        stopRecording();
      } else {
        await startRecording();
      }
    } catch (err) {
      alert(err.message || 'Não foi possível gravar áudio.');
    }
  });

  form?.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const para = Number(paraInput?.value || 0);
    if (!para) {
      alert('Selecione uma conversa.');
      return;
    }
    const texto = String(textoEl?.value || '').trim();
    if (!texto && !audioBlob) {
      alert('Digite um texto ou grave um áudio.');
      return;
    }

    const fd = new FormData();
    fd.append('para_usuario_id', String(para));
    if (texto) fd.append('texto', texto);
    if (audioBlob) {
      fd.append('audio', audioBlob, 'audio.webm');
    }

    const btn = form.querySelector('button[type="submit"]');
    if (btn) btn.disabled = true;
    try {
      const resp = await fetch('api/mensagens_enviar.php', {
        method: 'POST',
        body: fd,
        credentials: 'same-origin',
      });
      const data = await resp.json();
      if (!resp.ok) throw new Error(data.mensagem || 'Falha ao enviar');

      if (textoEl) textoEl.value = '';
      audioBlob = null;
      if (preview) {
        preview.hidden = true;
        preview.removeAttribute('src');
      }
      if (recStatus) recStatus.hidden = true;

      const openUrl = data.whatsapp?.abrir_url;
      if (openUrl && !data.whatsapp?.enviado_api) {
        window.open(openUrl, '_blank', 'noopener');
      }

      await openThread(para, nameEl?.textContent || 'Conversa');
    } catch (err) {
      alert(err.message || 'Erro ao enviar');
    } finally {
      if (btn) btn.disabled = false;
    }
  });

  if (com > 0) {
    setChatVisible(true);
    openThread(com, nameEl?.textContent || 'Conversa');
  } else {
    setChatVisible(false);
    loadThreads();
  }

  setInterval(() => {
    loadThreads();
    if (com > 0) {
      fetch('api/mensagens_listar.php?com=' + com, { credentials: 'same-origin' })
        .then((r) => r.json())
        .then((data) => renderMessages(data.mensagens || []))
        .catch(() => {});
    }
  }, 12000);
})();
