<?php

namespace App\Models;

use App\Domain\Shared\Enums\ModeEffectifPoste;
use App\Domain\Shared\Support\ShiftInterval;
use App\Domain\Shared\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Poste extends Model
{
    use Auditable, HasUuids, SoftDeletes;

    protected $fillable = [
        'site_id', 'nom', 'agents_requis', 'mode_effectif',
        'heure_debut', 'heure_fin',
        'heure_debut_nuit', 'heure_fin_nuit',
    ];

    protected function casts(): array
    {
        return [
            'agents_requis' => 'integer',
            'mode_effectif' => ModeEffectifPoste::class,
        ];
    }

    /** Poste avec découpage quarts jour + nuit (champs nuit renseignés). */
    public function hasDecoupageQuarts(): bool
    {
        return $this->heure_debut_nuit !== null && $this->heure_fin_nuit !== null;
    }

    /**
     * Couverture 24h découpée (jours/nuits distincts) — utilisée par
     * GetPosteCoverageAction pour exiger les deux quarts.
     */
    public function estCouverture24h(): bool
    {
        return $this->hasDecoupageQuarts();
    }

    /** Cycle 24h continu sans découpage (début === fin). */
    public function estCycle24h(): bool
    {
        if ($this->hasDecoupageQuarts()) {
            return false;
        }
        if ($this->heure_debut === null || $this->heure_fin === null) {
            return false;
        }

        return ShiftInterval::isCycle24h(
            (string) $this->heure_debut,
            (string) $this->heure_fin,
        );
    }

    /** Surveillance 24h : découpage OU cycle. */
    public function estSurveillance24h(): bool
    {
        return $this->hasDecoupageQuarts() || $this->estCycle24h();
    }

    /**
     * Sans quarts + effectif ≥ 2 + mode alternance : un seul agent à la fois.
     * Avec quarts, le mode effectif ne s’applique pas.
     */
    public function alterne(): bool
    {
        if ($this->hasDecoupageQuarts()) {
            return false;
        }
        if ((int) $this->agents_requis < 2) {
            return false;
        }

        return ($this->mode_effectif ?? ModeEffectifPoste::Ensemble)
            === ModeEffectifPoste::Alternance;
    }

    public function monteEnsemble(): bool
    {
        return ! $this->alterne();
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
