<?php

namespace App\Models;

use App\Domain\Shared\Traits\Auditable;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanningSemaineCreneau extends Model
{
    use Auditable, HasUuids;

    protected $table = 'planning_semaine_creneaux';

    protected $fillable = [
        'planning_semaine_id',
        'agent_id',
        'jour',
        'heure_debut',
        'heure_fin',
        'quart',
    ];

    protected function casts(): array
    {
        return [
            'jour' => 'integer',
        ];
    }

    public function planningSemaine(): BelongsTo
    {
        return $this->belongsTo(PlanningSemaine::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    /** Date calendaire du créneau dans la semaine du planning. */
    public function datePourSemaine(PlanningSemaine $semaine): string
    {
        return $semaine->lundi()
            ->copy()
            ->addDays($this->jour - 1)
            ->toDateString();
    }

    public function dateFinPourSemaine(PlanningSemaine $semaine): string
    {
        $debut = Carbon::parse($this->datePourSemaine($semaine).' '.$this->heureDebutHm(), 'Africa/Abidjan');
        $finHm = $this->heureFinHm();
        $fin = $debut->copy()->setTimeFromTimeString($finHm);
        if ($fin->lte($debut)) {
            $fin->addDay();
        }

        return $fin->toDateString();
    }

    public function heureDebutHm(): string
    {
        return substr((string) $this->heure_debut, 0, 5);
    }

    public function heureFinHm(): string
    {
        return substr((string) $this->heure_fin, 0, 5);
    }
}
