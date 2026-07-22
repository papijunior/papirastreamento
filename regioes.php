<?php

declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

Auth::requireGestor();

$db = (new Database())->getConnection();
$userRepo = new UserRepository($db);
$userRepo->ensureSchema();
$regiaoRepo = new RegiaoRepository($db);

$user = Auth::user();
$empresaId = Auth::empresaId();
if ($empresaId === null) {
    $empresas = (new EmpresaRepository($db))->listAll();
    $empresaId = isset($empresas[0]['id']) ? (int) $empresas[0]['id'] : null;
}

$mensagem = null;
$erro = null;
$editando = null;

if (isset($_GET['editar'])) {
    $editando = $regiaoRepo->find((int) $_GET['editar']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = (string) ($_POST['acao'] ?? '');
    try {
        if ($acao === 'salvar') {
            if ($empresaId === null) {
                throw new RuntimeException('Cadastre uma empresa antes de criar regiões.');
            }
            $id = (int) ($_POST['id'] ?? 0);
            $nome = trim((string) ($_POST['nome'] ?? ''));
            $bairro = trim((string) ($_POST['bairro'] ?? ''));
            $rua = trim((string) ($_POST['rua'] ?? ''));
            $complemento = trim((string) ($_POST['complemento'] ?? ''));
            $cidade = trim((string) ($_POST['cidade'] ?? ''));
            $ativo = isset($_POST['ativo']);

            if ($id > 0) {
                $regiaoRepo->update($id, $nome, $bairro, $rua, $complemento, $cidade, $ativo);
            } else {
                $regiaoRepo->create($empresaId, $nome, $bairro, $rua, $complemento, $cidade, $ativo);
            }
            header('Location: regioes.php?ok=1');
            exit;
        }
        if ($acao === 'excluir') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
                $regiaoRepo->delete($id);
            }
            header('Location: regioes.php?ok=1');
            exit;
        }
    } catch (Throwable $e) {
        $erro = $e->getMessage();
        $editando = [
            'id' => (int) ($_POST['id'] ?? 0),
            'nome' => (string) ($_POST['nome'] ?? ''),
            'bairro' => (string) ($_POST['bairro'] ?? ''),
            'rua' => (string) ($_POST['rua'] ?? ''),
            'complemento' => (string) ($_POST['complemento'] ?? ''),
            'cidade' => (string) ($_POST['cidade'] ?? ''),
            'ativo' => isset($_POST['ativo']) ? 1 : 0,
        ];
    }
}

if (isset($_GET['ok'])) {
    $mensagem = 'Operação concluída.';
}

$regioes = $regiaoRepo->listByEmpresa($empresaId);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>PAPI Rastro — Regiões</title>
  <link rel="icon" href="assets/favicon.ico" sizes="any">
  <link rel="icon" type="image/png" sizes="32x32" href="assets/favicon-32.png">
  <link rel="apple-touch-icon" href="assets/apple-touch-icon.png">
  <link href="https://fonts.googleapis.com/css2?family=Source+Sans+3:wght@400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/admin.css">
</head>
<body>
  <?php render_nav('regioes', $user); ?>
  <main class="admin">
    <h1>Regiões de cobertura</h1>
    <p class="lede">Cadastre bairros, ruas e áreas onde a segurança é alocada. Moradores são vinculados a essas regiões.</p>

    <?php if ($mensagem): ?><div class="alert ok"><?= e($mensagem) ?></div><?php endif; ?>
    <?php if ($erro): ?><div class="alert err"><?= e($erro) ?></div><?php endif; ?>

    <section class="grid">
      <div class="panel">
        <h2><?= $editando && (int) ($editando['id'] ?? 0) > 0 ? 'Editar região' : 'Nova região' ?></h2>
        <form method="post" class="form">
          <input type="hidden" name="acao" value="salvar">
          <input type="hidden" name="id" value="<?= (int) ($editando['id'] ?? 0) ?>">
          <label>Nome da região<input type="text" name="nome" required value="<?= e((string) ($editando['nome'] ?? '')) ?>" placeholder="ex.: Condomínio Sol"></label>
          <label>Bairro<input type="text" name="bairro" value="<?= e((string) ($editando['bairro'] ?? '')) ?>"></label>
          <label>Rua<input type="text" name="rua" value="<?= e((string) ($editando['rua'] ?? '')) ?>"></label>
          <label>Complemento<input type="text" name="complemento" value="<?= e((string) ($editando['complemento'] ?? '')) ?>"></label>
          <label>Cidade<input type="text" name="cidade" value="<?= e((string) ($editando['cidade'] ?? '')) ?>"></label>
          <label class="check"><input type="checkbox" name="ativo" <?= !isset($editando['ativo']) || !empty($editando['ativo']) ? 'checked' : '' ?>> Ativa</label>
          <div class="actions">
            <button type="submit">Salvar</button>
            <?php if ($editando && (int) ($editando['id'] ?? 0) > 0): ?>
              <a class="btn-outline" href="regioes.php">Cancelar</a>
            <?php endif; ?>
          </div>
        </form>
      </div>
      <div class="panel">
        <h2>Regiões cadastradas</h2>
        <div class="table-wrap">
          <table>
            <thead>
              <tr><th>Nome</th><th>Bairro</th><th>Rua</th><th>Status</th><th></th></tr>
            </thead>
            <tbody>
              <?php if ($regioes === []): ?>
                <tr><td colspan="5">Nenhuma região ainda.</td></tr>
              <?php endif; ?>
              <?php foreach ($regioes as $r): ?>
                <tr>
                  <td><?= e((string) $r['nome']) ?></td>
                  <td><?= e((string) ($r['bairro'] ?? '—')) ?></td>
                  <td><?= e((string) ($r['rua'] ?? '—')) ?></td>
                  <td><?= !empty($r['ativo']) ? 'Ativa' : 'Inativa' ?></td>
                  <td class="row-actions">
                    <a href="regioes.php?editar=<?= (int) $r['id'] ?>">Editar</a>
                    <form method="post" onsubmit="return confirm('Excluir região?');">
                      <input type="hidden" name="acao" value="excluir">
                      <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
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
