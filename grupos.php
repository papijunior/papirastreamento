<?php

declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

Auth::requireOperacional();

$db = (new Database())->getConnection();
$userRepo = new UserRepository($db);
$groupRepo = new GroupRepository($db);
$userRepo->ensureSchema();

$mensagem = null;
$erro = null;
$editando = null;
$user = Auth::user();
$souGestor = Auth::isGestor();

if (isset($_GET['editar'])) {
    $gid = (int) $_GET['editar'];
    if ($groupRepo->viewerCanManageGrupo((int) $user['id'], $gid, $souGestor)) {
        $editando = $groupRepo->find($gid);
    } else {
        $erro = 'Você só pode editar grupos aos quais tem acesso.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = (string) ($_POST['acao'] ?? '');

    try {
        if ($acao === 'salvar') {
            $id = (int) ($_POST['id'] ?? 0);
            $nome = trim((string) ($_POST['nome'] ?? ''));
            $modo = (string) ($_POST['modo'] ?? GroupModes::SOCIAL);

            if ($id > 0) {
                if (!$groupRepo->viewerCanManageGrupo((int) $user['id'], $id, $souGestor)) {
                    throw new RuntimeException('Sem permissão para editar este grupo.');
                }
                $groupRepo->update($id, $nome, $modo);
            } else {
                // master/gestor: ao criar, o criador entra no grupo automaticamente
                $groupRepo->create($nome, $modo, (int) $user['id']);
            }

            header('Location: grupos.php?ok=1');
            exit;
        }

        if ($acao === 'excluir') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
                if (!$groupRepo->viewerCanManageGrupo((int) $user['id'], $id, $souGestor)) {
                    throw new RuntimeException('Sem permissão para excluir este grupo.');
                }
                $groupRepo->delete($id);
            }
            header('Location: grupos.php?ok=1');
            exit;
        }
    } catch (Throwable $e) {
        $erro = $e->getMessage();
        $editando = [
            'id' => (int) ($_POST['id'] ?? 0),
            'nome' => (string) ($_POST['nome'] ?? ''),
            'modo' => (string) ($_POST['modo'] ?? GroupModes::SOCIAL),
        ];
    }
}

if (isset($_GET['ok'])) {
    $mensagem = 'Operação concluída.';
}

$grupos = $groupRepo->listGroupsForViewer((int) $user['id'], $souGestor);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>PAPI Rastro — Grupos</title>
  <link rel="icon" href="assets/favicon.ico" sizes="any">
  <link rel="icon" type="image/png" sizes="32x32" href="assets/favicon-32.png">
  <link rel="apple-touch-icon" href="assets/apple-touch-icon.png">
  <link href="https://fonts.googleapis.com/css2?family=Source+Sans+3:wght@400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/admin.css">
</head>
<body>
  <?php render_nav('grupos', $user); ?>

  <main class="admin">
    <h1>Grupos</h1>
    <p class="lede">
      Amigos, família, ronda… Quem está em mais de um grupo escolhe no mapa qual quer ver.
      Grupo <strong>ronda</strong> mostra só quem está em escala no dia/hora atuais.
      <?php if (!$souGestor): ?>
        <br>Como usuário master, você vê e edita apenas os grupos em que participa (novos grupos já te incluem).
      <?php endif; ?>
    </p>

    <?php if ($mensagem): ?>
      <div class="alert ok"><?= e($mensagem) ?></div>
    <?php endif; ?>
    <?php if ($erro): ?>
      <div class="alert err"><?= e($erro) ?></div>
    <?php endif; ?>

    <section class="grid">
      <div class="panel">
        <h2><?= $editando && (int) ($editando['id'] ?? 0) > 0 ? 'Editar grupo' : 'Novo grupo' ?></h2>
        <form method="post" class="form">
          <input type="hidden" name="acao" value="salvar">
          <input type="hidden" name="id" value="<?= (int) ($editando['id'] ?? 0) ?>">
          <label>
            Nome do grupo
            <input type="text" name="nome" required placeholder="ex.: Amigos, Ronda São Dimas"
                   value="<?= e((string) ($editando['nome'] ?? '')) ?>">
          </label>
          <label>
            Modo no mapa
            <select name="modo" required>
              <?php
                $modoAtual = (string) ($editando['modo'] ?? GroupModes::SOCIAL);
                foreach (GroupModes::all() as $m):
              ?>
                <option value="<?= e($m) ?>" <?= $modoAtual === $m ? 'selected' : '' ?>>
                  <?= e(GroupModes::label($m)) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </label>
          <div class="actions">
            <button type="submit"><?= $editando && (int) ($editando['id'] ?? 0) > 0 ? 'Salvar' : 'Criar' ?></button>
            <?php if ($editando && (int) ($editando['id'] ?? 0) > 0): ?>
              <a class="btn-outline" href="grupos.php">Cancelar</a>
            <?php endif; ?>
          </div>
        </form>
      </div>

      <div class="panel">
        <h2>Grupos cadastrados</h2>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Nome</th>
                <th>Modo</th>
                <th>Membros</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php if ($grupos === []): ?>
                <tr><td colspan="4">Nenhum grupo ainda.</td></tr>
              <?php endif; ?>
              <?php foreach ($grupos as $g): ?>
                <tr>
                  <td><?= e((string) $g['nome']) ?></td>
                  <td><?= e(GroupModes::label((string) ($g['modo'] ?? GroupModes::SOCIAL))) ?></td>
                  <td><?= (int) $g['membros'] ?></td>
                  <td class="row-actions">
                    <a href="grupos.php?editar=<?= (int) $g['id'] ?>">Editar</a>
                    <form method="post" onsubmit="return confirm('Excluir este grupo?');">
                      <input type="hidden" name="acao" value="excluir">
                      <input type="hidden" name="id" value="<?= (int) $g['id'] ?>">
                      <button type="submit" class="link-danger">Excluir</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </section>
  </main>
</body>
</html>
