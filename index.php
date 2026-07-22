<?php

declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

Auth::requireLogin();

$user = Auth::user();
$foto = null;
$compartilhando = true;
$tipo = $user['tipo'];
$participaEscala = false;

try {
    $db = (new Database())->getConnection();
    $repo = new UserRepository($db);
    $repo->ensureSchema();
    $full = $repo->find($user['id']);
    if ($full) {
        $foto = $full['foto'] ?? null;
        $compartilhando = !empty($full['compartilhando']);
        $participaEscala = !empty($full['participa_escala']);
        $tipo = UserTypes::normalize((string) $full['tipo']);
        Auth::refreshFromUser($full);
        $user = Auth::user();
    }
    $gruposMapa = (new GroupRepository($db))->listGroupsForViewer($user['id'], Auth::podeVerTodosDaEmpresa());
} catch (Throwable $e) {
    // ignore soft failures
}

$gruposMapa = $gruposMapa ?? [];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#1a4f9c">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-title" content="PAPI Rastro">
  <title>PAPI Rastro — Mapa</title>
  <link rel="manifest" href="manifest.webmanifest">
  <link rel="icon" href="assets/favicon.ico" sizes="any">
  <link rel="icon" type="image/png" sizes="32x32" href="assets/favicon-32.png">
  <link rel="apple-touch-icon" href="assets/apple-touch-icon.png">
  <link href="https://fonts.googleapis.com/css2?family=Source+Sans+3:wght@400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
  <link rel="stylesheet" href="assets/app.css?v=20260722o">
