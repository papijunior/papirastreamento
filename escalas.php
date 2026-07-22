<?php

declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

Auth::requireOperacional();

$db = (new Database())->getConnection();
$userRepo = new UserRepository($db);
$userRepo->ensureSchema();
$escalaRepo = new EscalaRepository($db);
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
    $editando = $escalaRepo->find((int) $_GET['editar']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = (string) ($_POST['acao'] ?? '');
    try {
        if ($acao === 'salvar') {
            if ($empresaId === null) {
                throw new RuntimeException('Cadastre uma empresa antes.');
            }
            $id = (int) ($_POST['id'] ?? 0);
            $usuarioId = (int) ($_POST['usuario_id'] ?? 0);
            $regiaoId = (int) ($_POST['regiao_id'] ?? 0);
            $data = (string) ($_POST['data'] ?? '');
            $horaInicio = (string) ($_POST['hora_inicio'] ?? '');
            $horaFim = (string) ($_POST['hora_fim'] ?? '');
            $ativo = isset($_POST['ativo']);

            if ($usuarioId <= 0 || $regiaoId <= 0) {
                throw new RuntimeException('Selecione funcionário e região.');
            }
            if (!$userRepo->canViewUser((int) $user['id'], Auth::isGestor(), $usuarioId)) {
                throw new RuntimeException('Usuário fora do seu alcance.');
            }
            $alvo = $userRepo->find($usuarioId);
            if ($alvo === null || empty($alvo['participa_escala'])) {
                throw new RuntimeException('Só usuários com “Participa de escala” podem ser escalados. Marque isso no cadastro.');
            }

            // Normaliza HH:MM para HH:MM:SS
            if (preg_match('/^\d{2}:\d{2}$/', $horaInicio)) {
                $horaInicio .= ':00';
            }
            if (preg_match('/^\d{2}:\d{2}$/', $horaFim)) {
                $horaFim .= ':00';
            }

            if ($id > 0) {
                $escalaRepo->update($id, $usuarioId, $regiaoId, $data, $horaInicio, $horaFim, $ativo);
            } else {
                $escalaRepo->create($empresaId, $usuarioId, $regiaoId, $data, $horaInicio, $horaFim, $ativo);
            }
            header('Location: escalas.php?ok=1');
            exit;
        }
        if ($acao === 'excluir') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
                $escalaRepo->delete($id);
            }
            header('Location: escalas.php?ok=1');
            exit;
        }
    } catch (Throwable $e) {
        $erro = $e->getMessage();
        $editando = [
            'id' => (int) ($_POST['id'] ?? 0),
            'usuario_id' => (int) ($_POST['usuario_id'] ?? 0),
            'regiao_id' => (int) ($_POST['regiao_id'] ?? 0),
            'data' => (string) ($_POST['data'] ?? ''),
            'hora_inicio' => (string) ($_POST['hora_inicio'] ?? ''),
            'hora_fim' => (string) ($_POST['hora_fim'] ?? ''),
            'ativo' => isset($_POST['ativo']) ? 1 : 0,
        ];
    }
}

if (isset($_GET['ok'])) {
    $mensagem = 'Operação concluída.';
}

$de = (string) ($_GET['de'] ?? date('Y-m-d', strtotime('-7 days')));
$ate = (string) ($_GET['ate'] ?? date('Y-m-d', strtotime('+14 days')));
$escalas = $escalaRepo->listByEmpresa($empresaId, $de, $ate);
$funcionarios = $userRepo->listEscalaveisAmong(
    $userRepo->visibleUserIds((int) $user['id'], Auth::isGestor())
);
$regioes = $regiaoRepo->listByEmpresa($empresaId);

