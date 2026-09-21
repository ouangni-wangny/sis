<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SisNotification extends Model
{
    use HasUuids;

    protected $table = 'sis_notifications';

    protected $fillable = [
        'user_id',
        'type',
        'titre',
        'message',
        'meta',
        'lue_le',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'lue_le' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
