<?php

namespace App\Models;

use App\Domain\Shared\Enums\UnitePrime;
use App\Domain\Shared\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Prime donnée par une qualification : ex. CP = 1 000 FCFA par mois (ou par jour). */
class PrimeQualification extends Model
{
    use Auditable, HasUuids, SoftDeletes;

    protected $table = 'primes_qualification';

    protected $fillable = ['qualification_id', 'montant', 'unite', 'actif'];

    protected function casts(): array
    {
        return [
            'montant' => 'decimal:2',
            'unite' => UnitePrime::class,
            'actif' => 'boolean',
        ];
    }

    public function qualification(): BelongsTo
    {
        return $this->belongsTo(Qualification::class);
    }
}
