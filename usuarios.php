<?php

declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

Auth::requireOperacional();

$db = (new Database())->getConnection();
$userRepo = new UserRepository($db);
$groupRepo = new GroupRepository($db);
$regiaoRepo = new RegiaoRepository($db);
$empresaRepo = new EmpresaRepository($db);
$userRepo->ensureSchema();

$mensagem = null;
$erro = null;
$editando = null;
$gruposSelecionados = [];
$regioesSelecionadas = [];

$user = Auth::user();
$souGestor = Auth::isGestor();
$empresaIdPadrao = Auth::empresaId();
$empresas = $souGestor ? $empresaRepo->listAll() : [];
if ($empresaIdPadrao === null && $empresas !== []) {
    $empresaIdPadrao = (int) $empresas[0]['id'];
}
if (!$souGestor && $empresaIdPadrao === null) {
    // master sem empresa na sessão: pega do próprio cadastro
    $fullMe = $userRepo->find((int) $user['id']);
    $empresaIdPadrao = isset($fullMe['empresa_id']) ? (int) $fullMe['empresa_id'] : null;
}

$tiposPermitidos = UserTypes::tiposAtribuiveisPor($user['tipo']);
$meusGrupoIds = $userRepo->grupoIdsDoUsuario((int) $user['id']);

