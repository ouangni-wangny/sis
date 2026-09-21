<?php

namespace App\Domain\Shared\Enums;

enum StatutUser: string
{
    case Actif = 'actif';
    case Inactif = 'inactif';
    case Bloque = 'bloque';
}
