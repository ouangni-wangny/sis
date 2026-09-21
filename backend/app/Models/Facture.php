<?php

namespace App\Models;

use App\Domain\Shared\Enums\PeriodiciteFacturation;
use App\Domain\Shared\Enums\StatutFacture;
use App\Domain\Shared\Enums\StatutPaiementFacture;
use App\Domain\Shared\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Facture extends Model implements HasMedia
{
    use Auditable, HasUuids, InteractsWithMedia, SoftDeletes;

    protected $fillable = [
        'client_id', 'abonnement_id', 'site_id', 'numero', 'date_emission', 'date_echeance',
        'periode_debut', 'periode_fin', 'periodicite',
        'date_debut_service', 'date_fin_service',
        'montant_ht', 'montant_tva', 'montant_ttc', 'devise', 'statut',
        'lieu_emission', 'affaire_suivie_par', 'telephone_commercial', 'taux_tva',
        'client_nom', 'client_adresse', 'client_telephone', 'client_email',
        'notes', 'conditions_paiement', 'delai_paiement_jours', 'delai_validite', 'duree_contrat_min',
        'signataire_nom', 'signataire_fonction', 'montant_ttc_lettres',
    ];

    protected function casts(): array
    {
        return [
            'date_emission' => 'date',
            'date_echeance' => 'date',
            'periode_debut' => 'date',
            'periode_fin' => 'date',
            'date_debut_service' => 'date',
            'date_fin_service' => 'date',
            'montant_ht' => 'decimal:2',
            'montant_tva' => 'decimal:2',
            'montant_ttc' => 'decimal:2',
            'taux_tva' => 'decimal:2',
            'delai_paiement_jours' => 'integer',
            'statut' => StatutFacture::class,
            'periodicite' => PeriodiciteFacturation::class,
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('pdf')->singleFile();
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function abonnement(): BelongsTo
    {
        return $this->belongsTo(Abonnement::class);
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(LigneFacture::class)->orderBy('ordre');
    }

    public function paiements(): HasMany
    {
        return $this->hasMany(Paiement::class);
    }

    public function montantPaye(): float
    {
        if (array_key_exists('paiements_sum_montant', $this->attributes)) {
            return round((float) $this->attributes['paiements_sum_montant'], 2);
        }

        if ($this->relationLoaded('paiements')) {
            return round((float) $this->paiements->sum('montant'), 2);
        }

        return round((float) $this->paiements()->sum('montant'), 2);
    }

    public function solde(): float
    {
        return round(max(0, (float) $this->montant_ttc - $this->montantPaye()), 2);
    }

    public function statutPaiement(): StatutPaiementFacture
    {
        $paye = $this->montantPaye();
        $ttc = (float) $this->montant_ttc;

        if ($paye <= 0) {
            return StatutPaiementFacture::NonPayee;
        }

        if ($paye + 1 >= $ttc) {
            return StatutPaiementFacture::Soldee;
        }

        return StatutPaiementFacture::Partiel;
    }
}
