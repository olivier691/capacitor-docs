<?php

namespace App\Enums;

enum Role: string
{
    /** PDG et entourage habilité à connaître son agenda. */
    case Direction = 'direction';

    /** Service de sécurité : lecture seule et pointage des arrivées. */
    case Security = 'security';

    public function label(): string
    {
        return match ($this) {
            self::Direction => 'Direction (PDG et entourage)',
            self::Security => 'Service de sécurité',
        };
    }

    public function homeRoute(): string
    {
        return match ($this) {
            self::Direction => 'direction',
            self::Security => 'security',
        };
    }
}