function fmt_hora(?string $h): string
{
    if ($h === null || $h === '') {
        return '';
    }
    return substr($h, 0, 5);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>PAPI Rastro — Escalas</title>
  <link rel="icon" href="assets/favicon.ico" sizes="any">
  <link rel="icon" type="image/png" sizes="32x32" href="assets/favicon-32.png">
  <link rel="apple-touch-icon" href="assets/apple-touch-icon.png">
  <link href="https://fonts.googleapis.com/css2?family=Source+Sans+3:wght@400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/admin.css">
</head>
<body>
  <?php render_nav('escalas', $user); ?>
  <main class="admin">
    <h1>Escalas de trabalho</h1>
    <p class="lede">Use em grupos do tipo <strong>ronda</strong> e para quem tem <strong>Participa de escala</strong> no cadastro: no mapa só aparecem no horário da escala.</p>
    <?php if ($funcionarios === []): ?>
      <div class="alert err">Nenhum usuário com “Participa de escala” disponível. Marque o check em <a href="usuarios.php">Usuários</a>.</div>
    <?php endif; ?>

    <?php if ($mensagem): ?><div class="alert ok"><?= e($mensagem) ?></div><?php endif; ?>
    <?php if ($erro): ?><div class="alert err"><?= e($erro) ?></div><?php endif; ?>

    <section class="grid">
      <div class="panel">
        <h2><?= $editando && (int) ($editando['id'] ?? 0) > 0 ? 'Editar escala' : 'Nova escala' ?></h2>
        <form method="post" class="form">
          <input type="hidden" name="acao" value="salvar">
          <input type="hidden" name="id" value="<?= (int) ($editando['id'] ?? 0) ?>">
          <label>
            Usuário
            <select name="usuario_id" required>
              <option value="">Selecione</option>
              <?php
                $vistos = [];
                foreach ($funcionarios as $f):
                  if (isset($vistos[(int) $f['id']])) continue;
                  $vistos[(int) $f['id']] = true;
              ?>
                <option value="<?= (int) $f['id'] ?>" <?= (int) ($editando['usuario_id'] ?? 0) === (int) $f['id'] ? 'selected' : '' ?>>
                  <?= e((string) ($f['nome'] ?: $f['usuario'])) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>
            Região
            <select name="regiao_id" required>
              <option value="">Selecione</option>
              <?php foreach ($regioes as $r): ?>
                <option value="<?= (int) $r['id'] ?>" <?= (int) ($editando['regiao_id'] ?? 0) === (int) $r['id'] ? 'selected' : '' ?>>
                  <?= e($regiaoRepo->label($r)) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>Data<input type="date" name="data" required value="<?= e((string) ($editando['data'] ?? date('Y-m-d'))) ?>"></label>
          <label>Horário inicial<input type="time" name="hora_inicio" required value="<?= e(fmt_hora((string) ($editando['hora_inicio'] ?? '08:00:00'))) ?>"></label>
          <label>Horário final<input type="time" name="hora_fim" required value="<?= e(fmt_hora((string) ($editando['hora_fim'] ?? '17:00:00'))) ?>"></label>
          <p class="hint">Se o final for menor que o início (ex.: 22:00–06:00), a escala cruza a meia-noite.</p>
          <label class="check"><input type="checkbox" name="ativo" <?= !isset($editando['ativo']) || !empty($editando['ativo']) ? 'checked' : '' ?>> Ativa</label>
          <div class="actions">
            <button type="submit">Salvar</button>
            <?php if ($editando && (int) ($editando['id'] ?? 0) > 0): ?>
              <a class="btn-outline" href="escalas.php">Cancelar</a>
            <?php endif; ?>
          </div>
        </form>
      </div>

      <div class="panel">
        <h2>Escalas</h2>
        <form method="get" class="form form-inline">
          <label>De<input type="date" name="de" value="<?= e($de) ?>"></label>
          <label>Até<input type="date" name="ate" value="<?= e($ate) ?>"></label>
          <div class="actions"><button type="submit">Filtrar</button></div>
        </form>
        <div class="table-wrap">
          <table>
            <thead>
              <tr><th>Data</th><th>Funcionário</th><th>Região</th><th>Horário</th><th></th></tr>
            </thead>
            <tbody>
              <?php if ($escalas === []): ?>
                <tr><td colspan="5">Nenhuma escala no período.</td></tr>
              <?php endif; ?>
              <?php foreach ($escalas as $e): ?>
                <tr>
                  <td><?= e(date('d/m/Y', strtotime((string) $e['data']))) ?></td>
                  <td><?= e((string) ($e['usuario_nome'] ?: $e['usuario'])) ?></td>
                  <td><?= e((string) ($e['regiao_nome'] ?? '')) ?><?= !empty($e['bairro']) ? ' — ' . e((string) $e['bairro']) : '' ?></td>
                  <td><?= e(fmt_hora((string) $e['hora_inicio'])) ?> – <?= e(fmt_hora((string) $e['hora_fim'])) ?></td>
                  <td class="row-actions">
                    <a href="escalas.php?editar=<?= (int) $e['id'] ?>">Editar</a>
                    <form method="post" onsubmit="return confirm('Excluir escala?');">
                      <input type="hidden" name="acao" value="excluir">
                      <input type="hidden" name="id" value="<?= (int) $e['id'] ?>">
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
