<?php

declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

Auth::requireGestor();

$db = (new Database())->getConnection();
$userRepo = new UserRepository($db);
$userRepo->ensureSchema();
$empresaRepo = new EmpresaRepository($db);
$user = Auth::user();

$mensagem = null;
$erro = null;
$editando = null;
$liberacoes = EmpresaRepository::liberacoesAcesso();

if (isset($_GET['editar'])) {
    $editando = $empresaRepo->find((int) $_GET['editar']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = (string) ($_POST['acao'] ?? '');
    try {
        if ($acao === 'salvar') {
            $id = (int) ($_POST['id'] ?? 0);
            $nome = trim((string) ($_POST['nome'] ?? ''));
            $cnpj = trim((string) ($_POST['cnpj'] ?? ''));
            $ativo = isset($_POST['ativo']);
            if ($id > 0) {
                $empresaRepo->update($id, $nome, $cnpj, $ativo);
            } else {
                $empresaRepo->create($nome, $cnpj, $ativo);
            }
            header('Location: empresas.php?ok=1');
            exit;
        }

        if ($acao === 'liberar') {
            $id = (int) ($_POST['id'] ?? 0);
            $dias = (int) ($_POST['dias'] ?? 0);
            $permitidos = array_column($liberacoes, 'dias');
            if ($id <= 0) {
                throw new RuntimeException('Empresa inválida.');
            }
            if (!in_array($dias, $permitidos, true)) {
                throw new RuntimeException('Selecione o período de liberação.');
            }
            $label = 'Liberação sem crédito';
            foreach ($liberacoes as $lib) {
                if ((int) $lib['dias'] === $dias) {
                    $label = 'Liberação sem crédito — ' . $lib['label'];
                    break;
                }
            }
            $empresaRepo->liberarAcesso($id, $dias, $user['id'], $label);
            header('Location: empresas.php?ok=liberado&dias=' . $dias);
            exit;
        }
    } catch (Throwable $e) {
        $erro = $e->getMessage();
        if ($acao === 'salvar') {
            $editando = [
                'id' => (int) ($_POST['id'] ?? 0),
                'nome' => (string) ($_POST['nome'] ?? ''),
                'cnpj' => (string) ($_POST['cnpj'] ?? ''),
                'ativo' => isset($_POST['ativo']) ? 1 : 0,
            ];
        }
    }
}

if (isset($_GET['ok'])) {
    if ((string) $_GET['ok'] === 'liberado') {
        $dias = (int) ($_GET['dias'] ?? 0);
        $mensagem = 'Acesso liberado sem crédito'
            . ($dias > 0 ? " por {$dias} dia(s)." : '.');
    } else {
        $mensagem = 'Operação concluída.';
    }
}

$empresas = $empresaRepo->listAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>PAPI Rastro — Empresas</title>
  <link rel="icon" href="assets/favicon.ico" sizes="any">
  <link rel="icon" type="image/png" sizes="32x32" href="assets/favicon-32.png">
  <link rel="apple-touch-icon" href="assets/apple-touch-icon.png">
  <link href="https://fonts.googleapis.com/css2?family=Source+Sans+3:wght@400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/admin.css">
</head>
<body>
  <?php render_nav('empresas', $user); ?>
  <main class="admin">
    <h1>Empresas</h1>
    <p class="lede">Cadastre empresas e, se precisar, libere acesso sem crédito (15 dias, 1 mês, 6 meses ou 1 ano).</p>

    <?php if ($mensagem): ?><div class="alert ok"><?= e($mensagem) ?></div><?php endif; ?>
    <?php if ($erro): ?><div class="alert err"><?= e($erro) ?></div><?php endif; ?>

    <section class="grid">
      <div class="panel">
        <h2><?= $editando && (int) ($editando['id'] ?? 0) > 0 ? 'Editar empresa' : 'Nova empresa' ?></h2>
        <form method="post" class="form">
          <input type="hidden" name="acao" value="salvar">
          <input type="hidden" name="id" value="<?= (int) ($editando['id'] ?? 0) ?>">
          <label>Nome<input type="text" name="nome" required value="<?= e((string) ($editando['nome'] ?? '')) ?>"></label>
          <label>CNPJ<input type="text" name="cnpj" value="<?= e((string) ($editando['cnpj'] ?? '')) ?>"></label>
          <label class="check"><input type="checkbox" name="ativo" <?= !isset($editando['ativo']) || !empty($editando['ativo']) ? 'checked' : '' ?>> Ativa</label>
          <div class="actions">
            <button type="submit">Salvar</button>
            <?php if ($editando && (int) ($editando['id'] ?? 0) > 0): ?>
              <a class="btn-outline" href="empresas.php">Cancelar</a>
            <?php endif; ?>
          </div>
        </form>

        <?php if ($editando && (int) ($editando['id'] ?? 0) > 0): ?>
          <hr class="sep">
          <h2>Liberar acesso sem crédito</h2>
          <p class="hint">Concede validade mesmo sem pagamento. O período soma a partir de hoje ou do vencimento atual.</p>
          <form method="post" class="form form-inline"
                onsubmit="return confirm('Liberar acesso sem crédito para esta empresa?');">
            <input type="hidden" name="acao" value="liberar">
            <input type="hidden" name="id" value="<?= (int) $editando['id'] ?>">
            <label>
              Período
              <select name="dias" required>
                <?php foreach ($liberacoes as $lib): ?>
                  <option value="<?= (int) $lib['dias'] ?>"><?= e($lib['label']) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
            <div class="actions">
              <button type="submit" class="btn-liberar">Liberar acesso</button>
            </div>
          </form>
        <?php endif; ?>
      </div>
      <div class="panel">
        <h2>Empresas cadastradas</h2>
        <div class="table-wrap">
          <table>
            <thead>
              <tr><th>Nome</th><th>CNPJ</th><th>Válido até</th><th>Usuários</th><th>Status</th><th>Liberar</th><th></th></tr>
            </thead>
            <tbody>
              <?php foreach ($empresas as $emp): ?>
                <?php
                  $valido = $emp['valido_ate'] ?? null;
                  $okCredito = $valido && (string) $valido >= date('Y-m-d');
                ?>
                <tr>
                  <td><?= e((string) $emp['nome']) ?></td>
                  <td><?= e((string) ($emp['cnpj'] ?? '—')) ?></td>
                  <td><?= $valido ? e(date('d/m/Y', strtotime((string) $valido))) : 'Sem crédito' ?>
                    <?= $okCredito ? '' : ' ⚠' ?>
                  </td>
                  <td><?= (int) $emp['usuarios'] ?></td>
                  <td><?= !empty($emp['ativo']) ? 'Ativa' : 'Inativa' ?></td>
                  <td>
                    <form method="post" class="liberar-inline"
                          onsubmit="return confirm('Liberar acesso sem crédito?');">
                      <input type="hidden" name="acao" value="liberar">
                      <input type="hidden" name="id" value="<?= (int) $emp['id'] ?>">
                      <select name="dias" required aria-label="Período de liberação">
                        <?php foreach ($liberacoes as $lib): ?>
                          <option value="<?= (int) $lib['dias'] ?>"><?= e($lib['label']) ?></option>
                        <?php endforeach; ?>
                      </select>
                      <button type="submit" class="btn-liberar-sm">Liberar</button>
                    </form>
                  </td>
                  <td class="row-actions">
                    <a href="empresas.php?editar=<?= (int) $emp['id'] ?>">Editar</a>
                    <a href="pagamentos.php?empresa_id=<?= (int) $emp['id'] ?>">Créditos</a>
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
