<?php

namespace App\Domain\Shared\Enums;

enum TypeClient: string
{
    case Entreprise = 'entreprise';
    case Particulier = 'particulier';
}
