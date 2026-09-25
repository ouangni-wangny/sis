<?php

namespace App\Domain\Shared\Enums;

enum DirectionMouvementTresorerie: string
{
    case Entree = 'entree';
    case Sortie = 'sortie';

    public function label(): string
    {
        return match ($this) {
            self::Entree => 'Entrée',
            self::Sortie => 'Sortie',
        };
    }

    public function inverse(): self
    {
        return match ($this) {
            self::Entree => self::Sortie,
            self::Sortie => self::Entree,
        };
    }
}
