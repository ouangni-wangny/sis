<?php

$base = dirname(__DIR__);
function w(string $rel, string $c): void {
    global $base;
    $p = "$base/$rel";
    if (!is_dir(dirname($p))) mkdir(dirname($p), 0777, true);
    file_put_contents($p, $c);
    echo "OK $rel\n";
}

// Jobs
w('app/Jobs/GenererRapportJob.php', <<<'PHP'
<?php

namespace App\Jobs;

use App\Models\RapportExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

class GenererRapportJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $rapportId) {}

    public function handle(): void
    {
        $rapport = RapportExport::query()->findOrFail($this->rapportId);
        $rapport->update(['statut' => 'processing']);

        try {
            $html = view('pdf.rapport', [
                'rapport' => $rapport,
                'generatedAt' => now()->timezone('Africa/Abidjan')->format('d/m/Y H:i'),
            ])->render();

            $pdf = Pdf::loadHTML($html);
            $path = "rapports/{$rapport->id}.pdf";
            Storage::disk('media')->put($path, $pdf->output());

            $rapport->addMedia(Storage::disk('media')->path($path))
                ->toMediaCollection('export');

            $rapport->update(['statut' => 'done']);
        } catch (\Throwable $e) {
            $rapport->update(['statut' => 'failed', 'erreur' => $e->getMessage()]);
            throw $e;
        }
    }
}

PHP);

w('app/Jobs/GenererFacturePdfJob.php', <<<'PHP'
<?php

namespace App\Jobs;

use App\Models\Facture;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

class GenererFacturePdfJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $factureId) {}

    public function handle(): void
    {
        $facture = Facture::query()->with(['client', 'lignes'])->findOrFail($this->factureId);

        $html = view('pdf.facture', [
            'facture' => $facture,
            'generatedAt' => now()->timezone('Africa/Abidjan')->format('d/m/Y H:i'),
        ])->render();

        $pdf = Pdf::loadHTML($html);
        $path = "factures/{$facture->id}.pdf";
        Storage::disk('media')->put($path, $pdf->output());

        $facture->clearMediaCollection('pdf');
        $facture->addMedia(Storage::disk('media')->path($path))
            ->toMediaCollection('pdf');
    }
}

PHP);

w('app/Jobs/EnvoyerPushAnomalieJob.php', <<<'PHP'
<?php

namespace App\Jobs;

use App\Models\Anomalie;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class EnvoyerPushAnomalieJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $anomalieId) {}

    public function handle(): void
    {
        $anomalie = Anomalie::query()->with('site')->find($this->anomalieId);
        if (! $anomalie) {
            return;
        }

        // Stub FCM — log for Phase 1
        Log::info('Push anomalie', [
            'anomalie_id' => $anomalie->id,
            'gravite' => $anomalie->gravite,
            'site' => $anomalie->site?->nom,
        ]);
    }
}

PHP);

w('app/Jobs/AlerteExpirationDocumentsJob.php', <<<'PHP'
<?php

namespace App\Jobs;

use App\Models\Agent;
use App\Models\Contrat;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class AlerteExpirationDocumentsJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        $soon = now()->addDays(30)->toDateString();

        $agentsContrat = Agent::query()
            ->whereNotNull('date_expiration_contrat')
            ->whereDate('date_expiration_contrat', '<=', $soon)
            ->get(['id', 'matricule', 'nom', 'prenom', 'date_expiration_contrat']);

        $agentsPermis = Agent::query()
            ->whereNotNull('date_expiration_permis')
            ->whereDate('date_expiration_permis', '<=', $soon)
            ->get(['id', 'matricule', 'nom', 'prenom', 'date_expiration_permis']);

        $contrats = Contrat::query()
            ->whereNotNull('date_fin')
            ->whereDate('date_fin', '<=', $soon)
            ->get(['id', 'agent_id', 'reference', 'date_fin']);

        Log::info('Alertes expiration documents', [
            'agents_contrat' => $agentsContrat->count(),
            'agents_permis' => $agentsPermis->count(),
            'contrats' => $contrats->count(),
        ]);
    }
}

PHP);

