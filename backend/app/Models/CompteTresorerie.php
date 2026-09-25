<?php

namespace App\Models;

use App\Domain\Shared\Enums\TypeCompteTresorerie;
use App\Domain\Shared\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CompteTresorerie extends Model
{
    use Auditable, HasUuids, SoftDeletes;

    protected $table = 'comptes_tresorerie';

    protected $fillable = [
        'libelle',
        'type',
        'solde_ouverture',
        'actif',
    ];

    protected function casts(): array
    {
        return [
            'type' => TypeCompteTresorerie::class,
            'solde_ouverture' => 'decimal:2',
            'actif' => 'boolean',
        ];
    }

    public function mouvements(): HasMany
    {
        return $this->hasMany(MouvementTresorerie::class, 'compte_tresorerie_id');
    }

    public function soldeCourant(): float
    {
        $entrees = (float) $this->mouvements()
            ->where('direction', 'entree')
            ->sum('montant');
        $sorties = (float) $this->mouvements()
            ->where('direction', 'sortie')
            ->sum('montant');

        return round((float) $this->solde_ouverture + $entrees - $sorties, 2);
    }
}
