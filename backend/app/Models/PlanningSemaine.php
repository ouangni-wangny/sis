<?php

namespace App\Models;

use App\Domain\Shared\Enums\StatutPlanningSemaine;
use App\Domain\Shared\Traits\Auditable;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlanningSemaine extends Model
{
    use Auditable, HasUuids;

    protected $table = 'planning_semaines';

    protected $fillable = [
        'poste_id',
        'annee_iso',
        'semaine_iso',
        'statut',
    ];

    protected function casts(): array
    {
        return [
            'annee_iso' => 'integer',
            'semaine_iso' => 'integer',
            'statut' => StatutPlanningSemaine::class,
        ];
    }

    public function poste(): BelongsTo
    {
        return $this->belongsTo(Poste::class);
    }

    public function creneaux(): HasMany
    {
        return $this->hasMany(PlanningSemaineCreneau::class)->orderBy('jour')->orderBy('heure_debut');
    }

    /** Lundi 00:00 de la semaine ISO (Africa/Abidjan). */
    public function lundi(): Carbon
    {
        return Carbon::now('Africa/Abidjan')
            ->setISODate($this->annee_iso, $this->semaine_iso)
            ->startOfWeek(Carbon::MONDAY)
            ->startOfDay();
    }

    public function dimanche(): Carbon
    {
        return $this->lundi()->copy()->endOfWeek(Carbon::SUNDAY)->startOfDay();
    }

    /**
     * @return array{annee_iso: int, semaine_iso: int}
     */
    public static function isoFromDate(string|Carbon $date): array
    {
        $c = $date instanceof Carbon
            ? $date->copy()->timezone('Africa/Abidjan')
            : Carbon::parse($date, 'Africa/Abidjan');

        return [
            'annee_iso' => (int) $c->isoWeekYear(),
            'semaine_iso' => (int) $c->isoWeek(),
        ];
    }
}
