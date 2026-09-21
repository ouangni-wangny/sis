<?php

namespace App\Application\Rh;

use App\Application\Operation\MarquerVacationsARecouvrir;
use App\Domain\Absence\AbsenceStatutTransition;
use App\Domain\Shared\Enums\SourceAbsence;
use App\Domain\Shared\Enums\StatutAbsence;
use App\Domain\Shared\Enums\StatutAgent;
use App\Domain\Shared\Enums\StatutVacation;
use App\Domain\Shared\Enums\TypeAbsence;
use App\Models\Absence;
use App\Models\Agent;
use App\Models\Vacation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpsertAbsenceAction
{
    public function create(array $data): AbsenceOperationResult
    {
        return DB::transaction(function () use ($data) {
            $statut = StatutAbsence::from($data['statut'] ?? StatutAbsence::EnAttente->value);
            $type = TypeAbsence::from($data['type'] ?? TypeAbsence::Autre->value);

            AbsenceStatutTransition::assertCreatable($statut);

            $this->assertNoOverlap(
                $data['agent_id'],
                $data['date_debut'],
                $data['date_fin'],
            );

            $absence = Absence::query()->create([
                ...$data,
                'statut' => $statut->value,
                'type' => $type->value,
                'source' => SourceAbsence::Rh->value,
            ])->load('agent');

            $marquees = 0;
            if ($statut === StatutAbsence::Approuvee) {
                $marquees = MarquerVacationsARecouvrir::execute(
                    $absence,
                    $data['agent_id'],
                    $data['date_debut'],
                    $data['date_fin'],
                );
                $this->syncAgentStatutOnApproval($data['agent_id'], $type);
                app(AjusterSoldeCongesAction::class)->deduirePourAbsence($absence);
                app(CreateSisNotificationAction::class)->notifyRhUsers(
                    'absence_approuvee',
                    'Absence approuvée',
                    "Absence approuvée pour {$absence->agent?->prenom} {$absence->agent?->nom}.",
                    ['absence_id' => $absence->id],
                );
            } elseif ($statut === StatutAbsence::EnAttente) {
                app(CreateSisNotificationAction::class)->notifyRhUsers(
                    'absence_en_attente',
                    'Nouvelle absence en attente',
                    "Demande d'absence à valider pour {$absence->agent?->prenom} {$absence->agent?->nom}.",
                    ['absence_id' => $absence->id],
                );
            }

            return new AbsenceOperationResult($absence, $marquees);
        });
    }

    public function update(Absence $absence, array $data): AbsenceOperationResult
    {
        return DB::transaction(function () use ($absence, $data) {
            if (AbsenceStatutTransition::isTerminal($absence->statut)) {
                throw ValidationException::withMessages([
                    'statut' => 'Une absence refusée ou annulée ne peut plus être modifiée.',
                ]);
            }

            $previousStatut = $absence->statut;
            $wasApproved = $previousStatut === StatutAbsence::Approuvee;

            $agentId = $absence->agent_id;
            $debut = $data['date_debut'] ?? $absence->date_debut->format('Y-m-d');
            $fin = $data['date_fin'] ?? $absence->date_fin->format('Y-m-d');
            $newStatut = isset($data['statut'])
                ? StatutAbsence::from($data['statut'])
                : $previousStatut;
            $newType = isset($data['type'])
                ? TypeAbsence::from($data['type'])
                : $absence->type;

            if ($newStatut !== $previousStatut) {
                AbsenceStatutTransition::assertCanTransition($previousStatut, $newStatut);
            }

            $this->assertNoOverlap($agentId, $debut, $fin, $absence->id);

            $oldDebut = $absence->date_debut->format('Y-m-d');
            $oldFin = $absence->date_fin->format('Y-m-d');
            $oldType = $absence->type;
            $datesChanged = $debut !== $oldDebut || $fin !== $oldFin;

            $restaurees = 0;
            $marquees = 0;

            $becomingUnapproved = $wasApproved && in_array($newStatut, [
                StatutAbsence::Refusee,
                StatutAbsence::Annulee,
            ], true);

            if ($becomingUnapproved) {
                $restaurees = $this->restaurerVacationsLiees($absence);
                $this->restoreAgentStatutIfNeeded(
                    $agentId,
                    $oldType,
                    $oldDebut,
                    $oldFin,
                    $absence->id,
                );
                app(AjusterSoldeCongesAction::class)->restaurerPourAbsence($absence->fresh());
            } elseif ($wasApproved && $datesChanged && $newStatut === StatutAbsence::Approuvee) {
                $restaurees = $this->restaurerVacationsLiees($absence);
            }

            $absence->update([
                ...$data,
                'statut' => $newStatut->value,
                'type' => $newType->value,
            ]);

            $becomingApproved = $newStatut === StatutAbsence::Approuvee
                && $previousStatut !== StatutAbsence::Approuvee;

            if ($becomingApproved) {
                $marquees = MarquerVacationsARecouvrir::execute(
                    $absence->fresh(),
                    $agentId,
                    $debut,
                    $fin,
                );
                $this->syncAgentStatutOnApproval($agentId, $newType);
                app(AjusterSoldeCongesAction::class)->deduirePourAbsence($absence->fresh());
                app(CreateSisNotificationAction::class)->notifyRhUsers(
                    'absence_approuvee',
                    'Absence approuvée',
                    'Une absence a été approuvée.',
                    ['absence_id' => $absence->id],
                );
            } elseif ($newStatut === StatutAbsence::Approuvee) {
                if ($datesChanged) {
                    $marquees = MarquerVacationsARecouvrir::execute(
                        $absence->fresh(),
                        $agentId,
                        $debut,
                        $fin,
                    );
                }

                if ($newType !== $oldType) {
                    $this->restoreAgentStatutIfNeeded(
                        $agentId,
                        $oldType,
                        $debut,
                        $fin,
                        $absence->id,
                    );
                    $this->syncAgentStatutOnApproval($agentId, $newType);
                }
            }

            return new AbsenceOperationResult(
                $absence->fresh('agent'),
                $marquees,
                $restaurees,
            );
        });
    }

    public function restaurerVacationsLiees(Absence $absence): int
    {
        return Vacation::query()
            ->where('absence_id', $absence->id)
            ->where('statut', StatutVacation::ARecouvrir->value)
            ->update([
                'statut' => StatutVacation::Planifiee->value,
                'absence_id' => null,
            ]);
    }

    public function restoreAgentStatutIfNeeded(
        string $agentId,
        TypeAbsence $type,
        string $debut,
        string $fin,
        ?string $exceptAbsenceId,
    ): void {
        if (! in_array($type, [TypeAbsence::Conge, TypeAbsence::Maladie], true)) {
            return;
        }

        $hasOtherApproved = Absence::query()
            ->where('agent_id', $agentId)
            ->where('statut', StatutAbsence::Approuvee->value)
            ->when($exceptAbsenceId, fn ($q) => $q->where('id', '!=', $exceptAbsenceId))
            ->whereDate('date_debut', '<=', $fin)
            ->whereDate('date_fin', '>=', $debut)
            ->exists();

        if ($hasOtherApproved) {
            return;
        }

        $agent = Agent::query()->find($agentId);

        if (
            $agent
            && in_array($agent->statut, [StatutAgent::Conge, StatutAgent::Malade], true)
        ) {
            $agent->update(['statut' => StatutAgent::Disponible->value]);
        }
    }

    private function syncAgentStatutOnApproval(string $agentId, TypeAbsence $type): void
    {
        $statut = match ($type) {
            TypeAbsence::Conge => StatutAgent::Conge,
            TypeAbsence::Maladie => StatutAgent::Malade,
            default => null,
        };

        if ($statut === null) {
            return;
        }

        Agent::query()
            ->whereKey($agentId)
            ->update(['statut' => $statut->value]);
    }

    private function assertNoOverlap(
        string $agentId,
        string $debut,
        string $fin,
        ?string $exceptId = null,
    ): void {
        $exists = Absence::query()
            ->where('agent_id', $agentId)
            ->whereNotIn('statut', [
                StatutAbsence::Refusee->value,
                StatutAbsence::Annulee->value,
            ])
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->whereDate('date_debut', '<=', $fin)
            ->whereDate('date_fin', '>=', $debut)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'agent_id' => 'Cet agent a déjà une absence qui chevauche cette période.',
            ]);
        }
    }
}
