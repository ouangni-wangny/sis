<?php

namespace App\Domain\Shared\Enums;

enum GraviteAnomalie: string
{
    case Basse = 'basse';
    case Moyenne = 'moyenne';
    case Haute = 'haute';
    case Critique = 'critique';
}
