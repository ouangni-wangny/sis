<?php

namespace App\Models;

use App\Domain\Shared\Enums\TypeAgent;
use App\Domain\Shared\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Grade extends Model
{
    use Auditable, HasUuids, SoftDeletes;

    protected $fillable = ['libelle', 'type_agent', 'description'];

    protected function casts(): array
    {
        return ['type_agent' => TypeAgent::class];
    }

    public function agents(): HasMany
    {
        return $this->hasMany(Agent::class);
    }
}
