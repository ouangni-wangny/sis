<?php

namespace App\Models;

use App\Domain\Shared\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Site extends Model
{
    use Auditable, HasUuids, SoftDeletes;

    protected $fillable = [
        'client_id', 'zone_id', 'nom', 'adresse', 'responsable',
        'tarif_mensuel', 'latitude', 'longitude', 'rayon_metres', 'interne',
    ];

    protected function casts(): array
    {
        return [
            'tarif_mensuel' => 'decimal:2',
            'latitude' => 'float',
            'longitude' => 'float',
            'rayon_metres' => 'integer',
            'interne' => 'boolean',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function postes(): HasMany
    {
        return $this->hasMany(Poste::class);
    }

    public function checkpoints(): HasMany
    {
        return $this->hasMany(Checkpoint::class);
    }

    public function vacations(): HasMany
    {
        return $this->hasMany(Vacation::class);
    }
}
