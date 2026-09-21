<?php

namespace App\Domain\Shared\Enums;

enum StatutRonde: string
{
    case Planifiee = 'planifiee';
    case EnCours = 'en_cours';
    case Terminee = 'terminee';
    case Annulee = 'annulee';
}
