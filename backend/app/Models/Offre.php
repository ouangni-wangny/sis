<?php

namespace App\Models;

use App\Domain\Shared\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Offre extends Model
{
    use Auditable, HasUuids, SoftDeletes;

    protected $fillable = ['libelle', 'description', 'prix_mensuel', 'actif'];

    protected function casts(): array
    {
        return [
            'prix_mensuel' => 'decimal:2',
            'actif' => 'boolean',
        ];
    }

    public function abonnements(): HasMany
    {
        return $this->hasMany(Abonnement::class);
    }
}
