<?php

declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

Auth::startSession();

if (Auth::check()) {
    header('Location: index.php');
    exit;
}

$erro = null;
$usuarioInformado = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuarioInformado = trim((string) ($_POST['usuario'] ?? ''));
    $senha = (string) ($_POST['senha'] ?? '');

    try {
        $db = (new Database())->getConnection();
        $repo = new UserRepository($db);
        $repo->ensureSchema();

        if (Auth::login($db, $usuarioInformado, $senha)) {
            header('Location: index.php');
            exit;
        }

        $erro = 'Usuário ou senha inválidos.';
    } catch (Throwable $exception) {
        $erro = 'Falha ao autenticar: ' . $exception->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PAPI Rastro — Login</title>
    <link rel="icon" href="assets/favicon.ico" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/favicon-32.png">
    <link rel="apple-touch-icon" href="assets/apple-touch-icon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Source+Sans+3:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/login.css">
</head>
<body>
    <div class="login-wrap">
        <div class="card">
            <img class="login-logo" src="assets/papijunior-logo.png" alt="Papi Junior">
            <div class="brand">PAPI RASTRO</div>
            <h1>Entrar</h1>
            <p>Informe usuário e senha para ver onde a equipe está alocada.</p>

            <?php if ($erro !== null): ?>
                <div class="alert" role="alert"><?= e($erro) ?></div>
            <?php endif; ?>

            <form method="post" action="login.php" autocomplete="on">
                <label>
                    Usuário
                    <input type="text" name="usuario" required autofocus
                           value="<?= e($usuarioInformado) ?>" autocomplete="username">
                </label>
                <label>
                    Senha
                    <input type="password" name="senha" required autocomplete="current-password">
                </label>
                <button type="submit">Entrar</button>
            </form>
        </div>
        <div class="credit">
            <span>por</span>
            <a href="https://www.papijunior.com.br" target="_blank" rel="noopener noreferrer" title="Papi Junior">
                <img src="assets/papijunior-logo.png" alt="Papi Junior">
            </a>
        </div>
    </div>
</body>
</html>
