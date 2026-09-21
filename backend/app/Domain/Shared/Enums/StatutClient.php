<?php

namespace App\Domain\Shared\Enums;

enum StatutClient: string
{
    case Actif = 'actif';
    case Resilie = 'resilie';
    case Suspendu = 'suspendu';
}
