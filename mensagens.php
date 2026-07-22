<?php

declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

Auth::requireLogin();
$user = Auth::user();

$db = (new Database())->getConnection();
$msgs = new MessageRepository($db);
$msgs->ensureSchema();
$users = new UserRepository($db);
$users->ensureSchema();

$com = isset($_GET['com']) ? (int) $_GET['com'] : 0;
$outro = $com > 0 ? $users->find($com) : null;
$naoLidas = $msgs->countUnread((int) $user['id']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Mensagens — PAPI Rastro</title>
  <link rel="stylesheet" href="assets/admin.css">
  <link rel="stylesheet" href="assets/mensagens.css">
  <link rel="icon" href="assets/favicon.ico">
</head>
<body class="admin-body">
  <?php render_nav('mensagens', $user); ?>
  <main class="admin-main mensagens-page">
    <h1>Mensagens</h1>
    <p class="lead">Texto e áudio ficam no app. Com WhatsApp API configurada, também vão para o Zap; ao ler aqui, mensagens que chegaram do Zap são marcadas como lidas lá.</p>

    <div class="msg-layout" data-me="<?= (int) $user['id'] ?>" data-com="<?= $com ?>">
      <aside class="msg-threads" id="msg-threads" aria-label="Conversas">
        <p class="hint">Carregando conversas…</p>
      </aside>
      <section class="msg-chat" id="msg-chat" <?= $com > 0 ? '' : 'hidden' ?>>
        <header class="msg-chat-head">
          <button type="button" class="btn-outline msg-back" id="msg-back">← Conversas</button>
          <div>
            <strong id="msg-chat-name"><?= $outro ? e((string) (($outro['nome'] ?? null) ?: $outro['usuario'])) : 'Conversa' ?></strong>
            <p class="hint" id="msg-chat-meta"></p>
          </div>
        </header>
        <div class="msg-list" id="msg-list"></div>
        <form class="msg-compose" id="msg-compose" enctype="multipart/form-data">
          <input type="hidden" name="para_usuario_id" id="msg-para" value="<?= $com > 0 ? $com : '' ?>">
          <label class="msg-text-wrap">
            <span class="sr-only">Mensagem</span>
            <textarea name="texto" id="msg-texto" rows="2" placeholder="Escreva uma mensagem…"></textarea>
          </label>
          <div class="msg-compose-actions">
            <button type="button" class="btn-outline" id="msg-rec" aria-pressed="false">Áudio</button>
            <button type="submit" class="cta-msg">Enviar</button>
          </div>
          <p class="hint" id="msg-rec-status" hidden></p>
          <audio id="msg-preview" controls hidden></audio>
        </form>
      </section>
      <p class="hint msg-empty" id="msg-empty" <?= $com > 0 ? 'hidden' : '' ?>>
        <?= $naoLidas > 0 ? "Você tem {$naoLidas} não lida(s). Abra uma conversa." : 'Nenhuma conversa ainda. No mapa, toque na foto de alguém e envie uma mensagem.' ?>
      </p>
    </div>
  </main>
  <script src="assets/mensagens.js"></script>
</body>
</html>
