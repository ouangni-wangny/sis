<?php

namespace App\Models;

use App\Domain\Contrat\ContratValideQuery;
use App\Domain\Shared\Enums\Civilite;
use App\Domain\Shared\Enums\JourSemaine;
use App\Domain\Shared\Enums\SituationMatrimoniale;
use App\Domain\Shared\Enums\StatutAgent;
use App\Domain\Shared\Enums\TypeAgent;
use App\Domain\Shared\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Agent extends Model implements HasMedia
{
    use Auditable, HasUuids, InteractsWithMedia, SoftDeletes;

    protected $fillable = [
        'user_id',
        'grade_id',
        'type',
        'nom',
        'prenom',
        'civilite',
        'date_naissance',
        'lieu_naissance',
        'situation_matrimoniale',
        'nombre_enfants',
        'nationalite',
        'telephone',
        'numero_cni',
        'ville',
        'ville_id',
        'domicile',
        'matricule',
        'cnps',
        'date_embauche',
        'date_expiration_permis',
        'statut',
        'pool_siege',
        'poste_siege_id',
        'jour_repos',
        'conges_acquis_annuel',
        'solde_conges_jours',
    ];

    protected function casts(): array
    {
        return [
            'type' => TypeAgent::class,
            'statut' => StatutAgent::class,
            'civilite' => Civilite::class,
            'situation_matrimoniale' => SituationMatrimoniale::class,
            'jour_repos' => JourSemaine::class,
            'nombre_enfants' => 'integer',
            'pool_siege' => 'boolean',
            'date_naissance' => 'date',
            'date_embauche' => 'date',
            'date_expiration_permis' => 'date',
            'conges_acquis_annuel' => 'decimal:1',
            'solde_conges_jours' => 'decimal:1',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photo')->singleFile();
        $this->addMediaCollection('piece_identite')->singleFile()->useDisk('private');
        $this->addMediaCollection('permis')->singleFile()->useDisk('private');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->fit(Fit::Crop, 150, 150)
            ->performOnCollections('photo')
            ->nonQueued();

        $this->addMediaConversion('preview')
            ->fit(Fit::Contain, 400, 400)
            ->performOnCollections('photo')
            ->nonQueued();
    }

    protected static function booted(): void
    {
        static::saving(function (Agent $agent) {
            if (! $agent->isDirty('ville_id')) {
                return;
            }
            if (! $agent->ville_id) {
                $agent->ville = null;

                return;
            }
            $agent->ville = Ville::query()->find($agent->ville_id)?->libelle;
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    public function villeRef(): BelongsTo
    {
        return $this->belongsTo(Ville::class, 'ville_id');
    }

    /**
     * Poste (sur un site interne) où cet agent, rattaché au pool siège,
     * est pointé chaque jour par Opération quand il n’est pas déployé
     * en remplacement ailleurs.
     */
    public function posteSiege(): BelongsTo
    {
        return $this->belongsTo(Poste::class, 'poste_siege_id');
    }

    public function vacations(): HasMany
    {
        return $this->hasMany(Vacation::class);
    }

    public function rondes(): HasMany
    {
        return $this->hasMany(Ronde::class);
    }

    public function perimetres(): HasMany
    {
        return $this->hasMany(RondierPerimetre::class);
    }

    public function contrats(): HasMany
    {
        return $this->hasMany(Contrat::class);
    }

    public function contratActif(): HasOne
    {
        return $this->hasOne(Contrat::class)
            ->valide()
            ->latestOfMany('date_debut');
    }

    /** @param  Builder<Agent>  $query */
    public function scopeAvecContratValide(Builder $query, ?string $date = null): void
    {
        $query->whereHas('contrats', fn ($q) => ContratValideQuery::apply($q, $date));
    }

    public function absences(): HasMany
    {
        return $this->hasMany(Absence::class);
    }

    public function soldeCongesMouvements(): HasMany
    {
        return $this->hasMany(SoldeCongesMouvement::class);
    }
}
