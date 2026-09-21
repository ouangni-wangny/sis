<?php

namespace App\Models;

use App\Domain\Shared\Enums\PeriodiciteFacturation;
use App\Domain\Shared\Enums\StatutAbonnement;
use App\Domain\Shared\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Abonnement extends Model
{
    use Auditable, HasUuids, SoftDeletes;

    protected $fillable = [
        'client_id',
        'offre_id',
        'designation',
        'site_id',
        'periodicite',
        'date_debut',
        'date_fin',
        'prochaine_facture_le',
        'statut',
    ];

    protected function casts(): array
    {
        return [
            'date_debut' => 'date',
            'date_fin' => 'date',
            'prochaine_facture_le' => 'date',
            'statut' => StatutAbonnement::class,
            'periodicite' => PeriodiciteFacturation::class,
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function offre(): BelongsTo
    {
        return $this->belongsTo(Offre::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function factures(): HasMany
    {
        return $this->hasMany(Facture::class);
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(LigneAbonnement::class)->orderBy('ordre');
    }
}
