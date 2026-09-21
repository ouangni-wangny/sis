<?php

namespace App\Domain\Shared\Enums;

enum JourSemaine: string
{
    case Lundi = 'lundi';
    case Mardi = 'mardi';
    case Mercredi = 'mercredi';
    case Jeudi = 'jeudi';
    case Vendredi = 'vendredi';
    case Samedi = 'samedi';
    case Dimanche = 'dimanche';

    /** Carbon::dayOfWeek : 0 = dimanche .. 6 = samedi. */
    public static function fromCarbonDayOfWeek(int $dayOfWeek): self
    {
        return match ($dayOfWeek) {
            0 => self::Dimanche,
            1 => self::Lundi,
            2 => self::Mardi,
            3 => self::Mercredi,
            4 => self::Jeudi,
            5 => self::Vendredi,
            default => self::Samedi,
        };
    }
}