if (isset($_GET['editar'])) {
    $editando = $userRepo->find((int) $_GET['editar']);
    if ($editando !== null) {
        if (!$souGestor) {
            $visiveis = $userRepo->visibleUserIds((int) $user['id'], false);
            if (!in_array((int) $editando['id'], $visiveis, true)
                && (int) $editando['id'] !== (int) $user['id']) {
                $editando = null;
                $erro = 'Você só pode editar usuários dos seus grupos.';
            } elseif (UserTypes::normalize((string) $editando['tipo']) === UserTypes::GESTOR) {
                $editando = null;
                $erro = 'Usuário master não pode editar gestores.';
            }
        }
        if ($editando !== null) {
            $gruposSelecionados = $userRepo->grupoIdsDoUsuario((int) $editando['id']);
            $regioesSelecionadas = $regiaoRepo->regioesDoUsuario((int) $editando['id']);
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = (string) ($_POST['acao'] ?? '');

    try {
        if ($acao === 'salvar') {
            $id = (int) ($_POST['id'] ?? 0);
            $usuario = trim((string) ($_POST['usuario'] ?? ''));
            $nome = trim((string) ($_POST['nome'] ?? ''));
            $telefone = trim((string) ($_POST['telefone'] ?? ''));
            $senha = (string) ($_POST['senha'] ?? '');
            $tipo = UserTypes::normalize((string) ($_POST['tipo'] ?? UserTypes::USUARIO));
            $ativo = isset($_POST['ativo']);
            $compartilhando = isset($_POST['compartilhando']);
            $participaEscala = isset($_POST['participa_escala']);
            $grupoIds = array_map('intval', (array) ($_POST['grupos'] ?? []));
            $regiaoIds = array_map('intval', (array) ($_POST['regioes'] ?? []));

            if ($usuario === '') {
                throw new RuntimeException('Informe o usuário.');
            }
            if (!in_array($tipo, $tiposPermitidos, true)) {
                throw new RuntimeException('Você não pode atribuir este tipo de usuário.');
            }

            if ($souGestor) {
                $empresaId = (int) ($_POST['empresa_id'] ?? 0);
                if ($empresaId <= 0) {
                    throw new RuntimeException('Selecione a empresa.');
                }
            } else {
                $empresaId = (int) ($empresaIdPadrao ?? 0);
                if ($empresaId <= 0) {
                    throw new RuntimeException('Sua conta não tem empresa vinculada.');
                }
                // master: só grupos aos quais tem acesso
                $grupoIds = array_values(array_intersect($grupoIds, $meusGrupoIds));
                if ($grupoIds === []) {
                    throw new RuntimeException('Selecione ao menos um grupo ao qual você tem acesso.');
                }
                // master não altera regiões
                if ($id > 0) {
                    $regiaoIds = $regiaoRepo->regioesDoUsuario($id);
                } else {
                    $regiaoIds = [];
                }

                if ($id > 0) {
                    $alvo = $userRepo->find($id);
                    if ($alvo === null) {
                        throw new RuntimeException('Usuário não encontrado.');
                    }
                    if (UserTypes::normalize((string) $alvo['tipo']) === UserTypes::GESTOR) {
                        throw new RuntimeException('Não é permitido editar gestores.');
                    }
                    $visiveis = $userRepo->visibleUserIds((int) $user['id'], false);
                    if (!in_array($id, $visiveis, true) && $id !== (int) $user['id']) {
                        throw new RuntimeException('Usuário fora dos seus grupos.');
                    }
                    // ao editar, mantém grupos fora do alcance do master
                    $atuais = $userRepo->grupoIdsDoUsuario($id);
                    $fora = array_values(array_diff($atuais, $meusGrupoIds));
                    $grupoIds = array_values(array_unique(array_merge($grupoIds, $fora)));
                }
            }

            $fotoAtual = null;
            if ($id > 0) {
                $atual = $userRepo->find($id);
                $fotoAtual = $atual['foto'] ?? null;
            }

            $foto = PhotoUpload::store(
                isset($_FILES['foto']) && is_array($_FILES['foto']) ? $_FILES['foto'] : null,
                is_string($fotoAtual) ? $fotoAtual : null
            );

            $permUsuarios = UserTypes::syncPermUsuarios($tipo);

            if ($id > 0) {
                $userRepo->update(
                    $id,
                    $usuario,
                    $nome !== '' ? $nome : null,
                    $telefone !== '' ? $telefone : null,
                    $foto,
                    $tipo,
                    $empresaId,
                    $permUsuarios,
                    $ativo,
                    $compartilhando,
                    $grupoIds,
                    $regiaoIds,
                    $senha !== '' ? $senha : null,
                    $participaEscala
                );
                $atualizado = $userRepo->find($id);
                if ($atualizado !== null) {
                    Auth::refreshFromUser($atualizado);
                }
            } else {
                if ($senha === '') {
                    throw new RuntimeException('Informe a senha do novo usuário.');
                }
                $userRepo->create(
                    $usuario,
                    $senha,
                    $nome !== '' ? $nome : null,
                    $telefone !== '' ? $telefone : null,
                    $foto,
                    $tipo,
                    $empresaId,
                    $permUsuarios,
                    $ativo,
                    $compartilhando,
                    $grupoIds,
                    $regiaoIds,
                    $participaEscala
                );
            }

            header('Location: usuarios.php?ok=1');
            exit;
        }

        if ($acao === 'excluir') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id === Auth::user()['id']) {
                throw new RuntimeException('Você não pode excluir o próprio usuário.');
            }
            if ($id > 0) {
                if (!$souGestor) {
                    $alvo = $userRepo->find($id);
                    if ($alvo === null || UserTypes::normalize((string) $alvo['tipo']) === UserTypes::GESTOR) {
                        throw new RuntimeException('Exclusão não permitida.');
                    }
                    $visiveis = $userRepo->visibleUserIds((int) $user['id'], false);
                    if (!in_array($id, $visiveis, true)) {
                        throw new RuntimeException('Usuário fora dos seus grupos.');
                    }
                }
                $userRepo->delete($id);
            }
            header('Location: usuarios.php?ok=1');
            exit;
        }
    } catch (Throwable $e) {
        $erro = $e->getMessage();
        if ($acao === 'salvar') {
            $gruposSelecionados = array_map('intval', (array) ($_POST['grupos'] ?? []));
            $regioesSelecionadas = array_map('intval', (array) ($_POST['regioes'] ?? []));
            $editando = [
                'id' => (int) ($_POST['id'] ?? 0),
                'usuario' => (string) ($_POST['usuario'] ?? ''),
                'nome' => (string) ($_POST['nome'] ?? ''),
                'telefone' => (string) ($_POST['telefone'] ?? ''),
                'tipo' => UserTypes::normalize((string) ($_POST['tipo'] ?? UserTypes::USUARIO)),
                'empresa_id' => (int) ($_POST['empresa_id'] ?? $empresaIdPadrao),
                'foto' => null,
                'ativo' => isset($_POST['ativo']) ? 1 : 0,
                'compartilhando' => isset($_POST['compartilhando']) ? 1 : 0,
                'participa_escala' => isset($_POST['participa_escala']) ? 1 : 0,
            ];
        }
    }
}

if (isset($_GET['ok'])) {
    $mensagem = 'Operação concluída.';
}

$usuarios = $userRepo->listUsersForManager((int) $user['id'], $souGestor, $empresaIdPadrao);
$grupos = $souGestor
    ? $groupRepo->listGroups()
    : $groupRepo->listGroupsForViewer((int) $user['id'], false);
