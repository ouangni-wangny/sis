<?php

namespace App\Models;

use App\Application\Shared\RondierPerimetreGuard;
use App\Domain\Shared\Enums\GraviteAnomalie;
use App\Domain\Shared\Enums\StatutAnomalie;
use App\Domain\Shared\Enums\TypeAgent;
use App\Domain\Shared\Enums\TypeAnomalie;
use App\Domain\Shared\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
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

    /**
     * Ce que ce compte peut lire. Comme le planning : le bureau voit tout ; le contrôleur
     * son périmètre et ses signalements ; l’agent ses signalements ; sans fiche, rien.
     *
     * @param  Builder<Anomalie>  $query
     */
    public function scopeVisiblesPar(Builder $query, User $user): void
    {
        if ($user->hasRole('super-admin') || $user->can('anomalies.update')
            || (! $user->hasRole('agent') && ! $user->hasRole('controleur') && $user->agent?->type !== TypeAgent::Controleur)) {
            return;
        }

        $fiche = $user->agent;

        $query->where(function (Builder $q) use ($fiche) {
            $q->where('signale_par_id', $fiche?->id ?? '');

            if ($fiche?->type === TypeAgent::Controleur) {
                $q->orWhereIn('site_id', RondierPerimetreGuard::authorizedSiteIds($fiche));
            }
        });
    }
}
