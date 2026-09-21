<?php

namespace App\Models;

use App\Domain\Shared\Enums\StatutClient;
use App\Domain\Shared\Enums\TypeClient;
use App\Domain\Shared\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use Auditable, HasUuids, SoftDeletes;

    protected $fillable = [
        'type', 'raison_sociale', 'nom_responsable', 'personne_contact',
        'telephone', 'email', 'adresse', 'statut',
    ];

    protected function casts(): array
    {
        return [
            'type' => TypeClient::class,
            'statut' => StatutClient::class,
        ];
    }

    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }

    public function abonnements(): HasMany
    {
        return $this->hasMany(Abonnement::class);
    }

    public function factures(): HasMany
    {
        return $this->hasMany(Facture::class);
    }
}
