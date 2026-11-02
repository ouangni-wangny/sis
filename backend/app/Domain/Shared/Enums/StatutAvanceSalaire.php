<?php

namespace App\Domain\Shared\Enums;

enum StatutAvanceSalaire: string
{
    /** Versée, pas encore retenue sur un bulletin payé. */
    case EnCours = 'en_cours';
    /** Retenue sur le bulletin réglé du mois. */
    case Deduite = 'deduite';
    case Annulee = 'annulee';
}
