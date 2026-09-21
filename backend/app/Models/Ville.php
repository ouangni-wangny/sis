<?php

namespace App\Models;

use App\Domain\Shared\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ville extends Model
{
    use Auditable, HasUuids, SoftDeletes;

    protected $fillable = ['libelle'];

    public function agents(): HasMany
    {
        return $this->hasMany(Agent::class);
    }
}
