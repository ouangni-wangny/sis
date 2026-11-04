<?php

namespace App\Models;

use App\Application\Shared\RondierPerimetreGuard;
use App\Domain\Shared\Enums\StatutVacation;
use App\Domain\Shared\Enums\TypeAgent;
use App\Domain\Shared\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vacation extends Model
{
    use Auditable, HasUuids, SoftDeletes;

    protected $fillable = [
        'agent_id', 'absence_id', 'site_id', 'poste_id', 'date_debut', 'date_fin',
        'heure_debut', 'heure_fin', 'statut', 'heures_supplementaires',
        'est_remplacement', 'reconduite', 'remplacee',
    ];

    protected function casts(): array
    {
        return [
            'date_debut' => 'date',
            'date_fin' => 'date',
            'statut' => StatutVacation::class,
            'heures_supplementaires' => 'boolean',
            'est_remplacement' => 'boolean',
            'reconduite' => 'boolean',
            'remplacee' => 'boolean',
        ];
    }

    public function absence(): BelongsTo
    {
        return $this->belongsTo(Absence::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function poste(): BelongsTo
    {
        return $this->belongsTo(Poste::class);
    }

    public function rondes(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Ronde::class);
    }

    /**
     * Ce que ce compte peut lire. Le planning voit tout ; le contrôleur son périmètre ;
     * l’agent ses vacations ; un compte terrain sans fiche agent, rien.
     *
     * @param  Builder<Vacation>  $query
     */
    public function scopeVisiblesPar(Builder $query, User $user): void
    {
        if (self::voitToutLePlanning($user)) {
            return;
        }

        $fiche = $user->agent;

        if ($fiche?->type === TypeAgent::Controleur) {
            $query->whereIn('site_id', RondierPerimetreGuard::authorizedSiteIds($fiche));

            return;
        }

        $query->where('agent_id', $fiche?->id ?? '');
    }

    public static function voitToutLePlanning(User $user): bool
    {
        if ($user->agent?->type === TypeAgent::Controleur) {
            return false;
        }

        return $user->hasRole('super-admin') || $user->can('vacations.create') || $user->can('vacations.update')
            || (! $user->hasRole('agent') && ! $user->hasRole('controleur'));
    }
}
