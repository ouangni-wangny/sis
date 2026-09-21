<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SoldeCongesMouvement extends Model
{
    use HasUuids;

    protected $fillable = [
        'agent_id',
        'absence_id',
        'type',
        'jours',
        'solde_apres',
        'motif',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'jours' => 'decimal:1',
            'solde_apres' => 'decimal:1',
        ];
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function absence(): BelongsTo
    {
        return $this->belongsTo(Absence::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
