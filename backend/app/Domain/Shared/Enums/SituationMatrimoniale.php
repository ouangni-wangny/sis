<?php

namespace App\Domain\Shared\Enums;

enum SituationMatrimoniale: string
{
    case Celibataire = 'celibataire';
    case Marie = 'marie';
    case Divorce = 'divorce';
    case Veuf = 'veuf';
}
