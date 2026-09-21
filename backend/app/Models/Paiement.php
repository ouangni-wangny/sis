<?php

namespace App\Models;

use App\Domain\Shared\Enums\ModePaiement;
use App\Domain\Shared\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Paiement extends Model
{
    use Auditable, HasUuids, SoftDeletes;

    protected $fillable = [
        'facture_id',
        'montant',
        'date_paiement',
        'mode',
        'reference',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'montant' => 'decimal:2',
            'date_paiement' => 'date',
            'mode' => ModePaiement::class,
        ];
    }

    public function facture(): BelongsTo
    {
        return $this->belongsTo(Facture::class);
    }
}
