<?php

declare(strict_types=1);

final class EscalaRepository
{
    public function __construct(private PDO $db)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listByEmpresa(?int $empresaId, ?string $de = null, ?string $ate = null): array
    {
        $sql = 'SELECT e.*, u.usuario, u.nome AS usuario_nome, r.nome AS regiao_nome, r.bairro, r.rua
                FROM escalas e
                INNER JOIN usuarios u ON u.id = e.usuario_id
                INNER JOIN regioes r ON r.id = e.regiao_id
                WHERE 1=1';
        $params = [];

        if ($empresaId !== null) {
            $sql .= ' AND e.empresa_id = :empresa_id';
            $params[':empresa_id'] = $empresaId;
        }
        if ($de !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $de)) {
            $sql .= ' AND e.data >= :de';
            $params[':de'] = $de;
        }
        if ($ate !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $ate)) {
            $sql .= ' AND e.data <= :ate';
            $params[':ate'] = $ate;
        }

        $sql .= ' ORDER BY e.data DESC, e.hora_inicio ASC, e.id DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM escalas WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function create(
        int $empresaId,
        int $usuarioId,
        int $regiaoId,
        string $data,
        string $horaInicio,
        string $horaFim,
        bool $ativo = true
    ): int {
        $this->assertHorario($data, $horaInicio, $horaFim);

        $stmt = $this->db->prepare(
            'INSERT INTO escalas (empresa_id, usuario_id, regiao_id, data, hora_inicio, hora_fim, ativo)
             VALUES (:empresa_id, :usuario_id, :regiao_id, :data, :hora_inicio, :hora_fim, :ativo)'
        );
        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':usuario_id' => $usuarioId,
            ':regiao_id' => $regiaoId,
            ':data' => $data,
            ':hora_inicio' => $horaInicio,
            ':hora_fim' => $horaFim,
            ':ativo' => $ativo ? 1 : 0,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function update(
        int $id,
        int $usuarioId,
        int $regiaoId,
        string $data,
        string $horaInicio,
        string $horaFim,
        bool $ativo
    ): void {
        $this->assertHorario($data, $horaInicio, $horaFim);

        $stmt = $this->db->prepare(
            'UPDATE escalas
             SET usuario_id = :usuario_id, regiao_id = :regiao_id, data = :data,
                 hora_inicio = :hora_inicio, hora_fim = :hora_fim, ativo = :ativo
             WHERE id = :id'
        );
        $stmt->execute([
            ':id' => $id,
            ':usuario_id' => $usuarioId,
            ':regiao_id' => $regiaoId,
            ':data' => $data,
            ':hora_inicio' => $horaInicio,
            ':hora_fim' => $horaFim,
            ':ativo' => $ativo ? 1 : 0,
        ]);
    }

    public function delete(int $id): void
    {
        $this->db->prepare('DELETE FROM escalas WHERE id = :id')->execute([':id' => $id]);
    }

    /**
     * Escala ativa agora para o funcionário (considerando virada de meia-noite).
     *
     * @return array<string, mixed>|null
     */
    public function escalaAtivaAgora(int $usuarioId, ?DateTimeImmutable $agora = null): ?array
    {
        $agora ??= new DateTimeImmutable('now');
        $hoje = $agora->format('Y-m-d');
        $ontem = $agora->modify('-1 day')->format('Y-m-d');
        $hora = $agora->format('H:i:s');

        $stmt = $this->db->prepare(
            'SELECT e.*, r.nome AS regiao_nome, r.bairro, r.rua
             FROM escalas e
             INNER JOIN regioes r ON r.id = e.regiao_id
             WHERE e.usuario_id = :usuario_id
               AND e.ativo = 1
               AND (
                    (e.data = :hoje AND (
                        (e.hora_inicio <= e.hora_fim AND :hora1 BETWEEN e.hora_inicio AND e.hora_fim)
                        OR (e.hora_inicio > e.hora_fim AND (:hora2 >= e.hora_inicio OR :hora3 <= e.hora_fim))
                    ))
                    OR
                    (e.data = :ontem AND e.hora_inicio > e.hora_fim AND :hora4 <= e.hora_fim)
               )
             ORDER BY e.id DESC
             LIMIT 1'
        );
        $stmt->execute([
            ':usuario_id' => $usuarioId,
            ':hoje' => $hoje,
            ':ontem' => $ontem,
            ':hora1' => $hora,
            ':hora2' => $hora,
            ':hora3' => $hora,
            ':hora4' => $hora,
        ]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function podeRastrearAgora(int $usuarioId, string $tipo, bool $compartilhando, bool $participaEscala = false): bool
    {
        unset($tipo);
        if (!$compartilhando) {
            return false;
        }
        // Quem participa de escala só grava/aparece no horário da escala
        if ($participaEscala) {
            return $this->escalaAtivaAgora($usuarioId) !== null;
        }
        return true;
    }

    /**
     * IDs de usuários com escala ativa agora (qualquer tipo).
     *
     * @param list<int> $usuarioIds filtro opcional; vazio = todos em escala
     * @return list<int>
     */
    public function usuariosEmEscalaAgora(?array $usuarioIds = null, ?DateTimeImmutable $agora = null): array
    {
        $agora ??= new DateTimeImmutable('now');
        $hoje = $agora->format('Y-m-d');
        $ontem = $agora->modify('-1 day')->format('Y-m-d');
        $hora = $agora->format('H:i:s');

        $sql = "SELECT DISTINCT e.usuario_id
                FROM escalas e
                INNER JOIN usuarios u ON u.id = e.usuario_id AND u.ativo = 1
                WHERE e.ativo = 1
                  AND (
                    (e.data = ? AND (
                        (e.hora_inicio <= e.hora_fim AND ? BETWEEN e.hora_inicio AND e.hora_fim)
                        OR (e.hora_inicio > e.hora_fim AND (? >= e.hora_inicio OR ? <= e.hora_fim))
                    ))
                    OR
                    (e.data = ? AND e.hora_inicio > e.hora_fim AND ? <= e.hora_fim)
                  )";
        $params = [$hoje, $hora, $hora, $hora, $ontem, $hora];

        if ($usuarioIds !== null) {
            $usuarioIds = array_values(array_unique(array_filter(array_map('intval', $usuarioIds), static fn (int $id): bool => $id > 0)));
            if ($usuarioIds === []) {
                return [];
            }
            $placeholders = implode(',', array_fill(0, count($usuarioIds), '?'));
            $sql .= " AND e.usuario_id IN ($placeholders)";
            $params = array_merge($params, $usuarioIds);
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * @param list<int> $regiaoIds
     * @return list<int>
     */
    public function funcionariosAtivosNasRegioes(array $regiaoIds, ?DateTimeImmutable $agora = null): array
    {
        $regiaoIds = array_values(array_unique(array_filter(array_map('intval', $regiaoIds), static fn (int $id): bool => $id > 0)));
        if ($regiaoIds === []) {
            return [];
        }

        $agora ??= new DateTimeImmutable('now');
        $hoje = $agora->format('Y-m-d');
        $ontem = $agora->modify('-1 day')->format('Y-m-d');
        $hora = $agora->format('H:i:s');

        $placeholders = implode(',', array_fill(0, count($regiaoIds), '?'));
        $sql = "SELECT DISTINCT e.usuario_id
                FROM escalas e
                INNER JOIN usuarios u ON u.id = e.usuario_id AND u.ativo = 1
                WHERE e.ativo = 1
                  AND e.regiao_id IN ($placeholders)
                  AND (
                    (e.data = ? AND (
                        (e.hora_inicio <= e.hora_fim AND ? BETWEEN e.hora_inicio AND e.hora_fim)
                        OR (e.hora_inicio > e.hora_fim AND (? >= e.hora_inicio OR ? <= e.hora_fim))
                    ))
                    OR
                    (e.data = ? AND e.hora_inicio > e.hora_fim AND ? <= e.hora_fim)
                  )";

        $params = array_merge($regiaoIds, [$hoje, $hora, $hora, $hora, $ontem, $hora]);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    private function assertHorario(string $data, string $horaInicio, string $horaFim): void
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
            throw new RuntimeException('Data da escala inválida.');
        }
        if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $horaInicio) || !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $horaFim)) {
            throw new RuntimeException('Informe horário inicial e final válidos.');
        }
    }
}
