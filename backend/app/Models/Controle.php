<?php

namespace App\Models;

use App\Domain\Shared\Enums\ResultatControle;
use App\Domain\Shared\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Controle extends Model implements HasMedia
{
    use Auditable, HasUuids, InteractsWithMedia, SoftDeletes;

    protected $fillable = [
        'client_uuid', 'agent_id', 'enregistre_par_user_id', 'site_id', 'poste_id',
        'controle_agent_id', 'ronde_id', 'effectue_at', 'latitude', 'longitude',
        'commentaire', 'resultat',
    ];

    protected function casts(): array
    {
        return [
            'effectue_at' => 'datetime',
            'latitude' => 'float',
            'longitude' => 'float',
            'resultat' => ResultatControle::class,
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

        $this->addMediaConversion('preview')
            ->fit(Fit::Contain, 400, 400)
            ->performOnCollections('photos')
            ->nonQueued();
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function enregistrePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enregistre_par_user_id');
    }

    /** Agent posté dont la présence est vérifiée. */
    public function controleAgent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'controle_agent_id');
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function poste(): BelongsTo
    {
        return $this->belongsTo(Poste::class);
    }

    public function ronde(): BelongsTo
    {
        return $this->belongsTo(Ronde::class);
    }
}
