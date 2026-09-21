<?php

namespace App\Application\Rh;

use App\Models\Absence;

final class AbsenceOperationResult
{
    public function __construct(
        public Absence $absence,
        public int $vacationsMarqueesARecouvrir = 0,
        public int $vacationsRestaurees = 0,
    ) {}
}
