<?php

namespace App\Domain\Shared\Enums;

enum TypeContrat: string
{
    case Cdi = 'cdi';
    case Cdd = 'cdd';
    case Prestation = 'prestation';
    case Stage = 'stage';
}