w('resources/views/pdf/facture.blade.php', <<<'HTML'
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Facture {{ $facture->numero }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
        h1 { font-size: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ccc; padding: 6px; text-align: left; }
        .right { text-align: right; }
    </style>
</head>
<body>
    <h1>S.I.S — Facture {{ $facture->numero }}</h1>
    <p>Client : {{ $facture->client->raison_sociale }}</p>
    <p>Date d'émission : {{ $facture->date_emission?->format('d/m/Y') }}</p>
    <p>Devise : {{ $facture->devise }} (FCFA)</p>
    <table>
        <thead>
            <tr>
                <th>Description</th>
                <th>Qté</th>
                <th>P.U.</th>
                <th>Montant</th>
            </tr>
        </thead>
        <tbody>
            @foreach($facture->lignes as $ligne)
                <tr>
                    <td>{{ $ligne->description }}</td>
                    <td>{{ $ligne->quantite }}</td>
                    <td class="right">{{ number_format($ligne->prix_unitaire, 0, ',', ' ') }}</td>
                    <td class="right">{{ number_format($ligne->montant, 0, ',', ' ') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <p class="right"><strong>Total TTC : {{ number_format($facture->montant_ttc, 0, ',', ' ') }} {{ $facture->devise }}</strong></p>
    <p><small>Généré le {{ $generatedAt }} (Africa/Abidjan)</small></p>
</body>
</html>
HTML);

w('resources/views/pdf/rapport.blade.php', <<<'HTML'
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Rapport {{ $rapport->type }}</title>
    <style>body{font-family:DejaVu Sans,sans-serif;font-size:12px}</style>
</head>
<body>
    <h1>S.I.S — Rapport {{ strtoupper($rapport->type) }}</h1>
    <p>Format : {{ $rapport->format }}</p>
    <p>Filtres : {{ json_encode($rapport->filtres) }}</p>
    <p>Généré le {{ $generatedAt }}</p>
</body>
</html>
HTML);

// Simple Resources generator
$resources = [
    'User' => <<<'PHP'
        'id' => $this->id,
        'nom' => $this->nom,
        'prenom' => $this->prenom,
        'email' => $this->email,
        'matricule' => $this->matricule,
        'type' => $this->type,
        'statut' => $this->statut,
        'last_login_at' => $this->last_login_at,
        'roles' => $this->whenLoaded('roles', fn () => $this->getRoleNames()),
        'permissions' => $this->whenLoaded('permissions', fn () => $this->getAllPermissions()->pluck('name')),
        'created_at' => $this->created_at,
PHP,
    'Client' => <<<'PHP'
        'id' => $this->id,
        'type' => $this->type,
        'raison_sociale' => $this->raison_sociale,
        'nom_responsable' => $this->nom_responsable,
        'personne_contact' => $this->personne_contact,
        'telephone' => $this->telephone,
        'email' => $this->email,
        'adresse' => $this->adresse,
        'statut' => $this->statut,
        'sites_count' => $this->whenCounted('sites'),
        'created_at' => $this->created_at,
PHP,
    'Zone' => <<<'PHP'
        'id' => $this->id,
        'nom' => $this->nom,
        'description' => $this->description,
        'sites_count' => $this->whenCounted('sites'),
        'created_at' => $this->created_at,
PHP,
    'Grade' => <<<'PHP'
        'id' => $this->id,
        'libelle' => $this->libelle,
        'type_agent' => $this->type_agent,
        'description' => $this->description,
PHP,
    'Site' => <<<'PHP'
        'id' => $this->id,
        'nom' => $this->nom,
        'adresse' => $this->adresse,
        'responsable' => $this->responsable,
        'tarif_mensuel' => $this->tarif_mensuel,
        'latitude' => $this->latitude,
        'longitude' => $this->longitude,
        'rayon_metres' => $this->rayon_metres,
        'client_id' => $this->client_id,
        'zone_id' => $this->zone_id,
        'client' => new ClientResource($this->whenLoaded('client')),
        'zone' => new ZoneResource($this->whenLoaded('zone')),
        'postes' => PosteResource::collection($this->whenLoaded('postes')),
        'checkpoints' => CheckpointResource::collection($this->whenLoaded('checkpoints')),
PHP,
    'Poste' => <<<'PHP'
        'id' => $this->id,
        'site_id' => $this->site_id,
        'nom' => $this->nom,
        'agents_requis' => $this->agents_requis,
        'heure_debut' => $this->heure_debut,
        'heure_fin' => $this->heure_fin,
PHP,
    'Checkpoint' => <<<'PHP'
        'id' => $this->id,
        'site_id' => $this->site_id,
        'nom' => $this->nom,
        'code_qr' => $this->code_qr,
        'latitude' => $this->latitude,
        'longitude' => $this->longitude,
        'ordre' => $this->ordre,
PHP,
    'Agent' => <<<'PHP'
        'id' => $this->id,
        'user_id' => $this->user_id,
        'grade_id' => $this->grade_id,
        'type' => $this->type,
        'nom' => $this->nom,
        'prenom' => $this->prenom,
        'telephone' => $this->telephone,
        'matricule' => $this->matricule,
        'cnps' => $this->cnps,
        'date_embauche' => $this->date_embauche,
        'date_expiration_contrat' => $this->date_expiration_contrat,
        'date_expiration_permis' => $this->date_expiration_permis,
        'statut' => $this->statut,
        'plain_pin' => $this->when(isset($this->plain_pin), $this->plain_pin),
        'grade' => new GradeResource($this->whenLoaded('grade')),
        'photo' => $this->whenLoaded('media', fn () => [
            'url' => $this->getFirstMediaUrl('photo'),
            'thumb' => $this->getFirstMediaUrl('photo', 'thumb'),
        ]),
PHP,
    'Vacation' => <<<'PHP'
        'id' => $this->id,
        'agent_id' => $this->agent_id,
        'site_id' => $this->site_id,
        'poste_id' => $this->poste_id,
        'date_debut' => $this->date_debut,
        'date_fin' => $this->date_fin,
        'heure_debut' => $this->heure_debut,
        'heure_fin' => $this->heure_fin,
        'statut' => $this->statut,
        'agent' => new AgentResource($this->whenLoaded('agent')),
        'site' => new SiteResource($this->whenLoaded('site')),
        'poste' => new PosteResource($this->whenLoaded('poste')),
PHP,
    'Ronde' => <<<'PHP'
        'id' => $this->id,
        'agent_id' => $this->agent_id,
        'site_id' => $this->site_id,
        'vacation_id' => $this->vacation_id,
        'demarree_at' => $this->demarree_at,
        'terminee_at' => $this->terminee_at,
        'statut' => $this->statut,
        'progression' => $this->progression,
        'agent' => new AgentResource($this->whenLoaded('agent')),
        'site' => new SiteResource($this->whenLoaded('site')),
        'ronde_checkpoints' => RondeCheckpointResource::collection($this->whenLoaded('rondeCheckpoints')),
PHP,
    'RondeCheckpoint' => <<<'PHP'
        'id' => $this->id,
        'ronde_id' => $this->ronde_id,
        'checkpoint_id' => $this->checkpoint_id,
        'scanne_at' => $this->scanne_at,
        'latitude' => $this->latitude,
        'longitude' => $this->longitude,
        'valide' => $this->valide,
        'checkpoint' => new CheckpointResource($this->whenLoaded('checkpoint')),
PHP,
    'Controle' => <<<'PHP'
        'id' => $this->id,
        'client_uuid' => $this->client_uuid,
        'agent_id' => $this->agent_id,
        'site_id' => $this->site_id,
        'poste_id' => $this->poste_id,
        'ronde_id' => $this->ronde_id,
        'effectue_at' => $this->effectue_at,
        'latitude' => $this->latitude,
        'longitude' => $this->longitude,
        'commentaire' => $this->commentaire,
        'photos' => $this->whenLoaded('media', fn () => $this->getMedia('photos')->map(fn ($m) => [
            'url' => $m->getUrl(),
            'thumb' => $m->getUrl('thumb'),
        ])),
PHP,
    'Anomalie' => <<<'PHP'
        'id' => $this->id,
        'client_uuid' => $this->client_uuid,
        'signale_par_id' => $this->signale_par_id,
        'assigne_a_id' => $this->assigne_a_id,
        'site_id' => $this->site_id,
        'type' => $this->type,
        'gravite' => $this->gravite,
        'statut' => $this->statut,
        'commentaire' => $this->commentaire,
        'signale_at' => $this->signale_at,
        'resolue_at' => $this->resolue_at,
        'photos' => $this->whenLoaded('media', fn () => $this->getMedia('photos')->map(fn ($m) => [
            'url' => $m->getUrl(),
        ])),
PHP,
    'Contrat' => <<<'PHP'
        'id' => $this->id,
        'agent_id' => $this->agent_id,
        'type' => $this->type,
        'reference' => $this->reference,
        'date_debut' => $this->date_debut,
        'date_fin' => $this->date_fin,
        'salaire' => $this->salaire,
        'statut' => $this->statut,
PHP,
    'Absence' => <<<'PHP'
        'id' => $this->id,
        'agent_id' => $this->agent_id,
        'date_debut' => $this->date_debut,
        'date_fin' => $this->date_fin,
        'motif' => $this->motif,
        'statut' => $this->statut,
PHP,
    'Offre' => <<<'PHP'
        'id' => $this->id,
        'libelle' => $this->libelle,
        'description' => $this->description,
        'prix_mensuel' => $this->prix_mensuel,
        'actif' => $this->actif,
PHP,
    'Abonnement' => <<<'PHP'
        'id' => $this->id,
        'client_id' => $this->client_id,
        'offre_id' => $this->offre_id,
        'site_id' => $this->site_id,
        'date_debut' => $this->date_debut,
        'date_fin' => $this->date_fin,
        'statut' => $this->statut,
PHP,
    'Facture' => <<<'PHP'
        'id' => $this->id,
        'client_id' => $this->client_id,
        'numero' => $this->numero,
        'date_emission' => $this->date_emission,
        'date_echeance' => $this->date_echeance,
        'montant_ht' => $this->montant_ht,
        'montant_tva' => $this->montant_tva,
        'montant_ttc' => $this->montant_ttc,
        'devise' => $this->devise,
        'statut' => $this->statut,
        'lignes' => LigneFactureResource::collection($this->whenLoaded('lignes')),
        'pdf' => $this->whenLoaded('media', fn () => ['url' => $this->getFirstMediaUrl('pdf')]),
PHP,
    'LigneFacture' => <<<'PHP'
        'id' => $this->id,
        'description' => $this->description,
        'quantite' => $this->quantite,
        'prix_unitaire' => $this->prix_unitaire,
        'montant' => $this->montant,
PHP,
    'JournalAudit' => <<<'PHP'
        'id' => $this->id,
        'user_id' => $this->user_id,
        'action' => $this->action,
        'auditable_type' => $this->auditable_type,
        'auditable_id' => $this->auditable_id,
        'ancien' => $this->ancien,
        'nouveau' => $this->nouveau,
        'ip' => $this->ip,
        'created_at' => $this->created_at,
PHP,
    'RapportExport' => <<<'PHP'
        'id' => $this->id,
        'type' => $this->type,
        'format' => $this->format,
        'statut' => $this->statut,
        'filtres' => $this->filtres,
        'erreur' => $this->erreur,
        'export' => $this->whenLoaded('media', fn () => ['url' => $this->getFirstMediaUrl('export')]),
        'created_at' => $this->created_at,
PHP,
    'RondierPerimetre' => <<<'PHP'
        'id' => $this->id,
        'agent_id' => $this->agent_id,
        'zone_id' => $this->zone_id,
        'site_id' => $this->site_id,
        'zone' => new ZoneResource($this->whenLoaded('zone')),
        'site' => new SiteResource($this->whenLoaded('site')),
PHP,
];

foreach ($resources as $name => $fields) {
    $uses = "use Illuminate\\Http\\Request;\nuse Illuminate\\Http\\Resources\\Json\\JsonResource;\n";
    if (str_contains($fields, 'ClientResource')) $uses .= "use App\\Http\\Resources\\ClientResource;\n";
    if (str_contains($fields, 'ZoneResource')) $uses .= "use App\\Http\\Resources\\ZoneResource;\n";
    if (str_contains($fields, 'PosteResource')) $uses .= "use App\\Http\\Resources\\PosteResource;\n";
    if (str_contains($fields, 'CheckpointResource')) $uses .= "use App\\Http\\Resources\\CheckpointResource;\n";
    if (str_contains($fields, 'GradeResource')) $uses .= "use App\\Http\\Resources\\GradeResource;\n";
    if (str_contains($fields, 'AgentResource')) $uses .= "use App\\Http\\Resources\\AgentResource;\n";
    if (str_contains($fields, 'SiteResource')) $uses .= "use App\\Http\\Resources\\SiteResource;\n";
    if (str_contains($fields, 'RondeCheckpointResource')) $uses .= "use App\\Http\\Resources\\RondeCheckpointResource;\n";
    if (str_contains($fields, 'LigneFactureResource')) $uses .= "use App\\Http\\Resources\\LigneFactureResource;\n";

    w("app/Http/Resources/{$name}Resource.php", <<<PHP
<?php

namespace App\\Http\\Resources;

{$uses}
class {$name}Resource extends JsonResource
{
    public function toArray(Request \$request): array
    {
        return [
{$fields}
        ];
    }
}

PHP);
}

echo "Jobs+Resources done\n";
