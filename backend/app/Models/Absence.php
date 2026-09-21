<?php

namespace App\Models;

use App\Domain\Shared\Enums\StatutAbsence;
use App\Domain\Shared\Enums\SourceAbsence;
use App\Domain\Shared\Enums\TypeAbsence;
use App\Domain\Shared\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Absence extends Model
{
    use Auditable, HasUuids, SoftDeletes;

    protected $fillable = [
        'agent_id',
        'type',
        'source',
        'controle_id',
        'date_debut',
        'date_fin',
        'motif',
        'statut',
    ];

    protected function casts(): array
    {
        return [
            'date_debut' => 'date',
            'date_fin' => 'date',
            'statut' => StatutAbsence::class,
            'type' => TypeAbsence::class,
            'source' => SourceAbsence::class,
        ];
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function controle(): BelongsTo
    {
        return $this->belongsTo(Controle::class);
    }
}
