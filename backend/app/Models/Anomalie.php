<?php

namespace App\Models;

use App\Domain\Shared\Enums\GraviteAnomalie;
use App\Domain\Shared\Enums\StatutAnomalie;
use App\Domain\Shared\Enums\TypeAnomalie;
use App\Domain\Shared\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Anomalie extends Model implements HasMedia
{
    use Auditable, HasUuids, InteractsWithMedia, SoftDeletes;

    protected $fillable = [
        'client_uuid', 'signale_par_id', 'assigne_a_id', 'site_id',
        'type', 'gravite', 'statut', 'commentaire', 'signale_at', 'resolue_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => TypeAnomalie::class,
            'gravite' => GraviteAnomalie::class,
            'statut' => StatutAnomalie::class,
            'signale_at' => 'datetime',
            'resolue_at' => 'datetime',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photos');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->fit(Fit::Crop, 150, 150)
            ->performOnCollections('photos')
            ->nonQueued();
    }

    public function signalePar(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'signale_par_id');
    }

    public function assigneA(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigne_a_id');
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
