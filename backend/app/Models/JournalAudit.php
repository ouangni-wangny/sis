<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class JournalAudit extends Model
{
    use HasUuids;

    protected $table = 'journal_audit';

    protected $fillable = [
        'user_id', 'action', 'auditable_type', 'auditable_id',
        'ancien', 'nouveau', 'resume', 'contexte', 'ip',
    ];

    protected function casts(): array
    {
        return [
            'ancien' => 'array',
            'nouveau' => 'array',
            'contexte' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }
}