</head>
<body>
  <?php render_nav('mapa', $user); ?>

  <div id="install-banner" class="install-banner" hidden>
    <div>
      <strong>Instale no celular</strong>
      <p>Com o app <strong>aberto</strong> (navegador ou instalado), a localização continua sendo enviada — inclusive se minimizar. Fechar o app/aba encerra o compartilhamento ao vivo. Instalar ajuda no Android a manter o GPS ativo em segundo plano.</p>
    </div>
    <div class="install-actions">
      <button type="button" id="btn-install">Instalar</button>
      <button type="button" id="btn-install-dismiss" class="btn-ghost">Agora não</button>
    </div>
  </div>

  <?php if (count($gruposMapa) > 0): ?>
  <div class="group-filter-bar" id="group-filter-bar" aria-label="Escolher grupo">
    <label class="filter-group filter-group-bar">
      <span>Ver grupo</span>
      <select id="filtro-grupo">
        <option value="" selected>Todos que eu posso ver</option>
        <?php foreach ($gruposMapa as $g): ?>
          <option value="<?= (int) $g['id'] ?>"
            data-modo="<?= e((string) ($g['modo'] ?? GroupModes::SOCIAL)) ?>">
            <?= e((string) $g['nome']) ?>
            — <?= (string) ($g['modo'] ?? '') === GroupModes::RONDA ? 'ronda' : 'social' ?>
            (<?= (int) ($g['membros'] ?? 0) ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </label>
    <div class="group-chips" id="group-chips" role="list">
      <button type="button" class="group-chip is-active" data-grupo="" role="listitem">Todos</button>
      <?php foreach ($gruposMapa as $g): ?>
        <button type="button"
          class="group-chip"
          data-grupo="<?= (int) $g['id'] ?>"
          role="listitem">
          <?= e((string) $g['nome']) ?>
        </button>
      <?php endforeach; ?>
    </div>
  </div>
  <?php else: ?>
  <select id="filtro-grupo" hidden>
    <option value="" selected>Todos que eu posso ver</option>
  </select>
  <?php endif; ?>

  <div id="geo-gate" class="geo-gate" role="dialog" aria-modal="true" aria-labelledby="geo-gate-title">
    <div class="geo-gate-card">
      <h2 id="geo-gate-title">Compartilhar localização</h2>
      <p id="geo-gate-text">
        Para você aparecer no mapa (e os outros te verem), o celular precisa liberar o GPS neste site.
        Toque no botão abaixo e aceite a permissão quando o sistema perguntar.
      </p>
      <p id="geo-gate-secure" class="hint" hidden></p>
      <button type="button" id="btn-geo-gate" class="cta-geo">Permitir minha localização</button>
      <button type="button" id="btn-geo-gate-later" class="btn-ghost-dark">Agora não</button>
    </div>
  </div>

  <main class="map-layout">
    <aside class="side-panel" id="side-panel" aria-label="Equipe no mapa">
      <button type="button" class="panel-scroll-cue" id="panel-scroll-cue" aria-controls="side-panel">
        <span class="panel-scroll-cue-handle" aria-hidden="true"></span>
        <span class="panel-scroll-cue-text">Deslize para ver a equipe, enviar msg e pausar localização</span>
        <span class="panel-scroll-cue-arrow" aria-hidden="true">↓</span>
      </button>

      <h1>Onde está a equipe</h1>
      <p class="lede hide-on-mobile">
        Em grupos <strong>sociais</strong> aparecem quem está online.
        Em grupos <strong>ronda</strong>, só quem está em escala agora.
      </p>

      <div id="legend" class="legend">
        <h2 id="legend-title">Online no grupo</h2>
        <p class="legend-hint" id="legend-hint">Quem está ativo agora no filtro selecionado.</p>
        <div id="legend-content"></div>
      </div>

      <p id="geo-status" class="status" role="status">Toque em “Ativar localização” para começar. Logado como <strong><?= e((string) (($user['nome'] ?? null) ?: $user['usuario'])) ?></strong>.</p>
      <button type="button" id="btn-geo" class="cta-geo">Ativar localização</button>
      <button type="button" id="btn-pause" class="btn-pause <?= $compartilhando ? '' : 'is-paused' ?>">
        <?= $compartilhando ? 'Pausar compartilhamento' : 'Retomar compartilhamento' ?>
      </button>
      <a class="link-hist" href="historico.php">Ver histórico diário</a>
    </aside>

    <div class="map-column">
      <div id="map" role="application" aria-label="Mapa de localização"></div>
      <button type="button" class="map-scroll-cue" id="map-scroll-cue" aria-controls="side-panel">
        <span class="map-scroll-cue-handle" aria-hidden="true"></span>
        <span class="map-scroll-cue-text">Role para baixo · equipe, mensagens e pausar GPS</span>
        <span class="map-scroll-cue-arrow" aria-hidden="true">↓</span>
      </button>
    </div>
  </main>

  <div id="person-sheet" class="sheet" hidden>
    <div class="sheet-card" role="dialog" aria-modal="true" aria-labelledby="sheet-title">
      <button type="button" class="sheet-close" id="sheet-close" aria-label="Fechar">×</button>
      <div class="sheet-head">
        <img id="sheet-foto" class="sheet-foto" alt="" hidden>
        <div id="sheet-foto-fallback" class="sheet-foto sheet-foto-fallback" hidden aria-hidden="true"></div>
        <div>
          <h2 id="sheet-title">Pessoa</h2>
          <p id="sheet-quando" class="sheet-meta"></p>
        </div>
      </div>
      <p id="sheet-endereco" class="sheet-endereco"></p>
      <div class="sheet-actions">
        <a id="sheet-maps" class="btn-outline" href="#" target="_blank" rel="noopener noreferrer">Abrir no Maps</a>
      </div>
      <div id="sheet-msg-block" class="sheet-msg-block">
        <h3 id="sheet-msg-heading" class="sheet-msg-heading">Enviar mensagem</h3>
        <form id="sheet-msg-form" class="sheet-msg-form">
          <input type="hidden" name="para_usuario_id" id="sheet-para-id" value="">
          <label class="sheet-msg-label">
            <span class="sr-only">Texto da mensagem</span>
            <textarea id="sheet-texto" name="texto" rows="2" placeholder="Escreva uma mensagem…"></textarea>
          </label>
          <div class="sheet-msg-actions">
            <button type="button" class="btn-outline" id="sheet-rec" aria-pressed="false">Áudio</button>
            <button type="submit" class="cta-msg" id="sheet-send">Enviar mensagem</button>
          </div>
          <p id="sheet-msg-status" class="hint" hidden></p>
          <audio id="sheet-audio-preview" controls hidden></audio>
        </form>
      </div>
      <p id="sheet-msg-hint" class="hint" hidden>Este é você — escolha outra pessoa no mapa para enviar mensagem.</p>
    </div>
  </div>

  <script>
    window.PAPI_RASTRO = {
      usuarioId: <?= (int) $user['id'] ?>,
      usuario: <?= json_encode($user['usuario'], JSON_UNESCAPED_UNICODE) ?>,
      nome: <?= json_encode((string) (($user['nome'] ?? null) ?: $user['usuario']), JSON_UNESCAPED_UNICODE) ?>,
      foto: <?= json_encode($foto, JSON_UNESCAPED_UNICODE) ?>,
      tipo: <?= json_encode($tipo, JSON_UNESCAPED_UNICODE) ?>,
      podeUsuarios: <?= Auth::podeUsuarios() ? 'true' : 'false' ?>,
      compartilhando: <?= $compartilhando ? 'true' : 'false' ?>,
      participaEscala: <?= $participaEscala ? 'true' : 'false' ?>,
      grupoIdInicial: null,
      onlineMinutos: 3
    };
  </script>
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
  <script src="assets/app.js?v=20260722o"></script>
</body>
</html>
