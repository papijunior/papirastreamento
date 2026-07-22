<?php

declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

Auth::requireLogin();

$user = Auth::user();
$mensagem = null;
$erro = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $atual = (string) ($_POST['senha_atual'] ?? '');
    $nova = (string) ($_POST['senha_nova'] ?? '');
    $confirma = (string) ($_POST['senha_confirma'] ?? '');

    try {
        if ($nova !== $confirma) {
            throw new RuntimeException('A confirmação não confere com a nova senha.');
        }
        $db = (new Database())->getConnection();
        $repo = new UserRepository($db);
        $repo->ensureSchema();
        $repo->changeOwnPassword((int) $user['id'], $atual, $nova);
        $mensagem = 'Senha alterada com sucesso.';
    } catch (Throwable $e) {
        $erro = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>PAPI Rastro — Minha senha</title>
  <link rel="icon" href="assets/favicon.ico" sizes="any">
  <link rel="icon" type="image/png" sizes="32x32" href="assets/favicon-32.png">
  <link rel="apple-touch-icon" href="assets/apple-touch-icon.png">
  <link href="https://fonts.googleapis.com/css2?family=Source+Sans+3:wght@400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/admin.css">
</head>
<body>
  <?php render_nav('senha', $user); ?>

  <main class="admin">
    <h1>Minha senha</h1>
    <p class="lede">Altere a senha da sua conta <strong><?= e((string) $user['usuario']) ?></strong>.</p>

    <?php if ($mensagem): ?>
      <div class="alert ok"><?= e($mensagem) ?></div>
    <?php endif; ?>
    <?php if ($erro): ?>
      <div class="alert err"><?= e($erro) ?></div>
    <?php endif; ?>

    <section class="panel" style="max-width: 420px">
      <form method="post" class="form" autocomplete="off">
        <label>
          Senha atual
          <input type="password" name="senha_atual" required autocomplete="current-password">
        </label>
        <label>
          Nova senha
          <input type="password" name="senha_nova" required minlength="6" autocomplete="new-password">
        </label>
        <label>
          Confirmar nova senha
          <input type="password" name="senha_confirma" required minlength="6" autocomplete="new-password">
        </label>
        <p class="hint">Mínimo de 6 caracteres.</p>
        <button type="submit">Salvar nova senha</button>
      </form>
    </section>
  </main>
</body>
</html>
