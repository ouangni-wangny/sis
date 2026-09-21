<?php

namespace App\Domain\Shared\Enums;

enum StatutAnomalie: string
{
    case Ouverte = 'ouverte';
    case EnCours = 'en_cours';
    case Resolue = 'resolue';
}
