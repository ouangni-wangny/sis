<?php

namespace App\Models;

use App\Domain\Shared\Enums\StatutAvanceSalaire;
use App\Domain\Shared\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

class AvanceSalaire extends Model
{
    use Auditable, HasUuids;

    protected $table = 'avances_salaire';

    protected $fillable = [
        'agent_id',
        'mois',
        'annee',
        'montant',
        'date_avance',
        'compte_tresorerie_id',
        'mode',
        'reference',
        'notes',
        'statut',
        'bulletin_paie_id',
        'annulee_le',
        'motif_annulation',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'mois' => 'integer',
            'annee' => 'integer',
            'montant' => 'decimal:2',
            'date_avance' => 'date',
            'statut' => StatutAvanceSalaire::class,
            'annulee_le' => 'datetime',
        ];
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function compte(): BelongsTo
    {
        return $this->belongsTo(CompteTresorerie::class, 'compte_tresorerie_id');
    }

    public function bulletinPaie(): BelongsTo
    {
        return $this->belongsTo(BulletinPaie::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @param  Builder<AvanceSalaire>  $query */
    public function scopeNonAnnulees(Builder $query): void
    {
        $query->where('statut', '!=', StatutAvanceSalaire::Annulee->value);
    }

    public static function totalPour(string $agentId, int $mois, int $annee): float
    {
        return round((float) static::query()
            ->nonAnnulees()
            ->where('agent_id', $agentId)
            ->where('mois', $mois)
            ->where('annee', $annee)
            ->sum('montant'), 2);
    }

    /**
     * Charge en une requête le total des avances d’une liste de bulletins.
     *
     * @param  iterable<BulletinPaie>  $bulletins
     */
    public static function prechargerPour(iterable $bulletins): void
    {
        $bulletins = Collection::make($bulletins);
        if ($bulletins->isEmpty()) {
            return;
        }

        $bulletins->each->loadMissing('periodePaie');

        $totaux = static::query()
            ->nonAnnulees()
            ->whereIn('agent_id', $bulletins->pluck('agent_id')->unique()->values())
            ->selectRaw('agent_id, mois, annee, sum(montant) as total')
            ->groupBy('agent_id', 'mois', 'annee')
            ->toBase()
            ->get()
            ->keyBy(fn ($ligne) => "{$ligne->agent_id}|{$ligne->mois}|{$ligne->annee}");

        foreach ($bulletins as $bulletin) {
            $periode = $bulletin->periodePaie;
            $cle = "{$bulletin->agent_id}|{$periode?->mois}|{$periode?->annee}";
            $bulletin->avancesPrechargees = round((float) ($totaux[$cle]->total ?? 0), 2);
        }
    }
}
