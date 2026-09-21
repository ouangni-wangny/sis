<?php

namespace App\Models;

use App\Domain\Shared\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RondierPerimetre extends Model
{
    use Auditable, HasUuids;

    protected $fillable = ['agent_id', 'zone_id', 'site_id', 'indice_releve', 'releve_depuis', 'releve_jusque'];

    protected function casts(): array
    {
        return [
            'releve_depuis' => 'date',
            'releve_jusque' => 'date',
            'indice_releve' => 'integer',
        ];
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
