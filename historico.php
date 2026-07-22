<?php

declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

Auth::requireLogin();

$db = (new Database())->getConnection();
$userRepo = new UserRepository($db);
$locRepo = new LocationRepository($db);
$userRepo->ensureSchema();

$viewer = Auth::user();
$visiveis = $userRepo->listVisibleUsers($viewer['id'], Auth::podeVerTodosDaEmpresa());

$mensagem = null;
$erro = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && Auth::isGestor()) {
    $acao = (string) ($_POST['acao'] ?? '');
    try {
        if ($acao === 'limpar') {
            $dias = (int) ($_POST['dias'] ?? 0);
            if ($dias < 1) {
                throw new RuntimeException('Informe quantos dias manter (mínimo 1).');
            }
            $apagados = $locRepo->purgeOlderThanDays($dias);
            $mensagem = "Histórico anterior a {$dias} dia(s) removido ({$apagados} registro(s)).";
        }
    } catch (Throwable $e) {
        $erro = $e->getMessage();
    }
}

$usuarioId = (int) ($_GET['usuario_id'] ?? ($visiveis[0]['id'] ?? 0));
if ($usuarioId > 0 && !$userRepo->canViewUser($viewer['id'], Auth::podeVerTodosDaEmpresa(), $usuarioId)) {
    $usuarioId = (int) ($visiveis[0]['id'] ?? 0);
}

$dia = (string) ($_GET['dia'] ?? date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dia)) {
    $dia = date('Y-m-d');
}

$diasDisponiveis = $usuarioId > 0 ? $locRepo->daysWithHistory($usuarioId) : [];
$pontos = $usuarioId > 0 ? $locRepo->historyForDay($usuarioId, $dia) : [];
$alvo = $usuarioId > 0 ? $userRepo->find($usuarioId) : null;
$totalRegistros = Auth::isGestor() ? $locRepo->countAll() : 0;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>PAPI Rastro — Histórico</title>
  <link rel="icon" href="assets/favicon.ico" sizes="any">
  <link rel="icon" type="image/png" sizes="32x32" href="assets/favicon-32.png">
  <link rel="apple-touch-icon" href="assets/apple-touch-icon.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Source+Sans+3:wght@400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/admin.css">
</head>
<body>
  <?php render_nav('historico', $viewer); ?>

  <main class="admin">
    <h1>Histórico diário</h1>
    <p class="lede">Veja o caminho do dia de cada pessoa que você tem permissão de acompanhar.</p>

    <?php if ($mensagem): ?>
      <div class="alert ok"><?= e($mensagem) ?></div>
    <?php endif; ?>
    <?php if ($erro): ?>
      <div class="alert err"><?= e($erro) ?></div>
    <?php endif; ?>

    <section class="grid historico-grid">
      <div class="panel">
        <h2>Filtro</h2>
        <form method="get" class="form">
          <label>
            Pessoa
            <select name="usuario_id" required>
              <?php foreach ($visiveis as $u): ?>
                <option value="<?= (int) $u['id'] ?>" <?= (int) $u['id'] === $usuarioId ? 'selected' : '' ?>>
                  <?= e((string) ($u['nome'] ?: $u['usuario'])) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>
            Dia
            <input type="date" name="dia" value="<?= e($dia) ?>" required>
          </label>
          <?php if ($diasDisponiveis !== []): ?>
            <p class="hint">Dias com registro:
              <?php foreach (array_slice($diasDisponiveis, 0, 8) as $d): ?>
                <a href="historico.php?usuario_id=<?= $usuarioId ?>&dia=<?= e($d) ?>"><?= e(date('d/m', strtotime($d))) ?></a>
              <?php endforeach; ?>
            </p>
          <?php endif; ?>
          <div class="actions">
            <button type="submit">Ver histórico</button>
          </div>
        </form>

        <?php if (Auth::isGestor()): ?>
          <hr class="sep">
          <h2>Limpar histórico antigo</h2>
          <p class="hint">Há <?= (int) $totalRegistros ?> ponto(s) salvos. Apaga registros anteriores ao prazo informado.</p>
          <form method="post" class="form" onsubmit="return confirm('Apagar histórico antigo? Esta ação não pode ser desfeita.');">
            <input type="hidden" name="acao" value="limpar">
            <label>
              Manter apenas os últimos (dias)
              <input type="number" name="dias" min="1" max="3650" value="30" required>
            </label>
            <div class="actions">
              <button type="submit" class="btn-danger">Apagar anteriores</button>
            </div>
          </form>
        <?php endif; ?>
      </div>

      <div class="panel">
        <h2>
          <?php if ($alvo): ?>
            <?= e((string) ($alvo['nome'] ?: $alvo['usuario'])) ?> — <?= e(date('d/m/Y', strtotime($dia))) ?>
          <?php else: ?>
            Sem dados
          <?php endif; ?>
        </h2>

        <?php if ($pontos === []): ?>
          <p class="hint">Nenhum ponto neste dia.</p>
        <?php else: ?>
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Hora</th>
                  <th>Endereço</th>
                  <th>Coord.</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($pontos as $p): ?>
                  <tr>
                    <td><?= e(date('H:i:s', strtotime((string) $p['criado_em']))) ?></td>
                    <td><?= e((string) ($p['endereco'] ?? '—')) ?></td>
                    <td>
                      <a href="https://www.google.com/maps?q=<?= e((string) $p['latitude']) ?>,<?= e((string) $p['longitude']) ?>"
                         target="_blank" rel="noopener noreferrer">
                        <?= e(number_format((float) $p['latitude'], 5) . ', ' . number_format((float) $p['longitude'], 5)) ?>
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <p class="hint"><?= count($pontos) ?> ponto(s) no dia.</p>
        <?php endif; ?>
      </div>
    </section>
  </main>
</body>
</html>
