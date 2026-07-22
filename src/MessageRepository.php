<?php

declare(strict_types=1);

final class MessageRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function ensureSchema(): void
    {
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS mensagens (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                empresa_id INT UNSIGNED NULL,
                de_usuario_id INT UNSIGNED NOT NULL,
                para_usuario_id INT UNSIGNED NOT NULL,
                tipo ENUM('texto','audio') NOT NULL DEFAULT 'texto',
                corpo TEXT NULL,
                audio_path VARCHAR(255) NULL,
                wa_message_id VARCHAR(128) NULL,
                wa_inbound_id VARCHAR(128) NULL,
                wa_enviado TINYINT(1) NOT NULL DEFAULT 0,
                lida TINYINT(1) NOT NULL DEFAULT 0,
                lida_em DATETIME NULL,
                criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_para_lida (para_usuario_id, lida, criado_em),
                INDEX idx_thread (de_usuario_id, para_usuario_id, criado_em),
                INDEX idx_empresa (empresa_id, criado_em)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS mensagens_notif_cursor (
                usuario_id INT UNSIGNED PRIMARY KEY,
                ultimo_visto_id INT UNSIGNED NOT NULL DEFAULT 0,
                atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function create(
        ?int $empresaId,
        int $deId,
        int $paraId,
        string $tipo,
        ?string $corpo,
        ?string $audioPath,
        ?string $waMessageId = null,
        bool $waEnviado = false
    ): array {
        $tipo = $tipo === 'audio' ? 'audio' : 'texto';
        $stmt = $this->pdo->prepare(
            'INSERT INTO mensagens
                (empresa_id, de_usuario_id, para_usuario_id, tipo, corpo, audio_path, wa_message_id, wa_enviado)
             VALUES
                (:empresa_id, :de_id, :para_id, :tipo, :corpo, :audio_path, :wa_id, :wa_enviado)'
        );
        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':de_id' => $deId,
            ':para_id' => $paraId,
            ':tipo' => $tipo,
            ':corpo' => $corpo,
            ':audio_path' => $audioPath,
            ':wa_id' => $waMessageId,
            ':wa_enviado' => $waEnviado ? 1 : 0,
        ]);

        $id = (int) $this->pdo->lastInsertId();
        return $this->findById($id) ?? ['id' => $id];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT m.*,
                    de.nome AS de_nome, de.usuario AS de_usuario, de.foto AS de_foto,
                    para.nome AS para_nome, para.usuario AS para_usuario, para.foto AS para_foto,
                    para.telefone AS para_telefone
             FROM mensagens m
             INNER JOIN usuarios de ON de.id = m.de_usuario_id
             INNER JOIN usuarios para ON para.id = m.para_usuario_id
             WHERE m.id = :id
             LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Conversas do usuário (última msg de cada thread).
     *
     * @return list<array<string, mixed>>
     */
    public function listThreads(int $usuarioId): array
    {
        $sql = "SELECT t.outro_id,
                       u.nome, u.usuario, u.foto, u.telefone,
                       m.id AS ultima_id, m.tipo, m.corpo, m.audio_path, m.criado_em,
                       m.de_usuario_id, m.lida,
                       (SELECT COUNT(*) FROM mensagens x
                         WHERE x.para_usuario_id = :me_count
                           AND x.de_usuario_id = t.outro_id
                           AND x.lida = 0) AS nao_lidas
                FROM (
                  SELECT CASE WHEN de_usuario_id = :me_a THEN para_usuario_id ELSE de_usuario_id END AS outro_id,
                         MAX(id) AS max_id
                  FROM mensagens
                  WHERE de_usuario_id = :me_b OR para_usuario_id = :me_c
                  GROUP BY outro_id
                ) t
                INNER JOIN mensagens m ON m.id = t.max_id
                INNER JOIN usuarios u ON u.id = t.outro_id
                ORDER BY m.criado_em DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':me_a' => $usuarioId,
            ':me_b' => $usuarioId,
            ':me_c' => $usuarioId,
            ':me_count' => $usuarioId,
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listThread(int $usuarioId, int $outroId, int $limit = 100): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT m.*,
                    de.nome AS de_nome, de.usuario AS de_usuario, de.foto AS de_foto
             FROM mensagens m
             INNER JOIN usuarios de ON de.id = m.de_usuario_id
             WHERE (m.de_usuario_id = :a1 AND m.para_usuario_id = :b1)
                OR (m.de_usuario_id = :b2 AND m.para_usuario_id = :a2)
             ORDER BY m.id ASC
             LIMIT ' . max(1, min(200, $limit))
        );
        $stmt->execute([
            ':a1' => $usuarioId,
            ':b1' => $outroId,
            ':a2' => $usuarioId,
            ':b2' => $outroId,
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function countUnread(int $usuarioId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM mensagens WHERE para_usuario_id = :id AND lida = 0'
        );
        $stmt->execute([':id' => $usuarioId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Mensagens novas para notificação (após cursor).
     *
     * @return list<array<string, mixed>>
     */
    public function listNewForNotify(int $usuarioId, int $afterId, int $limit = 20): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT m.id, m.tipo, m.corpo, m.criado_em, m.de_usuario_id,
                    de.nome AS de_nome, de.usuario AS de_usuario
             FROM mensagens m
             INNER JOIN usuarios de ON de.id = m.de_usuario_id
             WHERE m.para_usuario_id = :uid AND m.id > :after
             ORDER BY m.id ASC
             LIMIT ' . max(1, min(50, $limit))
        );
        $stmt->execute([':uid' => $usuarioId, ':after' => $afterId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getNotifyCursor(int $usuarioId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT ultimo_visto_id FROM mensagens_notif_cursor WHERE usuario_id = :id'
        );
        $stmt->execute([':id' => $usuarioId]);
        $v = $stmt->fetchColumn();
        return $v !== false ? (int) $v : 0;
    }

    public function setNotifyCursor(int $usuarioId, int $lastId): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO mensagens_notif_cursor (usuario_id, ultimo_visto_id)
             VALUES (:id, :last)
             ON DUPLICATE KEY UPDATE ultimo_visto_id = GREATEST(ultimo_visto_id, VALUES(ultimo_visto_id))'
        );
        $stmt->execute([':id' => $usuarioId, ':last' => $lastId]);
    }

    /**
     * Marca como lidas as mensagens recebidas de $deId (ou todas se null).
     * Retorna wa_inbound_id das que tinham inbound do Zap.
     *
     * @return list<string>
     */
    public function markRead(int $paraUsuarioId, ?int $deUsuarioId = null): array
    {
        $params = [':para' => $paraUsuarioId];
        $extra = '';
        if ($deUsuarioId !== null && $deUsuarioId > 0) {
            $extra = ' AND de_usuario_id = :de';
            $params[':de'] = $deUsuarioId;
        }

        $sel = $this->pdo->prepare(
            "SELECT id, wa_inbound_id FROM mensagens
             WHERE para_usuario_id = :para AND lida = 0{$extra}"
        );
        $sel->execute($params);
        $rows = $sel->fetchAll(PDO::FETCH_ASSOC) ?: [];

        if ($rows === []) {
            return [];
        }

        $upd = $this->pdo->prepare(
            "UPDATE mensagens SET lida = 1, lida_em = NOW()
             WHERE para_usuario_id = :para AND lida = 0{$extra}"
        );
        $upd->execute($params);

        $inbound = [];
        foreach ($rows as $r) {
            $wid = trim((string) ($r['wa_inbound_id'] ?? ''));
            if ($wid !== '') {
                $inbound[] = $wid;
            }
        }
        return $inbound;
    }

    public function attachInboundWaId(int $messageId, string $waInboundId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE mensagens SET wa_inbound_id = :w WHERE id = :id'
        );
        $stmt->execute([':w' => $waInboundId, ':id' => $messageId]);
    }

    /**
     * Para resposta via webhook: quem falou por último com este usuário no app.
     */
    public function sugerirDestinatarioResposta(int $deUsuarioId): ?int
    {
        $stmt = $this->pdo->prepare(
            'SELECT CASE
                WHEN de_usuario_id = :id1 THEN para_usuario_id
                ELSE de_usuario_id
             END AS outro
             FROM mensagens
             WHERE de_usuario_id = :id2 OR para_usuario_id = :id3
             ORDER BY id DESC
             LIMIT 1'
        );
        $stmt->execute([
            ':id1' => $deUsuarioId,
            ':id2' => $deUsuarioId,
            ':id3' => $deUsuarioId,
        ]);
        $outro = $stmt->fetchColumn();
        if ($outro !== false && (int) $outro > 0 && (int) $outro !== $deUsuarioId) {
            return (int) $outro;
        }

        // fallback: gestor da mesma empresa
        $stmt = $this->pdo->prepare(
            'SELECT g.id FROM usuarios u
             INNER JOIN usuarios g ON g.empresa_id = u.empresa_id AND g.ativo = 1
               AND g.tipo = \'gestor\'
             WHERE u.id = :id AND g.id <> u.id
             ORDER BY g.id ASC
             LIMIT 1'
        );
        $stmt->execute([':id' => $deUsuarioId]);
        $gid = $stmt->fetchColumn();
        return $gid !== false ? (int) $gid : null;
    }
}
