<?php

declare(strict_types=1);

final class UserRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function ensureSchema(): void
    {
        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS empresas (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                nome VARCHAR(160) NOT NULL,
                cnpj VARCHAR(14) NULL,
                ativo TINYINT(1) NOT NULL DEFAULT 1,
                valido_ate DATE NULL,
                criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uk_empresas_cnpj (cnpj)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS usuarios (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                empresa_id INT UNSIGNED NULL,
                usuario VARCHAR(60) NOT NULL,
                senha_hash VARCHAR(255) NOT NULL,
                nome VARCHAR(120) NULL,
                foto VARCHAR(255) NULL,
                telefone VARCHAR(20) NULL,
                tipo VARCHAR(20) NOT NULL DEFAULT 'usuario',
                compartilhando TINYINT(1) NOT NULL DEFAULT 1,
                ativo TINYINT(1) NOT NULL DEFAULT 1,
                perm_usuarios TINYINT(1) NOT NULL DEFAULT 0,
                criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uk_usuarios_usuario (usuario),
                KEY idx_usuarios_empresa (empresa_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->ensureColumn('usuarios', 'empresa_id', 'INT UNSIGNED NULL AFTER id');
        $this->ensureColumn('usuarios', 'foto', 'VARCHAR(255) NULL AFTER nome');
        $this->ensureColumn('usuarios', 'telefone', 'VARCHAR(20) NULL AFTER foto');
        $this->ensureColumn('usuarios', 'tipo', "VARCHAR(20) NOT NULL DEFAULT 'usuario' AFTER telefone");
        $this->ensureColumn('usuarios', 'compartilhando', 'TINYINT(1) NOT NULL DEFAULT 1 AFTER tipo');
        $this->ensureColumn('usuarios', 'participa_escala', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER compartilhando');

        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS grupos (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                nome VARCHAR(120) NOT NULL,
                modo VARCHAR(20) NOT NULL DEFAULT 'social',
                criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uk_grupos_nome (nome)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $this->ensureColumn('grupos', 'modo', "VARCHAR(20) NOT NULL DEFAULT 'social' AFTER nome");

        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS usuario_grupo (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                usuario_id INT UNSIGNED NOT NULL,
                grupo_id INT UNSIGNED NOT NULL,
                criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uk_usuario_grupo (usuario_id, grupo_id),
                CONSTRAINT fk_ug_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
                    ON UPDATE CASCADE ON DELETE CASCADE,
                CONSTRAINT fk_ug_grupo FOREIGN KEY (grupo_id) REFERENCES grupos (id)
                    ON UPDATE CASCADE ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS regioes (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                empresa_id INT UNSIGNED NOT NULL,
                nome VARCHAR(120) NOT NULL,
                bairro VARCHAR(120) NULL,
                rua VARCHAR(160) NULL,
                complemento VARCHAR(160) NULL,
                cidade VARCHAR(120) NULL,
                ativo TINYINT(1) NOT NULL DEFAULT 1,
                criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_regioes_empresa (empresa_id),
                CONSTRAINT fk_regioes_empresa FOREIGN KEY (empresa_id) REFERENCES empresas (id)
                    ON UPDATE CASCADE ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS usuario_regiao (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                usuario_id INT UNSIGNED NOT NULL,
                regiao_id INT UNSIGNED NOT NULL,
                criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uk_usuario_regiao (usuario_id, regiao_id),
                CONSTRAINT fk_ur_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
                    ON UPDATE CASCADE ON DELETE CASCADE,
                CONSTRAINT fk_ur_regiao FOREIGN KEY (regiao_id) REFERENCES regioes (id)
                    ON UPDATE CASCADE ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS escalas (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                empresa_id INT UNSIGNED NOT NULL,
                usuario_id INT UNSIGNED NOT NULL,
                regiao_id INT UNSIGNED NOT NULL,
                data DATE NOT NULL,
                hora_inicio TIME NOT NULL,
                hora_fim TIME NOT NULL,
                ativo TINYINT(1) NOT NULL DEFAULT 1,
                criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_escalas_usuario_data (usuario_id, data),
                KEY idx_escalas_regiao_data (regiao_id, data),
                CONSTRAINT fk_escalas_empresa FOREIGN KEY (empresa_id) REFERENCES empresas (id)
                    ON UPDATE CASCADE ON DELETE CASCADE,
                CONSTRAINT fk_escalas_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
                    ON UPDATE CASCADE ON DELETE CASCADE,
                CONSTRAINT fk_escalas_regiao FOREIGN KEY (regiao_id) REFERENCES regioes (id)
                    ON UPDATE CASCADE ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS localizacoes (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                usuario_id INT UNSIGNED NOT NULL,
                latitude DECIMAL(10,7) NOT NULL,
                longitude DECIMAL(10,7) NOT NULL,
                endereco VARCHAR(500) NULL,
                ip VARCHAR(45) NULL,
                dispositivo VARCHAR(120) NULL,
                criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_localizacoes_usuario_criado (usuario_id, criado_em),
                CONSTRAINT fk_localizacoes_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
                    ON UPDATE CASCADE ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS pagamentos (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                empresa_id INT UNSIGNED NOT NULL,
                plano VARCHAR(30) NOT NULL,
                dias INT UNSIGNED NOT NULL,
                valor DECIMAL(12,2) NULL,
                observacao VARCHAR(255) NULL,
                criado_por INT UNSIGNED NULL,
                criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_pagamentos_empresa (empresa_id),
                CONSTRAINT fk_pagamentos_empresa FOREIGN KEY (empresa_id) REFERENCES empresas (id)
                    ON UPDATE CASCADE ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        PhotoUpload::ensureDir();
        $this->ensureInitialEmpresaAndUser();
        $this->migrateLegacyTipos();
        $this->ensureAmigosESherley();
    }

    private function migrateLegacyTipos(): void
    {
        $this->db->exec(
            "UPDATE usuarios
             SET tipo = 'usuario'
             WHERE tipo IN ('familia', 'funcionario', 'morador', '')
                OR tipo IS NULL
                OR tipo NOT IN ('gestor', 'usuario_master', 'usuario')"
        );
        // legado: quem tinha só a flag, vira gestor (não sobrescreve master)
        $this->db->exec(
            "UPDATE usuarios SET tipo = 'gestor'
             WHERE perm_usuarios = 1 AND tipo = 'usuario'"
        );
        $this->db->exec(
            "UPDATE usuarios SET perm_usuarios = 1
             WHERE tipo IN ('gestor', 'usuario_master')"
        );
        $this->db->exec(
            "UPDATE usuarios SET perm_usuarios = 0 WHERE tipo = 'usuario'"
        );
        $this->db->exec(
            "UPDATE grupos SET modo = 'social' WHERE modo IS NULL OR modo = '' OR modo NOT IN ('social', 'ronda')"
        );
    }

    private function ensureColumn(string $table, string $column, string $definition): void
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table
               AND COLUMN_NAME = :column'
        );
        $stmt->execute([':table' => $table, ':column' => $column]);
        if ((int) $stmt->fetchColumn() === 0) {
            $this->db->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
        }
    }

    private function ensureInitialEmpresaAndUser(): void
    {
        $empresaId = $this->db->query("SELECT id FROM empresas WHERE nome = 'Papi Rastro Demo' LIMIT 1")->fetchColumn();
        if ($empresaId === false) {
            $this->db->prepare(
                'INSERT INTO empresas (nome, cnpj, ativo, valido_ate)
                 VALUES (:nome, NULL, 1, :valido_ate)'
            )->execute([
                ':nome' => 'Papi Rastro Demo',
                ':valido_ate' => (new DateTimeImmutable('today'))->modify('+365 days')->format('Y-m-d'),
            ]);
            $empresaId = (int) $this->db->lastInsertId();
        } else {
            $empresaId = (int) $empresaId;
            $emp = $this->db->prepare('SELECT valido_ate FROM empresas WHERE id = :id');
            $emp->execute([':id' => $empresaId]);
            $valido = $emp->fetchColumn();
            if ($valido === false || $valido === null || (string) $valido < date('Y-m-d')) {
                $this->db->prepare(
                    'UPDATE empresas SET valido_ate = :v, ativo = 1 WHERE id = :id'
                )->execute([
                    ':v' => (new DateTimeImmutable('today'))->modify('+365 days')->format('Y-m-d'),
                    ':id' => $empresaId,
                ]);
            }
        }

        $stmt = $this->db->query("SELECT id FROM usuarios WHERE usuario = 'papijunior' LIMIT 1");
        $id = $stmt->fetchColumn();

        if ($id === false) {
            $ins = $this->db->prepare(
                'INSERT INTO usuarios
                    (empresa_id, usuario, senha_hash, nome, tipo, compartilhando, ativo, perm_usuarios)
                 VALUES
                    (:empresa_id, :usuario, :senha_hash, :nome, :tipo, 1, 1, 1)'
            );
            $ins->execute([
                ':empresa_id' => $empresaId,
                ':usuario' => 'papijunior',
                ':senha_hash' => password_hash('123456', PASSWORD_DEFAULT),
                ':nome' => 'Papi Junior',
                ':tipo' => UserTypes::GESTOR,
            ]);
            return;
        }

        $this->db->prepare(
            'UPDATE usuarios
             SET ativo = 1, perm_usuarios = 1, tipo = :tipo,
                 empresa_id = COALESCE(empresa_id, :empresa_id),
                 compartilhando = COALESCE(compartilhando, 1)
             WHERE id = :id'
        )->execute([
            ':id' => (int) $id,
            ':tipo' => UserTypes::GESTOR,
            ':empresa_id' => $empresaId,
        ]);
    }

    /**
     * Grupo Amigos + usuário de teste sherley7 (senha 123456).
     */
    private function ensureAmigosESherley(): void
    {
        $empresaId = (int) ($this->db->query(
            "SELECT id FROM empresas WHERE nome = 'Papi Rastro Demo' LIMIT 1"
        )->fetchColumn() ?: 0);
        if ($empresaId <= 0) {
            $empresaId = (int) ($this->db->query('SELECT id FROM empresas ORDER BY id ASC LIMIT 1')->fetchColumn() ?: 0);
        }
        if ($empresaId <= 0) {
            return;
        }

        $grupoId = $this->db->query("SELECT id FROM grupos WHERE nome = 'Amigos' LIMIT 1")->fetchColumn();
        if ($grupoId === false) {
            $this->db->prepare('INSERT INTO grupos (nome, modo) VALUES (:nome, :modo)')->execute([
                ':nome' => 'Amigos',
                ':modo' => GroupModes::SOCIAL,
            ]);
            $grupoId = (int) $this->db->lastInsertId();
        } else {
            $grupoId = (int) $grupoId;
            $this->db->prepare(
                "UPDATE grupos SET modo = COALESCE(NULLIF(modo, ''), 'social') WHERE id = :id"
            )->execute([':id' => $grupoId]);
        }

        $sherleyId = $this->db->query("SELECT id FROM usuarios WHERE usuario = 'sherley7' LIMIT 1")->fetchColumn();
        if ($sherleyId === false) {
            $this->db->prepare(
                'INSERT INTO usuarios
                    (empresa_id, usuario, senha_hash, nome, tipo, compartilhando, ativo, perm_usuarios)
                 VALUES
                    (:empresa_id, :usuario, :senha_hash, :nome, :tipo, 1, 1, 0)'
            )->execute([
                ':empresa_id' => $empresaId,
                ':usuario' => 'sherley7',
                ':senha_hash' => password_hash('123456', PASSWORD_DEFAULT),
                ':nome' => 'Sherley',
                ':tipo' => UserTypes::USUARIO,
            ]);
            $sherleyId = (int) $this->db->lastInsertId();
        } else {
            $sherleyId = (int) $sherleyId;
            $this->db->prepare(
                'UPDATE usuarios
                 SET ativo = 1, tipo = :tipo, empresa_id = :empresa_id, compartilhando = 1, perm_usuarios = 0
                 WHERE id = :id'
            )->execute([
                ':id' => $sherleyId,
                ':tipo' => UserTypes::USUARIO,
                ':empresa_id' => $empresaId,
            ]);
        }

        $papiId = (int) ($this->db->query("SELECT id FROM usuarios WHERE usuario = 'papijunior' LIMIT 1")->fetchColumn() ?: 0);

        $add = $this->db->prepare(
            'INSERT IGNORE INTO usuario_grupo (usuario_id, grupo_id) VALUES (:usuario_id, :grupo_id)'
        );
        if ($papiId > 0) {
            $add->execute([':usuario_id' => $papiId, ':grupo_id' => $grupoId]);
        }
        $add->execute([':usuario_id' => $sherleyId, ':grupo_id' => $grupoId]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function authenticate(string $usuario, string $senha): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, empresa_id, usuario, senha_hash, nome, foto, telefone, tipo,
                    compartilhando, participa_escala, ativo, perm_usuarios
             FROM usuarios
             WHERE usuario = :usuario
             LIMIT 1'
        );
        $stmt->execute([':usuario' => trim($usuario)]);
        $row = $stmt->fetch();

        if ($row === false || empty($row['ativo'])) {
            return null;
        }

        if (!password_verify($senha, (string) $row['senha_hash'])) {
            return null;
        }

        unset($row['senha_hash']);

        return $row;
    }

    public function changeOwnPassword(int $userId, string $senhaAtual, string $novaSenha): void
    {
        $novaSenha = trim($novaSenha);
        if (strlen($novaSenha) < 6) {
            throw new RuntimeException('A nova senha precisa ter pelo menos 6 caracteres.');
        }
        if ($senhaAtual === '') {
            throw new RuntimeException('Informe a senha atual.');
        }
        if ($senhaAtual === $novaSenha) {
            throw new RuntimeException('A nova senha precisa ser diferente da atual.');
        }

        $stmt = $this->db->prepare(
            'SELECT senha_hash FROM usuarios WHERE id = :id AND ativo = 1 LIMIT 1'
        );
        $stmt->execute([':id' => $userId]);
        $hash = $stmt->fetchColumn();
        if ($hash === false) {
            throw new RuntimeException('Usuário não encontrado.');
        }
        if (!password_verify($senhaAtual, (string) $hash)) {
            throw new RuntimeException('Senha atual incorreta.');
        }

        $upd = $this->db->prepare(
            'UPDATE usuarios SET senha_hash = :senha_hash WHERE id = :id'
        );
        $upd->execute([
            ':id' => $userId,
            ':senha_hash' => password_hash($novaSenha, PASSWORD_DEFAULT),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listUsers(?int $empresaId = null): array
    {
        if ($empresaId !== null) {
            $stmt = $this->db->prepare(
                'SELECT u.id, u.empresa_id, u.usuario, u.nome, u.foto, u.telefone, u.tipo,
                        u.compartilhando, u.participa_escala, u.ativo, u.perm_usuarios, u.criado_em, u.atualizado_em,
                        GROUP_CONCAT(DISTINCT g.nome ORDER BY g.nome SEPARATOR ", ") AS grupos
                 FROM usuarios u
                 LEFT JOIN usuario_grupo ug ON ug.usuario_id = u.id
                 LEFT JOIN grupos g ON g.id = ug.grupo_id
                 WHERE u.empresa_id = :empresa_id
                 GROUP BY u.id
                 ORDER BY u.usuario ASC'
            );
            $stmt->execute([':empresa_id' => $empresaId]);
            return $stmt->fetchAll();
        }

        $stmt = $this->db->query(
            'SELECT u.id, u.empresa_id, u.usuario, u.nome, u.foto, u.telefone, u.tipo,
                    u.compartilhando, u.participa_escala, u.ativo, u.perm_usuarios, u.criado_em, u.atualizado_em,
                    GROUP_CONCAT(DISTINCT g.nome ORDER BY g.nome SEPARATOR ", ") AS grupos
             FROM usuarios u
             LEFT JOIN usuario_grupo ug ON ug.usuario_id = u.id
             LEFT JOIN grupos g ON g.id = ug.grupo_id
             GROUP BY u.id
             ORDER BY u.usuario ASC'
        );

        return $stmt->fetchAll();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listByTipo(string $tipo, ?int $empresaId): array
    {
        $sql = 'SELECT id, usuario, nome, foto, telefone, tipo
                FROM usuarios
                WHERE ativo = 1 AND tipo = :tipo';
        $params = [':tipo' => $tipo];
        if ($empresaId !== null) {
            $sql .= ' AND empresa_id = :empresa_id';
            $params[':empresa_id'] = $empresaId;
        }
        $sql .= ' ORDER BY COALESCE(nome, usuario) ASC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listVisibleUsers(int $viewerId, bool $podeVerTodos): array
    {
        $ids = $this->visibleUserIds($viewerId, $podeVerTodos);
        if ($ids === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare(
            "SELECT id, usuario, nome, foto, telefone, tipo, compartilhando, participa_escala
             FROM usuarios
             WHERE id IN ($placeholders) AND ativo = 1
             ORDER BY COALESCE(nome, usuario) ASC"
        );
        $stmt->execute(array_values($ids));

        return $stmt->fetchAll();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, empresa_id, usuario, nome, foto, telefone, tipo, compartilhando,
                    participa_escala, ativo, perm_usuarios
             FROM usuarios WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Busca por telefone (só dígitos), aceitando com ou sem 55.
     *
     * @return array<string, mixed>|null
     */
    public function findByTelefoneDigits(string $digits): ?array
    {
        $d = preg_replace('/\D+/', '', $digits) ?? '';
        if ($d === '') {
            return null;
        }
        $variants = array_values(array_unique([
            $d,
            str_starts_with($d, '55') ? substr($d, 2) : ('55' . $d),
        ]));

        $placeholders = implode(',', array_fill(0, count($variants), '?'));
        $stmt = $this->db->prepare(
            "SELECT id, empresa_id, usuario, nome, foto, telefone, tipo, compartilhando, participa_escala, ativo, perm_usuarios
             FROM usuarios
             WHERE ativo = 1 AND telefone IN ($placeholders)
             LIMIT 1"
        );
        $stmt->execute($variants);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function canViewUser(int $viewerId, bool $podeVerTodos, int $targetId): bool
    {
        return in_array($targetId, $this->visibleUserIds($viewerId, $podeVerTodos), true);
    }

    public function setCompartilhando(int $id, bool $compartilhando): void
    {
        $this->db->prepare(
            'UPDATE usuarios SET compartilhando = :c WHERE id = :id'
        )->execute([
            ':c' => $compartilhando ? 1 : 0,
            ':id' => $id,
        ]);
    }

    /**
     * @param list<int> $grupoIds
     * @param list<int> $regiaoIds
     */
    public function create(
        string $usuario,
        string $senha,
        ?string $nome,
        ?string $telefone,
        ?string $foto,
        string $tipo,
        ?int $empresaId,
        bool $permUsuarios,
        bool $ativo,
        bool $compartilhando,
        array $grupoIds,
        array $regiaoIds,
        bool $participaEscala = false
    ): int {
        if (!UserTypes::isValid($tipo)) {
            throw new RuntimeException('Tipo de usuário inválido.');
        }

        $stmt = $this->db->prepare(
            'INSERT INTO usuarios
                (empresa_id, usuario, senha_hash, nome, telefone, foto, tipo, compartilhando, participa_escala, ativo, perm_usuarios)
             VALUES
                (:empresa_id, :usuario, :senha_hash, :nome, :telefone, :foto, :tipo, :compartilhando, :participa_escala, :ativo, :perm_usuarios)'
        );
        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':usuario' => trim($usuario),
            ':senha_hash' => password_hash($senha, PASSWORD_DEFAULT),
            ':nome' => $nome !== null && trim($nome) !== '' ? trim($nome) : null,
            ':telefone' => self::normalizeTelefone($telefone),
            ':foto' => $foto,
            ':tipo' => $tipo,
            ':compartilhando' => $compartilhando ? 1 : 0,
            ':participa_escala' => $participaEscala ? 1 : 0,
            ':ativo' => $ativo ? 1 : 0,
            ':perm_usuarios' => (UserTypes::syncPermUsuarios($tipo) || $permUsuarios) ? 1 : 0,
        ]);

        $id = (int) $this->db->lastInsertId();
        $this->syncGrupos($id, $grupoIds);
        (new RegiaoRepository($this->db))->syncUsuarioRegioes($id, $regiaoIds);

        return $id;
    }

    /**
     * @param list<int> $grupoIds
     * @param list<int> $regiaoIds
     */
    public function update(
        int $id,
        string $usuario,
        ?string $nome,
        ?string $telefone,
        ?string $foto,
        string $tipo,
        ?int $empresaId,
        bool $permUsuarios,
        bool $ativo,
        bool $compartilhando,
        array $grupoIds,
        array $regiaoIds,
        ?string $novaSenha = null,
        bool $participaEscala = false
    ): void {
        if (!UserTypes::isValid($tipo)) {
            throw new RuntimeException('Tipo de usuário inválido.');
        }

        $perm = UserTypes::syncPermUsuarios($tipo) || $permUsuarios;

        if ($novaSenha !== null && $novaSenha !== '') {
            $stmt = $this->db->prepare(
                'UPDATE usuarios
                 SET empresa_id = :empresa_id, usuario = :usuario, nome = :nome, telefone = :telefone,
                     foto = :foto, tipo = :tipo, compartilhando = :compartilhando,
                     participa_escala = :participa_escala, ativo = :ativo, perm_usuarios = :perm_usuarios,
                     senha_hash = :senha_hash
                 WHERE id = :id'
            );
            $stmt->execute([
                ':id' => $id,
                ':empresa_id' => $empresaId,
                ':usuario' => trim($usuario),
                ':nome' => $nome !== null && trim($nome) !== '' ? trim($nome) : null,
                ':telefone' => self::normalizeTelefone($telefone),
                ':foto' => $foto,
                ':tipo' => $tipo,
                ':compartilhando' => $compartilhando ? 1 : 0,
                ':participa_escala' => $participaEscala ? 1 : 0,
                ':ativo' => $ativo ? 1 : 0,
                ':perm_usuarios' => $perm ? 1 : 0,
                ':senha_hash' => password_hash($novaSenha, PASSWORD_DEFAULT),
            ]);
        } else {
            $stmt = $this->db->prepare(
                'UPDATE usuarios
                 SET empresa_id = :empresa_id, usuario = :usuario, nome = :nome, telefone = :telefone,
                     foto = :foto, tipo = :tipo, compartilhando = :compartilhando,
                     participa_escala = :participa_escala, ativo = :ativo, perm_usuarios = :perm_usuarios
                 WHERE id = :id'
            );
            $stmt->execute([
                ':id' => $id,
                ':empresa_id' => $empresaId,
                ':usuario' => trim($usuario),
                ':nome' => $nome !== null && trim($nome) !== '' ? trim($nome) : null,
                ':telefone' => self::normalizeTelefone($telefone),
                ':foto' => $foto,
                ':tipo' => $tipo,
                ':compartilhando' => $compartilhando ? 1 : 0,
                ':participa_escala' => $participaEscala ? 1 : 0,
                ':ativo' => $ativo ? 1 : 0,
                ':perm_usuarios' => $perm ? 1 : 0,
            ]);
        }

        $this->syncGrupos($id, $grupoIds);
        (new RegiaoRepository($this->db))->syncUsuarioRegioes($id, $regiaoIds);
    }

    public function delete(int $id): void
    {
        $user = $this->find($id);
        if ($user !== null && !empty($user['foto'])) {
            PhotoUpload::delete((string) $user['foto']);
        }

        $stmt = $this->db->prepare('DELETE FROM usuarios WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    /**
     * @return list<int>
     */
    public function grupoIdsDoUsuario(int $usuarioId): array
    {
        $stmt = $this->db->prepare(
            'SELECT grupo_id FROM usuario_grupo WHERE usuario_id = :id ORDER BY grupo_id'
        );
        $stmt->execute([':id' => $usuarioId]);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * Visibilidade no mapa/histórico.
     * Gestor: todos da empresa. Master/usuário: quem compartilha grupo + eu.
     *
     * @return list<int>
     */
    public function visibleUserIds(int $viewerId, bool $podeVerTodos): array
    {
        $viewer = $this->find($viewerId);
        if ($viewer === null) {
            return [];
        }

        $tipo = UserTypes::normalize((string) ($viewer['tipo'] ?? UserTypes::USUARIO));
        $empresaId = isset($viewer['empresa_id']) ? (int) $viewer['empresa_id'] : null;

        if ($podeVerTodos || $tipo === UserTypes::GESTOR) {
            if ($empresaId) {
                $stmt = $this->db->prepare(
                    'SELECT id FROM usuarios WHERE ativo = 1 AND empresa_id = :empresa_id ORDER BY id'
                );
                $stmt->execute([':empresa_id' => $empresaId]);
            } else {
                $stmt = $this->db->query('SELECT id FROM usuarios WHERE ativo = 1 ORDER BY id');
            }
            return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        }

        $stmt = $this->db->prepare(
            'SELECT DISTINCT u.id
             FROM usuarios u
             INNER JOIN usuario_grupo ug1 ON ug1.usuario_id = u.id
             INNER JOIN usuario_grupo ug2 ON ug2.grupo_id = ug1.grupo_id AND ug2.usuario_id = :viewer
             WHERE u.ativo = 1
             UNION
             SELECT :viewer2
             ORDER BY 1'
        );
        $stmt->execute([
            ':viewer' => $viewerId,
            ':viewer2' => $viewerId,
        ]);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * Lista usuários que o ator pode gerenciar (master: só do seus grupos).
     *
     * @return list<array<string, mixed>>
     */
    public function listUsersForManager(int $managerId, bool $isGestor, ?int $empresaId = null): array
    {
        if ($isGestor) {
            return $this->listUsers($empresaId);
        }

        $ids = $this->visibleUserIds($managerId, false);
        if ($ids === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "SELECT u.id, u.empresa_id, u.usuario, u.nome, u.foto, u.telefone, u.tipo,
                       u.compartilhando, u.participa_escala, u.ativo, u.perm_usuarios, u.criado_em, u.atualizado_em,
                       GROUP_CONCAT(DISTINCT g.nome ORDER BY g.nome SEPARATOR ', ') AS grupos
                FROM usuarios u
                LEFT JOIN usuario_grupo ug ON ug.usuario_id = u.id
                LEFT JOIN grupos g ON g.id = ug.grupo_id
                WHERE u.id IN ($placeholders)
                GROUP BY u.id
                ORDER BY u.usuario ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_values($ids));
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Dentre os IDs, quais participam de escala.
     *
     * @param list<int> $usuarioIds
     * @return list<int>
     */
    public function idsParticipamEscala(array $usuarioIds): array
    {
        $usuarioIds = array_values(array_unique(array_filter(array_map('intval', $usuarioIds), static fn (int $id): bool => $id > 0)));
        if ($usuarioIds === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($usuarioIds), '?'));
        $stmt = $this->db->prepare(
            "SELECT id FROM usuarios
             WHERE ativo = 1 AND participa_escala = 1 AND id IN ($placeholders)"
        );
        $stmt->execute($usuarioIds);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    /**
     * Usuários elegíveis para cadastro em escalas (check participa_escala),
     * restritos a um conjunto candidato (ex.: visíveis do gestor/master).
     *
     * @param list<int> $candidateIds
     * @return list<array<string, mixed>>
     */
    public function listEscalaveisAmong(array $candidateIds): array
    {
        $ids = $this->idsParticipamEscala($candidateIds);
        if ($ids === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare(
            "SELECT id, usuario, nome, foto, telefone, tipo, compartilhando, participa_escala
             FROM usuarios
             WHERE id IN ($placeholders) AND ativo = 1
             ORDER BY COALESCE(nome, usuario) ASC"
        );
        $stmt->execute(array_values($ids));
        return $stmt->fetchAll() ?: [];
    }

    /**
     * No mapa: quem tem participa_escala só entra se estiver em escala agora.
     *
     * @param list<int> $ids
     * @return list<int>
     */
    public function filterIdsVisiveisNoMapa(array $ids, EscalaRepository $escalas): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return [];
        }
        $escalaveis = $this->idsParticipamEscala($ids);
        if ($escalaveis === []) {
            return $ids;
        }
        $emEscala = $escalas->usuariosEmEscalaAgora($escalaveis);
        $livres = array_values(array_diff($ids, $escalaveis));
        return array_values(array_unique(array_merge($livres, $emEscala)));
    }

    public static function normalizeTelefone(?string $telefone): ?string
    {
        if ($telefone === null) {
            return null;
        }
        $digits = preg_replace('/\D+/', '', $telefone) ?? '';
        if ($digits === '') {
            return null;
        }
        return $digits;
    }

    /**
     * @param list<int> $grupoIds
     */
    private function syncGrupos(int $usuarioId, array $grupoIds): void
    {
        $this->db->prepare('DELETE FROM usuario_grupo WHERE usuario_id = :id')
            ->execute([':id' => $usuarioId]);

        $ids = array_values(array_unique(array_filter(array_map('intval', $grupoIds), static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return;
        }

        $ins = $this->db->prepare(
            'INSERT INTO usuario_grupo (usuario_id, grupo_id) VALUES (:usuario_id, :grupo_id)'
        );
        foreach ($ids as $grupoId) {
            $ins->execute([
                ':usuario_id' => $usuarioId,
                ':grupo_id' => $grupoId,
            ]);
        }
    }
}
