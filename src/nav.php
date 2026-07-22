<?php

declare(strict_types=1);

/**
 * Barra de navegação compartilhada.
 */
function render_nav(string $ativo, array $user): void
{
    $tipo = UserTypes::normalize((string) ($user['tipo'] ?? UserTypes::USUARIO));
    $operacional = UserTypes::podeGerenciarOperacional($tipo);
    $gestor = $tipo === UserTypes::GESTOR;

    $links = [
        'mapa' => ['href' => 'index.php', 'label' => 'Mapa'],
        'mensagens' => ['href' => 'mensagens.php', 'label' => 'Mensagens'],
        'historico' => ['href' => 'historico.php', 'label' => 'Histórico'],
        'senha' => ['href' => 'minha_senha.php', 'label' => 'Senha'],
    ];
    if ($operacional) {
        $links['escalas'] = ['href' => 'escalas.php', 'label' => 'Escalas'];
        $links['usuarios'] = ['href' => 'usuarios.php', 'label' => 'Usuários'];
        $links['grupos'] = ['href' => 'grupos.php', 'label' => 'Grupos'];
    }
    if ($gestor) {
        $links['regioes'] = ['href' => 'regioes.php', 'label' => 'Regiões'];
        $links['empresas'] = ['href' => 'empresas.php', 'label' => 'Empresas'];
        $links['pagamentos'] = ['href' => 'pagamentos.php', 'label' => 'Pagamentos'];
    }

    $naoLidas = 0;
    try {
        $db = (new Database())->getConnection();
        $msgRepo = new MessageRepository($db);
        $msgRepo->ensureSchema();
        $naoLidas = $msgRepo->countUnread((int) ($user['id'] ?? 0));
    } catch (Throwable $e) {
        $naoLidas = 0;
    }
    ?>
  <header class="topbar">
    <div class="topbar-brand">
      <img src="assets/papilab-logo.png" alt="Papi Lab" class="papilab">
      <span>PAPI Rastro</span>
    </div>
    <nav class="topbar-nav">
      <?php foreach ($links as $key => $link): ?>
        <a href="<?= e($link['href']) ?>" class="<?= $ativo === $key ? 'is-active' : '' ?>">
          <?= e($link['label']) ?>
          <?php if ($key === 'mensagens'): ?>
            <span class="nav-badge" data-msg-badge <?= $naoLidas > 0 ? '' : 'hidden' ?>><?= $naoLidas > 0 ? (int) $naoLidas : '' ?></span>
          <?php endif; ?>
        </a>
      <?php endforeach; ?>
      <span class="user-chip"><?= e(($user['nome'] ?? null) ?: ($user['usuario'] ?? '')) ?></span>
      <a class="btn-outline" href="logout.php">Sair</a>
    </nav>
  </header>
  <script src="assets/notify.js" defer></script>
    <?php
}
