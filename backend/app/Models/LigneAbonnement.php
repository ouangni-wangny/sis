<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LigneAbonnement extends Model
{
    use HasUuids;

    protected $fillable = [
        'abonnement_id',
        'offre_id',
        'description',
        'quantite',
        'prix_unitaire',
        'montant',
        'ordre',
    ];

    protected function casts(): array
    {
        return [
            'quantite' => 'decimal:2',
            'prix_unitaire' => 'decimal:2',
            'montant' => 'decimal:2',
            'ordre' => 'integer',
        ];
    }

    public function abonnement(): BelongsTo
    {
        return $this->belongsTo(Abonnement::class);
    }

    public function offre(): BelongsTo
    {
        return $this->belongsTo(Offre::class);
    }
}
