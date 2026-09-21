<?php

$base = dirname(__DIR__);
function w(string $rel, string $c): void {
    global $base;
    $p = "$base/$rel";
    if (!is_dir(dirname($p))) mkdir(dirname($p), 0777, true);
    file_put_contents($p, $c);
    echo "OK $rel\n";
}

// ─── ACTIONS ─────────────────────────────────────────────────────────

w('app/Application/Identity/LoginWebAction.php', <<<'PHP'
<?php

namespace App\Application\Identity;

use App\Domain\Shared\Enums\StatutUser;
use App\Domain\Shared\Enums\TypeUser;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class LoginWebAction
{
    public function execute(string $email, string $password, ?string $deviceName = 'web'): array
    {
        $user = User::query()
            ->where('email', $email)
            ->where('type', TypeUser::Backoffice)
            ->first();

        if (! $user || ! $user->password || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Identifiants invalides.'],
            ]);
        }

        if ($user->statut !== StatutUser::Actif) {
            throw ValidationException::withMessages([
                'email' => ['Compte inactif.'],
            ]);
        }

        $user->forceFill(['last_login_at' => now()])->save();
        $token = $user->createToken($deviceName, ['backoffice'])->plainTextToken;

        return compact('user', 'token');
    }
}

PHP);

w('app/Application/Identity/LoginMobileAction.php', <<<'PHP'
<?php

namespace App\Application\Identity;

use App\Domain\Shared\Enums\StatutUser;
use App\Domain\Shared\Enums\TypeUser;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class LoginMobileAction
{
    public function execute(string $matricule, string $pin, ?string $deviceName = 'mobile'): array
    {
        $user = User::query()
            ->where('matricule', $matricule)
            ->where('type', TypeUser::Mobile)
            ->first();

        if (! $user || ! $user->pin_hash || ! Hash::check($pin, $user->pin_hash)) {
            throw ValidationException::withMessages([
                'matricule' => ['Matricule ou PIN invalide.'],
            ]);
        }

        if ($user->statut !== StatutUser::Actif) {
            throw ValidationException::withMessages([
                'matricule' => ['Compte inactif.'],
            ]);
        }

        $user->forceFill(['last_login_at' => now()])->save();
        $token = $user->createToken($deviceName, ['mobile'])->plainTextToken;

        return [
            'user' => $user->load('agent'),
            'token' => $token,
        ];
    }
}

PHP);

w('app/Application/Identity/RevokeDeviceTokenAction.php', <<<'PHP'
<?php

namespace App\Application\Identity;

use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

final class RevokeDeviceTokenAction
{
    public function execute(User $user, ?string $tokenId = null): void
    {
        if ($tokenId) {
            $user->tokens()->where('id', $tokenId)->delete();

            return;
        }

        /** @var PersonalAccessToken|null $current */
        $current = $user->currentAccessToken();
        $current?->delete();
    }
}

PHP);

w('app/Application/Referentiel/CreateClientAction.php', <<<'PHP'
<?php

namespace App\Application\Referentiel;

use App\Models\Client;
use Illuminate\Support\Facades\DB;

final class CreateClientAction
{
    public function execute(array $data): Client
    {
        return DB::transaction(fn () => Client::query()->create($data));
    }
}

PHP);

w('app/Application/Referentiel/UpdateClientAction.php', <<<'PHP'
<?php

namespace App\Application\Referentiel;

use App\Models\Client;
use Illuminate\Support\Facades\DB;

final class UpdateClientAction
{
    public function execute(Client $client, array $data): Client
    {
        return DB::transaction(function () use ($client, $data) {
            $client->update($data);

            return $client->refresh();
        });
    }
}

PHP);

w('app/Application/Site/CreateSiteWithPostesAction.php', <<<'PHP'
<?php

namespace App\Application\Site;

use App\Models\Site;
use Illuminate\Support\Facades\DB;

final class CreateSiteWithPostesAction
{
    public function execute(array $data, array $postes = []): Site
    {
        return DB::transaction(function () use ($data, $postes) {
            $site = Site::query()->create($data);

            foreach ($postes as $poste) {
                $site->postes()->create($poste);
            }

            return $site->load(['postes', 'client', 'zone']);
        });
    }
}

PHP);

