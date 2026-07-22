<?php

declare(strict_types=1);

final class RegiaoRepository
{
    public function __construct(private PDO $db)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listByEmpresa(?int $empresaId): array
    {
        if ($empresaId === null) {
            $stmt = $this->db->query(
                'SELECT * FROM regioes ORDER BY bairro ASC, rua ASC, nome ASC'
            );
            return $stmt->fetchAll();
        }

        $stmt = $this->db->prepare(
            'SELECT * FROM regioes
             WHERE empresa_id = :empresa_id
             ORDER BY bairro ASC, rua ASC, nome ASC'
        );
        $stmt->execute([':empresa_id' => $empresaId]);
        return $stmt->fetchAll();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM regioes WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function create(
        int $empresaId,
        string $nome,
        ?string $bairro,
        ?string $rua,
        ?string $complemento,
        ?string $cidade,
        bool $ativo = true
    ): int {
        $nome = trim($nome);
        if ($nome === '') {
            throw new RuntimeException('Informe o nome da região.');
        }

        $stmt = $this->db->prepare(
            'INSERT INTO regioes (empresa_id, nome, bairro, rua, complemento, cidade, ativo)
             VALUES (:empresa_id, :nome, :bairro, :rua, :complemento, :cidade, :ativo)'
        );
        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':nome' => $nome,
            ':bairro' => self::nullIfEmpty($bairro),
            ':rua' => self::nullIfEmpty($rua),
            ':complemento' => self::nullIfEmpty($complemento),
            ':cidade' => self::nullIfEmpty($cidade),
            ':ativo' => $ativo ? 1 : 0,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function update(
        int $id,
        string $nome,
        ?string $bairro,
        ?string $rua,
        ?string $complemento,
        ?string $cidade,
        bool $ativo
    ): void {
        $nome = trim($nome);
        if ($nome === '') {
            throw new RuntimeException('Informe o nome da região.');
        }

        $stmt = $this->db->prepare(
            'UPDATE regioes
             SET nome = :nome, bairro = :bairro, rua = :rua, complemento = :complemento,
                 cidade = :cidade, ativo = :ativo
             WHERE id = :id'
        );
        $stmt->execute([
            ':id' => $id,
            ':nome' => $nome,
            ':bairro' => self::nullIfEmpty($bairro),
            ':rua' => self::nullIfEmpty($rua),
            ':complemento' => self::nullIfEmpty($complemento),
            ':cidade' => self::nullIfEmpty($cidade),
            ':ativo' => $ativo ? 1 : 0,
        ]);
    }

    public function delete(int $id): void
    {
        $this->db->prepare('DELETE FROM regioes WHERE id = :id')->execute([':id' => $id]);
    }

    /**
     * @return list<int>
     */
    public function regioesDoUsuario(int $usuarioId): array
    {
        $stmt = $this->db->prepare(
            'SELECT regiao_id FROM usuario_regiao WHERE usuario_id = :id ORDER BY regiao_id'
        );
        $stmt->execute([':id' => $usuarioId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * @param list<int> $regiaoIds
     */
    public function syncUsuarioRegioes(int $usuarioId, array $regiaoIds): void
    {
        $this->db->prepare('DELETE FROM usuario_regiao WHERE usuario_id = :id')
            ->execute([':id' => $usuarioId]);

        $ids = array_values(array_unique(array_filter(array_map('intval', $regiaoIds), static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return;
        }

        $ins = $this->db->prepare(
            'INSERT INTO usuario_regiao (usuario_id, regiao_id) VALUES (:usuario_id, :regiao_id)'
        );
        foreach ($ids as $regiaoId) {
            $ins->execute([':usuario_id' => $usuarioId, ':regiao_id' => $regiaoId]);
        }
    }

    public function label(array $regiao): string
    {
        $parts = array_filter([
            $regiao['nome'] ?? null,
            $regiao['bairro'] ?? null,
            $regiao['rua'] ?? null,
        ], static fn ($v) => $v !== null && trim((string) $v) !== '');
        return implode(' — ', array_map('strval', $parts));
    }

    private static function nullIfEmpty(?string $v): ?string
    {
        if ($v === null) {
            return null;
        }
        $t = trim($v);
        return $t === '' ? null : $t;
    }
}
