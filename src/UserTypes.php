<?php

declare(strict_types=1);

/**
 * Tipos: gestor | usuario_master | usuario.
 * Família, amigos, ronda etc. são definidos pelos grupos.
 */
final class UserTypes
{
    public const GESTOR = 'gestor';
    public const USUARIO_MASTER = 'usuario_master';
    public const USUARIO = 'usuario';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [self::GESTOR, self::USUARIO_MASTER, self::USUARIO];
    }

    public static function label(string $tipo): string
    {
        return match (self::normalize($tipo)) {
            self::GESTOR => 'Gestor',
            self::USUARIO_MASTER => 'Usuário master',
            default => 'Usuário',
        };
    }

    public static function isValid(string $tipo): bool
    {
        return in_array($tipo, self::all(), true);
    }

    /** Converte tipos antigos para o modelo atual. */
    public static function normalize(string $tipo): string
    {
        $tipo = trim(strtolower($tipo));
        return match ($tipo) {
            self::GESTOR, 'admin' => self::GESTOR,
            self::USUARIO_MASTER, 'master', 'usuario master' => self::USUARIO_MASTER,
            default => self::USUARIO,
        };
    }

    /** Gestor e master podem gerenciar cadastros operacionais. */
    public static function podeGerenciarOperacional(string $tipo): bool
    {
        $t = self::normalize($tipo);
        return $t === self::GESTOR || $t === self::USUARIO_MASTER;
    }

    public static function syncPermUsuarios(string $tipo): bool
    {
        return self::podeGerenciarOperacional($tipo);
    }

    /**
     * Tipos que o ator pode atribuir ao criar/editar usuários.
     *
     * @return list<string>
     */
    public static function tiposAtribuiveisPor(string $atorTipo): array
    {
        return match (self::normalize($atorTipo)) {
            self::GESTOR => self::all(),
            self::USUARIO_MASTER => [self::USUARIO_MASTER, self::USUARIO],
            default => [self::USUARIO],
        };
    }
}
