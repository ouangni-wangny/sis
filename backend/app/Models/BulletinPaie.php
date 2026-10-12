<?php

namespace App\Models;

use App\Domain\Shared\Enums\StatutBulletinPaie;
use App\Domain\Shared\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class BulletinPaie extends Model implements HasMedia
{
    // Journal d’audit : qui a saisi / modifié / réglé quel salaire, et quand.
    use Auditable, HasUuids, InteractsWithMedia;

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
        'mode_paiement',
        'compte_tresorerie_id',
        'reference_paiement',
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

    public function compteTresorerie(): BelongsTo
    {
        return $this->belongsTo(CompteTresorerie::class, 'compte_tresorerie_id');
    }

    /** Libellé du journal : « Bulletin 09/2026 — Prénom Nom (matricule) ». */
    public function auditLabel(): ?string
    {
        $details = is_array($this->details) ? $this->details : [];
        $periode = $this->periodePaie;
        $qui = trim(($details['agent'] ?? '').(isset($details['matricule']) ? ' ('.$details['matricule'].')' : ''));

        return trim(sprintf(
            'Bulletin %s %s',
            $periode ? sprintf('%02d/%d', $periode->mois, $periode->annee) : '',
            $qui !== '' ? '— '.$qui : '',
        ));
    }
}
