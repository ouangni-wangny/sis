<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RondeCheckpoint extends Model
{
    use HasUuids;

    protected $fillable = [
        'ronde_id', 'checkpoint_id', 'scanne_at', 'latitude', 'longitude', 'valide',
    ];

    protected function casts(): array
    {
        return [
            'scanne_at' => 'datetime',
            'latitude' => 'float',
            'longitude' => 'float',
            'valide' => 'boolean',
        ];
    }

    public function ronde(): BelongsTo
    {
        return $this->belongsTo(Ronde::class);
    }

    public function checkpoint(): BelongsTo
    {
        return $this->belongsTo(Checkpoint::class);
    }
}
