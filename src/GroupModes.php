<?php

declare(strict_types=1);

/**
 * Modo do grupo no mapa.
 * social = Amigos/família (quem está online no grupo)
 * ronda  = só quem está em escala no dia/hora atuais
 */
final class GroupModes
{
    public const SOCIAL = 'social';
    public const RONDA = 'ronda';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [self::SOCIAL, self::RONDA];
    }

    public static function label(string $modo): string
    {
        return match ($modo) {
            self::RONDA => 'Ronda (só quem está em escala agora)',
            default => 'Social (online no grupo)',
        };
    }

    public static function isValid(string $modo): bool
    {
        return in_array($modo, self::all(), true);
    }
}