w('app/Application/Agent/CreateAgentWithMobileAccountAction.php', <<<'PHP'
<?php

namespace App\Application\Agent;

use App\Domain\Shared\Enums\StatutUser;
use App\Domain\Shared\Enums\TypeUser;
use App\Models\Agent;
use App\Models\Grade;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class CreateAgentWithMobileAccountAction
{
    public function execute(array $data): Agent
    {
        return DB::transaction(function () use ($data) {
            $grade = Grade::query()->findOrFail($data['grade_id']);
            $pin = $data['pin'] ?? (string) random_int(1000, 9999);
            unset($data['pin']);

            $user = User::query()->create([
                'nom' => $data['nom'],
                'prenom' => $data['prenom'],
                'name' => trim($data['prenom'].' '.$data['nom']),
                'matricule' => $data['matricule'],
                'pin_hash' => Hash::make($pin),
                'type' => TypeUser::Mobile,
                'statut' => StatutUser::Actif,
                'email' => $data['email'] ?? null,
                'password' => null,
            ]);

            $role = $grade->type_agent->value;
            $user->assignRole($role);

            $agent = Agent::query()->create([
                ...$data,
                'user_id' => $user->id,
                'type' => $grade->type_agent->value,
            ]);

            $agent->setAttribute('plain_pin', $pin);
            $agent->load(['grade', 'user', 'media']);

            return $agent;
        });
    }
}

PHP);

w('app/Application/Agent/UploadAgentPhotoAction.php', <<<'PHP'
<?php

namespace App\Application\Agent;

use App\Models\Agent;
use Illuminate\Http\UploadedFile;

final class UploadAgentPhotoAction
{
    public function execute(Agent $agent, UploadedFile $file): Agent
    {
        $agent->addMedia($file)->toMediaCollection('photo');

        return $agent->load('media');
    }
}

PHP);

w('app/Application/Agent/SyncRondierPerimetreAction.php', <<<'PHP'
<?php

namespace App\Application\Agent;

use App\Models\Agent;
use Illuminate\Support\Facades\DB;

final class SyncRondierPerimetreAction
{
    /**
     * @param  array<int, array{zone_id?: ?string, site_id?: ?string}>  $items
     */
    public function execute(Agent $agent, array $items): Agent
    {
        return DB::transaction(function () use ($agent, $items) {
            $agent->perimetres()->delete();

            foreach ($items as $item) {
                if (empty($item['zone_id']) && empty($item['site_id'])) {
                    continue;
                }
                $agent->perimetres()->create([
                    'zone_id' => $item['zone_id'] ?? null,
                    'site_id' => $item['site_id'] ?? null,
                ]);
            }

            return $agent->load(['perimetres.zone', 'perimetres.site']);
        });
    }
}

PHP);

w('app/Application/Operation/DetecterConflitsVacationAction.php', <<<'PHP'
<?php

namespace App\Application\Operation;

use App\Models\Vacation;
use Carbon\Carbon;
use Illuminate\Support\Collection;

final class DetecterConflitsVacationAction
{
    public function execute(
        string $agentId,
        string $dateDebut,
        string $heureDebut,
        string $heureFin,
        ?string $dateFin = null,
        ?string $excludeId = null,
    ): Collection {
        $start = Carbon::parse("{$dateDebut} {$heureDebut}");
        $endDate = $dateFin ?? $dateDebut;
        $end = Carbon::parse("{$endDate} {$heureFin}");

        return Vacation::query()
            ->where('agent_id', $agentId)
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->whereNotIn('statut', ['annulee'])
            ->get()
            ->filter(function (Vacation $v) use ($start, $end) {
                $vStart = Carbon::parse($v->date_debut->format('Y-m-d').' '.$v->heure_debut);
                $vEndDate = ($v->date_fin ?? $v->date_debut)->format('Y-m-d');
                $vEnd = Carbon::parse($vEndDate.' '.$v->heure_fin);

                return $start->lt($vEnd) && $end->gt($vStart);
            })
            ->values();
    }
}

PHP);