$regioes = $souGestor ? $regiaoRepo->listByEmpresa($empresaIdPadrao) : [];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>PAPI Rastro — Usuários</title>
  <link rel="icon" href="assets/favicon.ico" sizes="any">
  <link rel="icon" type="image/png" sizes="32x32" href="assets/favicon-32.png">
  <link rel="apple-touch-icon" href="assets/apple-touch-icon.png">
  <link href="https://fonts.googleapis.com/css2?family=Source+Sans+3:wght@400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/admin.css">
</head>
<body>
  <?php render_nav('usuarios', $user); ?>
  <main class="admin">
    <h1>Cadastro de usuários</h1>
    <p class="lede">
      Tipos: <strong>Gestor</strong>, <strong>Usuário master</strong> ou <strong>Usuário</strong>.
      Amigos, família e rondas são definidos pelos <strong>grupos</strong>.
    </p>

    <?php if ($mensagem): ?><div class="alert ok"><?= e($mensagem) ?></div><?php endif; ?>
    <?php if ($erro): ?><div class="alert err"><?= e($erro) ?></div><?php endif; ?>

    <section class="grid">
      <div class="panel">
        <h2><?= $editando && (int) ($editando['id'] ?? 0) > 0 ? 'Editar usuário' : 'Novo usuário' ?></h2>
        <form method="post" class="form" enctype="multipart/form-data" id="form-user">
          <input type="hidden" name="acao" value="salvar">
          <input type="hidden" name="id" value="<?= (int) ($editando['id'] ?? 0) ?>">

          <?php if ($souGestor): ?>
            <label>
              Empresa
              <select name="empresa_id" required>
                <?php foreach ($empresas as $emp): ?>
                  <option value="<?= (int) $emp['id'] ?>"
                    <?= (int) ($editando['empresa_id'] ?? $empresaIdPadrao) === (int) $emp['id'] ? 'selected' : '' ?>>
                    <?= e((string) $emp['nome']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </label>
          <?php else: ?>
            <input type="hidden" name="empresa_id" value="<?= (int) $empresaIdPadrao ?>">
          <?php endif; ?>

          <label>
            Tipo
            <select name="tipo" id="tipo" required>
              <?php
                $tipoAtual = UserTypes::normalize((string) ($editando['tipo'] ?? UserTypes::USUARIO));
                if (!in_array($tipoAtual, $tiposPermitidos, true)) {
                    $tipoAtual = UserTypes::USUARIO;
                }
                foreach ($tiposPermitidos as $t):
              ?>
                <option value="<?= e($t) ?>" <?= $tipoAtual === $t ? 'selected' : '' ?>>
                  <?= e(UserTypes::label($t)) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </label>
          <p class="hint">
            Gestor: empresa inteira. Usuário master: grupos, usuários dos seus grupos e escalas.
            Usuário: mapa e mensagens dos seus grupos.
          </p>

          <label>Usuário<input type="text" name="usuario" required value="<?= e((string) ($editando['usuario'] ?? '')) ?>"></label>
          <label>Nome<input type="text" name="nome" value="<?= e((string) ($editando['nome'] ?? '')) ?>"></label>
          <label>Telefone / WhatsApp<input type="tel" name="telefone" value="<?= e((string) ($editando['telefone'] ?? '')) ?>"></label>
          <label>Foto no mapa<input type="file" name="foto" accept="image/jpeg,image/png,image/webp"></label>
          <?php if (!empty($editando['foto'])): ?>
            <div class="foto-preview">
              <img src="<?= e((string) $editando['foto']) ?>" alt="Foto atual">
              <span>Foto atual</span>
            </div>
          <?php endif; ?>
          <label>
            Senha<?= $editando && (int) ($editando['id'] ?? 0) > 0 ? ' (deixe em branco para manter)' : '' ?>
            <input type="password" name="senha" <?= $editando && (int) ($editando['id'] ?? 0) > 0 ? '' : 'required' ?> autocomplete="new-password">
          </label>

          <fieldset class="perm-box">
            <legend>Grupos</legend>
            <p class="hint">
              <?= $souGestor
                ? 'Amigos, família, ronda etc. No mapa o usuário escolhe qual grupo quer ver.'
                : 'Você só pode alocar em grupos aos quais tem acesso.' ?>
            </p>
            <?php if ($grupos === []): ?>
              <p class="hint">Nenhum grupo. Crie em <a href="grupos.php">Grupos</a>.</p>
            <?php else: ?>
              <?php foreach ($grupos as $g): ?>
                <label class="check">
                  <input type="checkbox" name="grupos[]" value="<?= (int) $g['id'] ?>"
                    <?= in_array((int) $g['id'], $gruposSelecionados, true) ? 'checked' : '' ?>>
                  <?= e((string) $g['nome']) ?>
                  <small>(<?= e(GroupModes::label((string) ($g['modo'] ?? GroupModes::SOCIAL))) ?>)</small>
                </label>
              <?php endforeach; ?>
            <?php endif; ?>
          </fieldset>

          <?php if ($souGestor): ?>
            <fieldset class="perm-box" id="box-regioes">
              <legend>Regiões (opcional)</legend>
              <p class="hint">Útil para vincular a áreas cobertas; escalas usam as regiões cadastradas.</p>
              <?php if ($regioes === []): ?>
                <p class="hint">Cadastre regiões em <a href="regioes.php">Regiões</a>.</p>
              <?php else: ?>
                <?php foreach ($regioes as $r): ?>
                  <label class="check">
                    <input type="checkbox" name="regioes[]" value="<?= (int) $r['id'] ?>"
                      <?= in_array((int) $r['id'], $regioesSelecionadas, true) ? 'checked' : '' ?>>
                    <?= e($regiaoRepo->label($r)) ?>
                  </label>
                <?php endforeach; ?>
              <?php endif; ?>
            </fieldset>
          <?php endif; ?>

          <fieldset class="perm-box">
            <legend>Status</legend>
            <label class="check">
              <input type="checkbox" name="participa_escala" value="1"
                <?= !empty($editando['participa_escala']) ? 'checked' : '' ?>>
              Participa de escala (só aparece no mapa no horário escalado; pode ser selecionado em Escalas)
            </label>
            <label class="check">
              <input type="checkbox" name="compartilhando" value="1"
                <?= !isset($editando['compartilhando']) || !empty($editando['compartilhando']) ? 'checked' : '' ?>>
              Compartilhar localização (pode pausar no mapa)
            </label>
            <label class="check">
              <input type="checkbox" name="ativo"
                <?= !isset($editando['ativo']) || !empty($editando['ativo']) ? 'checked' : '' ?>>
              Ativo
            </label>
          </fieldset>

          <div class="actions">
            <button type="submit"><?= $editando && (int) ($editando['id'] ?? 0) > 0 ? 'Salvar' : 'Criar' ?></button>
            <?php if ($editando && (int) ($editando['id'] ?? 0) > 0): ?>
              <a class="btn-outline" href="usuarios.php">Cancelar</a>
            <?php endif; ?>
          </div>
        </form>
      </div>

      <div class="panel">
        <h2>Usuários</h2>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th></th><th>Usuário</th><th>Tipo</th><th>Telefone</th><th>Grupos</th><th>Escala</th><th>GPS</th><th></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($usuarios as $u): ?>
                <tr>
                  <td>
                    <?php if (!empty($u['foto'])): ?>
                      <img class="avatar-sm" src="<?= e((string) $u['foto']) ?>" alt="">
                    <?php else: ?>
                      <span class="avatar-sm avatar-placeholder"><?= e(mb_strtoupper(mb_substr((string) ($u['nome'] ?: $u['usuario']), 0, 1))) ?></span>
                    <?php endif; ?>
                  </td>
                  <td><?= e((string) ($u['nome'] ?: $u['usuario'])) ?><br><small><?= e((string) $u['usuario']) ?></small></td>
                  <td><?= e(UserTypes::label(UserTypes::normalize((string) $u['tipo']))) ?></td>
                  <td><?= e((string) ($u['telefone'] ?? '—')) ?></td>
                  <td><?= e((string) ($u['grupos'] ?? '—')) ?></td>
                  <td><?= !empty($u['participa_escala']) ? 'Sim' : '—' ?></td>
                  <td><?= !empty($u['compartilhando']) ? 'On' : 'Pausado' ?></td>
                  <td class="row-actions">
                    <?php
                      $podeEditar = $souGestor
                        || UserTypes::normalize((string) $u['tipo']) !== UserTypes::GESTOR;
                    ?>
                    <?php if ($podeEditar): ?>
                      <a href="usuarios.php?editar=<?= (int) $u['id'] ?>">Editar</a>
                    <?php endif; ?>
                    <?php if ((int) $u['id'] !== $user['id'] && $podeEditar): ?>
                      <form method="post" onsubmit="return confirm('Excluir este usuário?');">
                        <input type="hidden" name="acao" value="excluir">
                        <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                        <button type="submit" class="link-danger">Excluir</button>
                      </form>
                    <?php endif; ?>
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
