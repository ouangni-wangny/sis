<?php

namespace App\Models;

use App\Domain\Shared\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ModePaiementParam extends Model
{
    use Auditable, HasUuids;

    protected $table = 'modes_paiement';

    protected $fillable = [
        'code',
        'libelle',
        'actif',
        'ordre',
    ];

    protected function casts(): array
    {
        return [
            'actif' => 'boolean',
            'ordre' => 'integer',
        ];
    }
}