w('app/Application/Operation/CreateVacationAction.php', <<<'PHP'
<?php

namespace App\Application\Operation;

use App\Domain\Shared\Exceptions\VacationConflitException;
use App\Models\Vacation;
use Illuminate\Support\Facades\DB;

final class CreateVacationAction
{
    public function __construct(private DetecterConflitsVacationAction $detecter) {}

    public function execute(array $data): Vacation
    {
        return DB::transaction(function () use ($data) {
            $conflits = $this->detecter->execute(
                $data['agent_id'],
                $data['date_debut'],
                $data['heure_debut'],
                $data['heure_fin'],
                $data['date_fin'] ?? null,
            );

            if ($conflits->isNotEmpty()) {
                throw new VacationConflitException(
                    'Conflit de vacation : '.$conflits->count().' créneau(x) en chevauchement.'
                );
            }

            return Vacation::query()->create($data)->load(['agent', 'site', 'poste']);
        });
    }
}

PHP);

w('app/Application/Operation/DemarrerRondeAction.php', <<<'PHP'
<?php

namespace App\Application\Operation;

use App\Domain\Shared\Enums\StatutRonde;
use App\Domain\Shared\Exceptions\RondeDejaDemarreeException;
use App\Models\Ronde;
use Illuminate\Support\Facades\DB;

final class DemarrerRondeAction
{
    public function execute(Ronde $ronde): Ronde
    {
        return DB::transaction(function () use ($ronde) {
            if ($ronde->statut === StatutRonde::EnCours) {
                throw new RondeDejaDemarreeException;
            }

            $exists = Ronde::query()
                ->where('agent_id', $ronde->agent_id)
                ->where('statut', StatutRonde::EnCours)
                ->where('id', '!=', $ronde->id)
                ->exists();

            if ($exists) {
                throw new RondeDejaDemarreeException('Une autre ronde est déjà en cours pour cet agent.');
            }

            $ronde->update([
                'statut' => StatutRonde::EnCours,
                'demarree_at' => now(),
                'progression' => 0,
            ]);

            $checkpoints = $ronde->site->checkpoints()->orderBy('ordre')->get();
            foreach ($checkpoints as $cp) {
                $ronde->rondeCheckpoints()->firstOrCreate(
                    ['checkpoint_id' => $cp->id],
                    ['valide' => false]
                );
            }

            return $ronde->fresh(['rondeCheckpoints.checkpoint', 'agent', 'site']);
        });
    }
}

PHP);

w('app/Application/Operation/ScannerCheckpointAction.php', <<<'PHP'
<?php

namespace App\Application\Operation;

use App\Domain\Shared\Enums\StatutRonde;
use App\Domain\Shared\Exceptions\DomainException;
use App\Models\Checkpoint;
use App\Models\Ronde;
use App\Support\Geo\Haversine;
use Illuminate\Support\Facades\DB;

final class ScannerCheckpointAction
{
    public function execute(
        Ronde $ronde,
        string $checkpointId,
        ?float $latitude = null,
        ?float $longitude = null,
        ?string $codeQr = null,
    ): Ronde {
        return DB::transaction(function () use ($ronde, $checkpointId, $latitude, $longitude, $codeQr) {
            if ($ronde->statut !== StatutRonde::EnCours) {
                throw new DomainException('La ronde doit être en cours pour scanner.');
            }

            $checkpoint = Checkpoint::query()->findOrFail($checkpointId);
            if ($checkpoint->site_id !== $ronde->site_id) {
                throw new DomainException('Checkpoint hors site de la ronde.');
            }

            if ($codeQr && $checkpoint->code_qr && $checkpoint->code_qr !== $codeQr) {
                throw new DomainException('Code QR invalide.');
            }

            $valide = true;
            if ($latitude !== null && $longitude !== null && $checkpoint->latitude && $checkpoint->longitude) {
                $valide = Haversine::isWithinRadius(
                    $latitude, $longitude,
                    (float) $checkpoint->latitude, (float) $checkpoint->longitude,
                    200
                );
            }

            $rc = $ronde->rondeCheckpoints()->firstOrCreate(['checkpoint_id' => $checkpoint->id]);
            $rc->update([
                'scanne_at' => now(),
                'latitude' => $latitude,
                'longitude' => $longitude,
                'valide' => $valide,
            ]);

            $total = $ronde->rondeCheckpoints()->count();
            $scanned = $ronde->rondeCheckpoints()->whereNotNull('scanne_at')->count();
            $progression = $total > 0 ? (int) round(($scanned / $total) * 100) : 0;
            $ronde->update(['progression' => $progression]);

            return $ronde->fresh(['rondeCheckpoints.checkpoint']);
        });
    }
}

PHP);

