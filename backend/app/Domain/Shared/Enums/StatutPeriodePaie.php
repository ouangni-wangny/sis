<?php

namespace App\Domain\Shared\Enums;

enum StatutPeriodePaie: string
{
    case Brouillon = 'brouillon';
    case Validee = 'validee';
    case Cloturee = 'cloturee';
}
