<?php

declare(strict_types=1);

final class LocationRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function save(
        int $usuarioId,
        float $latitude,
        float $longitude,
        ?string $endereco,
        ?string $ip,
        ?string $dispositivo
    ): void {
        $stmt = $this->db->prepare(
            'INSERT INTO localizacoes (usuario_id, latitude, longitude, endereco, ip, dispositivo)
             VALUES (:usuario_id, :latitude, :longitude, :endereco, :ip, :dispositivo)'
        );
        $stmt->execute([
            ':usuario_id' => $usuarioId,
            ':latitude' => $latitude,
            ':longitude' => $longitude,
            ':endereco' => $endereco !== null && trim($endereco) !== '' ? trim($endereco) : null,
            ':ip' => $ip,
            ':dispositivo' => $dispositivo !== null ? mb_substr($dispositivo, 0, 120) : null,
        ]);
    }

    /**
     * Última localização de cada usuário visível.
     * Se $onlineMinutos > 0, só quem atualizou recentemente (ainda “no mapa”).
     *
     * @param list<int> $usuarioIds
     * @return list<array<string, mixed>>
     */
    public function latestForUsers(array $usuarioIds, ?int $viewerId = null, int $onlineMinutos = 0): array
    {
        if ($usuarioIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($usuarioIds), '?'));
        $onlineMinutos = max(0, min(120, $onlineMinutos));

        $sql = "SELECT l.usuario_id, u.usuario, u.nome, u.foto, u.telefone, u.tipo, u.compartilhando,
                       l.latitude, l.longitude, l.endereco, l.ip, l.dispositivo, l.criado_em,
                       TIMESTAMPDIFF(SECOND, l.criado_em, NOW()) AS segundos_atras
                FROM localizacoes l
                INNER JOIN usuarios u ON u.id = l.usuario_id
                INNER JOIN (
                    SELECT usuario_id, MAX(id) AS max_id
                    FROM localizacoes
                    WHERE usuario_id IN ($placeholders)
                    GROUP BY usuario_id
                ) latest ON latest.max_id = l.id
                WHERE (u.compartilhando = 1 OR u.id = ?)";

        $params = array_values($usuarioIds);
        $params[] = $viewerId ?? 0;

        if ($onlineMinutos > 0) {
            $sql .= ' AND l.criado_em >= (NOW() - INTERVAL ' . $onlineMinutos . ' MINUTE)';
        }

        $sql .= ' ORDER BY u.usuario ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * Histórico de um dia (ordem cronológica).
     *
     * @return list<array<string, mixed>>
     */
    public function historyForDay(int $usuarioId, string $dia): array
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dia)) {
            throw new InvalidArgumentException('Data inválida.');
        }

        $stmt = $this->db->prepare(
            'SELECT id, usuario_id, latitude, longitude, endereco, ip, dispositivo, criado_em
             FROM localizacoes
             WHERE usuario_id = :usuario_id
               AND DATE(criado_em) = :dia
             ORDER BY criado_em ASC, id ASC'
        );
        $stmt->execute([
            ':usuario_id' => $usuarioId,
            ':dia' => $dia,
        ]);

        return $stmt->fetchAll();
    }

    /**
     * Dias com histórico (mais recentes primeiro).
     *
     * @return list<string>
     */
    public function daysWithHistory(int $usuarioId, int $limit = 60): array
    {
        $stmt = $this->db->prepare(
            'SELECT DATE(criado_em) AS dia
             FROM localizacoes
             WHERE usuario_id = :usuario_id
             GROUP BY DATE(criado_em)
             ORDER BY dia DESC
             LIMIT ' . max(1, min(365, $limit))
        );
        $stmt->execute([':usuario_id' => $usuarioId]);

        return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    public function purgeOlderThanDays(int $dias): int
    {
        $dias = max(1, min(3650, $dias));
        $stmt = $this->db->prepare(
            "DELETE FROM localizacoes
             WHERE criado_em < (NOW() - INTERVAL {$dias} DAY)"
        );
        $stmt->execute();

        return $stmt->rowCount();
    }

    public function countAll(): int
    {
        return (int) $this->db->query('SELECT COUNT(*) FROM localizacoes')->fetchColumn();
    }
}