w('app/Application/Operation/TerminerRondeAction.php', <<<'PHP'
<?php

namespace App\Application\Operation;

use App\Domain\Shared\Enums\StatutRonde;
use App\Domain\Shared\Exceptions\DomainException;
use App\Models\Ronde;
use Illuminate\Support\Facades\DB;

final class TerminerRondeAction
{
    public function execute(Ronde $ronde): Ronde
    {
        return DB::transaction(function () use ($ronde) {
            if ($ronde->statut !== StatutRonde::EnCours) {
                throw new DomainException('Seule une ronde en cours peut être terminée.');
            }

            $ronde->update([
                'statut' => StatutRonde::Terminee,
                'terminee_at' => now(),
                'progression' => 100,
            ]);

            return $ronde->fresh(['rondeCheckpoints.checkpoint', 'agent', 'site']);
        });
    }
}

PHP);

w('app/Application/Operation/EnregistrerControleAction.php', <<<'PHP'
<?php

namespace App\Application\Operation;

use App\Domain\Shared\Exceptions\PresenceHorsZoneException;
use App\Models\Controle;
use App\Models\Site;
use App\Support\Geo\Haversine;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

final class EnregistrerControleAction
{
    public function execute(array $data, array $photos = []): Controle
    {
        return DB::transaction(function () use ($data, $photos) {
            if (! empty($data['client_uuid'])) {
                $existing = Controle::query()->where('client_uuid', $data['client_uuid'])->first();
                if ($existing) {
                    return $existing->load('media');
                }
            }

            $site = Site::query()->findOrFail($data['site_id']);

            if (isset($data['latitude'], $data['longitude']) && $site->latitude && $site->longitude) {
                $radius = $site->rayon_metres ?: 200;
                if (! Haversine::isWithinRadius(
                    (float) $data['latitude'],
                    (float) $data['longitude'],
                    (float) $site->latitude,
                    (float) $site->longitude,
                    $radius
                )) {
                    throw new PresenceHorsZoneException;
                }
            }

            $data['effectue_at'] = $data['effectue_at'] ?? now();
            $controle = Controle::query()->create($data);

            foreach ($photos as $photo) {
                if ($photo instanceof UploadedFile) {
                    $controle->addMedia($photo)->toMediaCollection('photos');
                }
            }

            return $controle->load(['media', 'site', 'agent']);
        });
    }
}

PHP);

w('app/Application/Operation/SignalerAnomalieAction.php', <<<'PHP'
<?php

namespace App\Application\Operation;

use App\Domain\Shared\Enums\GraviteAnomalie;
use App\Jobs\EnvoyerPushAnomalieJob;
use App\Models\Anomalie;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

final class SignalerAnomalieAction
{
    public function execute(array $data, array $photos = []): Anomalie
    {
        return DB::transaction(function () use ($data, $photos) {
            if (! empty($data['client_uuid'])) {
                $existing = Anomalie::query()->where('client_uuid', $data['client_uuid'])->first();
                if ($existing) {
                    return $existing->load('media');
                }
            }

            $data['signale_at'] = $data['signale_at'] ?? now();
            $data['statut'] = $data['statut'] ?? 'ouverte';
            $anomalie = Anomalie::query()->create($data);

            foreach ($photos as $photo) {
                if ($photo instanceof UploadedFile) {
                    $anomalie->addMedia($photo)->toMediaCollection('photos');
                }
            }

            $gravite = $anomalie->gravite;
            if (in_array($gravite, [GraviteAnomalie::Haute, GraviteAnomalie::Critique], true)) {
                EnvoyerPushAnomalieJob::dispatch($anomalie->id);
            }

            return $anomalie->load(['media', 'site', 'signalePar']);
        });
    }
}

PHP);

