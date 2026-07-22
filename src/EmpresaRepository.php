<?php

declare(strict_types=1);

final class EmpresaRepository
{
    public function __construct(private PDO $db)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listAll(): array
    {
        $stmt = $this->db->query(
            'SELECT e.*,
                    (SELECT COUNT(*) FROM usuarios u WHERE u.empresa_id = e.id) AS usuarios
             FROM empresas e
             ORDER BY e.nome ASC'
        );
        return $stmt->fetchAll();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM empresas WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function create(string $nome, ?string $cnpj, bool $ativo = true): int
    {
        $nome = trim($nome);
        if ($nome === '') {
            throw new RuntimeException('Informe o nome da empresa.');
        }

        $stmt = $this->db->prepare(
            'INSERT INTO empresas (nome, cnpj, ativo, valido_ate)
             VALUES (:nome, :cnpj, :ativo, NULL)'
        );
        $stmt->execute([
            ':nome' => $nome,
            ':cnpj' => self::normalizeCnpj($cnpj),
            ':ativo' => $ativo ? 1 : 0,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, string $nome, ?string $cnpj, bool $ativo): void
    {
        $nome = trim($nome);
        if ($nome === '') {
            throw new RuntimeException('Informe o nome da empresa.');
        }

        $stmt = $this->db->prepare(
            'UPDATE empresas SET nome = :nome, cnpj = :cnpj, ativo = :ativo WHERE id = :id'
        );
        $stmt->execute([
            ':id' => $id,
            ':nome' => $nome,
            ':cnpj' => self::normalizeCnpj($cnpj),
            ':ativo' => $ativo ? 1 : 0,
        ]);
    }

    /**
     * Adiciona dias de crédito a partir de hoje ou do vencimento atual (o que for maior).
     */
    public function adicionarCredito(int $empresaId, int $dias, string $plano, ?string $valor, ?string $observacao, int $criadoPor): void
    {
        $dias = max(1, min(3660, $dias));
        $empresa = $this->find($empresaId);
        if ($empresa === null) {
            throw new RuntimeException('Empresa não encontrada.');
        }

        $base = new DateTimeImmutable('today');
        if (!empty($empresa['valido_ate'])) {
            $atual = new DateTimeImmutable((string) $empresa['valido_ate']);
            if ($atual > $base) {
                $base = $atual;
            }
        }
        $novo = $base->modify('+' . $dias . ' days')->format('Y-m-d');

        $this->db->prepare(
            'UPDATE empresas SET valido_ate = :valido_ate, ativo = 1 WHERE id = :id'
        )->execute([':valido_ate' => $novo, ':id' => $empresaId]);

        $ins = $this->db->prepare(
            'INSERT INTO pagamentos (empresa_id, plano, dias, valor, observacao, criado_por)
             VALUES (:empresa_id, :plano, :dias, :valor, :observacao, :criado_por)'
        );
        $ins->execute([
            ':empresa_id' => $empresaId,
            ':plano' => $plano,
            ':dias' => $dias,
            ':valor' => $valor !== null && $valor !== '' ? $valor : null,
            ':observacao' => $observacao !== null && trim($observacao) !== '' ? trim($observacao) : null,
            ':criado_por' => $criadoPor,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listPagamentos(?int $empresaId = null): array
    {
        if ($empresaId !== null) {
            $stmt = $this->db->prepare(
                'SELECT p.*, e.nome AS empresa_nome, u.usuario AS criado_por_usuario
                 FROM pagamentos p
                 INNER JOIN empresas e ON e.id = p.empresa_id
                 LEFT JOIN usuarios u ON u.id = p.criado_por
                 WHERE p.empresa_id = :empresa_id
                 ORDER BY p.criado_em DESC, p.id DESC'
            );
            $stmt->execute([':empresa_id' => $empresaId]);
            return $stmt->fetchAll();
        }

        $stmt = $this->db->query(
            'SELECT p.*, e.nome AS empresa_nome, u.usuario AS criado_por_usuario
             FROM pagamentos p
             INNER JOIN empresas e ON e.id = p.empresa_id
             LEFT JOIN usuarios u ON u.id = p.criado_por
             ORDER BY p.criado_em DESC, p.id DESC
             LIMIT 200'
        );
        return $stmt->fetchAll();
    }

    public function empresaAtivaComCredito(?int $empresaId): bool
    {
        if ($empresaId === null || $empresaId <= 0) {
            return true; // usuário sem empresa (legado/admin)
        }
        $empresa = $this->find($empresaId);
        if ($empresa === null || empty($empresa['ativo'])) {
            return false;
        }
        if (empty($empresa['valido_ate'])) {
            return false;
        }
        return (string) $empresa['valido_ate'] >= date('Y-m-d');
    }

    /**
     * Libera acesso sem pagamento (cortesia / trial).
     */
    public function liberarAcesso(int $empresaId, int $dias, int $criadoPor, ?string $observacao = null): void
    {
        $dias = max(1, min(3660, $dias));
        $obs = $observacao !== null && trim($observacao) !== ''
            ? trim($observacao)
            : 'Liberação de acesso sem crédito';

        $this->adicionarCredito(
            $empresaId,
            $dias,
            'liberacao',
            null,
            $obs,
            $criadoPor
        );
    }

    /**
     * @return array{dias:int, label:string}[]
     */
    public static function liberacoesAcesso(): array
    {
        return [
            ['dias' => 15, 'label' => '15 dias'],
            ['dias' => 30, 'label' => '1 mês'],
            ['dias' => 180, 'label' => '6 meses'],
            ['dias' => 365, 'label' => '1 ano'],
        ];
    }

    public static function normalizeCnpj(?string $cnpj): ?string
    {
        if ($cnpj === null) {
            return null;
        }
        $digits = preg_replace('/\D+/', '', $cnpj) ?? '';
        return $digits !== '' ? $digits : null;
    }

    /**
     * @return array{plano:string, dias:int, label:string}[]
     */
    public static function planos(): array
    {
        return [
            ['plano' => 'mensal', 'dias' => 30, 'label' => 'Mensal (30 dias)'],
            ['plano' => 'bimestral', 'dias' => 60, 'label' => 'Bimestral (60 dias)'],
            ['plano' => 'trimestral', 'dias' => 90, 'label' => 'Trimestral (90 dias)'],
            ['plano' => 'anual', 'dias' => 365, 'label' => 'Anual (365 dias)'],
            ['plano' => 'credito_dias', 'dias' => 0, 'label' => 'Crédito manual (informar dias)'],
        ];
    }
}
