<?php

namespace App\Domain\Shared\Enums;

enum StatutBulletinPaie: string
{
    case Brouillon = 'brouillon';
    case Valide = 'valide';
    case Paye = 'paye';
}
