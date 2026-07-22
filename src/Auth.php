<?php

declare(strict_types=1);

final class Auth
{
    public static function startSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }

    public static function login(PDO $pdo, string $usuario, string $senha): bool
    {
        self::startSession();
        $repo = new UserRepository($pdo);
        $user = $repo->authenticate($usuario, $senha);

        if ($user === null) {
            return false;
        }

        $empresaId = isset($user['empresa_id']) ? (int) $user['empresa_id'] : null;
        $empresas = new EmpresaRepository($pdo);
        if (!$empresas->empresaAtivaComCredito($empresaId > 0 ? $empresaId : null)) {
            throw new RuntimeException(
                'A empresa está sem créditos/plano ativo. Peça ao gestor para registrar o pagamento.'
            );
        }

        session_regenerate_id(true);
        self::hydrateSession($user);

        return true;
    }

    /**
     * @param array<string, mixed> $user
     */
    public static function hydrateSession(array $user): void
    {
        self::startSession();
        $tipo = UserTypes::normalize((string) ($user['tipo'] ?? UserTypes::USUARIO));
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['empresa_id'] = isset($user['empresa_id']) ? (int) $user['empresa_id'] : null;
        $_SESSION['usuario'] = (string) $user['usuario'];
        $_SESSION['nome'] = $user['nome'] !== null ? (string) $user['nome'] : null;
        $_SESSION['tipo'] = $tipo;
        $_SESSION['compartilhando'] = !empty($user['compartilhando']);
        // derivado do tipo (coluna legado mantida em sync)
        $_SESSION['perm_usuarios'] = UserTypes::syncPermUsuarios($tipo);
    }

    public static function logout(): void
    {
        self::startSession();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                (bool) $params['secure'],
                (bool) $params['httponly']
            );
        }
        session_destroy();
    }

    public static function check(): bool
    {
        self::startSession();
        return isset($_SESSION['user_id']) && (int) $_SESSION['user_id'] > 0;
    }

    public static function requireLogin(string $redirectTo = 'login.php'): void
    {
        if (!self::check()) {
            header('Location: ' . $redirectTo);
            exit;
        }
    }

    public static function requireLoginApi(): void
    {
        if (!self::check()) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['status' => 'erro', 'mensagem' => 'Não autenticado.']);
            exit;
        }
    }

    /**
     * @return array{
     *   id:int,
     *   empresa_id:?int,
     *   usuario:string,
     *   nome:?string,
     *   tipo:string,
     *   compartilhando:bool,
     *   perm_usuarios:bool
     * }
     */
    public static function user(): array
    {
        self::startSession();
        $tipo = UserTypes::normalize((string) ($_SESSION['tipo'] ?? UserTypes::USUARIO));

        return [
            'id' => (int) ($_SESSION['user_id'] ?? 0),
            'empresa_id' => isset($_SESSION['empresa_id']) && (int) $_SESSION['empresa_id'] > 0
                ? (int) $_SESSION['empresa_id']
                : null,
            'usuario' => (string) ($_SESSION['usuario'] ?? ''),
            'nome' => isset($_SESSION['nome']) ? (string) $_SESSION['nome'] : null,
            'tipo' => $tipo,
            'compartilhando' => !empty($_SESSION['compartilhando']),
            'perm_usuarios' => UserTypes::syncPermUsuarios($tipo),
        ];
    }

    public static function empresaId(): ?int
    {
        return self::user()['empresa_id'];
    }

    public static function tipo(): string
    {
        return self::user()['tipo'];
    }

    public static function isGestor(): bool
    {
        return self::tipo() === UserTypes::GESTOR;
    }

    public static function isMaster(): bool
    {
        return self::tipo() === UserTypes::USUARIO_MASTER;
    }

    /** Mapa/histórico: gestor vê a empresa inteira. */
    public static function podeVerTodosDaEmpresa(): bool
    {
        return self::isGestor();
    }

    /** Usuários, grupos e escalas. */
    public static function podeOperacional(): bool
    {
        return UserTypes::podeGerenciarOperacional(self::tipo());
    }

    /** Empresas, regiões e pagamentos — só gestor. */
    public static function podeEmpresa(): bool
    {
        return self::isGestor();
    }

    /** @deprecated Use podeOperacional(); mantido por compatibilidade. */
    public static function podeUsuarios(): bool
    {
        return self::podeOperacional();
    }

    public static function compartilhando(): bool
    {
        return self::user()['compartilhando'];
    }

    public static function requireOperacional(): void
    {
        self::requireLogin();
        if (!self::podeOperacional()) {
            header('Location: index.php');
            exit;
        }
    }

    /** @deprecated Use requireOperacional() */
    public static function requireUsuarios(): void
    {
        self::requireOperacional();
    }

    public static function requireGestor(): void
    {
        self::requireLogin();
        if (!self::isGestor()) {
            header('Location: index.php');
            exit;
        }
    }

    public static function refreshFromUser(array $user): void
    {
        self::startSession();
        if (!self::check() || (int) $user['id'] !== self::user()['id']) {
            return;
        }
        self::hydrateSession($user);
    }
}
