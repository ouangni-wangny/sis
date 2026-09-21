<?php

namespace App\Models;

use App\Domain\Shared\Enums\StatutBulletinPaie;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class BulletinPaie extends Model implements HasMedia
{
    use HasUuids, InteractsWithMedia;

    protected $table = 'bulletins_paie';

    protected $fillable = [
        'periode_paie_id',
        'agent_id',
        'contrat_id',
        'salaire_brut',
        'retenue_cnps',
        'montant_igr',
        'salaire_net',
        'statut',
        'paye_le',
        'details',
    ];

    protected function casts(): array
    {
        return [
            'salaire_brut' => 'decimal:2',
            'retenue_cnps' => 'decimal:2',
            'montant_igr' => 'decimal:2',
            'salaire_net' => 'decimal:2',
            'statut' => StatutBulletinPaie::class,
            'paye_le' => 'datetime',
            'details' => 'array',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('pdf')->singleFile()->useDisk('private');
    }

    public function periodePaie(): BelongsTo
    {
        return $this->belongsTo(PeriodePaie::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function contrat(): BelongsTo
    {
        return $this->belongsTo(Contrat::class);
    }
}
