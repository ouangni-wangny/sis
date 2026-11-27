<?php

namespace App\Models;

use App\Domain\Shared\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Qualification d’un agent : Chef de poste (CP), Garde armée (GA), Maître-chien (MC)…
 * Elle donne droit à une ou plusieurs primes (par mois ou par jour).
 */
class Qualification extends Model
{
    use Auditable, HasUuids, SoftDeletes;

    protected $table = 'qualifications';

    protected $fillable = ['libelle', 'abreviation', 'description', 'actif'];

    protected function casts(): array
    {
        return ['actif' => 'boolean'];
    }

    public function agents(): BelongsToMany
    {
        return $this->belongsToMany(Agent::class, 'agent_qualifications')->withTimestamps();
    }

    public function primes(): HasMany
    {
        return $this->hasMany(PrimeQualification::class);
    }
}
