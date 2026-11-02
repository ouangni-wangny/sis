<?php

namespace App\Domain\Shared\Enums;

enum Genre: string
{
    case Homme = 'homme';
    case Femme = 'femme';

    public static function depuisCivilite(Civilite $civilite): self
    {
        return $civilite === Civilite::Monsieur ? self::Homme : self::Femme;
    }
}
