<?php

namespace App\Domain\Shared\Enums;

enum TypeAnomalie: string
{
    case Intrusion = 'intrusion';
    case Vol = 'vol';
    case Incendie = 'incendie';
    case Technique = 'technique';
    case Comportement = 'comportement';
    /** Générée automatiquement quand un contrôle terrain constate un agent absent de son poste. */
    case AbsencePoste = 'absence_poste';
    case Autre = 'autre';
}