w('app/Application/Operation/UpdateAnomalieStatutAction.php', <<<'PHP'
<?php

namespace App\Application\Operation;

use App\Domain\Shared\Enums\StatutAnomalie;
use App\Domain\Shared\Exceptions\TransitionAnomalieInvalideException;
use App\Models\Anomalie;
use Illuminate\Support\Facades\DB;

final class UpdateAnomalieStatutAction
{
    private const TRANSITIONS = [
        'ouverte' => ['en_cours'],
        'en_cours' => ['resolue'],
        'resolue' => [],
    ];

    public function execute(Anomalie $anomalie, string $statut, ?string $assigneAId = null): Anomalie
    {
        return DB::transaction(function () use ($anomalie, $statut, $assigneAId) {
            $from = $anomalie->statut->value;
            $allowed = self::TRANSITIONS[$from] ?? [];

            if (! in_array($statut, $allowed, true)) {
                throw new TransitionAnomalieInvalideException(
                    "Transition invalide : {$from} → {$statut}."
                );
            }

            $payload = ['statut' => $statut];
            if ($assigneAId) {
                $payload['assigne_a_id'] = $assigneAId;
            }
            if ($statut === StatutAnomalie::Resolue->value) {
                $payload['resolue_at'] = now();
            }

            $anomalie->update($payload);

            return $anomalie->fresh(['assigneA', 'site', 'signalePar']);
        });
    }
}

PHP);

w('app/Application/Commercial/GenererFactureDepuisVacationsAction.php', <<<'PHP'
<?php

namespace App\Application\Commercial;

use App\Domain\Shared\Enums\StatutFacture;
use App\Jobs\GenererFacturePdfJob;
use App\Models\Client;
use App\Models\Facture;
use App\Models\Vacation;
use Illuminate\Support\Facades\DB;

final class GenererFactureDepuisVacationsAction
{
    public function execute(string $clientId, string $dateDebut, string $dateFin): Facture
    {
        return DB::transaction(function () use ($clientId, $dateDebut, $dateFin) {
            $client = Client::query()->findOrFail($clientId);

            $vacations = Vacation::query()
                ->whereHas('site', fn ($q) => $q->where('client_id', $clientId))
                ->whereBetween('date_debut', [$dateDebut, $dateFin])
                ->whereNotIn('statut', ['annulee'])
                ->with('site')
                ->get();

            $numero = 'FAC-'.now()->format('Ymd').'-'.strtoupper(substr(uniqid(), -5));

            $lignes = [];
            $montantHt = 0;

            foreach ($vacations->groupBy('site_id') as $siteVacations) {
                $site = $siteVacations->first()->site;
                $tarif = (float) ($site->tarif_mensuel ?? 0);
                $count = $siteVacations->count();
                $montant = $tarif > 0 ? $tarif : ($count * 5000);
                $lignes[] = [
                    'description' => "Prestations site {$site->nom} ({$count} vacation(s))",
                    'quantite' => $count,
                    'prix_unitaire' => $count > 0 ? round($montant / $count, 2) : 0,
                    'montant' => $montant,
                ];
                $montantHt += $montant;
            }

            if (empty($lignes)) {
                $lignes[] = [
                    'description' => 'Prestation sécurité (période sans vacation)',
                    'quantite' => 1,
                    'prix_unitaire' => 0,
                    'montant' => 0,
                ];
            }

            $tva = 0; // XOF / CI: oft exempt for security services in this MVP
            $ttc = $montantHt + $tva;

            $facture = Facture::query()->create([
                'client_id' => $client->id,
                'numero' => $numero,
                'date_emission' => now()->toDateString(),
                'date_echeance' => now()->addDays(30)->toDateString(),
                'montant_ht' => $montantHt,
                'montant_tva' => $tva,
                'montant_ttc' => $ttc,
                'devise' => 'XOF',
                'statut' => StatutFacture::Emise,
            ]);

            foreach ($lignes as $ligne) {
                $facture->lignes()->create($ligne);
            }

            GenererFacturePdfJob::dispatch($facture->id);

            return $facture->load(['lignes', 'client', 'media']);
        });
    }
}

PHP);

