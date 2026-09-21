<?php

namespace App\Domain\Shared\Enums;

enum StatutAbonnement: string
{
    case Actif = 'actif';
    case Suspendu = 'suspendu';
    case Resilie = 'resilie';
    case Expire = 'expire';
}
