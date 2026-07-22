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
$empresaFiltro = isset($_GET['empresa_id']) ? (int) $_GET['empresa_id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = (string) ($_POST['acao'] ?? '');
    try {
        if ($acao === 'creditar') {
            $empresaId = (int) ($_POST['empresa_id'] ?? 0);
            $plano = (string) ($_POST['plano'] ?? 'mensal');
            $dias = (int) ($_POST['dias'] ?? 0);
            $valor = trim((string) ($_POST['valor'] ?? ''));
            $observacao = trim((string) ($_POST['observacao'] ?? ''));

            foreach (EmpresaRepository::planos() as $p) {
                if ($p['plano'] === $plano && $p['dias'] > 0) {
                    $dias = $p['dias'];
                    break;
                }
            }
            if ($dias < 1) {
                throw new RuntimeException('Informe a quantidade de dias de crédito.');
            }
            if ($empresaId <= 0) {
                throw new RuntimeException('Selecione a empresa.');
            }

            $empresaRepo->adicionarCredito(
                $empresaId,
                $dias,
                $plano,
                $valor !== '' ? str_replace(',', '.', $valor) : null,
                $observacao !== '' ? $observacao : null,
                $user['id']
            );
            header('Location: pagamentos.php?empresa_id=' . $empresaId . '&ok=1');
            exit;
        }
    } catch (Throwable $e) {
        $erro = $e->getMessage();
    }
}

if (isset($_GET['ok'])) {
    $mensagem = 'Crédito lançado com sucesso.';
}

$empresas = $empresaRepo->listAll();
$pagamentos = $empresaRepo->listPagamentos($empresaFiltro > 0 ? $empresaFiltro : null);
$planos = EmpresaRepository::planos();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>PAPI Rastro — Pagamentos</title>
  <link rel="icon" href="assets/favicon.ico" sizes="any">
  <link rel="icon" type="image/png" sizes="32x32" href="assets/favicon-32.png">
  <link rel="apple-touch-icon" href="assets/apple-touch-icon.png">
  <link href="https://fonts.googleapis.com/css2?family=Source+Sans+3:wght@400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/admin.css">
</head>
<body>
  <?php render_nav('pagamentos', $user); ?>
  <main class="admin">
    <h1>Pagamentos e créditos</h1>
    <p class="lede">Baixa manual de planos (mensal, anual etc.). Sem crédito válido, os usuários da empresa não entram no sistema.</p>

    <?php if ($mensagem): ?><div class="alert ok"><?= e($mensagem) ?></div><?php endif; ?>
    <?php if ($erro): ?><div class="alert err"><?= e($erro) ?></div><?php endif; ?>

    <section class="grid">
      <div class="panel">
        <h2>Lançar crédito</h2>
        <form method="post" class="form" id="form-credito">
          <input type="hidden" name="acao" value="creditar">
          <label>
            Empresa
            <select name="empresa_id" required>
              <option value="">Selecione</option>
              <?php foreach ($empresas as $emp): ?>
                <option value="<?= (int) $emp['id'] ?>" <?= $empresaFiltro === (int) $emp['id'] ? 'selected' : '' ?>>
                  <?= e((string) $emp['nome']) ?>
                  <?php if (!empty($emp['valido_ate'])): ?>
                    (até <?= e(date('d/m/Y', strtotime((string) $emp['valido_ate']))) ?>)
                  <?php endif; ?>
                </option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>
            Plano
            <select name="plano" id="plano" required>
              <?php foreach ($planos as $p): ?>
                <option value="<?= e($p['plano']) ?>" data-dias="<?= (int) $p['dias'] ?>"><?= e($p['label']) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label id="dias-wrap">
            Dias de crédito
            <input type="number" name="dias" id="dias" min="1" max="3660" value="30">
          </label>
          <label>Valor (R$)<input type="text" name="valor" placeholder="0.00"></label>
          <label>Observação<input type="text" name="observacao" placeholder="ex.: PIX 22/07"></label>
          <div class="actions"><button type="submit">Baixar crédito</button></div>
        </form>
      </div>

      <div class="panel">
        <h2>Histórico de baixas</h2>
        <form method="get" class="form form-inline">
          <label>
            Filtrar empresa
            <select name="empresa_id" onchange="this.form.submit()">
              <option value="0">Todas</option>
              <?php foreach ($empresas as $emp): ?>
                <option value="<?= (int) $emp['id'] ?>" <?= $empresaFiltro === (int) $emp['id'] ? 'selected' : '' ?>>
                  <?= e((string) $emp['nome']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </label>
        </form>
        <div class="table-wrap">
          <table>
            <thead>
              <tr><th>Quando</th><th>Empresa</th><th>Plano</th><th>Dias</th><th>Valor</th><th>Obs.</th><th>Por</th></tr>
            </thead>
            <tbody>
              <?php if ($pagamentos === []): ?>
                <tr><td colspan="7">Nenhum pagamento lançado.</td></tr>
              <?php endif; ?>
              <?php foreach ($pagamentos as $p): ?>
                <tr>
                  <td><?= e(date('d/m/Y H:i', strtotime((string) $p['criado_em']))) ?></td>
                  <td><?= e((string) $p['empresa_nome']) ?></td>
                  <td><?= e((string) $p['plano']) ?></td>
                  <td><?= (int) $p['dias'] ?></td>
                  <td><?= $p['valor'] !== null ? 'R$ ' . e(number_format((float) $p['valor'], 2, ',', '.')) : '—' ?></td>
                  <td><?= e((string) ($p['observacao'] ?? '—')) ?></td>
                  <td><?= e((string) ($p['criado_por_usuario'] ?? '—')) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </section>
  </main>
  <script>
    const plano = document.getElementById('plano');
    const dias = document.getElementById('dias');
    function syncDias() {
      const opt = plano.options[plano.selectedIndex];
      const d = Number(opt.getAttribute('data-dias') || 0);
      if (d > 0) {
        dias.value = String(d);
        dias.readOnly = true;
      } else {
        dias.readOnly = false;
      }
    }
    plano.addEventListener('change', syncDias);
    syncDias();
  </script>
</body>
</html>
