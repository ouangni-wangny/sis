<?php

namespace App\Models;

use App\Domain\Contrat\CalculDureeContrat;
use App\Domain\Contrat\ContratSurveillanceQuery;
use App\Domain\Contrat\ContratValideQuery;
use App\Domain\Contrat\ReglesTypeContrat;
use App\Domain\Shared\Enums\TypeContrat;
use App\Domain\Shared\Traits\Auditable;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Contrat extends Model implements HasMedia
{
    use Auditable, HasUuids, InteractsWithMedia, SoftDeletes;

    protected $fillable = [
        'agent_id',
        'contrat_parent_id',
        'type',
        'reference',
        'date_debut',
        'date_fin',
        'duree_mois',
        'periode_essai_mois',
        'salaire_base',
        'indemnite_fonction',
        'prime_responsabilite',
        'prime_transport',
        'prime_entretien_tenue',
        'sursalaire',
        'nombre_enfants',
        'parts_igr',
        'montant_igr',
        'retenue_cnps',
        'salaire',
        'salaire_brut',
        'salaire_net',
        'statut',
    ];

    protected function casts(): array
    {
        return [
            'type' => TypeContrat::class,
            'date_debut' => 'date',
            'date_fin' => 'date',
            'duree_mois' => 'integer',
            'periode_essai_mois' => 'integer',
            'nombre_enfants' => 'integer',
            'salaire_base' => 'decimal:2',
            'indemnite_fonction' => 'decimal:2',
            'prime_responsabilite' => 'decimal:2',
            'prime_transport' => 'decimal:2',
            'prime_entretien_tenue' => 'decimal:2',
            'sursalaire' => 'decimal:2',
            'parts_igr' => 'decimal:1',
            'montant_igr' => 'decimal:2',
            'retenue_cnps' => 'decimal:2',
            'salaire' => 'decimal:2',
            'salaire_brut' => 'decimal:2',
            'salaire_net' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Contrat $contrat): void {
            $contrat->duree_mois = CalculDureeContrat::fromDates(
                $contrat->date_debut?->format('Y-m-d'),
                $contrat->date_fin?->format('Y-m-d'),
            );

            if ($contrat->isDirty('salaire_brut') && $contrat->salaire_brut !== null) {
                $contrat->salaire = $contrat->salaire_brut;
            } elseif ($contrat->isDirty('salaire') && $contrat->salaire !== null && $contrat->salaire_brut === null) {
                $contrat->salaire_brut = $contrat->salaire;
            }
        });
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('document')->singleFile()->useDisk('private');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function contratParent(): BelongsTo
    {
        return $this->belongsTo(Contrat::class, 'contrat_parent_id');
    }

    public function avenants(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Contrat::class, 'contrat_parent_id');
    }

    /** @param  Builder<Contrat>  $query */
    public function scopeValide(Builder $query, Carbon|string|null $date = null): Builder
    {
        return ContratValideQuery::apply($query, $date);
    }

    /** @param  Builder<Contrat>  $query */
    public function scopeASurveiller(
        Builder $query,
        int $joursEssai = ContratSurveillanceQuery::JOURS_ESSAI,
        int $joursFin = ContratSurveillanceQuery::JOURS_FIN,
        Carbon|string|null $date = null,
    ): Builder {
        return ContratSurveillanceQuery::apply($query, $joursEssai, $joursFin, $date);
    }

    public static function hasActiveOverlap(
        string $agentId,
        string $debut,
        ?string $fin,
        ?string $exceptId = null,
    ): bool {
        return static::query()
            ->where('agent_id', $agentId)
            ->where('statut', 'actif')
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->where(function ($q) use ($debut, $fin) {
                $q->whereDate('date_debut', '<=', $fin ?? '9999-12-31')
                    ->where(function ($ends) use ($debut) {
                        $ends->whereNull('date_fin')
                            ->orWhereDate('date_fin', '>=', $debut);
                    });
            })
            ->exists();
    }

    public function requiresEndDate(): bool
    {
        $type = $this->type instanceof TypeContrat ? $this->type : TypeContrat::from((string) $this->type);

        return ReglesTypeContrat::needsDateFin($type);
    }
}
