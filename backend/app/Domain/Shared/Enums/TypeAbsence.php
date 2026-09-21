<?php

namespace App\Domain\Shared\Enums;

enum TypeAbsence: string
{
    case Conge = 'conge';
    case Maladie = 'maladie';
    case Permission = 'permission';
    case Autre = 'autre';
}
