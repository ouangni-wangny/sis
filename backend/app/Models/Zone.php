<?php

namespace App\Models;

use App\Domain\Shared\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Zone extends Model
{
    use Auditable, HasUuids, SoftDeletes;

    protected $fillable = ['nom', 'description'];

    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }

    public function perimetres(): HasMany
    {
        return $this->hasMany(RondierPerimetre::class);
    }

    public function controleurs(): BelongsToMany
    {
        return $this->belongsToMany(Agent::class, 'rondier_perimetres')
            ->withPivot(['indice_releve', 'releve_depuis', 'releve_jusque'])
            ->withTimestamps()
            ->orderByPivot('indice_releve');
    }
}
