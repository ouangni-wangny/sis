<?php

namespace App\Models;

use App\Domain\Shared\Enums\StatutRonde;
use App\Domain\Shared\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ronde extends Model
{
    use Auditable, HasUuids, SoftDeletes;

    protected $fillable = [
        'agent_id', 'site_id', 'vacation_id', 'demarree_at', 'terminee_at',
        'statut', 'progression',
    ];

    protected function casts(): array
    {
        return [
            'demarree_at' => 'datetime',
            'terminee_at' => 'datetime',
            'statut' => StatutRonde::class,
            'progression' => 'integer',
        ];
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function vacation(): BelongsTo
    {
        return $this->belongsTo(Vacation::class);
    }

    public function rondeCheckpoints(): HasMany
    {
        return $this->hasMany(RondeCheckpoint::class);
    }
}
