<?php

namespace App\Models;

use App\Domain\Shared\Enums\StatutVacation;
use App\Domain\Shared\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vacation extends Model
{
    use Auditable, HasUuids, SoftDeletes;

    protected $fillable = [
        'agent_id', 'absence_id', 'site_id', 'poste_id', 'date_debut', 'date_fin',
        'heure_debut', 'heure_fin', 'statut',
    ];

    protected function casts(): array
    {
        return [
            'date_debut' => 'date',
            'date_fin' => 'date',
            'statut' => StatutVacation::class,
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

    public function poste(): BelongsTo
    {
        return $this->belongsTo(Poste::class);
    }

    public function rondes(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Ronde::class);
    }
}
