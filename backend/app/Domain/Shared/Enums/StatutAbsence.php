<?php

namespace App\Domain\Shared\Enums;

enum StatutAbsence: string
{
    case EnAttente = 'en_attente';
    case Approuvee = 'approuvee';
    case Refusee = 'refusee';
    case Annulee = 'annulee';
}