w('app/Application/Reporting/EnqueueRapportAction.php', <<<'PHP'
<?php

namespace App\Application\Reporting;

use App\Jobs\GenererRapportJob;
use App\Models\RapportExport;
use App\Models\User;

final class EnqueueRapportAction
{
    public function execute(User $user, string $type, string $format = 'pdf', array $filtres = []): RapportExport
    {
        $rapport = RapportExport::query()->create([
            'user_id' => $user->id,
            'type' => $type,
            'format' => $format,
            'statut' => 'pending',
            'filtres' => $filtres,
        ]);

        GenererRapportJob::dispatch($rapport->id);

        return $rapport;
    }
}

PHP);

w('app/Application/Reporting/GetDashboardStatsAction.php', <<<'PHP'
<?php

namespace App\Application\Reporting;

use App\Domain\Shared\Enums\StatutAgent;
use App\Domain\Shared\Enums\StatutAnomalie;
use App\Domain\Shared\Enums\StatutRonde;
use App\Domain\Shared\Enums\StatutVacation;
use App\Models\Agent;
use App\Models\Anomalie;
use App\Models\Controle;
use App\Models\Ronde;
use App\Models\Site;
use App\Models\Vacation;
use Illuminate\Support\Carbon;

final class GetDashboardStatsAction
{
    public function execute(?string $from = null, ?string $to = null, ?string $zoneId = null, ?string $clientId = null): array
    {
        $from = $from ? Carbon::parse($from)->startOfDay() : now()->startOfDay();
        $to = $to ? Carbon::parse($to)->endOfDay() : now()->endOfDay();

        $sitesQuery = Site::query()
            ->when($zoneId, fn ($q) => $q->where('zone_id', $zoneId))
            ->when($clientId, fn ($q) => $q->where('client_id', $clientId));

        $siteIds = (clone $sitesQuery)->pluck('id');

        $controles7j = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = now()->subDays($i)->toDateString();
            $controles7j[] = [
                'date' => $day,
                'count' => Controle::query()
                    ->whereDate('effectue_at', $day)
                    ->when($siteIds->isNotEmpty() || $zoneId || $clientId, fn ($q) => $q->whereIn('site_id', $siteIds))
                    ->count(),
            ];
        }

        $agentsParStatut = [];
        foreach (StatutAgent::cases() as $statut) {
            $agentsParStatut[$statut->value] = Agent::query()->where('statut', $statut)->count();
        }

        $anomaliesParType = Anomalie::query()
            ->when($siteIds->isNotEmpty() || $zoneId || $clientId, fn ($q) => $q->whereIn('site_id', $siteIds))
            ->where('signale_at', '>=', now()->subDays(30))
            ->selectRaw('type, count(*) as total')
            ->groupBy('type')
            ->pluck('total', 'type')
            ->all();

        return [
            'agents_disponibles' => Agent::query()->where('statut', StatutAgent::Disponible)->count(),
            'agents_actifs' => Agent::query()->whereIn('statut', [StatutAgent::Disponible, StatutAgent::EnMission])->count(),
            'vacations_actives' => Vacation::query()
                ->whereIn('statut', [StatutVacation::Planifiee, StatutVacation::EnCours])
                ->when($siteIds->isNotEmpty() || $zoneId || $clientId, fn ($q) => $q->whereIn('site_id', $siteIds))
                ->count(),
            'rondes_du_jour' => Ronde::query()
                ->whereDate('created_at', today())
                ->when($siteIds->isNotEmpty() || $zoneId || $clientId, fn ($q) => $q->whereIn('site_id', $siteIds))
                ->count(),
            'controles_du_jour' => Controle::query()
                ->whereBetween('effectue_at', [$from, $to])
                ->when($siteIds->isNotEmpty() || $zoneId || $clientId, fn ($q) => $q->whereIn('site_id', $siteIds))
                ->count(),
            'anomalies_ouvertes' => Anomalie::query()
                ->where('statut', '!=', StatutAnomalie::Resolue)
                ->when($siteIds->isNotEmpty() || $zoneId || $clientId, fn ($q) => $q->whereIn('site_id', $siteIds))
                ->count(),
            'sites_surveilles' => $sitesQuery->count(),
            'controles_7j' => $controles7j,
            'agents_par_statut' => $agentsParStatut,
            'anomalies_par_type' => $anomaliesParType,
        ];
    }
}

PHP);

echo "Actions done\n";
