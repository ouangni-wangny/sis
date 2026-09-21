<?php

namespace App\Application\Operation;

use App\Application\Shared\AssertAgentAssignable;
use App\Application\Shared\RondierPerimetreGuard;
use App\Domain\Shared\Enums\GraviteAnomalie;
use App\Domain\Shared\Enums\ResultatControle;
use App\Domain\Shared\Enums\SourceAbsence;
use App\Domain\Shared\Enums\StatutAbsence;
use App\Domain\Shared\Enums\StatutAnomalie;
use App\Domain\Shared\Enums\StatutVacation;
use App\Domain\Shared\Enums\TypeAbsence;
use App\Domain\Shared\Enums\TypeAgent;
use App\Domain\Shared\Enums\TypeAnomalie;
use App\Domain\Shared\Exceptions\PresenceHorsZoneException;
use App\Models\Absence;
use App\Models\Agent;
use App\Models\Anomalie;
use App\Models\Controle;
use App\Models\Ronde;
use App\Models\Site;
use App\Models\Vacation;
use App\Support\Geo\Haversine;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class EnregistrerControleAction
{
    /**
     * Contrôle métier : un contrôleur (ou Opération sur site interne) vérifie
     * qu’un agent posté est bien à son poste.
     *
     * - agent_id                 = le contrôleur terrain (nullable pour siège Ops)
     * - enregistre_par_user_id   = utilisateur back-office qui saisit (siège)
     * - controle_agent_id        = l’agent contrôlé au poste
     *
     * @param  list<UploadedFile|null>  $photos
     * @param  list<string>  $base64Photos
     */
    public function execute(
        array $data,
        array $photos = [],
        bool $requirePhoto = true,
        array $base64Photos = [],
    ): Controle {
        return DB::transaction(function () use ($data, $photos, $requirePhoto, $base64Photos) {
            if (! empty($data['client_uuid'])) {
                $existing = Controle::query()->where('client_uuid', $data['client_uuid'])->first();
                if ($existing) {
                    return $existing->load(['media', 'site', 'agent', 'controleAgent', 'poste', 'enregistrePar']);
                }
            }

            $site = Site::query()->findOrFail($data['site_id']);
            $user = auth()->user();
            $isSiegeOp = SiegeControleAuthorization::canOperate($user, $site);

            if (empty($data['controle_agent_id'])) {
                throw ValidationException::withMessages([
                    'controle_agent_id' => 'Indiquez l’agent à contrôler à son poste.',
                ]);
            }

            if (
                ! empty($data['agent_id'])
                && $data['controle_agent_id'] === $data['agent_id']
            ) {
                throw ValidationException::withMessages([
                    'controle_agent_id' => 'Un contrôleur ne peut pas se contrôler lui-même.',
                ]);
            }

            $controleur = null;
            if ($isSiegeOp) {
                $data['agent_id'] = filled($data['agent_id'] ?? null)
                    ? (string) $data['agent_id']
                    : null;
                $data['enregistre_par_user_id'] = $user?->id;
            } else {
                $controleur = AssertAgentAssignable::execute(
                    (string) $data['agent_id'],
                    TypeAgent::Controleur,
                );
                RondierPerimetreGuard::assertSiteAllowed($controleur, $site->id);
            }

            $controleAgent = Agent::query()->findOrFail($data['controle_agent_id']);
            if ($controleAgent->type !== TypeAgent::Agent) {
                throw ValidationException::withMessages([
                    'controle_agent_id' => 'Seul un agent posté peut être contrôlé.',
                ]);
            }

            AssertAgentAssignable::posteBelongsToSite($data['poste_id'] ?? null, $site->id);
            $this->assertAgentEnPosteMaintenant(
                (string) $data['controle_agent_id'],
                $site->id,
                $data['poste_id'] ?? null,
            );
            $this->assertPasDeControleRecent((string) $data['controle_agent_id']);

            $uploaded = array_values(array_filter($photos, fn ($p) => $p instanceof UploadedFile));
            $base64 = array_values(array_filter($base64Photos, fn ($p) => is_string($p) && $p !== ''));

            if ($requirePhoto && $uploaded === [] && $base64 === []) {
                throw ValidationException::withMessages([
                    'photos' => 'Une photo de l’agent au poste est obligatoire.',
                ]);
            }

            if (! empty($data['ronde_id'])) {
                $ronde = Ronde::query()->findOrFail($data['ronde_id']);
                if ($ronde->site_id !== $site->id) {
                    throw ValidationException::withMessages([
                        'ronde_id' => 'La ronde sélectionnée n’appartient pas à ce site.',
                    ]);
                }
            }

            $siteHasGeo = $site->latitude !== null && $site->longitude !== null;
            if (
                ! $isSiegeOp
                && $siteHasGeo
                && (! isset($data['latitude']) || ! isset($data['longitude']))
            ) {
                throw ValidationException::withMessages([
                    'latitude' => 'GPS obligatoire pour valider la présence au poste.',
                ]);
            }

            if (
                ! $isSiegeOp
                && config('sis.controle_enforce_site_radius')
                && isset($data['latitude'], $data['longitude'])
                && $siteHasGeo
            ) {
                $radius = $site->rayon_metres ?: 200;
                $distance = Haversine::distanceMeters(
                    (float) $data['latitude'],
                    (float) $data['longitude'],
                    (float) $site->latitude,
                    (float) $site->longitude,
                );
                if ($distance > $radius) {
                    throw new PresenceHorsZoneException(
                        'Hors zone du site ('.(int) $distance.' m, max '.$radius.' m). Approchez-vous du poste pour valider.'
                    );
                }
            }

            $data['effectue_at'] = now();
            $controle = Controle::query()->create($data);

            foreach ($uploaded as $photo) {
                $controle->addMedia($photo)->toMediaCollection('photos');
            }

            foreach ($base64 as $index => $encoded) {
                $this->attachBase64Photo($controle, $encoded, $index + 1);
            }

            if ($controle->resultat === ResultatControle::Absent) {
                $this->signalerAbsence($controle, $controleur, $site, $controleAgent);
            }

            return $controle->load(['media', 'site', 'agent', 'controleAgent', 'poste', 'enregistrePar']);
        });
    }

    /**
     * Écrit la photo dans storage/app/tmp puis l’attache via Media Library.
     * Évite addMediaFromBase64 (tempnam système → erreur PHP sous Herd/local).
     */
    private function attachBase64Photo(Controle $controle, string $encoded, int $index): void
    {
        $payload = preg_replace('#^data:image/[\w.+-]+;base64,#i', '', trim($encoded)) ?? '';
        $binary = base64_decode($payload, true);
        if ($binary === false || $binary === '') {
            throw ValidationException::withMessages([
                'photo_base64' => 'Photo invalide.',
            ]);
        }

        $dir = storage_path('app/tmp');
        File::ensureDirectoryExists($dir);
        $path = $dir.'/controle-'.Str::uuid().'.jpg';
        File::put($path, $binary);

        try {
            $controle
                ->addMedia($path)
                ->usingFileName('controle-'.$index.'.jpg')
                ->toMediaCollection('photos');
        } finally {
            if (File::exists($path)) {
                File::delete($path);
            }
        }
    }

    private function assertAgentEnPosteMaintenant(
        string $controleAgentId,
        string $siteId,
        ?string $posteId,
    ): void {
        $today = now()->toDateString();

        $vacations = Vacation::query()
            ->where('agent_id', $controleAgentId)
            ->where('site_id', $siteId)
            ->when($posteId, fn ($q) => $q->where('poste_id', $posteId))
            ->whereIn('statut', [
                StatutVacation::Planifiee,
                StatutVacation::EnCours,
                StatutVacation::ARecouvrir,
            ])
            ->whereDate('date_debut', '<=', $today)
            ->where(function ($q) use ($today) {
                $q->whereNull('date_fin')->orWhereDate('date_fin', '>=', $today);
            })
            ->get();

        if ($vacations->isEmpty()) {
            throw ValidationException::withMessages([
                'controle_agent_id' => 'Cet agent n’a pas de vacation active aujourd’hui sur ce site/poste.',
            ]);
        }

        $tolerance = (int) config('sis.controle.heure_tolerance_minutes', 60);
        $now = now();
        $anchors = [$now->copy()->startOfDay(), $now->copy()->subDay()->startOfDay()];

        foreach ($vacations as $vacation) {
            foreach ($anchors as $anchor) {
                $anchorDate = $anchor->toDateString();
                if ($anchorDate < $vacation->date_debut->toDateString()) {
                    continue;
                }
                if ($vacation->date_fin && $anchorDate > $vacation->date_fin->toDateString()) {
                    continue;
                }
                [$start, $end] = $this->windowOnDate($anchor, $vacation->heure_debut, $vacation->heure_fin);
                if ($now->between($start->copy()->subMinutes($tolerance), $end->copy()->addMinutes($tolerance))) {
                    return;
                }
            }
        }

        throw ValidationException::withMessages([
            'controle_agent_id' => 'Cet agent n’est pas censé être en poste à cette heure (hors créneau de sa vacation).',
        ]);
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function windowOnDate(Carbon $date, string $heureDebut, string $heureFin): array
    {
        $start = $date->copy()->startOfDay()->setTimeFromTimeString($heureDebut);
        $end = $date->copy()->startOfDay()->setTimeFromTimeString($heureFin);
        if ($end->lte($start)) {
            $end = $end->copy()->addDay();
        }

        return [$start, $end];
    }

    private function assertPasDeControleRecent(string $controleAgentId): void
    {
        $minutes = (int) config('sis.controle.recheck_min_minutes', 30);

        $recent = Controle::query()
            ->where('controle_agent_id', $controleAgentId)
            ->where('effectue_at', '>=', now()->subMinutes($minutes))
            ->exists();

        if ($recent) {
            throw ValidationException::withMessages([
                'controle_agent_id' => "Cet agent a déjà été contrôlé il y a moins de {$minutes} minutes.",
            ]);
        }
    }

    private function signalerAbsence(
        Controle $controle,
        ?Agent $controleur,
        Site $site,
        Agent $controleAgent,
    ): void {
        $nomAgent = trim($controleAgent->prenom.' '.$controleAgent->nom) ?: 'agent posté';
        $sourceLabel = $controleur
            ? 'contrôle terrain'
            : 'contrôle siège (Opération)';

        Anomalie::query()->create([
            'signale_par_id' => $controleur?->id,
            'site_id' => $site->id,
            'type' => TypeAnomalie::AbsencePoste,
            'gravite' => GraviteAnomalie::Haute,
            'statut' => StatutAnomalie::Ouverte,
            'commentaire' => 'Absence constatée lors d’un '.$sourceLabel.' — agent '.$nomAgent.'.'
                .($controle->commentaire ? ' Note : '.$controle->commentaire : ''),
            'signale_at' => $controle->effectue_at,
        ]);

        $jour = $controle->effectue_at->toDateString();
        $motif = 'Absence constatée au '.$sourceLabel
            .($controle->commentaire ? ' — '.$controle->commentaire : '');

        $absence = Absence::query()
            ->where('agent_id', $controleAgent->id)
            ->where('source', SourceAbsence::Controle->value)
            ->whereNotIn('statut', [
                StatutAbsence::Refusee->value,
                StatutAbsence::Annulee->value,
            ])
            ->whereDate('date_debut', '<=', $jour)
            ->whereDate('date_fin', '>=', $jour)
            ->first();

        if ($absence === null) {
            $absence = Absence::query()->create([
                'agent_id' => $controleAgent->id,
                'type' => TypeAbsence::Autre->value,
                'source' => SourceAbsence::Controle->value,
                'controle_id' => $controle->id,
                'statut' => StatutAbsence::Approuvee->value,
                'date_debut' => $jour,
                'date_fin' => $jour,
                'motif' => $motif,
            ]);
        } else {
            $absence->update([
                'statut' => StatutAbsence::Approuvee->value,
                'controle_id' => $absence->controle_id ?: $controle->id,
                'motif' => $absence->motif ?: $motif,
            ]);
        }

        MarquerVacationsARecouvrir::execute(
            $absence->fresh(),
            $controleAgent->id,
            $jour,
            $jour,
        );
    }
}
