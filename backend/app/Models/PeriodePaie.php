<?php

namespace App\Models;

use App\Domain\Shared\Enums\StatutPeriodePaie;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PeriodePaie extends Model
{
    use HasUuids;

    protected $table = 'periodes_paie';

    protected $fillable = [
        'mois',
        'annee',
        'date_debut',
        'date_fin',
        'statut',
        'commentaire',
    ];

    protected function casts(): array
    {
        return [
            'mois' => 'integer',
            'annee' => 'integer',
            'date_debut' => 'date',
            'date_fin' => 'date',
            'statut' => StatutPeriodePaie::class,
        ];
    }

    public function bulletins(): HasMany
    {
        return $this->hasMany(BulletinPaie::class);
    }
}
