<?php

declare(strict_types=1);

final class GroupRepository
{
    public function __construct(private PDO $db)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listGroups(): array
    {
        $stmt = $this->db->query(
            'SELECT g.id, g.nome, g.modo, g.criado_em, g.atualizado_em,
                    COUNT(ug.usuario_id) AS membros
             FROM grupos g
             LEFT JOIN usuario_grupo ug ON ug.grupo_id = g.id
             GROUP BY g.id
             ORDER BY g.nome ASC'
        );

        return $stmt->fetchAll();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, nome, modo FROM grupos WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function create(string $nome, string $modo = GroupModes::SOCIAL, ?int $criadorId = null): int
    {
        $nome = trim($nome);
        if ($nome === '') {
            throw new RuntimeException('Informe o nome do grupo.');
        }
        if (!GroupModes::isValid($modo)) {
            $modo = GroupModes::SOCIAL;
        }

        $stmt = $this->db->prepare('INSERT INTO grupos (nome, modo) VALUES (:nome, :modo)');
        $stmt->execute([':nome' => $nome, ':modo' => $modo]);
        $id = (int) $this->db->lastInsertId();

        if ($criadorId !== null && $criadorId > 0) {
            $this->addMember($id, $criadorId);
        }

        return $id;
    }

    public function addMember(int $grupoId, int $usuarioId): void
    {
        $stmt = $this->db->prepare(
            'INSERT IGNORE INTO usuario_grupo (usuario_id, grupo_id) VALUES (:usuario_id, :grupo_id)'
        );
        $stmt->execute([':usuario_id' => $usuarioId, ':grupo_id' => $grupoId]);
    }

    public function viewerCanManageGrupo(int $viewerId, int $grupoId, bool $isGestor): bool
    {
        if ($isGestor) {
            return $this->find($grupoId) !== null;
        }
        $stmt = $this->db->prepare(
            'SELECT 1 FROM usuario_grupo WHERE usuario_id = :u AND grupo_id = :g LIMIT 1'
        );
        $stmt->execute([':u' => $viewerId, ':g' => $grupoId]);
        return (bool) $stmt->fetchColumn();
    }

    public function update(int $id, string $nome, string $modo = GroupModes::SOCIAL): void
    {
        $nome = trim($nome);
        if ($nome === '') {
            throw new RuntimeException('Informe o nome do grupo.');
        }
        if (!GroupModes::isValid($modo)) {
            $modo = GroupModes::SOCIAL;
        }

        $stmt = $this->db->prepare('UPDATE grupos SET nome = :nome, modo = :modo WHERE id = :id');
        $stmt->execute([':id' => $id, ':nome' => $nome, ':modo' => $modo]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM grupos WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    /**
     * @return list<int>
     */
    public function userIdsInGrupo(int $grupoId): array
    {
        $stmt = $this->db->prepare(
            'SELECT usuario_id FROM usuario_grupo WHERE grupo_id = :grupo_id'
        );
        $stmt->execute([':grupo_id' => $grupoId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listGroupsForViewer(int $viewerId, bool $podeVerTodos): array
    {
        if ($podeVerTodos) {
            return $this->listGroups();
        }

        $stmt = $this->db->prepare(
            'SELECT g.id, g.nome, g.modo, g.criado_em, g.atualizado_em,
                    COUNT(ug2.usuario_id) AS membros
             FROM grupos g
             INNER JOIN usuario_grupo ug ON ug.grupo_id = g.id AND ug.usuario_id = :viewer
             LEFT JOIN usuario_grupo ug2 ON ug2.grupo_id = g.id
             GROUP BY g.id
             ORDER BY g.nome ASC'
        );
        $stmt->execute([':viewer' => $viewerId]);
        return $stmt->fetchAll();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByNome(string $nome): ?array
    {
        $stmt = $this->db->prepare('SELECT id, nome, modo FROM grupos WHERE nome = :nome LIMIT 1');
        $stmt->execute([':nome' => trim($nome)]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /**
     * @return list<array{id:int, nome:string}>
     */
    public function membros(int $grupoId): array
    {
        $stmt = $this->db->prepare(
            'SELECT u.id, u.usuario, u.nome
             FROM usuario_grupo ug
             INNER JOIN usuarios u ON u.id = ug.usuario_id
             WHERE ug.grupo_id = :grupo_id
             ORDER BY u.usuario ASC'
        );
        $stmt->execute([':grupo_id' => $grupoId]);

        return $stmt->fetchAll();
    }
}
