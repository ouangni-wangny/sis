<?php

namespace App\Domain\Shared\Enums;

enum TypeAgent: string
{
    case Agent = 'agent';
    case Controleur = 'controleur';
    case Administration = 'administration';
}
