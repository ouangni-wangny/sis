<?php

namespace App\Domain\Shared\Enums;

enum StatutAgent: string
{
    case Disponible = 'disponible';
    case EnActivite = 'en_activite';
    case Conge = 'conge';
    case Malade = 'malade';
    case Suspendu = 'suspendu';
    case Archive = 'archive';
}
