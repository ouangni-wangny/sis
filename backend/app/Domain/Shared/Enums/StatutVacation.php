<?php

namespace App\Domain\Shared\Enums;

enum StatutVacation: string
{
    case Planifiee = 'planifiee';
    case EnCours = 'en_cours';
    case Terminee = 'terminee';
    case Annulee = 'annulee';
    /** Agent devenu indisponible (congé/maladie/suspension/archivage) alors
     *  que la vacation était encore planifiée ou en cours : à recouvrir. */
    case ARecouvrir = 'a_recouvrir';
}
