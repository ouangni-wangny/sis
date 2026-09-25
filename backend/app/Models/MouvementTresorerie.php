<?php

namespace App\Models;

use App\Domain\Shared\Enums\DirectionMouvementTresorerie;
use App\Domain\Shared\Enums\SourceMouvementTresorerie;
use App\Domain\Shared\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MouvementTresorerie extends Model
{
    use Auditable, HasUuids, SoftDeletes;

    protected $table = 'mouvements_tresorerie';

    protected $fillable = [
        'compte_tresorerie_id',
        'direction',
        'montant',
        'date_mouvement',
        'mode',
        'source_type',
        'source_id',
        'reference',
        'notes',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'direction' => DirectionMouvementTresorerie::class,
            'montant' => 'decimal:2',
            'date_mouvement' => 'date',
            'source_type' => SourceMouvementTresorerie::class,
        ];
    }

    public function compte(): BelongsTo
    {
        return $this->belongsTo(CompteTresorerie::class, 'compte_tresorerie_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
